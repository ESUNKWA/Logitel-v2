<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\EnsureAdminAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * php artisan hotel:ensure-admin — default manager created at container start on an empty
 * database only, never touching existing accounts.
 */
class EnsureAdminAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function test_on_an_empty_database_it_creates_a_manager_with_a_random_password(): void
    {
        $this->artisan('hotel:ensure-admin')
            ->expectsOutputToContain('Compte administrateur par défaut créé')
            ->expectsOutputToContain(EnsureAdminAccount::DEFAULT_EMAIL)
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame(EnsureAdminAccount::DEFAULT_EMAIL, $admin->email);
        $this->assertTrue($admin->role->is_system);       // Gérant: every permission
        $this->assertTrue($admin->is_active);
        $this->assertTrue((bool) $admin->must_change_password); // forced change at first login
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_the_printed_random_password_works_and_the_change_is_forced(): void
    {
        $output = $this->withoutMockingConsoleOutput()->artisan('hotel:ensure-admin');
        $this->assertSame(0, $output);
        preg_match('/Mot de passe : (\S+)/', Artisan::output(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'The generated password is printed.');

        $this->post('/admin/login', ['email' => EnsureAdminAccount::DEFAULT_EMAIL, 'password' => $m[1]])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect(route('admin.profile.edit')); // must change it first
    }

    public function test_it_uses_the_credentials_from_the_environment(): void
    {
        putenv('ADMIN_EMAIL=gerant@katioka.ci');
        putenv('ADMIN_PASSWORD=Katioka-Depart-2026');

        $this->artisan('hotel:ensure-admin')
            ->expectsOutputToContain('gerant@katioka.ci')
            ->doesntExpectOutputToContain('Katioka-Depart-2026') // never echoed back
            ->assertSuccessful();

        $admin = User::sole();
        $this->assertSame('gerant@katioka.ci', $admin->email);
        $this->assertTrue(Hash::check('Katioka-Depart-2026', $admin->password));
    }

    public function test_a_weak_environment_password_is_refused(): void
    {
        putenv('ADMIN_PASSWORD=abc'); // under 4 characters

        $this->artisan('hotel:ensure-admin')->expectsOutputToContain('NON créé')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_it_never_touches_an_existing_database(): void
    {
        $existing = User::factory()->reception()->create(['email' => 'reception@katioka.ci']);
        putenv('ADMIN_EMAIL=reception@katioka.ci');
        putenv('ADMIN_PASSWORD=Autre-Pass-2026x');

        $this->artisan('hotel:ensure-admin')->expectsOutputToContain('Comptes existants')->assertSuccessful();
        $this->artisan('hotel:ensure-admin')->assertSuccessful(); // every restart: still nothing

        $this->assertSame(1, User::count());
        $this->assertFalse(Hash::check('Autre-Pass-2026x', $existing->fresh()->password));
        $this->assertFalse($existing->fresh()->role->is_system);
    }

    public function test_random_passwords_meet_the_password_policy(): void
    {
        foreach (range(1, 50) as $i) {
            $password = EnsureAdminAccount::randomPassword();
            $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z0-9]{16}$/', $password);
        }
    }
}
