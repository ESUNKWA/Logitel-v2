<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Daytime: affectations here use the hourly mode (a nuitée only starts at 22:00).
        $this->travelTo('2026-10-07 10:00:00');
    }

    private function occupiedRoom(): Room
    {
        $room = Room::factory()->create(['number' => 'OCC-1', 'status' => RoomStatus::Disponible]);
        $guest = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);

        Reservation::factory()->create([
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(3)->toDateString(),
            'status' => ReservationStatus::Confirmee,
        ])->update(['status' => ReservationStatus::Enregistree]);

        return $room->refresh();
    }

    public function test_occupied_filter_lists_only_occupied_rooms_without_the_guest_name(): void
    {
        $this->occupiedRoom();
        Room::factory()->create(['number' => 'LIB-2', 'status' => RoomStatus::Disponible]);

        $this->actingAs(User::factory()->reception()->create())
            ->get('/admin/rooms?status=occupee')
            ->assertOk()
            ->assertSee('OCC-1')
            ->assertDontSee('Awa Koné')                     // occupied rooms never show the guest's name
            ->assertSee(today()->addDays(3)->format('d/m/Y'))
            ->assertDontSee('LIB-2');
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        Room::factory()->create(['number' => 'CH-303']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/rooms?status=<script>')
            ->assertOk()
            ->assertSee('CH-303')
            ->assertDontSee('<script>', false);
    }

    public function test_guest_name_is_hidden_without_reservation_access(): void
    {
        $this->occupiedRoom();
        $role = Role::create(['name' => 'Gouvernante']);
        $role->syncPermissions([Permission::RoomsView->value]);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))
            ->get('/admin/rooms?status=occupee')
            ->assertOk()
            ->assertSee('OCC-1')
            ->assertDontSee('Awa Koné');
    }

    private function assign(Room $room, string $mode = 'heure', int $duration = 2): void
    {
        $this->actingAs(User::factory()->reception()->create())->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Moussa',
            'new_last_name' => 'Traoré',
            'new_phone' => '0700000000',
            'pricing_mode' => $mode,
            'duration' => $duration,
            'adults' => 1,
            'children' => 0,
            // paid in full, at the room type's rate (the only one billed)
            'payments' => [['method' => 'wave', 'amount' => (float) PricingMode::from($mode)->rateFor($room->roomType) * $duration]],
        ])->assertRedirect(route('admin.rooms.board'))->assertSessionHasNoErrors();
    }

    /**
     * A reservation made through the reservations module (not the board) covering tonight.
     */
    private function reservedTonight(Room $room): Reservation
    {
        return Reservation::factory()->guaranteed()->create([
            'room_id' => $room->id,
            'guest_id' => Guest::factory()->create(['first_name' => 'Moussa', 'last_name' => 'Traoré'])->id,
            'check_in' => today()->toDateString(),
            'check_out' => today()->addDays(2)->toDateString(),
            'status' => ReservationStatus::Confirmee,
        ]);
    }

    public function test_assigning_checks_the_guest_in_immediately(): void
    {
        $room = Room::factory()->create(['number' => 'AFF-1', 'status' => RoomStatus::Disponible]);
        $this->assign($room);

        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
        $this->assertSame(ReservationStatus::Enregistree, $room->reservations()->first()->status);
        $this->assertNotNull($room->reservations()->first()->invoice);

        $user = User::factory()->reception()->create();
        $this->actingAs($user)->get('/admin/rooms?status=disponible')->assertOk()->assertDontSee('<td>AFF-1</td>', false);
        $this->actingAs($user)->get('/admin/rooms?status=occupee')->assertOk()->assertSee('<td>AFF-1</td>', false)->assertDontSee('Moussa Traoré');
        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()->assertDontSee(route('admin.rooms.assign', $room));
    }

    public function test_assigning_for_today_is_refused_for_a_room_that_is_not_physically_free(): void
    {
        foreach ([RoomStatus::Nettoyage, RoomStatus::HorsService] as $status) {
            $room = Room::factory()->create(['status' => $status]);

            $this->actingAs(User::factory()->reception()->create())->post("/admin/rooms/{$room->id}/assign", [
                'new_first_name' => 'Moussa',
                'new_last_name' => 'Traoré',
                'new_phone' => '0700000000',
                'pricing_mode' => 'heure',
                'duration' => 1,
                'adults' => 1,
                'children' => 0,
                'payments' => [['method' => 'wave', 'amount' => 5000]], // the type's hourly rate
            ])->assertSessionHasErrors('pricing_mode');

            $this->assertSame($status, $room->fresh()->status);
            $this->assertSame(0, $room->reservations()->count());
        }
    }

    public function test_room_reserved_for_tonight_leaves_the_available_list(): void
    {
        $room = Room::factory()->create(['number' => 'RES-1', 'status' => RoomStatus::Disponible]);
        $this->reservedTonight($room);

        $user = User::factory()->reception()->create();

        $this->assertFalse(Room::query()->availableNow()->whereKey($room->id)->exists());
        $this->actingAs($user)->get('/admin/rooms?status=disponible')->assertOk()->assertDontSee('<td>RES-1</td>', false);
        $this->actingAs($user)->get('/admin/rooms?status=reservee')->assertOk()->assertSee('<td>RES-1</td>', false)->assertSee('Moussa Traoré');
        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()
            ->assertDontSee(route('admin.rooms.assign', $room))
            ->assertSee('Réservée');
    }

    public function test_room_reserved_for_a_later_date_stays_free_tonight(): void
    {
        $room = Room::factory()->create(['number' => 'FUT-1', 'status' => RoomStatus::Disponible]);
        Reservation::factory()->guaranteed()->create([
            'room_id' => $room->id,
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(7)->toDateString(),
            'status' => ReservationStatus::Confirmee,
        ]);

        $this->assertSame(RoomStatus::Disponible, $room->fresh()->status);
        $this->assertTrue(Room::query()->availableNow()->whereKey($room->id)->exists());

        $this->actingAs(User::factory()->reception()->create())->get('/admin/rooms/board')->assertOk()
            ->assertSee(route('admin.rooms.assign', $room))
            ->assertSee('Réservée à partir du '.today()->addDays(5)->format('d/m/Y'));
    }

    public function test_cancelling_a_reservation_makes_the_room_available_again(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Disponible]);
        $reservation = $this->reservedTonight($room);

        $this->actingAs(User::factory()->create())->post("/admin/reservations/{$reservation->id}/cancel");

        $this->assertTrue(Room::query()->availableNow()->whereKey($room->id)->exists());
    }

    public function test_dashboard_only_counts_free_rooms_as_available(): void
    {
        $this->assign(Room::factory()->create(['status' => RoomStatus::Disponible]));
        $this->reservedTonight(Room::factory()->create(['status' => RoomStatus::Disponible]));
        Room::factory()->create(['status' => RoomStatus::HorsService]);
        Room::factory()->create(['status' => RoomStatus::Disponible]);

        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertViewHas('availableRooms', 1)
            ->assertViewHas('occupiedRooms', 1)
            ->assertViewHas('reservedRooms', 1);
    }

    public function test_occupied_rooms_show_entry_and_exit_times(): void
    {
        $room = Room::factory()->create(['number' => 'OCC-T', 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create([
            'room_id' => $room->id,
            'pricing_mode' => PricingMode::Heure,
            'check_in' => '2026-10-07 08:15',
            'duration' => 3,
            'status' => ReservationStatus::Confirmee,
        ]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        $user = User::factory()->reception()->create();

        $guest = $stay->guest->fullName();

        $this->actingAs($user)->get('/admin/rooms/board')->assertOk()
            ->assertSeeInOrder(['N°OCC-T', 'Entrée', '07/10 08h15', 'Sortie', '07/10 11h15'], false)
            ->assertDontSee($guest)
            ->assertSee(route('admin.affectations.show', $stay));

        $this->actingAs($user)->get('/admin/rooms?status=occupee')->assertOk()
            ->assertSeeInOrder(['Entrée le 07/10/2026 à 08h15', 'Sortie le 07/10/2026 à 11h15'], false)
            ->assertDontSee('<th>Client</th>', false)
            ->assertDontSee($guest);

        // "Toutes": the Client column exists (for reserved rooms) but stays empty for occupied ones
        $this->actingAs($user)->get('/admin/rooms')->assertOk()
            ->assertSee('<th>Client</th>', false)
            ->assertDontSee($guest);
    }
}
