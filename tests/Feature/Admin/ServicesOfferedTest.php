<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\CatalogItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Sale;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * « Services proposés » (Configuration → Hôtel, client request 2026-11-27): not every hotel has a
 * restaurant, a pool or meeting rooms. None is offered by default; only the ticked ones appear
 * (menus, catalogue, venues, Recettes, Pilotage, invoices filter), the others' pages are blocked
 * (404) — and unticking a service keeps its history in the figures.
 */
class ServicesOfferedTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-27 12:00:00');
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->reception = User::factory()->reception()->create();
    }

    private function hotelInfo(array $services): array
    {
        return ['hotel_name' => 'Hôtel Evasion', 'hotel_phone' => '+225 07 11 22 33 44', 'services' => $services];
    }

    private function item(Department $department, string $name, int $price): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    public function test_none_is_offered_by_default_and_everything_is_hidden_and_blocked(): void
    {
        $this->assertSame([Department::Hebergement], Department::offered());
        $dish = $this->item(Department::Restauration, 'Poulet', 5000);
        $venue = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);

        foreach ([$this->manager, $this->reception] as $user) {
            foreach (['complet', 'simple'] as $ui) {
                $user->forceFill(['ui_mode' => $ui])->save();
                $page = $this->actingAs($user)->get('/admin')->assertOk();
                foreach (['/admin/ventes/restauration', '/admin/ventes/plage-piscine', '/admin/evenements', '/admin/salles'] as $url) {
                    $page->assertDontSee('href="'.url($url).'"', false);
                }
                $page->assertDontSee('href="'.route('admin.catalog.index').'"', false);
            }
        }

        // Pages and actions blocked, even typed by hand.
        $this->actingAs($this->manager);
        foreach (['/admin/ventes/restauration', '/admin/ventes/restauration/nouvelle', '/admin/ventes/plage-piscine', '/admin/evenements',
            '/admin/evenements/planning', '/admin/evenements/nouvelle', '/admin/salles'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/admin/ventes/restauration', ['items' => [$dish->id => 1]])->assertNotFound();
        $this->post(route('admin.events.store'), ['venue_id' => $venue->id])->assertNotFound();
        $this->post(route('admin.venues.store'), ['name' => 'X', 'capacity' => 1, 'half_day_rate' => 1, 'day_rate' => 1])->assertNotFound();
        $this->post(route('admin.catalog.store'), ['service' => 'restauration', 'name' => 'X', 'price' => 1])->assertNotFound();
        $this->put(route('admin.catalog.update', $dish), ['name' => 'Y', 'price' => 1])->assertNotFound();
        $this->assertSame(0, Sale::count());

        $this->get(route('admin.catalog.index'))->assertOk()->assertSee("ne propose aucun service en plus de l'hébergement", false);
        $this->get(route('admin.revenue.index'))->assertOk()->assertDontSee('Tous les services');
        $this->get(route('admin.pilotage.index'))->assertOk()->assertDontSee('pilot-kpi--restauration', false);
        $this->get(route('admin.invoices.index'))->assertOk()->assertDontSee('<option value="restauration"', false);
        $this->get(route('admin.hotel.edit'))->assertOk()->assertSee('Services proposés')
            ->assertSee('name="services[]" value="restauration" id="service-restauration" >', false);
    }

    public function test_the_manager_ticks_the_services_offered(): void
    {
        $this->actingAs($this->manager)->put(route('admin.hotel.update'), $this->hotelInfo(['evenements', 'restauration']))->assertSessionHasNoErrors();

        $this->assertSame('restauration,evenements', DB::table('settings')->where('key', 'hotel_services')->value('value')); // fixed order
        $this->assertSame([Department::Hebergement, Department::Restauration, Department::Evenements], Department::offered());
        $this->assertTrue(ActivityLog::query()->where('action', 'settings.hotel')->where('changes', 'like', '%Services propos%')->exists());

        $this->get(route('admin.hotel.edit'))->assertSee('name="services[]" value="restauration" id="service-restauration" checked', false);
        $this->get('/admin')->assertSee(route('admin.sales.index', 'restauration'), false)->assertSee(route('admin.events.index'), false)
            ->assertSee(route('admin.venues.index'), false)->assertDontSee(route('admin.sales.index', 'plage-piscine'), false);
        $this->get('/admin/ventes/restauration')->assertOk();
        $this->get('/admin/evenements')->assertOk();
        $this->get('/admin/ventes/plage-piscine')->assertNotFound();
        $this->get(route('admin.catalog.index'))->assertOk()->assertSee('Restauration')->assertSee('Réunions et événements')->assertDontSee('Plage et piscine');
        $this->get(route('admin.catalog.index', ['service' => 'plage-piscine']))->assertNotFound();

        // Forged values refused; nothing ticked = none.
        $this->put(route('admin.hotel.update'), $this->hotelInfo(['spa']))->assertSessionHasErrors('services.0');
        $this->put(route('admin.hotel.update'), ['hotel_name' => 'Hôtel Evasion', 'hotel_phone' => '+225 07 11 22 33 44'])->assertSessionHasNoErrors();
        $this->assertSame([Department::Hebergement], Department::offered());

        // Only the manager (settings.manage) changes it.
        $this->actingAs($this->reception)->put(route('admin.hotel.update'), $this->hotelInfo(['restauration']))->assertForbidden();
        $this->assertSame([Department::Hebergement], Department::offered());
    }

    public function test_unticking_a_service_keeps_its_history(): void
    {
        config(['hotel.services' => 'restauration']);
        $dish = $this->item(Department::Restauration, 'Poulet', 5000);
        $this->actingAs($this->reception)->post('/admin/ventes/restauration', ['items' => [$dish->id => 2], 'payments' => [['method' => 'wave', 'amount' => 10000]]])
            ->assertSessionHasNoErrors();
        $sale = Sale::sole();

        // The restaurant closes.
        config(['hotel.services' => '']);

        $this->actingAs($this->manager)->get(route('admin.pilotage.index'))->assertOk()
            ->assertSee('pilot-kpi--restauration', false)->assertSee('10 000')->assertSee('Service plus proposé (historique)');
        $this->get(route('admin.revenue.index'))->assertOk()->assertSee('Tous les services')->assertSeeInOrder(['Restauration', '10 000']);
        $this->get(route('admin.invoices.show', $sale->invoice))->assertOk()->assertSee('Restauration')->assertDontSee(route('admin.sales.show', ['restauration', $sale]), false);
        $this->get(route('admin.sales.show', ['restauration', $sale]))->assertNotFound();
        $this->assertSame(1, Sale::count()); // nothing deleted

        // Ticked again: everything is back.
        config(['hotel.services' => 'restauration']);
        $this->get(route('admin.sales.show', ['restauration', $sale]))->assertOk();
    }

    public function test_the_accommodation_never_changes(): void
    {
        $type = RoomType::factory()->create(['hourly_rate' => 3000]);
        $room = Room::factory()->create(['number' => '101', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);

        $this->actingAs($this->reception)->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0, 'payments' => [['method' => 'wave', 'amount' => 6000]],
        ])->assertSessionHasNoErrors();
        $stay = Reservation::query()->where('room_id', $room->id)->sole();

        // No « + Restauration » on the stay while no service is offered.
        $this->item(Department::Restauration, 'Poulet', 5000);
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertDontSee('Consommations reportées sur la chambre');
        config(['hotel.services' => 'restauration']);
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertSee('Consommations reportées sur la chambre')
            ->assertSee(e(route('admin.sales.create', ['restauration', 'chambre' => $stay->id])), false);
    }
}
