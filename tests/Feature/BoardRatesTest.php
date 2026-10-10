<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room board: prices shown on free room cards and a rate grid per room type, to inform guests.
 */
class BoardRatesTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $confort;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->confort = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'day_rate' => 15000, 'night_rate' => 25000, 'stay_rate' => null]);
    }

    public function test_free_room_cards_show_their_formulas_and_prices(): void
    {
        $this->travelTo('2026-10-08 14:00:00'); // the nuitée window (22:00 → 12:00) is closed
        Room::factory()->create(['number' => 'L-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);

        $html = $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="room-card__rates"'));
        $card = substr($html, strpos($html, 'N°L-1'));
        $card = substr($card, 0, strpos($card, '</ul>'));
        $this->assertStringContainsString("À l'heure", html_entity_decode($card, ENT_QUOTES));
        $this->assertStringContainsString('5 000', $card);
        $this->assertStringContainsString('15 000', $card);
        $this->assertStringContainsString('25 000', $card);
        $this->assertStringNotContainsString('Séjour', $card); // no stay rate for this type
        $this->assertStringContainsString('dès 22h00', $card);  // announced, not yet open
    }

    public function test_the_nuitee_mention_disappears_once_open(): void
    {
        $this->travelTo('2026-10-08 22:30:00');
        Room::factory()->create(['number' => 'L-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);

        $html = $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->getContent();
        $card = substr($html, strpos($html, 'N°L-1'));
        $this->assertStringNotContainsString('dès 22h00', substr($card, 0, strpos($card, '</ul>')));
    }

    public function test_only_free_rooms_show_prices(): void
    {
        Room::factory()->create(['number' => 'L-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['number' => 'N-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Nettoyage]);
        Room::factory()->create(['number' => 'HS-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::HorsService]);
        $occupied = Room::factory()->create(['number' => 'O-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['room_id' => $occupied->id, 'check_in' => now()->subHour(), 'check_out' => now()->addHours(2), 'status' => 'confirmee']);
        $stay->update(['status' => 'enregistree']);
        $reserved = Room::factory()->create(['number' => 'R-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        Reservation::factory()->guaranteed()->create(['room_id' => $reserved->id, 'check_in' => now()->addHours(3), 'check_out' => now()->addHours(5), 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $html = $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->getContent();

        $this->assertSame(1, substr_count($html, 'class="room-card__rates"'), 'Only the free room L-1 shows its prices.');
    }

    public function test_the_rate_grid_lists_active_types_with_their_free_rooms(): void
    {
        $suite = RoomType::factory()->create(['name' => 'Suite', 'hourly_rate' => null, 'day_rate' => null, 'night_rate' => 50000, 'stay_rate' => 80000]);
        RoomType::factory()->create(['name' => 'Ancienne', 'hourly_rate' => 1000, 'is_active' => false]);                                  // inactive
        RoomType::factory()->create(['name' => 'Sans prix', 'hourly_rate' => null, 'day_rate' => null, 'night_rate' => null, 'stay_rate' => null]); // no formula
        Room::factory()->count(2)->create(['room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        Room::factory()->create(['room_type_id' => $this->confort->id, 'status' => RoomStatus::Nettoyage]);
        Room::factory()->create(['room_type_id' => $suite->id, 'status' => RoomStatus::HorsService]);

        $response = $this->actingAs(User::factory()->reception()->create())->get('/admin/rooms/board')->assertOk()
            ->assertSee('Grille tarifaire')
            ->assertSee('data-rate-grid', false)
            ->assertDontSee('Ancienne')
            ->assertDontSee('Sans prix');

        $grid = $response->getContent();
        $grid = substr($grid, strpos($grid, 'data-rate-grid'));
        $grid = substr($grid, 0, strpos($grid, '</details>'));
        $this->assertMatchesRegularExpression('#Confort.*?<strong>5 000</strong>.*?<strong>15 000</strong>.*?<strong>25 000</strong>.*?—.*?>2</span>#s', $grid);
        $this->assertMatchesRegularExpression('#Suite.*?—.*?—.*?<strong>50 000</strong>.*?<strong>80 000</strong>.*?>0</span>#s', $grid);
    }

    public function test_no_grid_when_no_room_type_has_a_price(): void
    {
        $this->confort->update(['hourly_rate' => null, 'day_rate' => null, 'night_rate' => null]);

        $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertOk()->assertDontSee('data-rate-grid', false);
    }
}
