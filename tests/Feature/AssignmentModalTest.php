<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A failed assignment comes back to the board with the modal reopened on the same room, the
 * user's input kept and the errors shown inside the modal (not behind it).
 */
class AssignmentModalTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        $this->travelTo('2026-10-08 10:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);

        return Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible, 'number' => 'MOD-1']);
    }

    private function submit(Room $room, array $data)
    {
        return $this->actingAs(User::factory()->create())
            ->from(route('admin.rooms.board'))
            ->followingRedirects()
            ->post("/admin/rooms/{$room->id}/assign", [
                'assign_room_id' => $room->id,
                'pricing_mode' => 'heure',
                'duration' => 3,
                'unit_rate' => 4500,
                'adults' => 2,
                'children' => 1,
                'payments' => [['method' => 'wave', 'amount' => 13500]], // paid in full (3 × 4500)
                ...$data,
            ]);
    }

    public function test_invalid_client_field_reopens_the_modal_with_input_and_inline_errors(): void
    {
        $room = $this->room();

        $this->submit($room, ['new_first_name' => 'Awa', 'new_phone' => str_repeat('7', 60), 'discount_value' => '1000', 'discount_reason' => 'Fidélité', 'payments' => [['method' => 'wave', 'amount' => 5000]]])
            ->assertOk()
            ->assertSee('data-reopen-room="'.$room->id.'"', false)
            // inline, under the field
            ->assertSee('Le texte de téléphone du client ne peut pas contenir plus de 50 caractères.')
            ->assertSee('is-invalid', false)
            // what the user typed is still there
            ->assertSee('name="new_first_name" value="Awa"', false)
            ->assertSee('name="duration" value="3"', false)
            ->assertSee('data-unit-rate-input readonly', false) // the rate is the room type's, not an input
            ->assertSee('name="discount_value" placeholder="0"', false)
            ->assertSee('value="1000"', false)
            ->assertSee('value="Fidélité"', false)
            ->assertSee('id="mode-heure" value="heure" data-mode-option checked', false)
            ->assertSee('name="payments[0][amount]" value="5000"', false)
            // not duplicated in a page-level alert behind the modal
            ->assertDontSee('data-page-errors', false);

        $this->assertSame(0, Reservation::count());
    }

    public function test_other_errors_are_listed_at_the_top_of_the_modal(): void
    {
        $room = $this->room();

        $this->submit($room, [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0102',
            'payments' => [['method' => 'especes', 'amount' => 99999]],
        ])
            ->assertSee('data-modal-errors', false)
            ->assertSee('dépasse le reste dû')
            ->assertSee('name="new_phone" value="0102"', false);
    }

    public function test_errors_stay_on_the_page_when_the_room_cannot_be_reopened(): void
    {
        $room = $this->room();
        // Someone else took the room in the meantime: it isn't assignable any more.
        $room->update(['status' => RoomStatus::Occupee]);

        $this->submit($room, ['new_first_name' => 'Awa'])
            ->assertSee('data-page-errors', false)
            ->assertSee('data-reopen-room=""', false);
    }

    public function test_a_normal_visit_does_not_reopen_the_modal(): void
    {
        $this->room();

        $this->actingAs(User::factory()->create())->get('/admin/rooms/board')
            ->assertOk()
            ->assertSee('data-reopen-room=""', false)
            ->assertDontSee('data-modal-errors', false);
    }
}
