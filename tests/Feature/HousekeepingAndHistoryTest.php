<?php

namespace Tests\Feature;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HousekeepingAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
    }

    private function stayInProgress(Room $room): Reservation
    {
        $stay = Reservation::factory()->create([
            'room_id' => $room->id, 'pricing_mode' => PricingMode::Heure, 'check_in' => '2026-10-08 09:00',
            'duration' => 3, 'status' => ReservationStatus::Confirmee, 'origin' => Reservation::ORIGIN_AFFECTATION,
        ]);
        $stay->update(['status' => ReservationStatus::Enregistree]);

        return $stay;
    }

    // --- 1. Cancelling a stay in progress frees the room -----------------------------

    public function test_cancelling_a_stay_in_progress_frees_the_room(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $stay = $this->stayInProgress($room);
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);

        $this->actingAs(User::factory()->create())->post("/admin/affectations/{$stay->id}/cancel")->assertRedirect();

        $this->assertSame(ReservationStatus::Annulee, $stay->fresh()->status);
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status); // free at once (client request)
        $this->assertTrue(Room::query()->whereKey($room->id)->availableBetween(now(), now()->addHour())->exists(), 'The cancelled stay no longer holds the room.');
    }

    public function test_cancelling_a_booking_before_arrival_leaves_the_room_alone(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'check_in' => '2026-10-12', 'status' => ReservationStatus::Confirmee]);

        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$booking->id}/cancel");

        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
    }

    public function test_cancelling_does_not_override_a_room_set_out_of_service_meanwhile(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $stay = $this->stayInProgress($room);
        $room->refresh()->update(['status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->create())->post("/admin/affectations/{$stay->id}/cancel");

        $this->assertSame(RoomStatus::HorsService, $room->fresh()->status);
    }

    public function test_check_out_does_not_override_a_room_set_out_of_service_meanwhile(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $stay = $this->stayInProgress($room);
        $room->refresh()->update(['status' => RoomStatus::HorsService]);

        $this->actingAs(User::factory()->create())->post("/admin/affectations/{$stay->id}/check-out");

        $this->assertSame(ReservationStatus::Terminee, $stay->fresh()->status);
        $this->assertSame(RoomStatus::HorsService, $room->fresh()->status);
    }

    // --- 2. "Chambre prête" -------------------------------------------------------------

    public function test_a_cleaned_room_is_made_available_in_one_click(): void
    {
        $room = Room::factory()->create(['number' => 'NET-1', 'status' => RoomStatus::Nettoyage]);
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get('/admin/rooms/board')->assertSee(route('admin.rooms.ready', $room), false)->assertSee('Chambre prête');
        $this->actingAs($reception)->get('/admin/rooms?status=nettoyage')->assertSee(route('admin.rooms.ready', $room), false);

        $this->actingAs($reception)->post("/admin/rooms/{$room->id}/ready")
            ->assertRedirect()->assertSessionHas('status', 'Chambre N°NET-1 prête : elle est de nouveau disponible.');
        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
    }

    public function test_only_rooms_in_cleaning_can_be_made_ready(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Occupee]);

        $this->actingAs(User::factory()->create())->post("/admin/rooms/{$room->id}/ready")->assertSessionHasErrors('room');
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
        $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertDontSee(route('admin.rooms.ready', $room), false);
    }

    public function test_making_a_room_ready_needs_the_room_edit_permission(): void
    {
        $role = Role::create(['name' => 'Lecture seule']);
        $role->syncPermissions(['rooms.view']);
        $room = Room::factory()->create(['status' => RoomStatus::Nettoyage]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))->post("/admin/rooms/{$room->id}/ready")->assertForbidden();
        $this->assertSame(RoomStatus::Nettoyage, $room->fresh()->status);
    }

    // --- 3. Financial history is protected ------------------------------------------------

    public function test_a_guest_with_stays_cannot_be_deleted(): void
    {
        $guest = Guest::factory()->create();
        Reservation::factory()->create(['guest_id' => $guest->id]);
        $invoices = Invoice::count();

        $this->actingAs(User::factory()->create())->delete("/admin/guests/{$guest->id}")
            ->assertSessionHasErrors(['delete' => "Ce client a 1 séjour(s) ou réservation(s) : il ne peut pas être supprimé, pour conserver l'historique et les factures."]);

        $this->assertModelExists($guest);
        $this->assertSame($invoices, Invoice::count());

        $newcomer = Guest::factory()->create();
        $this->actingAs(User::factory()->create())->delete("/admin/guests/{$newcomer->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($newcomer);
    }

    public function test_a_room_with_stays_cannot_be_deleted(): void
    {
        $room = Room::factory()->create(['number' => 'HIS-1']);
        Reservation::factory()->create(['room_id' => $room->id]);

        $this->actingAs(User::factory()->create())->delete("/admin/rooms/{$room->id}")
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($room);

        $unused = Room::factory()->create();
        $this->actingAs(User::factory()->create())->delete("/admin/rooms/{$unused->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($unused);
    }

    public function test_a_room_type_with_rooms_cannot_be_deleted(): void
    {
        $type = RoomType::factory()->create(['name' => 'Suite']);
        Room::factory()->create(['room_type_id' => $type->id]);

        $this->actingAs(User::factory()->create())->delete("/admin/room-types/{$type->id}")
            ->assertSessionHasErrors('delete');
        $this->assertModelExists($type);

        $empty = RoomType::factory()->create();
        $this->actingAs(User::factory()->create())->delete("/admin/room-types/{$empty->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);
    }

    public function test_the_database_itself_refuses_to_cascade_away_history(): void
    {
        $stay = Reservation::factory()->create();

        foreach ([fn () => DB::table('guests')->where('id', $stay->guest_id)->delete(),
            fn () => DB::table('rooms')->where('id', $stay->room_id)->delete(),
            fn () => DB::table('room_types')->where('id', $stay->room->room_type_id)->delete()] as $rawDelete) {
            try {
                $rawDelete();
                $this->fail('The foreign key should restrict the delete.');
            } catch (QueryException) {
                // expected
            }
        }

        $this->assertModelExists($stay);
        $this->assertNotNull($stay->fresh()->invoice);
    }
}
