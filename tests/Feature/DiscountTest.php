<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discounts ("remises", amount or percentage) on affectations and réservations, with the rate
 * locked to the room type's: totals, invoices, payments, extensions, receipts, dashboard.
 */
class DiscountTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 5000, 'night_rate' => 25000]);
        $this->room = Room::factory()->create(['number' => 'RM-1', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->staff = User::factory()->create(['name' => 'Aminata Koffi']);
    }

    private function assign(array $data)
    {
        return $this->actingAs($this->staff)->post("/admin/rooms/{$this->room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 3, 'adults' => 1, 'children' => 0, ...$data,
        ]);
    }

    public function test_a_percentage_discount_lowers_the_total_and_what_must_be_paid(): void
    {
        $this->assign(['discount_type' => 'pourcentage', 'discount_value' => 10, 'discount_reason' => 'Client fidèle',
            'payments' => [['method' => 'wave', 'amount' => 13500]]])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('5000.00', (string) $stay->unit_rate);
        $this->assertSame('15000.00', $stay->subtotal());
        $this->assertSame('1500.00', (string) $stay->discount_amount);
        $this->assertSame('13500.00', (string) $stay->total_amount);
        $this->assertSame('13500.00', (string) $stay->invoice->amount);
        $this->assertSame(InvoiceStatus::Payee, $stay->invoice->status);
        $this->assertSame($this->staff->id, $stay->discount_by);
        $this->assertNotNull($stay->discount_at);
        $this->assertSame('10 % — Client fidèle', $stay->discountLabel());
    }

    public function test_an_amount_discount_and_its_limits(): void
    {
        // Paying the full price is now an overpayment; the discounted total is due.
        $this->assign(['discount_type' => 'montant', 'discount_value' => '2 000', 'payments' => [['method' => 'wave', 'amount' => 15000]]])->assertSessionHasErrors('payments');
        $this->assign(['discount_type' => 'montant', 'discount_value' => 20000, 'payments' => [['method' => 'wave', 'amount' => 1]]])->assertSessionHasErrors('discount_value');
        $this->assign(['discount_type' => 'pourcentage', 'discount_value' => 120])->assertSessionHasErrors('discount_value');
        $this->assign(['discount_type' => 'montant', 'discount_value' => -5])->assertSessionHasErrors('discount_value');
        $this->assign(['discount_type' => 'cadeau', 'discount_value' => 5])->assertSessionHasErrors('discount_type');
        $this->assertSame(0, Reservation::count());

        $this->assign(['discount_type' => 'montant', 'discount_value' => '2 000', 'payments' => [['method' => 'wave', 'amount' => 13000]]])->assertSessionHasNoErrors();
        $this->assertSame('13000.00', (string) Reservation::sole()->total_amount);
    }

    public function test_no_discount_by_default_and_the_submitted_rate_is_ignored(): void
    {
        $this->assign(['unit_rate' => 1, 'discount_value' => '', 'payments' => [['method' => 'wave', 'amount' => 15000]]])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('5000.00', (string) $stay->unit_rate);
        $this->assertFalse($stay->hasDiscount());
        $this->assertNull($stay->discount_type);
        $this->assertNull($stay->discount_by);
    }

    public function test_extending_recomputes_a_percentage_but_keeps_an_amount(): void
    {
        $this->assign(['discount_type' => 'pourcentage', 'discount_value' => 10, 'payments' => [['method' => 'wave', 'amount' => 13500]]]);
        $stay = Reservation::sole();

        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", [
            'duration' => 5, 'adults' => 1, 'children' => 0, 'discount_type' => 'pourcentage', 'discount_value' => 10,
        ])->assertSessionHasNoErrors();
        $stay->refresh();
        $this->assertSame('2500.00', (string) $stay->discount_amount);    // 10 % of 25 000
        $this->assertSame('22500.00', (string) $stay->invoice->amount);   // invoice follows
        $this->assertSame(InvoiceStatus::PartiellementPayee, $stay->invoice->status);

        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", [
            'duration' => 5, 'adults' => 1, 'children' => 0, 'discount_type' => 'montant', 'discount_value' => 3000,
        ])->assertSessionHasNoErrors();
        $this->assertSame('22000.00', (string) $stay->fresh()->total_amount);
    }

    public function test_a_discount_can_be_added_later_and_settles_the_invoice(): void
    {
        $this->assign(['payments' => [['method' => 'wave', 'amount' => 15000]]]);
        $stay = Reservation::sole();
        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", ['duration' => 4, 'adults' => 1, 'children' => 0]);
        $this->assertSame(InvoiceStatus::PartiellementPayee, $stay->fresh()->invoice->status); // 20 000 due, 15 000 paid

        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", [
            'duration' => 4, 'adults' => 1, 'children' => 0, 'discount_type' => 'montant', 'discount_value' => 5000, 'discount_reason' => 'Geste commercial',
        ])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Payee, $stay->fresh()->invoice->status);

        // …but never below what was already cashed.
        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", [
            'duration' => 4, 'adults' => 1, 'children' => 0, 'discount_type' => 'montant', 'discount_value' => 9000,
        ])->assertSessionHasErrors('duration');

        // Clearing it.
        $this->actingAs($this->staff)->put("/admin/affectations/{$stay->id}", ['duration' => 3, 'adults' => 1, 'children' => 0, 'discount_value' => ''])->assertSessionHasNoErrors();
        $this->assertFalse($stay->fresh()->hasDiscount());
        $this->assertSame('15000.00', (string) $stay->fresh()->total_amount);
    }

    public function test_reservations_take_a_discount_and_are_guaranteed_on_the_discounted_total(): void
    {
        $this->actingAs($this->staff)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $this->room->id, 'pricing_mode' => 'nuitee',
            'check_in' => '2026-10-10', 'duration' => 2, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            'unit_rate' => 1, // ignored
            'discount_type' => 'pourcentage', 'discount_value' => 20,
            'payments' => [['method' => 'orange_money', 'amount' => 40000]],
        ])->assertSessionHasNoErrors();

        $booking = Reservation::sole();
        $this->assertSame('25000.00', (string) $booking->unit_rate);
        $this->assertSame('40000.00', (string) $booking->total_amount); // 50 000 − 20 %
        $this->assertTrue($booking->is_guaranteed);                       // paid in full (discounted)

        // A deposit above the discounted total is refused.
        $this->actingAs($this->staff)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => Room::factory()->create(['room_type_id' => $this->room->room_type_id])->id,
            'pricing_mode' => 'nuitee', 'check_in' => '2026-10-10', 'duration' => 1, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            'discount_type' => 'montant', 'discount_value' => 5000,
            'payments' => [['method' => 'wave', 'amount' => 25000]],
        ])->assertSessionHasErrors('payments');
    }

    public function test_editing_a_booking_keeps_its_agreed_rate_unless_room_or_mode_change(): void
    {
        $booking = Reservation::factory()->create(['room_id' => $this->room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 25000,
            'check_in' => '2026-10-12', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->room->roomType->update(['night_rate' => 30000]); // the price list changes afterwards
        $payload = ['guest_id' => $booking->guest_id, 'room_id' => $this->room->id, 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-12',
            'duration' => 2, 'adults' => 1, 'children' => 0, 'status' => 'confirmee'];

        $this->actingAs($this->staff)->put("/admin/reservations/{$booking->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame('25000.00', (string) $booking->fresh()->unit_rate); // agreed price kept
        $this->assertSame('50000.00', (string) $booking->fresh()->total_amount);

        $other = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['night_rate' => 60000])->id]);
        $this->actingAs($this->staff)->put("/admin/reservations/{$booking->id}", [...$payload, 'room_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame('60000.00', (string) $booking->fresh()->unit_rate); // new room: its type's rate
    }

    public function test_the_discount_is_shown_on_the_file_invoice_receipt_and_lists(): void
    {
        $this->assign(['discount_type' => 'pourcentage', 'discount_value' => 10, 'discount_reason' => 'Client fidèle',
            'payments' => [['method' => 'wave', 'amount' => 13500]]]);
        $stay = Reservation::sole();

        $this->actingAs($this->staff)->get("/admin/affectations/{$stay->id}")->assertOk()
            ->assertSee('Remise')->assertSee('− 1 500 FCFA')->assertSee('10 % — Client fidèle')->assertSee('par Aminata Koffi')
            ->assertSee('name="discount_value"', false)->assertSee('value="10"', false);
        $this->actingAs($this->staff)->get("/admin/invoices/{$stay->invoice->id}")->assertOk()
            ->assertSee('Sous-total')->assertSee('15 000 FCFA')->assertSee('− 1 500 FCFA');
        $this->actingAs($this->staff)->get("/admin/recus/{$stay->id}")->assertOk()
            ->assertSee('Sous-total')->assertSee('Remise (10 %)')->assertSee('− 1 500')->assertSee('Client fidèle')->assertSee('13 500 FCFA');
        $this->actingAs($this->staff)->get('/admin/affectations')->assertOk()->assertSee('remise − 1 500');
    }

    public function test_the_forms_show_a_locked_rate_and_the_discount_fields(): void
    {
        $this->actingAs($this->staff)->get('/admin/rooms/board')->assertOk()
            ->assertSee('data-unit-rate-input readonly', false)->assertDontSee('name="unit_rate"', false)->assertSee('name="discount_value"', false);
        $this->actingAs($this->staff)->get('/admin/reservations/create')->assertOk()
            ->assertDontSee('name="unit_rate"', false)->assertSee('name="discount_type"', false);
    }

    public function test_the_dashboard_shows_the_discounts_of_the_month(): void
    {
        $this->assign(['discount_type' => 'montant', 'discount_value' => 2000, 'payments' => [['method' => 'wave', 'amount' => 13000]]]);

        $this->actingAs($this->staff)->get('/admin')->assertOk()
            ->assertViewHas('monthlyRevenue', fn ($v) => (float) $v === 13000.0)
            ->assertViewHas('monthlyDiscounts', 2000.0)
            ->assertSee('de remises');
    }

    public function test_a_guest_request_can_be_validated_with_a_discount(): void
    {
        $this->post('/demande/chambre/'.$this->room->id, ['pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0]);
        $request = StayRequest::sole();

        $this->actingAs($this->staff)->post("/admin/demandes/{$request->id}/valider", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'discount_type' => 'montant', 'discount_value' => 1000,
            'payments' => [['method' => 'wave', 'amount' => 9000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('9000.00', (string) Reservation::sole()->total_amount);
    }
}
