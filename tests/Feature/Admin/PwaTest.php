<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Installable application (PWA → APK): only the management application, never the website.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_covers_the_application_only(): void
    {
        $response = $this->get('/admin/manifest.webmanifest')->assertOk();

        $this->assertStringContainsString('application/manifest+json', $response->headers->get('Content-Type'));
        $response->assertJson([
            'scope' => '/admin/',
            'start_url' => '/admin/?source=app',
            'display' => 'standalone',
            'lang' => 'fr',
        ]);
        $this->assertStringEndsWith('— Gestion', $response->json('name'));

        foreach ($response->json('icons') as $icon) {
            $this->assertFileExists(public_path(ltrim((string) parse_url($icon['src'], PHP_URL_PATH), '/')));
        }
        $this->assertContains('maskable', array_column($response->json('icons'), 'purpose'));
        $this->assertSame('/admin/rooms/board', $response->json('shortcuts.0.url'));
    }

    public function test_the_application_and_its_login_are_installable_but_never_the_website(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('name="app-sw"', false)
            ->assertSee('Installer l\'application sur cet appareil', false)
            // Versioned so a deployment reaches phones at once (Caddy lets them cache /app/* a week).
            ->assertSee('/app/js/pwa.js?v='.filemtime(public_path('app/js/pwa.js')), false)
            ->assertSee('/app/css/admin.css?v=', false);
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-pwa-install', false);

        foreach (['/', '/chambres', '/services', '/galerie', '/contact', '/reservation', '/demande'] as $page) {
            $this->get($page)->assertOk()->assertDontSee('rel="manifest"', false)->assertDontSee('app-sw', false);
        }
    }

    public function test_the_service_worker_is_scoped_to_the_application_and_never_caches_pages(): void
    {
        $response = $this->get('/admin/sw.js')->assertOk();

        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('/admin/', $response->headers->get('Service-Worker-Allowed'));
        $script = $response->getContent();
        $this->assertStringContainsString('const OFFLINE_URL = "\/admin\/hors-ligne"', $script);
        $this->assertStringContainsString("request.mode === 'navigate'", $script);
        $this->assertStringContainsString("request.method !== 'GET'", $script);
    }

    public function test_the_offline_page_is_public_and_holds_no_data(): void
    {
        $this->get('/admin/hors-ligne')->assertOk()
            ->assertSee('Pas de connexion')
            ->assertSee('Réessayer')
            ->assertDontSee('FCFA');
    }

    public function test_android_asset_links_appear_only_once_configured(): void
    {
        $this->get('/.well-known/assetlinks.json')->assertNotFound();

        config([
            'pwa.android_package' => 'ci.hotelevasion.gestion',
            'pwa.android_fingerprints' => ['AB:CD:EF:01'],
        ]);

        $this->get('/.well-known/assetlinks.json')->assertOk()->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => 'ci.hotelevasion.gestion', 'sha256_cert_fingerprints' => ['AB:CD:EF:01']],
        ]]);
    }
}
