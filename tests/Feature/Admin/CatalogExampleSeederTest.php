<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\Venue;
use Database\Seeders\CatalogExampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Example catalogue (restaurant, beach & pool, meetings & events) and venues: inserted once,
 * safe to run again, never overwriting an existing item's price.
 */
class CatalogExampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_each_service_and_never_overwrites(): void
    {
        $existing = new CatalogItem(['name' => 'Garba', 'price' => 2500]);
        $existing->forceFill(['department' => Department::Restauration])->save();

        $this->seed(CatalogExampleSeeder::class);
        $count = CatalogItem::count();
        $this->seed(CatalogExampleSeeder::class); // again: nothing duplicated

        $this->assertSame($count, CatalogItem::count());
        $this->assertSame('2500.00', (string) $existing->fresh()->price); // kept as it was
        foreach (Department::salesServices() as $service) {
            $this->assertGreaterThan(5, CatalogItem::query()->ofDepartment($service)->count());
        }
        $this->assertTrue(CatalogItem::query()->where('name', 'Entrée adulte (journée)')->sole()->counts_entry);
        $this->assertFalse(CatalogItem::query()->where('name', 'Transat')->sole()->counts_entry);
        $this->assertSame(0, CatalogItem::query()->where('department', '!=', 'plage_piscine')->where('counts_entry', true)->count());
        $this->assertSame(2, Venue::count());

        // Each catalogue reflects its service: food in Restauration, drinks in Bar.
        foreach ([Department::PlagePiscine, Department::Evenements] as $service) {
            $this->assertSame(0, CatalogItem::query()->ofDepartment($service)
                ->where(fn ($q) => $q->whereIn('category', ['Boissons', 'Bar', 'Restauration', 'Plats'])
                    ->orWhere('name', 'like', '%Eau%')->orWhere('name', 'like', '%Cocktail%')->orWhere('name', 'like', '%Déjeuner%')->orWhere('name', 'like', '%Pause-café%'))
                ->count(), $service->getLabel());
        }
        // Drinks at the bar, food at the restaurant (client request, 2026-11-28).
        $this->assertTrue(CatalogItem::query()->ofDepartment(Department::Bar)->where('name', 'Eau minérale 50 cl')->exists());
        $this->assertFalse(CatalogItem::query()->ofDepartment(Department::Restauration)->where('name', 'like', '%Eau%')->exists());
        $this->assertTrue(CatalogItem::query()->ofDepartment(Department::Restauration)->where('name', 'Garba')->exists());
    }
}
