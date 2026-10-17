<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SpecialRate;
use App\Models\StayRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Forfaits (« 2 heures = 5 000 FCFA ») applied automatically to affectations: exact duration,
 * cheapest one wins and only when below the normal price, days / period / room types / active,
 * extensions recomputed on the entry day, existing stays keep their price, réservations never
 * get one; the management screen and its permission.
 */
class SpecialRateTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $confort;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00'); // Thursday
        $this->confort = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 3000, 'day_rate' => 15000, 'night_rate' => 25000]);
        $this->room = Room::factory()->create(['number' => 'F-1', 'room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs(User::factory()->create());
    }

    private function forfait(array $attributes = [], ?array $types = null): SpecialRate
    {
        $rate = SpecialRate::create([
            'name' => 'Promo 2 heures', 'pricing_mode' => 'heure', 'duration' => 2, 'price' => 5000,
            'all_room_types' => false, 'is_active' => true, ...$attributes,
        ]);
        $rate->roomTypes()->sync($types ?? [$this->confort->id]);

        return $rate;
    }

    private function assign(int $duration, int $paid, array $extra = [], ?Room $room = null)
    {
        return $this->post('/admin/rooms/'.($room ?? $this->room)->id.'/assign', [
            'pricing_mode' => 'heure', 'duration' => $duration, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => $paid]], ...$extra,
        ]);
    }

    public function test_an_affectation_of_the_forfait_duration_gets_the_forfait_price(): void
    {
        $rate = $this->forfait();

        $this->assign(2, 5000)->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('3000.00', (string) $stay->unit_rate); // the type's rate is still recorded
        $this->assertSame($rate->id, $stay->special_rate_id);
        $this->assertSame('Promo 2 heures', $stay->special_rate_name);
        $this->assertSame('5000.00', (string) $stay->special_price);
        $this->assertSame('5000.00', (string) $stay->total_amount);
        $this->assertSame('5000.00', (string) $stay->invoice->amount);
        $this->assertSame(InvoiceStatus::Payee, $stay->invoice->status);
        $this->assertSame('6000.00', $stay->normalSubtotal());
        $this->assertSame('Forfait 2 heures : 5 000 FCFA au lieu de 6 000 FCFA', $stay->billingLabel());
    }

    public function test_the_payment_in_full_follows_the_forfait(): void
    {
        $this->forfait();

        $this->assign(2, 6000)->assertSessionHasErrors('payments'); // the normal price is now an overpayment
        $this->assign(2, 4000)->assertSessionHasErrors('payments'); // and less is still refused
        $this->assertSame(0, Reservation::count());
    }

    public function test_other_durations_keep_the_normal_price(): void
    {
        $this->forfait();

        $this->assign(3, 9000)->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertNull($stay->special_rate_id);
        $this->assertFalse($stay->hasSpecialRate());
        $this->assertSame('9000.00', (string) $stay->total_amount);
        $this->assertSame('3 heures × 3 000 FCFA', $stay->billingLabel());
    }

    public function test_the_cheapest_matching_forfait_wins_and_never_one_above_the_normal_price(): void
    {
        $this->forfait(['name' => 'Promo', 'price' => 5000]);
        $this->forfait(['name' => 'Super promo', 'price' => 4500]);
        $this->forfait(['name' => 'Trop cher', 'price' => 7000]);

        $this->assign(2, 4500)->assertSessionHasNoErrors();
        $this->assertSame('Super promo', Reservation::sole()->special_rate_name);

        SpecialRate::query()->update(['price' => 6000]); // equal to the normal price: no forfait
        $other = Room::factory()->create(['room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        $this->assign(2, 6000, room: $other)->assertSessionHasNoErrors();
        $this->assertNull(Reservation::query()->where('room_id', $other->id)->sole()->special_rate_id);
    }

    public function test_days_of_the_week_follow_the_business_day_of_entry(): void
    {
        $this->forfait(['days' => [7]]); // Sundays only

        $this->assign(2, 6000)->assertSessionHasNoErrors(); // Thursday: normal price
        $this->assertNull(Reservation::sole()->special_rate_id);

        // Sunday 03:00 still belongs to Saturday's business day (it starts at 06:00).
        $this->travelTo('2026-10-11 03:00:00');
        $saturdayNight = Room::factory()->create(['room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        $this->assign(2, 6000, room: $saturdayNight)->assertSessionHasNoErrors();

        $this->travelTo('2026-10-11 10:00:00');
        $sunday = Room::factory()->create(['room_type_id' => $this->confort->id, 'status' => RoomStatus::Disponible]);
        $this->assign(2, 5000, room: $sunday)->assertSessionHasNoErrors();
        $this->assertSame('5000.00', (string) Reservation::query()->where('room_id', $sunday->id)->sole()->total_amount);
    }

    public function test_period_activity_and_room_types_are_respected(): void
    {
        $this->forfait(['starts_on' => '2026-10-09']); // starts tomorrow
        $this->forfait(['ends_on' => '2026-10-07']);   // ended yesterday
        $this->forfait(['is_active' => false]);
        $suite = RoomType::factory()->create(['name' => 'Suite', 'hourly_rate' => 3000]);
        $this->forfait([], [$suite->id]);               // another type

        $this->assign(2, 6000)->assertSessionHasNoErrors();
        $this->assertNull(Reservation::sole()->special_rate_id);

        // « Tous les types » covers types created later too.
        $this->forfait(['name' => 'Partout', 'all_room_types' => true], []);
        $later = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['hourly_rate' => 3000])->id, 'status' => RoomStatus::Disponible]);
        $this->assign(2, 5000, room: $later)->assertSessionHasNoErrors();
        $this->assertSame('Partout', Reservation::query()->where('room_id', $later->id)->sole()->special_rate_name);
    }

    public function test_a_discount_applies_on_top_of_the_forfait(): void
    {
        $this->forfait();

        $this->assign(2, 4000, ['discount_type' => 'montant', 'discount_value' => 1000])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('5000.00', $stay->subtotal());
        $this->assertSame('1000.00', (string) $stay->discount_amount);
        $this->assertSame('4000.00', (string) $stay->total_amount);
    }

    public function test_extending_or_shortening_recomputes_the_forfait_on_the_entry_day(): void
    {
        $this->forfait();
        $this->forfait(['name' => 'Promo 4 heures', 'duration' => 4, 'price' => 10000]);
        $this->assign(2, 5000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        // An hour later the guest stays longer: the forfaits of the entry day are applied again.
        $this->travelTo('2026-10-08 11:00:00');
        $this->put(route('admin.affectations.update', $stay), ['duration' => 3, 'adults' => 1, 'children' => 0])->assertSessionHasNoErrors();
        $stay->refresh();
        $this->assertNull($stay->special_rate_id);
        $this->assertSame('9000.00', (string) $stay->total_amount); // 3 h: no forfait, normal price
        $this->assertSame('9000.00', (string) $stay->invoice->amount);

        $this->put(route('admin.affectations.update', $stay), ['duration' => 4, 'adults' => 1, 'children' => 0])->assertSessionHasNoErrors();
        $this->assertSame('Promo 4 heures', $stay->fresh()->special_rate_name);
        $this->assertSame('10000.00', (string) $stay->fresh()->total_amount);
    }

    public function test_an_extension_the_next_day_uses_the_forfaits_of_the_entry_day(): void
    {
        $this->forfait(['ends_on' => '2026-10-08']);
        $this->forfait(['name' => 'Promo 3 heures', 'duration' => 3, 'price' => 7000, 'ends_on' => '2026-10-08']);
        $this->assign(2, 5000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        $this->travelTo('2026-10-09 07:00:00'); // next business day: both forfaits have ended
        $this->put(route('admin.affectations.update', $stay), ['duration' => 3, 'adults' => 1, 'children' => 0])->assertSessionHasNoErrors();

        $this->assertSame('Promo 3 heures', $stay->fresh()->special_rate_name);
        $this->assertSame('7000.00', (string) $stay->fresh()->total_amount);
    }

    public function test_shortening_below_what_was_paid_is_still_refused_with_forfait_prices(): void
    {
        $this->forfait(['name' => 'Promo 4 heures', 'duration' => 4, 'price' => 10000]);
        $this->assign(4, 10000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        // 3 h = 9 000 F < 10 000 F already paid.
        $this->put(route('admin.affectations.update', $stay), ['duration' => 3, 'adults' => 1, 'children' => 0])->assertSessionHasErrors('duration');
        $this->assertSame('10000.00', (string) $stay->fresh()->total_amount);
    }

    public function test_existing_stays_keep_their_price_when_the_forfait_changes_or_is_deleted(): void
    {
        $rate = $this->forfait();
        $this->assign(2, 5000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        $this->put(route('admin.special-rates.update', $rate), [
            'name' => 'Promo 2 heures', 'pricing_mode' => 'heure', 'duration' => 2, 'forfait_price' => '4 000',
            'room_type_ids' => [$this->confort->id], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $stay->update(['adults' => 2]); // an unrelated change doesn't re-price
        $this->assertSame('5000.00', (string) $stay->fresh()->total_amount);

        $this->delete(route('admin.special-rates.destroy', $rate))->assertRedirect();
        $stay->refresh();
        $this->assertNull($stay->special_rate_id);
        $this->assertSame('Promo 2 heures', $stay->special_rate_name);
        $this->assertSame('5000.00', (string) $stay->special_price);
        $this->assertSame('5000.00', (string) $stay->total_amount);
    }

    public function test_reservations_never_get_a_forfait_even_once_arrived(): void
    {
        $this->forfait();

        $booking = Reservation::factory()->create(['room_id' => $this->room->id, 'pricing_mode' => 'heure', 'unit_rate' => 3000,
            'check_in' => '2026-10-08 14:00', 'duration' => 2, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->assertNull($booking->special_rate_id);
        $this->assertSame('6000.00', (string) $booking->total_amount);

        $this->travelTo('2026-10-08 14:00:00');
        $this->post(route('admin.reservations.check-in', $booking))->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->special_rate_id);
        $this->assertSame('6000.00', (string) $booking->fresh()->total_amount);
    }

    public function test_validating_a_guest_request_applies_the_forfait(): void
    {
        $this->forfait();
        // The guest's request from their phone (public /demande page).
        auth()->logout();
        $this->post('/demande/chambre/'.$this->room->id, [
            'pricing_mode' => 'heure', 'duration' => 2, 'first_name' => 'Awa', 'last_name' => 'Koné',
            'phone' => '07 11 22 33 44', 'adults' => 1, 'children' => 0, 'website' => '',
        ])->assertSessionHasNoErrors();
        $request = StayRequest::sole();
        $this->actingAs(User::factory()->create());

        $this->get("/admin/demandes/{$request->id}")->assertOk()->assertSee('data-forfaits="{&quot;heure&quot;:{&quot;2&quot;:5000}}"', false);
        $this->post("/admin/demandes/{$request->id}/valider", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('5000.00', (string) Reservation::sole()->total_amount);
    }

    public function test_the_board_gives_today_s_forfaits_to_the_modal_and_the_file_shows_it(): void
    {
        $this->forfait();
        $this->forfait(['name' => 'Inutile', 'duration' => 3, 'price' => 9000]); // not cheaper: left out
        $this->forfait(['name' => 'Dimanche', 'duration' => 4, 'price' => 8000, 'days' => [7]]); // not today

        $this->get('/admin/rooms/board')->assertOk()
            ->assertSee('data-forfaits="{&quot;heure&quot;:{&quot;2&quot;:5000}}"', false)
            ->assertSee('data-forfait-hint', false);

        $this->assign(2, 5000)->assertSessionHasNoErrors();
        $stay = Reservation::sole();

        $this->get(route('admin.affectations.show', $stay))->assertOk()
            ->assertSee('Forfait 2 heures : 5 000 FCFA au lieu de 6 000 FCFA')
            ->assertSee("Forfaits (jour d'entrée) :", false)
            ->assertSee('2 heures = 5 000 FCFA');
        $this->get(route('admin.affectations.index'))->assertOk()->assertSee('Forfait 2 heures : 5 000 FCFA au lieu de 6 000 FCFA');
        $this->get(route('admin.invoices.show', $stay->invoice))->assertOk()->assertSee('Forfait 2 heures : 5 000 FCFA au lieu de 6 000 FCFA');
        $this->get(route('admin.receipts.show', $stay))->assertOk()
            ->assertSee('Forfait 2 heures')
            ->assertSee('au lieu de 6 000 (Promo 2 heures)');
    }

    public function test_managing_forfaits(): void
    {
        $this->get(route('admin.special-rates.index'))->assertOk()->assertSee("Aucun forfait pour l'instant.", false);
        $this->get(route('admin.special-rates.create'))->assertOk()->assertSee('Nouveau forfait');

        $this->post(route('admin.special-rates.store'), [
            'name' => 'Week-end', 'pricing_mode' => 'nuitee', 'duration' => 2, 'forfait_price' => '45 000',
            'room_type_ids' => [$this->confort->id], 'days' => [6, 7], 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31', 'is_active' => 1,
        ])->assertRedirect(route('admin.special-rates.index'))->assertSessionHas('status');

        $rate = SpecialRate::sole();
        $this->assertSame('45000.00', (string) $rate->price);
        $this->assertSame([6, 7], $rate->days);
        $this->assertSame([$this->confort->id], $rate->roomTypes->pluck('id')->all());
        $this->assertSame('Sam, Dim', $rate->daysLabel());
        $this->assertSame('du 01/10/2026 au 31/12/2026', $rate->periodLabel());

        $this->get(route('admin.special-rates.index'))->assertOk()
            ->assertSee('Week-end')->assertSee('2 nuits = ')->assertSee('45 000 FCFA')
            ->assertSee('(− 5 000)');
        $this->get(route('admin.special-rates.edit', $rate))->assertOk()->assertSee('value="45 000"', false);

        // Every day ticked = no restriction.
        $this->put(route('admin.special-rates.update', $rate), [
            'name' => 'Week-end', 'pricing_mode' => 'nuitee', 'duration' => 2, 'forfait_price' => 45000,
            'all_room_types' => 1, 'days' => [1, 2, 3, 4, 5, 6, 7], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertNull($rate->fresh()->days);
        $this->assertTrue($rate->fresh()->all_room_types);

        $this->delete(route('admin.special-rates.destroy', $rate))->assertRedirect();
        $this->assertSame(0, SpecialRate::count());
    }

    public function test_a_forfait_that_is_not_cheaper_is_flagged(): void
    {
        $this->post(route('admin.special-rates.store'), [
            'name' => 'Pas une promo', 'pricing_mode' => 'heure', 'duration' => 2, 'forfait_price' => 6000,
            'room_type_ids' => [$this->confort->id], 'is_active' => 1,
        ])->assertSessionHas('status', fn ($status) => str_contains($status, 'il ne s\'appliquera pas pour Confort'));

        $this->get(route('admin.special-rates.index'))->assertSee("ne s'applique pas", false);
    }

    public function test_validation(): void
    {
        $this->post(route('admin.special-rates.store'), [])
            ->assertSessionHasErrors(['name', 'pricing_mode', 'duration', 'forfait_price', 'room_type_ids']);
        $this->post(route('admin.special-rates.store'), [
            'name' => 'X', 'pricing_mode' => 'autre', 'duration' => 0, 'forfait_price' => -1,
            'room_type_ids' => [999], 'days' => [8], 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-01',
        ])->assertSessionHasErrors(['pricing_mode', 'duration', 'forfait_price', 'room_type_ids.0', 'days.0', 'ends_on']);
        $this->assertSame(0, SpecialRate::count());
    }

    public function test_only_staff_managing_rates_can_manage_forfaits(): void
    {
        $rate = $this->forfait();
        $this->actingAs(User::factory()->reception()->create());

        $this->get(route('admin.special-rates.index'))->assertForbidden();
        $this->post(route('admin.special-rates.store'), [])->assertForbidden();
        $this->delete(route('admin.special-rates.destroy', $rate))->assertForbidden();
        $this->get('/admin/rooms/board')->assertOk()->assertDontSee(route('admin.special-rates.index'), false);

        $this->actingAs(User::factory()->create());
        $this->get('/admin/rooms/board')->assertOk()->assertSee(route('admin.special-rates.index'), false);
    }
}
