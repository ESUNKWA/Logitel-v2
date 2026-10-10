<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Suite Prestige',
            'description' => 'Vue sur jardin',
            'hourly_rate' => 10000,
            'day_rate' => 30000,
            'night_rate' => 45000,
            'stay_rate' => 60000,
            'capacity_adults' => 2,
            'capacity_children' => 1,
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_room_type_is_created_with_its_three_rates(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/room-types', $this->payload())
            ->assertRedirect(route('admin.room-types.index'))
            ->assertSessionHasNoErrors();

        $type = RoomType::where('name', 'Suite Prestige')->firstOrFail();
        $this->assertSame('10000.00', $type->hourly_rate);
        $this->assertSame('30000.00', $type->day_rate);
        $this->assertSame('45000.00', $type->night_rate);
        $this->assertSame('60000.00', $type->stay_rate);
        $this->assertSame('suite-prestige', $type->slug);
    }

    public function test_every_rate_is_optional(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/room-types', $this->payload(['hourly_rate' => '', 'day_rate' => '', 'night_rate' => '', 'stay_rate' => '']))
            ->assertSessionHasNoErrors();

        $type = RoomType::where('name', 'Suite Prestige')->firstOrFail();
        $this->assertNull($type->hourly_rate);
        $this->assertNull($type->day_rate);
        $this->assertNull($type->night_rate);
        $this->assertNull($type->stay_rate);

        $this->actingAs(User::factory()->create())
            ->get('/admin/room-types')
            ->assertOk()
            ->assertSee('Suite Prestige');
    }

    public function test_invalid_rates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/room-types', $this->payload(['hourly_rate' => -1, 'night_rate' => 'abc']))
            ->assertSessionHasErrors(['hourly_rate', 'night_rate']);

        $this->assertDatabaseMissing('room_types', ['name' => 'Suite Prestige']);
    }

    public function test_room_type_names_are_unique(): void
    {
        RoomType::factory()->create(['name' => 'Suite Prestige']);

        $this->actingAs(User::factory()->create())
            ->post('/admin/room-types', $this->payload())
            ->assertSessionHasErrors('name');
    }

    public function test_updating_rates_keeps_the_slug(): void
    {
        $type = RoomType::factory()->create(['name' => 'Chambre Simple', 'slug' => 'chambre-simple']);

        $this->actingAs(User::factory()->create())
            ->put("/admin/room-types/{$type->id}", $this->payload(['name' => 'Chambre Simple', 'night_rate' => 30000, 'hourly_rate' => '']))
            ->assertSessionHasNoErrors();

        $type->refresh();
        $this->assertSame('30000.00', $type->night_rate);
        $this->assertNull($type->hourly_rate);
        $this->assertSame('chambre-simple', $type->slug);
    }

    public function test_night_rate_prefills_the_reservation_rate(): void
    {
        $type = RoomType::factory()->create(['night_rate' => 42000]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()->assertSee('data-night-rate="42000.00"', false);

        $this->actingAs($user)
            ->getJson('/admin/rooms/available?check_in='.today()->addDay()->toDateString().'&check_out='.today()->addDays(2)->toDateString())
            ->assertOk()
            ->assertJsonFragment(['id' => $room->id, 'rate' => '42000.00']);
    }
}
