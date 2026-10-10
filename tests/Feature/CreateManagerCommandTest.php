<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * php artisan hotel:create-manager — first login on a fresh (production) database.
 */
class CreateManagerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_manager_who_can_log_in(): void
    {
        $this->artisan('hotel:create-manager', ['email' => 'gerant@katioka.ci', '--name' => 'Mme Koffi', '--password' => 'Katioka-2026-ok'])
            ->expectsOutputToContain('Compte créé')
            ->assertSuccessful();

        $user = User::where('email', 'gerant@katioka.ci')->sole();
        $this->assertTrue($user->is_active);
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertTrue($user->role->is_system); // Gérant: every permission

        $this->post('/admin/login', ['email' => 'gerant@katioka.ci', 'password' => 'Katioka-2026-ok'])->assertRedirect('/admin');
        $this->get('/admin/parametres')->assertOk();
    }

    public function test_it_asks_interactively_and_rejects_a_weak_password(): void
    {
        $this->artisan('hotel:create-manager')
            ->expectsQuestion('E-mail de connexion', 'gerant@katioka.ci')
            ->expectsQuestion('Nom affiché', 'Gérant')
            ->expectsQuestion('Mot de passe (4 caractères minimum)', 'abc')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'gerant@katioka.ci')->count());
    }

    public function test_it_resets_an_existing_account(): void
    {
        $user = User::factory()->reception()->inactive()->create(['email' => 'chef@katioka.ci']);

        $this->artisan('hotel:create-manager', ['email' => 'chef@katioka.ci', '--name' => 'Chef', '--password' => 'Nouveau-Pass-2026'])
            ->expectsOutputToContain('Compte réinitialisé')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->role->is_system);
        $this->assertTrue(Hash::check('Nouveau-Pass-2026', $user->password));
        $this->assertSame(1, User::where('email', 'chef@katioka.ci')->count());
    }

    public function test_four_characters_is_the_only_password_rule(): void
    {
        foreach (['abcd', '1234', 'ABCD'] as $i => $password) {
            $this->artisan('hotel:create-manager', ['email' => "ok{$i}@katioka.ci", '--name' => 'Ok', '--password' => $password])->assertSuccessful();
        }
        $this->assertSame(3, User::count());

        $this->artisan('hotel:create-manager', ['email' => 'court@katioka.ci', '--name' => 'Court', '--password' => 'abc'])->assertFailed();
        $this->assertSame(0, User::where('email', 'court@katioka.ci')->count());
    }
}
