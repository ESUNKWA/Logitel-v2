<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\TrashedFile;
use App\Models\User;
use App\Support\FileTrash;
use App\Support\FinanceStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting affectations typed by mistake (Gérant only) → « Corbeille »: restore, erase for good,
 * automatic deletion after 30 days, and every figure following (client request, 2026-11-11).
 */
class AffectationTrashTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private User $manager;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-11 10:00:00');
        $this->type = RoomType::factory()->create();
        $this->manager = User::factory()->create(['name' => 'Le Gérant']);
        $this->reception = User::factory()->reception()->create(['name' => 'Awa']);
    }

    /**
     * A stay in progress (or finished), paid $paid (cash), in a new room.
     */
    private function stay(int $paid = 3000, bool $finish = false, ?Guest $guest = null, string $origin = Reservation::ORIGIN_AFFECTATION): Reservation
    {
        $this->actingAs($this->reception);
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create([
            'guest_id' => ($guest ?? Guest::factory()->create(['first_name' => 'Essai', 'last_name' => 'Test']))->id,
            'room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 1000, 'check_in' => now()->subMinutes(30),
            'duration' => 3, 'status' => 'confirmee', 'origin' => $origin,
        ]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        if ($paid > 0) {
            $stay->invoice->settle([['method' => 'especes', 'amount' => $paid, 'received' => $paid]], now());
        }
        if ($finish) {
            $stay->update(['status' => ReservationStatus::Terminee]);
        }

        return $stay->fresh();
    }

    private function trash(array $ids, array $extra = [])
    {
        return $this->actingAs($this->manager)->delete(route('admin.affectations.destroy-many'), ['ids' => $ids] + $extra);
    }

    public function test_the_manager_deletes_a_stay_with_its_invoice_payments_and_test_client_and_figures_follow(): void
    {
        $keep = $this->stay(2000);
        $stay = $this->stay(3000);
        $invoiceId = $stay->invoice->id;
        $guestId = $stay->guest_id;
        [$from, $to] = [now()->startOfDay(), now()->endOfDay()];
        $this->assertEquals(5000, FinanceStats::cashBetween($from, $to));

        $this->trash([$stay->id], ['reason' => 'Essai de la réceptionniste'])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains($s, $stay->reference()) && str_contains($s, '3 000 FCFA retirés des recettes'));

        $this->assertDatabaseMissing('reservations', ['id' => $stay->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoiceId]);
        $this->assertSame(0, Payment::query()->where('invoice_id', $invoiceId)->count());
        $this->assertDatabaseMissing('guests', ['id' => $guestId]); // test client with no other file
        $this->assertSame(RoomStatus::Disponible, $stay->room->fresh()->status); // room freed
        $this->assertEquals(2000, FinanceStats::cashBetween($from, $to)); // Recettes adjusted
        $this->assertEquals(2000, FinanceStats::cashBetween($from, $to, $this->reception->id));
        $this->assertNotNull($keep->fresh());

        $entry = TrashedFile::query()->sole();
        $this->assertSame($stay->reference(), $entry->reference);
        $this->assertSame('3000.00', (string) $entry->paid_amount);
        $this->assertSame('Essai de la réceptionniste', $entry->reason);
        $this->assertSame($this->manager->id, $entry->trashed_by);
        $this->assertTrue($entry->isInTrash());
        // The copy is encrypted at rest: no client name or amount readable in the database.
        $raw = DB::table('trashed_files')->value('payload');
        $this->assertStringNotContainsString('Essai', $raw);
        $this->assertStringNotContainsString('especes', $raw);

        // Every list and page drops it (the first page shows the confirmation message, naming it).
        $this->get(route('admin.affectations.index'))->assertOk()->assertSee('mise à la corbeille');
        $this->get(route('admin.affectations.index', ['onglet' => 'toutes']))->assertOk()->assertDontSee($stay->reference())->assertSee($keep->reference());
        $this->get(route('admin.affectations.show', $stay->id))->assertNotFound();
        $this->get(route('admin.revenue.index', ['periode' => 'aujourdhui']))->assertOk()->assertDontSee($stay->reference());
    }

    public function test_a_client_with_other_files_is_kept(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->stay(0, true, $guest);
        $this->stay(0, true, $guest);

        $this->trash([$first->id])->assertRedirect();

        $this->assertDatabaseHas('guests', ['id' => $guest->id]);
    }

    public function test_several_lines_at_once_and_an_affectation_from_a_booking_takes_its_booking_along(): void
    {
        $a = $this->stay(1000, true);
        $b = $this->stay(2000, false, null, Reservation::ORIGIN_RESERVATION);
        $requestId = DB::table('booking_requests')->insertGetId(['pricing_mode' => 'heure', 'check_in' => now(), 'duration' => 3, 'check_out' => now()->addHours(3),
            'unit_rate' => 1000, 'total_amount' => 3000, 'phone' => '0700000000', 'status' => 'traitee', 'reservation_id' => $b->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->trash([$a->id, $b->id])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '2 affectations mises à la corbeille'));

        $this->assertSame(0, Reservation::query()->whereKey([$a->id, $b->id])->count());
        $this->assertSame(2, TrashedFile::query()->inTrash()->count());
        $this->assertSame(1, TrashedFile::query()->distinct()->count('batch'));
        $this->assertSame('RES-'.str_pad((string) $b->id, 6, '0', STR_PAD_LEFT), TrashedFile::query()->where('reservation_id', $b->id)->value('booking_reference'));
        $this->assertNull(BookingRequest::query()->find($requestId)->reservation_id);
        $this->get(route('admin.reservations.index', ['onglet' => 'deja-affectees']))->assertOk()->assertDontSee($b->bookingReference());
    }

    public function test_restoring_brings_everything_back_identically(): void
    {
        $stay = $this->stay(3000);
        $before = [
            'reservation' => (array) DB::table('reservations')->where('id', $stay->id)->first(),
            'invoice' => (array) DB::table('invoices')->where('reservation_id', $stay->id)->first(),
            'payments' => DB::table('payments')->where('invoice_id', $stay->invoice->id)->get()->map(fn ($r) => (array) $r)->all(),
            'guest' => (array) DB::table('guests')->where('id', $stay->guest_id)->first(),
        ];
        $stayRequestId = DB::table('stay_requests')->insertGetId(['room_id' => $stay->room_id, 'first_name' => '', 'last_name' => '', 'phone' => '', 'pricing_mode' => 'heure',
            'duration' => 3, 'unit_rate' => 1000, 'total_amount' => 3000, 'check_in' => now(), 'check_out' => now()->addHours(3), 'status' => 'validee',
            'expires_at' => now(), 'reservation_id' => $stay->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->trash([$stay->id]);
        $entry = TrashedFile::query()->sole();

        $this->actingAs($this->manager)->post(route('admin.trash.restore'), ['ids' => [$entry->id]])
            ->assertRedirect(route('admin.trash.index'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, $stay->reference()));

        $this->assertEquals($before['reservation'], (array) DB::table('reservations')->where('id', $stay->id)->first());
        $this->assertEquals($before['invoice'], (array) DB::table('invoices')->where('reservation_id', $stay->id)->first());
        $this->assertEquals($before['payments'], DB::table('payments')->where('invoice_id', $before['invoice']['id'])->get()->map(fn ($r) => (array) $r)->all());
        $this->assertEquals($before['guest'], (array) DB::table('guests')->where('id', $stay->guest_id)->first());
        $this->assertSame($stay->id, StayRequest::query()->find($stayRequestId)->reservation_id);
        $this->assertSame(RoomStatus::Occupee, $stay->room->fresh()->status);
        $this->assertEquals(3000, FinanceStats::cashBetween(now()->startOfDay(), now()->endOfDay()));
        $this->assertSame(InvoiceStatus::Payee, Invoice::query()->where('reservation_id', $stay->id)->first()->status);

        $entry->refresh();
        $this->assertFalse($entry->isInTrash());
        $this->assertNull($entry->payload);
        $this->assertSame($this->manager->id, $entry->restored_by);

        // The invoice counter was never touched: the next invoice gets a new number.
        $next = $this->stay(0);
        $this->assertNotSame($before['invoice']['number'], $next->invoice->number);
    }

    public function test_restoring_a_stay_whose_room_was_taken_is_refused_with_a_message_and_others_still_come_back(): void
    {
        $stay = $this->stay(3000);
        $other = $this->stay(1000, true);
        $this->trash([$stay->id, $other->id]);

        // Someone else moves into the freed room.
        $this->actingAs($this->reception);
        $taker = Reservation::factory()->create(['room_id' => $stay->room_id, 'pricing_mode' => 'heure', 'unit_rate' => 1000,
            'check_in' => now(), 'duration' => 2, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $taker->update(['status' => ReservationStatus::Enregistree]);

        $ids = TrashedFile::query()->pluck('id')->all();
        $this->actingAs($this->manager)->post(route('admin.trash.restore'), ['ids' => $ids])
            ->assertRedirect(route('admin.trash.index'))
            ->assertSessionHasErrors('restore')
            ->assertSessionHas('status', fn ($s) => str_contains($s, $other->reference()));

        $errors = session('errors')->get('restore');
        $this->assertStringContainsString($stay->reference(), $errors[0]);
        $this->assertStringContainsString("N°{$stay->room->number}", $errors[0]);
        $this->assertDatabaseMissing('reservations', ['id' => $stay->id]);
        $this->assertDatabaseHas('reservations', ['id' => $other->id]);
        $this->assertTrue(TrashedFile::query()->where('reservation_id', $stay->id)->sole()->isInTrash());
    }

    public function test_definitive_deletion_and_automatic_deletion_after_30_days_keep_the_log(): void
    {
        $a = $this->stay(1000, true);
        $b = $this->stay(1000, true);
        $this->trash([$a->id]);
        $this->travel(1)->days();
        $this->trash([$b->id]);
        [$entryA, $entryB] = [TrashedFile::query()->where('reservation_id', $a->id)->sole(), TrashedFile::query()->where('reservation_id', $b->id)->sole()];

        $this->actingAs($this->manager)->post(route('admin.trash.purge'), ['ids' => [$entryB->id]])
            ->assertRedirect(route('admin.trash.index'))->assertSessionHas('status');
        $entryB->refresh();
        $this->assertNotNull($entryB->purged_at);
        $this->assertSame($this->manager->id, $entryB->purged_by);
        $this->assertNull($entryB->payload);
        $this->assertNull($entryB->guest_name);
        // No restoring once erased.
        $this->post(route('admin.trash.restore'), ['ids' => [$entryB->id]])->assertSessionHasErrors('restore');
        $this->assertDatabaseMissing('reservations', ['id' => $b->id]);

        // Day 29 after A: still there; day 30: erased automatically (command and page alike).
        $this->travelTo($entryA->trashed_at->copy()->addDays(29));
        $this->artisan('hotel:purge-trash')->assertSuccessful();
        $this->assertTrue($entryA->fresh()->isInTrash());
        $this->travelTo($entryA->trashed_at->copy()->addDays(30));
        $this->get(route('admin.trash.index'))->assertOk();
        $entryA->refresh();
        $this->assertNotNull($entryA->purged_at);
        $this->assertNull($entryA->purged_by);
        $this->assertNull($entryA->payload);

        // History keeps both lines.
        $this->get(route('admin.trash.index', ['onglet' => 'historique']))->assertOk()
            ->assertSee($a->reference())->assertSee($b->reference())
            ->assertSee('Supprimé définitivement')->assertSee('(automatiquement)');
        $this->assertSame(0, TrashedFile::query()->inTrash()->count());
    }

    public function test_trash_page_lists_files_with_who_when_why_and_the_automatic_deletion_date(): void
    {
        $stay = $this->stay(3000);
        $this->trash([$stay->id], ['reason' => 'Doublon <script>']);

        $this->actingAs($this->manager)->get(route('admin.trash.index'))->assertOk()
            ->assertSee($stay->reference())->assertSee('Essai Test')->assertSee('Le Gérant')
            ->assertSee('« Doublon &lt;script&gt; »', false)->assertDontSee('Doublon <script>', false)
            ->assertSee('11/12/2026') // 30 days later
            ->assertSee('id="restoreModal"', false)->assertSee('id="purgeModal"', false);

        $this->get(route('admin.affectations.index'))->assertOk()
            ->assertSee(route('admin.trash.index'))
            ->assertSee('id="affectationDeleteForm"', false)
            ->assertSee('id="deleteAffectationsModal"', false);
    }

    public function test_only_the_manager_can_delete_restore_or_purge_and_the_permission_cant_be_granted(): void
    {
        $stay = $this->stay(3000);

        // Reception: no button, no page, no action.
        $this->actingAs($this->reception)->get(route('admin.affectations.index'))->assertOk()
            ->assertDontSee('affectationDeleteForm')->assertDontSee(route('admin.trash.index'));
        $this->delete(route('admin.affectations.destroy-many'), ['ids' => [$stay->id]])->assertForbidden();
        $this->get(route('admin.trash.index'))->assertForbidden();
        $this->post(route('admin.trash.restore'), ['ids' => [1]])->assertForbidden();
        $this->post(route('admin.trash.purge'), ['ids' => [1]])->assertForbidden();
        $this->assertDatabaseHas('reservations', ['id' => $stay->id]);

        // A non-system role listing the permission (forged row) still gets nothing.
        $role = Role::query()->create(['name' => 'Adjoint']);
        DB::table('permission_role')->insert(['role_id' => $role->id, 'permission' => 'affectations.delete']);
        foreach (Permission::values() as $permission) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission' => $permission]);
        }
        $deputy = User::factory()->create();
        $deputy->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($deputy->fresh())->delete(route('admin.affectations.destroy-many'), ['ids' => [$stay->id]])->assertForbidden();

        // Not offered in the role form, refused if posted.
        $this->actingAs($this->manager)->get(route('admin.roles.create'))->assertOk()->assertDontSee('affectations.delete');
        $this->post(route('admin.roles.store'), ['name' => 'Test', 'permissions' => ['affectations.delete']])->assertSessionHasErrors('permissions.0');
    }

    public function test_input_is_validated_and_only_affectations_can_be_deleted(): void
    {
        $stay = $this->stay(1000);
        $this->actingAs($this->reception);
        $booking = Reservation::factory()->create(['pricing_mode' => 'nuitee', 'unit_rate' => 75000, 'check_in' => now()->addDays(3)->setTime(22, 0),
            'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $this->trash([])->assertSessionHasErrors('ids');
        $this->trash(['abc'])->assertSessionHasErrors('ids.0');
        $this->trash([$stay->id], ['reason' => ['x']])->assertSessionHasErrors('reason');
        $this->trash([$stay->id], ['reason' => str_repeat('a', 501)])->assertSessionHasErrors('reason');
        $this->trash(range(1, 101))->assertSessionHasErrors('ids');
        $this->actingAs($this->manager)->delete(route('admin.affectations.destroy-many'), ['ids' => 'x'])->assertSessionHasErrors('ids');

        // A réservation not started isn't an affectation: all or nothing, nothing deleted.
        $this->trash([$stay->id, $booking->id])->assertSessionHasErrors('ids');
        $this->assertDatabaseHas('reservations', ['id' => $stay->id]);
        $this->assertDatabaseHas('reservations', ['id' => $booking->id]);
        $this->assertSame(0, TrashedFile::query()->count());

        // Deleting twice: the second time is refused cleanly.
        $this->trash([$stay->id])->assertSessionHasNoErrors();
        $this->trash([$stay->id])->assertSessionHasErrors('ids');
        $this->assertSame(1, TrashedFile::query()->count());

        // Garbage on the trash actions.
        $this->post(route('admin.trash.restore'), ['ids' => ['x']])->assertSessionHasErrors('ids.0');
        $this->post(route('admin.trash.purge'), [])->assertSessionHasErrors('ids');
        $this->get(route('admin.trash.index', ['onglet' => ['x']]))->assertOk();
    }

    public function test_menus_link_the_trash_for_the_manager_only(): void
    {
        $this->actingAs($this->manager)->get(route('admin.stats'))->assertOk()->assertSee(route('admin.trash.index'));
        $simple = User::factory()->simpleUi()->create();
        $this->actingAs($simple)->get(route('admin.affectations.index'))->assertOk()->assertSee('Corbeille des affectations');
        $this->actingAs($this->reception)->get(route('admin.affectations.index'))->assertOk()->assertDontSee('Corbeille');
    }

    public function test_restore_survives_a_deleted_forfait_and_a_recreated_client_is_not_duplicated(): void
    {
        $guest = Guest::factory()->create();
        $a = $this->stay(0, true, $guest);
        $b = $this->stay(0, true, $guest);
        $this->trash([$a->id, $b->id]); // the guest goes with the second one
        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);

        foreach (TrashedFile::query()->orderBy('id')->get() as $entry) {
            FileTrash::restore($entry);
        }

        $this->assertSame(1, Guest::query()->whereKey($guest->id)->count());
        $this->assertSame(2, Reservation::query()->where('guest_id', $guest->id)->count());
    }
}
