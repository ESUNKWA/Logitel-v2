<?php

namespace Tests\Feature\Site;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Website page views are counted anonymously, without cookies, robots or staff.
 */
class SiteVisitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 15:00:00');
    }

    private function visit(string $uri, string $ua = self::DESKTOP, ?string $referer = null, string $ip = '41.202.1.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => $ua, 'Referer' => $referer ?? ''])
            ->get($uri);
    }

    public function test_a_visitor_is_one_visit_a_day_whatever_the_pages_viewed(): void
    {
        $this->visit('/', self::PHONE, 'https://www.google.com/')->assertOk();
        $this->visit('/galerie', self::PHONE, 'http://localhost/');
        $this->visit('/services', self::PHONE, 'http://localhost/galerie');

        $rows = SiteVisit::query()->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame([true, false, false], $rows->pluck('is_entry')->all());
        $this->assertSame(['Accueil', 'Galerie', 'Services'], $rows->pluck('label')->all());
        $this->assertSame('google', $rows[0]->source);
        $this->assertSame('mobile', $rows[0]->device);
        $this->assertSame(1, $rows->pluck('visitor')->unique()->count());

        // Another person, then the same person the next day: new visits.
        $this->visit('/', self::DESKTOP, null, '41.202.1.99');
        $this->travelTo('2026-10-11 09:00:00');
        $this->visit('/', self::PHONE);
        $this->assertSame(3, SiteVisit::query()->where('is_entry', true)->count());
        $this->assertNotSame($rows[0]->visitor, SiteVisit::query()->latest('id')->first()->visitor);
    }

    public function test_no_ip_and_no_cookie_are_stored(): void
    {
        $response = $this->visit('/', self::DESKTOP, null, '41.202.1.10');

        $row = SiteVisit::sole();
        $this->assertSame(64, strlen($row->visitor));
        $this->assertStringNotContainsString('41.202', json_encode($row->getAttributes()));
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertNotSame('visitor', $cookie->getName());
        }
    }

    public function test_robots_previews_staff_and_errors_are_not_counted(): void
    {
        $this->visit('/', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->visit('/', 'WhatsApp/2.23.20.0 A');
        $this->visit('/', 'facebookexternalhit/1.1');
        $this->visit('/', 'curl/8.4.0');
        $this->visit('/page-inexistante')->assertNotFound();
        $this->withHeaders(['User-Agent' => self::DESKTOP, 'Sec-Purpose' => 'prefetch'])->get('/');
        $this->actingAs(User::factory()->create());
        $this->visit('/');

        $this->assertSame(0, SiteVisit::count());
    }

    public function test_the_application_is_never_counted(): void
    {
        $this->visit('/admin/login')->assertOk();

        $this->assertSame(0, SiteVisit::count());
    }

    public function test_sources_devices_and_room_pages_are_classified(): void
    {
        $type = RoomType::factory()->create(['name' => 'Confort']);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'is_published' => true, 'public_name' => 'Suite Lagune']);

        $this->visit($room->publicUrl(), self::DESKTOP, 'https://l.facebook.com/l.php?u=x', '1.1.1.1');
        $this->visit('/', self::DESKTOP, 'https://wa.me/225070000', '1.1.1.2');
        $this->visit('/', 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)', 'https://www.bing.com/search', '1.1.1.3');
        $this->visit('/', 'Mozilla/5.0 (Linux; Android 14; SM-X200) AppleWebKit/537.36 Chrome/128.0 Safari/537.36', 'https://blog.exemple.ci/hotels', '1.1.1.4');
        $this->visit('/demande', self::PHONE, null, '1.1.1.5');

        $rows = SiteVisit::query()->orderBy('id')->get();
        $this->assertSame(['facebook', 'whatsapp', 'search', 'other', 'direct'], $rows->pluck('source')->all());
        $this->assertSame(['desktop', 'desktop', 'tablet', 'tablet', 'mobile'], $rows->pluck('device')->all());
        $this->assertSame('Chambre : Suite Lagune', $rows[0]->label);
        $this->assertSame('blog.exemple.ci', $rows[3]->referrer_host);
        $this->assertSame('Demande sur place (QR code)', $rows[4]->label);
    }

    public function test_a_failure_to_record_never_breaks_the_page(): void
    {
        Schema::drop('site_visits');

        $this->visit('/')->assertOk();
    }
}
