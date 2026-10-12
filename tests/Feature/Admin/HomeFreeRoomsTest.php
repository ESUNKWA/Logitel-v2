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
 * « Chambres disponibles » on the home page: for receptionists only, free rooms by type,
 * each opening the assignment modal on the board; refreshed live.
 */
class HomeFreeRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-12 10:00:00');

        $suite = RoomType::factory()->create(['name' => 'Suite']);
        $standard = RoomType::factory()->create(['name' => 'Standard']);
        Room::factory()->create(['room_type_id' => $standard->id, 'number' => '10', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['room_type_id' => $standard->id, 'number' => '9', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['room_type_id' => $suite->id, 'number' => '201', 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['room_type_id' => $suite->id, 'number' => '202', 'status' => RoomStatus::Nettoyage]);
        Room::factory()->create(['room_type_id' => $suite->id, 'number' => '203', 'status' => RoomStatus::HorsService]);
        $occupied = Room::factory()->create(['room_type_id' => $standard->id, 'number' => '11', 'status' => RoomStatus::Occupee]);
        Reservation::factory()->create(['room_id' => $occupied->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => now()->subHour(), 'duration' => 3, 'status' => 'enregistree', 'origin' => Reservation::ORIGIN_AFFECTATION]);
    }

    public function test_receptionists_see_the_free_rooms_by_type_on_their_home(): void
    {
        $room9 = Room::query()->where('number', '9')->first();

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee('Chambres disponibles')
            ->assertSee('3 libres')
            ->assertSeeInOrder(['Standard', 'N°9', 'N°10', 'Suite', 'N°201'], false)
            ->assertDontSee('>N°11<', false)
            ->assertDontSee('>N°202<', false)
            ->assertDontSee('>N°203<', false)
            ->assertSee(route('admin.rooms.board', ['affecter' => $room9->id]).'#disponible', false)
            ->assertSee('data-live-block="free-rooms"', false)
            // Two columns: the usual blocks on the left, the free rooms on the right.
            ->assertSeeInOrder(['class="home-layout"', 'class="home-layout__main"', 'class="home-tiles"', '<aside class="home-layout__side" data-live-block="free-rooms">'], false);
    }

    public function test_empty_state_when_no_room_is_free(): void
    {
        Room::query()->update(['status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee('Aucune chambre libre pour le moment.');
    }

    public function test_the_manager_does_not_get_the_block(): void
    {
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertDontSee('Chambres disponibles')
            ->assertDontSee('data-live-block="free-rooms"', false)
            ->assertDontSee('class="home-layout"', false);

        $this->getJson('/admin/chiffres-en-direct?blocs=free-rooms')->assertOk()->assertJsonMissingPath('blocks.free-rooms');
    }

    public function test_the_block_is_refreshed_live_for_receptionists(): void
    {
        $this->actingAs(User::factory()->reception()->simpleUi()->create());
        Room::query()->where('number', '201')->update(['status' => RoomStatus::Nettoyage]);

        $html = $this->getJson('/admin/chiffres-en-direct?blocs=free-rooms')->assertOk()->json('blocks.free-rooms');
        $this->assertStringContainsString('2 libres', $html);
        $this->assertStringNotContainsString('N°201', $html);
    }
}
