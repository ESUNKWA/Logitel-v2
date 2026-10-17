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
 * « dont réservations » on the home page and the figures dashboard (option B, same rule as the
 * Recettes): the manager's day/month figures and per-staff table, each receptionist's own till;
 * refreshed live; never the hotel's figures for a receptionist.
 */
class BookingShareFiguresTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $awa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-14 09:00:00');
        $type = RoomType::factory()->create(['hourly_rate' => 5000]);
        $this->manager = User::factory()->simpleUi()->create(['name' => 'Le Gérant']);
        $this->awa = User::factory()->reception()->simpleUi()->create(['name' => 'Awa Réception']);

        // Awa: an affectation (5 000) and a réservation deposit (6 000); the manager: a deposit (4 000).
        $room = Room::factory()->create(['room_type_id' => $type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs($this->awa)->post("/admin/rooms/{$room->id}/assign", ['pricing_mode' => 'heure', 'duration' => 1, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 5000]]])->assertSessionHasNoErrors();
        foreach ([[$this->awa, 6000], [$this->manager, 4000]] as [$user, $deposit]) {
            $this->actingAs($user)->post('/admin/reservations', [
                'guest_id' => Guest::factory()->create()->id, 'room_id' => Room::factory()->create(['room_type_id' => $type->id])->id,
                'pricing_mode' => 'heure', 'check_in' => '2026-10-14T14:00', 'duration' => 3, 'adults' => 1, 'children' => 0, 'status' => 'confirmee',
                'payments' => [['method' => 'orange_money', 'amount' => $deposit]],
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(3, Reservation::count());
    }

    public function test_the_manager_sees_the_booking_share_on_the_home_page(): void
    {
        $page = $this->actingAs($this->manager)->get('/admin')->assertOk()->assertViewIs('admin.home');
        $finance = $page->viewData('finance');
        $this->assertSame(15000.0, $finance['today']);
        $this->assertSame(10000.0, $finance['todayBooking']);
        $this->assertSame(10000.0, $finance['monthBooking']);
        $page->assertSee('dont réservations : <strong>10 000 F</strong>', false);

        // Per staff: Awa 11 000 of which 6 000 réservations; the manager 4 000, all réservations.
        $staff = collect($page->viewData('staffCash'))->keyBy('name');
        $this->assertSame([11000.0, 6000.0], [$staff['Awa Réception']['total'], $staff['Awa Réception']['booking']]);
        $this->assertSame([4000.0, 4000.0], [$staff['Le Gérant']['total'], $staff['Le Gérant']['booking']]);
        $page->assertSee('<th class="text-end">dont réservations</th>', false);

        // Same figure as the Recettes page.
        $this->assertSame(10000.0, $this->get(route('admin.revenue.index'))->viewData('byKind')['reservation']['total']);
    }

    public function test_the_figures_dashboard_shows_it_and_refreshes_it_live(): void
    {
        $this->manager->forceFill(['ui_mode' => 'complet'])->save();
        $this->actingAs($this->manager)->get('/admin/tableau-de-bord')->assertOk()
            ->assertSee('<span data-live-value="revenueTodayBooking">10 000</span>', false)
            ->assertSee('<span data-live-value="revenueMonthBooking">10 000</span>', false);

        $values = $this->getJson('/admin/chiffres-en-direct?blocs=home-figures&valeurs=1')->assertOk()->json('values');
        $this->assertSame('10 000', $values['revenueTodayBooking']);
        $this->assertSame('10 000', $values['revenueMonthBooking']);
        $this->assertStringContainsString('dont réservations : <strong>10 000 F</strong>',
            $this->getJson('/admin/chiffres-en-direct?blocs=home-figures')->json('blocks.home-figures'));
    }

    public function test_a_receptionist_sees_only_her_own_booking_share(): void
    {
        $page = $this->actingAs($this->awa)->get('/admin')->assertOk();
        $this->assertNull($page->viewData('finance'));
        $this->assertNull($page->viewData('staffCash'));
        $this->assertSame(11000.0, $page->viewData('myCash')['total']);
        $this->assertSame(6000.0, $page->viewData('myCash')['booking']); // not the manager's 4 000
        $page->assertSee('dont réservations : <strong>6 000 F</strong>', false)
            ->assertDontSee('10 000 F', false);

        // Live: her till only, no hotel values.
        $data = $this->getJson('/admin/chiffres-en-direct?blocs=my-cash,home-figures,staff-cash&valeurs=1')->assertOk()->json();
        $this->assertSame([], $data['values']);
        $this->assertArrayNotHasKey('staff-cash', $data['blocks']);
        $this->assertStringContainsString('dont réservations : <strong>6 000 F</strong>', $data['blocks']['my-cash']);
        $this->assertStringNotContainsString('10 000', $data['blocks']['home-figures'] ?? '');
    }
}
