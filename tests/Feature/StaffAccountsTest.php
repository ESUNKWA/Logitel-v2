<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'NouveauMdp2026';

    private function manager(): User
    {
        return User::factory()->create();
    }

    /**
     * A non-system role that can manage staff but lacks most other permissions.
     */
    private function staffAdminRole(): Role
    {
        $role = Role::create(['name' => 'Chef de réception']);
        $role->syncPermissions([
            Permission::UsersManage->value,
            Permission::RolesManage->value,
            Permission::RoomsView->value,
        ]);

        return $role;
    }

    // --- Password change -------------------------------------------------------------

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'password', 'must_change_password' => true]);

        $this->actingAs($user)->put('/admin/profil/mot-de-passe', [
            'current_password' => 'password',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.profile.edit'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $user->password));
        $this->assertNotSame(self::STRONG_PASSWORD, $user->password);
        $this->assertFalse($user->must_change_password);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->put('/admin/profil/mot-de-passe', [
            'current_password' => 'mauvais',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_shorter_than_four_characters_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->put('/admin/profil/mot-de-passe', [
            'current_password' => 'password',
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');

        // 4 characters is the only rule (client request).
        $this->actingAs($user)->put('/admin/profil/mot-de-passe', [
            'current_password' => 'password',
            'password' => 'abcd',
            'password_confirmation' => 'abcd',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('abcd', $user->fresh()->password));
    }

    public function test_logout_everywhere_drops_other_sessions_and_rotates_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $oldToken = $user->remember_token;

        foreach (['current', 'other-device'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $user->logoutEverywhere(exceptSessionId: 'current');

        $this->assertSame(['current'], DB::table('sessions')->pluck('id')->all());
        $this->assertNotSame($oldToken, $user->fresh()->remember_token);
    }

    // --- Temporary password / deactivation -------------------------------------------

    public function test_user_with_temporary_password_is_confined_to_profile(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.profile.edit'));
        $this->actingAs($user)->get('/admin/guests')->assertRedirect(route('admin.profile.edit'));
        $this->actingAs($user)->get('/admin/profil')->assertOk();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'password']);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->assertGuest();
    }

    public function test_user_deactivated_while_logged_in_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'mauvais']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // --- User management ------------------------------------------------------------

    public function test_manager_can_create_a_staff_account(): void
    {
        $receptionRole = Role::where('slug', 'reception')->firstOrFail();

        $this->actingAs($this->manager())->post('/admin/users', [
            'name' => 'Awa Koné',
            'email' => 'Awa.Kone@Espace-Katioka.local',
            'role_id' => $receptionRole->id,
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $created = User::where('email', 'awa.kone@espace-katioka.local')->firstOrFail();
        $this->assertSame($receptionRole->id, $created->role_id);
        $this->assertTrue($created->is_active);
        $this->assertTrue($created->must_change_password);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $created->password));
    }

    public function test_manager_can_deactivate_and_reset_a_staff_account(): void
    {
        $target = User::factory()->reception()->create();

        $this->actingAs($this->manager())->put("/admin/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $target->role_id,
            'is_active' => '0',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertRedirect(route('admin.users.index'))->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertTrue($target->must_change_password);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $target->password));
    }

    public function test_user_cannot_edit_their_own_account_through_staff_management(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)->get("/admin/users/{$manager->id}/edit")->assertForbidden();
        $this->actingAs($manager)->put("/admin/users/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'role_id' => $manager->role_id,
            'is_active' => '0',
        ])->assertForbidden();

        $this->assertTrue($manager->fresh()->is_active);
    }

    public function test_staff_admin_cannot_assign_a_role_with_more_rights_than_their_own(): void
    {
        $staffAdmin = User::factory()->create(['role_id' => $this->staffAdminRole()->id]);
        $managerRole = Role::where('slug', 'gerant')->firstOrFail();

        $this->actingAs($staffAdmin)->post('/admin/users', [
            'name' => 'Intrus',
            'email' => 'intrus@espace-katioka.local',
            'role_id' => $managerRole->id,
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'intrus@espace-katioka.local']);
    }

    public function test_staff_admin_cannot_edit_a_higher_ranked_account(): void
    {
        $staffAdmin = User::factory()->create(['role_id' => $this->staffAdminRole()->id]);
        $manager = $this->manager();

        $this->actingAs($staffAdmin)->get("/admin/users/{$manager->id}/edit")->assertForbidden();
        $this->actingAs($staffAdmin)->put("/admin/users/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'role_id' => $manager->role_id,
            'is_active' => '1',
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $manager->fresh()->password));
    }

    // --- Role management ------------------------------------------------------------

    public function test_manager_can_create_a_role_with_permissions(): void
    {
        $this->actingAs($this->manager())->post('/admin/roles', [
            'name' => 'Comptable',
            'description' => 'Suivi des factures',
            'permissions' => [Permission::InvoicesView->value, Permission::DashboardRevenue->value],
            'is_system' => '1',
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHasNoErrors();

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $this->assertFalse($role->is_system, 'is_system must not be mass assignable.');
        $this->assertSame('comptable', $role->slug);
        $this->assertEqualsCanonicalizing(['invoices.view', 'dashboard.revenue'], $role->permissionValues());
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->manager())->post('/admin/roles', [
            'name' => 'Pirate',
            'permissions' => ['everything'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'Pirate']);
    }

    public function test_staff_admin_cannot_grant_permissions_they_lack(): void
    {
        $staffAdmin = User::factory()->create(['role_id' => $this->staffAdminRole()->id]);

        $this->actingAs($staffAdmin)->post('/admin/roles', [
            'name' => 'Super réception',
            'permissions' => [Permission::RoomsView->value, Permission::PaymentsDelete->value],
        ])->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('roles', ['name' => 'Super réception']);
    }

    public function test_user_cannot_edit_their_own_role(): void
    {
        $role = $this->staffAdminRole();
        $staffAdmin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($staffAdmin)->put("/admin/roles/{$role->id}", [
            'name' => $role->name,
            'permissions' => [Permission::UsersManage->value],
        ])->assertForbidden();
    }

    public function test_system_role_cannot_be_edited_or_deleted(): void
    {
        $managerRole = Role::where('slug', 'gerant')->firstOrFail();
        $actor = User::factory()->create(['role_id' => Role::create(['name' => 'Autre direction'])->id]);
        // Give the actor every permission without making their role a system one.
        $actor->role->syncPermissions(Permission::values());

        $this->actingAs($actor)->get("/admin/roles/{$managerRole->id}/edit")->assertForbidden();
        $this->actingAs($actor)->delete("/admin/roles/{$managerRole->id}")->assertForbidden();
        $this->assertModelExists($managerRole);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $receptionRole = Role::where('slug', 'reception')->firstOrFail();
        User::factory()->reception()->create();

        $this->actingAs($this->manager())
            ->delete("/admin/roles/{$receptionRole->id}")
            ->assertSessionHasErrors('role');

        $this->assertModelExists($receptionRole);
    }

    public function test_staff_pages_render_for_manager(): void
    {
        $manager = $this->manager();
        $reception = User::factory()->reception()->create();
        $receptionRole = $reception->role;

        foreach (['/admin/users', '/admin/users/create', "/admin/users/{$reception->id}/edit", '/admin/roles', '/admin/roles/create', "/admin/roles/{$receptionRole->id}/edit"] as $uri) {
            $this->actingAs($manager)->get($uri)->assertOk();
        }
    }
}
