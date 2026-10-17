<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No export / print buttons on phones (client request, 2026-10-17): each one carries
 * .phone-hidden (hidden under 860 px by admin.css); wider screens keep them. The receipt preview
 * shown after a creation isn't opened on phones (admin.js).
 */
class PhoneHiddenActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_export_and_print_button_is_hidden_on_phones(): void
    {
        $this->travelTo('2026-10-17 10:00:00');
        $this->actingAs(User::factory()->create());
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id, 'status' => RoomStatus::Disponible]);

        // The receipt preview after an affectation: its print button.
        $this->post("/admin/rooms/{$room->id}/assign", ['pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]]])->assertSessionHasNoErrors();
        $stay = Reservation::sole();
        $this->get('/admin/rooms/board')->assertOk()
            ->assertSee('<button type="button" class="btn btn-primary phone-hidden" data-receipt-print>', false);

        $reprint = 'class="btn btn-outline-primary phone-hidden" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>';
        $this->get(route('admin.affectations.show', $stay))->assertOk()->assertSee($reprint, false);
        $this->get(route('admin.invoices.show', $stay->invoice))->assertOk()->assertSee($reprint, false);

        $booking = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'nuitee', 'unit_rate' => 75000,
            'check_in' => '2026-10-20 22:00', 'duration' => 1, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_RESERVATION]);
        $this->get(route('admin.reservations.edit', $booking))->assertOk()->assertSee($reprint, false);

        $this->get(route('admin.revenue.index'))->assertOk()
            ->assertSee('class="btn btn-outline-primary phone-hidden"><i class="far fa-file-excel"></i> Exporter (Excel)</a>', false)
            ->assertSee('class="btn btn-primary phone-hidden" onclick="window.print()"><i class="far fa-print"></i> Imprimer</button>', false);

        $this->get(route('admin.stay-requests.qr'))->assertOk()
            ->assertSee('class="btn btn-primary phone-hidden" onclick="window.print()"><i class="fas fa-print"></i> Imprimer l\'affiche</button>', false)
            ->assertSee('class="btn btn-outline-primary phone-hidden"><i class="fas fa-download"></i> Télécharger le QR code (SVG)</a>', false);

        // Rule present in the stylesheet, inside the phone breakpoint.
        $css = file_get_contents(public_path('app/css/admin.css'));
        $phone = substr($css, strpos($css, '@media (max-width: 860px) {'));
        $this->assertStringContainsString('.phone-hidden { display: none !important; }', $phone);
    }

    public function test_on_phones_the_affectations_list_keeps_three_filters_unless_another_is_in_use(): void
    {
        $this->actingAs(User::factory()->create());
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create()->id]);

        $html = $this->get(route('admin.affectations.index'))->assertOk()->getContent();
        // Kept on phones: Entrée, Mode, Paiement (no marker on them).
        foreach (['f-periode', 'f-mode', 'f-paiement'] as $id) {
            $this->assertMatchesRegularExpression('#<div>\s*<label class="form-label" for="'.$id.'">#', $html);
        }
        // Wider screens only: Client, Chambre, Type, Origine, Trier par, Départs dépassés.
        $this->assertStringContainsString('<div class="filter-bar__search filter-bar__wide-only">', $html);
        foreach (['f-chambre', 'f-type', 'f-origine', 'f-tri'] as $id) {
            $this->assertMatchesRegularExpression('#<div class="filter-bar__wide-only">\s*<label class="form-label" for="'.$id.'">#', $html);
        }
        $this->assertStringContainsString('<div class="form-check mb-0 filter-bar__wide-only">', $html);

        // A filter in use stays visible on phones (e.g. « Dernier séjour » from the board filters by room).
        $html = $this->get(route('admin.affectations.index', ['chambre' => $room->id, 'q' => 'Awa', 'depasse' => 1]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<div class="">\s*<label class="form-label" for="f-chambre">#', $html);
        $this->assertStringContainsString('<div class="filter-bar__search ">', $html);
        $this->assertStringContainsString('<div class="form-check mb-0 ">', $html);
        $this->assertMatchesRegularExpression('#<div class="filter-bar__wide-only">\s*<label class="form-label" for="f-type">#', $html);
    }
}
