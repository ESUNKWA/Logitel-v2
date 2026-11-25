<?php

namespace Tests\Feature\Admin;

use App\Enums\Department;
use App\Enums\InvoiceStatus;
use App\Enums\RoomStatus;
use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Sale;
use App\Models\User;
use App\Support\FinanceStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Other services (client request, 2026-11-25): restaurant and beach & pool sales from a
 * catalogue, cashed by the Réception or charged to an in-progress stay, with their own invoice
 * and the service stamped on every payment. OWASP: prices and totals from the catalogue only,
 * service from the URL only, forged ids refused, access per permission, cancelled sales never
 * cashed, typed text escaped.
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    private User $reception;

    private User $manager;

    private CatalogItem $dish;

    private CatalogItem $drink;

    private CatalogItem $entry;

    protected function setUp(): void
    {
        parent::setUp();
        // The hotel offers these services (Configuration → Hôtel; none by default).
        config(['hotel.services' => 'restauration,plage_piscine,evenements']);
        $this->travelTo('2026-11-25 12:00:00');
        $this->reception = User::factory()->reception()->create(['name' => 'Awa Réception']);
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->dish = $this->item(Department::Restauration, 'Poulet braisé', 5000, 'Plats');
        $this->drink = $this->item(Department::Restauration, 'Jus de bissap', 1000, 'Boissons');
        $this->entry = $this->item(Department::PlagePiscine, 'Entrée adulte', 2000, null, countsEntry: true);
    }

    private function item(Department $department, string $name, int $price, ?string $category = null, bool $countsEntry = false, bool $active = true): CatalogItem
    {
        $item = new CatalogItem(['name' => $name, 'price' => $price, 'category' => $category, 'counts_entry' => $countsEntry, 'is_active' => $active]);
        $item->forceFill(['department' => $department])->save();

        return $item;
    }

    private function sell(array $data, string $service = 'restauration', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reception)->post(route('admin.sales.store', $service), $data);
    }

    /** A stay in progress (affectation from the board, 2 h paid). */
    private function stay(): Reservation
    {
        $type = RoomType::factory()->create(['hourly_rate' => 3000]);
        $room = Room::factory()->create(['number' => 'R'.fake()->unique()->numberBetween(100, 999), 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs($this->reception)->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0, 'payments' => [['method' => 'wave', 'amount' => 6000]],
        ])->assertSessionHasNoErrors();

        return Reservation::query()->where('room_id', $room->id)->sole();
    }

    // ---- Recording a sale ----------------------------------------------------------------------

    public function test_a_restaurant_sale_cashed_at_once_with_change(): void
    {
        $this->sell(['items' => [$this->dish->id => 2, $this->drink->id => 3], 'label' => 'Table 4',
            'payments' => [['method' => 'especes', 'amount' => 13000, 'received' => 15000]]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Vente enregistrée : 13 000 FCFA (soldée). Monnaie à rendre : 2 000 FCFA.');

        $sale = Sale::sole();
        $this->assertSame(Department::Restauration, $sale->department);
        $this->assertSame(['13000.00', '0.00', 'Table 4'], [(string) $sale->total_amount, (string) $sale->discount_amount, $sale->label]);
        $this->assertSame([['Poulet braisé', 2, '10000.00'], ['Jus de bissap', 3, '3000.00']],
            $sale->lines->map(fn ($l) => [$l->label, $l->quantity, (string) $l->line_total])->all());

        $invoice = $sale->invoice;
        $this->assertSame(Department::Restauration, $invoice->department);
        $this->assertNull($invoice->reservation_id);
        $this->assertSame(InvoiceStatus::Payee, $invoice->status);
        $payment = $invoice->payments()->sole();
        $this->assertSame(Department::Restauration, $payment->department);
        $this->assertSame($this->reception->id, $payment->attributed_to); // the Réception cashes everything
        $this->assertSame('2000.00', (string) $payment->change_given);
    }

    public function test_prices_come_from_the_catalogue_never_from_the_form(): void
    {
        $this->sell(['items' => [$this->dish->id => 1], 'price' => 1, 'total_amount' => 1, 'unit_price' => 1, 'department' => 'hebergement'])
            ->assertSessionHasNoErrors();

        $sale = Sale::sole();
        $this->assertSame('5000.00', (string) $sale->total_amount);
        $this->assertSame(Department::Restauration, $sale->department);

        // A later price change never changes the past sale.
        $this->dish->update(['price' => 9000]);
        $this->assertSame('5000.00', (string) $sale->fresh()->lines->sole()->unit_price);
    }

    public function test_forged_or_foreign_items_are_refused(): void
    {
        $inactive = $this->item(Department::Restauration, 'Ancien plat', 3000, active: false);

        $this->sell(['items' => []])->assertSessionHasErrors('items');
        $this->sell(['items' => [$this->dish->id => 0]])->assertSessionHasErrors('items');
        $this->sell(['items' => [$this->dish->id => -2]])->assertSessionHasErrors('items.'.$this->dish->id);
        $this->sell(['items' => [$this->dish->id => 1000]])->assertSessionHasErrors('items.'.$this->dish->id);
        $this->sell(['items' => ['x' => 1]])->assertSessionHasErrors('items');
        $this->sell(['items' => [999999 => 1]])->assertSessionHasErrors('items');
        $this->sell(['items' => [$inactive->id => 1]])->assertSessionHasErrors('items');
        // A pool item on the restaurant page (the service comes from the URL).
        $this->sell(['items' => [$this->entry->id => 1]])->assertSessionHasErrors('items');
        $this->assertSame(0, Sale::count());

        // Unknown services don't exist.
        $this->actingAs($this->reception)->get('/admin/ventes/hebergement')->assertNotFound();
        $this->post('/admin/ventes/spa', ['items' => [$this->dish->id => 1]])->assertNotFound();
    }

    public function test_discount_never_above_the_subtotal_and_payment_never_above_the_total(): void
    {
        $this->sell(['items' => [$this->dish->id => 1], 'discount_amount' => '6 000'])->assertSessionHasErrors('discount_amount');
        $this->sell(['items' => [$this->dish->id => 1], 'payments' => [['method' => 'wave', 'amount' => 6000]]])->assertSessionHasErrors('payments');
        $this->sell(['items' => [$this->dish->id => 1], 'payments' => [['method' => 'especes', 'amount' => 5000]]])->assertSessionHasErrors('payments.0.received');
        $this->assertSame(0, Sale::count());

        $this->sell(['items' => [$this->dish->id => 2], 'discount_amount' => '1 000', 'discount_reason' => 'Fidélité'])->assertSessionHasNoErrors();
        $sale = Sale::sole();
        $this->assertSame(['10000.00', '1000.00', '9000.00', 'Fidélité'], [(string) $sale->subtotal, (string) $sale->discount_amount, (string) $sale->total_amount, $sale->discount_reason]);
    }

    public function test_a_sale_left_due_appears_in_the_open_balances(): void
    {
        $this->sell(['items' => [$this->drink->id => 2]])->assertSessionHas('status', 'Vente enregistrée : 2 000 FCFA — reste dû 2 000 FCFA.');
        $sale = Sale::sole();

        [$outstanding, $count] = FinanceStats::outstanding();
        $this->assertSame([2000.0, 1], [$outstanding, $count]);
        $this->actingAs($this->reception)->get(route('admin.front-desk.collect'))->assertOk()
            ->assertSee('Restauration')->assertSee($sale->invoice->number);

        // Cashed later from its invoice page, like any invoice.
        $this->post(route('admin.invoices.payments.store', $sale->invoice), ['payments' => [['method' => 'wave', 'amount' => 2000]], 'paid_at' => '2026-11-25 13:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Payee, $sale->invoice->fresh()->status);
        $this->assertSame(Department::Restauration, Payment::sole()->department);
    }

    public function test_cashing_at_once_needs_the_payment_permission(): void
    {
        $role = Role::create(['name' => 'Serveur']);
        $role->syncPermissions(['sales.create']);
        $waiter = User::factory()->create();
        $waiter->forceFill(['role_id' => $role->id])->save();

        $this->sell(['items' => [$this->dish->id => 1], 'payments' => [['method' => 'wave', 'amount' => 5000]]], as: $waiter)->assertForbidden();
        $this->sell(['items' => [$this->dish->id => 1]], as: $waiter)->assertSessionHasNoErrors(); // left due
        $this->assertSame(1, Sale::count());
    }

    // ---- Room charge ---------------------------------------------------------------------------

    public function test_a_sale_charged_to_the_room_is_settled_with_the_stay(): void
    {
        $stay = $this->stay();

        $this->sell(['items' => [$this->dish->id => 1, $this->drink->id => 1], 'reservation_id' => $stay->id])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'reportée sur la chambre N°'.$stay->room->number));

        $sale = Sale::sole();
        $this->assertSame($stay->id, $sale->reservation_id);
        $this->assertSame(0, $sale->invoice->payments()->count());
        $this->assertSame(6000.0, Sale::dueOnStay($stay));

        // The affectation page shows it, the total to settle and the check-out warning include it.
        $this->actingAs($this->reception)->get(route('admin.affectations.show', $stay))->assertOk()
            ->assertSee('Consommations reportées sur la chambre')->assertSee('6 000 F')
            ->assertSee('Total à régler (séjour + consommations)')
            ->assertSee('il reste 6 000 FCFA à encaisser (dont 6 000 FCFA de consommations reportées sur la chambre)');

        // Paid at departure from its invoice; the turnover stays the restaurant's.
        $this->post(route('admin.invoices.payments.store', $sale->invoice), ['payments' => [['method' => 'especes', 'amount' => 6000, 'received' => 6000]], 'paid_at' => '2026-11-25 13:00'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, Sale::dueOnStay($stay));
        $this->assertSame(6000.0, (float) Payment::query()->ofDepartment(Department::Restauration)->sum('amount'));
    }

    public function test_room_charge_rules(): void
    {
        $stay = $this->stay();

        // No payment with a room charge.
        $this->sell(['items' => [$this->dish->id => 1], 'reservation_id' => $stay->id, 'payments' => [['method' => 'wave', 'amount' => 5000]]])
            ->assertSessionHasErrors('payments');

        // Only a stay in progress.
        $booking = Reservation::factory()->create(['origin' => Reservation::ORIGIN_RESERVATION, 'check_in' => '2026-12-01']);
        $this->sell(['items' => [$this->dish->id => 1], 'reservation_id' => $booking->id])->assertSessionHasErrors('reservation_id');
        $this->actingAs($this->reception)->post(route('admin.affectations.check-out', $stay));
        $this->sell(['items' => [$this->dish->id => 1], 'reservation_id' => $stay->id])->assertSessionHasErrors('reservation_id');
        $this->sell(['items' => [$this->dish->id => 1], 'reservation_id' => 999999])->assertSessionHasErrors('reservation_id');
        $this->assertSame(0, Sale::count());
    }

    // ---- Cancelling -----------------------------------------------------------------------------

    public function test_cancelling_a_sale(): void
    {
        $this->sell(['items' => [$this->dish->id => 1]]);
        $sale = Sale::sole();

        $this->actingAs($this->reception)->post(route('admin.sales.cancel', ['restauration', $sale]), ['cancel_reason' => 'Erreur'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('admin.sales.cancel', ['restauration', $sale]), ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->post(route('admin.sales.cancel', ['plage-piscine', $sale]), ['cancel_reason' => 'Erreur'])->assertNotFound(); // other service's URL

        $this->post(route('admin.sales.cancel', ['restauration', $sale]), ['cancel_reason' => 'Saisie en double'])->assertSessionHasNoErrors();
        $sale->refresh();
        $this->assertTrue($sale->isCancelled());
        $this->assertSame([$this->manager->id, 'Saisie en double'], [$sale->cancelled_by, $sale->cancel_reason]);

        // Never cashed afterwards, out of the open balances.
        $this->post(route('admin.invoices.payments.store', $sale->invoice), ['payments' => [['method' => 'wave', 'amount' => 5000]], 'paid_at' => '2026-11-25 13:00'])
            ->assertSessionHasErrors(['payments' => 'Cette vente est annulée : elle ne peut plus être encaissée.']);
        $this->assertSame([0.0, 0], FinanceStats::outstanding());
        $this->assertSame(1, Sale::count()); // never deleted
    }

    public function test_a_cashed_sale_cannot_be_cancelled(): void
    {
        $this->sell(['items' => [$this->dish->id => 1], 'payments' => [['method' => 'wave', 'amount' => 5000]]]);
        $sale = Sale::sole();

        $this->actingAs($this->manager)->post(route('admin.sales.cancel', ['restauration', $sale]), ['cancel_reason' => 'Erreur'])
            ->assertSessionHasErrors('cancel_reason');
        $this->assertFalse($sale->fresh()->isCancelled());
    }

    // ---- Pages ---------------------------------------------------------------------------------

    public function test_pages_list_show_and_print_and_escape_typed_text(): void
    {
        $this->sell(['items' => [$this->entry->id => 3], 'label' => '<script>alert(1)</script>', 'payments' => [['method' => 'wave', 'amount' => 6000]]], 'plage-piscine');
        $sale = Sale::sole();

        $this->actingAs($this->reception)->get(route('admin.sales.index', 'plage-piscine'))->assertOk()
            ->assertSee('Plage et piscine')->assertSee('Entrées (fréquentation)')->assertSee($sale->invoice->number)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.sales.show', ['plage-piscine', $sale]))->assertOk()->assertSee('Entrée adulte')->assertSee('6 000 F')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.sales.show', ['restauration', $sale]))->assertNotFound();
        $this->get(route('admin.sales.receipt', ['plage-piscine', $sale]))->assertOk()->assertSee('PLAGE ET PISCINE')->assertSee('SOLDÉ')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('admin.sales.create', 'plage-piscine'))->assertOk()->assertSee('Entrée adulte')->assertDontSee('Poulet braisé');
        $this->get(route('admin.invoices.show', $sale->invoice))->assertOk()->assertSee('Plage et piscine')->assertSee('3 × Entrée adulte');
        $this->get(route('admin.invoices.index', ['type' => 'plage_piscine']))->assertOk()->assertSee($sale->invoice->number);
        $this->assertSame(3, $sale->entries());
    }

    public function test_access_is_per_permission(): void
    {
        $role = Role::create(['name' => 'Lecture']);
        $role->syncPermissions(['reservations.view']);
        $viewer = User::factory()->create();
        $viewer->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($viewer)->get(route('admin.sales.index', 'restauration'))->assertForbidden();
        $this->get(route('admin.sales.create', 'restauration'))->assertForbidden();
        $this->post(route('admin.sales.store', 'restauration'), ['items' => [$this->dish->id => 1]])->assertForbidden();
        $this->assertSame(0, Sale::count());

        // The Réception sells (granted by the migration) but doesn't manage the catalogue.
        $this->actingAs($this->reception)->get(route('admin.sales.index', 'restauration'))->assertOk();
        $this->get(route('admin.catalog.index'))->assertForbidden();
    }

    public function test_the_menu_shows_a_service_once_it_has_a_catalogue(): void
    {
        $this->actingAs($this->reception)->get('/admin')->assertOk()
            ->assertSee(route('admin.sales.index', 'restauration'), false)->assertSee(route('admin.sales.index', 'plage-piscine'), false);

        CatalogItem::query()->where('department', 'plage_piscine')->update(['is_active' => false]);
        $this->get('/admin')->assertDontSee(route('admin.sales.index', 'plage-piscine'), false);
        // The Gérant always sees them, to set them up.
        $this->actingAs($this->manager)->get('/admin')->assertSee(route('admin.sales.index', 'plage-piscine'), false);
    }

    // ---- No regression on the accommodation -----------------------------------------------------

    public function test_stays_are_untouched_and_kept_apart_from_the_services(): void
    {
        $stay = $this->stay();
        $this->sell(['items' => [$this->dish->id => 1], 'payments' => [['method' => 'wave', 'amount' => 5000]]]);

        $stayPayment = $stay->invoice->payments()->sole();
        $this->assertSame(Department::Hebergement, $stay->invoice->fresh()->department);
        $this->assertSame(Department::Hebergement, $stayPayment->department);

        // « Affectations » in Recettes = accommodation only; the global cash includes everything.
        $this->assertSame([6000.0], Payment::query()->stayPayments()->pluck('amount')->map(fn ($a) => (float) $a)->all());
        [$from, $to] = [Carbon::parse('2026-11-25 06:00'), Carbon::parse('2026-11-26 06:00')];
        $this->assertSame(11000.0, FinanceStats::cashBetween($from, $to));

        $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('Tous les services')->assertSeeInOrder(['Hébergement', '6 000', 'Restauration', '5 000']);
        $this->get(route('admin.revenue.index', ['service' => 'restauration']))->assertOk()
            ->assertSeeInOrder(['Total encaissé', '5 000']);
        $this->get(route('admin.revenue.index', ['service' => 'inconnu']))->assertOk()->assertSeeInOrder(['Total encaissé', '11 000']);
        $csv = $this->get(route('admin.revenue.export'))->streamedContent();
        $this->assertStringContainsString(';Restauration;', $csv);
    }

    public function test_existing_invoices_are_the_accommodations_and_have_one_origin(): void
    {
        $stay = $this->stay();
        $this->assertNotNull($stay->invoice->reservation_id);
        $this->assertNull($stay->invoice->sale_id);
        $this->assertSame(1, Invoice::query()->where('department', 'hebergement')->count());
    }
}
