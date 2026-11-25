<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\DutyShift;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\FinanceStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * « Prise de service » by periods (client request, 2026-11-25): one receptionist on duty at a
 * time, each with her own sales — the Gérant alone, one team or several teams a day. Taken
 * automatically when nobody is on duty, relief after confirmation, « renfort » (her sales go to
 * the colleague on duty), end of service (button or at log-out). Every sale is attributed to
 * whoever is on duty when it is recorded, while « saisi par » keeps who typed it. The Gérant
 * never takes it; he corrects periods, designates a day without any and ends a forgotten one.
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

    private function attributee(Reservation $sale): ?int
    {
        return $sale->invoice->payments()->sole()->attributed_to;
    }

    // ---- Taking the service ------------------------------------------------------------------

    public function test_nobody_on_duty_the_receptionist_takes_the_service_automatically(): void
    {
        $this->actingAs($this->fatou)->get('/admin')->assertOk()
            ->assertSee('Vous êtes de service depuis 10h00 : vos encaissements vous sont attribués.')
            ->assertSee('De service :')->assertSee('Fatou Du Jour');

        $shift = DutyShift::sole();
        $this->assertSame($this->fatou->id, $shift->user_id);
        $this->assertTrue($shift->isOpen());
        $this->assertSame('2026-10-08 10:00', $shift->started_at->format('Y-m-d H:i'));

        // Every page works; she stays on duty (no second period).
        foreach (['/admin/rooms/board', '/admin/affectations', '/admin/recettes'] as $page) {
            $this->get($page)->assertOk();
        }
        $this->assertSame(1, DutyShift::count());
    }

    public function test_the_manager_is_never_on_duty(): void
    {
        $this->actingAs($this->manager)->get('/admin')->assertOk()->assertSee('Personne de service');
        $this->get(route('admin.duty.show'))->assertRedirect(route('admin.duty.index'));
        $this->post(route('admin.duty.take'))->assertForbidden();
        $this->post(route('admin.duty.end'))->assertForbidden();
        $this->assertSame(0, DutyShift::count());
    }

    public function test_background_requests_and_forms_never_take_the_service(): void
    {
        $this->actingAs($this->fatou)->getJson(route('admin.alerts.checkouts'))->assertOk();
        $this->get('/admin/profil')->assertOk();
        $this->assertSame(0, DutyShift::count());
    }

    // ---- Relief -----------------------------------------------------------------------------

    public function test_a_colleague_arriving_is_asked_to_take_over_and_the_sales_split(): void
    {
        $this->travelTo('2026-10-08 07:00:00');
        $this->actingAs($this->awa)->get('/admin')->assertOk(); // Awa on duty from 07:00
        $byAwa = $this->sell($this->awa);

        $this->travelTo('2026-10-08 19:00:00');
        foreach (['/admin', '/admin/rooms/board'] as $page) {
            $this->actingAs($this->fatou)->get($page)->assertRedirect(route('admin.duty.show'));
        }
        $this->get(route('admin.duty.show'))->assertOk()
            ->assertSee('Prendre la relève ?')->assertSee('Awa Sortante')->assertSee('08/10 à 07h00')
            ->assertSee('Oui, je prends la relève')->assertSee('Non, je viens en renfort');

        $this->post(route('admin.duty.take'))->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'Vous avez pris la relève de Awa Sortante à 19h00 : vos encaissements vous sont attribués à partir de maintenant.');
        $byFatou = $this->sell($this->fatou);
        $byManager = $this->sell($this->manager);

        [$awaShift, $fatouShift] = DutyShift::query()->orderBy('started_at')->get()->all();
        $this->assertSame('19:00', $awaShift->ended_at->format('H:i'));
        $this->assertSame($this->fatou->id, $awaShift->ended_by);
        $this->assertTrue($fatouShift->isOpen());

        // Each her own sales, in the same business day.
        $this->assertSame($this->awa->id, $this->attributee($byAwa));
        $this->assertSame($this->fatou->id, $this->attributee($byFatou));
        $this->assertSame($this->fatou->id, $this->attributee($byManager));
        [$from, $to] = [Carbon::parse('2026-10-08 06:00'), Carbon::parse('2026-10-09 06:00')];
        $this->assertSame(6000.0, FinanceStats::cashBetween($from, $to, $this->awa->id));
        $this->assertSame(12000.0, FinanceStats::cashBetween($from, $to, $this->fatou->id));

        // Awa coming back on a page is now asked in turn.
        $this->actingAs($this->awa)->get('/admin')->assertRedirect(route('admin.duty.show'));
    }

    public function test_reinforcement_works_without_relieving_and_her_sales_go_to_the_colleague(): void
    {
        $this->actingAs($this->awa)->get('/admin');

        $this->actingAs($this->fatou)->post(route('admin.duty.decline'))->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'D\'accord : Awa Sortante reste de service, vos encaissements lui sont attribués.');
        $this->get('/admin/rooms/board')->assertOk(); // not asked again for this period
        $sale = $this->sell($this->fatou);

        $this->assertSame($this->awa->id, $this->attributee($sale)); // one till at a time
        $this->assertSame($this->fatou->id, $sale->invoice->payments()->sole()->created_by);
        $this->assertSame(1, DutyShift::count());

        // A new period (Awa ends, Mariam takes it): Fatou is asked again.
        $mariam = User::factory()->reception()->create(['name' => 'Mariam']);
        $this->actingAs($mariam)->post(route('admin.duty.take'));
        $this->actingAs($this->fatou)->get('/admin')->assertRedirect(route('admin.duty.show'));
    }

    public function test_a_night_period_goes_on_after_the_business_day_changes(): void
    {
        $this->travelTo('2026-10-08 19:00:00');
        $this->actingAs($this->awa)->get('/admin');

        // 06:30: new business day, Awa still at the desk — no prompt, still hers.
        $this->travelTo('2026-10-09 06:30:00');
        $this->actingAs($this->awa)->get('/admin')->assertOk();
        $sale = $this->sell($this->awa);
        $this->assertSame($this->awa->id, $this->attributee($sale));

        // 07:00: Fatou takes over.
        $this->travelTo('2026-10-09 07:00:00');
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));
        $this->assertSame($this->awa->id, $this->attributee($sale));
        $this->assertSame($this->fatou->id, $this->attributee($this->sell($this->manager)));
        $this->assertSame(2, DutyShift::count());
    }

    // ---- Ending the service -------------------------------------------------------------------

    public function test_ending_the_service_then_the_next_one_takes_it_at_login(): void
    {
        $this->travelTo('2026-10-08 07:00:00');
        $this->actingAs($this->awa)->get('/admin')->assertOk();
        $this->get(route('admin.duty.show'))->assertOk()->assertSee('Terminer mon service');

        $this->travelTo('2026-10-08 14:00:00');
        $this->post(route('admin.duty.end'))->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', 'Votre service est terminé. La prochaine réceptionniste prendra le service à sa connexion.');
        $this->assertFalse(DutyShift::sole()->isOpen());

        $this->travelTo('2026-10-08 14:10:00');
        $this->actingAs($this->fatou)->get('/admin')->assertOk(); // automatic, no question
        $this->assertSame($this->fatou->id, DutyShift::current()->user_id);
    }

    public function test_at_log_out_the_receptionist_on_duty_chooses_to_end_her_service_or_not(): void
    {
        $this->actingAs($this->awa)->get('/admin')->assertOk()
            ->assertSee('data-logout-duty', false)->assertSee('name="end_duty" value="0"', false);

        $this->post(route('admin.logout'), ['end_duty' => '0'])->assertRedirect(route('admin.login'));
        $this->assertTrue(DutyShift::sole()->isOpen()); // logged out only

        $this->actingAs($this->awa)->post(route('admin.logout'), ['end_duty' => '1'])->assertRedirect(route('admin.login'));
        $this->assertFalse(DutyShift::sole()->isOpen());
        $this->assertSame($this->awa->id, DutyShift::sole()->ended_by);

        // Not on duty: no question at log-out.
        $this->actingAs($this->manager)->get('/admin')->assertDontSee('data-logout-duty', false);
    }

    // ---- Attribution ------------------------------------------------------------------------

    public function test_a_sale_recorded_while_nobody_is_on_duty_goes_to_the_next_receptionist(): void
    {
        // A form posted from a page opened earlier is never blocked nor lost.
        $this->travelTo('2026-10-08 07:00:00');
        $early = $this->sell($this->manager);
        $this->assertSame($this->manager->id, $this->attributee($early)); // nobody on duty: its author
        $this->assertSame(0, DutyShift::count());

        $this->travelTo('2026-10-08 08:00:00');
        $this->actingAs($this->fatou)->get('/admin')->assertOk()
            ->assertSee('Chambre N°')->assertSee('Vous êtes de service depuis 08h00 : vos encaissements vous sont attribués, dont 6 000 FCFA encaissés avant votre arrivée, quand personne n&#039;était de service.', false);

        $payment = $early->invoice->payments()->sole();
        $this->assertSame($this->fatou->id, $payment->attributed_to);
        $this->assertSame($this->manager->id, $payment->created_by); // who typed it is kept
        $this->assertSame($this->fatou->id, $early->fresh()->attributed_to);
        $this->actingAs($this->fatou)->get(route('admin.revenue.index'))->assertOk()
            ->assertSee($early->invoice->number)->assertSee('saisi par Le Gérant');
    }

    public function test_the_gap_never_reaches_back_into_a_previous_period_or_day(): void
    {
        $this->travelTo('2026-10-08 07:00:00');
        $this->actingAs($this->awa)->get('/admin');
        $byAwa = $this->sell($this->awa);
        $this->travelTo('2026-10-08 12:00:00');
        $this->post(route('admin.duty.end'));

        $this->travelTo('2026-10-08 13:00:00');
        $inGap = $this->sell($this->manager);
        $this->travelTo('2026-10-08 14:00:00');
        $this->actingAs($this->fatou)->get('/admin');

        $this->assertSame($this->awa->id, $this->attributee($byAwa));
        $this->assertSame($this->fatou->id, $this->attributee($inGap));

        // The previous business day's unattended sales stay their author's.
        $this->travelTo('2026-10-09 05:00:00');
        $this->post(route('admin.duty.end'));
        $lastNight = $this->sell($this->manager);
        $this->travelTo('2026-10-09 07:00:00');
        $this->actingAs($this->awa)->get('/admin');
        $this->assertSame($this->manager->id, $this->attributee($lastNight));
    }

    public function test_every_sale_during_her_period_is_hers_even_typed_by_the_manager(): void
    {
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));

        $byManager = $this->sell($this->manager);
        $byColleague = $this->sell($this->awa);

        foreach ([$byManager, $byColleague] as $stay) {
            $this->assertSame($this->fatou->id, $stay->attributed_to);
            $this->assertSame($this->fatou->id, $this->attributee($stay));
        }
        $this->assertSame($this->manager->id, $byManager->invoice->payments()->sole()->created_by);

        $rows = FinanceStats::cashByUser(Carbon::parse('2026-10-08 06:00'), Carbon::parse('2026-10-09 06:00'));
        $this->assertSame([['Fatou Du Jour', 12000.0]], array_map(fn ($r) => [$r['name'], $r['total']], $rows));
        $this->actingAs($this->manager)->get(route('admin.revenue.index', ['par' => $this->fatou->id]))->assertOk()
            ->assertSee($byManager->invoice->number)->assertSee('saisi par Le Gérant')
            ->assertSee($byColleague->invoice->number)->assertSee('saisi par Awa Sortante');

        $csv = $this->get(route('admin.revenue.export'))->streamedContent();
        $this->assertStringContainsString(';"Encaissé par";"Affecté par";"Saisi par"', $csv);
        $this->assertStringContainsString(';"Fatou Du Jour";"Fatou Du Jour";"Le Gérant"', $csv);
    }

    public function test_till_expenses_follow_the_period(): void
    {
        $category = ExpenseCategory::query()->firstOrFail();
        $record = fn (string $date) => Expense::request(
            ['business_date' => $date, 'expense_category_id' => $category->id, 'amount' => 1000, 'source' => 'especes', 'description' => 'Test'],
            'receipts/x.jpg', 'image/jpeg', $this->manager,
        );

        $this->travelTo('2026-10-08 07:00:00');
        $this->actingAs($this->awa)->get('/admin');
        $this->travelTo('2026-10-08 19:00:00');
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));

        // Today's: whoever is on duty when recorded.
        $this->assertSame($this->fatou->id, $record('2026-10-08')->attributed_to);
        // A past date: the last period of that date (client decision).
        $this->travelTo('2026-10-10 10:00:00');
        $this->assertSame($this->fatou->id, $record('2026-10-08')->attributed_to);
        // A date without any period: its author.
        $this->assertSame($this->manager->id, $record('2026-10-05')->attributed_to);
    }

    public function test_without_any_prise_de_service_the_author_is_the_attributee(): void
    {
        // The Gérant alone, or duty not required: each sale stays its author's (as before the feature).
        config(['hotel.duty_required' => false]);
        $sale = $this->sell($this->awa);

        $this->assertSame($this->awa->id, $this->attributee($sale));
        $this->assertSame($this->awa->id, $sale->attributed_to);
        $this->assertSame(0, DutyShift::count());
        $this->assertSame(1, Payment::query()->where('attributed_to', $this->awa->id)->count());
    }

    // ---- Gérant --------------------------------------------------------------------------------

    public function test_the_manager_sees_the_periods_and_corrects_one(): void
    {
        $this->travelTo('2026-10-08 07:00:00');
        $this->actingAs($this->awa)->get('/admin');
        $sale = $this->sell($this->manager);
        $this->travelTo('2026-10-08 19:00:00');
        $this->actingAs($this->fatou)->post(route('admin.duty.take'));
        $awaShift = DutyShift::query()->orderBy('started_at')->firstOrFail();

        $this->actingAs($this->manager)->get(route('admin.duty.index'))->assertOk()
            ->assertSee('Prises de service')->assertSee('Jeu. 08/10/2026')->assertSee("Aujourd'hui", false)
            ->assertSeeInOrder(['Awa Sortante', '08/10 07h00', '08/10 19h00', 'relève : Fatou Du Jour', '6 000 F', 'Fatou Du Jour', 'en cours'])
            ->assertSee('Terminer ce service');

        $this->post(route('admin.duty.reassign', $awaShift), ['user_id' => $this->fatou->id])
            ->assertSessionHas('status', 'Fatou Du Jour est la réceptionniste de la période du 08/10/2026 à 07h00 au 08/10/2026 à 19h00 : toutes les ventes de cette période lui sont attribuées.');

        $awaShift->refresh();
        $this->assertSame($this->fatou->id, $awaShift->user_id);
        $this->assertSame($this->manager->id, $awaShift->assigned_by);
        $this->assertSame($this->fatou->id, $this->attributee($sale));
        $this->assertSame($this->fatou->id, $sale->fresh()->attributed_to);
        $this->get(route('admin.duty.index'))->assertSee('désignée par Le Gérant');
    }

    public function test_the_manager_designates_a_past_day_without_any_period(): void
    {
        $this->travelTo('2026-10-07 09:00:00');
        $sale = $this->sell($this->manager);

        $this->travelTo('2026-10-08 10:00:00');
        $this->actingAs($this->manager)->post(route('admin.duty.assign'), ['business_date' => '2026-10-07', 'user_id' => $this->awa->id])
            ->assertSessionHas('status', 'Awa Sortante est la réceptionniste de la journée du 07/10/2026 : toutes les ventes de cette journée lui sont attribuées.');

        $shift = DutyShift::sole();
        $this->assertSame('2026-10-07 06:00', $shift->started_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 06:00', $shift->ended_at->format('Y-m-d H:i'));
        $this->assertSame($this->awa->id, $this->attributee($sale));

        // A day that has periods is corrected period by period.
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-07', 'user_id' => $this->fatou->id])
            ->assertSessionHasErrors(['business_date' => 'Cette journée a déjà des périodes de service : corrigez la réceptionniste de la période concernée.']);
        $this->assertSame(1, DutyShift::count());
    }

    public function test_the_manager_ends_a_forgotten_service(): void
    {
        $this->actingAs($this->awa)->get('/admin');
        $shift = DutyShift::sole();

        $this->actingAs($this->manager)->post(route('admin.duty.close', $shift))
            ->assertSessionHas('status', 'Le service de Awa Sortante est terminé. La prochaine réceptionniste prendra le service à sa connexion.');
        $this->assertFalse($shift->fresh()->isOpen());
        $this->assertSame($this->manager->id, $shift->fresh()->ended_by);
        $this->post(route('admin.duty.close', $shift))->assertNotFound(); // already ended

        $this->actingAs($this->fatou)->get('/admin')->assertOk();
        $this->assertSame($this->fatou->id, DutyShift::current()->user_id);
    }

    public function test_designations_are_validated(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-09', 'user_id' => $this->fatou->id])->assertSessionHasErrors('business_date');
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-08', 'user_id' => $this->manager->id])->assertSessionHasErrors('user_id');
        $this->post(route('admin.duty.assign'), ['business_date' => 'hier', 'user_id' => $this->fatou->id])->assertSessionHasErrors('business_date');
        $inactive = User::factory()->reception()->inactive()->create();
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-08', 'user_id' => $inactive->id])->assertSessionHasErrors('user_id');
        $this->assertSame(0, DutyShift::count());

        $this->actingAs($this->awa)->get('/admin');
        $shift = DutyShift::sole();
        $this->actingAs($this->manager)->post(route('admin.duty.reassign', $shift), ['user_id' => $this->manager->id])->assertSessionHasErrors('user_id');
        $this->assertSame($this->awa->id, $shift->fresh()->user_id);
    }

    public function test_only_the_manager_manages_the_periods(): void
    {
        $this->actingAs($this->fatou)->get('/admin');
        $shift = DutyShift::sole();

        $this->get(route('admin.duty.index'))->assertForbidden();
        $this->post(route('admin.duty.assign'), ['business_date' => '2026-10-07', 'user_id' => $this->awa->id])->assertForbidden();
        $this->post(route('admin.duty.reassign', $shift), ['user_id' => $this->awa->id])->assertForbidden();
        $this->post(route('admin.duty.close', $shift))->assertForbidden();
        $this->get('/admin')->assertDontSee(route('admin.duty.index'), false);

        $this->actingAs($this->manager)->get('/admin')->assertSee(route('admin.duty.index'), false);
    }

    // ---- Migration ------------------------------------------------------------------------------

    public function test_the_former_days_become_periods_without_changing_any_attribution(): void
    {
        $sale = $this->sell($this->manager);
        $migration = require database_path('migrations/2026_11_25_100000_create_duty_shifts_table.php');
        $migration->down(); // back to duty_days
        $this->assertTrue(Schema::hasTable('duty_days'));

        Payment::query()->update(['attributed_to' => $this->awa->id]); // as the old rule left it
        DB::table('duty_days')->insert([
            ['business_date' => '2026-10-07', 'user_id' => $this->awa->id, 'taken_at' => '2026-10-07 07:00', 'assigned_by' => $this->manager->id, 'created_at' => now(), 'updated_at' => now()],
            ['business_date' => '2026-10-08', 'user_id' => $this->awa->id, 'taken_at' => '2026-10-08 07:00', 'assigned_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('duty_days'));
        [$past, $today] = DutyShift::query()->orderBy('started_at')->get()->all();
        $this->assertSame(['2026-10-07 06:00', '2026-10-08 06:00'], [$past->started_at->format('Y-m-d H:i'), $past->ended_at->format('Y-m-d H:i')]);
        $this->assertSame($this->manager->id, $past->assigned_by);
        $this->assertSame('2026-10-08 06:00', $today->started_at->format('Y-m-d H:i'));
        $this->assertTrue($today->isOpen()); // today's day: still on duty
        $this->assertSame($this->awa->id, $this->attributee($sale)); // untouched
    }
}
