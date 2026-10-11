<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Réservations pages: messages, errors and confirmations as SweetAlert pop-ups.
 */
class SweetAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_pages_use_sweetalert_for_messages_errors_and_confirmations(): void
    {
        $this->travelTo('2026-10-11 10:00:00');
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id]);
        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-15 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->actingAs(User::factory()->create());

        // Flash message → pop-up data, no alert box.
        $this->post(route('admin.reservations.cancel', $booking))->assertSessionHas('status', 'Réservation annulée.');
        $this->withSession(['status' => 'Réservation annulée.'])->get(route('admin.reservations.index'))->assertOk()
            ->assertSee('sweetalert2.all.min.js', false)
            ->assertSee('<script type="application/json" id="swalFlash">', false)
            ->assertSee(substr(json_encode(['status' => 'Réservation annulée.']), 1, -1), false)
            ->assertDontSee('<div class="alert alert-success">', false);

        // Validation errors → pop-up data (escaped), no error box.
        $this->from(route('admin.reservations.create'))->post(route('admin.reservations.store'), [])->assertRedirect();
        $this->get(route('admin.reservations.create'))->assertOk()
            ->assertSee('"errors":[', false)
            ->assertDontSee('data-page-errors', false);

        // Confirmations through data-confirm, no window.confirm().
        $other = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-20 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->get(route('admin.reservations.edit', $other))->assertOk()
            ->assertSee('data-confirm-title="Annuler cette réservation ?"', false)
            ->assertDontSee('return confirm(', false);
    }

    public function test_other_pages_keep_their_alert_boxes(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['status' => 'Enregistré.'])->get(route('admin.guests.index'))->assertOk()
            ->assertSee('<div class="alert alert-success">Enregistré.</div>', false)
            ->assertDontSee('sweetalert2.all.min.js', false)
            ->assertDontSee('id="swalFlash"', false);
    }
}
