<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\DutyDay;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\FinanceStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * « Prise de service » (client request, 2026-10-25): one receptionist per business day, who must
 * take the service before working; every sale of the day (payments, affectations) is attributed
 * to her — including what the outgoing colleague or the Gérant cashed before she arrived — while
 * « saisi par » keeps who typed it. The Gérant never takes it and may designate / correct a day.
 */
class DutyTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $type;

    private User $awa;

    private User $fatou;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hotel.duty_required' => true]);
        $this->travelTo('2026-10-08 10:00:00'); // business day of 08/10 (06:00 → 05:59)
        $this->type = RoomType::factory()->create(['name' => 'Confort', 'hourly_rate' => 3000]);
        $this->awa = User::factory()->reception()->simpleUi()->create(['name' => 'Awa Sortante']);
        $this->fatou = User::factory()->reception()->simpleUi()->create(['name' => 'Fatou Du Jour']);
        $this->manager = User::factory()->simpleUi()->create(['name' => 'Le Gérant']);
    }

    /** An affectation (2 h, 6 000 F paid by Wave) made from the board by `$by`. */
    private function sell(User $by): Reservation
    {
        $room = Room::factory()->create(['room_type_id' => $this->type->id, 'status' => RoomStatus::Disponible]);
        $this->actingAs($by)->post("/admin/rooms/{$room->id}/assign", [
            'pricing_mode' => 'heure', 'duration' => 2, 'adults' => 1, 'children' => 0,
            'payments' => [['method' => 'wave', 'amount' => 6000]],
        ])->assertSessionHasNoErrors();

        return Reservation::query()->where('room_id', $room->id)->sole();
    }

    private function deferFor(User $user): void
    {
        $this->actingAs($user)->post(route('admin.duty.defer'))->assertRedirect(route('admin.dashboard'));
    }

    // ---- Mandatory ------------------------------------------------------------------------

    public function test_a_receptionist_must_take_the_service_before_working(): void
    {
        $this->actingAs($this->fatou);

        foreach (['/admin', '/admin/rooms/board', '/admin/affectations', '/admin/recettes'] as $page) {
            $this->get($page)->assertRedirect(route('admin.duty.show'));
        }
        $this->get(route('admin.duty.show'))->assertOk()
            ->assertSee('Prise de service')->assertSee('Journée du <strong>Jeudi 08/10/2026</strong>', false)
            ->assertSee('Je prends mon service')->assertSee('Je termine la journée précédente');
        // Still open: profile, logout, and background polling (the alarm keeps ringing).
        $this->get('/admin/profil')->assertOk();
        $this->getJson(route('admin.alerts.checkouts'))->assertOk();

        $this->post(route('admin.duty.take'))->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'Vous avez pris le service de la journée du 08/10/2026. Toutes les ventes de la journée vous sont attribuées.');

        $duty = DutyDay::sole();
        $this->assertSame($this->fatou->id, $duty->user_id);
        $this->assertSame('2026-10-08', $duty->business_date->toDateString());
        $this->get('/admin')->assertOk()->assertSee('De service :')->assertSee('Fatou Du Jour');
        $this->get('/admin/rooms/board')->assertOk();
    }

    public function test_the_manager_is_never_asked_and_cannot_take_the_service(): void
    {
        $this->actingAs($this->manager)->get('/admin')->assertOk()->assertSee('Personne de service');
        $this->get(route('admin.duty.show'))->assertRedirect(route('admin.duty.index'));
        $this->post(route('admin.duty.take'))->assertForbidden();
        $this->assertSame(0, DutyDay::count());
    }

    public function test_once_a_colleague_holds_the_day_the_others_work_normally_but_cannot_take_it(): void
    {
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));

        $this->actingAs($this->awa)->get('/admin/rooms/board')->assertOk()->assertSee('Fatou Du Jour');
        $this->from(route('admin.duty.show'))->post(route('admin.duty.take'))
            ->assertSessionHasErrors(['duty' => 'Fatou Du Jour a déjà pris le service de cette journée. Seul le Gérant peut changer la réceptionniste du jour.']);
        $this->assertSame($this->fatou->id, DutyDay::sole()->user_id);
    }

    public function test_the_outgoing_receptionist_may_finish_her_day_without_taking_the_new_one(): void
    {
        $this->deferFor($this->awa);
        $this->get('/admin/rooms/board')->assertOk()->assertSee('Prendre mon service');
        $this->assertSame(0, DutyDay::count());

        // Next business day: asked again.
        $this->travelTo('2026-10-09 06:30:00');
        $this->actingAs($this->awa)->get('/admin')->assertRedirect(route('admin.duty.show'));
    }

    public function test_a_sale_submitted_just_after_the_day_changed_is_never_lost(): void
    {
        // Awa's page was opened before 06:00; she submits an affectation at 06:05 (new day, nobody on duty).
        $this->travelTo('2026-10-09 06:05:00');
        $sale = $this->sell($this->awa); // saved, not redirected to the prise-de-service page

        $this->assertSame($this->awa->id, $sale->invoice->payments()->sole()->attributed_to);
        $this->actingAs($this->awa)->get('/admin/rooms/board')->assertRedirect(route('admin.duty.show')); // next page view: asked

        $this->actingAs($this->fatou)->post(route('admin.duty.take'));
        $this->assertSame($this->fatou->id, $sale->invoice->payments()->sole()->attributed_to);
    }

    // ---- Attribution ---------------------------------------------------------------------

    public function test_sales_made_before_the_relief_go_to_the_receptionist_of_the_day(): void
    {
        $this->deferFor($this->awa);
        $this->travelTo('2026-10-08 07:00:00');
        $early = $this->sell($this->awa); // Awa still at the desk after 06:00

        $payment = $early->invoice->payments()->sole();
        $this->assertSame($this->awa->id, $payment->attributed_to); // nobody on duty yet: her own
        $this->assertSame($this->awa->id, $early->attributed_to);

        $this->travelTo('2026-10-08 08:00:00');
        $this->actingAs($this->fatou)->post(route('admin.duty.take'))
            ->assertSessionHas('status', 'Vous avez pris le service de la journée du 08/10/2026. Toutes les ventes de la journée vous sont attribuées, dont 6 000 FCFA déjà encaissés avant votre arrivée.');

        $payment->refresh();
        $this->assertSame($this->fatou->id, $payment->attributed_to);
        $this->assertSame($this->awa->id, $payment->created_by); // who typed it is kept
        $this->assertSame($this->fatou->id, $early->fresh()->attributed_to);
        $this->assertSame($this->awa->id, $early->fresh()->checked_in_by);

        // Their tills follow.
        [$from, $to] = [Carbon::parse('2026-10-08 06:00'), Carbon::parse('2026-10-09 06:00')];
        $this->assertSame(6000.0, FinanceStats::cashBetween($from, $to, $this->fatou->id));
        $this->assertSame(0.0, FinanceStats::cashBetween($from, $to, $this->awa->id));
        $this->actingAs($this->fatou)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee($early->invoice->number)->assertSee('saisi par Awa Sortante');
        $this->actingAs($this->awa)->get(route('admin.revenue.index'))->assertOk()->assertDontSee($early->invoice->number);
    }

    public function test_every_sale_after_the_prise_de_service_is_hers_even_typed_by_the_manager(): void
    {
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));

        $byManager = $this->sell($this->manager);
        $byColleague = $this->sell($this->awa);

        foreach ([$byManager, $byColleague] as $stay) {
            $this->assertSame($this->fatou->id, $stay->attributed_to);
            $this->assertSame($this->fatou->id, $stay->invoice->payments()->sole()->attributed_to);
        }
        $this->assertSame($this->manager->id, $byManager->invoice->payments()->sole()->created_by);

        // The manager's per-staff table and the Recettes page show it under her name.
        $rows = FinanceStats::cashByUser(Carbon::parse('2026-10-08 06:00'), Carbon::parse('2026-10-09 06:00'));
        $this->assertSame([['Fatou Du Jour', 12000.0]], array_map(fn ($r) => [$r['name'], $r['total']], $rows));
        $this->actingAs($this->manager)->get(route('admin.revenue.index', ['par' => $this->fatou->id]))->assertOk()
            ->assertSee($byManager->invoice->number)->assertSee('saisi par Le Gérant')
            ->assertSee($byColleague->invoice->number)->assertSee('saisi par Awa Sortante');

        $csv = $this->get(route('admin.revenue.export'))->streamedContent();
        $this->assertStringContainsString(';"Encaissé par";"Affecté par";"Saisi par"', $csv);
        $this->assertStringContainsString(';"Fatou Du Jour";"Fatou Du Jour";"Le Gérant"', $csv);
    }

    public function test_the_business_day_boundary_decides_whose_sale_it_is(): void
    {
        DutyDay::assign(Carbon::parse('2026-10-07'), $this->awa);
        DutyDay::assign(Carbon::parse('2026-10-08'), $this->fatou);

        $this->travelTo('2026-10-08 05:59:00');
        $late = $this->sell($this->manager);
        $this->travelTo('2026-10-08 06:00:00');
        $early = $this->sell($this->manager);

        $this->assertSame($this->awa->id, $late->invoice->payments()->sole()->attributed_to);
        $this->assertSame($this->fatou->id, $early->invoice->payments()->sole()->attributed_to);
    }

    // ---- Gérant ---------------------------------------------------------------------------

    public function test_the_manager_sees_the_days_and_corrects_the_receptionist_of_a_past_day(): void
    {
        $this->travelTo('2026-10-07 09:00:00');
        DutyDay::assign(Carbon::parse('2026-10-07'), $this->awa);
        $sale = $this->sell($this->manager);
        $this->assertSame($this->awa->id, $sale->invoice->payments()->sole()->attributed_to);

        $this->travelTo('2026-10-08 10:00:00');
        $this->actingAs($this->manager)->get(route('admin.duty.index'))->assertOk()
            ->assertSee('Prises de service')->assertSee('Jeu. 08/10/2026')->assertSee("Aujourd'hui", false)
            ->assertSee('Mer. 07/10/2026')->assertSee('Awa Sortante')->assertSee('6 000 F');

        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-07', 'user_id' => $this->fatou->id])
            ->assertSessionHas('status', 'Fatou Du Jour est la réceptionniste de la journée du 07/10/2026 : toutes les ventes de cette journée lui sont attribuées.');

        $duty = DutyDay::query()->whereDate('business_date', '2026-10-07')->sole();
        $this->assertSame($this->fatou->id, $duty->user_id);
        $this->assertSame($this->manager->id, $duty->assigned_by);
        $this->assertSame($this->fatou->id, $sale->invoice->payments()->sole()->attributed_to);
        $this->assertSame($this->fatou->id, $sale->fresh()->attributed_to);
        $this->get(route('admin.duty.index'))->assertSee('désignée par Le Gérant');
    }

    public function test_designations_are_validated(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-09', 'user_id' => $this->fatou->id])->assertSessionHasErrors('business_date');
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-08', 'user_id' => $this->manager->id])->assertSessionHasErrors('user_id');
        $this->post(route('admin.duty.assign'), ['business_date' => 'hier', 'user_id' => $this->fatou->id])->assertSessionHasErrors('business_date');
        $inactive = User::factory()->reception()->inactive()->create();
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-08', 'user_id' => $inactive->id])->assertSessionHasErrors('user_id');
        $this->assertSame(0, DutyDay::count());
    }

    public function test_only_the_manager_manages_the_days(): void
    {
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));
        $this->get(route('admin.duty.index'))->assertForbidden();
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-08', 'user_id' => $this->awa->id])->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.duty.index'), false);

        $this->actingAs($this->manager)->get('/admin')->assertSee(route('admin.duty.index'), false);
    }

    public function test_older_rows_keep_their_author_as_attributee(): void
    {
        // Without any prise de service the author is the attributee (as before the feature).
        config(['hotel.duty_required' => false]);
        $sale = $this->sell($this->awa);

        $this->assertSame($this->awa->id, $sale->invoice->payments()->sole()->attributed_to);
        $this->assertSame($this->awa->id, $sale->attributed_to);
        $this->assertSame(0, DutyDay::count());
        $this->assertSame(1, Payment::query()->where('attributed_to', $this->awa->id)->count());
    }
}
