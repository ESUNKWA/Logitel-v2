<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website page loader: the hotel's logo, else its name — never the template's sample icon.
 */
class PreloaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_loader_shows_the_hotel_logo_when_there_is_one(): void
    {
        config(['hotel.info.name' => 'Hôtel Evasion', 'hotel.info.logo' => 'hotel/logo.png']);

        $html = $this->get('/')->assertOk()->getContent();
        $loader = $this->loader($html);

        $this->assertStringContainsString('<img src="'.asset('storage/hotel/logo.png').'" alt="Hôtel Evasion" class="site-preloader__logo">', $loader);
        $this->assertStringNotContainsString('brand-logo-text', $loader);
    }

    public function test_without_logo_the_loader_shows_the_hotel_name(): void
    {
        config(['hotel.info.name' => 'Grand <Hôtel> Evasion', 'hotel.info.logo' => '']);

        $loader = $this->loader($this->get('/chambres')->assertOk()->getContent());

        $this->assertStringContainsString('brand-logo-text is-dark', $loader);
        $this->assertStringContainsString('<span>Grand</span>', $loader);
        $this->assertStringContainsString('&lt;Hôtel&gt; Evasion', $loader);
        $this->assertStringNotContainsString('<img', $loader);
    }

    public function test_the_template_sample_icon_is_gone_and_the_page_still_hides_the_loader_without_js(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertStringNotContainsString('loader.png', $html);
        $this->assertStringContainsString('<div class="preloader" role="status" aria-label="Chargement…">', $html);
        $this->assertStringContainsString('<noscript><style>.preloader { display: none !important; }</style></noscript>', $html);
        // Version-stamped, so returning visitors get the loader's styles right after a deployment.
        $this->assertStringContainsString('site/css/site.css?v='.filemtime(public_path('site/css/site.css')), $html);
        // theme.js still fades it out once the page has loaded.
        $this->assertStringContainsString("$('.preloader').delay(500).fadeOut(500);", file_get_contents(public_path('site/js/theme.js')));
    }

    private function loader(string $html): string
    {
        $start = strpos($html, '<div class="preloader"');
        $this->assertNotFalse($start, 'Loader missing');

        return substr($html, $start, strpos($html, '<noscript>', $start) - $start);
    }
}
