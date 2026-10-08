<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(int $amount = 25000): Invoice
    {
        return Reservation::factory()->create([
            'pricing_mode' => PricingMode::Journee,
            'check_in' => '2026-10-08 08:00',
            'duration' => 1,
            'unit_rate' => $amount,
        ])->invoice;
    }

    private function settle(Invoice $invoice, array $lines, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => $lines,
            'paid_at' => '2026-10-08 09:00',
        ]);
    }

    public function test_mixed_payment_is_one_settlement_with_several_methods(): void
    {
        $invoice = $this->invoice(25000);

        $this->settle($invoice, [
            ['method' => 'especes', 'amount' => 10000],
            ['method' => 'wave', 'amount' => 15000, 'reference' => 'WV-123'], // no reference field: ignored
        ])->assertRedirect(route('admin.invoices.show', $invoice))->assertSessionHasNoErrors();

        $payments = $invoice->payments()->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame([PaymentMethod::Especes, PaymentMethod::Wave], $payments->pluck('method')->all());
        $this->assertNull($payments[1]->reference);
        $this->assertNotNull($payments[0]->settlement);
        $this->assertSame($payments[0]->settlement, $payments[1]->settlement);
        $this->assertSame(InvoiceStatus::Payee, $invoice->fresh()->status);

        $this->actingAs(User::factory()->create())->get("/admin/invoices/{$invoice->id}")->assertOk()
            ->assertSee('Mixte')->assertSee('Wave · 15 000 FCFA')->assertSee('Facture soldée');
    }

    public function test_partial_orange_money_payment_leaves_the_rest_due(): void
    {
        $invoice = $this->invoice(25000);

        $this->settle($invoice, [['method' => 'orange_money', 'amount' => 5000]])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiellementPayee, $invoice->status);
        $this->assertSame('20000.00', $invoice->remainingAmount());
    }

    public function test_paying_more_than_what_is_due_is_refused(): void
    {
        $invoice = $this->invoice(25000);
        $this->settle($invoice, [['method' => 'especes', 'amount' => 20000]])->assertSessionHasNoErrors();

        $this->settle($invoice, [
            ['method' => 'especes', 'amount' => 3000],
            ['method' => 'wave', 'amount' => 3000],
        ])->assertSessionHasErrors('payments');

        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_the_model_also_refuses_overpayment(): void
    {
        $invoice = $this->invoice(10000);

        $this->expectException(ValidationException::class);
        $invoice->settle([['method' => 'especes', 'amount' => 10001]], now());
    }

    public function test_invalid_lines_are_refused(): void
    {
        $invoice = $this->invoice(25000);

        $this->settle($invoice, [['method' => 'carte', 'amount' => 1000]])->assertSessionHasErrors('payments.0.method');
        $this->settle($invoice, [['method' => 'wave', 'amount' => 1000], ['method' => 'wave', 'amount' => 1000]])
            ->assertSessionHasErrors('payments.0.method');
        $this->settle($invoice, [['method' => 'wave', 'amount' => 0]])->assertSessionHasErrors('payments.0.amount');
        $this->settle($invoice, [])->assertSessionHasErrors('payments');

        $this->assertSame(0, Payment::count());
    }

    public function test_the_same_method_cannot_be_used_twice_in_one_settlement(): void
    {
        $invoice = $this->invoice(25000);

        $this->settle($invoice, [['method' => 'especes', 'amount' => 5000], ['method' => 'especes', 'amount' => 5000]])
            ->assertSessionHasErrors(['payments.0.method' => 'Chaque mode de paiement ne peut apparaître qu\'une fois par encaissement.']);

        $this->assign($this->room(), [['method' => 'wave', 'amount' => 1000], ['method' => 'wave', 'amount' => 1000]])
            ->assertSessionHasErrors('payments.0.method');

        $this->assertSame(0, Payment::count());
        $this->assertSame(1, Reservation::count()); // only the invoice's own stay: the assignment was refused
    }

    public function test_the_model_also_refuses_a_repeated_method(): void
    {
        $invoice = $this->invoice(25000);

        try {
            $invoice->settle([['method' => 'wave', 'amount' => 1000], ['method' => PaymentMethod::Wave, 'amount' => 1000]], now());
            $this->fail('A repeated method must be refused.');
        } catch (ValidationException) {
            $this->assertSame(0, Payment::count());
        }
    }

    public function test_deleting_a_mixed_settlement_removes_all_its_lines(): void
    {
        $invoice = $this->invoice(25000);
        $this->settle($invoice, [['method' => 'especes', 'amount' => 5000]]);
        $this->settle($invoice, [['method' => 'especes', 'amount' => 10000], ['method' => 'orange_money', 'amount' => 10000]]);
        $mixedLine = $invoice->payments()->where('method', 'orange_money')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->delete("/admin/invoices/{$invoice->id}/payments/{$mixedLine->id}")
            ->assertRedirect(route('admin.invoices.show', $invoice));

        $this->assertSame(['5000.00'], $invoice->payments()->pluck('amount')->all());
        $this->assertSame(InvoiceStatus::PartiellementPayee, $invoice->fresh()->status);
    }

    public function test_a_payment_cannot_be_deleted_through_another_invoice(): void
    {
        $invoice = $this->invoice();
        $other = $this->invoice();
        $this->settle($invoice, [['method' => 'especes', 'amount' => 1000]]);
        $payment = $invoice->payments()->firstOrFail();

        $this->actingAs(User::factory()->create())->delete("/admin/invoices/{$other->id}/payments/{$payment->id}")->assertNotFound();
        $this->assertModelExists($payment);
    }

    // --- Encaissement at assignment ----------------------------------------------------

    private function room(): Room
    {
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);

        return Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(Room $room, array $payments, ?User $user = null)
    {
        $this->travelTo('2026-10-08 10:00:00');

        return $this->actingAs($user ?? User::factory()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0102',
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => $payments,
        ]);
    }

    public function test_mixed_payment_can_be_taken_at_assignment(): void
    {
        $room = $this->room();

        $this->assign($room, [
            ['method' => 'especes', 'amount' => 5000, 'received' => 10000],
            ['method' => 'orange_money', 'amount' => 10000],
        ])->assertRedirect(route('admin.rooms.board'))->assertSessionHasNoErrors();

        $invoice = $room->reservations()->firstOrFail()->invoice;
        $this->assertSame(InvoiceStatus::Payee, $invoice->status);
        $this->assertSame(2, $invoice->payments()->distinct()->count('method'));
        $this->assertSame(1, $invoice->payments()->distinct()->count('settlement'));
    }

    public function test_an_affectation_must_be_paid_in_full(): void
    {
        $room = $this->room(); // 3 h × 5 000 = 15 000

        $this->assign($room, [['method' => 'especes', 'amount' => '']])
            ->assertSessionHasErrors(['payments' => 'Une affectation se paie en totalité : 0 FCFA saisis sur 15 000 FCFA à encaisser.']);
        $this->assign($room, [['method' => 'wave', 'amount' => 10000]])->assertSessionHasErrors('payments');

        $this->assertSame(0, Reservation::count());
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);

        $this->assign($room, [['method' => 'wave', 'amount' => 10000], ['method' => 'orange_money', 'amount' => 5000]])->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Payee, $room->reservations()->firstOrFail()->invoice->status);
    }

    public function test_a_free_affectation_needs_no_payment(): void
    {
        $room = $this->room();
        $this->travelTo('2026-10-08 10:00:00');

        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0102',
            'pricing_mode' => 'heure', 'duration' => 1, 'unit_rate' => 0, 'adults' => 1, 'children' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Reservation::count());
    }

    public function test_overpayment_at_assignment_cancels_the_whole_assignment(): void
    {
        $room = $this->room();

        $this->assign($room, [['method' => 'especes', 'amount' => 20000]])->assertSessionHasErrors('payments');

        $this->assertSame(0, Reservation::count());
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
    }

    public function test_taking_money_at_assignment_requires_the_payment_permission(): void
    {
        $role = Role::create(['name' => 'Bagagiste']);
        $role->syncPermissions(['rooms.view', 'reservations.manage', 'guests.view']);
        $room = $this->room();

        $this->assign($room, [['method' => 'especes', 'amount' => 5000]], User::factory()->create(['role_id' => $role->id]))
            ->assertForbidden();

        $this->assertSame(0, Reservation::count());
    }
}
