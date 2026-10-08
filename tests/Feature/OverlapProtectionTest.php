<?php

namespace Tests\Feature;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\Admin\ReservationRequest;
use App\Http\Requests\Admin\RoomAssignmentRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A room can never be held twice over the same period (date, time or any overlap).
 */
class OverlapProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000, 'day_rate' => 20000, 'night_rate' => 40000, 'stay_rate' => 60000]);
        $this->room = Room::factory()->create(['number' => 'OV-1', 'room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
    }

    private function existing(string $checkIn, PricingMode $mode, int $duration, ReservationStatus $status = ReservationStatus::Confirmee): Reservation
    {
        // Paid in full: a booking only holds its room once guaranteed.
        return Reservation::factory()->guaranteed()->create([
            'room_id' => $this->room->id, 'pricing_mode' => $mode, 'check_in' => $checkIn,
            'duration' => $duration, 'unit_rate' => 5000, 'status' => $status,
        ]);
    }

    private function book(string $mode, string $checkIn, int $duration, array $extra = [])
    {
        return $this->actingAs(User::factory()->create())->post('/admin/reservations', [
            'guest_id' => Guest::factory()->create()->id, 'room_id' => $this->room->id, 'pricing_mode' => $mode,
            'check_in' => $checkIn, 'duration' => $duration, 'unit_rate' => PricingMode::from($mode)->rateFor($this->room->roomType),
            'adults' => 1, 'children' => 0, 'status' => 'confirmee', ...$extra,
        ]);
    }

    public function test_overlapping_periods_are_refused_and_stays_need_the_cleaning_gap(): void
    {
        $this->existing('2026-10-10 14:00', PricingMode::Heure, 3); // 14:00 → 17:00, 30-min cleaning on each side

        $this->book('heure', '2026-10-10T14:00', 3)->assertSessionHasErrors(['room_id' => 'La chambre N°OV-1 est déjà prise du 10/10/2026 à 14h00 au 10/10/2026 à 17h00 (réservation soldée).']);
        $this->book('heure', '2026-10-10T16:59', 1)->assertSessionHasErrors('room_id'); // ends inside
        $this->book('heure', '2026-10-10T13:00', 2)->assertSessionHasErrors('room_id'); // starts before, ends inside
        $this->book('heure', '2026-10-10T15:00', 1)->assertSessionHasErrors('room_id'); // contained
        $this->book('sejour', '2026-10-10T08:00', 1)->assertSessionHasErrors('room_id'); // contains it
        $this->book('nuitee', '2026-10-09', 2)->assertSessionHasErrors('room_id');       // nights of 9 and 10 cover it
        $this->assertSame(1, Reservation::count());

        // Back-to-back without the cleaning time is refused…
        $this->book('heure', '2026-10-10T17:00', 2)->assertSessionHasErrors(['room_id' => 'La chambre N°OV-1 est libérée le 10/10/2026 à 17h00 ; avec le ménage obligatoire de 30 min, le séjour suivant peut commencer au plus tôt le 10/10/2026 à 17h30.']);
        $this->book('heure', '2026-10-10T11:00', 3)->assertSessionHasErrors(['room_id' => 'La chambre N°OV-1 est prise à partir du 10/10/2026 à 14h00 ; avec le ménage obligatoire de 30 min, ce séjour doit se terminer au plus tard le 10/10/2026 à 13h30.']);
        $this->assertSame(1, Reservation::count());

        // …with it, fine (these are unpaid bookings: they don't hold the room, so don't block each other).
        $this->book('heure', '2026-10-10T17:30', 2)->assertSessionHasNoErrors();         // 17:00 + 30 min
        $this->book('heure', '2026-10-10T10:30', 3)->assertSessionHasNoErrors();         // ends 13:30, cleaning until 14:00
        $this->book('nuitee', '2026-10-10', 1)->assertSessionHasNoErrors();              // 22:00 → 12:00
        $this->book('journee', '2026-10-11T12:30', 1)->assertSessionHasNoErrors();       // after the nuitée + cleaning
        $this->assertSame(5, Reservation::count());
    }

    public function test_cancelled_and_finished_stays_do_not_block(): void
    {
        $this->existing('2026-10-10 14:00', PricingMode::Heure, 3, ReservationStatus::Annulee);
        $this->existing('2026-10-08 06:00', PricingMode::Heure, 6, ReservationStatus::Terminee);

        $this->book('heure', '2026-10-10T14:00', 3)->assertSessionHasNoErrors();
    }

    public function test_postponing_onto_another_booking_is_refused_but_resaving_in_place_is_fine(): void
    {
        $this->existing('2026-10-12 10:00', PricingMode::Heure, 2);
        $mine = $this->existing('2026-10-10 10:00', PricingMode::Heure, 2);
        $payload = ['guest_id' => $mine->guest_id, 'room_id' => $this->room->id, 'pricing_mode' => 'heure', 'duration' => 2,
            'unit_rate' => 5000, 'adults' => 1, 'children' => 0, 'status' => 'confirmee'];

        $this->actingAs(User::factory()->create())->put("/admin/reservations/{$mine->id}", [...$payload, 'check_in' => '2026-10-12T11:00'])->assertSessionHasErrors('room_id');
        $this->actingAs(User::factory()->create())->put("/admin/reservations/{$mine->id}", [...$payload, 'check_in' => '2026-10-10T10:00', 'adults' => 2])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-10 10:00', $mine->fresh()->check_in->format('Y-m-d H:i'));
    }

    public function test_an_overdue_stay_holds_the_room_until_its_real_check_out(): void
    {
        $stay = $this->existing('2026-10-08 06:00', PricingMode::Heure, 3); // due 09:00, it is 10:00
        $stay->update(['status' => ReservationStatus::Enregistree]);

        // Even a booking for tomorrow is refused while the guest hasn't been checked out.
        $this->book('heure', '2026-10-09T10:00', 2)->assertSessionHasErrors([
            'room_id' => "La chambre N°OV-1 est encore occupée : le départ du séjour en cours était prévu le 08/10/2026 à 09h00 et le check-out n'a pas été fait. Faites le check-out ou prolongez ce séjour d'abord.",
        ]);

        $this->actingAs(User::factory()->create())->post("/admin/affectations/{$stay->id}/check-out");
        $this->book('heure', '2026-10-09T10:00', 2)->assertSessionHasNoErrors();
    }

    public function test_a_room_out_of_service_cannot_be_booked_nor_listed(): void
    {
        $this->room->update(['status' => RoomStatus::HorsService]);

        $this->book('heure', '2026-10-10T14:00', 2)->assertSessionHasErrors(['room_id' => 'La chambre N°OV-1 est hors service : elle ne peut pas être réservée.']);
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/rooms/available?pricing_mode=heure&check_in=2026-10-10T14:00&duration=2')
            ->assertOk()->assertJsonMissing(['id' => $this->room->id]);
    }

    public function test_a_concurrent_booking_slipping_in_after_validation_is_caught_by_the_lock(): void
    {
        // Simulates two cashiers: once this request has passed validation, someone else books
        // the same slot before it is saved. The re-check under lock must refuse it.
        $this->app->afterResolving(ReservationRequest::class, function () {
            $this->existing('2026-10-10 14:00', PricingMode::Heure, 3);
        });

        $this->book('heure', '2026-10-10T15:00', 1)->assertSessionHasErrors('room_id');
        $this->assertSame(1, Reservation::count(), 'Only the other booking exists — no double booking.');
    }

    public function test_a_concurrent_affectation_slipping_in_after_validation_is_caught_by_the_lock(): void
    {
        $this->app->afterResolving(RoomAssignmentRequest::class, function () {
            $this->existing('2026-10-08 10:30', PricingMode::Heure, 2);
        });

        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$this->room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0101',
            'pricing_mode' => 'heure', 'duration' => 2, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 10000]],
        ])->assertSessionHasErrors('duration');

        $this->assertSame(1, Reservation::count());
        $this->assertSame(0, Guest::where('first_name', 'Awa')->count(), 'The whole assignment was rolled back.');
        $this->assertSame(RoomStatus::Disponible, $this->room->fresh()->status);
    }

    public function test_extending_a_stay_onto_a_booking_explains_the_conflict(): void
    {
        $stay = $this->existing('2026-10-08 09:00', PricingMode::Heure, 2);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        $this->existing('2026-10-08 12:00', PricingMode::Heure, 2);

        $this->actingAs(User::factory()->create())->put("/admin/affectations/{$stay->id}", ['duration' => 4, 'unit_rate' => 5000, 'adults' => 1, 'children' => 0])
            ->assertSessionHasErrors(['duration' => "Impossible de prolonger jusqu'au 08/10/2026 à 13h00 : la chambre N°OV-1 est déjà prise du 08/10/2026 à 12h00 au 08/10/2026 à 14h00 (réservation soldée)."]);
    }
}
