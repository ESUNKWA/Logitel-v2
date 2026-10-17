<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recettes split into affectations and réservations, option B (client decision, 2026-10-17):
 * a réservation's payment recorded before the guest arrived counts as a réservation's; its
 * balance paid once the guest is in — and every affectation payment — as an affectation's.
 * The moment compared is when the payment was recorded (created_at), not the typed paid_at.
 */
class RevenueKindTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private User $manager;

    private User $awa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
        $this->type = RoomType::factory()->create(['hourly_rate' => 5000]);
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->awa = User::factory()->reception()->create(['name' => 'Awa Réception']);
    }

    private function room(): Room
    {
        return Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
    }

    /** Board affectation, paid in full at check-in. */
    private function affectation(User $by, int $hours = 1): Reservation
    {
        $room = $this->room();
        $this->actingAs($by)->post("/admin/rooms/{$room->id}/assign", ['pricing_mode' => 'heure', 'duration' => $hours, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000 * $hours]]])->assertSessionHasNoErrors();

        return Reservation::query()->where('room_id', $room->id)->sole();
    }

    /** Réservation for 14:00 (3 h = 15 000 F) with a deposit taken when booking. */
    private function bookingWithDeposit(User $by, int $deposit, string $method = 'orange_money'): Reservation
    {
        $room = $this->room();
        $this->actingAs($by)->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $room->id, 'pricing_mode' => 'heure',
            'check_in' => '2026-10-14T14:00', 'duration' => 3, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
            'payments' => [['method' => $method, 'amount' => $deposit] + ($method === 'especes' ? ['received' => $deposit] : [])],
        ])->assertSessionHasNoErrors();

        return Reservation::query()->where('room_id', $room->id)->sole();
    }

    private function arrive(Reservation $booking, User $by): void
    {
        $this->actingAs($by)->post(route('admin.reservations.check-in', $booking))->assertSessionHasNoErrors();
    }

    private function payOnInvoice(Reservation $file, User $by, int $amount, string $paidAt): void
    {
        $this->actingAs($by)->post(route('admin.invoices.payments.store', $file->invoice), [
            'paid_at' => $paidAt, 'payments' => [['method' => 'wave', 'amount' => $amount]],
        ])->assertSessionHasNoErrors();
    }

    private function kinds(): array
    {
        return Payment::query()->with('invoice.reservation')->orderBy('id')->get()
            ->map(fn (Payment $p) => [(int) $p->amount, $p->kind()])->all();
    }

    public function test_a_deposit_stays_a_reservation_payment_and_the_balance_after_arrival_is_an_affectation_s(): void
    {
        $this->affectation($this->awa);                    // 5 000 — affectation
        $booking = $this->bookingWithDeposit($this->awa, 6000); // 6 000 — deposit before arrival

        $this->travelTo('2026-10-14 14:00:00');
        $this->arrive($booking, $this->awa);
        $this->travelTo('2026-10-14 14:00:30');            // same minute as the arrival
        $this->payOnInvoice($booking, $this->awa, 9000, '2026-10-14T14:00'); // balance once the guest is in

        $this->assertSame([[5000, 'affectation'], [6000, 'reservation'], [9000, 'affectation']], $this->kinds());
        // Scopes and the per-payment rule agree.
        $this->assertSame([2], Payment::query()->bookingPayments()->pluck('id')->all());
        $this->assertSame([1, 3], Payment::query()->stayPayments()->orderBy('id')->pluck('id')->all());
    }

    public function test_a_back_dated_entry_doesn_t_move_money_to_the_reservations(): void
    {
        $booking = $this->bookingWithDeposit($this->awa, 6000);
        $this->travelTo('2026-10-14 15:00:00');
        $this->arrive($booking, $this->awa);

        // Recorded after the arrival but dated before it: still an affectation payment.
        $this->travelTo('2026-10-14 16:00:00');
        $this->payOnInvoice($booking, $this->awa, 9000, '2026-10-14T09:30');

        $this->assertSame([[6000, 'reservation'], [9000, 'affectation']], $this->kinds());
    }

    public function test_a_cancelled_booking_s_deposit_and_old_stays_are_classified(): void
    {
        $cancelled = $this->bookingWithDeposit($this->awa, 6000);
        $this->actingAs($this->awa)->post(route('admin.reservations.cancel', $cancelled))->assertSessionHasNoErrors();

        // A stay started before checked_in_at existed: the planned arrival is the reference.
        $old = $this->bookingWithDeposit($this->awa, 6000);
        $other = $this->bookingWithDeposit($this->awa, 6000);
        $old->forceFill(['status' => 'enregistree', 'checked_in_at' => null])->saveQuietly();
        $old->invoice->settle([['method' => 'wave', 'amount' => 9000]], now());   // recorded at 09:00, before 14:00
        $other->forceFill(['status' => 'enregistree', 'checked_in_at' => null])->saveQuietly();
        $this->travelTo('2026-10-14 15:00:00');
        $other->invoice->settle([['method' => 'wave', 'amount' => 3000]], now()); // recorded at 15:00, after 14:00

        $this->assertSame([[6000, 'reservation'], [6000, 'reservation'], [6000, 'reservation'], [9000, 'reservation'], [3000, 'affectation']], $this->kinds());
        $this->assertSame(Payment::query()->bookingPayments()->count(), collect($this->kinds())->where(1, 'reservation')->count());
    }

    public function test_the_page_splits_the_totals_and_filters_by_kind(): void
    {
        $this->affectation($this->awa);
        $this->affectation($this->manager, 2);
        $booking = $this->bookingWithDeposit($this->awa, 6000, 'especes');

        $page = $this->actingAs($this->manager)->get(route('admin.revenue.index'))->assertOk();
        $byKind = $page->viewData('byKind');
        $this->assertSame(15000.0, $byKind['affectation']['total']);
        $this->assertSame(2, $byKind['affectation']['count']);
        $this->assertSame(6000.0, $byKind['reservation']['total']);
        $this->assertSame(1, $byKind['reservation']['count']);
        $this->assertSame(21000.0, $page->viewData('summary')['total']); // the two sides make the whole
        $page->assertSee('Réservations')->assertSee("Encaissé sur réservation (avant l'arrivée)", false)
            ->assertSee('<option value="reservation" >Réservations</option>', false);

        // ?dossier=reservation: lines, totals, staff table.
        $page = $this->get(route('admin.revenue.index', ['dossier' => 'reservation']))->assertOk();
        $this->assertSame(1, $page->viewData('payments')->total());
        $this->assertSame(6000.0, $page->viewData('summary')['total']);
        $this->assertSame([6000.0], array_column($page->viewData('staff'), 'total'));
        $this->assertSame(15000.0, $page->viewData('byKind')['affectation']['total']); // both cards stay, to compare
        $this->assertSame(6000.0, $page->viewData('byKind')['reservation']['total']);
        $page->assertSee('Total encaissé — réservations')->assertSee('<option value="reservation" selected>Réservations</option>', false);

        $page = $this->get(route('admin.revenue.index', ['dossier' => 'affectation']))->assertOk();
        $this->assertSame(2, $page->viewData('payments')->total());
        $this->assertSame(15000.0, $page->viewData('summary')['total']);

        // Forged array values on any filter are ignored, never a crash.
        foreach (['periode', 'du', 'au', 'jour', 'par', 'mode', 'dossier'] as $key) {
            $this->get(route('admin.revenue.index', [$key => ['x']]))->assertOk();
            $this->get(route('admin.revenue.export', [$key => ['x']]))->assertOk();
        }

        // Unknown or malicious values are ignored (both kinds shown).
        foreach (['tout', "reservation' or '1'='1", '<script>', ['x']] as $value) {
            $page = $this->get(route('admin.revenue.index', ['dossier' => $value]))->assertOk();
            $this->assertSame(21000.0, $page->viewData('summary')['total']);
            $this->assertNull($page->viewData('filters')['kind']);
        }

        // The export follows the filter and says which kind each line is.
        $csv = $this->get(route('admin.revenue.export', ['dossier' => 'reservation']))->assertOk()->streamedContent();
        $this->assertStringContainsString(';Réservation;'.$booking->reference().';', $csv);
        $this->assertStringNotContainsString(';Affectation;', $csv);
        $csv = $this->get(route('admin.revenue.export'))->streamedContent();
        $this->assertSame(2, substr_count($csv, ';Affectation;'));
        $this->assertSame(1, substr_count($csv, ';Réservation;'));
    }

    public function test_a_receptionist_still_only_sees_her_own_payments_with_the_kind_filter(): void
    {
        $this->affectation($this->awa);
        $this->bookingWithDeposit($this->manager, 6000); // the manager's réservation deposit
        $this->bookingWithDeposit($this->awa, 4000);

        foreach ([[], ['dossier' => 'reservation'], ['dossier' => 'reservation', 'par' => (string) $this->manager->id], ['par' => 'aucun']] as $query) {
            $page = $this->actingAs($this->awa)->get(route('admin.revenue.index', $query))->assertOk();
            $this->assertSame([], $page->viewData('staff'));
            $this->assertSame([$this->awa->id], $page->viewData('payments')->getCollection()->pluck('created_by')->unique()->values()->all());
            $csv = $this->get(route('admin.revenue.export', $query))->streamedContent();
            $this->assertStringNotContainsString('6000', $csv);
        }

        $byKind = $this->get(route('admin.revenue.index'))->viewData('byKind');
        $this->assertSame(5000.0, $byKind['affectation']['total']);
        $this->assertSame(4000.0, $byKind['reservation']['total']); // not the manager's 6 000
    }

    public function test_the_export_never_lets_excel_run_a_formula_typed_in_a_name(): void
    {
        $room = $this->room();
        $this->actingAs($this->manager)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => '=HYPERLINK("http://evil.example","x")', 'new_last_name' => '', 'new_phone' => '0711223344',
            'pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]],
        ])->assertSessionHasNoErrors();

        $csv = $this->get(route('admin.revenue.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString(";\"'=HYPERLINK(\"\"http://evil.example\"\",\"\"x\"\")\";", $csv);
        $this->assertStringNotContainsString(';"=HYPERLINK', $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }
}
