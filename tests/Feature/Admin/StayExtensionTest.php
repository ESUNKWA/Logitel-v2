<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SpecialRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extending a stay in progress at the guest's request, before its departure time (client request,
 * 2026-10-25): + N units of the same formula, for every room and formula; the supplement is
 * cashed at once (fully or partly) or left due; availability, forfaits and discounts as usual;
 * « Prolonger » reachable from the board, the departures page and the affectations list.
 */
class StayExtensionTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 3000, 'day_rate' => 15000, 'night_rate' => 25000, 'stay_rate' => 40000]);
        $this->room = Room::factory()->create(['number' => 'E-1', 'room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs(User::factory()->create());
    }

    private function stay(string $mode = 'heure', int $duration = 2, int $paid = 6000, array $extra = []): Reservation
    {
        $this->post("/admin/rooms/{$this->room->id}/assign", [
            'pricing_mode' => $mode, 'duration' => $duration, 'adults' => 2, 'children' => 0,
            'payments' => $paid > 0 ? [['method' => 'wave', 'amount' => $paid]] : [], ...$extra,
        ])->assertSessionHasNoErrors();

        return Reservation::sole();
    }

    private function extend(Reservation $stay, int $extra, array $payments = [])
    {
        return $this->post(route('admin.affectations.extend', $stay), ['extra' => $extra, 'payments' => $payments]);
    }

    public function test_a_stay_is_extended_before_its_departure_and_the_supplement_left_due(): void
    {
        $stay = $this->stay(); // 10:00 → 12:00, 6 000 paid
        $this->travelTo('2026-10-08 11:00:00'); // departure not reached yet

        $this->extend($stay, 3)
            ->assertRedirect(route('admin.affectations.show', $stay))
            ->assertSessionHas('receipt', $stay->id)
            ->assertSessionHas('status', 'Séjour prolongé de 3 heures : sortie le 08/10/2026 à 15h00, supplément 9 000 FCFA. Reste dû : 9 000 FCFA.');

        $stay->refresh();
        $this->assertSame(5, $stay->duration);
        $this->assertSame('2026-10-08 15:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('heure', $stay->pricing_mode->value); // same formula
        $this->assertSame('15000.00', (string) $stay->total_amount);
        $this->assertSame('15000.00', (string) $stay->invoice->amount);
        $this->assertSame(InvoiceStatus::PartiellementPayee, $stay->invoice->status);
        $this->assertSame(ReservationStatus::Enregistree, $stay->status);
        $this->assertSame(RoomStatus::Occupee, $stay->room->fresh()->status);
    }

    public function test_the_supplement_can_be_cashed_at_once_with_the_change(): void
    {
        $stay = $this->stay();

        $this->extend($stay, 2, [['method' => 'especes', 'amount' => 6000, 'received' => 10000]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Séjour prolongé de 2 heures : sortie le 08/10/2026 à 14h00, supplément 6 000 FCFA. Encaissé : 6 000 FCFA. Monnaie à rendre : 4 000 FCFA. Facture soldée.');

        $invoice = $stay->fresh()->invoice;
        $this->assertSame(InvoiceStatus::Payee, $invoice->status);
        $payment = $invoice->payments()->latest('id')->first();
        $this->assertSame('Prolongation', $payment->notes);
        $this->assertSame('4000.00', (string) $payment->change_given);

        // On the receipt.
        $this->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertSee('<span>Montant reçu</span><span>16 000 FCFA</span>', false)
            ->assertSee('<span>Monnaie rendue</span><span>4 000 FCFA</span>', false)
            ->assertSee('SOLDÉ');
    }

    public function test_a_mixed_or_partial_payment_is_accepted_but_never_more_than_due(): void
    {
        $stay = $this->stay();

        $this->extend($stay, 2, [['method' => 'wave', 'amount' => 7000]])->assertSessionHasErrors('payments');
        $this->assertSame(2, $stay->fresh()->duration); // nothing changed

        $this->extend($stay, 2, [['method' => 'wave', 'amount' => 2000], ['method' => 'orange_money', 'amount' => 1000]])->assertSessionHasNoErrors();
        $this->assertSame('3000.00', $stay->fresh()->invoice->remainingAmount());
    }

    public function test_cash_received_is_required_at_the_desk(): void
    {
        $stay = $this->stay();

        $this->extend($stay, 1, [['method' => 'especes', 'amount' => 3000]])->assertSessionHasErrors('payments.0.received');
        $this->assertSame(2, $stay->fresh()->duration);
    }

    public function test_every_formula_keeps_its_own_unit(): void
    {
        $cases = [
            'journee' => ['2026-10-08 22:00', '30000.00'],   // 10:00 + 2 × 6 h
            'nuitee' => ['2026-10-09 12:00', '50000.00'],    // morning arrival = previous night, + 1 night → 12:00 next day
            'sejour' => ['2026-10-10 10:00', '80000.00'],    // + 24 h
        ];

        foreach ($cases as $mode => [$end, $total]) {
            Reservation::query()->each(fn (Reservation $r) => $r->update(['status' => ReservationStatus::Terminee]));
            $rate = (float) $this->type->{['journee' => 'day_rate', 'nuitee' => 'night_rate', 'sejour' => 'stay_rate'][$mode]};
            $this->post("/admin/rooms/{$this->room->id}/assign", [
                'pricing_mode' => $mode, 'duration' => 1, 'adults' => 2, 'children' => 0,
                'payments' => [['method' => 'wave', 'amount' => $rate]],
            ])->assertSessionHasNoErrors();
            $stay = Reservation::latest('id')->first();

            $this->extend($stay, 1)->assertSessionHasNoErrors();

            $stay->refresh();
            $this->assertSame($mode, $stay->pricing_mode->value);
            $this->assertSame(2, $stay->duration, $mode);
            $this->assertSame($end, $stay->check_out->format('Y-m-d H:i'), $mode);
            $this->assertSame($total, (string) $stay->total_amount, $mode);
        }
    }

    public function test_an_extension_onto_a_guaranteed_booking_is_refused(): void
    {
        $stay = $this->stay(); // until 12:00 (+30 min cleaning)
        Reservation::factory()->guaranteed()->create([
            'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'check_in' => '2026-10-08 14:00:00', 'duration' => 2,
            'unit_rate' => 3000, 'status' => ReservationStatus::Confirmee,
        ]);

        $this->extend($stay, 1)->assertSessionHasNoErrors(); // until 13:00: 13:30 ≤ 14:00
        $this->extend($stay, 1)->assertSessionHasErrors('extra'); // until 14:00: overlaps the cleaning time

        $this->assertSame(3, $stay->fresh()->duration);
    }

    public function test_forfaits_and_discounts_follow_the_new_duration(): void
    {
        SpecialRate::create(['name' => 'Promo 3 h', 'pricing_mode' => 'heure', 'duration' => 3, 'price' => 7000, 'all_room_types' => true, 'is_active' => true]);
        $stay = $this->stay('heure', 2, 5400, ['discount_type' => 'pourcentage', 'discount_value' => 10]); // 6 000 − 10 %

        $this->extend($stay, 1)->assertSessionHasNoErrors();

        $stay->refresh();
        $this->assertSame('Promo 3 h', $stay->special_rate_name);
        $this->assertSame('6300.00', (string) $stay->total_amount); // 7 000 − 10 %
    }

    public function test_extensions_are_validated(): void
    {
        $stay = $this->stay();

        $this->extend($stay, 0)->assertSessionHasErrors('extra');
        $this->post(route('admin.affectations.extend', $stay), ['extra' => 'abc'])->assertSessionHasErrors('extra');
        $this->extend($stay, 364)->assertSessionHasErrors('extra'); // 2 + 364 > 365
        $this->assertSame(2, $stay->fresh()->duration);
    }

    public function test_only_stays_in_progress_can_be_extended(): void
    {
        $stay = $this->stay();
        $stay->update(['status' => ReservationStatus::Terminee]);
        $this->extend($stay, 1)->assertNotFound();

        $booking = Reservation::factory()->create(['status' => ReservationStatus::Confirmee, 'check_in' => '2026-10-12 14:00:00']);
        $this->extend($booking, 1)->assertNotFound();
    }

    public function test_permissions(): void
    {
        $stay = $this->stay();

        // Réception: allowed, with or without payment.
        $this->actingAs(User::factory()->reception()->create());
        $this->extend($stay, 1, [['method' => 'wave', 'amount' => 3000]])->assertSessionHasNoErrors();

        // Viewer only: refused.
        $viewer = Role::create(['name' => 'Lecture', 'slug' => 'lecture']);
        $viewer->syncPermissions(['affectations.view']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $viewer->id])->save();
        $this->actingAs($user)->extend($stay, 1)->assertForbidden();

        // Allowed to extend but not to cash: fine without payment, refused with one.
        $desk = Role::create(['name' => 'Accueil', 'slug' => 'accueil']);
        $desk->syncPermissions(['affectations.view', 'affectations.manage']);
        $deskUser = User::factory()->create();
        $deskUser->forceFill(['role_id' => $desk->id])->save();
        $this->actingAs($deskUser)->extend($stay, 1, [['method' => 'wave', 'amount' => 3000]])->assertForbidden();
        $this->actingAs($deskUser)->extend($stay, 1)->assertSessionHasNoErrors();
        $this->assertSame(4, $stay->fresh()->duration);
    }

    // ---- Where « Prolonger » is ----------------------------------------------------------

    public function test_the_affectation_page_has_the_extension_form_with_an_exact_preview(): void
    {
        $stay = $this->stay();

        $html = $this->get(route('admin.affectations.show', $stay))->assertOk()
            ->assertSee('id="prolonger"', false)->assertSee('Prolonger le séjour')
            ->assertSee('action="'.route('admin.affectations.extend', $stay).'"', false)
            ->assertDontSee('id="modifier"', false)->assertDontSee('Modifier le séjour') // removed (client request, 2026-10-25): too much for the desk
            ->getContent();

        preg_match('/data-quotes="([^"]+)"/', $html, $m);
        $quotes = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame(['end' => '08/10/2026 à 13h00', 'total' => 9000, 'supplement' => 3000], $quotes['1']);
        $this->assertSame('08/10/2026 à 15h00', $quotes['3']['end']);
    }

    public function test_an_occupied_card_is_clickable_as_a_whole(): void
    {
        $stay = $this->stay();
        $free = Room::factory()->create(['number' => 'E-2', 'room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);

        $html = $this->get('/admin/rooms/board')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-open-url='));
        $this->assertStringContainsString('data-open-url="'.route('admin.affectations.show', $stay).'"', $html);
        $this->assertStringContainsString("aria-label=\"Ouvrir l'affectation de la chambre N°E-1\"", $html);
        $this->assertMatchesRegularExpression('/class="room-card room-card--occupee is-clickable"\s+data-open-url=/', $html);
        $this->assertStringContainsString('data-room-id="'.$free->id.'"', $html); // the free room still opens the assignment modal

        // Without access to stays: not clickable.
        $role = Role::create(['name' => 'Ménage', 'slug' => 'menage']);
        $role->syncPermissions(['rooms.view']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()->assertDontSee('data-open-url=', false);
    }

    public function test_prolonger_links_on_the_board_departures_and_list(): void
    {
        $stay = $this->stay();
        $link = route('admin.affectations.show', $stay).'#prolonger';

        $this->get('/admin/rooms/board')->assertOk()->assertSee($link, false);
        $this->get('/admin/departs')->assertOk()->assertSee($link, false);
        $this->get('/admin/affectations')->assertOk()->assertSee($link, false);

        // Not for a finished stay, nor for someone who can't extend.
        $viewer = Role::create(['name' => 'Lecture', 'slug' => 'lecture']);
        $viewer->syncPermissions(['affectations.view', 'rooms.view']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $viewer->id])->save();
        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()->assertDontSee($link, false);

        $stay->update(['status' => ReservationStatus::Terminee]);
        $this->actingAs(User::factory()->create())->get('/admin/affectations?onglet=toutes')->assertOk()->assertDontSee($link, false);
    }
}
