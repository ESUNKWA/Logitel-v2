<?php

namespace Tests\Feature\Site;

use App\Enums\RoomAmenity;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website room pages, built only from what the application publishes.
 */
class PublicRoomsTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $confort;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 14:00:00');
        // Hourly / day / night rates exist but the website only shows the séjour (PricingMode::WEBSITE).
        $this->confort = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'day_rate' => 15000, 'night_rate' => 25000, 'stay_rate' => 45000, 'capacity_adults' => 2, 'capacity_children' => 1]);
    }

    private int $rooms = 0;

    private function room(array $attributes = []): Room
    {
        // Room names are unique: « Suite Lagune », then « Suite Lagune 2 »…
        $this->rooms++;

        return Room::factory()->create([
            'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible, 'is_published' => true,
            'public_name' => 'Suite Lagune'.($this->rooms > 1 ? ' '.$this->rooms : ''), 'tagline' => 'Vue sur le jardin', 'surface' => 32, 'beds' => 2, 'bathrooms' => 1,
            'description' => "Une chambre lumineuse.\n\nAvec un balcon.", 'amenities' => [RoomAmenity::Wifi, RoomAmenity::Climatisation],
            'features' => ['Bouilloire', 'Coffre-fort'], 'rules' => ['Non-fumeur'], 'cancellation_policy' => 'Annulation gratuite 24 h avant.',
            ...$attributes,
        ]);
    }

    public function test_the_list_shows_only_published_rooms_of_active_types(): void
    {
        $this->room(['number' => 'P-1']);
        $this->room(['number' => 'P-2', 'public_name' => 'Brouillon', 'is_published' => false]);
        $this->room(['number' => 'P-3', 'public_name' => 'Type inactif', 'room_type_id' => RoomType::factory()->create(['is_active' => false])->id]);

        $this->get('/chambres')->assertOk()
            ->assertSee('Suite Lagune')->assertSee('Confort')->assertSee('à partir de')->assertSee('45 000 FCFA')->assertSee('/ 24 h')
            ->assertDontSee('/ heure')->assertDontSee('/ nuit')->assertDontSee('/ journée')
            ->assertSee('2 pers.')->assertSee('32 m²')->assertSee('2 lits')
            ->assertDontSee('Brouillon')->assertDontSee('Type inactif')
            ->assertDontSee('635')->assertDontSee('Clients heureux'); // no fictive figures any more
    }

    public function test_the_list_says_so_when_nothing_is_published_yet(): void
    {
        $this->get('/chambres')->assertOk()->assertSee('bientôt présentées')->assertSee(route('contact'), false);
    }

    public function test_the_list_offers_type_filters_when_there_are_several_types(): void
    {
        $this->room();
        $this->room(['public_name' => 'Grande suite', 'room_type_id' => RoomType::factory()->create(['name' => 'Suite', 'night_rate' => 60000])->id]);

        $this->get('/chambres')->assertOk()->assertSee('data-filter="*"', false)->assertSeeInOrder(['Confort', 'Suite']);
    }

    public function test_the_room_page_shows_its_presentation_rates_and_photos(): void
    {
        $room = $this->room();
        $room->images()->create(['path' => 'rooms/'.$room->id.'/a.jpg', 'position' => 1]);
        $room->images()->create(['path' => 'rooms/'.$room->id.'/b.jpg', 'position' => 2]);

        $this->get($room->publicUrl())->assertOk()
            ->assertSee('Suite Lagune')->assertSee('Vue sur le jardin')
            ->assertSee('Une chambre lumineuse.')->assertSee('Avec un balcon.')
            ->assertSeeInOrder(['Tarifs', 'Séjour (24 h)', 'par période de 24 heures', '45 000 FCFA'], false)
            ->assertDontSee("À l'heure", false)->assertDontSee('Journée')->assertDontSee('Nuitée')
            ->assertDontSee('15 000 FCFA')->assertDontSee('25 000 FCFA')
            ->assertSee('Wi-Fi gratuit')->assertSee('Climatisation')
            ->assertSee('Bouilloire')->assertSee('Non-fumeur')->assertSee('Annulation gratuite 24 h avant.')
            ->assertSee('storage/rooms/'.$room->id.'/a.jpg', false)->assertSee('storage/rooms/'.$room->id.'/b.jpg', false)
            ->assertSee('2 adultes')->assertSee('1 enfant');
        $this->assertStringEndsWith('/chambres/'.$room->id.'/suite-lagune', $room->publicUrl());
    }

    public function test_a_room_without_a_sejour_rate_shows_no_other_formula(): void
    {
        $type = RoomType::factory()->create(['name' => 'Express', 'hourly_rate' => 3000, 'day_rate' => 10000, 'night_rate' => 20000, 'stay_rate' => null]);
        $room = $this->room(['public_name' => 'Chambre express', 'room_type_id' => $type->id]);

        $this->get('/chambres')->assertOk()->assertSee('Chambre express')->assertSee('Tarif sur demande')->assertDontSee('3 000 FCFA');
        $this->get($room->publicUrl())->assertOk()->assertSee('Tarif sur demande')
            ->assertDontSee("À l'heure", false)->assertDontSee('Nuitée')->assertDontSee('20 000');
    }

    public function test_text_from_the_application_is_escaped(): void
    {
        $room = $this->room(['description' => '<script>alert(1)</script>', 'tagline' => '<b>x</b>']);

        $this->get($room->publicUrl())->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_unpublished_rooms_and_bad_addresses(): void
    {
        $draft = $this->room(['is_published' => false]);
        $hidden = $this->room(['room_type_id' => RoomType::factory()->create(['is_active' => false])->id]);
        $room = $this->room();

        $this->get('/chambres/'.$draft->id.'/suite-lagune')->assertNotFound();
        $this->get('/chambres/'.$hidden->id.'/suite-lagune')->assertNotFound();
        $this->get('/chambres/99999/x')->assertNotFound();
        $this->get('/chambres/abc')->assertNotFound();

        // Missing or old slug: permanent redirect to the right address.
        $this->get('/chambres/'.$room->id)->assertRedirect($room->publicUrl())->assertStatus(301);
        $this->get('/chambres/'.$room->id.'/ancien-nom')->assertRedirect($room->publicUrl());
    }

    public function test_the_room_page_says_when_it_is_free_without_linking_the_request_page(): void
    {
        $free = $this->room(['number' => 'F-1']);
        $busy = $this->room(['number' => 'B-1']);
        $stay = Reservation::factory()->create(['room_id' => $busy->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000, 'check_in' => now()->subHour(), 'duration' => 3, 'status' => 'confirmee']);
        $stay->update(['status' => 'enregistree']);

        $this->get($free->publicUrl())->assertSee('Libre en ce moment')->assertDontSee(route('stay-request.create', $free), false);
        $this->get($busy->fresh()->publicUrl())->assertOk()->assertDontSee('Libre en ce moment')->assertDontSee(route('stay-request.create', $busy), false);
    }

    public function test_the_home_page_shows_published_rooms_only_when_there_are_some(): void
    {
        $this->get('/')->assertOk()->assertDontSee('luxivo-room_one', false);

        $room = $this->room();
        $this->get('/')->assertOk()->assertSee('luxivo-room_one', false)->assertSee('Suite Lagune')->assertSee($room->publicUrl(), false);
    }

    public function test_the_application_shows_what_is_published(): void
    {
        $room = $this->room(['number' => 'PUB-1']);
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/admin/rooms')->assertOk()->assertSee('Publiée')->assertSee($room->publicUrl(), false);
        $this->actingAs($staff)->get("/admin/rooms/{$room->id}/edit")->assertOk()->assertSee('Voir sur le site');
    }
}
