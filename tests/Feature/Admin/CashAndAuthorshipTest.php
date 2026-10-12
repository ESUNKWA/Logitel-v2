<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAndAuthorshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
    }

    private function room(): Room
    {
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);

        return Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(Room $room, User $user, array $payments)
    {
        return $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0101',
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => $payments,
        ]);
    }

    public function test_cash_received_and_change_are_stored_and_the_change_is_computed_server_side(): void
    {
        $room = $this->room();

        $this->assign($room, User::factory()->create(), [
            ['method' => 'especes', 'amount' => 10000, 'received' => 15000, 'change' => 999999],
            ['method' => 'wave', 'amount' => 5000, 'received' => 8000],
        ])->assertSessionHasNoErrors()->assertSessionHas('status', fn ($m) => str_contains($m, 'Monnaie à rendre : 5 000 FCFA'));

        $cash = Payment::where('method', 'especes')->firstOrFail();
        $this->assertSame('15000.00', $cash->amount_received);
        $this->assertSame('5000.00', $cash->change_given);

        $wave = Payment::where('method', 'wave')->firstOrFail();
        $this->assertNull($wave->amount_received, 'No cash handed over for mobile money.');
        $this->assertNull($wave->change_given);
    }

    public function test_the_cash_received_must_be_entered_at_the_desk(): void
    {
        $room = $this->room();

        // Affectation: "Reçu du client" is mandatory on a cash line.
        $this->assign($room, User::factory()->create(), [['method' => 'especes', 'amount' => 15000, 'received' => '']])
            ->assertSessionHasErrors(['payments.0.received' => 'Saisissez le montant reçu du client en espèces, pour calculer la monnaie à rendre.']);
        $this->assertSame(0, Reservation::count());

        // Réservation deposit: same rule.
        $this->actingAs(User::factory()->create())->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $room->id, 'pricing_mode' => 'heure',
            'check_in' => '2026-10-09T10:00', 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            'payments' => [['method' => 'especes', 'amount' => 5000]],
        ])->assertSessionHasErrors('payments.0.received');
        $this->assertSame(0, Reservation::count());
    }

    public function test_on_the_invoice_page_a_blank_received_means_the_exact_amount(): void
    {
        $invoice = Reservation::factory()->create(['pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-09 10:00', 'duration' => 2, 'unit_rate' => 5000])->invoice;

        $this->actingAs(User::factory()->create())->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => [['method' => 'especes', 'amount' => 5000]], 'paid_at' => '2026-10-08 10:00',
        ])->assertSessionHasNoErrors();

        $cash = Payment::firstOrFail();
        $this->assertSame('5000.00', $cash->amount_received);
        $this->assertSame('0.00', $cash->change_given);
    }

    public function test_received_below_the_cash_amount_is_refused(): void
    {
        $room = $this->room();

        $this->assign($room, User::factory()->create(), [['method' => 'especes', 'amount' => 10000, 'received' => 9000]])
            ->assertSessionHasErrors('payments.0.received');
        $this->assertSame(0, Reservation::count());

        $invoice = Reservation::factory()->create(['pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-09 10:00', 'duration' => 2, 'unit_rate' => 5000])->invoice;
        $this->actingAs(User::factory()->create())->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => [['method' => 'especes', 'amount' => 5000, 'received' => 2000]], 'paid_at' => '2026-10-08 10:00',
        ])->assertSessionHasErrors('payments.0.received');
        $this->assertSame(0, Payment::count());
    }

    public function test_the_change_shows_in_the_invoice_history(): void
    {
        $invoice = Reservation::factory()->create(['pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-09 10:00', 'duration' => 2, 'unit_rate' => 5000])->invoice;
        $cashier = User::factory()->create(['name' => 'Fatou Caisse']);

        $this->actingAs($cashier)->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => [['method' => 'especes', 'amount' => 7500, 'received' => 10000]], 'paid_at' => '2026-10-08 10:00',
        ])->assertSessionHas('status', fn ($m) => str_contains($m, 'Monnaie à rendre : 2 500 FCFA'));

        $this->actingAs($cashier)->get("/admin/invoices/{$invoice->id}")->assertOk()
            ->assertSee('reçu 10 000, monnaie rendue 2 500')
            ->assertSee('par Fatou Caisse');
    }

    public function test_the_author_of_each_record_is_stored(): void
    {
        $receptionist = User::factory()->reception()->create(['name' => 'Awa Réception']);
        $room = $this->room();

        $this->assign($room, $receptionist, [['method' => 'especes', 'amount' => 15000, 'received' => 20000]])->assertSessionHasNoErrors();

        $stay = $room->reservations()->firstOrFail();
        $this->assertSame($receptionist->id, $stay->created_by);
        $this->assertSame($receptionist->id, $stay->checked_in_by);
        $this->assertSame('2026-10-08 10:00', $stay->checked_in_at->format('Y-m-d H:i'));
        $this->assertSame($receptionist->id, $stay->guest->created_by);
        $this->assertSame($receptionist->id, Payment::firstOrFail()->created_by);

        $manager = User::factory()->create(['name' => 'Gérant Kouassi']);
        $this->travelTo('2026-10-08 13:05:00');
        $this->actingAs($manager)->post("/admin/affectations/{$stay->id}/check-out");

        $stay->refresh();
        $this->assertSame($manager->id, $stay->checked_out_by);
        $this->assertSame('2026-10-08 13:05', $stay->checked_out_at->format('Y-m-d H:i'));

        $this->actingAs($manager)->get("/admin/affectations/{$stay->id}")->assertOk()
            ->assertSeeInOrder(['Affectation enregistrée', 'Awa Réception', 'Check-in', 'Awa Réception', 'Sortie', 'Gérant Kouassi'], false);
        $this->actingAs($manager)->get('/admin/affectations?onglet=terminees')->assertSee('par Awa Réception');
    }

    public function test_reservation_author_arrival_and_cancellation_are_stored(): void
    {
        $booker = User::factory()->create(['name' => 'Standard']);
        $room = $this->room();

        $this->actingAs($booker)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $room->id, 'pricing_mode' => 'heure',
            'check_in' => '2026-10-09T10:00', 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
        ])->assertSessionHasNoErrors();
        $booking = Reservation::firstOrFail();
        $this->assertSame($booker->id, $booking->created_by);
        $this->actingAs($booker)->get('/admin/reservations')->assertSee('par Standard');

        $canceller = User::factory()->create();
        $this->actingAs($canceller)->post("/admin/reservations/{$booking->id}/cancel");
        $booking->refresh();
        $this->assertSame(ReservationStatus::Annulee, $booking->status);
        $this->assertSame($canceller->id, $booking->cancelled_by);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_authorship_cannot_be_forged_from_input(): void
    {
        $real = User::factory()->create();
        $other = User::factory()->create();
        $invoice = Reservation::factory()->create(['pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-09 10:00', 'duration' => 2, 'unit_rate' => 5000])->invoice;

        $this->actingAs($real)->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => [['method' => 'wave', 'amount' => 1000, 'created_by' => $other->id]],
            'paid_at' => '2026-10-08 10:00', 'created_by' => $other->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($real->id, Payment::firstOrFail()->created_by);
        $this->assertSame(PaymentMethod::Wave, Payment::firstOrFail()->method);
    }
}
