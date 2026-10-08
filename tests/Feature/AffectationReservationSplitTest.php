<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Affectations (guest moves in now, room board) and réservations (booked ahead) are two
 * separate features sharing one guest file: the invoice and deposit follow the guest from the
 * réservation into the stay.
 */
class AffectationReservationSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
    }

    private function room(array $attributes = []): Room
    {
        $type = RoomType::factory()->create(['hourly_rate' => 5000, 'day_rate' => 20000, 'night_rate' => 40000, 'stay_rate' => 60000]);

        return Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible, ...$attributes]);
    }

    private function book(Room $room, array $data = [], ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create(['first_name' => 'Yao', 'last_name' => 'Kouamé'])->id,
            'room_id' => $room->id,
            'pricing_mode' => 'heure',
            'check_in' => '2026-10-10T14:00',
            'duration' => 3,
            'unit_rate' => 5000,
            'adults' => 1,
            'children' => 0,
            'status' => 'confirmee',
            ...$data,
        ]);
    }

    private function assignNow(Room $room)
    {
        return $this->actingAs(User::factory()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Traoré', 'new_phone' => '0101',
            'pricing_mode' => 'heure', 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 10000]], // an affectation is paid in full
        ]);
    }

    public function test_an_affectation_is_listed_only_on_the_affectations_side(): void
    {
        $room = $this->room(['number' => 'AF-1']);
        $this->assignNow($room)->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame(Reservation::ORIGIN_AFFECTATION, $stay->origin);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/affectations')->assertOk()->assertSee('N°AF-1')->assertSee('Awa Traoré');
        $this->actingAs($user)->get('/admin/reservations')->assertOk()->assertDontSee('Awa Traoré');
        $this->actingAs($user)->get("/admin/reservations/{$stay->id}/edit")->assertNotFound();
    }

    public function test_a_reservation_in_any_mode_is_listed_only_on_the_reservations_side(): void
    {
        foreach (['heure' => '2026-10-10T14:00', 'journee' => '2026-10-10T08:00', 'nuitee' => '2026-10-10', 'sejour' => '2026-10-10T12:00'] as $mode => $arrival) {
            $room = $this->room();
            $this->book($room, ['pricing_mode' => $mode, 'check_in' => $arrival, 'unit_rate' => PricingMode::from($mode)->rateFor($room->roomType)])
                ->assertRedirect(route('admin.reservations.index'))->assertSessionHasNoErrors();

            $booking = $room->reservations()->firstOrFail();
            $this->assertSame(Reservation::ORIGIN_RESERVATION, $booking->origin);
            $this->assertSame(PricingMode::from($mode), $booking->pricing_mode);
            $this->assertNotNull($booking->invoice, 'The invoice is created with the réservation.');
            $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
        }

        $nuitee = Reservation::where('pricing_mode', 'nuitee')->firstOrFail();
        $this->assertSame('2026-10-10 22:00', $nuitee->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 12:00', $nuitee->check_out->format('Y-m-d H:i')); // 3 nights

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/reservations')->assertOk()->assertSee('Yao Kouamé');
        $this->actingAs($user)->get('/admin/affectations')->assertOk()->assertDontSee('Yao Kouamé');
        $this->actingAs($user)->get('/admin/affectations/'.$nuitee->id)->assertNotFound();
    }

    public function test_a_reservation_must_be_for_later(): void
    {
        $room = $this->room();

        $this->book($room, ['check_in' => '2026-10-08T09:00'])->assertSessionHasErrors('check_in');
        $this->book($room, ['pricing_mode' => 'nuitee', 'check_in' => '2026-10-07', 'unit_rate' => 40000])->assertSessionHasErrors('check_in');
        $this->assertSame(0, Reservation::count());
    }

    public function test_a_mode_the_room_type_does_not_offer_is_refused(): void
    {
        $room = $this->room();
        $room->roomType->update(['day_rate' => null]);

        $this->book($room, ['pricing_mode' => 'journee', 'check_in' => '2026-10-10T08:00'])->assertSessionHasErrors('pricing_mode');
    }

    public function test_a_deposit_can_be_taken_when_booking(): void
    {
        $room = $this->room();

        $this->book($room, ['payments' => [['method' => 'especes', 'amount' => 5000, 'received' => 5000], ['method' => 'wave', 'amount' => 2000]]])
            ->assertSessionHasNoErrors();

        $invoice = $room->reservations()->firstOrFail()->invoice;
        $this->assertSame(7000.0, (float) $invoice->amountPaid());
        $this->assertSame(InvoiceStatus::PartiellementPayee, $invoice->status);
        $this->assertSame(1, $invoice->payments()->distinct()->count('settlement'));
    }

    public function test_a_deposit_above_the_total_cancels_the_booking(): void
    {
        $room = $this->room();

        $this->book($room, ['payments' => [['method' => 'especes', 'amount' => 20000]]])->assertSessionHasErrors('payments');
        $this->assertSame(0, Reservation::count());
    }

    public function test_a_deposit_requires_the_payment_permission(): void
    {
        $role = Role::create(['name' => 'Standardiste']);
        $role->syncPermissions(['reservations.view', 'reservations.manage', 'rooms.view']);
        $room = $this->room();

        $this->book($room, ['payments' => [['method' => 'especes', 'amount' => 1000, 'received' => 1000]]], User::factory()->create(['role_id' => $role->id]))
            ->assertForbidden();
        $this->assertSame(0, Reservation::count());
    }

    public function test_the_guest_arrival_turns_the_reservation_into_an_affectation_keeping_invoice_and_deposit(): void
    {
        $room = $this->room(['number' => 'AR-1']);
        $this->book($room, ['payments' => [['method' => 'orange_money', 'amount' => 5000]]])->assertSessionHasNoErrors();
        $booking = $room->reservations()->firstOrFail();
        $invoiceId = $booking->invoice->id;

        $this->travelTo('2026-10-10 14:05:00');
        $this->actingAs(User::factory()->reception()->create())
            ->post("/admin/reservations/{$booking->id}/check-in")
            ->assertRedirect(route('admin.affectations.show', $booking));

        $booking->refresh();
        $this->assertSame(ReservationStatus::Enregistree, $booking->status);
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
        $this->assertSame($invoiceId, $booking->invoice->id);
        $this->assertSame(5000.0, (float) $booking->invoice->amountPaid());

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/affectations')->assertSee('N°AR-1')->assertSee('sur réservation');
        $this->actingAs($user)->get('/admin/reservations')->assertSee('Honorée (client arrivé)');
        $this->actingAs($user)->get("/admin/reservations/{$booking->id}/edit")->assertRedirect(route('admin.affectations.show', $booking));
    }

    public function test_the_guest_cannot_be_checked_in_if_the_room_is_not_ready(): void
    {
        $room = $this->room();
        $this->book($room)->assertSessionHasNoErrors();
        $booking = $room->reservations()->firstOrFail();
        $room->update(['status' => RoomStatus::Nettoyage]);

        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/check-in")->assertSessionHasErrors('room_id');
        $this->assertSame(ReservationStatus::Confirmee, $booking->fresh()->status);
    }

    public function test_a_no_show_can_be_cancelled_or_postponed_by_the_manager(): void
    {
        $room = $this->room();
        $this->book($room, ['payments' => [['method' => 'especes', 'amount' => 5000, 'received' => 5000]]]);
        $booking = $room->reservations()->firstOrFail();
        $this->travelTo('2026-10-10 18:00:00'); // guest never came

        $this->actingAs(User::factory()->create())->get("/admin/reservations/{$booking->id}/edit")->assertOk()->assertSee('Client non arrivé');

        // Postpone ("reporter") to a later date
        $payload = ['guest_id' => $booking->guest_id, 'room_id' => $room->id, 'pricing_mode' => 'heure', 'duration' => 3,
            'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee'];
        $this->actingAs(User::factory()->create())->put("/admin/reservations/{$booking->id}", [...$payload, 'check_in' => '2026-10-12T14:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-12 14:00', $booking->fresh()->check_in->format('Y-m-d H:i'));

        // …but not into the past, nor below the deposit already taken
        $this->actingAs(User::factory()->create())->put("/admin/reservations/{$booking->id}", [...$payload, 'check_in' => '2026-10-09T14:00'])
            ->assertSessionHasErrors('check_in');
        $this->actingAs(User::factory()->create())->put("/admin/reservations/{$booking->id}", [...$payload, 'check_in' => '2026-10-12T14:00', 'duration' => 1, 'unit_rate' => 1000])
            ->assertSessionHasErrors('duration');

        // A booking whose whole period is over must be postponed before the guest can be checked in.
        $this->travelTo('2026-10-13 09:00:00');
        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/check-in")->assertSessionHasErrors('check_in');
        $this->assertSame(ReservationStatus::Confirmee, $booking->fresh()->status);

        // Cancel
        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/cancel");
        $this->assertSame(ReservationStatus::Annulee, $booking->fresh()->status);
        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/check-in")->assertNotFound();
    }

    public function test_affectation_actions_refuse_files_that_are_not_stays(): void
    {
        $room = $this->room();
        $this->book($room);
        $booking = $room->reservations()->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->post("/admin/affectations/{$booking->id}/check-out")->assertNotFound();
        $this->actingAs($user)->put("/admin/affectations/{$booking->id}", ['duration' => 5, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0])->assertNotFound();
        $this->assertSame(ReservationStatus::Confirmee, $booking->fresh()->status);
    }

    public function test_extending_an_affectation_over_a_reservation_is_refused(): void
    {
        $room = $this->room();
        $this->assignNow($room)->assertSessionHasNoErrors();         // 10:00 → 12:00
        $stay = $room->reservations()->firstOrFail();
        // 13:30 → 16:30 on the same room, paid in full (so it holds the room)
        $this->book($room, ['check_in' => '2026-10-08T13:30', 'payments' => [['method' => 'wave', 'amount' => 15000]]])->assertSessionHasNoErrors();

        // until 14:00 overlaps it; until 13:30 leaves no time for cleaning
        $this->actingAs(User::factory()->create())->put("/admin/affectations/{$stay->id}", ['duration' => 4, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0])
            ->assertSessionHasErrors('duration');
        $this->actingAs(User::factory()->create())->put("/admin/affectations/{$stay->id}", ['duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0])
            ->assertSessionHasNoErrors();                            // until 13:00 + 30 min cleaning = 13:30
        $this->assertSame('2026-10-08 13:00', $stay->fresh()->check_out->format('Y-m-d H:i'));
    }

    public function test_permissions_are_separate(): void
    {
        $bookingsOnly = Role::create(['name' => 'Réservations seules']);
        $bookingsOnly->syncPermissions(['reservations.view', 'reservations.manage', 'rooms.view']);
        $user = User::factory()->create(['role_id' => $bookingsOnly->id]);
        $room = $this->room();

        $this->actingAs($user)->get('/admin/reservations/create')->assertOk();
        $this->actingAs($user)->get('/admin/affectations')->assertForbidden();
        $this->assignNow($room); // as a full-rights user, to have a stay
        $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [])->assertForbidden();

        // Réception got the affectation permissions mirrored from its reservation ones.
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception)->get('/admin/affectations')->assertOk();
        $this->assertTrue($reception->hasPermission('affectations.manage'));
        $this->assertFalse($reception->hasPermission('affectations.cancel'));
    }

    public function test_pages_render(): void
    {
        $room = $this->room();
        $this->assignNow($room);
        $stay = $room->reservations()->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/affectations?onglet=terminees')->assertOk();
        $this->actingAs($user)->get('/admin/affectations?onglet=annulees')->assertOk();
        $this->actingAs($user)->get("/admin/affectations/{$stay->id}")->assertOk()->assertSee('Check-out')->assertSee('Prolonger');
        $this->actingAs($user)->get('/admin/reservations/create')->assertOk()
            ->assertSee('data-booking-form', false)->assertSee('booking-mode-journee', false)->assertSee('Acompte');
    }
}
