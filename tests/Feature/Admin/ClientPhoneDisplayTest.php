<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The client's phone number is shown by pairs wherever it appears (client request, 2026-11-10:
 * « 05 46 53 34 14 »); call links and table searches keep the bare digits.
 */
class ClientPhoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_s_phone_is_grouped_by_pairs_in_the_application(): void
    {
        $this->travelTo('2026-10-14 10:00:00');
        $guest = Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '0546533414']);
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['guest_id' => $guest->id, 'room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 1000,
            'duration' => 2, 'check_in' => now()->subHour(), 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.affectations.index'))->assertOk()
            ->assertSee('<div class="small text-muted">05 46 53 34 14</div>', false)
            ->assertSee('data-search="Awa Koné 0546533414 05 46 53 34 14"', false);
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertSee('Awa Koné — 05 46 53 34 14');
        $this->get(route('admin.guests.index'))->assertOk()
            ->assertSee('<td data-search="0546533414 05 46 53 34 14">05 46 53 34 14</td>', false);
        $this->get(route('admin.front-desk.departures'))->assertOk()
            ->assertSee('<a href="tel:0546533414">05 46 53 34 14</a>', false);
        $this->get(route('admin.front-desk.search', ['q' => 'Koné']))->assertOk()
            ->assertSee('<a href="tel:0546533414">05 46 53 34 14</a>', false);
    }
}
