<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * New affectations and réservations start with 2 adults (client request, 2026-10-17); existing
 * files, online requests and re-submitted forms keep their own number.
 */
class DefaultOccupantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_affectations_and_reservations_default_to_two_adults(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $this->actingAs(User::factory()->create());
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        $this->get('/admin/rooms/board')->assertOk()
            ->assertSee('<input type="number" min="1" value="2" class="form-control" name="adults" required>', false);
        $this->get(route('admin.reservations.create'))->assertOk()
            ->assertSee('id="adults" name="adults" value="2" required>', false);

        // An existing booking keeps its own number.
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-20 22:00', 'duration' => 1, 'adults' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->get(route('admin.reservations.edit', $booking))->assertOk()->assertSee('id="adults" name="adults" value="1" required>', false);

        // A re-submitted form keeps what was typed.
        $this->from('/admin/rooms/board')->post("/admin/rooms/{$room->id}/assign", ['pricing_mode' => 'heure', 'duration' => 1, 'adults' => 3, 'children' => 0]);
        $this->get('/admin/rooms/board')->assertOk()->assertSee('value="3" class="form-control" name="adults"', false);
    }

    public function test_an_online_request_keeps_the_guest_s_number(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $this->actingAs(User::factory()->create());
        $type = RoomType::factory()->create();
        Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible, 'is_published' => true]);
        $request = BookingRequest::create([
            'room_type_id' => $type->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-20 22:00', 'duration' => 1,
            'adults' => 1, 'children' => 0, 'unit_rate' => 75000, 'first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '0711223344',
        ]);

        $this->get(route('admin.reservations.create', ['demande' => $request->id]))->assertOk()
            ->assertSee('id="adults" name="adults" value="1" required>', false);
    }
}
