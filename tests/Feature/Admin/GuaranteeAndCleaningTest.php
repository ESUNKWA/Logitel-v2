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

/**
 * A booking only holds its room once paid in full ("garantie"); stays are separated by a
 * mandatory cleaning time.
 */
class GuaranteeAndCleaningTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000, 'night_rate' => 40000]);
        $this->room = Room::factory()->create(['number' => 'GA-1', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function book(string $checkIn = '2026-10-10T14:00', array $extra = [])
    {
        return $this->actingAs(User::factory()->create())->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $this->room->id, 'pricing_mode' => 'heure',
            'check_in' => $checkIn, 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            ...$extra,
        ]);
    }

    private function pay(Reservation $booking, int $amount, string $method = 'wave')
    {
        return $this->actingAs(User::factory()->create())->post("/admin/invoices/{$booking->invoice->id}/payments", [
            'payments' => [['method' => $method, 'amount' => $amount]], 'paid_at' => '2026-10-08 10:00',
        ]);
    }

    public function test_an_unpaid_or_partly_paid_booking_does_not_hold_the_room(): void
    {
        $this->book()->assertSessionHasNoErrors();
        $this->book(extra: ['payments' => [['method' => 'wave', 'amount' => 4000]]])->assertSessionHasNoErrors(); // same slot, deposit only

        $this->assertSame(2, Reservation::count());
        $this->assertSame(0, Reservation::where('is_guaranteed', true)->count());
    }

    public function test_paying_in_full_guarantees_the_room_and_removing_a_payment_loses_it(): void
    {
        $this->book()->assertSessionHasNoErrors();
        $booking = Reservation::firstOrFail();

        $this->pay($booking, 4000)->assertSessionHasNoErrors();
        $this->assertFalse($booking->fresh()->is_guaranteed);

        $this->pay($booking, 6000)->assertSessionHasNoErrors();
        $this->assertTrue($booking->fresh()->is_guaranteed);
        $this->book()->assertSessionHasErrors('room_id'); // now the slot is held

        $payment = $booking->invoice->payments()->latest('id')->first();
        $this->actingAs(User::factory()->create())->delete("/admin/invoices/{$booking->invoice->id}/payments/{$payment->id}");
        $this->assertFalse($booking->fresh()->is_guaranteed);
        $this->book()->assertSessionHasNoErrors();
    }

    public function test_a_booking_paid_in_full_when_made_is_guaranteed_at_once(): void
    {
        $this->book(extra: ['payments' => [['method' => 'especes', 'amount' => 10000, 'received' => 10000]]])->assertSessionHasNoErrors();

        $this->assertTrue(Reservation::firstOrFail()->is_guaranteed);
        $this->book('2026-10-10T15:00')->assertSessionHasErrors('room_id');
    }

    public function test_the_first_booking_paid_in_full_wins(): void
    {
        $this->book()->assertSessionHasNoErrors();
        $this->book('2026-10-10T15:00')->assertSessionHasNoErrors(); // overlaps the first, both unpaid
        [$first, $second] = Reservation::orderBy('id')->get()->all();

        $this->pay($first, 10000)->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->is_guaranteed);

        $this->pay($second, 10000)->assertSessionHasErrors('payments');   // slot already guaranteed
        $this->assertFalse($second->fresh()->is_guaranteed);
        $this->assertSame(0, $second->invoice->payments()->count());

        $this->pay($second, 3000)->assertSessionHasNoErrors();            // a deposit is still fine
        $this->assertFalse($second->fresh()->is_guaranteed);

        $this->actingAs(User::factory()->create())->get("/admin/reservations/{$second->id}/edit")->assertOk()
            ->assertSee('Non garantie — chambre prise.');
        $this->actingAs(User::factory()->create())->get('/admin/reservations')->assertOk()
            ->assertSee('Soldée · chambre garantie')->assertSee('Non garantie · chambre prise');
    }

    public function test_an_affectation_can_take_the_slot_of_an_unpaid_booking_which_is_then_flagged(): void
    {
        $this->book('2026-10-08T11:00')->assertSessionHasNoErrors(); // unpaid booking later today
        $booking = Reservation::firstOrFail();

        // Walk-in now, paid in full, for 3 hours: overlaps the unpaid booking — allowed.
        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$this->room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0101',
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 15000]],
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertNotNull($booking->takenBy());
        $this->assertSame(ReservationStatus::Confirmee, $booking->status, 'The unpaid booking is kept, not cancelled.');

        // Its guest can't be checked in while the room is taken.
        $this->room->refresh()->update(['status' => RoomStatus::Disponible]); // even if the room looked free
        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/check-in")->assertSessionHasErrors('room_id');
        $this->assertSame(ReservationStatus::Confirmee, $booking->fresh()->status);
    }

    public function test_the_board_only_marks_a_room_reserved_for_a_guaranteed_booking(): void
    {
        $this->book('2026-10-08T20:00')->assertSessionHasNoErrors(); // unpaid, tonight
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()
            ->assertSee(route('admin.rooms.assign', $this->room), false) // still assignable
            ->assertSee('Réservation non soldée aujourd\'hui (20h00)', false);

        $this->pay(Reservation::firstOrFail(), 10000)->assertSessionHasNoErrors();
        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()
            ->assertDontSee(route('admin.rooms.assign', $this->room), false)
            ->assertSee('Réservée');
    }

    public function test_cancelling_or_checking_in_ends_the_booking_guarantee_but_a_stay_keeps_holding(): void
    {
        $booking = Reservation::factory()->guaranteed()->create(['room_id' => $this->room->id, 'pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-08 10:00', 'duration' => 2, 'unit_rate' => 5000, 'status' => ReservationStatus::Confirmee]);
        $this->assertTrue($booking->is_guaranteed);

        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/check-in");
        $booking->refresh();
        $this->assertSame(ReservationStatus::Enregistree, $booking->status);
        $this->assertFalse($booking->is_guaranteed);
        $this->assertTrue($booking->holdsRoom(), 'A stay in progress always holds its room.');

        $other = Reservation::factory()->guaranteed()->create(['room_id' => Room::factory()->create()->id, 'check_in' => '2026-10-12', 'status' => ReservationStatus::Confirmee]);
        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$other->id}/cancel");
        $this->assertFalse($other->fresh()->is_guaranteed);
    }

    public function test_the_cleaning_time_is_configurable(): void
    {
        Reservation::factory()->guaranteed()->create(['room_id' => $this->room->id, 'pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-10 10:00', 'duration' => 2, 'unit_rate' => 5000, 'status' => ReservationStatus::Confirmee]); // → 12:00
        $manager = User::factory()->create();
        $settings = fn (int $minutes) => ['business_day_start' => '06:00', 'night_from' => '22:00', 'night_until' => '12:00', 'day_hours' => 6, 'stay_min_hours' => 24, 'cleaning_buffer_minutes' => $minutes];

        $this->book('2026-10-10T12:15')->assertSessionHasErrors('room_id');  // default 30 min

        $this->actingAs($manager)->put('/admin/parametres', $settings(60))->assertSessionHasNoErrors();
        $this->book('2026-10-10T12:45')->assertSessionHasErrors(['room_id' => 'La chambre N°GA-1 est libérée le 10/10/2026 à 12h00 ; avec le ménage obligatoire de 60 min, le séjour suivant peut commencer au plus tôt le 10/10/2026 à 13h00.']);

        $this->actingAs($manager)->put('/admin/parametres', $settings(0))->assertSessionHasNoErrors();
        $this->book('2026-10-10T12:00')->assertSessionHasNoErrors();          // no cleaning time: back-to-back

        $this->actingAs($manager)->put('/admin/parametres', $settings(300))->assertSessionHasErrors('cleaning_buffer_minutes');
    }
}
