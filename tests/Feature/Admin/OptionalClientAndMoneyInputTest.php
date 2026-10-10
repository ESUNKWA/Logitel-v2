<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client's details are optional at the desk (affectation), and amounts are typed with
 * thousands separators ("15 000"), stripped server-side by NormalizeMoneyInputs.
 */
class OptionalClientAndMoneyInputTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);
        $this->room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(array $data)
    {
        return $this->actingAs(User::factory()->create())->post("/admin/rooms/{$this->room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 3, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 15000]],
            ...$data,
        ]);
    }

    public function test_an_affectation_can_be_made_without_any_client_details(): void
    {
        $this->assign([])->assertSessionHasNoErrors()->assertRedirect(route('admin.rooms.board'));

        $guest = Reservation::sole()->guest;
        $this->assertSame('Client de passage', $guest->fullName());
        $this->assertSame('', $guest->phone);
        $this->assertSame(RoomStatus::Occupee, $this->room->fresh()->status);

        // Shown cleanly (no dangling dash) and the receipt still prints.
        $stay = Reservation::sole();
        $user = User::factory()->create();
        $this->actingAs($user)->get("/admin/affectations/{$stay->id}")->assertOk()->assertDontSee('Client de passage —');
        $this->actingAs($user)->get("/admin/recus/{$stay->id}")->assertOk()->assertSee('Client de passage');
    }

    public function test_partial_client_details_are_kept(): void
    {
        $this->assign(['new_first_name' => 'Awa', 'new_last_name' => '', 'new_phone' => ''])->assertSessionHasNoErrors();
        $this->assertSame('Awa', Reservation::sole()->guest->fullName());

        $this->room = Room::factory()->create(['room_type_id' => $this->room->room_type_id, 'status' => RoomStatus::Disponible]);
        $this->assign(['new_phone' => '0711223344'])->assertSessionHasNoErrors();
        $this->assertSame('0711223344', Reservation::latest('id')->first()->guest->phone);
        $this->assertSame('Client de passage', Reservation::latest('id')->first()->guest->fullName());
    }

    public function test_an_existing_client_can_still_be_chosen(): void
    {
        $guest = Guest::factory()->create();
        $this->assign(['guest_id' => $guest->id])->assertSessionHasNoErrors();

        $this->assertSame($guest->id, Reservation::sole()->guest_id);
        $this->assertSame(1, Guest::count());
    }

    public function test_reservations_still_require_a_client_or_at_least_a_phone(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/reservations', [
            'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'check_in' => '2026-10-10T14:00', 'duration' => 2,
            'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
        ])->assertSessionHasErrors('new_phone'); // an existing client, or at least the new client's phone
        $this->assertSame(0, Guest::count());
    }

    public function test_amounts_typed_with_thousands_separators_are_accepted(): void
    {
        // Regular, non-breaking and narrow non-breaking spaces.
        $this->assign([
            'unit_rate' => "5\u{00A0}000",
            'duration' => 3,
            'payments' => [['method' => 'especes', 'amount' => '10 000', 'received' => "12\u{202F}000"], ['method' => 'wave', 'amount' => '5 000']],
        ])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('5000.00', (string) $stay->unit_rate);
        $this->assertSame('15000.00', (string) $stay->total_amount);
        $cash = $stay->invoice->payments()->where('method', 'especes')->sole();
        $this->assertSame('2000.00', (string) $cash->change_given);
    }

    public function test_room_type_rates_and_invoice_payments_accept_separators(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/admin/room-types', [
            'name' => 'Suite', 'hourly_rate' => '12 500', 'night_rate' => '150 000', 'capacity_adults' => 2, 'capacity_children' => 0, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $type = RoomType::where('name', 'Suite')->sole();
        $this->assertSame('12500.00', (string) $type->hourly_rate);
        $this->assertSame('150000.00', (string) $type->night_rate);

        // Shown back grouped in the edit form.
        $this->actingAs($user)->get("/admin/room-types/{$type->id}/edit")->assertSee('value="150 000"', false)->assertSee('data-money', false);

        $booking = Reservation::factory()->create(['room_id' => $this->room->id, 'unit_rate' => 40000, 'pricing_mode' => 'nuitee', 'duration' => 1,
            'check_in' => '2026-10-12 22:00', 'check_out' => '2026-10-13 12:00', 'origin' => Reservation::ORIGIN_RESERVATION, 'status' => 'confirmee']);
        $invoice = Invoice::where('reservation_id', $booking->id)->sole();
        $this->actingAs($user)->post("/admin/invoices/{$invoice->id}/payments", [
            'payments' => [['method' => 'orange_money', 'amount' => '25 000']], 'paid_at' => '2026-10-08 10:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame('25000.00', (string) $invoice->payments()->sole()->amount);
    }

    public function test_other_fields_are_not_touched_by_the_normalisation(): void
    {
        $this->assign(['new_first_name' => 'Jean Marc', 'new_last_name' => 'Yao Koffi', 'new_phone' => '07 11 22 33 44'])->assertSessionHasNoErrors();

        $guest = Reservation::sole()->guest;
        $this->assertSame('Jean Marc Yao Koffi', $guest->fullName());
        $this->assertSame('07 11 22 33 44', $guest->phone); // spaces kept outside amount fields
    }
}
