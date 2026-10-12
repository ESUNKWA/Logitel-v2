<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Live money on the home page / dashboard: a payment recorded by one staff member reaches the
 * other users' screens without reloading — each only gets what they may see.
 */
class LiveFiguresTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = '/admin/chiffres-en-direct?blocs=my-cash,home-figures,staff-cash&valeurs=1';

    private RoomType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-12 10:00:00');
        $this->type = RoomType::factory()->create();
    }

    private function cashed(User $cashier, int $amount, string $method = 'wave'): void
    {
        $this->actingAs($cashier);
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 1000,
            'check_in' => now()->subHour(), 'duration' => $amount / 1000, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);
        $stay->invoice->settle([['method' => $method, 'amount' => $amount]], now());
    }

    public function test_a_payment_by_a_receptionist_reaches_the_manager_without_reloading(): void
    {
        $manager = User::factory()->simpleUi()->create(['name' => 'Le Gérant']);
        $awa = User::factory()->reception()->create(['name' => 'Awa Réception']);
        $this->cashed($awa, 10000);

        // The manager's page is open with these figures…
        $this->actingAs($manager)->get('/admin')->assertOk()
            ->assertSee('data-live-block="home-figures"', false)
            ->assertSee('data-live-block="staff-cash"', false)
            ->assertSee('data-live-url="'.route('admin.live-figures').'"', false);

        // …Awa records another payment, the next refresh carries the new totals.
        $this->cashed($awa, 5000, 'especes');
        $data = $this->actingAs($manager)->getJson(self::ALL)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

        $this->assertStringContainsString('15 000', $data['blocks']['home-figures']);       // recette du jour
        $this->assertStringContainsString('Awa Réception', $data['blocks']['staff-cash']);
        $this->assertStringContainsString('15 000 F', $data['blocks']['staff-cash']);
        $this->assertArrayNotHasKey('my-cash', $data['blocks']);                            // the manager has no till of their own
        $this->assertSame(['revenueToday' => '15 000', 'revenueMonth' => '15 000', 'monthlyRevenue' => '15 000', 'outstandingAmount' => '0'], $data['values']);
    }

    public function test_a_receptionist_gets_their_own_till_and_never_the_hotel_figures(): void
    {
        $awa = User::factory()->reception()->simpleUi()->create();
        $koffi = User::factory()->reception()->create();
        $this->cashed($awa, 10000);
        $this->actingAs($awa)->get('/admin')->assertOk()->assertSee('data-live-block="my-cash"', false);

        $this->cashed($koffi, 7000);
        $this->cashed($awa, 3000);
        $data = $this->actingAs($awa)->getJson(self::ALL)->assertOk()->json();

        $this->assertStringContainsString('13 000', $data['blocks']['my-cash']);
        $this->assertStringNotContainsString('20 000', $data['blocks']['my-cash']);
        $this->assertArrayNotHasKey('staff-cash', $data['blocks']);
        $this->assertStringNotContainsString('Recette du jour', $data['blocks']['home-figures']);
        $this->assertStringContainsString('Chambres occupées', $data['blocks']['home-figures']);
        $this->assertSame([], $data['values']);
    }

    public function test_the_dashboard_figures_are_marked_for_refresh_and_unknown_blocks_ignored(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertOk()
            ->assertSee('data-live-value="revenueToday"', false)
            ->assertSee('data-live-value="outstandingAmount"', false)
            ->assertSee('data-live-block="staff-cash"', false);

        $this->getJson('/admin/chiffres-en-direct?blocs=nimporte,../secret')->assertOk()->assertExactJson(['blocks' => [], 'values' => []]);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/admin/chiffres-en-direct?blocs=home-figures')->assertRedirect(route('admin.login'));
    }
}
