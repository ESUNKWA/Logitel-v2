<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * « Clôture de caisse » removed (client request, 2026-11-24): no page, menu, permission or tile
 * left, and the clean-up migration never loses a closing already declared.
 */
class CashClosingRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_24_100000_remove_cash_closings.php');
    }

    public function test_no_page_menu_or_permission_left(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.cash-closings.index'));
        $this->assertNotContains('cash.close', Permission::values());
        $this->assertNotContains('cash.review', Permission::values());
        $this->assertFalse(Schema::hasTable('cash_closings'));

        $this->actingAs(User::factory()->create())->get('/admin/caisse')->assertNotFound();

        // The Gérant (figures) and the Réception (« Ma recette du jour », where « Clôturer ma caisse » was).
        foreach ([[User::factory()->create(), 'Recettes'], [User::factory()->reception()->create(), 'Ma recette du jour']] as [$user, $landmark]) {
            foreach (['complet', 'simple'] as $ui) {
                $user->forceFill(['ui_mode' => $ui])->save();
                $this->actingAs($user)->get('/admin')
                    ->assertOk()->assertSee($landmark)->assertDontSee('Clôture de caisse')->assertDontSee('Clôturer ma caisse')->assertDontSee('Écarts de caisse');
            }
        }

        $this->actingAs(User::factory()->create())->get(route('admin.roles.create'))->assertOk()->assertDontSee('Clôture de caisse');
    }

    public function test_the_migration_takes_the_permissions_back_from_every_role(): void
    {
        $role = Role::query()->where('slug', 'reception')->firstOrFail();
        DB::table('permission_role')->insert([['role_id' => $role->id, 'permission' => 'cash.close'], ['role_id' => $role->id, 'permission' => 'cash.review']]);

        $this->migration()->up();

        $this->assertSame(0, DB::table('permission_role')->whereIn('permission', ['cash.close', 'cash.review'])->count());
        $this->assertTrue(DB::table('permission_role')->where('role_id', $role->id)->exists()); // other permissions kept
    }

    public function test_a_table_holding_closings_is_kept_an_empty_one_dropped(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
        });
        DB::table('cash_closings')->insert(['business_date' => '2026-11-20']);

        $this->migration()->up();
        $this->assertTrue(Schema::hasTable('cash_closings'));
        $this->assertSame(1, DB::table('cash_closings')->count());

        DB::table('cash_closings')->delete();
        $this->migration()->up();
        $this->assertFalse(Schema::hasTable('cash_closings'));
    }
}
