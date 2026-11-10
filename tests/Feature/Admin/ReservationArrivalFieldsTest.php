<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réservation form (client request, 2026-11-10): the arrival is a date + a time, two separate
 * mandatory fields, empty on a new booking; no search of existing clients any more.
 */
class ReservationArrivalFieldsTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->room = Room::factory()->create(['number' => 'AF-1', 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $this->staff = User::factory()->create();
    }

    private function book(array $data)
    {
        return $this->actingAs($this->staff)->post('/admin/reservations', [
            'room_type_id' => $this->room->room_type_id, 'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'duration' => 2,
            'adults' => 2, 'children' => 0, 'status' => 'confirmee', 'new_phone' => '0707070707', ...$data,
        ]);
    }

    public function test_the_new_booking_form_has_empty_mandatory_date_and_time_and_no_client_search(): void
    {
        $this->actingAs($this->staff)->get('/admin/reservations/create')->assertOk()
            ->assertSeeInOrder(["Date d'arrivée", '<input type="date" class="form-control " id="arrival_date" name="arrival_date" value="" data-arrival-date data-availability-trigger required>',
                "Heure d'arrivée", '<input type="time" class="form-control " id="arrival_time" name="arrival_time" value="" data-arrival-time data-availability-trigger required>'], false)
            ->assertSee('<input type="hidden" name="check_in" value="" data-check-in-input>', false)
            ->assertDontSee('datetime-local', false)
            ->assertDontSee('data-guest-search', false)->assertDontSee('name="guest_id"', false)
            ->assertSee('name="new_phone" value=""', false);
        $this->assertMatchesRegularExpression('/data-new-client-phone\s+required\s*>/', $this->get('/admin/reservations/create')->getContent());
    }

    public function test_the_arrival_is_built_from_the_date_and_the_time(): void
    {
        // The hidden check_in is ignored: the two fields win.
        $this->book(['arrival_date' => '2026-10-12', 'arrival_time' => '14:30', 'check_in' => '2030-01-01 08:00'])->assertSessionHasNoErrors();

        $booking = Reservation::sole();
        $this->assertSame('2026-10-12 14:30', $booking->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-12 16:30', $booking->check_out->format('Y-m-d H:i'));
    }

    public function test_a_nuitee_starts_at_the_time_typed(): void
    {
        $this->book(['pricing_mode' => 'nuitee', 'duration' => 1, 'arrival_date' => '2026-10-12', 'arrival_time' => '22:00'])->assertSessionHasNoErrors();

        $booking = Reservation::sole();
        $this->assertSame('2026-10-12 22:00', $booking->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 12:00', $booking->check_out->format('Y-m-d H:i'));
    }

    public function test_date_and_time_are_both_mandatory_and_checked(): void
    {
        $this->book(['arrival_date' => '', 'arrival_time' => '14:30'])->assertSessionHasErrors(['arrival_date' => "La date d'arrivée est obligatoire."]);
        $this->book(['arrival_date' => '2026-10-12', 'arrival_time' => ''])->assertSessionHasErrors(['arrival_time' => "L'heure d'arrivée est obligatoire."]);
        $this->book(['arrival_date' => '2026-10-12'])->assertSessionHasErrors('arrival_time');
        $this->book(['arrival_time' => '14:30'])->assertSessionHasErrors('arrival_date');
        $this->book(['arrival_date' => '12/10/2026', 'arrival_time' => '2pm'])->assertSessionHasErrors(['arrival_date', 'arrival_time']);
        $this->book(['arrival_date' => '2026-02-31', 'arrival_time' => '14:30'])->assertSessionHasErrors('arrival_date');
        $this->book(['arrival_date' => ['2026-10-12'], 'arrival_time' => ['14:30']])->assertSessionHasErrors(['arrival_date', 'arrival_time']);

        // Still a booking for later: an arrival now or in the past is refused.
        $this->book(['arrival_date' => '2026-10-08', 'arrival_time' => '09:00'])->assertSessionHasErrors('check_in');

        $this->assertSame(0, Reservation::count());
    }

    public function test_a_failed_submission_keeps_what_was_typed(): void
    {
        $this->book(['arrival_date' => '2026-10-12', 'arrival_time' => ''])->assertSessionHasErrors('arrival_time');

        $this->get('/admin/reservations/create')->assertOk()
            ->assertSee('name="arrival_date" value="2026-10-12"', false)
            ->assertSee('name="arrival_time" value=""', false);
    }

    public function test_editing_shows_the_arrival_and_keeps_the_client_unless_replaced(): void
    {
        $this->book(['arrival_date' => '2026-10-12', 'arrival_time' => '14:30', 'new_first_name' => 'Awa', 'new_phone' => '0101010101'])->assertSessionHasNoErrors();
        $booking = Reservation::sole();
        $awa = $booking->guest;

        $this->get("/admin/reservations/{$booking->id}/edit")->assertOk()
            ->assertSee('name="arrival_date" value="2026-10-12"', false)
            ->assertSee('name="arrival_time" value="14:30"', false)
            ->assertSee('<strong>Awa</strong> — 0101010101', false)
            ->assertSee('<input type="hidden" name="guest_id" value="'.$awa->id.'" data-current-client-id >', false)
            ->assertSee('Remplacer par un nouveau client')
            ->assertSee('<div data-new-client  hidden >', false);

        $update = fn (array $client) => $this->put("/admin/reservations/{$booking->id}", [
            'room_type_id' => $this->room->room_type_id, 'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'duration' => 3,
            'arrival_date' => '2026-10-13', 'arrival_time' => '09:15', 'adults' => 2, 'children' => 0, 'status' => 'confirmee', ...$client,
        ]);

        // Kept: guest_id sent, new fields empty.
        $update(['guest_id' => $awa->id, 'new_phone' => ''])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame($awa->id, $booking->guest_id);
        $this->assertSame('2026-10-13 09:15', $booking->check_in->format('Y-m-d H:i'));
        $this->assertSame(3, $booking->duration);

        // Replaced (the box ticked: no guest_id sent) by a new client.
        $update(['replace_client' => '1', 'new_first_name' => 'Koffi', 'new_phone' => '0202020202'])->assertSessionHasNoErrors();
        $this->assertSame('Koffi', $booking->refresh()->guest->fullName());
        $this->assertSame($awa->id, Guest::where('phone', '0101010101')->value('id')); // the former client is untouched
    }

    public function test_an_online_request_matched_by_phone_shows_that_client(): void
    {
        $guest = Guest::factory()->create(['first_name' => 'Moussa', 'last_name' => 'Traoré', 'phone' => '0505050505']);
        $request = BookingRequest::create([
            'room_type_id' => $this->room->room_type_id, 'pricing_mode' => 'heure', 'check_in' => '2026-10-15 11:00', 'duration' => 2, 'unit_rate' => 5000,
            'adults' => 1, 'children' => 0, 'first_name' => 'Moussa', 'last_name' => 'Traoré', 'phone' => '05 05 05 05 05', 'status' => BookingRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->staff)->get('/admin/reservations/create?demande='.$request->id)->assertOk()
            ->assertSee('<strong>Moussa Traoré</strong>', false)
            ->assertSee('name="guest_id" value="'.$guest->id.'"', false)
            ->assertSee('name="arrival_date" value="2026-10-15"', false)
            ->assertSee('name="arrival_time" value="11:00"', false);
    }
}
