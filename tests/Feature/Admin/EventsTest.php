<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Enums\InvoiceStatus;
use App\Models\CatalogItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBooking;
use App\Support\DepartmentRevenue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Meetings & events (client request, 2026-11-25, step 2): venues, room bookings (quote /
 * confirmed), catalogue services, deposit and balance through the booking's sale, weekly
 * planning, quote / confirmation document. OWASP: prices from the venue and the catalogue only,
 * capacity and overlaps checked server-side under a lock, access per permission, cancelled
 * bookings never cashed, typed text escaped.
 */
class EventsTest extends TestCase
{
    use RefreshDatabase;

    private User $reception;

    private User $manager;

    private Venue $hall;

    private Venue $small;

    private CatalogItem $coffee;

    private CatalogItem $lunch;

    protected function setUp(): void
    {
        parent::setUp();
        // The hotel offers these services (Configuration → Hôtel; none by default).
        config(['hotel.services' => 'restauration,plage_piscine,evenements']);
        $this->travelTo('2026-11-25 10:00:00');
        $this->reception = User::factory()->reception()->create(['name' => 'Awa Réception']);
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->hall = Venue::create(['name' => 'Salle Baobab', 'capacity' => 50, 'half_day_rate' => 100000, 'day_rate' => 180000]);
        $this->small = Venue::create(['name' => 'Salle Kapok', 'capacity' => 10, 'half_day_rate' => 30000, 'day_rate' => 50000]);
        $this->coffee = $this->item('Pause-café', 2000, 'Restauration');
        $this->lunch = $this->item('Déjeuner', 7000, 'Restauration');
    }

    private function item(string $name, int $price, ?string $category = null): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price, 'category' => $category]);
        $item->forceFill(['department' => Department::Evenements])->save();

        return $item;
    }

    private function payload(array $extra = []): array
    {
        return [
            'venue_id' => $this->hall->id, 'event_date' => '2026-12-02', 'start_time' => '08:00', 'end_time' => '13:00', 'formula' => 'demi_journee',
            'participants' => 30, 'client_name' => 'Kouamé Yao', 'client_phone' => '0707070707', 'organization' => 'ONG Espoir', 'title' => 'Séminaire',
            'status' => 'option', 'items' => [$this->coffee->id => 30],
            ...$extra,
        ];
    }

    private function book(array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reception)->post(route('admin.events.store'), $this->payload($extra));
    }

    // ---- Booking ---------------------------------------------------------------------------------

    public function test_a_booking_with_services_and_a_deposit(): void
    {
        $this->book(['items' => [$this->coffee->id => 30, $this->lunch->id => 30], 'discount_amount' => '10 000', 'discount_reason' => 'Client fidèle',
            'payments' => [['method' => 'wave', 'amount' => 100000]]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Salle « Salle Baobab » réservée le 02/12/2026 de 08h00 à 13h00 — 360 000 FCFA, acompte encaissé : 100 000 FCFA.');

        $booking = VenueBooking::sole();
        $this->assertSame([VenueBooking::STATUS_OPTION, 30, '100000.00', 'Kouamé Yao', 'ONG Espoir'],
            [$booking->status, $booking->participants, (string) $booking->venue_rate, $booking->client_name, $booking->organization]);
        $this->assertSame('2026-12-02 08:00', $booking->start_at->format('Y-m-d H:i'));

        $sale = $booking->sale;
        $this->assertSame(Department::Evenements, $sale->department);
        $this->assertSame([['Location salle Salle Baobab (demi-journée)', 1, '100000.00'], ['Pause-café', 30, '60000.00'], ['Déjeuner', 30, '210000.00']],
            $sale->lines->map(fn ($l) => [$l->label, $l->quantity, (string) $l->line_total])->all());
        $this->assertSame(['370000.00', '10000.00', '360000.00'], [(string) $sale->subtotal, (string) $sale->discount_amount, (string) $sale->total_amount]);

        $invoice = $sale->invoice;
        $this->assertSame(Department::Evenements, $invoice->department);
        $this->assertSame(InvoiceStatus::PartiellementPayee, $invoice->status);
        $this->assertSame(Department::Evenements, Payment::sole()->department);
        $this->assertSame($this->reception->id, Payment::sole()->attributed_to);
    }

    public function test_prices_come_from_the_venue_and_the_catalogue(): void
    {
        $this->book(['formula' => 'journee', 'venue_rate' => 1, 'total_amount' => 1, 'items' => []])->assertSessionHasNoErrors();

        $sale = VenueBooking::sole()->sale;
        $this->assertSame('180000.00', (string) $sale->total_amount);

        // A later rate change never changes the booking.
        $this->hall->update(['day_rate' => 999999]);
        $this->assertSame('180000.00', (string) $sale->fresh()->total_amount);
    }

    public function test_capacity_venue_dates_and_services_are_checked(): void
    {
        $this->book(['participants' => 51])->assertSessionHasErrors('participants');
        $this->book(['venue_id' => $this->small->id, 'participants' => 11])->assertSessionHasErrors('participants');
        $this->book(['event_date' => '2026-11-20'])->assertSessionHasErrors('event_date');
        $this->book(['event_date' => '2026-11-25', 'start_time' => '09:00', 'end_time' => '12:00'])->assertSessionHasErrors('event_date'); // already started
        $this->book(['end_time' => '07:00'])->assertSessionHasErrors('end_time');
        $this->book(['formula' => 'semaine'])->assertSessionHasErrors('formula');
        $this->book(['status' => 'terminee'])->assertSessionHasErrors('status');
        $this->book(['items' => ['x' => 1]])->assertSessionHasErrors('items');
        $restaurantItem = new CatalogItem(['name' => 'Poulet', 'price' => 5000]);
        $restaurantItem->forceFill(['department' => Department::Restauration])->save();
        $this->book(['items' => [$restaurantItem->id => 1]])->assertSessionHasErrors('items');
        $this->book(['discount_amount' => '200 000'])->assertSessionHasErrors('discount_amount');
        $this->book(['payments' => [['method' => 'wave', 'amount' => 200000]]])->assertSessionHasErrors('payments');
        $this->book(['payments' => [['method' => 'especes', 'amount' => 1000]]])->assertSessionHasErrors('payments.0.received');

        $this->hall->update(['is_active' => false]);
        $this->book()->assertSessionHasErrors('venue_id');
        $this->assertSame(0, VenueBooking::count());
    }

    public function test_two_bookings_of_a_venue_never_overlap(): void
    {
        $this->book()->assertSessionHasNoErrors();

        $this->book(['start_time' => '12:00', 'end_time' => '15:00'])
            ->assertSessionHasErrors(['start_time' => 'La salle « Salle Baobab » est déjà réservée de 08h00 à 13h00 ce jour-là (Kouamé Yao).']);
        $this->book(['start_time' => '13:00', 'end_time' => '18:00'])->assertSessionHasNoErrors(); // right after
        $this->book(['venue_id' => $this->small->id, 'participants' => 8])->assertSessionHasNoErrors(); // another room
        $this->assertSame(3, VenueBooking::count());

        // A cancelled booking frees its slot.
        $first = VenueBooking::query()->orderBy('id')->first();
        $this->actingAs($this->manager)->post(route('admin.events.cancel', $first), ['cancel_reason' => 'Report du client'])->assertSessionHasNoErrors();
        $this->book(['start_time' => '09:00', 'end_time' => '12:00'])->assertSessionHasNoErrors();
    }

    public function test_a_deposit_needs_the_payment_permission(): void
    {
        $role = Role::create(['name' => 'Commercial']);
        $role->syncPermissions(['events.manage']);
        $sales = User::factory()->create();
        $sales->forceFill(['role_id' => $role->id])->save();

        $this->book(['payments' => [['method' => 'wave', 'amount' => 1000]]], $sales)->assertForbidden();
        $this->book([], $sales)->assertSessionHasNoErrors();
    }

    // ---- Changing, confirming, cancelling --------------------------------------------------------

    public function test_changing_a_booking_reprices_it_never_below_what_was_cashed(): void
    {
        $this->book(['payments' => [['method' => 'wave', 'amount' => 150000]]]);
        $booking = VenueBooking::sole();
        $edit = fn (array $extra) => $this->actingAs($this->reception)->put(route('admin.events.update', $booking), [...$this->payload($extra)]);

        $this->get(route('admin.events.edit', $booking))->assertOk()->assertSee('Modifier la réservation')->assertSee('value="30"', false);

        // Full day, 40 people with lunch: 180 000 + 40×2 000 + 40×7 000 = 540 000.
        $edit(['formula' => 'journee', 'end_time' => '17:00', 'participants' => 40, 'items' => [$this->coffee->id => 40, $this->lunch->id => 40]])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertSame(['journee', 40, '17:00'], [$booking->formula, $booking->participants, $booking->end_at->format('H:i')]);
        $this->assertSame('540000.00', (string) $booking->sale->fresh()->total_amount);
        $this->assertSame('540000.00', (string) $booking->sale->invoice->fresh()->amount);
        $this->assertSame(3, $booking->sale->lines()->count());

        // Below the deposit: refused, nothing changed.
        $edit(['venue_id' => $this->small->id, 'participants' => 5, 'items' => []])->assertSessionHasErrors('items');
        $this->assertSame($this->hall->id, $booking->fresh()->venue_id);
        $this->assertSame('540000.00', (string) $booking->sale->fresh()->total_amount);

        // No payment through the edit form.
        $edit(['payments' => [['method' => 'wave', 'amount' => 1000]]])->assertSessionHasErrors('payments');
    }

    public function test_confirming_and_the_quote_or_confirmation_document(): void
    {
        $this->book(['client_name' => '<script>alert(1)</script>']);
        $booking = VenueBooking::sole();

        $this->actingAs($this->reception)->get(route('admin.events.document', $booking))->assertOk()
            ->assertSee('DEVIS')->assertSee('Location salle Salle Baobab (demi-journée)')->assertSee('160 000')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->post(route('admin.events.confirm', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame([VenueBooking::STATUS_CONFIRMED, $this->reception->id], [$booking->status, $booking->confirmed_by]);
        $this->get(route('admin.events.document', $booking))->assertSee('CONFIRMATION DE RÉSERVATION');
        $this->get(route('admin.events.show', $booking))->assertOk()->assertSee('Confirmée')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cancelling_is_the_managers_and_never_once_cashed(): void
    {
        $this->book(['payments' => [['method' => 'wave', 'amount' => 50000]]]);
        $booking = VenueBooking::sole();

        $this->actingAs($this->reception)->post(route('admin.events.cancel', $booking), ['cancel_reason' => 'X'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('admin.events.cancel', $booking), ['cancel_reason' => 'Annulé par le client'])->assertSessionHasErrors('cancel_reason');
        $this->assertFalse($booking->fresh()->isCancelled());

        // Deposit refunded (payment deleted) → cancellable; then never cashed nor changed.
        $payment = Payment::sole();
        $this->delete(route('admin.invoices.payments.destroy', [$booking->sale->invoice, $payment]))->assertRedirect();
        $this->post(route('admin.events.cancel', $booking), ['cancel_reason' => 'Annulé par le client'])->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertTrue($booking->isCancelled());
        $this->assertTrue($booking->sale->fresh()->isCancelled());
        $this->post(route('admin.invoices.payments.store', $booking->sale->invoice), ['payments' => [['method' => 'wave', 'amount' => 1000]], 'paid_at' => '2026-11-25 11:00'])
            ->assertSessionHasErrors('payments');
        $this->get(route('admin.events.edit', $booking))->assertRedirect(route('admin.events.show', $booking));
        $this->actingAs($this->reception)->put(route('admin.events.update', $booking), $this->payload())->assertSessionHasErrors('venue_id');
    }

    // ---- Pages, planning, figures ------------------------------------------------------------------

    public function test_list_planning_and_prefill(): void
    {
        $this->book();
        $booking = VenueBooking::sole();

        $this->actingAs($this->reception)->get(route('admin.events.index'))->assertOk()
            ->assertSee('Salle Baobab')->assertSee('Kouamé Yao')->assertSee('Option (devis)')->assertSee('160 000 F');
        $this->get(route('admin.events.planning', ['semaine' => '2026-12-02']))->assertOk()
            ->assertSee('Semaine du 30/11/2026')->assertSee('08h00–13h00')->assertSee(route('admin.events.show', $booking), false)
            ->assertSee(e(route('admin.events.create', ['salle' => $this->small->id, 'date' => '2026-12-02'])), false);
        $this->get(route('admin.events.planning', ['semaine' => 'n\'importe quoi']))->assertOk();
        $this->get(route('admin.events.create', ['salle' => $this->small->id, 'date' => '2026-12-04']))->assertOk()
            ->assertSee('value="2026-12-04"', false)->assertSee('Pause-café');

        $this->travelTo('2026-12-03 10:00:00');
        $this->get(route('admin.events.index'))->assertDontSee('Kouamé Yao');
        $this->get(route('admin.events.index', ['onglet' => 'passees']))->assertSee('Kouamé Yao')->assertSee('Passée');
    }

    public function test_turnover_reaches_recettes_pilotage_and_the_open_balances(): void
    {
        $this->book(['payments' => [['method' => 'wave', 'amount' => 60000]]]);
        $booking = VenueBooking::sole();

        $totals = DepartmentRevenue::totals(Carbon::parse('2026-11-25 06:00'), Carbon::parse('2026-11-26 06:00'));
        $this->assertSame(60000.0, $totals['evenements']['amount']);

        $this->actingAs($this->manager)->get(route('admin.pilotage.index'))->assertOk()
            ->assertSee('pilot-kpi--evenements', false)->assertSee('Réunions et événements')->assertSee('Voir les réservations');
        $this->get(route('admin.revenue.index', ['service' => 'evenements']))->assertOk()->assertSeeInOrder(['Total encaissé', '60 000']);
        $this->get(route('admin.front-desk.collect'))->assertOk()->assertSee('Réunions et événements')->assertSee($booking->sale->invoice->number);
        $this->get(route('admin.invoices.show', $booking->sale->invoice))->assertOk()
            ->assertSee('Voir la réservation de salle')->assertSee(route('admin.events.show', $booking), false)
            ->assertSee(route('admin.events.document', $booking), false);
        $this->get(route('admin.invoices.index', ['type' => 'evenements']))->assertOk()->assertSee($booking->sale->invoice->number);
    }

    // ---- Venues and access -------------------------------------------------------------------------

    public function test_the_manager_manages_the_venues(): void
    {
        $this->actingAs($this->manager)->post(route('admin.venues.store'), ['name' => 'Salle Iroko', 'capacity' => 120, 'half_day_rate' => '150 000', 'day_rate' => '250 000'])
            ->assertRedirect(route('admin.venues.index'));
        $venue = Venue::query()->where('name', 'Salle Iroko')->sole();
        $this->assertSame([120, '150000.00', '250000.00', true], [$venue->capacity, (string) $venue->half_day_rate, (string) $venue->day_rate, $venue->is_active]);

        $this->post(route('admin.venues.store'), ['name' => 'Salle Iroko', 'capacity' => 1, 'half_day_rate' => 1, 'day_rate' => 1])->assertSessionHasErrors('name');
        $this->post(route('admin.venues.store'), ['name' => 'Zéro', 'capacity' => 0, 'half_day_rate' => 1, 'day_rate' => 1])->assertSessionHasErrors('capacity');

        $this->book();
        $this->actingAs($this->manager)->delete(route('admin.venues.destroy', $this->hall))->assertSessionHasErrors('venue');
        $this->delete(route('admin.venues.destroy', $venue))->assertRedirect();
        $this->assertNull($venue->fresh());
        $this->put(route('admin.venues.update', $this->hall), ['name' => 'Salle Baobab', 'capacity' => 60, 'half_day_rate' => 100000, 'day_rate' => 180000, 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($this->hall->fresh()->is_active);
    }

    public function test_access_is_per_permission(): void
    {
        $role = Role::create(['name' => 'Lecture']);
        $role->syncPermissions(['reservations.view']);
        $viewer = User::factory()->create();
        $viewer->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($viewer)->get(route('admin.events.index'))->assertForbidden();
        $this->get(route('admin.events.planning'))->assertForbidden();
        $this->post(route('admin.events.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, VenueBooking::count());

        // The Réception books (granted by the migration) but doesn't manage the venues.
        $this->actingAs($this->reception)->get(route('admin.events.index'))->assertOk();
        $this->get('/admin')->assertSee(route('admin.events.index'), false);
        $this->get(route('admin.venues.index'))->assertForbidden();
        $this->post(route('admin.venues.store'), ['name' => 'X', 'capacity' => 1, 'half_day_rate' => 1, 'day_rate' => 1])->assertForbidden();

        // Event sales have no counter page.
        $this->get('/admin/ventes/evenements')->assertNotFound();
    }
}
