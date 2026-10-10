<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The website and the management application are two distinct halves: their own routes,
 * views, assets — and, once there's a domain, their own sub-domains (config/routing.php).
 */
class SiteAndAppSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_one_address_the_application_lives_under_admin(): void
    {
        $this->assertSame('/admin/login', parse_url(route('admin.login'), PHP_URL_PATH));
        $this->get('/admin/login')->assertOk()->assertSee('noindex, nofollow', false)->assertSee('app/css/admin.css', false)->assertDontSee('site/css/style.css', false);
        $this->get('/')->assertOk()->assertSee('site/css/style.css', false)->assertDontSee('noindex', false);
    }

    public function test_search_engines_are_kept_out_of_the_application(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    public function test_assets_are_split_between_site_admin_and_shared_vendor_files(): void
    {
        foreach (['site/css/style.css', 'site/css/demande.css', 'app/css/admin.css', 'app/js/admin.js', 'app/images/login-default.jpg', 'vendor/bootstrap/bootstrap.min.css', 'vendor/fontawesome/css/all.min.css'] as $file) {
            $this->assertFileExists(public_path($file), $file);
        }
        $this->assertDirectoryDoesNotExist(public_path('assets'));
    }

    public function test_admin_redirects_guests_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/login')->assertOk()->assertSee('Se connecter');
    }

    /**
     * A real folder in public/ named like a route's first segment (e.g. public/admin for the
     * /admin prefix) makes the web server serve that folder instead of Laravel: /admin then
     * answered 404 under `php artisan serve`. Public folders must never shadow a route.
     */
    public function test_no_public_folder_shadows_a_route(): void
    {
        $segments = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', trim($route->uri(), '/'))[0])
            ->filter(fn ($segment) => $segment !== '' && ! str_starts_with($segment, '{'))
            // public/storage is Laravel's intended link to uploaded files (served directly);
            // the framework's own /storage/{path} route is for the private local disk.
            ->reject(fn ($segment) => $segment === 'storage')
            ->unique();

        foreach ($segments as $segment) {
            $this->assertDirectoryDoesNotExist(public_path($segment), "public/{$segment}/ would shadow the /{$segment} route.");
        }
        $this->assertContains('admin', $segments->all());
    }
}
