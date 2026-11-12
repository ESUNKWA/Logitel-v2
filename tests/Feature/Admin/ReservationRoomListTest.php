<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réservation form (client request, 2026-11-11): type and room on the same row, the type's rooms
 * listed as soon as it's chosen, then only the free ones once the arrival is typed — where every
 * pending booking counts, paid or not (decision A), while affectations keep the old rule.
 */
class ReservationRoomListTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-11 10:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Deluxe']);
        $this->actingAs(User::factory()->create());
    }

    private function room(string $number, RoomStatus $status = RoomStatus::Disponible, ?RoomType $type = null): Room
    {
        return Room::factory()->create(['number' => $number, 'room_type_id' => ($type ?? $this->type)->id, 'status' => $status]);
    }

    public function test_choosing_the_type_lists_its_rooms_before_the_arrival(): void
    {
        $this->room('10');
        $this->room('9');
        $this->room('11', RoomStatus::Occupee);          // occupied now, maybe free later: listed
        $this->room('12', RoomStatus::HorsService);      // never bookable
        $this->room('20', RoomStatus::Disponible, RoomType::factory()->create());

        $rooms = $this->getJson(route('admin.rooms.available', ['room_type_id' => $this->type->id, 'pricing_mode' => 'nuitee']))
            ->assertOk()->json();

        $this->assertSame(['N°9 — Deluxe', 'N°10 — Deluxe', 'N°11 — Deluxe'], array_column($rooms, 'label')); // natural order
        $this->assertSame([false, false, false], array_column($rooms, 'checked'));
        $this->assertEquals(75000, $rooms[0]['rate']);

        // Neither type nor arrival: refused.
        $this->getJson(route('admin.rooms.available'))->assertUnprocessable();
        $this->getJson(route('admin.rooms.available', ['room_type_id' => 'x']))->assertUnprocessable();
    }

    public function test_with_the_arrival_only_free_rooms_remain_and_an_unpaid_booking_counts(): void
    {
        $free = $this->room('1');
        $booked = $this->room('2');
        Reservation::factory()->create(['room_id' => $booked->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-11-20 14:00', 'duration' => 3, 'status' => 'en_attente', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $query = ['room_type_id' => $this->type->id, 'pricing_mode' => 'heure', 'check_in' => '2026-11-20T15:00', 'duration' => 1];
        $rooms = $this->getJson(route('admin.rooms.available', $query))->assertOk()->json();
        $this->assertSame([$free->id], array_column($rooms, 'id'));
        $this->assertSame([true], array_column($rooms, 'checked'));

        // Another time of the day (after 17:00 + cleaning): both free.
        $later = $this->getJson(route('admin.rooms.available', ['check_in' => '2026-11-20T17:30'] + $query))->json();
        $this->assertSame([$free->id, $booked->id], array_column($later, 'id'));

        // The booking being edited doesn't hide its own room.
        $own = Reservation::query()->firstOrFail();
        $edit = $this->getJson(route('admin.rooms.available', ['exclude_reservation_id' => $own->id] + $query))->json();
        $this->assertContains($booked->id, array_column($edit, 'id'));
    }

    public function test_the_desk_can_still_assign_a_room_over_an_unpaid_booking(): void
    {
        $room = $this->room('5');
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-11-11 11:00', 'duration' => 3, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $this->post(route('admin.rooms.assign', $room), [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 10000]],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($booking->fresh()->takenBy());
    }

    public function test_the_form_puts_type_and_room_on_the_same_row(): void
    {
        $this->room('1');

        $this->get(route('admin.reservations.create'))->assertOk()
            ->assertSeeInOrder(['<div class="col-6">', 'for="booking_room_type">Type de chambre<', '<div class="col-6">', 'for="room_id">Chambre<', 'data-room-hint', 'Mode'], false);
    }

    public function test_a_form_sent_back_keeps_the_chosen_room(): void
    {
        $room = $this->room('1');

        $this->from(route('admin.reservations.create'))->post(route('admin.reservations.store'), [
            'room_type_id' => $this->type->id, 'room_id' => $room->id, 'pricing_mode' => 'nuitee', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'status' => 'confirmee', 'arrival_date' => '2026-11-20', 'arrival_time' => '22:00',
        ])->assertSessionHasErrors('new_phone');

        $this->get(route('admin.reservations.create'))->assertOk()->assertSee('data-old-room="'.$room->id.'"', false);
    }
}
