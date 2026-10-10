<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomAmenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return [
            'room_type_id' => RoomType::factory()->create(['name' => 'Chambre Standard'])->id,
            'number' => '601',
            'floor' => '6',
            'status' => 'disponible',
            'presentation' => '1',
            ...$extra,
        ];
    }

    public function test_the_public_presentation_is_saved_with_the_room(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/rooms', $this->payload([
            'is_published' => '1',
            'public_name' => 'Chambre Standard Vue Jardin',
            'tagline' => 'Un excellent choix pour un séjour reposant',
            'surface' => 40,
            'beds' => 2,
            'bathrooms' => 1,
            'description' => "Décoration élégante.\n\nBalcon orienté sud.",
            'amenities' => ['wifi', 'climatisation', 'douche'],
            'features' => "Bouilloire / cafetière\n\n  Literie haut de gamme  \nBouilloire / cafetière",
            'rules' => "Arrivée à partir de 15h00\r\nDépart avant 11h00",
            'cancellation_policy' => 'Annulation gratuite jusqu\'à 48 h avant l\'arrivée.',
        ]))->assertRedirect(route('admin.rooms.index'))->assertSessionHasNoErrors();

        $room = Room::where('number', '601')->firstOrFail();
        $this->assertTrue($room->is_published);
        $this->assertSame('Chambre Standard Vue Jardin', $room->displayName());
        $this->assertSame(40, $room->surface);
        $this->assertSame(2, $room->beds);
        $this->assertSame([RoomAmenity::Wifi, RoomAmenity::Climatisation, RoomAmenity::Douche], $room->amenities->all());
        // one item per line, trimmed, blank lines and duplicates dropped
        $this->assertSame(['Bouilloire / cafetière', 'Literie haut de gamme'], $room->features);
        $this->assertSame(['Arrivée à partir de 15h00', 'Départ avant 11h00'], $room->rules);

        $this->actingAs(User::factory()->create())->get("/admin/rooms/{$room->id}/edit")->assertOk()
            ->assertSee('Présentation sur le site web')
            ->assertSee('value="Chambre Standard Vue Jardin"', false)
            ->assertSee('id="amenity-wifi" value="wifi" checked', false)
            ->assertSee("Bouilloire / cafetière\nLiterie haut de gamme", false);
    }

    public function test_a_room_can_be_created_with_empty_features_and_rules(): void
    {
        // Empty textareas arrive as null: they used to fail with "must be an array".
        $this->actingAs(User::factory()->create())
            ->post('/admin/rooms', $this->payload(['features' => '', 'rules' => '', 'description' => '']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $room = Room::where('number', '601')->sole();
        $this->assertSame([], $room->features ?? []);
        $this->assertSame([], $room->rules ?? []);
    }

    public function test_emptying_the_textareas_clears_the_lists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/rooms', $this->payload(['features' => "Climatisation\nWi-Fi", 'rules' => 'Non-fumeur']));
        $room = Room::where('number', '601')->sole();
        $this->assertSame(['Climatisation', 'Wi-Fi'], $room->features);

        $this->actingAs($user)->put("/admin/rooms/{$room->id}", [
            'room_type_id' => $room->room_type_id, 'number' => '601', 'floor' => '6', 'status' => 'disponible',
            'presentation' => '1', 'features' => '', 'rules' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame([], $room->fresh()->features ?? []);
        $this->assertSame([], $room->fresh()->rules ?? []);
    }

    public function test_the_display_name_defaults_to_the_room_type(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/rooms', $this->payload())->assertSessionHasNoErrors();

        $room = Room::where('number', '601')->firstOrFail();
        $this->assertSame('Chambre Standard', $room->displayName());
        $this->assertFalse($room->is_published);
    }

    public function test_unticking_every_amenity_clears_them(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post('/admin/rooms', $this->payload(['amenities' => ['wifi', 'piscine']]));
        $room = Room::where('number', '601')->firstOrFail();

        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload(['number' => '601']))->assertSessionHasNoErrors();

        $this->assertSame([], $room->fresh()->amenities->all());
    }

    public function test_invalid_values_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/rooms', $this->payload([
            'amenities' => ['wifi', 'jacuzzi'],
            'surface' => -5,
            'beds' => 'deux',
            'features' => implode("\n", array_map(fn ($i) => "Caractéristique {$i}", range(1, 21))),
            'tagline' => str_repeat('x', 161),
        ]))->assertSessionHasErrors(['amenities.1', 'surface', 'beds', 'features', 'tagline']);

        $this->assertSame(0, Room::count());
    }

    public function test_reception_can_change_the_status_without_touching_the_presentation(): void
    {
        $room = Room::factory()->create(['status' => 'nettoyage', 'public_name' => 'Suite Lagune', 'is_published' => true, 'surface' => 55]);
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get("/admin/rooms/{$room->id}/edit")->assertOk()->assertDontSee('Présentation sur le site web');

        // The réception form has no presentation fields: saving the status leaves them alone.
        $this->actingAs($reception)->put("/admin/rooms/{$room->id}", [
            'room_type_id' => $room->room_type_id, 'number' => $room->number, 'floor' => $room->floor, 'status' => 'disponible',
        ])->assertSessionHasNoErrors();
        $room->refresh();
        $this->assertSame('disponible', $room->status->value);
        $this->assertSame('Suite Lagune', $room->public_name);
        $this->assertTrue($room->is_published);

        // …and forging them is refused, with nothing saved.
        $this->actingAs($reception)->put("/admin/rooms/{$room->id}", [
            'room_type_id' => $room->room_type_id, 'number' => $room->number, 'status' => 'hors_service', 'is_published' => '0',
        ])->assertForbidden();
        $this->assertSame('disponible', $room->fresh()->status->value);
        $this->assertTrue($room->fresh()->is_published);
    }
}
