<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\Sale;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * « Autres services » on the réception's home (client request, 2026-11-28): a tile per service
 * offered, with today's figures and a direct « Nouvelle vente » / « Nouvelle réservation ».
 */
class HomeOtherServicesTest extends TestCase
{
    use RefreshDatabase;

    private CatalogItem $dish;

    private CatalogItem $entry;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.services' => 'restauration,plage_piscine,evenements']);
        $this->travelTo('2026-11-25 12:00:00');
        $this->dish = $this->item(Department::Restauration, 'Poulet braisé', 5000);
        $this->entry = $this->item(Department::PlagePiscine, 'Entrée adulte', 2000, countsEntry: true);
    }

    private function item(Department $department, string $name, int $price, bool $countsEntry = false): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price, 'counts_entry' => $countsEntry, 'is_active' => true]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    private function venueBooking(Venue $venue, string $start, string $end, string $status = VenueBooking::STATUS_CONFIRMED): VenueBooking
    {
        return VenueBooking::book(['venue_id' => $venue->id, 'start_at' => Carbon::parse($start), 'end_at' => Carbon::parse($end),
            'formula' => VenueBooking::FORMULA_HALF, 'participants' => 10, 'client_name' => 'M. Test', 'client_phone' => '0700000000',
            'status' => $status, 'quantities' => [], 'discount' => '0'], User::factory()->create());
    }

    public function test_the_reception_sees_each_service_with_todays_figures_and_a_direct_sale_button(): void
    {
        Sale::record(Department::Restauration, [$this->dish->id => 2]); // 10 000
        Sale::record(Department::Restauration, [$this->dish->id => 1]); // 5 000
        Sale::record(Department::PlagePiscine, [$this->entry->id => 3]); // 6 000, 3 entries
        $this->travelTo('2026-11-24 12:00:00');
        Sale::record(Department::Restauration, [$this->dish->id => 4]); // yesterday: left out
        $this->travelTo('2026-11-25 12:00:00');
        Sale::record(Department::Restauration, [$this->dish->id => 1])->cancel(User::factory()->create(), 'Erreur'); // cancelled: left out

        $hall = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);
        $this->venueBooking($hall, '2026-11-25 14:00', '2026-11-25 19:00'); // today
        $this->venueBooking($hall, '2026-11-28 08:00', '2026-11-28 13:00'); // upcoming
        $this->venueBooking($hall, '2026-11-29 08:00', '2026-11-29 13:00')->cancel(User::factory()->create(), 'Annulé'); // cancelled

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee('Autres services')
            ->assertSeeInOrder(['Restauration', '2 ventes', '15 000 F', 'aujourd\'hui', 'Nouvelle vente'])
            ->assertSeeInOrder(['Plage et piscine', '1 vente', '6 000 F', '3 entrées', 'Nouvelle vente'])
            ->assertSeeInOrder(['Réunions et événements', '1 événement', 'aujourd\'hui', '1 réservation à venir', 'Nouvelle réservation'])
            ->assertSee(route('admin.sales.create', 'restauration'), false)
            ->assertSee(route('admin.sales.create', 'plage-piscine'), false)
            ->assertSee(route('admin.events.create'), false);
    }

    public function test_only_the_services_the_hotel_offers_are_shown(): void
    {
        config(['hotel.services' => 'restauration']);

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertSee(route('admin.sales.create', 'restauration'), false)
            ->assertDontSee(route('admin.sales.create', 'plage-piscine'), false)
            ->assertDontSee(route('admin.events.create'), false);
    }

    public function test_the_block_is_hidden_when_no_service_is_offered(): void
    {
        config(['hotel.services' => '']);

        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('services', null)
            ->assertDontSee('Autres services');
    }

    public function test_the_manager_does_not_get_the_block(): void
    {
        $this->actingAs(User::factory()->simpleUi()->create())->get('/admin')->assertOk()
            ->assertViewHas('services', null)
            ->assertDontSee('Autres services');
    }
}
