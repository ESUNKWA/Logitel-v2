<?php

namespace Tests\Feature\Site;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Once the hotel has a domain: SITE_DOMAIN / ADMIN_DOMAIN give each half its own sub-domain
 * (config/routing.php). The routes are registered at boot, so the app is rebuilt with those
 * variables set (and its in-memory database migrated) — hence no RefreshDatabase here.
 */
class SubDomainRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('SITE_DOMAIN=www.hotel.test');
        putenv('ADMIN_DOMAIN=gestion.hotel.test');
        parent::setUp();
        Artisan::call('migrate');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('SITE_DOMAIN');
        putenv('ADMIN_DOMAIN');
    }

    public function test_each_host_only_answers_its_own_half(): void
    {
        // The application at the root of its own host…
        $this->assertSame(['gestion.hotel.test', '/login'], [parse_url(route('admin.login'), PHP_URL_HOST), parse_url(route('admin.login'), PHP_URL_PATH)]);
        $this->get('http://gestion.hotel.test/login')->assertOk()->assertSee('Se connecter');
        $this->get('http://gestion.hotel.test/')->assertRedirect('http://gestion.hotel.test/login');
        $this->actingAs(User::factory()->create())->get('http://gestion.hotel.test/')->assertOk();

        // …the website on the other, and neither answers the other's pages.
        $this->assertSame(['www.hotel.test', '/demande'], [parse_url(route('stay-request.index'), PHP_URL_HOST), parse_url(route('stay-request.index'), PHP_URL_PATH)]);
        $this->get('http://www.hotel.test/')->assertOk()->assertSee('site/css/style.css', false);
        $this->get('http://www.hotel.test/demande')->assertOk();
        $this->get('http://www.hotel.test/admin/login')->assertNotFound();
        $this->get('http://www.hotel.test/login')->assertNotFound();
        $this->get('http://gestion.hotel.test/chambres')->assertNotFound();
    }
}
