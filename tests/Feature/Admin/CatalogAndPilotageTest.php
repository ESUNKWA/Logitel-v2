<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\CatalogItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Catalogue des prestations » (catalog.manage) and « Pilotage » (dashboard.revenue): the
 * turnover — cash received — globally and per service, by period, compared with the previous
 * period and the year before, its evolution and each sales service's activity.
 */
class CatalogAndPilotageTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        // The hotel offers these services (Configuration → Hôtel; none by default).
        config(['hotel.services' => 'restauration,plage_piscine,evenements']);
        $this->travelTo('2026-11-25 12:00:00');
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->reception = User::factory()->reception()->create();
    }

    private function item(Department $department, string $name, int $price, bool $countsEntry = false): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price, 'counts_entry' => $countsEntry]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    private function cashSale(CatalogItem $item, int $quantity, string $at): void
    {
        $this->travelTo($at);
        $this->actingAs($this->reception)->post(route('admin.sales.store', $item->department->slug()), [
            'items' => [$item->id => $quantity], 'payments' => [['method' => 'wave', 'amount' => (int) $item->price * $quantity]],
        ])->assertSessionHasNoErrors();
    }

    private function cashStay(int $amount, string $at): void
    {
        $this->travelTo($at);
        $type = RoomType::factory()->create(['hourly_rate' => $amount]);
        $room = Room::factory()->create(['number' => 'P'.fake()->unique()->numberBetween(100, 999), 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs($this->reception)->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0, 'payments' => [['method' => 'wave', 'amount' => $amount]],
        ])->assertSessionHasNoErrors();
    }

    // ---- Catalogue -----------------------------------------------------------------------------

    public function test_the_manager_manages_the_catalogue(): void
    {
        $this->actingAs($this->manager)->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Attiéké poisson', 'category' => 'Plats', 'price' => '4 500'])
            ->assertRedirect(route('admin.catalog.index', ['service' => 'restauration']));
        $item = CatalogItem::sole();
        $this->assertSame([Department::Restauration, 'Attiéké poisson', 'Plats', '4500.00', true, false],
            [$item->department, $item->name, $item->category, (string) $item->price, $item->is_active, $item->counts_entry]);

        // Same name in the same service refused; entry flag only for the pool.
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Attiéké poisson', 'price' => 1])->assertSessionHasErrors('name');
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Café', 'price' => 500, 'counts_entry' => '1']);
        $this->assertFalse(CatalogItem::query()->where('name', 'Café')->sole()->counts_entry);
        $this->post(route('admin.catalog.store'), ['service' => 'plage-piscine', 'name' => 'Entrée', 'price' => 2000, 'counts_entry' => '1']);
        $this->assertTrue(CatalogItem::query()->where('name', 'Entrée')->sole()->counts_entry);
        $this->post(route('admin.catalog.store'), ['service' => 'hebergement', 'name' => 'Nuit', 'price' => 1])->assertNotFound();
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'Négatif', 'price' => -5])->assertSessionHasErrors('price');

        $this->put(route('admin.catalog.update', $item), ['name' => 'Attiéké poisson', 'price' => '5 000', 'is_active' => '0', 'counts_entry' => '0'])->assertRedirect();
        $this->assertSame(['5000.00', false], [(string) $item->fresh()->price, $item->fresh()->is_active]);
        $this->get(route('admin.catalog.index', ['service' => 'restauration']))->assertOk()->assertSee('Attiéké poisson');
        $this->assertTrue(ActivityLog::query()->where('category', 'configuration')->where('description', 'like', '%Attiéké poisson%')->exists());
    }

    public function test_a_sold_item_is_deactivated_not_deleted(): void
    {
        $sold = $this->item(Department::Restauration, 'Poulet', 5000);
        $unsold = $this->item(Department::Restauration, 'Soupe', 2000);
        $this->cashSale($sold, 1, '2026-11-25 12:00:00');

        $this->actingAs($this->manager)->delete(route('admin.catalog.destroy', $sold))->assertSessionHasErrors('item');
        $this->assertNotNull($sold->fresh());
        $this->delete(route('admin.catalog.destroy', $unsold))->assertRedirect();
        $this->assertNull($unsold->fresh());
    }

    public function test_only_the_manager_manages_the_catalogue(): void
    {
        $item = $this->item(Department::Restauration, 'Poulet', 5000);
        $this->actingAs($this->reception)->get(route('admin.catalog.index'))->assertForbidden();
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'X', 'price' => 1])->assertForbidden();
        $this->put(route('admin.catalog.update', $item), ['name' => 'X', 'price' => 1])->assertForbidden();
        $this->delete(route('admin.catalog.destroy', $item))->assertForbidden();
        $this->assertSame('Poulet', $item->fresh()->name);
    }

    // ---- Pilotage ------------------------------------------------------------------------------

    public function test_turnover_globally_and_per_service_with_comparisons(): void
    {
        $dish = $this->item(Department::Restauration, 'Poulet', 5000);
        $entry = $this->item(Department::PlagePiscine, 'Entrée', 2000, countsEntry: true);

        // October (previous period of « ce mois »): 10 000 accommodation.
        $this->cashStay(10000, '2026-10-10 10:00:00');
        // November: 20 000 accommodation, 15 000 restaurant, 6 000 pool (3 entries).
        $this->cashStay(20000, '2026-11-05 10:00:00');
        $this->cashSale($dish, 3, '2026-11-10 13:00:00');
        $this->cashSale($entry, 3, '2026-11-20 15:00:00');
        // A cancelled sale and a sale left due: no cash.
        $this->travelTo('2026-11-21 12:00:00');
        $this->actingAs($this->reception)->post(route('admin.sales.store', 'restauration'), ['items' => [$dish->id => 1]]);

        $this->travelTo('2026-11-25 12:00:00');
        $page = $this->actingAs($this->manager)->get(route('admin.pilotage.index'))->assertOk();
        $page->assertSee("Chiffre d'affaires global", false)->assertSee('41 000')
            ->assertSeeInOrder(['Hébergement', '20 000', 'Restauration', '15 000', 'Plage et piscine', '6 000'])
            ->assertSee('+310,0 %')            // 41 000 vs 10 000 in October
            ->assertSee('Évolution par jour')->assertSee('Panier moyen')->assertSee('Entrées');

        // Turnover = cash: the sale left due (5 000) isn't in it, but is in « Vendu ».
        $this->get(route('admin.pilotage.index', ['periode' => 'mois-dernier']))->assertOk()->assertSee('10 000');

        // Custom range over 62 days: per month.
        $this->get(route('admin.pilotage.index', ['du' => '2026-01-01', 'au' => '2026-11-25']))->assertOk()
            ->assertSee('Évolution par mois')->assertSee('nov. 2026')->assertSee('51 000');

        $csv = $this->get(route('admin.pilotage.export', ['periode' => 'mois']))->streamedContent();
        $this->assertStringContainsString('Journée;Hébergement;Restauration;"Plage et piscine";"Réunions et événements";Total', $csv);
        $this->assertStringContainsString('10/11;0;15000;0;0;15000', $csv);
        $this->assertTrue(ActivityLog::query()->where('action', 'export.pilotage')->exists());
    }

    public function test_forged_periods_fall_back_and_only_the_manager_sees_it(): void
    {
        $this->actingAs($this->manager)->get(route('admin.pilotage.index', ['du' => 'hier', 'au' => ['x'], 'periode' => '<b>']))->assertOk();
        $this->get(route('admin.pilotage.index', ['du' => '2099-01-01']))->assertOk()->assertSee('25/11/2026');

        $this->actingAs($this->reception)->get(route('admin.pilotage.index'))->assertForbidden();
        $this->get(route('admin.pilotage.export'))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.pilotage.index'), false);
        $this->actingAs($this->manager)->get('/admin')->assertSee(route('admin.pilotage.index'), false);
    }

    public function test_only_the_services_offered_are_shown(): void
    {
        // Configuration → Hôtel: only the accommodation offered.
        config(['hotel.services' => '']);
        $this->actingAs($this->manager)->get(route('admin.pilotage.index'))->assertOk()
            ->assertSee('pilot-kpi--hebergement', false)
            ->assertDontSee('pilot-kpi--restauration', false)->assertDontSee('pilot-kpi--plage_piscine', false)->assertDontSee('pilot-kpi--evenements', false);

        // Restaurant offered: shown even before any sale; the others stay hidden.
        config(['hotel.services' => 'restauration']);
        $this->get(route('admin.pilotage.index'))->assertOk()
            ->assertSee('pilot-kpi--restauration', false)->assertDontSee('pilot-kpi--plage_piscine', false);
        $this->assertSame(0, Sale::count());
    }
}
