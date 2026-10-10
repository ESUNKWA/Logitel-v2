<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lobby poster: a QR code guests scan to open the room request page (/demande).
 */
class RequestQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_poster_encodes_the_address_staff_are_using(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('http://192.168.1.166/admin/demandes/qr-code')->assertOk()
            ->assertSee('Choisissez votre chambre')
            ->assertSee('<svg', false)
            ->assertSee('http://192.168.1.166/demande')
            ->assertSee("Imprimer l'affiche", false)
            ->assertDontSee('les téléphones des clients ne pourront pas');

        // The very QR code of that address (deterministic output).
        $expected = preg_replace('/^<\?xml[^>]*>\s*/', '', QrCode::svg('http://192.168.1.166/demande'));
        $this->assertStringContainsString($expected, $response->getContent());
    }

    public function test_it_warns_when_the_address_only_works_on_this_computer(): void
    {
        $this->actingAs(User::factory()->create())->get('http://localhost/admin/demandes/qr-code')->assertOk()
            ->assertSee("les téléphones des clients ne pourront pas l'ouvrir", false);
    }

    public function test_the_qr_code_can_be_downloaded_as_svg(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('http://hotel.example.ci/admin/demandes/qr-code.svg')->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-code-demande-chambre.svg"');

        $this->assertSame(QrCode::svg('http://hotel.example.ci/demande', 1200), $response->getContent());
        $this->assertStringStartsWith('<?xml', $response->getContent());
    }

    public function test_it_is_linked_from_the_requests_page_and_reception_can_print_it(): void
    {
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get('/admin/demandes')->assertSee(route('admin.stay-requests.qr'), false);
        $this->actingAs($reception)->get('/admin/demandes/qr-code')->assertOk();
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertSee(route('admin.stay-requests.qr'), false);

        $this->actingAs(User::factory()->withoutRole()->create())->get('/admin/demandes/qr-code')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get('/admin/demandes/qr-code.svg')->assertRedirect(route('admin.login'));
    }
}
