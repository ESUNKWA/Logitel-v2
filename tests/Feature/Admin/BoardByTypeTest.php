<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room board, « Libres » tab: free rooms grouped by room type.
 */
class BoardByTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_rooms_are_grouped_by_type_with_count_and_lowest_rate(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $suite = RoomType::factory()->create(['name' => 'Suite', 'hourly_rate' => null, 'day_rate' => null, 'night_rate' => 45000, 'stay_rate' => 60000]);
        $standard = RoomType::factory()->create(['name' => 'Standard', 'hourly_rate' => 5000]);
        Room::factory()->create(['room_type_id' => $suite->id, 'number' => '201']);
        Room::factory()->create(['room_type_id' => $standard->id, 'number' => '102']);
        Room::factory()->create(['room_type_id' => $standard->id, 'number' => '101']);
        Room::factory()->create(['room_type_id' => $standard->id, 'number' => '103', 'status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->create())->get(route('admin.rooms.board'))->assertOk()
            ->assertSeeInOrder([
                'data-room-panel="disponible"',
                'room-type-heading__name">Standard', '2 libres', 'dès 5 000 F / heure', 'N°101', 'N°102',
                'room-type-heading__name">Suite', '1 libre', 'dès 45 000 F / nuit', 'N°201',
                'data-room-panel="hors_service"', 'N°103',
            ], false);
    }
}
