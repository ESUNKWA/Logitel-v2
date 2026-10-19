<?php

namespace Tests\Feature\Site;

use App\Support\VideoCapsules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Advertising video capsules in the home banner: muted, inline, nothing preloaded, light
 * posters, the real files (encoded paths), accessible controls; missing files skipped; no
 * player at all when there's no capsule; only on the home page.
 */
class HeroVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_banner_plays_the_real_capsules_without_preloading_them(): void
    {
        // Whatever capsules are configured (config/site.php): every file exists, with its poster.
        $this->assertNotEmpty(config('site.capsules'));
        $capsules = VideoCapsules::all();
        $count = count($capsules);
        $this->assertSame(count(config('site.capsules')), $count, 'Every configured capsule file exists.');
        foreach ($capsules as $capsule) {
            $this->assertNotNull($capsule['poster']);
            $this->assertLessThan(150 * 1024, filesize(public_path(str_replace(asset(''), '', rawurldecode($capsule['poster'])))));
        }

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="luxivo-hero_one p-r z-1 bg_cover has-video"', $html);
        $this->assertStringContainsString('src="'.$capsules[0]['src'].'"', $html);
        $this->assertStringContainsString('poster="'.$capsules[0]['poster'].'"', $html);
        // Autoplay-friendly on mobile, nothing downloaded before the page has loaded, no layout shift.
        $this->assertMatchesRegularExpression('#<video [^>]*\bmuted playsinline preload="none" controls disablepictureinpicture width="848" height="478"#', $html);
        // Controls: play/pause, sound (« Activer le son » pill when the browser refuses sound),
        // previous/next and one dot per capsule when there are several, announced status.
        foreach (['data-action="toggle" aria-label="Lire la vidéo"', 'data-action="mute" aria-label="Activer le son" aria-pressed="false"', 'class="hero-video__btn-text" aria-hidden="true">Activer le son</span>', 'aria-live="polite"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        if ($count > 1) {
            $this->assertStringContainsString('aria-label="Capsule précédente"', $html);
            $this->assertSame($count, substr_count($html, 'class="hero-video__dot"'));
            $this->assertStringContainsString('aria-label="Capsule 2 — 2 sur '.$count.'"', $html);
        }
        $this->assertStringContainsString('site/js/hero-video.js?v=', $html);
        // The template turns any [data-src] into a background image: the player must not use it.
        $player = substr($html, strpos($html, '<div class="hero-video"'), 3000);
        $this->assertStringNotContainsString('data-src=', substr($player, 0, strpos($player, '</video>')));
    }

    public function test_file_names_with_spaces_are_url_encoded(): void
    {
        $spaced = public_path('site/images/stock/videos-capsules/capsule test.mp4');
        copy(public_path(config('site.capsules')[0]['video']), $spaced);
        try {
            config(['site.capsules' => [['video' => 'site/images/stock/videos-capsules/capsule test.mp4']]]);
            $this->assertSame(asset('site/images/stock/videos-capsules/capsule%20test.mp4'), VideoCapsules::all()[0]['src']);
        } finally {
            unlink($spaced);
        }
    }

    public function test_missing_files_are_skipped_and_no_capsule_means_no_player(): void
    {
        config(['site.capsules' => [
            ['video' => 'site/images/stock/absente.mp4', 'poster' => 'site/images/stock/capsules/capsule-1.jpg'],
            ['video' => 'site/images/stock/videos-capsules/v1.mp4', 'poster' => 'site/images/stock/capsules/absent.jpg', 'title' => 'Terrasse'],
        ]]);
        $capsules = VideoCapsules::all();
        $this->assertCount(1, $capsules);
        $this->assertNull($capsules[0]['poster']);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('absente.mp4', $html);
        $this->assertStringNotContainsString('data-action="next"', $html); // one capsule: no previous/next
        $this->assertStringContainsString('aria-label="Terrasse — 1 sur 1"', $html);

        config(['site.capsules' => []]);
        $this->get('/')->assertOk()->assertDontSee('data-hero-video', false)->assertDontSee('has-video', false)->assertDontSee('hero-video.js', false);
    }

    public function test_only_the_home_page_has_the_player(): void
    {
        foreach (['/chambres', '/services', '/galerie', '/contact'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('data-hero-video', false)->assertDontSee('hero-video.js', false);
        }
    }
}
