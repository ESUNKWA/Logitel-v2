<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Recettes »: payments over a period, who cashed them and who gave the room.
 */
class RevenueTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private User $manager;

    private User $awa;

    private User $koffi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 10:00:00'); // a Wednesday
        $this->type = RoomType::factory()->create();
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->awa = User::factory()->reception()->create(['name' => 'Awa Réception']);
        $this->koffi = User::factory()->reception()->create(['name' => 'Koffi Réception']);
    }

    /**
     * A stay given by $assigner, paid $amount by $cashier at $paidAt.
     */
    private function stay(User $assigner, User $cashier, int $amount, string $paidAt, string $method = 'wave', string $name = 'Client'): Reservation
    {
        $this->travelTo($paidAt);
        $this->actingAs($assigner);
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['guest_id' => Guest::factory()->create(['first_name' => $name, 'last_name' => 'Test'])->id,
            'room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 1000, 'check_in' => now()->subHour(), 'duration' => $amount / 1000,
            'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        $this->actingAs($cashier);
        $stay->invoice->settle([['method' => $method, 'amount' => $amount] + ($method === 'especes' ? ['received' => $amount] : [])], now());
        $this->travelTo('2026-10-14 10:00:00');

        return $stay;
    }

    public function test_lines_show_who_cashed_and_who_gave_the_room(): void
    {
        $stay = $this->stay($this->awa, $this->koffi, 20000, '2026-10-14 08:00', 'especes', 'Moussa');

        $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk()
            // PC: every column, as before.
            ->assertSeeInOrder(['<th>Date</th>', 'Dossier', 'Client', 'Mode', 'Montant', 'Reçu / rendu', 'Encaissé par', 'Affecté par'], false)
            ->assertSeeInOrder([$stay->reference(), 'Moussa Test', 'Espèces', '20 000 F', 'Koffi Réception', 'Awa Réception'])
            // Phones: those columns are dropped (cell-hide-sm), Formule + « Détails » (cell-show-sm) open the popup's detail.
            ->assertSee('<th class="cell-hide-sm">Client</th>', false)
            ->assertSee('<th class="cell-show-sm">Formule</th>', false)
            ->assertSeeInOrder(['data-line-detail', 'Détails', 'Dossier', $stay->reference(), 'Chambre', 'Paiement', 'Espèces',
                'Client', 'Moussa Test', 'Reçu / rendu', 'Encaissé par', 'Koffi Réception', 'Affecté par', 'Awa Réception'])
            ->assertSee('id="revenueLineModal"', false)
            ->assertSeeInOrder(['Total encaissé', '20 000']);
    }

    public function test_a_booking_shows_who_recorded_the_arrival_or_that_the_guest_hasnt_come(): void
    {
        $this->actingAs($this->awa);
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-16 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $booking->invoice->settle([['method' => 'wave', 'amount' => 25000]], now(), 'Acompte');

        $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('Réservation (client pas encore arrivé)');

        // The guest arrives; Koffi records the arrival.
        $this->travelTo('2026-10-16 22:30:00');
        $this->actingAs($this->koffi);
        $booking->update(['status' => ReservationStatus::Enregistree]);
        $this->assertSame('Koffi Réception', $booking->fresh(['checkedInBy', 'creator'])->assignedBy()->name);
    }

    public function test_period_shortcuts_days_and_custom_ranges(): void
    {
        $this->stay($this->awa, $this->awa, 1000, '2026-10-14 08:00');  // today
        $this->stay($this->awa, $this->awa, 2000, '2026-10-14 05:30');  // business day of the 13th (before 06:00)
        $this->stay($this->awa, $this->awa, 4000, '2026-10-12 12:00');  // Monday, this week
        $this->stay($this->awa, $this->awa, 8000, '2026-10-02 12:00');  // this month
        $this->stay($this->awa, $this->awa, 16000, '2026-09-20 12:00'); // last month
        $this->actingAs($this->manager);
        $total = fn (array $query) => $this->get(route('admin.revenue.index', $query))->assertOk()->viewData('summary')['total'];

        $this->assertSame(1000.0, $total([]));
        $this->assertSame(2000.0, $total(['periode' => 'hier']));
        $this->assertSame(7000.0, $total(['periode' => 'semaine']));
        $this->assertSame(15000.0, $total(['periode' => 'mois']));
        $this->assertSame(16000.0, $total(['periode' => 'mois-dernier']));
        $this->assertSame(4000.0, $total(['jour' => '2026-10-12']));
        $this->assertSame(12000.0, $total(['du' => '2026-10-01', 'au' => '2026-10-12']));
        $this->assertSame(14000.0, $total(['du' => '2026-10-01', 'au' => '2026-10-13']));
        $this->assertSame(12000.0, $total(['du' => '2026-10-12', 'au' => '2026-10-01'])); // swapped
        // Future dates are brought back to today; garbage is ignored.
        $this->assertSame(1000.0, $total(['jour' => '2030-01-01']));
        $this->assertSame(1000.0, $total(['jour' => 'nimporte', 'periode' => 'nimporte', 'mode' => 'carte', 'par' => 'x']));

        $this->get(route('admin.revenue.index', ['jour' => '2026-10-13']))
            ->assertSee(route('admin.revenue.index', ['jour' => '2026-10-12']), false)
            ->assertSee(route('admin.revenue.index', ['jour' => '2026-10-14']), false);
    }

    public function test_the_manager_filters_by_staff_member_and_payment_method(): void
    {
        $this->stay($this->awa, $this->awa, 10000, '2026-10-14 08:00', 'wave');
        $this->stay($this->koffi, $this->koffi, 15000, '2026-10-14 08:30', 'especes');
        $this->actingAs($this->manager);

        $response = $this->get(route('admin.revenue.index'))->assertOk()->assertSee('Par réceptionniste');
        $this->assertSame(25000.0, $response->viewData('summary')['total']);
        $this->assertSame(10000.0, $this->get(route('admin.revenue.index', ['par' => $this->awa->id]))->viewData('summary')['total']);
        $this->assertSame(15000.0, $this->get(route('admin.revenue.index', ['mode' => 'especes']))->viewData('summary')['total']);
        $this->assertSame(0.0, $this->get(route('admin.revenue.index', ['par' => $this->awa->id, 'mode' => 'especes']))->viewData('summary')['total']);
    }

    public function test_a_receptionist_only_ever_sees_their_own_payments(): void
    {
        $mine = $this->stay($this->koffi, $this->awa, 10000, '2026-10-14 08:00');
        $other = $this->stay($this->awa, $this->koffi, 15000, '2026-10-14 08:30');

        $this->actingAs($this->awa)->get(route('admin.revenue.index', ['par' => $this->koffi->id]))->assertOk()
            ->assertSee($mine->reference())->assertDontSee($other->reference())
            ->assertDontSee('Par réceptionniste')->assertDontSee('name="par"', false)
            ->assertViewHas('summary', fn ($s) => $s['total'] === 10000.0);

        $csv = $this->get(route('admin.revenue.export', ['par' => $this->koffi->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString($mine->reference(), $csv);
        $this->assertStringNotContainsString($other->reference(), $csv);
    }

    public function test_the_export_is_a_spreadsheet_of_the_same_lines(): void
    {
        $stay = $this->stay($this->awa, $this->koffi, 20000, '2026-10-14 08:00', 'especes', 'Moussa');

        $response = $this->actingAs($this->manager)->get(route('admin.revenue.export', ['periode' => 'aujourdhui']))->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('recettes-2026-10-14.csv', $response->headers->get('Content-Disposition'));
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFDate;Heure;Type;Dossier", $csv);
        $this->assertStringContainsString('14/10/2026;08:00;Affectation;'.$stay->reference(), $csv);
        $this->assertStringContainsString(';Espèces;20000;20000;0;"Koffi Réception";"Awa Réception"', $csv);
    }

    public function test_filter_by_the_file_s_formula(): void
    {
        $hourly = $this->stay($this->awa, $this->koffi, 20000, '2026-10-14 08:00', 'especes', 'Moussa');
        $this->actingAs($this->awa);
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $night = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-16 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $night->invoice->settle([['method' => 'wave', 'amount' => 25000]], now(), 'Acompte');

        $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('<label class="form-label small mb-1" for="formule">Formule</label>', false)
            ->assertSee($hourly->reference())->assertSee($night->reference())
            ->assertSeeInOrder(['Total encaissé', '45 000']);

        // Nuitée only: lines, total, per-staff table, kind cards and links keep the filter.
        $page = $this->get(route('admin.revenue.index', ['formule' => 'nuitee']))->assertOk()
            ->assertSee('<option value="nuitee" selected>Nuitée</option>', false)
            ->assertSee($night->reference())->assertDontSee($hourly->reference())
            ->assertSeeInOrder(['Total encaissé — nuitée', '25 000'])
            ->assertSee('Awa Réception');
        // Per-staff table (the « Réceptionniste » list still names everyone): Koffi's hourly sale is out.
        $this->assertMatchesRegularExpression('#par='.$this->awa->id.'\\b#', $page->getContent());
        $this->assertDoesNotMatchRegularExpression('#par='.$this->koffi->id.'\\b#', $page->getContent());
        $this->assertStringContainsString('formule=nuitee', $page->getContent());

        $this->get(route('admin.revenue.index', ['formule' => 'heure']))->assertOk()
            ->assertSee($hourly->reference())->assertDontSee($night->reference())->assertSeeInOrder(["Total encaissé — à l'heure", '20 000']);

        // Export: same filter, plus the formula column.
        $csv = $this->get(route('admin.revenue.export', ['formule' => 'nuitee']))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFDate;Heure;Type;Dossier;Formule;Chambre", $csv);
        $this->assertStringContainsString(';Nuitée;', $csv);
        $this->assertStringNotContainsString($hourly->reference(), $csv);

        // Unknown or forged values are ignored (everything shown).
        foreach (['formule=xyz', 'formule[]=nuitee'] as $query) {
            $this->get(route('admin.revenue.index').'?'.$query)->assertOk()->assertSee($hourly->reference())->assertSee($night->reference());
        }
    }

    public function test_kind_bands_are_compact_with_the_detail_in_a_popup(): void
    {
        $this->stay($this->awa, $this->koffi, 20000, '2026-10-14 08:00', 'especes');

        $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk()
            // Card (PC: full count + per method) / band (phones: short count) + the ⓘ button opening the popup.
            ->assertSeeInOrder(['revenue-kind--affectation', 'Affectations', '20 000', '1 encaissement(s)', '1 enc.', 'Espèces 20 000', 'data-bs-target="#revenueKind-affectation"'], false)
            // Popup: total, settlements, per method, link filtering the page.
            ->assertSeeInOrder(['id="revenueKind-affectation"', '20 000', '1 encaissement(s)', 'Espèces', '20 000 F', 'Orange Money', 'Wave',
                'dossier=affectation', 'Voir ces encaissements'], false)
            ->assertSee('id="revenueKind-reservation"', false);
    }

    public function test_the_manager_s_staff_and_kind_filters_are_hidden_on_phones_unless_in_use(): void
    {
        $this->actingAs($this->manager);
        $this->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('<div class="col-12 col-md-auto phone-hidden">
                    <label class="form-label small mb-1" for="par">Réceptionniste</label>', false)
            ->assertSee('<div class="col-12 col-md-auto phone-hidden">
                <label class="form-label small mb-1" for="dossier">Dossier</label>', false)
            ->assertSee('<label class="form-label small mb-1" for="mode">Mode de paiement</label>', false);

        // In use: shown, so a filtered list always says why.
        $this->get(route('admin.revenue.index', ['par' => $this->awa->id, 'dossier' => 'reservation']))->assertOk()
            ->assertDontSee('phone-hidden">
                    <label class="form-label small mb-1" for="par">', false)
            ->assertDontSee('phone-hidden">
                <label class="form-label small mb-1" for="dossier">', false);

        // Receptionists keep « Dossier » on phones.
        $this->actingAs($this->awa)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('<label class="form-label small mb-1" for="dossier">Dossier</label>', false)
            ->assertDontSee('phone-hidden">
                <label class="form-label small mb-1" for="dossier">', false);
    }

    public function test_access_and_menu(): void
    {
        $this->actingAs(User::factory()->withoutRole()->create())->get(route('admin.revenue.index'))->assertForbidden();
        $this->get(route('admin.revenue.export'))->assertForbidden();

        $this->actingAs($this->awa)->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.revenue.index'), false);
    }

    public function test_the_simple_layout_has_a_recettes_top_link(): void
    {
        $topLink = '<a href="'.route('admin.revenue.index').'" class=""><i class="far fa-cash-register"></i> Recettes</a>';

        foreach ([User::factory()->simpleUi()->create(), $this->awa->forceFill(['ui_mode' => 'simple'])] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee($topLink, false);
        }

        // Full layout: one sidebar entry, no duplicate.
        $html = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<i class="far fa-cash-register"></i> Recettes'));

        $this->actingAs(User::factory()->withoutRole()->create()->forceFill(['ui_mode' => 'simple']))->get(route('admin.dashboard'))->assertOk()->assertDontSee($topLink, false);
    }
}
