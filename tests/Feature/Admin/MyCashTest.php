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
 * « Ma recette du jour »: each staff member sees what they cashed themselves, not the hotel's total.
 */
class MyCashTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private User $awa;

    private User $koffi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-08 10:00:00');
        $this->type = RoomType::factory()->create();
        $this->awa = User::factory()->reception()->simpleUi()->create(['name' => 'Awa Réception']);
        $this->koffi = User::factory()->reception()->simpleUi()->create(['name' => 'Koffi Réception']);
    }

    /**
     * A stay of $hours hours (5 000 F/h) cashed by $cashier at $paidAt.
     */
    private function cashed(User $cashier, int $hours, array $lines, string $paidAt = '2026-10-08 09:00'): Reservation
    {
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $stay = Reservation::factory()->create(['room_id' => $room->id, 'pricing_mode' => 'heure', 'unit_rate' => 5000,
            'check_in' => '2026-10-08 08:00', 'duration' => $hours, 'status' => 'confirmee', 'origin' => Reservation::ORIGIN_AFFECTATION]);
        $stay->update(['status' => ReservationStatus::Enregistree]);

        $this->actingAs($cashier);
        $this->travelTo($paidAt);
        $stay->invoice->settle($lines, now());
        $this->travelTo('2026-10-08 10:00:00');

        return $stay;
    }

    public function test_each_receptionist_sees_only_what_they_cashed_today(): void
    {
        $this->cashed($this->awa, 4, [['method' => 'especes', 'amount' => 15000, 'received' => 20000], ['method' => 'wave', 'amount' => 5000]]);
        $this->cashed($this->awa, 2, [['method' => 'orange_money', 'amount' => 10000]]);
        $this->cashed($this->awa, 1, [['method' => 'especes', 'amount' => 5000]], '2026-10-08 05:30'); // previous business day
        $this->cashed($this->koffi, 3, [['method' => 'especes', 'amount' => 15000]]);

        $this->actingAs($this->awa)->get('/admin')->assertOk()
            ->assertViewHas('myCash', fn ($c) => $c['total'] === 30000.0 && $c['count'] === 2 && $c['cash'] === 15000.0)
            ->assertViewHas('finance', null) // still no hotel-wide figures
            ->assertSeeInOrder(['Ma recette du jour', '30 000', '2 encaissements', 'Espèces', '15 000 F', 'Orange Money', '10 000 F', 'Wave', '5 000 F'])
            ->assertSee('Espèces à remettre')
            ->assertDontSee('Recette du mois');

        $this->actingAs($this->koffi)->get('/admin')->assertOk()
            ->assertViewHas('myCash', fn ($c) => $c['total'] === 15000.0 && $c['count'] === 1);
    }

    public function test_the_detail_page_lists_only_my_payments_and_previous_days(): void
    {
        $mine = $this->cashed($this->awa, 2, [['method' => 'especes', 'amount' => 10000, 'received' => 10000]]);
        $other = $this->cashed($this->koffi, 3, [['method' => 'wave', 'amount' => 15000]]);
        $yesterday = $this->cashed($this->awa, 1, [['method' => 'wave', 'amount' => 5000]], '2026-10-07 20:00');

        $this->actingAs($this->awa)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee($mine->invoice->number)
            ->assertDontSee($other->invoice->number)
            ->assertDontSee($yesterday->invoice->number)
            ->assertSeeInOrder(['Total encaissé', '10 000'])
            ->assertSee('À remettre en caisse');

        $this->get(route('admin.revenue.index', ['jour' => '2026-10-07']))->assertOk()
            ->assertSee($yesterday->invoice->number)
            ->assertDontSee($mine->invoice->number);

        // Invalid or future dates fall back to today.
        $this->get(route('admin.revenue.index', ['jour' => 'nimporte']))->assertOk()->assertSee($mine->invoice->number);
        $this->get(route('admin.revenue.index', ['jour' => '2030-01-01']))->assertOk()->assertSee($mine->invoice->number);
    }

    public function test_the_manager_sees_every_receptionists_takings(): void
    {
        $manager = User::factory()->simpleUi()->create(['name' => 'Le Gérant']);
        $this->cashed($this->awa, 2, [['method' => 'wave', 'amount' => 10000]]);
        $this->cashed($this->awa, 1, [['method' => 'especes', 'amount' => 3000, 'received' => 5000], ['method' => 'wave', 'amount' => 2000]]);
        $this->cashed($this->koffi, 3, [['method' => 'especes', 'amount' => 15000]]);
        $this->cashed($manager, 1, [['method' => 'especes', 'amount' => 5000]]);

        $response = $this->actingAs($manager)->get('/admin')->assertOk()
            ->assertViewHas('finance', fn ($f) => $f['today'] === 35000.0)
            ->assertViewHas('myCash', null)
            ->assertSeeInOrder(['Recettes du jour par réceptionniste', 'Réception', 'Réception', 'Le Gérant', '5 000 F'])
            ->assertSee('Koffi Réception')->assertSee('Awa Réception');
        $awa = collect($response->viewData('staffCash'))->firstWhere('name', 'Awa Réception');
        $this->assertSame(15000.0, $awa['total']);
        $this->assertSame(2, $awa['count']); // the mixed payment counts once
        $this->assertSame(['especes' => 3000.0, 'orange_money' => 0.0, 'wave' => 12000.0], $awa['byMethod']);

        // Full-mode dashboard too.
        $manager->forceFill(['ui_mode' => 'complet'])->save();
        $this->get('/admin')->assertOk()->assertSee('Recettes du jour par réceptionniste')->assertSee('Koffi Réception');
    }

    public function test_the_manager_reviews_every_payment_of_a_day_per_staff_member(): void
    {
        $manager = User::factory()->create();
        $awaStay = $this->cashed($this->awa, 2, [['method' => 'wave', 'amount' => 10000]]);
        $koffiStay = $this->cashed($this->koffi, 3, [['method' => 'especes', 'amount' => 15000]]);
        $old = $this->cashed($this->awa, 1, [['method' => 'wave', 'amount' => 5000]]);
        $old->invoice->payments()->update(['created_by' => null]); // recorded before authorship existed

        $this->actingAs($manager)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee($awaStay->invoice->number)->assertSee($koffiStay->invoice->number)
            ->assertSee('Non attribué (anciens paiements)')
            ->assertSeeInOrder(['Total encaissé', '30 000']);

        $this->get(route('admin.revenue.index', ['par' => $this->koffi->id]))->assertOk()
            ->assertSee($koffiStay->invoice->number)->assertDontSee($awaStay->invoice->number)
            ->assertSeeInOrder(['Total encaissé', '15 000']);
        $this->get(route('admin.revenue.index', ['par' => 'aucun']))->assertOk()
            ->assertSee($old->invoice->number)->assertDontSee($koffiStay->invoice->number);

        // A receptionist opening it only gets their own payments, whatever they ask for.
        $this->actingAs($this->awa)->get(route('admin.revenue.index', ['par' => $this->koffi->id]))->assertOk()
            ->assertSee($awaStay->invoice->number)->assertDontSee($koffiStay->invoice->number)->assertDontSee('Par réceptionniste');
    }

    public function test_full_mode_dashboard_shows_the_receptionists_own_takings(): void
    {
        $this->awa->forceFill(['ui_mode' => 'complet'])->save();
        $this->cashed($this->awa, 2, [['method' => 'wave', 'amount' => 10000]]);

        $this->actingAs($this->awa)->get('/admin')->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertSeeInOrder(['Ma recette du jour', '10 000'])
            ->assertViewMissing('revenueMonth');
    }

    public function test_staff_who_dont_cash_have_no_till(): void
    {
        $viewer = User::factory()->withoutRole()->simpleUi()->create();

        $this->actingAs($viewer)->get('/admin')->assertOk()->assertViewHas('myCash', null)->assertDontSee('Ma recette du jour');
        $this->get(route('admin.revenue.index'))->assertForbidden();
    }
}
