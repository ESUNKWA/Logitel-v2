<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_reservation_generates_an_invoice_with_the_correct_amount(): void
    {
        $reservation = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'status' => ReservationStatus::EnAttente,
            'unit_rate' => 75000,
        ]);

        $this->assertSame(3, $reservation->duration);
        $this->assertSame('225000.00', $reservation->total_amount);

        $this->assertNotNull($reservation->invoice);
        $this->assertSame('225000.00', $reservation->invoice->amount);
        $this->assertSame(InvoiceStatus::Impayee, $reservation->invoice->status);
        $this->assertStringStartsWith('INV-', $reservation->invoice->number);
    }

    public function test_room_status_updates_on_check_in_and_check_out(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);

        $reservation = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => ReservationStatus::EnAttente,
            'unit_rate' => 75000,
        ]);

        $reservation->update(['status' => ReservationStatus::Enregistree]);
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);

        $reservation->update(['status' => ReservationStatus::Terminee]);
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status); // check-out frees the room at once
    }

    public function test_room_is_not_available_when_it_has_an_overlapping_active_reservation(): void
    {
        $room = Room::factory()->create();

        $booking = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => '2027-01-10',
            'check_out' => '2027-01-15',
            'status' => ReservationStatus::Confirmee,
            'unit_rate' => 75000,
        ]);
        // A booking only holds its room once paid in full.
        $booking->invoice->settle([['method' => PaymentMethod::Wave, 'amount' => $booking->total_amount]], now());

        $this->assertFalse(
            Room::query()->whereKey($room->id)->availableBetween('2027-01-12 22:00', '2027-01-18 12:00')->exists()
        );

        // The nuitées end on 2027-01-15 at 12:00: the next stay may start after the 30-min cleaning.
        $this->assertFalse(
            Room::query()->whereKey($room->id)->availableBetween('2027-01-15 12:00', '2027-01-18 12:00')->exists()
        );
        $this->assertTrue(
            Room::query()->whereKey($room->id)->availableBetween('2027-01-15 12:30', '2027-01-18 12:00')->exists()
        );
    }

    public function test_room_is_available_when_the_overlapping_reservation_is_cancelled(): void
    {
        $room = Room::factory()->create();

        Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => $room->id,
            'check_in' => '2027-02-10',
            'check_out' => '2027-02-15',
            'status' => ReservationStatus::Annulee,
            'unit_rate' => 75000,
        ]);

        $this->assertTrue(
            Room::query()->whereKey($room->id)->availableBetween('2027-02-12', '2027-02-18')->exists()
        );
    }

    public function test_invoice_status_becomes_payee_once_fully_paid(): void
    {
        $reservation = Reservation::create([
            'guest_id' => Guest::factory()->create()->id,
            'room_id' => Room::factory()->create()->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => ReservationStatus::EnAttente,
            'unit_rate' => 75000,
        ]);

        $invoice = $reservation->invoice;

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 40000,
            'method' => PaymentMethod::Especes,
            'paid_at' => now(),
        ]);
        $this->assertSame(InvoiceStatus::PartiellementPayee, $invoice->fresh()->status);

        Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 35000,
            'method' => PaymentMethod::OrangeMoney,
            'paid_at' => now(),
        ]);
        $this->assertSame(InvoiceStatus::Payee, $invoice->fresh()->status);
    }
}
