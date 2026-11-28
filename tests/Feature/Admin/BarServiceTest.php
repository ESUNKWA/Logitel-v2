<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bar, apart from the restaurant (client request, 2026-11-28): Restauration = food, Bar =
 * drinks — its own catalogue, sales, figures and home tile; one sale per service.
 */
class BarServiceTest extends TestCase
{
    use RefreshDatabase;

    private function item(Department $department, string $name, int $price, ?string $category = null, ?array $days = null): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price, 'category' => $category, 'is_active' => true, 'days' => $days]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    public function test_the_migration_moves_the_drinks_to_the_bar_and_ticks_it(): void
    {
        $water = $this->item(Department::Restauration, 'Eau minérale', 500, 'Boissons', [1, 5]);
        $beer = $this->item(Department::Restauration, 'Bière', 1500, ' boissons ');
        $dish = $this->item(Department::Restauration, 'Garba', 2000, 'Plats');
        DB::table('settings')->updateOrInsert(['key' => 'hotel_services'], ['value' => 'restauration,plage_piscine']);
        Cache::forget('hotel.settings');

        $migration = require database_path('migrations/2026_11_28_200000_split_bar_from_restauration.php');
        $migration->up();
        $migration->up(); // run again: nothing changes

        $this->assertSame(Department::Bar, $water->fresh()->department);
        $this->assertNull($water->fresh()->days); // the bar serves everything every day
        $this->assertSame(Department::Bar, $beer->fresh()->department);
        $this->assertSame(Department::Restauration, $dish->fresh()->department);
        $this->assertSame('restauration,bar,plage_piscine', DB::table('settings')->where('key', 'hotel_services')->value('value'));
    }

    public function test_a_hotel_without_restaurant_does_not_get_the_bar(): void
    {
        DB::table('settings')->updateOrInsert(['key' => 'hotel_services'], ['value' => 'plage_piscine']);

        (require database_path('migrations/2026_11_28_200000_split_bar_from_restauration.php'))->up();

        $this->assertSame('plage_piscine', DB::table('settings')->where('key', 'hotel_services')->value('value'));
    }

    public function test_the_bar_sells_on_its_own_and_counts_apart(): void
    {
        config(['hotel.services' => 'restauration,bar']);
        $this->travelTo('2026-11-28 20:00:00');
        $dish = $this->item(Department::Restauration, 'Poulet braisé', 6000, 'Plats');
        $beer = $this->item(Department::Bar, 'Bière (Flag, Bock)', 1500, 'Bières');
        $reception = User::factory()->reception()->simpleUi()->create();

        // Its own sale page, with drinks only and no menu of the day.
        $this->actingAs($reception)->get(route('admin.sales.create', 'bar'))->assertOk()
            ->assertSee('Nouvelle vente — Bar')->assertSee('Bière (Flag, Bock)')
            ->assertDontSee('Poulet braisé')->assertDontSee('Menu du jour');

        // One sale per service: the bar's is stamped Bar, on its sale, invoice and payment.
        $this->post(route('admin.sales.store', 'bar'), ['items' => [$beer->id => 2], 'payments' => [['method' => 'especes', 'amount' => 3000, 'received' => 3000]]])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.sales.store', 'restauration'), ['items' => [$dish->id => 1]])->assertSessionHasNoErrors();
        $bar = Sale::query()->where('department', 'bar')->sole();
        $this->assertSame(Department::Bar, $bar->invoice->department);
        $this->assertSame(Department::Bar, $bar->invoice->payments()->sole()->department);
        // A restaurant item can't be sold at the bar (and the other way round).
        $this->post(route('admin.sales.store', 'bar'), ['items' => [$dish->id => 1]])->assertSessionHasErrors();
        $this->assertSame(2, Sale::count());

        // The réception's home: a Bar tile with today's figures and a direct sale.
        $this->get('/admin')->assertOk()
            ->assertSeeInOrder(['Restauration', '1 vente', '6 000 F', 'Bar', '1 vente', '3 000 F'])
            ->assertSee(route('admin.sales.create', 'bar'), false);

        // The manager: a Bar tab in the catalogue and the bar's turnover apart in Pilotage.
        $manager = User::factory()->create();
        $this->actingAs($manager)->get(route('admin.catalog.index', ['service' => 'bar']))->assertOk()
            ->assertSee('Bière (Flag, Bock)')->assertDontSee('Menu de la semaine');
        $this->get(route('admin.pilotage.index'))->assertOk()->assertSee('Bar')->assertSee('3 000');
    }

    public function test_the_bar_is_blocked_when_not_offered(): void
    {
        config(['hotel.services' => 'restauration']);
        $this->item(Department::Bar, 'Bière', 1500);

        $this->actingAs(User::factory()->reception()->create())->get(route('admin.sales.create', 'bar'))->assertNotFound();
        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertDontSee(route('admin.sales.create', 'bar'), false);
    }
}
