<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AffectationFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var array<string, Reservation> */
    private array $stays = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-15 10:00:00');
        $this->user = User::factory()->create();

        $deluxe = RoomType::factory()->create(['name' => 'Deluxe']);
        $suite = RoomType::factory()->create(['name' => 'Suite']);

        $this->stays = [
            // in progress, direct, hourly, paid, today
            'awa' => $this->stay('Awa', 'Koné', '0101', $deluxe, 'D-1', PricingMode::Heure, '2026-10-15 08:00', 4, 5000, ReservationStatus::Enregistree, Reservation::ORIGIN_AFFECTATION, 20000),
            // in progress & overdue (left on the 14th at noon), from a réservation, nuitée, unpaid
            'yao' => $this->stay('Yao', 'Kouamé', '0202', $suite, 'S-1', PricingMode::Nuitee, '2026-10-13', 1, 40000, ReservationStatus::Enregistree, Reservation::ORIGIN_RESERVATION, 0),
            // finished, direct, journée, partly paid, 10 days ago
            'ama' => $this->stay('Ama', 'Bamba', '0303', $deluxe, 'D-2', PricingMode::Journee, '2026-10-05 08:00', 1, 25000, ReservationStatus::Terminee, Reservation::ORIGIN_AFFECTATION, 10000),
            // finished last month
            'koffi' => $this->stay('Koffi', 'Yao', '0404', $suite, 'S-2', PricingMode::Sejour, '2026-09-20 12:00', 2, 60000, ReservationStatus::Terminee, Reservation::ORIGIN_RESERVATION, 120000),
        ];
    }

    private function stay(string $first, string $last, string $phone, RoomType $type, string $number, PricingMode $mode, string $in, int $duration, int $rate, ReservationStatus $status, string $origin, int $paid): Reservation
    {
        $stay = Reservation::factory()->create([
            'guest_id' => Guest::factory()->create(['first_name' => $first, 'last_name' => $last, 'phone' => $phone])->id,
            'room_id' => Room::factory()->create(['room_type_id' => $type->id, 'number' => $number])->id,
            'pricing_mode' => $mode,
            'check_in' => $in,
            'duration' => $duration,
            'unit_rate' => $rate,
            'status' => $status,
            'origin' => $origin,
        ]);
        if ($paid > 0) {
            $stay->invoice->settle([['method' => PaymentMethod::Especes, 'amount' => $paid]], now());
        }

        return $stay;
    }

    /**
     * @return Collection<int, string> first names listed
     */
    private function listed(array $query): Collection
    {
        $response = $this->actingAs($this->user)->get(route('admin.affectations.index', $query))->assertOk();

        return $response->viewData('affectations')->getCollection()->map(fn ($s) => $s->guest->first_name)->sort()->values();
    }

    public function test_tabs(): void
    {
        $this->assertSame(['Awa', 'Yao'], $this->listed([])->all());
        $this->assertSame(['Ama', 'Koffi'], $this->listed(['onglet' => 'terminees'])->all());
        $this->assertSame(['Ama', 'Awa', 'Koffi', 'Yao'], $this->listed(['onglet' => 'toutes'])->all());
    }

    public function test_search_by_name_full_name_or_phone_case_insensitively(): void
    {
        $this->assertSame(['Awa'], $this->listed(['onglet' => 'toutes', 'q' => 'koné'])->all());
        $this->assertSame(['Awa'], $this->listed(['onglet' => 'toutes', 'q' => 'AWA KON'])->all());
        $this->assertSame(['Ama'], $this->listed(['onglet' => 'toutes', 'q' => '0303'])->all());
        // "Yao" is Yao Kouamé's first name and Koffi Yao's last name — and nobody else's.
        $this->assertSame(['Koffi', 'Yao'], $this->listed(['onglet' => 'toutes', 'q' => 'yao'])->all());
    }

    public function test_entry_period_follows_business_days(): void
    {
        $this->assertSame(['Awa'], $this->listed(['onglet' => 'toutes', 'periode' => 'aujourdhui'])->all());
        $this->assertSame(['Awa', 'Yao'], $this->listed(['onglet' => 'toutes', 'periode' => '7j'])->all());
        $this->assertSame(['Ama', 'Awa', 'Yao'], $this->listed(['onglet' => 'toutes', 'periode' => 'mois'])->all());
        $this->assertSame(['Ama', 'Awa', 'Koffi', 'Yao'], $this->listed(['onglet' => 'toutes', 'periode' => '30j'])->all());
        $this->assertSame(['Ama', 'Koffi'], $this->listed(['onglet' => 'toutes', 'periode' => 'personnalisee', 'du' => '2026-09-01', 'au' => '2026-10-10'])->all());
    }

    public function test_room_type_mode_payment_origin_and_overdue_filters(): void
    {
        $this->assertSame(['Ama'], $this->listed(['onglet' => 'toutes', 'chambre' => $this->stays['ama']->room_id])->all());
        $this->assertSame(['Ama', 'Awa'], $this->listed(['onglet' => 'toutes', 'type' => $this->stays['awa']->room->room_type_id])->all());
        $this->assertSame(['Ama'], $this->listed(['onglet' => 'toutes', 'mode' => 'journee'])->all());
        $this->assertSame(['Awa', 'Koffi'], $this->listed(['onglet' => 'toutes', 'paiement' => 'payee'])->all());
        $this->assertSame(['Ama'], $this->listed(['onglet' => 'toutes', 'paiement' => 'partiellement_payee'])->all());
        $this->assertSame(['Koffi', 'Yao'], $this->listed(['onglet' => 'toutes', 'origine' => 'reservation'])->all());
        $this->assertSame(['Ama', 'Awa'], $this->listed(['onglet' => 'toutes', 'origine' => 'directe'])->all());
        $this->assertSame(['Yao'], $this->listed(['onglet' => 'toutes', 'depasse' => 1])->all());
    }

    public function test_totals_and_tab_counts_follow_the_filters(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.affectations.index', ['onglet' => 'toutes', 'type' => $this->stays['awa']->room->room_type_id]))->assertOk();

        // Deluxe: Awa 20 000 (paid 20 000) + Ama 25 000 (paid 10 000)
        $response->assertViewHas('totals', fn ($t) => $t['count'] === 2 && $t['billed'] === 45000.0 && $t['paid'] === 30000.0 && $t['due'] === 15000.0)
            ->assertViewHas('counts', fn ($c) => $c['en-cours'] === 1 && $c['terminees'] === 1 && $c['toutes'] === 2)
            ->assertSee('Réinitialiser');
    }

    public function test_sorting(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.affectations.index', ['onglet' => 'toutes', 'tri' => 'montant_desc']))->assertOk();
        $this->assertSame(['Koffi', 'Yao', 'Ama', 'Awa'], $response->viewData('affectations')->getCollection()->map(fn ($s) => $s->guest->first_name)->all());
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        $this->assertSame(['Awa', 'Yao'], $this->listed(['mode' => 'gratuit', 'paiement' => 'x', 'periode' => 'hier', 'du' => 'pas-une-date', 'origine' => '1 OR 1=1', 'tri' => 'drop', 'onglet' => 'zzz'])->all());
    }

    public function test_each_row_shows_the_stay_details(): void
    {
        $this->actingAs($this->user)->get(route('admin.affectations.index', ['onglet' => 'toutes', 'q' => 'Ama']))->assertOk()
            ->assertSeeInOrder(['N°D-2', 'Deluxe', 'Ama Bamba', '0303', 'Journée (6 h)', '1 journée × 25 000 FCFA', '05/10/2026', '08h00', '05/10/2026', '14h00', '25 000 FCFA', 'encaissé 10 000', 'reste 15 000', 'Partiellement payée', 'Terminée (check-out)', 'affectation directe'], false);
    }
}
