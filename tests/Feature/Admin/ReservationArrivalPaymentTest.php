<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deposit at booking, rest at arrival: « Affecter » / « Arrivée du client » opens a page showing
 * the total, the deposit and what is left, with an optional encaissement (all, part or nothing)
 * posted together with the check-in, in one transaction.
 */
class ReservationArrivalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 10:00:00');
        $this->staff = User::factory()->create();
    }

    /** 2 h × 5 000 = 10 000 FCFA today at 15:00, with an optional deposit. */
    private function booking(int $deposit = 0, RoomStatus $roomStatus = RoomStatus::Disponible, string $checkIn = '2026-10-14 15:00', string $number = 'AR-1'): Reservation
    {
        $room = Room::factory()->create(['number' => $number, 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000, 'duration' => 2,
            'check_in' => $checkIn, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        if ($deposit > 0) {
            $booking->invoice->settle([['method' => 'wave', 'amount' => $deposit]], now(), 'Acompte à la réservation');
        }
        $room->refresh()->update(['status' => $roomStatus]);

        return $booking->refresh();
    }

    private function cashierWithoutPayments(): User
    {
        $role = Role::create(['name' => 'Accueil sans caisse']);
        $role->syncPermissions(['reservations.view', 'reservations.manage', 'affectations.view']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    private function checkIn(Reservation $booking, array $payments = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->post(route('admin.reservations.check-in', $booking), $payments === [] ? [] : ['payments' => $payments]);
    }

    public function test_the_arrival_page_shows_total_deposit_and_rest_prefilled(): void
    {
        $booking = $this->booking(deposit: 4000);

        $this->actingAs($this->staff)->get(route('admin.reservations.arrival', $booking))->assertOk()
            ->assertSeeInOrder(['Montant total', '10 000 FCFA', 'Acompte déjà versé', '4 000 FCFA', 'Reste à payer', '6 000 FCFA'])
            ->assertSee('action="'.route('admin.reservations.check-in', $booking).'"', false)
            ->assertSee('data-payment-lines', false)
            ->assertSee('data-due="6000"', false)
            ->assertSee('value="6000"', false)
            ->assertSee('data-prefill="1"', false)
            ->assertSee('data-required="0"', false);
    }

    public function test_the_rest_paid_in_full_at_arrival(): void
    {
        $booking = $this->booking(deposit: 4000);

        $this->checkIn($booking, [['method' => 'especes', 'amount' => 6000, 'received' => 10000]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.affectations.show', $booking))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'check-in effectué') && str_contains($status, '(soldé)')
                && str_contains($status, 'Monnaie à rendre : 4 000 FCFA'));

        $booking->refresh();
        $this->assertSame(ReservationStatus::Enregistree, $booking->status);
        $this->assertSame(RoomStatus::Occupee, $booking->room->fresh()->status);
        $this->assertEqualsWithDelta(10000, (float) $booking->invoice->amountPaid(), 0.001);
        $this->assertSame('0.00', $booking->invoice->remainingAmount());
        $this->assertSame(InvoiceStatus::Payee, $booking->invoice->fresh()->status);
        $this->assertDatabaseHas('payments', ['invoice_id' => $booking->invoice->id, 'amount' => 6000, 'method' => 'especes', 'notes' => "Encaissement à l'arrivée du client"]);
    }

    public function test_part_of_the_rest_or_nothing_the_rest_stays_due(): void
    {
        $partial = $this->booking(deposit: 4000);
        $this->checkIn($partial, [['method' => 'wave', 'amount' => 2000]])->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'reste dû 4 000 FCFA'));
        $this->assertSame(ReservationStatus::Enregistree, $partial->refresh()->status);
        $this->assertSame('4000.00', $partial->invoice->remainingAmount());

        // Nothing cashed at arrival (rest not mandatory): checked in, the rest stays due.
        $none = $this->booking(number: 'AR-2');
        $this->checkIn($none)->assertSessionHasNoErrors()->assertRedirect(route('admin.affectations.show', $none))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Aucun encaissement : reste dû 10 000 FCFA'));
        $this->assertSame(ReservationStatus::Enregistree, $none->refresh()->status);
        $this->assertSame(0, $none->invoice->payments()->count());
    }

    public function test_more_than_the_rest_is_refused_and_nothing_is_checked_in(): void
    {
        $booking = $this->booking(deposit: 4000);

        $this->checkIn($booking, [['method' => 'wave', 'amount' => 7000]])->assertSessionHasErrors('payments');

        $this->assertSame(ReservationStatus::Confirmee, $booking->refresh()->status);
        $this->assertSame(RoomStatus::Disponible, $booking->room->fresh()->status);
        $this->assertSame(1, $booking->invoice->payments()->count()); // the deposit only
    }

    public function test_a_payment_refused_under_the_lock_checks_nothing_in(): void
    {
        $booking = $this->booking(deposit: 4000);

        // Someone cashes the rest between the validation and the write: Invoice::settle() refuses
        // under its row lock, and the check-in in the same transaction is undone with it.
        $once = false;
        Reservation::updated(function (Reservation $r) use ($booking, &$once) {
            if (! $once && $r->is($booking) && $r->status === ReservationStatus::Enregistree) {
                $once = true;
                $r->invoice->settle([['method' => 'orange_money', 'amount' => 6000]], now());
            }
        });

        $this->checkIn($booking, [['method' => 'wave', 'amount' => 6000]])->assertSessionHasErrors('payments');

        $this->assertSame(ReservationStatus::Confirmee, $booking->refresh()->status);
        $this->assertSame(RoomStatus::Disponible, $booking->room->fresh()->status);
        $this->assertSame(1, $booking->invoice->payments()->count()); // the deposit only
    }

    public function test_cash_needs_the_amount_received(): void
    {
        $booking = $this->booking();

        $this->checkIn($booking, [['method' => 'especes', 'amount' => 10000]])->assertSessionHasErrors('payments.0.received');

        $this->assertSame(ReservationStatus::Confirmee, $booking->refresh()->status);
        $this->assertSame(0, $booking->invoice->payments()->count());
    }

    public function test_cashing_needs_the_payment_permission(): void
    {
        $booking = $this->booking(deposit: 4000);
        $user = $this->cashierWithoutPayments();

        $this->checkIn($booking, [['method' => 'wave', 'amount' => 6000]], $user)->assertForbidden();
        $this->assertSame(ReservationStatus::Confirmee, $booking->refresh()->status);

        // Without the permission: the plain check-in from the list, no encaissement on the page.
        $this->actingAs($user)->get(route('admin.reservations.index'))->assertOk()
            ->assertSee('action="'.route('admin.reservations.check-in', $booking).'"', false)
            ->assertDontSee(route('admin.reservations.arrival', $booking), false);
        $this->actingAs($user)->get(route('admin.reservations.arrival', $booking))->assertOk()
            ->assertDontSee('data-payment-lines', false)
            ->assertSee('Le reste à payer pourra être encaissé depuis la facture.');

        $this->checkIn($booking, as: $user)->assertSessionHasNoErrors()->assertRedirect(route('admin.affectations.show', $booking));
        $this->assertSame(ReservationStatus::Enregistree, $booking->refresh()->status);
    }

    public function test_a_booking_already_paid_checks_in_straight_from_the_list(): void
    {
        $booking = $this->booking(deposit: 10000);
        $arrivalLink = 'href="'.route('admin.reservations.arrival', $booking).'"';

        $this->actingAs($this->staff)->get(route('admin.reservations.index'))->assertOk()
            ->assertSee('action="'.route('admin.reservations.check-in', $booking).'"', false)
            ->assertDontSee($arrivalLink, false);
        $this->get(route('admin.reservations.edit', $booking))->assertOk()->assertDontSee($arrivalLink, false);
        $this->get('/admin/arrivees')->assertOk()->assertDontSee($arrivalLink, false);

        $this->get(route('admin.reservations.arrival', $booking))->assertOk()->assertSee('Soldée')->assertDontSee('data-payment-lines', false);

        // Nothing left: any payment is refused.
        $this->checkIn($booking, [['method' => 'wave', 'amount' => 1000]])->assertSessionHasErrors('payments');
        $this->assertSame(ReservationStatus::Confirmee, $booking->refresh()->status);
    }

    public function test_unpaid_booking_links_to_the_arrival_page_everywhere(): void
    {
        $booking = $this->booking(deposit: 4000);
        $arrivalLink = 'href="'.route('admin.reservations.arrival', $booking).'"';

        $this->actingAs($this->staff)->get(route('admin.reservations.index'))->assertOk()->assertSee($arrivalLink, false);
        $this->get(route('admin.reservations.edit', $booking))->assertOk()->assertSee($arrivalLink, false);
        $this->get('/admin/arrivees')->assertOk()->assertSee($arrivalLink, false);
    }

    public function test_the_arrival_page_refuses_what_check_in_refuses(): void
    {
        $busy = $this->booking(roomStatus: RoomStatus::Nettoyage);
        $this->actingAs($this->staff)->from(route('admin.reservations.index'))->get(route('admin.reservations.arrival', $busy))
            ->assertRedirect(route('admin.reservations.index'))->assertSessionHasErrors('room_id');

        $busy->update(['status' => ReservationStatus::Annulee]);
        $this->get(route('admin.reservations.arrival', $busy))->assertNotFound();
        $this->checkIn($busy, [['method' => 'wave', 'amount' => 1000]])->assertNotFound();
        $this->assertSame(0, $busy->invoice->payments()->count());
    }

    public function test_the_arrival_page_needs_reservations_manage(): void
    {
        $booking = $this->booking();
        $role = Role::create(['name' => 'Lecture']);
        $role->syncPermissions(['reservations.view']);
        $viewer = User::factory()->create();
        $viewer->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($viewer)->get(route('admin.reservations.arrival', $booking))->assertForbidden();
    }
}
