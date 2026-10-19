<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Affectations and réservations only show the modes the room type offers — hidden, not greyed
 * (client request, 2026-10-19). The réservation form starts with the room type; the nuitée stays
 * selectable at the desk before 22h00 (that evening's night).
 */
class ModesByRoomTypeTest extends TestCase
{
    use RefreshDatabase;

    private function type(array $attributes): RoomType
    {
        return RoomType::factory()->create(['is_active' => true, 'hourly_rate' => null, 'day_rate' => null, 'night_rate' => null, 'stay_rate' => null, ...$attributes]);
    }

    // ---- Réservation form -------------------------------------------------------------------

    public function test_the_reservation_form_starts_with_the_room_types_offering_a_rate(): void
    {
        $this->actingAs(User::factory()->create());
        $this->type(['name' => 'Suite', 'night_rate' => 75000, 'stay_rate' => 90000]);
        $this->type(['name' => 'Chambre 10', 'hourly_rate' => 5000]);
        $this->type(['name' => 'Chambre 2', 'hourly_rate' => 5000]);
        $this->type(['name' => 'Fermée', 'night_rate' => 1000, 'is_active' => false]);
        $this->type(['name' => 'Sans tarif']);

        $html = $this->get(route('admin.reservations.create'))->assertOk()
            ->assertSeeInOrder(['Type de chambre', 'Choisissez un type de chambre…', '>Chambre 2<', '>Chambre 10<', '>Suite<', 'Mode', 'Chambre'], false)
            ->assertSee("Choisissez d'abord le type de chambre : seuls ses modes s'affichent.", false)
            ->assertDontSee('>Fermée<', false)->assertDontSee('>Sans tarif<', false)
            ->getContent();

        // Each type says which modes it offers (read by admin.js); no type chosen yet → no mode hidden.
        $this->assertStringContainsString('data-heure="" data-journee="" data-nuitee="1" data-sejour="1"', $html);
        $this->assertStringNotContainsString('" hidden><i class="fas', $html);
    }

    public function test_a_known_type_shows_only_its_modes(): void
    {
        $this->travelTo('2026-10-19 10:00:00');
        $this->actingAs(User::factory()->create());
        $type = $this->type(['name' => 'Suite', 'night_rate' => 75000, 'stay_rate' => 90000]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-25 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);

        $html = $this->get(route('admin.reservations.edit', $booking))->assertOk()
            ->assertSee('<option value="'.$type->id.'" selected data-', false)->getContent();

        foreach (['heure', 'journee'] as $hidden) {
            $this->assertStringContainsString('for="booking-mode-'.$hidden.'" hidden >', $html);
            $this->assertMatchesRegularExpression('/id="booking-mode-'.$hidden.'"[^>]*disabled/', $html);
        }
        foreach (['nuitee', 'sejour'] as $shown) {
            $this->assertStringContainsString('for="booking-mode-'.$shown.'"><i', $html);
        }
        $this->assertStringContainsString("data-booking-type-hint hidden", $html);

        // An online booking request pre-selects its type.
        $request = BookingRequest::create(['room_type_id' => $type->id, 'pricing_mode' => 'sejour', 'check_in' => '2026-10-28 14:00', 'duration' => 1,
            'adults' => 2, 'children' => 0, 'unit_rate' => 90000, 'first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '0711223344']);
        $this->get(route('admin.reservations.create', ['demande' => $request->id]))->assertOk()
            ->assertSee('<option value="'.$type->id.'" selected data-', false)
            ->assertSee('for="booking-mode-heure" hidden >', false);
    }

    public function test_the_room_list_and_the_booking_follow_the_chosen_type(): void
    {
        $this->travelTo('2026-10-19 10:00:00');
        $this->actingAs(User::factory()->create());
        $suite = $this->type(['name' => 'Suite', 'night_rate' => 75000]);
        $simple = $this->type(['name' => 'Simple', 'night_rate' => 20000]);
        $inSuite = Room::factory()->create(['number' => 'S1', 'room_type_id' => $suite->id, 'status' => RoomStatus::Disponible]);
        $inSimple = Room::factory()->create(['number' => 'C1', 'room_type_id' => $simple->id, 'status' => RoomStatus::Disponible]);

        $query = ['pricing_mode' => 'nuitee', 'check_in' => '2026-10-25', 'duration' => 1];
        $this->getJson(route('admin.rooms.available', [...$query, 'room_type_id' => $suite->id]))->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.id', $inSuite->id);
        $this->getJson(route('admin.rooms.available', $query))->assertOk()->assertJsonCount(2); // without a type: unchanged

        $form = ['new_phone' => '0711223344', 'pricing_mode' => 'nuitee', 'check_in' => '2026-10-25', 'duration' => 1, 'adults' => 2, 'children' => 0, 'status' => 'confirmee'];

        // A room of another type than the one chosen is refused, nothing created.
        $this->post(route('admin.reservations.store'), [...$form, 'room_type_id' => $suite->id, 'room_id' => $inSimple->id])
            ->assertSessionHasErrors(['room_id' => "La chambre N°C1 n'est pas du type de chambre choisi : choisissez une chambre de ce type."]);
        $this->assertSame(0, Reservation::count());

        $this->post(route('admin.reservations.store'), [...$form, 'room_type_id' => $suite->id, 'room_id' => $inSuite->id])->assertSessionHasNoErrors();
        $this->assertSame('75000.00', (string) Reservation::sole()->total_amount);

        // Older forms without the type still work.
        $this->post(route('admin.reservations.store'), [...$form, 'new_phone' => '0544332211', 'room_id' => $inSimple->id, 'check_in' => '2026-10-26'])->assertSessionHasNoErrors();
        $this->assertSame(2, Reservation::count());
    }

    // ---- Affectations ----------------------------------------------------------------------

    public function test_the_board_no_longer_greys_out_the_nuitee_before_22h(): void
    {
        $this->travelTo('2026-10-19 16:00:00');
        $this->actingAs(User::factory()->create());
        Room::factory()->create(['room_type_id' => $this->type(['night_rate' => 25000])->id, 'status' => RoomStatus::Disponible]);

        $this->get('/admin/rooms/board')->assertOk()
            ->assertSee('une entrée avant 22h00 compte pour la nuit de ce soir', false)
            ->assertDontSee('Les modes sans tarif pour ce type de chambre sont désactivés', false);
    }

    public function test_a_guest_request_shows_only_its_type_s_modes_and_accepts_an_early_nuitee(): void
    {
        $this->travelTo('2026-10-19 09:00:00');
        $type = $this->type(['hourly_rate' => 5000, 'night_rate' => 25000]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->post('/demande/chambre/'.$room->id, ['pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0, 'website' => '']);
        $request = StayRequest::sole();

        $this->travelTo('2026-10-19 21:30:00');
        $staff = User::factory()->create();
        $html = $this->actingAs($staff)->get('/admin/demandes/'.$request->id)->assertOk()->getContent();
        $this->assertStringContainsString('id="mode-heure"', $html);
        $this->assertStringContainsString('id="mode-nuitee"', $html);
        $this->assertStringNotContainsString('id="mode-journee"', $html);
        $this->assertStringNotContainsString('id="mode-sejour"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="mode-nuitee"[^>]*disabled/', $html);

        $this->post("/admin/demandes/{$request->id}/valider", [
            'pricing_mode' => 'nuitee', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'especes', 'amount' => 25000, 'received' => 25000]],
        ])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('2026-10-19 21:30', $stay->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 12:00', $stay->check_out->format('Y-m-d H:i'));
    }
}
