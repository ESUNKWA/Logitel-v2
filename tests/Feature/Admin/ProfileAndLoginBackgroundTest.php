<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Mon profil": each staff member edits their own name and login e-mail; Configuration →
 * Hôtel: photo behind the staff login page.
 */
class ProfileAndLoginBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private function hotelInfo(array $extra = []): array
    {
        return ['hotel_name' => 'Hôtel Evasion', 'hotel_phone' => '+225 07 11 22 33 44', ...$extra];
    }

    public function test_a_user_changes_their_own_name_without_password(): void
    {
        $user = User::factory()->create(['name' => 'Ancien Nom', 'email' => 'awa@katioka.ci']);

        $this->actingAs($user)->get('/admin/profil')->assertOk()->assertSee('Mes informations')->assertSee('value="Ancien Nom"', false);
        $this->actingAs($user)->put('/admin/profil', ['name' => 'Awa Koné', 'email' => 'awa@katioka.ci'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/profil')->assertSessionHas('status', 'Informations enregistrées.');

        $this->assertSame('Awa Koné', $user->fresh()->name);
    }

    public function test_changing_the_login_email_needs_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'awa@katioka.ci', 'password' => 'secret1']);

        $this->actingAs($user)->put('/admin/profil', ['name' => $user->name, 'email' => 'awa.kone@katioka.ci'])->assertSessionHasErrors('confirm_password');
        $this->actingAs($user)->put('/admin/profil', ['name' => $user->name, 'email' => 'awa.kone@katioka.ci', 'confirm_password' => 'mauvais'])->assertSessionHasErrors('confirm_password');
        $this->assertSame('awa@katioka.ci', $user->fresh()->email);

        $this->actingAs($user)->put('/admin/profil', ['name' => $user->name, 'email' => 'awa.kone@katioka.ci', 'confirm_password' => 'secret1'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, 'awa.kone@katioka.ci'));
        $this->assertSame('awa.kone@katioka.ci', $user->fresh()->email);

        // The new e-mail is the login from now on.
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'awa.kone@katioka.ci', 'password' => 'secret1'])->assertRedirect('/admin');
    }

    public function test_the_email_must_be_valid_and_not_taken(): void
    {
        User::factory()->create(['email' => 'pris@katioka.ci']);
        $user = User::factory()->create(['password' => 'secret1']);

        $this->actingAs($user)->put('/admin/profil', ['name' => 'X', 'email' => 'pris@katioka.ci', 'confirm_password' => 'secret1'])->assertSessionHasErrors('email');
        $this->actingAs($user)->put('/admin/profil', ['name' => 'X', 'email' => 'pas-un-mail', 'confirm_password' => 'secret1'])->assertSessionHasErrors('email');
        $this->actingAs($user)->put('/admin/profil', ['name' => '', 'email' => $user->email])->assertSessionHasErrors('name');
    }

    public function test_role_and_status_cannot_be_changed_from_the_profile(): void
    {
        $user = User::factory()->reception()->create(['password' => 'secret1']);
        $roleId = $user->role_id;

        $this->actingAs($user)->put('/admin/profil', ['name' => 'Moi', 'email' => $user->email, 'role_id' => 1, 'is_active' => 0, 'password' => 'hack1234'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame($roleId, $user->role_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret1', $user->password));
    }

    public function test_a_temporary_password_must_be_changed_first(): void
    {
        $user = User::factory()->create(['must_change_password' => true, 'name' => 'Avant']);

        $this->actingAs($user)->put('/admin/profil', ['name' => 'Après', 'email' => $user->email])->assertRedirect(route('admin.profile.edit'));
        $this->assertSame('Avant', $user->fresh()->name);
    }

    public function test_the_login_page_shows_the_uploaded_photo(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();

        // Built-in hotel photo by default.
        $this->get('/admin/login')->assertOk()->assertSee('has-photo', false)->assertSee('app/images/login-default.jpg', false);
        $this->assertFileExists(public_path('app/images/login-default.jpg'));

        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['login_background' => UploadedFile::fake()->image('facade.jpg', 1600, 900)]))
            ->assertSessionHasNoErrors();
        $path = config('hotel.info.login_background');
        $this->assertStringStartsWith('hotel/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, DB::table('settings')->where('key', 'hotel_login_background')->value('value'));

        $this->actingAs($manager)->get('/admin/hotel')->assertSee('Retirer mon image', false);
        $this->app['auth']->forgetGuards();
        $this->get('/admin/login')->assertOk()->assertSee('has-photo', false)->assertSee('storage/'.$path, false)->assertDontSee('login-default.jpg', false);

        // Saving again without a file keeps it; removing deletes the file.
        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo())->assertSessionHasNoErrors();
        $this->assertSame($path, config('hotel.info.login_background'));
        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['remove_login_background' => 1]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('', config('hotel.info.login_background'));
        $this->app['auth']->forgetGuards();
        $this->get('/admin/login')->assertSee('app/images/login-default.jpg', false); // back to the default photo
    }

    public function test_the_logo_and_the_login_photo_are_independent(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo([
            'logo' => UploadedFile::fake()->image('logo.png', 300, 120),
            'login_background' => UploadedFile::fake()->image('facade.jpg', 1600, 900),
        ]))->assertSessionHasNoErrors();
        $logo = config('hotel.info.logo');

        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['remove_login_background' => 1]))->assertSessionHasNoErrors();
        $this->assertSame($logo, config('hotel.info.logo'));
        Storage::disk('public')->assertExists($logo);
    }

    public function test_invalid_login_photos_are_refused(): void
    {
        Storage::fake('public');
        $manager = User::factory()->create();

        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['login_background' => UploadedFile::fake()->image('petit.jpg', 300, 200)]))->assertSessionHasErrors('login_background');
        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['login_background' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))->assertSessionHasErrors('login_background');
        $this->actingAs($manager)->put('/admin/hotel', $this->hotelInfo(['login_background' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')]))->assertSessionHasErrors('login_background');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
