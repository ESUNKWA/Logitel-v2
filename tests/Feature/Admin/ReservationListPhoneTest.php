<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réservations list (client request, 2026-11-10): the client's phone number in its own column,
 * tap to call — on phones and on PC.
 */
class ReservationListPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function booking(Guest $guest): Reservation
    {
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        return Reservation::factory()->create(['guest_id' => $guest->id, 'room_id' => $room->id, 'pricing_mode' => 'nuitee',
            'check_in' => now()->addDays(2)->setTime(22, 0), 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
    }

    public function test_the_client_s_phone_shows_in_its_own_column(): void
    {
        $this->booking(Guest::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné', 'phone' => '07 11 22 33 44']));
        $this->booking(Guest::factory()->create(['first_name' => 'Sans', 'last_name' => 'Numéro', 'phone' => '']));

        $html = $this->actingAs(User::factory()->create())->get(route('admin.reservations.index'))->assertOk()
            ->assertSeeInOrder(['<th>Client</th>', '<th>Téléphone</th>', '<th>Chambre</th>'], false)
            // Shown by pairs (client request, 2026-11-10); the link and the table search keep the digits.
            ->assertSee('<a href="tel:0711223344"><i class="far fa-phone-alt"></i> 07 11 22 33 44</a>', false)
            ->assertSee('data-search="07 11 22 33 44 0711223344"', false)
            ->assertDontSee('cell-show-sm', false) // shown on PC too
            ->getContent();
        $this->assertStringContainsString('<td class="text-nowrap" data-search=""> — </td>', $html);
    }

    public function test_the_receptionist_sees_it_too_and_the_empty_list_still_spans_the_table(): void
    {
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.reservations.index'))->assertOk()
            ->assertSee('<th>Téléphone</th>', false)
            ->assertSee('Aucune réservation à affecter.');
    }
}
