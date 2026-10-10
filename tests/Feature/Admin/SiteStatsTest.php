<?php

namespace Tests\Feature\Admin;

use App\Models\BookingRequest;
use App\Models\RoomType;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Site web → Statistiques »: visits per day / month / year, sources, devices, pages, conversions.
 */
class SiteStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 15:00:00');
    }

    /**
     * $pages page views by one visitor on a day (the first one is the visit).
     */
    private function visitor(string $day, int $pages = 1, string $source = 'direct', string $device = 'mobile', string $label = 'Accueil'): void
    {
        $visitor = hash('sha256', uniqid('', true));
        foreach (range(1, $pages) as $i) {
            SiteVisit::query()->insert([
                'day' => $day, 'visited_at' => "{$day} 10:00:00", 'visitor' => $visitor, 'is_entry' => $i === 1,
                'route' => 'home', 'path' => '/', 'label' => $i === 1 ? $label : 'Chambres', 'source' => $source, 'device' => $device,
            ]);
        }
    }

    public function test_today_month_and_year_figures_with_comparisons(): void
    {
        $this->visitor('2026-10-10', 3, 'google');
        $this->visitor('2026-10-10', 1, 'facebook', 'desktop');
        $this->visitor('2026-10-09');           // yesterday
        $this->visitor('2026-10-02');           // this month
        $this->visitor('2026-09-05');           // same span last month
        $this->visitor('2026-09-25');           // after the same span: not compared
        $this->visitor('2026-02-01');           // this year
        $this->visitor('2025-03-01');           // last year, same span

        $response = $this->actingAs(User::factory()->create())->get(route('admin.site.stats'))->assertOk();
        $kpis = $response->viewData('kpis');

        $this->assertSame(['visits' => 2, 'views' => 4, 'previous' => 1, 'delta' => 100], $kpis['today']);
        $this->assertSame(4, $kpis['month']['visits']);
        $this->assertSame(1, $kpis['month']['previous']);
        $this->assertSame(7, $kpis['year']['visits']);
        $this->assertSame(1, $kpis['year']['previous']);
        $response->assertSee('+100 %');
    }

    public function test_the_three_periods_bucket_the_visits(): void
    {
        $this->visitor('2026-10-10', 2);
        $this->visitor('2026-10-01');
        $this->visitor('2026-08-15');
        $this->visitor('2025-12-31');
        $this->visitor('2024-06-01');
        $user = User::factory()->create();

        $days = $this->actingAs($user)->get(route('admin.site.stats'))->viewData('series')['buckets'];
        $this->assertCount(30, $days);
        $this->assertSame(['visits' => 1, 'views' => 2], ['visits' => end($days)['visits'], 'views' => end($days)['views']]);
        $this->assertSame(2, collect($days)->sum('visits'));

        $months = $this->get(route('admin.site.stats', ['periode' => 'mois']))->viewData('series')['buckets'];
        $this->assertCount(12, $months);
        $this->assertSame('2026-10', end($months)['key']);
        $this->assertSame(2, end($months)['visits']);
        $this->assertSame(1, collect($months)->firstWhere('key', '2026-08')['visits']);
        $this->assertSame(1, collect($months)->firstWhere('key', '2025-12')['visits']);

        $years = $this->get(route('admin.site.stats', ['periode' => 'annees']))->viewData('series')['buckets'];
        $this->assertSame(['2024', '2025', '2026'], collect($years)->pluck('key')->all());
        $this->assertSame([1, 1, 3], collect($years)->pluck('visits')->all());

        // Unknown period: the default one.
        $this->assertCount(30, $this->get(route('admin.site.stats', ['periode' => 'nimporte']))->viewData('series')['buckets']);
    }

    public function test_sources_devices_pages_and_conversions(): void
    {
        $this->visitor('2026-10-10', 2, 'google', 'mobile');
        $this->visitor('2026-10-09', 1, 'google', 'desktop', 'Chambre : Suite Lagune');
        $this->visitor('2026-10-08', 1, 'whatsapp', 'mobile');
        $type = RoomType::factory()->create();
        $booking = BookingRequest::create(['room_type_id' => $type->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-20 22:00', 'duration' => 1, 'unit_rate' => 75000, 'phone' => '0701']);
        $booking->forceFill(['status' => BookingRequest::STATUS_HANDLED])->save();
        BookingRequest::create(['room_type_id' => $type->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-21 22:00', 'duration' => 1, 'unit_rate' => 75000, 'phone' => '0702']);

        $response = $this->actingAs(User::factory()->create())->get(route('admin.site.stats'))->assertOk();

        $this->assertSame([['key' => 'google', 'label' => 'Google', 'count' => 2, 'share' => 67], ['key' => 'whatsapp', 'label' => 'WhatsApp', 'count' => 1, 'share' => 33]], $response->viewData('sources'));
        $this->assertSame('Téléphone', $response->viewData('devices')[0]['label']);
        $this->assertSame(['label' => 'Accueil', 'views' => 2, 'visitors' => 2], $response->viewData('pages')[0]);
        $this->assertSame(2, $response->viewData('conversions')['booking_requests']);
        $this->assertSame(1, $response->viewData('conversions')['booking_handled']);
        $response->assertSee('Chambre : Suite Lagune')->assertSee('Demandes de réservation en ligne');
    }

    public function test_an_empty_site_shows_zeroes(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.site.stats'))->assertOk()
            ->assertSee('Aucune visite sur la période.');
        foreach (['mois', 'annees'] as $period) {
            $this->get(route('admin.site.stats', ['periode' => $period]))->assertOk();
        }
    }

    public function test_statistics_need_their_permission(): void
    {
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.site.stats'))->assertForbidden();
        $this->actingAs(User::factory()->withoutRole()->create())->get(route('admin.site.stats'))->assertForbidden();
    }
}
