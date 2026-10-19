<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The affectation form shows the entry date and time — now — right above « Départ prévu »
 * (client request, 2026-10-19). It's display only: the stay still starts at the server's time when saved, a
 * forged entry time is ignored.
 */
class EntryTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_board_modal_shows_the_entry_date_and_time_read_only(): void
    {
        $this->travelTo('2026-10-19 14:07:00');
        $this->actingAs(User::factory()->create());
        Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        $this->get('/admin/rooms/board')->assertOk()
            // In the « Règlement » box, the line right above « Départ prévu ».
            ->assertSeeInOrder(['Règlement', '<div class="assign-pay__total">', 'Entrée : <span data-entry-time>19/10/2026 à 14h07</span>', '<div data-stay-preview>'], false)
            ->assertDontSee('name="check_in"', false);
    }

    public function test_the_stay_starts_at_save_time_whatever_is_sent(): void
    {
        $this->travelTo('2026-10-19 14:07:00');
        $this->actingAs(User::factory()->create());
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        $this->travel(3)->minutes(); // the form stayed open a while
        $this->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 2, 'children' => 0,
            'check_in' => '2026-10-18 08:00', 'entry' => '2026-10-18 08:00',
            'payments' => [['method' => 'especes', 'amount' => 10000, 'received' => 10000]],
        ])->assertSessionHasNoErrors();

        $stay = Reservation::sole();
        $this->assertSame('2026-10-19 14:10', $stay->check_in->format('Y-m-d H:i'));
        $this->assertSame('2026-10-19 16:10', $stay->check_out->format('Y-m-d H:i'));
    }

    public function test_the_guest_request_validation_form_shows_it_too(): void
    {
        $this->travelTo('2026-10-19 09:30:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000, 'is_active' => true]);
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->post('/demande/chambre/'.$room->id, ['pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0, 'website' => '']);

        $this->actingAs(User::factory()->create())->get('/admin/demandes/'.StayRequest::sole()->id)->assertOk()
            ->assertSeeInOrder(['Règlement', 'Entrée : <span data-entry-time>19/10/2026 à 09h30</span>', '<div data-stay-preview>'], false);
    }
}
