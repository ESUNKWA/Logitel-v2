<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayPricingTest extends TestCase
{
    use RefreshDatabase;

    private function room(array $rates = []): Room
    {
        $type = RoomType::factory()->create([
            'hourly_rate' => 5000,
            'day_rate' => 25000,
            'night_rate' => 40000,
            'stay_rate' => 60000,
            ...$rates,
        ]);

        return Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(Room $room, string $mode, int $duration, ?int $rate = null, array $extra = [])
    {
        $rate ??= (int) PricingMode::from($mode)->rateFor($room->roomType);

        return $this->actingAs(User::factory()->reception()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Kofi',
            'new_last_name' => 'Yao',
            'new_phone' => '0101010101',
            'pricing_mode' => $mode,
            'duration' => $duration,
            'unit_rate' => $rate,
            'adults' => 1,
            'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => $rate * max(1, $duration)]], // paid in full
            ...$extra,
        ]);
    }

    public function test_hourly_affectation_is_billed_per_hour_and_checked_in(): void
    {
        $this->travelTo('2026-10-07 10:15:00');
        $room = $this->room();

        $this->assign($room, 'heure', 3)->assertRedirect(route('admin.rooms.board'))->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame(PricingMode::Heure, $stay->pricing_mode);
        $this->assertSame('2026-10-07 10:15', $stay->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-07 13:15', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('15000.00', $stay->total_amount);
        $this->assertSame('15000.00', $stay->invoice->amount);
        $this->assertSame(ReservationStatus::Enregistree, $stay->status);
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
    }

    public function test_day_affectation_is_a_6_hour_block(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $room = $this->room();

        $this->assign($room, 'journee', 1)->assertRedirect(route('admin.rooms.board'))->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame(PricingMode::Journee, $stay->pricing_mode);
        $this->assertSame('2026-10-07 14:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('25000.00', $stay->total_amount);
        $this->assertSame('1 journée × 25 000 FCFA', $stay->billingLabel());
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
    }

    public function test_several_days_are_consecutive_6_hour_blocks(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $room = $this->room();

        $this->assign($room, 'journee', 2)->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame('2026-10-07 20:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('50000.00', $stay->total_amount);
    }

    public function test_day_mode_is_refused_when_the_room_type_has_no_day_rate(): void
    {
        $this->travelTo('2026-10-07 08:00:00');
        $room = $this->room(['day_rate' => null]);

        $this->assign($room, 'journee', 1, rate: 25000)->assertSessionHasErrors('pricing_mode');
        $this->assertSame(0, Reservation::count());
    }

    public function test_stay_affectation_is_billed_per_24_hours(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();

        $this->assign($room, 'sejour', 2)->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame('2026-10-09 10:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('120000.00', $stay->total_amount);
    }

    public function test_nuitee_from_22h_ends_at_noon(): void
    {
        $this->travelTo('2026-10-07 22:40:00');
        $room = $this->room();

        $this->assign($room, 'nuitee', 2)->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame('2026-10-09 12:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('80000.00', $stay->total_amount);
    }

    public function test_nuitee_after_midnight_ends_at_noon_the_same_day(): void
    {
        $this->travelTo('2026-10-08 02:00:00');
        $room = $this->room();

        $this->assign($room, 'nuitee', 1)->assertSessionHasNoErrors();

        $this->assertSame('2026-10-08 12:00', $room->reservations()->firstOrFail()->check_out->format('Y-m-d H:i'));
    }

    public function test_nuitee_is_refused_before_22h(): void
    {
        $this->travelTo('2026-10-07 16:00:00');
        $room = $this->room();

        $this->assign($room, 'nuitee', 1)->assertSessionHasErrors('pricing_mode');

        $this->assertSame(0, Reservation::count());
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
    }

    public function test_mode_without_a_rate_on_the_room_type_is_refused(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room(['hourly_rate' => null]);

        $this->assign($room, 'heure', 1, rate: 5000)->assertSessionHasErrors('pricing_mode');

        $this->assertSame(0, Reservation::count());
    }

    public function test_the_rate_is_always_the_room_types_and_a_reduction_is_a_discount(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();

        // A different submitted rate is ignored (the rate field isn't editable any more)…
        $this->assign($room, 'heure', 2, rate: 4000)->assertSessionHasErrors('payments'); // 8 000 paid, 10 000 due
        $this->assertSame(0, Reservation::count());

        // …a reduction goes through the discount instead: 10 000 − 2 000 = 8 000.
        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0102',
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'discount_type' => 'montant', 'discount_value' => 2000,
            'payments' => [['method' => 'wave', 'amount' => 8000]],
        ])->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame('5000.00', (string) $stay->unit_rate);
        $this->assertSame('8000.00', (string) $stay->total_amount);
    }

    public function test_departure_and_total_are_never_taken_from_input(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();

        $this->assign($room, 'heure', 1, extra: [
            'check_in' => '2020-01-01 00:00',
            'check_out' => '2030-01-01 00:00',
            'total_amount' => 1,
            'status' => 'terminee',
        ])->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame('2026-10-07 11:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('5000.00', $stay->total_amount);
        $this->assertSame(ReservationStatus::Enregistree, $stay->status);
    }

    public function test_invalid_duration_is_refused(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();

        $this->assign($room, 'heure', 0)->assertSessionHasErrors('duration');
        $this->assertSame(0, Reservation::count());
    }

    public function test_availability_is_checked_to_the_minute(): void
    {
        $room = $this->room();
        Reservation::factory()->guaranteed()->create([ // paid: it holds the room
            'room_id' => $room->id,
            'pricing_mode' => PricingMode::Heure,
            'check_in' => '2026-10-07 10:00',
            'duration' => 2,
            'status' => ReservationStatus::Confirmee,
        ]);

        $available = fn (string $from, string $to) => Room::query()->whereKey($room->id)->availableBetween($from, $to)->exists();

        $this->assertFalse($available('2026-10-07 11:59', '2026-10-07 13:00'));
        $this->assertFalse($available('2026-10-07 09:00', '2026-10-07 10:01'));
        // 30-min cleaning on each side of the 10:00 → 12:00 stay
        $this->assertFalse($available('2026-10-07 12:00', '2026-10-07 14:00'));
        $this->assertFalse($available('2026-10-07 08:00', '2026-10-07 10:00'));
        $this->assertTrue($available('2026-10-07 12:30', '2026-10-07 14:00'));
        $this->assertTrue($available('2026-10-07 08:00', '2026-10-07 09:30'));
    }

    public function test_an_ongoing_stay_blocks_a_new_affectation(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();
        $this->assign($room, 'heure', 3)->assertSessionHasNoErrors();

        // Room flipped back to "disponible" by mistake while the guest is still inside.
        $room->refresh()->update(['status' => RoomStatus::Disponible]);
        $this->travelTo('2026-10-07 11:00:00');

        $this->assign($room, 'heure', 1)->assertSessionHasErrors('duration');
        $this->assertSame(1, $room->reservations()->count());
    }

    public function test_checked_out_stay_frees_the_room_before_its_planned_end(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();
        $this->assign($room, 'heure', 3)->assertSessionHasNoErrors();
        $stay = $room->reservations()->firstOrFail();

        $this->actingAs(User::factory()->create())->post("/admin/affectations/{$stay->id}/check-out");
        $room->refresh()->update(['status' => RoomStatus::Disponible]); // cleaned

        $this->assign($room, 'heure', 1)->assertSessionHasNoErrors();
        $this->assertSame(2, $room->reservations()->count());
    }

    public function test_extending_an_hourly_stay_rebills_the_invoice_and_keeps_its_mode(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();
        $this->assign($room, 'heure', 2)->assertSessionHasNoErrors();
        $stay = $room->reservations()->firstOrFail();

        // Extending a stay in progress is done from its affectation page.
        $this->actingAs(User::factory()->create())->put("/admin/affectations/{$stay->id}", [
            'duration' => 4,
            'adults' => 1,
            'children' => 0,
            'unit_rate' => 5000,
        ])->assertSessionHasNoErrors();

        $stay->refresh();
        $this->assertSame(PricingMode::Heure, $stay->pricing_mode);
        $this->assertSame('2026-10-07 14:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('20000.00', $stay->total_amount);
        $this->assertSame('20000.00', $stay->invoice->amount);
    }

    public function test_date_based_reservation_form_still_books_nuitees(): void
    {
        $room = $this->room();

        $this->actingAs(User::factory()->create())->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => '2027-03-10',
            'check_out' => '2027-03-13',
            'adults' => 2,
            'children' => 0,
            'status' => 'confirmee',
            'unit_rate' => 40000,
        ])->assertRedirect(route('admin.reservations.index'))->assertSessionHasNoErrors();

        $stay = Reservation::firstOrFail();
        $this->assertSame(PricingMode::Nuitee, $stay->pricing_mode);
        $this->assertSame(3, $stay->duration);
        $this->assertSame('2027-03-10 22:00', $stay->check_in->format('Y-m-d H:i'));
        $this->assertSame('2027-03-13 12:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('120000.00', $stay->total_amount);
    }

    public function test_board_and_reservation_pages_render_with_modes(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $room = $this->room();
        $this->assign($room, 'heure', 2)->assertSessionHasNoErrors();
        $stay = $room->reservations()->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()->assertSee('data-stay-form', false);
        // An affectation lives on the affectations side, not in the réservations list.
        $this->actingAs($user)->get('/admin/affectations')->assertOk()->assertSee("À l'heure")->assertSee('2 heures');
        $this->actingAs($user)->get("/admin/affectations/{$stay->id}")->assertOk()->assertSee('name="duration"', false);
        $this->actingAs($user)->get("/admin/invoices/{$stay->invoice->id}")->assertOk()->assertSee('2 heures × 5 000 FCFA');
        $this->actingAs($user)->get('/admin/rooms?status=occupee')->assertOk()->assertSee('07/10/2026 à 12h00');
    }
}
