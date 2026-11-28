<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restaurant menu by day (client request, 2026-11-28): each dish has its days of service (none =
 * every day); the sale form shows today's menu (business day) and folds the other dishes in
 * « Hors menu »; the catalogue shows the week; the réception's home tile shows today's dishes.
 */
class MenuByDayTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.services' => 'restauration,plage_piscine']);
        $this->travelTo('2026-11-23 12:00:00'); // a Monday
        $this->manager = User::factory()->create();
    }

    private function item(Department $department, string $name, ?array $days = null, ?string $category = 'Plats'): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => 5000, 'category' => $category, 'is_active' => true, 'days' => $days]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    public function test_the_catalogue_stores_the_days_of_service_of_restaurant_dishes(): void
    {
        $this->actingAs($this->manager)->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Kedjenou', 'price' => '6 500', 'days' => ['5', '1', '1']])
            ->assertSessionHasNoErrors();
        $this->assertSame([1, 5], CatalogItem::query()->where('name', 'Kedjenou')->first()->days);

        // All seven days ticked, or none: every day.
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Garba', 'price' => 2000, 'days' => ['1', '2', '3', '4', '5', '6', '7']]);
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Bissap', 'price' => 1000]);
        $this->assertNull(CatalogItem::query()->where('name', 'Garba')->value('days'));
        $this->assertNull(CatalogItem::query()->where('name', 'Bissap')->value('days'));

        // Updating a line without any day ticked: back to every day.
        $kedjenou = CatalogItem::query()->where('name', 'Kedjenou')->first();
        $this->put(route('admin.catalog.update', $kedjenou), ['name' => 'Kedjenou', 'price' => 6500, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($kedjenou->fresh()->days);

        // Out-of-range days are refused; the beach & pool has no menu by day.
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Foutou', 'price' => 5500, 'days' => ['8']])->assertSessionHasErrors('days.0');
        $this->post(route('admin.catalog.store'), ['service' => 'plage-piscine', 'name' => 'Transat', 'price' => 1000, 'days' => ['1']])->assertSessionHasNoErrors();
        $this->assertNull(CatalogItem::query()->where('name', 'Transat')->value('days'));
    }

    public function test_the_catalogue_shows_the_week_and_the_day_toggles(): void
    {
        $this->item(Department::Restauration, 'Kedjenou de poulet', [1, 5]);
        $this->item(Department::Restauration, 'Foutou sauce graine', [3]);
        $this->item(Department::Restauration, 'Jus de bissap', null, 'Boissons');

        $this->actingAs($this->manager)->get(route('admin.catalog.index', ['service' => 'restauration']))->assertOk()
            ->assertSee('Menu de la semaine')
            ->assertSeeInOrder(['Lundi', 'Aujourd&#039;hui', 'Kedjenou de poulet', 'Mardi', 'Pas de plat du jour', 'Mercredi', 'Foutou sauce graine', 'Jeudi', 'Vendredi', 'Kedjenou de poulet'], false)
            ->assertSee('1 autre prestation servie tous les jours')
            ->assertSee('name="days[]"', false);

        // No menu by day elsewhere.
        $this->get(route('admin.catalog.index', ['service' => 'plage-piscine']))->assertOk()
            ->assertDontSee('Menu de la semaine')->assertDontSee('name="days[]"', false);
    }

    public function test_the_sale_form_shows_todays_menu_and_folds_the_other_dishes(): void
    {
        $this->item(Department::Restauration, 'Kedjenou de poulet', [1, 5]);
        $foutou = $this->item(Department::Restauration, 'Foutou sauce graine', [3]);
        $this->item(Department::Restauration, 'Jus de bissap', null, 'Boissons');

        $page = $this->actingAs(User::factory()->reception()->create())->get(route('admin.sales.create', 'restauration'))->assertOk();
        $page->assertSeeInOrder(['Menu du jour — lundi', 'Boissons', 'Jus de bissap', 'Plats', 'Kedjenou de poulet', 'Hors menu du jour', 'Foutou sauce graine'], false)
            ->assertDontSee('<details class="sale-off-menu" open', false); // folded

        // An off-menu dish can still be sold (special order).
        $this->post(route('admin.sales.store', 'restauration'), ['items' => [$foutou->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(1, Sale::count());
    }

    public function test_the_menu_follows_the_business_day(): void
    {
        $this->item(Department::Restauration, 'Kedjenou de poulet', [1]);
        $this->item(Department::Restauration, 'Foutou sauce graine', [2]);

        // Tuesday 02:00 is still Monday's business day (it starts at 06:00).
        $this->travelTo('2026-11-24 02:00:00');
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.sales.create', 'restauration'))->assertOk()
            ->assertSeeInOrder(['Menu du jour — lundi', 'Kedjenou de poulet', 'Hors menu du jour', 'Foutou sauce graine'], false);
    }

    public function test_with_nothing_on_todays_menu_the_other_dishes_are_unfolded(): void
    {
        $this->item(Department::Restauration, 'Foutou sauce graine', [3]);

        $this->actingAs(User::factory()->reception()->create())->get(route('admin.sales.create', 'restauration'))->assertOk()
            ->assertSee('Aucun plat prévu au menu de ce jour')
            ->assertSee('<details class="sale-off-menu"  open', false);
    }

    public function test_the_beach_sale_form_has_no_menu(): void
    {
        $this->item(Department::PlagePiscine, 'Transat', null, 'Locations');

        $this->actingAs(User::factory()->reception()->create())->get(route('admin.sales.create', 'plage-piscine'))->assertOk()
            ->assertSee('Transat')->assertDontSee('Menu du jour')->assertDontSee('Hors menu du jour');
    }

    public function test_the_home_tile_shows_todays_dishes(): void
    {
        $this->item(Department::Restauration, 'Kedjenou de poulet', [1, 5]);
        $this->item(Department::Restauration, 'Garba', [1]);
        $this->item(Department::Restauration, 'Foutou sauce graine', [3]);
        $this->item(Department::Restauration, 'Jus de bissap', null, 'Boissons'); // every day: not « du jour »

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee('Menu du jour : Garba, Kedjenou de poulet')
            ->assertDontSee('Foutou sauce graine');
    }
}
