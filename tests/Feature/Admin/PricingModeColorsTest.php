<?php

namespace Tests\Feature\Admin;

use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each pricing mode has its own colour and icon wherever staff pick or read it: the mode
 * selectors (board modal, réservation form), badges on lists and file pages, board prices.
 * The form fields themselves (name, value, data-*) are unchanged.
 */
class PricingModeColorsTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-17 10:00:00');
        $this->type = RoomType::factory()->create(['name' => 'Confort']);
        $this->actingAs(User::factory()->create());
    }

    public function test_every_mode_has_an_icon(): void
    {
        $this->assertSame(['fa-clock', 'fa-sun', 'fa-moon', 'fa-calendar-alt'],
            array_map(fn (PricingMode $m) => $m->icon(), PricingMode::cases()));
    }

    public function test_the_assignment_modal_colours_each_mode_button(): void
    {
        Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);

        $html = $this->get('/admin/rooms/board')->assertOk()->getContent();

        foreach (PricingMode::cases() as $mode) {
            $this->assertStringContainsString('name="pricing_mode" id="mode-'.$mode->value.'" value="'.$mode->value.'" data-mode-option', $html);
            $this->assertStringContainsString('<label class="btn mode-btn mode--'.$mode->value.'" for="mode-'.$mode->value.'"><i class="fas '.$mode->icon().'" aria-hidden="true"></i> '.e($mode->getLabel()).'</label>', $html);
            // Prices on the free card and in the rate grid carry the mode's icon too.
            $this->assertStringContainsString('<i class="fas '.$mode->icon().' mode-icon mode--'.$mode->value.'" aria-hidden="true"></i>', $html);
        }
        $this->assertStringNotContainsString('btn-outline-primary" for="mode-', $html);
    }

    public function test_the_reservation_form_colours_each_mode_button(): void
    {
        $html = $this->get(route('admin.reservations.create'))->assertOk()->getContent();

        foreach (PricingMode::cases() as $mode) {
            $this->assertStringContainsString('id="booking-mode-'.$mode->value.'" value="'.$mode->value.'" data-booking-mode data-availability-trigger', $html);
            $this->assertStringContainsString('<label class="btn mode-btn mode--'.$mode->value.'" for="booking-mode-'.$mode->value.'"><i class="fas '.$mode->icon().'" aria-hidden="true"></i>', $html);
        }
    }

    public function test_lists_and_file_pages_show_a_mode_badge(): void
    {
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Occupee]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'journee', 'unit_rate' => 15000,
            'check_in' => now()->subHour(), 'duration' => 1, 'status' => 'enregistree', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $booking = Reservation::factory()->create(['room_id' => Room::factory()->create(['room_type_id' => $this->type->id])->id,
            'pricing_mode' => 'nuitee', 'unit_rate' => 25000, 'check_in' => '2026-10-20 22:00', 'duration' => 2, 'status' => 'confirmee',
            'origin' => Reservation::ORIGIN_RESERVATION]);

        $journee = '<span class="mode-badge mode--journee"><i class="fas fa-sun" aria-hidden="true"></i> Journée (6 h)</span>';
        $nuitee = '<span class="mode-badge mode--nuitee"><i class="fas fa-moon" aria-hidden="true"></i> Nuitée</span>';

        $this->get(route('admin.affectations.index'))->assertOk()->assertSee($journee, false);
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertSee($journee, false);
        $this->get(route('admin.invoices.show', $stay->invoice))->assertOk()->assertSee($journee, false);
        $this->get(route('admin.rooms.index'))->assertOk()->assertSee($journee, false);
        $this->get(route('admin.reservations.index'))->assertOk()->assertSee($nuitee, false);
    }
}
