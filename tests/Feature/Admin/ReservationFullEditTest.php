<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full edit of a réservation (client request, 2026-11-22): on top of the room, formula, dates,
 * occupants, status, discount and notes, the booking's own client is corrected in place and a
 * further deposit can be cashed — never above what is left, never pre-filled.
 */
class ReservationFullEditTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Reservation $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 10:00:00');
        $this->staff = User::factory()->create();
        $room = Room::factory()->create(['number' => 'FE-1', 'room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $guest = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '0101010101']);
        // 2 h × 5 000 = 10 000 FCFA, 4 000 already paid as a deposit.
        $this->booking = Reservation::factory()->create(['room_id' => $room->id, 'guest_id' => $guest->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'duration' => 2, 'check_in' => '2026-10-14 15:00', 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->booking->invoice->settle([['method' => 'wave', 'amount' => 4000]], now(), 'Acompte à la réservation');
        $this->booking->refresh();
    }

    private function update(array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->put(route('admin.reservations.update', $this->booking), [
            'room_id' => $this->booking->room_id, 'pricing_mode' => 'heure', 'check_in' => '2026-10-14 15:00', 'duration' => 2,
            'adults' => 1, 'children' => 0, 'status' => 'confirmee', 'guest_id' => $this->booking->guest_id,
            ...$extra,
        ]);
    }

    private function userWith(array $permissions): User
    {
        $role = Role::create(['name' => 'Rôle '.count($permissions)]);
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    public function test_the_edit_page_offers_the_client_details_and_a_further_deposit(): void
    {
        $this->actingAs($this->staff)->get(route('admin.reservations.edit', $this->booking))->assertOk()
            ->assertSee('name="client_phone" value="0101010101"', false)
            ->assertSee('name="client_first_name" value="Awa"', false)
            ->assertSee('name="client_last_name" value="Koné"', false)
            ->assertDontSee('name="client_company_name"', false)
            ->assertSee('Acompte supplémentaire')
            ->assertSee('déjà encaissé : 4 000 FCFA')
            ->assertSee('data-deposit-paid="4000"', false)
            ->assertSee('data-prefill="0"', false);

        // Creating keeps the pre-filled deposit (client request, 2026-11-09).
        $this->get(route('admin.reservations.create'))->assertOk()->assertSee('data-prefill="1"', false)->assertDontSee('Acompte supplémentaire');
    }

    public function test_the_client_is_corrected_in_place_with_the_booking(): void
    {
        $this->update(['client_phone' => ' 0707070707 ', 'client_first_name' => 'Aya', 'client_last_name' => 'Konan', 'notes' => 'Arrivée tardive'])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.reservations.index'));

        $guest = $this->booking->guest->fresh();
        $this->assertSame(['0707070707', 'Aya', 'Konan'], [$guest->phone, $guest->first_name, $guest->last_name]);
        $this->assertSame('Arrivée tardive', $this->booking->fresh()->notes);
        $this->assertSame($guest->id, $this->booking->fresh()->guest_id);
        $this->assertSame(1, $this->booking->invoice->payments()->count()); // nothing cashed
    }

    public function test_a_company_client_keeps_its_company_fields(): void
    {
        $this->booking->guest->update(['type' => Guest::TYPE_COMPANY, 'company_name' => 'ONG Espoir']);

        $this->actingAs($this->staff)->get(route('admin.reservations.edit', $this->booking))->assertOk()
            ->assertSee('name="client_company_name" value="ONG Espoir"', false);

        $this->update(['client_phone' => '0101010101', 'client_company_name' => 'ONG Espoir CI', 'client_tax_id' => 'CI-123', 'client_first_name' => '', 'client_last_name' => ''])
            ->assertSessionHasNoErrors();
        $guest = $this->booking->guest->fresh();
        $this->assertSame(['ONG Espoir CI', 'CI-123', ''], [$guest->company_name, $guest->tax_id, $guest->first_name]);
        $this->assertSame('ONG Espoir CI', $guest->fullName());
    }

    public function test_the_phone_stays_mandatory(): void
    {
        $this->update(['client_phone' => '', 'notes' => 'x'])->assertSessionHasErrors('client_phone');
        $this->assertSame('0101010101', $this->booking->guest->fresh()->phone);
        $this->assertNull($this->booking->fresh()->notes);
    }

    public function test_replacing_the_client_never_edits_the_previous_one(): void
    {
        $this->update(['guest_id' => '', 'new_phone' => '0909090909', 'new_first_name' => 'Jean', 'client_phone' => '0000', 'client_first_name' => 'Pirate'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['0101010101', 'Awa'], [$this->booking->guest->fresh()->phone, $this->booking->guest->fresh()->first_name]);
        $this->assertSame('Jean', $this->booking->fresh()->guest->first_name);
    }

    public function test_another_guest_is_never_edited_through_the_booking(): void
    {
        $other = Guest::factory()->create(['first_name' => 'Ali', 'phone' => '0202020202']);

        $this->update(['guest_id' => $other->id, 'client_phone' => '0000', 'client_first_name' => 'Pirate'])->assertSessionHasNoErrors();

        $this->assertSame(['0202020202', 'Ali'], [$other->fresh()->phone, $other->fresh()->first_name]);
        $this->assertSame(['0101010101', 'Awa'], [$this->booking->guest->fresh()->phone, $this->booking->guest->fresh()->first_name]);
    }

    public function test_client_details_need_guests_manage(): void
    {
        $user = $this->userWith(['reservations.view', 'reservations.manage']);

        $this->actingAs($user)->get(route('admin.reservations.edit', $this->booking))->assertOk()
            ->assertDontSee('name="client_phone"', false)->assertSee('Awa Koné');
        $this->update(['client_phone' => '0000', 'client_first_name' => 'Pirate'], $user)->assertSessionHasNoErrors();
        $this->assertSame(['0101010101', 'Awa'], [$this->booking->guest->fresh()->phone, $this->booking->guest->fresh()->first_name]);
    }

    public function test_a_further_deposit_when_editing(): void
    {
        $this->update(['payments' => [['method' => 'especes', 'amount' => 3000, 'received' => 5000]]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('receipt', $this->booking->id)
            ->assertSessionHas('status', 'Réservation mise à jour. Acompte encaissé : 3 000 FCFA. Monnaie à rendre : 2 000 FCFA.');

        $this->assertEqualsWithDelta(7000, (float) $this->booking->invoice->fresh()->amountPaid(), 0.001);
        $this->assertDatabaseHas('payments', ['invoice_id' => $this->booking->invoice->id, 'amount' => 3000, 'notes' => 'Acompte à la modification de la réservation']);
    }

    public function test_the_deposit_is_checked_against_the_new_total(): void
    {
        // Longer stay (3 h = 15 000): up to 11 000 more may be cashed in the same save.
        $this->update(['duration' => 3, 'payments' => [['method' => 'wave', 'amount' => 11000]]])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->booking->fresh()->duration);
        $this->assertSame('0.00', $this->booking->invoice->fresh()->remainingAmount());
    }

    public function test_more_than_what_is_left_is_refused_and_nothing_is_saved(): void
    {
        $this->update(['notes' => 'ne doit pas rester', 'client_phone' => '0707070707', 'payments' => [['method' => 'wave', 'amount' => 7000]]])
            ->assertSessionHasErrors('payments');

        $this->assertNull($this->booking->fresh()->notes);
        $this->assertSame('0101010101', $this->booking->guest->fresh()->phone);
        $this->assertSame(1, $this->booking->invoice->payments()->count());
    }

    public function test_cash_needs_the_amount_received(): void
    {
        $this->update(['payments' => [['method' => 'especes', 'amount' => 1000]]])->assertSessionHasErrors('payments.0.received');
        $this->assertSame(1, $this->booking->invoice->payments()->count());
    }

    public function test_a_further_deposit_needs_the_payment_permission(): void
    {
        $user = $this->userWith(['reservations.view', 'reservations.manage', 'guests.manage']);

        $this->actingAs($user)->get(route('admin.reservations.edit', $this->booking))->assertOk()->assertDontSee('Acompte supplémentaire');
        $this->update(['notes' => 'x', 'payments' => [['method' => 'wave', 'amount' => 1000]]], $user)->assertForbidden();
        $this->assertNull($this->booking->fresh()->notes);
        $this->assertSame(1, $this->booking->invoice->payments()->count());

        // Without lines, the edit itself still works.
        $this->update(['notes' => 'ok'], $user)->assertSessionHasNoErrors();
        $this->assertSame('ok', $this->booking->fresh()->notes);
    }

    public function test_an_edit_without_payment_cashes_nothing(): void
    {
        $this->update(['notes' => 'rien à encaisser'])->assertSessionHasNoErrors()
            ->assertSessionMissing('receipt')
            ->assertSessionHas('status', 'Réservation mise à jour.');
        $this->assertSame(1, $this->booking->invoice->payments()->count());
    }
}
