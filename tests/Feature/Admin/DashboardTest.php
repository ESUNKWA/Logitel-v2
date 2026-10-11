<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function stay(string $checkIn, PricingMode $mode, int $duration, int $rate, ReservationStatus $status = ReservationStatus::Enregistree, ?Room $room = null): Reservation
    {
        return Reservation::factory()->create([
            'room_id' => ($room ?? Room::factory()->create())->id,
            'pricing_mode' => $mode,
            'check_in' => $checkIn,
            'duration' => $duration,
            'unit_rate' => $rate,
            'status' => $status,
        ]);
    }

    private function pay(Reservation $stay, int $amount, string $at): void
    {
        Payment::create(['invoice_id' => $stay->invoice->id, 'amount' => $amount, 'method' => PaymentMethod::Especes, 'paid_at' => $at]);
    }

    public function test_money_figures_follow_payments_and_business_days(): void
    {
        $this->travelTo('2026-10-15 10:00:00');

        $september = $this->stay('2026-09-10 10:00', PricingMode::Heure, 2, 10000, ReservationStatus::Terminee);
        $this->pay($september, 20000, '2026-09-10 12:00');               // previous month, before the 15th 10:00

        $hourly = $this->stay('2026-10-15 08:00', PricingMode::Heure, 3, 5000);     // 15 000 billed
        $this->pay($hourly, 15000, '2026-10-15 08:05');                  // today

        $night = $this->stay('2026-10-14', PricingMode::Nuitee, 2, 40000);          // 80 000 billed
        $this->pay($night, 30000, '2026-10-15 03:00');                   // 03:00 → business day of Oct 14

        $this->stay('2026-10-12 09:00', PricingMode::Journee, 1, 25000, ReservationStatus::Annulee); // cancelled: not billed

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertViewHas('revenueToday', 15000.0)
            ->assertViewHas('revenueMonth', 45000.0)
            ->assertViewHas('revenueMonthDelta', 125)                    // 45 000 vs 20 000
            ->assertViewHas('monthlyRevenue', fn ($v) => (float) $v === 95000.0)
            ->assertViewHas('outstandingAmount', 50000.0 + 25000.0)      // night 80k−30k, cancelled journée still invoiced 25k
            ->assertViewHas('dailyRevenue', function (array $series) {
                $byDay = collect($series)->mapWithKeys(fn ($p) => [$p['date']->toDateString() => $p['amount']]);

                return count($series) === 14 && $byDay['2026-10-15'] === 15000.0 && $byDay['2026-10-14'] === 30000.0;
            })
            ->assertViewHas('revenueByMode', function (array $rows) {
                $byMode = collect($rows)->keyBy(fn ($r) => $r['mode']->value);

                return $byMode['heure']['amount'] === 15000.0 && $byMode['nuitee']['amount'] === 80000.0
                    && $byMode['journee']['amount'] === 0.0 && $byMode['nuitee']['count'] === 1;
            });
    }

    public function test_room_and_activity_figures(): void
    {
        $this->travelTo('2026-10-15 10:00:00');

        Room::factory()->count(2)->create(['status' => RoomStatus::Disponible]);
        Room::factory()->create(['status' => RoomStatus::Nettoyage]);
        Room::factory()->create(['status' => RoomStatus::HorsService]);
        $occupied = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $this->stay('2026-10-15 07:00', PricingMode::Heure, 2, 5000, ReservationStatus::Confirmee, $occupied)
            ->update(['status' => ReservationStatus::Enregistree]);    // due 09:00 → overdue at 10:00
        $this->stay('2026-10-20', PricingMode::Nuitee, 1, 40000, ReservationStatus::Confirmee); // future booking

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertViewHas('roomCounts', fn ($c) => $c['disponible'] === 3 && $c['occupee'] === 1 && $c['nettoyage'] === 1 && $c['hors_service'] === 1)
            ->assertViewHas('occupancyRate', 20)                          // 1 occupied / 5 sellable
            ->assertViewHas('upcomingReservations', 1)
            ->assertViewHas('overdueCheckouts', 1)
            ->assertSee('1 départ(s) dépassé(s)')
            ->assertViewHas('todayDepartures', fn ($list) => $list->count() === 1);
    }

    public function test_money_is_not_computed_for_staff_without_revenue_permission(): void
    {
        $this->actingAs(User::factory()->reception()->create())->get('/admin')->assertOk()
            ->assertViewMissing('revenueMonth')
            ->assertViewMissing('outstandingAmount')
            ->assertViewHas('occupancyRate')
            // No hotel-wide money; only their own takings (« Ma recette du jour »).
            ->assertDontSee('Recettes encaissées ce mois')
            ->assertDontSee("Chiffre d'affaires du mois", false)
            ->assertDontSee('Reste à encaisser')
            ->assertSee('Ma recette du jour');
    }
}
