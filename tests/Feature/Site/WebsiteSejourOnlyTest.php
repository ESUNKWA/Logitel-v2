<?php

namespace Tests\Feature\Site;

use App\Enums\PricingMode;
use App\Enums\RoomAmenity;
use App\Models\BookingRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\SiteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Client decision (2026-10-19, advertising and reliability): the public website never mentions
 * the hourly, day or night formulas — only the séjour (PricingMode::WEBSITE). Every public page
 * is checked with rooms whose types have all four rates. The lobby QR-code page /demande is the
 * only exception (guests already on site), on purpose.
 */
class WebsiteSejourOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = ["à l'heure", 'journée', 'nuitée', '/ heure', '/ journée', '/ nuit', '3 000 f', '12 000 f', '25 000 f', '"heure"', '"journee"', '"nuitee"'];

    public function test_no_public_page_mentions_another_formula_than_the_sejour(): void
    {
        $this->travelTo('2026-10-19 10:00:00');
        $this->assertSame(PricingMode::Sejour, PricingMode::WEBSITE);

        $type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 3000, 'day_rate' => 12000, 'night_rate' => 25000, 'stay_rate' => 45000]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'is_published' => true, 'public_name' => 'Suite Lagune',
            'amenities' => [RoomAmenity::Wifi], 'description' => 'Une belle chambre.']);
        Service::create(['title' => 'Restaurant', 'icon' => 'fa-utensils', 'is_published' => true, 'show_on_home' => true]);
        SiteEvent::create(['title' => 'Soirée jazz', 'starts_at' => now()->addDays(3), 'is_published' => true]);
        $booking = BookingRequest::create(['room_type_id' => $type->id, 'pricing_mode' => 'sejour', 'check_in' => now()->addDays(5), 'duration' => 2,
            'adults' => 2, 'children' => 0, 'unit_rate' => 45000, 'phone' => '0701020304', 'first_name' => 'Awa', 'last_name' => 'Koné']);

        $pages = [
            '/', '/chambres', $room->publicUrl(), '/services', '/galerie', '/evenements', '/contact',
            '/reservation', '/reservation?type='.$type->id, URL::signedRoute('booking.show', $booking), '/page-inexistante',
        ];
        foreach ($pages as $page) {
            $response = $this->get($page);
            $this->assertContains($response->status(), [200, 404], $page);
            $text = mb_strtolower(html_entity_decode((string) $response->getContent(), ENT_QUOTES));
            foreach (self::FORBIDDEN as $term) {
                $this->assertStringNotContainsString($term, $text, "« {$term} » apparaît sur {$page}.");
            }
        }

        // The séjour itself is shown (price on the home page, the room page and the booking form).
        foreach (['/', $room->publicUrl(), '/reservation'] as $page) {
            $this->get($page)->assertSee('45 000');
        }
    }

    public function test_the_lobby_qr_code_page_keeps_every_formula(): void
    {
        $this->travelTo('2026-10-19 10:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 3000, 'day_rate' => 12000, 'night_rate' => 25000, 'stay_rate' => 45000, 'is_active' => true]);
        $room = Room::factory()->create(['room_type_id' => $type->id]);

        $this->get('/demande/chambre/'.$room->id)->assertOk()->assertSee("À l'heure")->assertSee('Journée')->assertSee('Séjour');
    }
}
