<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Money figures on the home page (simple mode) and per payment method on the figures dashboard:
 * cash of the business day by method, month to date, open balances, occupancy.
 */
class HomeFinanceTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->type = RoomType::factory()->create(['hourly_rate' => 5000]);
    }

    private function stay(string $number, int $hours, array $payments, string $paidAt = '2026-10-08 09:00'): Reservation
    {
        $room = Room::factory()->create(['number' => $number, 'room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-10-08 08:00', 'duration' => $hours, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        if ($payments) {
            $this->travelTo($paidAt);
            $stay->invoice->settle($payments, now());
            $this->travelTo('2026-10-08 10:00:00');
        }

        return $stay;
    }

    public function test_the_home_page_shows_the_money_of_the_day_by_payment_method(): void
    {
        // Mixed payment = one "encaissement" of two lines.
        $this->stay('101', 4, [['method' => 'especes', 'amount' => 15000, 'received' => 15000], ['method' => 'wave', 'amount' => 5000]]);
        $this->stay('102', 2, [['method' => 'orange_money', 'amount' => 10000]]);
        $this->stay('103', 2, [['method' => 'wave', 'amount' => 4000]], '2026-10-07 20:00'); // previous business day, same month
        $this->stay('104', 3, []); // nothing paid: 15 000 outstanding
        Room::factory()->create(['number' => 'HS-1', 'room_type_id' => $this->type->id, 'status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('finance', fn ($f) => $f['today'] === 30000.0 && $f['todayCount'] === 2 && $f['month'] === 34000.0
                && $f['outstanding'] === 21000.0 && $f['outstandingCount'] === 2)
            ->assertSeeInOrder(['Recette du jour', '30 000', '2 encaissement(s)'])
            ->assertSeeInOrder(['Chambres occupées', '4', '/ 4', '100 %'])
            ->assertSeeInOrder(['Recette du mois', '34 000'])
            ->assertSeeInOrder(['Reste à encaisser', '21 000', '2 facture(s)'])
            ->assertSeeInOrder(['Recette du jour par mode de paiement', 'Espèces', '15 000 F', '50 %', 'Orange Money', '10 000 F', '33 %', 'Wave', '5 000 F', '17 %']);
    }

    public function test_todays_discounts_are_mentioned(): void
    {
        $stay = $this->stay('101', 4, []);
        $stay->update(['discount_type' => 'montant', 'discount_value' => 2000]);

        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertSee('2 000 F de remises');
    }

    public function test_money_is_neither_shown_nor_computed_without_the_revenue_permission(): void
    {
        $this->stay('101', 2, [['method' => 'wave', 'amount' => 10000]]);

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('finance', null)
            ->assertDontSee('Recette du jour')->assertDontSee('10 000')
            ->assertSee('Chambres occupées'); // occupancy stays visible
    }

    public function test_an_empty_day_shows_zeroes(): void
    {
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSeeInOrder(['Recette du jour', '0', '0 encaissement(s)'])
            ->assertSeeInOrder(['Espèces', '0 F', '0 %']);
    }

    public function test_the_figures_dashboard_shows_cash_per_payment_method(): void
    {
        $this->stay('101', 2, [['method' => 'orange_money', 'amount' => 10000]]);
        $this->stay('102', 2, [['method' => 'wave', 'amount' => 4000]], '2026-10-07 20:00');

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()->assertViewIs('admin.dashboard')
            ->assertSee('Encaissé par mode de paiement')
            ->assertSeeInOrder(['Orange Money', '10 000', '71 %', '10 000 aujourd', 'Wave', '4 000', '29 %', '0 aujourd']);
    }
}
