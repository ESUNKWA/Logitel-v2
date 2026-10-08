<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function rooms(): void
    {
        $this->travelTo('2026-10-08 10:00:00');

        Room::factory()->create(['number' => 'HS-1', 'status' => RoomStatus::HorsService]);
        Room::factory()->create(['number' => 'L-10', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['number' => 'N-1', 'status' => RoomStatus::Nettoyage]);
        Room::factory()->create(['number' => 'L-2', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['number' => 'O-1', 'status' => RoomStatus::Occupee]);
        $reserved = Room::factory()->create(['number' => 'R-1', 'status' => RoomStatus::Disponible]);
        Reservation::factory()->guaranteed()->create([
            'room_id' => $reserved->id,
            'check_in' => '2026-10-08',
            'check_out' => '2026-10-09',
            'status' => ReservationStatus::Confirmee,
        ]);
    }

    public function test_rooms_are_ordered_by_state_then_naturally_by_number(): void
    {
        $this->rooms();

        $sorted = Room::sortForDisplay(Room::with('pendingArrival')->get());

        // Occupées, Libres (2 before 10), Réservées, En nettoyage, Hors service
        $this->assertSame(['O-1', 'L-2', 'L-10', 'R-1', 'N-1', 'HS-1'], $sorted->pluck('number')->all());
    }

    public function test_board_shows_one_section_per_state_in_order(): void
    {
        $this->rooms();

        $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertOk()
            ->assertSeeInOrder(['id="etat-occupee"', 'N°O-1', 'id="etat-disponible"', 'N°L-2', 'N°L-10', 'id="etat-reservee"', 'N°R-1', 'id="etat-nettoyage"', 'N°N-1', 'id="etat-hors_service"', 'N°HS-1'], false);
    }

    public function test_room_list_is_grouped_by_state_with_header_rows(): void
    {
        $this->rooms();

        $this->actingAs(User::factory()->create())->get('/admin/rooms')->assertOk()
            ->assertSeeInOrder(['Occupées', '<td>O-1</td>', 'Libres', '<td>L-2</td>', '<td>L-10</td>', 'Réservées', '<td>R-1</td>', 'En nettoyage', '<td>N-1</td>', 'Hors service', '<td>HS-1</td>'], false);
    }

    public function test_status_filter_and_pagination_still_work(): void
    {
        $this->rooms();
        Room::factory()->count(25)->create(['status' => RoomStatus::Disponible]);

        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/rooms?status=occupee')->assertOk()
            ->assertSee('<td>O-1</td>', false)->assertDontSee('<td>L-2</td>', false);

        $this->actingAs($user)->get('/admin/rooms?page=2')->assertOk()
            ->assertSee('<td>HS-1</td>', false)                 // last group lands on page 2
            ->assertDontSee('<td>O-1</td>', false)
            ->assertSee('page=1', false);                        // pagination links rendered
    }

    public function test_board_renders_one_tab_per_state_with_empty_states_disabled(): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        Room::factory()->create(['number' => 'L-1', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['number' => 'O-1', 'status' => RoomStatus::Occupee]);

        $response = $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSeeInOrder(['data-room-tab="occupee"', 'data-room-tab="disponible"', 'data-room-tab="reservee"', 'data-room-tab="nettoyage"', 'data-room-tab="hors_service"'], false)
            ->assertSee('role="tabpanel" aria-labelledby="tab-occupee" data-room-panel="occupee"', false)
            ->assertSee('data-room-panel="disponible"', false)
            ->assertDontSee('data-room-panel="nettoyage"', false);   // no room: no panel

        // Empty states get a disabled tab
        $this->assertMatchesRegularExpression('/data-room-tab="nettoyage"\s+disabled/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/data-room-tab="disponible"\s+disabled/', $response->getContent());
    }
}
