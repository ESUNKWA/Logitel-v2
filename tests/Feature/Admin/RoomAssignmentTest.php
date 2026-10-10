<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Affectations start now; pin the clock inside the nuitée window (from 22:00).
        $this->travelTo('2026-10-07 22:30:00');
    }

    public function test_assigning_an_available_room_creates_a_reservation_and_invoice(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);

        $response = $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa',
            'new_last_name' => 'Koné',
            'new_phone' => '0102030405',
            'pricing_mode' => 'nuitee',
            'duration' => 3,
            'adults' => 2,
            'children' => 0,
            'unit_rate' => 75000,
            'payments' => [['method' => 'wave', 'amount' => 225000]], // an affectation is paid in full
        ]);

        $response->assertRedirect(route('admin.rooms.board'));

        $this->assertDatabaseCount('reservations', 1);
        $reservation = Reservation::first();
        $this->assertSame($room->id, $reservation->room_id);
        $this->assertSame('225000.00', $reservation->total_amount);
        $this->assertNotNull($reservation->invoice);
        $this->assertDatabaseHas('guests', ['first_name' => 'Awa', 'last_name' => 'Koné']);
    }

    public function test_assigning_a_room_with_an_existing_guest_reuses_that_guest(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $guest = Guest::factory()->create();

        $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [
            'guest_id' => $guest->id,
            'pricing_mode' => 'nuitee',
            'duration' => 1,
            'adults' => 1,
            'children' => 0,
            'unit_rate' => 75000,
            'payments' => [['method' => 'wave', 'amount' => 75000]],
        ])->assertRedirect(route('admin.rooms.board'));

        $this->assertDatabaseCount('guests', 1);
        $this->assertSame($guest->id, Reservation::first()->guest_id);
    }

    public function test_assigning_an_already_booked_room_is_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);

        $booking = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'status' => ReservationStatus::Confirmee,
            'unit_rate' => 75000,
        ]);
        // Paid in full: a guaranteed booking holds the room.
        $booking->invoice->settle([['method' => PaymentMethod::Wave, 'amount' => $booking->total_amount]], now());

        $response = $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Fatou',
            'new_last_name' => 'Diarra',
            'new_phone' => '0708090001',
            'pricing_mode' => 'nuitee',
            'duration' => 1,
            'adults' => 1,
            'children' => 0,
            'unit_rate' => 75000,
            'payments' => [['method' => 'wave', 'amount' => 75000]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_check_in_and_check_out_update_room_status(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $reservation = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => ReservationStatus::Confirmee,
            'unit_rate' => 75000,
        ]);

        $this->actingAs($user)
            ->post("/admin/reservations/{$reservation->id}/check-in")
            ->assertRedirect();
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);

        $this->actingAs($user)
            ->post("/admin/affectations/{$reservation->id}/check-out")
            ->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'est de nouveau libre'));
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status); // free at once (client request)
        $this->assertTrue($room->fresh()->isAvailableNow());
    }

    public function test_recording_a_full_payment_marks_the_invoice_as_paid(): void
    {
        $user = User::factory()->create();
        $reservation = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => ReservationStatus::Confirmee,
            'unit_rate' => 75000,
        ]);
        $invoice = $reservation->invoice;

        $this->actingAs($user)->post("/admin/invoices/{$invoice->id}/payments", [
            'amount' => 75000,
            'method' => 'especes',
            'paid_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame('payee', $invoice->fresh()->status->value);
    }
}
