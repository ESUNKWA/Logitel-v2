<?php

namespace Tests\Feature\Site;

use App\Enums\PricingMode;
use App\Enums\RoomAmenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Support\HotelOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Home « Ce que nous vous offrons » (replaces the decorative contact form): formulas of the
 * published rooms with their lowest price and window, their equipment, every published service,
 * payment methods; nothing from unpublished rooms / inactive types / unpublished services; the
 * old form is gone.
 */
class HomeOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_offer_shows_only_what_the_application_publishes(): void
    {
        $cheap = RoomType::factory()->create(['name' => 'Standard', 'hourly_rate' => 3000, 'day_rate' => null, 'night_rate' => 25000, 'stay_rate' => 40000]);
        $suite = RoomType::factory()->create(['name' => 'Suite', 'hourly_rate' => 5000, 'day_rate' => 15000, 'night_rate' => 40000, 'stay_rate' => 70000]);
        $hidden = RoomType::factory()->create(['name' => 'Cachée', 'hourly_rate' => 1000, 'stay_rate' => 9000]); // only unpublished rooms
        $inactive = RoomType::factory()->create(['name' => 'Ancienne', 'hourly_rate' => 500, 'is_active' => false]);
        Room::factory()->create(['room_type_id' => $cheap->id, 'is_published' => true, 'amenities' => [RoomAmenity::Wifi, RoomAmenity::Climatisation]]);
        Room::factory()->create(['room_type_id' => $suite->id, 'is_published' => true, 'amenities' => [RoomAmenity::Wifi, RoomAmenity::Television]]);
        Room::factory()->create(['room_type_id' => $hidden->id, 'is_published' => false, 'amenities' => [RoomAmenity::Refrigerateur]]);
        Room::factory()->create(['room_type_id' => $inactive->id, 'is_published' => true, 'amenities' => [RoomAmenity::Douche]]);
        Service::create(['title' => 'Piscine', 'icon' => 'fa-swimmer', 'is_published' => true, 'position' => 2]);
        Service::create(['title' => 'Restaurant', 'icon' => 'fa-utensils', 'is_published' => true, 'position' => 1]);
        Service::create(['title' => 'Spa (bientôt)', 'is_published' => false]);

        $offer = HotelOffer::summary();
        // The séjour only (PricingMode::WEBSITE): the cheapest among published rooms — not the 9 000 F of the type with only unpublished rooms.
        $this->assertSame([['sejour', 40000.0]], array_map(fn ($f) => [$f['mode']->value, $f['from']], $offer['formulas']));
        $this->assertSame(['wifi', 'climatisation', 'television'], array_map(fn ($a) => $a->value, $offer['amenities']));
        $this->assertSame(['Restaurant', 'Piscine'], $offer['services']->pluck('title')->all());

        $html = $this->get('/')->assertOk()->getContent();
        $block = substr($html, strpos($html, '<div class="booking-wrapper home-offer">'));
        $block = substr($block, 0, strpos($block, '</section>'));
        $this->assertStringContainsString('Ce que nous vous offrons', $block);
        $this->assertStringContainsString('dès <strong>40 000 F</strong>', $block);
        $this->assertStringContainsString('Par période de 24 h', $block);
        foreach (["À l'heure", 'Journée', 'Nuitée', '22h00', '3 000 F'] as $other) {
            $this->assertStringNotContainsString($other, html_entity_decode($block));
        }
        $this->assertStringContainsString('Climatisation', $block);
        $this->assertStringNotContainsString('Réfrigérateur', $block);
        $this->assertStringNotContainsString(RoomAmenity::Douche->getLabel(), $block);
        $this->assertStringContainsString('Restaurant', $block);
        $this->assertStringNotContainsString('Spa (bientôt)', $block);
        $this->assertStringContainsString('Orange Money', $block);
        $this->assertStringContainsString(route('booking.create'), $block);
        $this->assertStringContainsString(route('services.index'), $block);

        // The decorative form is gone.
        $this->assertStringNotContainsString('booking-form', $html);
        $this->assertStringNotContainsString('VÉRIFIER LES DISPONIBILITÉS', $html);
        $this->assertStringNotContainsString('name="CheckIn"', $html);
    }

    public function test_an_empty_hotel_shows_no_empty_headings(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Ce que nous vous offrons', $html);
        $this->assertStringNotContainsString('Nos tarifs', $html);
        $this->assertStringNotContainsString('Dans nos chambres', $html);
        $this->assertStringNotContainsString('>Nos services</h3>', $html);
        $this->assertStringNotContainsString('Voir tous nos services', $html);
        $this->assertStringContainsString('Paiement à la réception', $html);
        $this->assertStringContainsString(route('booking.create'), $html);
    }

    public function test_the_windows_follow_the_hotel_settings(): void
    {
        config(['hotel.night.from' => '21:00', 'hotel.night.until' => '11:00', 'hotel.day_hours' => 8]);
        $this->assertSame('De 21h00 à 11h00 le lendemain', HotelOffer::window(PricingMode::Nuitee));
        $this->assertSame('Bloc de 8 h en journée', HotelOffer::window(PricingMode::Journee));
    }
}
