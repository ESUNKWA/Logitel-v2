<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Configuration → Hôtel: the hotel's identity and contact details, used on receipts, in the
 * back-office, on the guest request pages and on the public site.
 */
class HotelInfoTest extends TestCase
{
    use RefreshDatabase;

    private function info(array $extra = []): array
    {
        return [
            'hotel_name' => 'Hôtel Hôtel Evasion',
            'hotel_tagline' => 'Le confort au cœur de Bouaké',
            'hotel_phone' => '+225 07 11 22 33 44',
            'hotel_phone2' => '+225 27 31 00 00 00',
            'hotel_whatsapp' => '+225 05 66 77 88 99',
            'hotel_email' => 'reception@katioka.ci',
            'hotel_website' => 'www.katioka.ci',
            'hotel_address' => 'Quartier Commerce',
            'hotel_city' => 'Bouaké',
            'hotel_country' => "Côte d'Ivoire",
            'hotel_rccm' => 'CI-BKE-2026-B-123',
            'hotel_ncc' => '2612345X',
            'hotel_tax_regime' => 'Taxe synthétique',
            'hotel_legal_other' => '',
            'hotel_facebook' => 'facebook.com/katioka',
            'hotel_instagram' => '',
            ...$extra,
        ];
    }

    public function test_the_manager_saves_the_hotel_information(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get('/admin/hotel')->assertOk()->assertSee("Informations de l'hôtel", false);
        $this->actingAs($manager)->put('/admin/hotel', $this->info())->assertSessionHasNoErrors()->assertRedirect('/admin/hotel');

        $this->assertSame('Hôtel Hôtel Evasion', config('hotel.info.name'));
        $this->assertSame('https://www.katioka.ci', config('hotel.info.website')); // scheme added
        $this->assertSame('https://facebook.com/katioka', config('hotel.info.facebook'));
        $this->assertSame('', config('hotel.info.instagram'));
        $this->assertSame('+225 07 11 22 33 44', DB::table('settings')->where('key', 'hotel_phone')->value('value'));

        $this->actingAs($manager)->get('/admin/hotel')->assertSee('value="+225 07 11 22 33 44"', false);
    }

    public function test_hotel_information_is_validated(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/hotel', $this->info(['hotel_name' => '']))->assertSessionHasErrors('hotel_name');
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['hotel_phone' => '']))->assertSessionHasErrors('hotel_phone');
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['hotel_phone2' => 'appelez-nous']))->assertSessionHasErrors('hotel_phone2');
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['hotel_email' => 'pas-un-mail']))->assertSessionHasErrors('hotel_email');
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['hotel_facebook' => 'javascript:alert(1)']))->assertSessionHasErrors('hotel_facebook');

        $this->assertSame('Hôtel Evasion', config('hotel.info.name')); // nothing saved
    }

    public function test_the_logo_is_uploaded_replaced_and_removed_safely(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/hotel', $this->info(['logo' => UploadedFile::fake()->image('logo.png', 300, 120)]))->assertSessionHasNoErrors();
        $first = config('hotel.info.logo');
        $this->assertStringStartsWith('hotel/', $first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($manager)->put('/admin/hotel', $this->info(['logo' => UploadedFile::fake()->image('logo2.jpg', 300, 120)]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);
        $second = config('hotel.info.logo');
        Storage::disk('public')->assertExists($second);

        // Saving without a file keeps it.
        $this->actingAs($manager)->put('/admin/hotel', $this->info())->assertSessionHasNoErrors();
        $this->assertSame($second, config('hotel.info.logo'));

        $this->actingAs($manager)->put('/admin/hotel', $this->info(['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))->assertSessionHasErrors('logo');
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['logo' => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;')]))->assertSessionHasErrors('logo');

        $this->actingAs($manager)->put('/admin/hotel', $this->info(['remove_logo' => 1]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($second);
        $this->assertSame('', config('hotel.info.logo'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_information_appears_on_the_receipt(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();
        $this->actingAs($manager)->put('/admin/hotel', $this->info(['logo' => UploadedFile::fake()->image('logo.png', 300, 120)]));

        $type = RoomType::factory()->create(['hourly_rate' => 5000]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs($manager)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0711223344',
            'pricing_mode' => 'heure', 'duration' => 1, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)->get('/admin/recus/'.Reservation::sole()->id)->assertOk()
            ->assertSee('Hôtel Hôtel Evasion')
            ->assertSee("Quartier Commerce, Bouaké, Côte d'Ivoire")
            ->assertSee('+225 07 11 22 33 44 / +225 27 31 00 00 00')
            ->assertSee('reception@katioka.ci')
            ->assertSee('RCCM CI-BKE-2026-B-123 · NCC 2612345X · Taxe synthétique')
            ->assertDontSee('storage/'.config('hotel.info.logo'), false); // no logo on the ticket (client request)
    }

    public function test_the_information_appears_in_the_back_office_and_guest_pages(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->put('/admin/hotel', $this->info());

        $this->actingAs($manager)->get('/admin')->assertSee('<title>Tableau de bord — Hôtel Hôtel Evasion</title>', false);
        $this->actingAs($manager)->get('/admin')->assertSee('data-initials="HE"', false);
        $this->get('/demande')->assertOk()->assertSee('Hôtel <span>Hôtel Evasion</span>', false)->assertSee('tel:+2250711223344', false);

        $this->app['auth']->forgetGuards();
        $this->get('/admin/login')->assertSee('Hôtel Hôtel Evasion');
    }

    public function test_the_public_site_shows_the_real_contact_details(): void
    {
        $this->actingAs(User::factory()->create())->put('/admin/hotel', $this->info());

        foreach (['/', '/contact', '/chambres/detail', '/offres/detail'] as $uri) {
            $this->get($uri)->assertOk()
                ->assertSee('+225 07 11 22 33 44')
                ->assertSee('tel:+2250711223344', false)
                ->assertDontSee('00 00 00 00 00')
                ->assertDontSee('espace-katioka.example');
        }

        $this->get('/contact')->assertSee('reception@katioka.ci')->assertSee('+225 27 31 00 00 00')
            ->assertSee('https://wa.me/2250566778899', false)
            ->assertSee('https://facebook.com/katioka', false)
            ->assertDontSee('fa-linkedin-in', false); // no fake social links
        $this->get('/')->assertSee('Le confort au cœur de Bouaké')->assertSee('&copy; '.date('Y').' Hôtel Hôtel Evasion', false);
    }

    public function test_empty_details_are_hidden_on_the_public_site(): void
    {
        // Nothing configured yet: no fake number, no empty "Appelez-nous" box, no dead social links.
        $this->get('/')->assertOk()->assertDontSee('Appelez-nous</span>', false)->assertSee('Contactez-nous');
        $this->get('/contact')->assertOk()->assertDontSee('RÉSEAUX SOCIAUX')->assertDontSee('tel:', false)->assertSee("Bouaké, Côte d'Ivoire");
    }

    public function test_settings_saved_for_the_receipt_are_carried_over(): void
    {
        $migration = require database_path('migrations/2026_10_09_200000_move_receipt_identity_to_hotel_info.php');
        $migration->down();
        DB::table('settings')->insert([
            ['key' => 'receipt_name', 'value' => 'Katioka Hôtel', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'receipt_phone', 'value' => '0707070707', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'receipt_legal', 'value' => 'RCCM X', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $this->assertSame(
            ['hotel_legal_other' => 'RCCM X', 'hotel_name' => 'Katioka Hôtel', 'hotel_phone' => '0707070707'],
            DB::table('settings')->orderBy('key')->pluck('value', 'key')->all(),
        );
    }

    public function test_only_settings_managers_can_edit_it(): void
    {
        $this->get('/admin/hotel')->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->reception()->create())->get('/admin/hotel')->assertForbidden();
        $this->actingAs(User::factory()->reception()->create())->put('/admin/hotel', $this->info())->assertForbidden();
        $this->assertSame('Hôtel Evasion', config('hotel.info.name'));
    }
}
