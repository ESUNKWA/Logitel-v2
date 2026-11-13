<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use App\Support\HotelSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Séjour ending the next day at a fixed time (client request, 2026-11-13): ticked per room type,
 * one hotel-wide time (12:00), departure that time the day after the arrival whatever the arrival
 * time, everywhere; each file keeps the rule it was made with; « Séjour (jusqu'à 12h00) ».
 */
class FixedStayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $fixed;

    private RoomType $hours;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-17 15:00:00'); // a Tuesday
        $this->fixed = RoomType::factory()->create(['name' => 'Suite', 'stay_fixed_checkout' => true]); // séjour 90 000
        $this->hours = RoomType::factory()->create(['name' => 'Standard']);
        $this->actingAs(User::factory()->create());
    }

    private function room(RoomType $type, string $number): Room
    {
        return Room::factory()->create(['number' => $number, 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function assign(Room $room, int $duration = 1)
    {
        return $this->post(route('admin.rooms.assign', $room), [
            'pricing_mode' => 'sejour', 'duration' => $duration, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 90000 * $duration]],
        ]);
    }

    public function test_the_engine_ends_the_stay_the_next_day_at_the_fixed_time_whatever_the_arrival(): void
    {
        $mode = PricingMode::Sejour;
        foreach (['2026-11-17 15:00' => '2026-11-18 12:00', '2026-11-17 08:00' => '2026-11-18 12:00', '2026-11-17 02:00' => '2026-11-18 12:00', '2026-11-17 23:59' => '2026-11-18 12:00'] as $arrival => $departure) {
            $this->assertSame($departure, $mode->end(Carbon::parse($arrival), 1, '12:00')->format('Y-m-d H:i'), $arrival);
        }
        $this->assertSame('2026-11-19 12:00', $mode->end(Carbon::parse('2026-11-17 15:00'), 2, '12:00')->format('Y-m-d H:i'));
        // Without the rule: periods of 24 h, as before.
        $this->assertSame('2026-11-18 15:00', $mode->end(Carbon::parse('2026-11-17 15:00'), 1)->format('Y-m-d H:i'));
        // The other formulas ignore it.
        $this->assertSame('2026-11-17 18:00', PricingMode::Heure->resolve(Carbon::parse('2026-11-17 15:00'), 3, null, '12:00')[1]->format('Y-m-d H:i'));

        // Duration inferred from a departure (date-based forms).
        $this->assertSame(1, $mode->durationBetween(Carbon::parse('2026-11-17 15:00'), Carbon::parse('2026-11-18 12:00'), '12:00'));
        $this->assertSame(3, $mode->durationBetween(Carbon::parse('2026-11-17 15:00'), Carbon::parse('2026-11-20 00:00'), '12:00'));

        $this->assertSame("Séjour (jusqu'à 12h00)", $mode->getLabel('12:00'));
        $this->assertSame('Séjour (24 h)', $mode->getLabel());
        $this->assertSame('2 séjours (sortie à 12h00)', $mode->durationLabel(2, '12:00'));
        $this->assertSame('/ séjour', $mode->rateUnit('12:00'));
        $this->assertSame('12:00', PricingMode::stayUntilFor($this->fixed));
        $this->assertNull(PricingMode::stayUntilFor($this->hours));
    }

    public function test_an_affectation_of_a_ticked_type_leaves_the_next_day_at_noon_others_after_24_hours(): void
    {
        $suite = $this->room($this->fixed, 'S1');
        $standard = $this->room($this->hours, 'A1');

        $this->assign($suite)->assertSessionHasNoErrors();
        $this->assign($standard)->assertSessionHasNoErrors();

        $stay = Reservation::query()->where('room_id', $suite->id)->sole();
        $this->assertSame('2026-11-18 12:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('12:00', $stay->stay_until);
        $this->assertSame('90000.00', (string) $stay->total_amount);
        $this->assertSame('1 séjour (sortie à 12h00) × 90 000 FCFA', $stay->billingLabel());

        $other = Reservation::query()->where('room_id', $standard->id)->sole();
        $this->assertSame('2026-11-18 15:00', $other->check_out->format('Y-m-d H:i'));
        $this->assertNull($other->stay_until);

        // Shown as « Séjour (jusqu'à 12h00) » on the list, the file and the receipt.
        $this->get(route('admin.affectations.index'))->assertOk()->assertSee('Séjour (jusqu&#039;à 12h00)', false)->assertSee('Séjour (24 h)');
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertSee('Séjour (jusqu&#039;à 12h00)', false);
        $this->get(route('admin.receipts.show', $stay))->assertOk()->assertSee('Séjour (jusqu&#039;à 12h00)', false)->assertSee('1 séjour (sortie à 12h00)');
    }

    public function test_the_board_and_forms_carry_the_rule_for_the_live_preview(): void
    {
        $this->room($this->fixed, 'S1');
        $this->room($this->hours, 'A1');

        $this->get(route('admin.rooms.board'))->assertOk()
            ->assertSee('data-stay-until="12:00"', false)
            ->assertSee('data-stay-until=""', false)
            ->assertSee('Séjour (jusqu&#039;à 12h00)', false)
            ->assertSee('jusqu&#039;à 12h00', false);

        $this->get(route('admin.reservations.create'))->assertOk()->assertSee('data-stay-until="12:00"', false);
    }

    public function test_each_file_keeps_its_rule_when_the_setting_or_the_type_changes(): void
    {
        $suite = $this->room($this->fixed, 'S1');
        $this->assign($suite)->assertSessionHasNoErrors();
        $stay = Reservation::query()->sole();

        // A file made before the rule (24 h) of a type ticked since: re-saved, it keeps 24 h.
        $legacy = Reservation::factory()->create(['room_id' => $this->room($this->hours, 'A2')->id, 'pricing_mode' => 'sejour', 'unit_rate' => 90000,
            'check_in' => '2026-11-20 15:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->hours->update(['stay_fixed_checkout' => true]);
        $legacy->update(['notes' => 'Rappeler', 'status' => ReservationStatus::EnAttente]);
        $this->assertSame('2026-11-21 15:00', $legacy->fresh()->check_out->format('Y-m-d H:i'));

        // The hotel time moves to 11:00 and the suite is unticked: the stay in progress doesn't move…
        HotelSettings::save(['stay_checkout_time' => '11:00']);
        HotelSettings::apply();
        $this->fixed->update(['stay_fixed_checkout' => false]);
        $stay->update(['notes' => 'VIP']);
        $this->assertSame('2026-11-18 12:00', $stay->fresh()->check_out->format('Y-m-d H:i'));

        // …and an extension keeps its own rule: + 1 séjour = the day after at 12:00.
        $this->post(route('admin.affectations.extend', $stay), ['extra' => 1])->assertSessionHasNoErrors();
        $stay->refresh();
        $this->assertSame('2026-11-19 12:00', $stay->check_out->format('Y-m-d H:i'));
        $this->assertSame('12:00', $stay->stay_until);
        $this->assertSame('180000.00', (string) $stay->total_amount);
    }

    public function test_reservations_use_the_rule_for_departure_and_availability(): void
    {
        $suite = $this->room($this->fixed, 'S1');
        $book = fn (string $date, string $time) => $this->post(route('admin.reservations.store'), [
            'new_phone' => '0700000001', 'room_type_id' => $this->fixed->id, 'room_id' => $suite->id, 'pricing_mode' => 'sejour',
            'arrival_date' => $date, 'arrival_time' => $time, 'duration' => 1, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
        ]);

        $book('2026-11-20', '18:00')->assertSessionHasNoErrors();
        $booking = Reservation::query()->sole();
        $this->assertSame('2026-11-21 12:00', $booking->check_out->format('Y-m-d H:i'));
        $this->assertSame('12:00', $booking->stay_until);

        // Next one: from 12:30 (12:00 + 30 min cleaning), not before.
        $book('2026-11-21', '12:15')->assertSessionHasErrors('room_id');
        $book('2026-11-21', '12:30')->assertSessionHasNoErrors();

        // The room list follows the type's rule: free on the 22nd at 12:30 once the 2nd booking ends at 12:00.
        $rooms = fn (string $checkIn) => array_column($this->getJson(route('admin.rooms.available', [
            'room_type_id' => $this->fixed->id, 'pricing_mode' => 'sejour', 'check_in' => $checkIn, 'duration' => 1,
        ]))->assertOk()->json(), 'id');
        $this->assertSame([], $rooms('2026-11-22T11:00'));
        $this->assertSame([$suite->id], $rooms('2026-11-22T12:30'));
    }

    public function test_guest_requests_and_online_requests_follow_the_rule(): void
    {
        $suite = $this->room($this->fixed, 'S1');
        $suite->update(['is_published' => true]);
        auth()->logout();

        // Lobby QR page /demande.
        $this->post('/demande/chambre/'.$suite->id, ['pricing_mode' => 'sejour', 'duration' => 1, 'adults' => 1, 'children' => 0, 'website' => ''])
            ->assertSessionHasNoErrors();
        $request = StayRequest::query()->sole();
        $this->assertSame('2026-11-18 12:00', $request->check_out->format('Y-m-d H:i'));
        $this->assertSame('12:00', $request->stay_until);
        $this->get('/demande/chambre/'.$this->room($this->fixed, 'S2')->id)->assertOk()->assertSee('Séjour (jusqu&#039;à 12h00)', false);
        $request->update(['status' => 'refusee']);

        // Website « Réserver » (séjour only).
        $this->post('/reservation', [
            'room_type_id' => $this->fixed->id, 'pricing_mode' => 'sejour', 'check_in' => '2026-11-25T09:00', 'duration' => 2,
            'adults' => 2, 'children' => 0, 'phone' => '07 01 02 03 04',
        ])->assertSessionHasNoErrors();
        $online = BookingRequest::query()->sole();
        $this->assertSame('2026-11-27 12:00', $online->check_out->format('Y-m-d H:i'));
        $this->assertSame('12:00', $online->stay_until);

        // The room page says it.
        $this->get($suite->publicUrl())->assertOk()->assertSee('Séjour (jusqu&#039;à 12h00)', false)->assertSee('jusqu&#039;au lendemain à 12h00', false)->assertSee('/ séjour');
    }

    public function test_settings_and_room_type_form(): void
    {
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('name="stay_checkout_time"', false)->assertSee('value="12:00"', false);

        $settings = HotelSettings::all();
        $this->put(route('admin.settings.update'), ['stay_checkout_time' => '25:00'] + $settings)->assertSessionHasErrors('stay_checkout_time');
        $this->put(route('admin.settings.update'), ['stay_checkout_time' => '11:30'] + $settings)->assertSessionHasNoErrors();
        $this->assertSame('11:30', config('hotel.stay_checkout_time'));

        $this->get(route('admin.room-types.edit', $this->hours))->assertOk()->assertSee('name="stay_fixed_checkout"', false)->assertSee("jusqu'au lendemain à 11h30", false);
        $this->put(route('admin.room-types.update', $this->hours), [
            'name' => 'Standard', 'stay_rate' => '90 000', 'capacity_adults' => 2, 'capacity_children' => 0, 'is_active' => 1, 'stay_fixed_checkout' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($this->hours->fresh()->stay_fixed_checkout);
        $this->assertSame('11:30', PricingMode::stayUntilFor($this->hours->fresh()));
    }

    public function test_the_other_formulas_of_a_ticked_type_are_unaffected(): void
    {
        $suite = $this->room($this->fixed, 'S1');
        $this->post(route('admin.rooms.assign', $suite), [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 10000]],
        ])->assertSessionHasNoErrors();
        $stay = Reservation::query()->sole();
        $this->assertNull($stay->stay_until);
        $this->assertSame('2026-11-17 17:00', $stay->check_out->format('Y-m-d H:i'));
    }
}
