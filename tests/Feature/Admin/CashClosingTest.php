<?php

namespace Tests\Feature\Admin;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Models\ActivityLog;
use App\Models\CashClosing;
use App\Models\DutyDay;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Clôture de caisse » (V2): one per business day and till, expected computed server side
 * (payments − validated till expenses), every method declared, the Gérant validates or contests.
 */
class CashClosingTest extends TestCase
{
    use RefreshDatabase;

    private User $awa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 18:00:00');
        $this->awa = User::factory()->reception()->create(['name' => 'Awa']);
        DutyDay::assign(now(), $this->awa);
    }

    private function cashed(int $amount, PaymentMethod $method): void
    {
        $this->actingAs($this->awa);
        Reservation::factory()->create(['unit_rate' => $amount, 'duration' => 1, 'pricing_mode' => 'heure'])->invoice->settle([['method' => $method, 'amount' => $amount]], now());
    }

    private function expense(int $amount, ExpenseSource $source, ExpenseStatus $status = ExpenseStatus::Validee): Expense
    {
        $e = new Expense(['business_date' => now()->toDateString(), 'expense_category_id' => ExpenseCategory::query()->value('id'), 'amount' => $amount, 'source' => $source, 'description' => 'Test']);
        $e->forceFill(['receipt_path' => 'x', 'receipt_mime' => 'image/jpeg', 'status' => $status, 'requested_by' => $this->awa->id, 'attributed_to' => $this->awa->id])->save();

        return $e;
    }

    private function close(array $declared, array $extra = [])
    {
        return $this->actingAs($this->awa)->post(route('admin.cash-closings.store'), array_merge([
            'jour' => '2026-11-15',
            'declared' => array_merge(['especes' => '0', 'orange_money' => '0', 'wave' => '0'], $declared),
        ], $extra));
    }

    public function test_expected_is_payments_minus_validated_till_expenses(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->cashed(10000, PaymentMethod::Wave);
        $this->expense(5000, ExpenseSource::Especes);
        $this->expense(2000, ExpenseSource::Especes, ExpenseStatus::EnAttente); // not validated: not deducted
        $this->expense(9000, ExpenseSource::HorsCaisse);                           // not from the till

        $expected = CashClosing::expectedFor(now(), $this->awa->id);
        $this->assertSame(['received' => 30000.0, 'expenses' => 5000.0, 'expected' => 25000.0], $expected['especes']);
        $this->assertSame(10000.0, $expected['wave']['expected']);
        $this->assertSame(0.0, $expected['orange_money']['expected']);

        $this->actingAs($this->awa)->get(route('admin.cash-closings.index'))->assertOk()
            ->assertSee('25 000 F')->assertSee('2 000 F de dépenses payées en caisse');
    }

    public function test_a_matching_closing_needs_no_comment_and_the_manager_validates_it(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->cashed(10000, PaymentMethod::OrangeMoney);

        $this->close(['especes' => '30 000', 'orange_money' => '10 000'])->assertRedirect();
        $closing = CashClosing::query()->sole();
        $this->assertSame(CashClosing::STATUS_SUBMITTED, $closing->status);
        $this->assertSame('40000.00', $closing->total_expected);
        $this->assertSame('0.00', $closing->total_difference);
        $this->assertSame($this->awa->id, $closing->user_id);

        $this->actingAs($this->awa)->post(route('admin.cash-closings.validate', $closing))->assertForbidden();

        $manager = User::factory()->create();
        $this->actingAs($manager)->get(route('admin.cash-closings.index'))->assertOk()->assertSee('Awa')->assertSee('1 à valider');
        $this->actingAs($manager)->post(route('admin.cash-closings.validate', $closing))->assertRedirect();
        $this->assertSame(CashClosing::STATUS_VALIDATED, $closing->fresh()->status);

        // Validated: frozen for the receptionist.
        $this->close(['especes' => '1', 'orange_money' => '10 000'], ['comment' => 'x'])->assertSessionHasErrors('closing');
        $this->assertSame('0.00', $closing->fresh()->total_difference);
        $this->assertSame(['cash_closing.submitted', 'cash_closing.validated'], ActivityLog::query()->where('category', 'cloture')->orderBy('id')->pluck('action')->all());
    }

    public function test_every_method_must_be_declared_and_a_gap_needs_a_comment(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);

        $this->actingAs($this->awa)->post(route('admin.cash-closings.store'), ['jour' => '2026-11-15', 'declared' => ['especes' => '30000']])
            ->assertSessionHasErrors(['declared.orange_money', 'declared.wave']);
        $this->close(['especes' => '28 000'])->assertSessionHasErrors('comment');
        $this->assertSame(0, CashClosing::query()->count());

        $this->close(['especes' => '28 000'], ['comment' => 'Erreur de rendu de monnaie'])->assertRedirect();
        $this->assertSame('-2000.00', CashClosing::query()->sole()->total_difference);
    }

    public function test_the_cash_count_sets_the_cash_amount_server_side(): void
    {
        $this->cashed(17500, PaymentMethod::Especes);

        $this->close(['especes' => '999'], ['counted' => ['notes' => ['10000' => 1, '5000' => 1, '2000' => 1], 'coins' => ['500' => 1]]])->assertRedirect();
        $closing = CashClosing::query()->sole();
        $this->assertSame(17500, $closing->declared['especes']);
        $this->assertSame('0.00', $closing->total_difference);
        $this->assertSame(['notes' => [10000 => 1, 5000 => 1, 2000 => 1], 'coins' => [500 => 1]], $closing->counted);
    }

    public function test_expected_is_never_taken_from_the_form_and_inputs_are_checked(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);

        $this->close(['especes' => '30000'], ['expected' => ['especes' => ['expected' => 0]], 'total_expected' => 0, 'status' => 'validee', 'user_id' => 999])->assertRedirect();
        $closing = CashClosing::query()->sole();
        $this->assertSame('30000.00', $closing->total_expected);
        $this->assertSame(CashClosing::STATUS_SUBMITTED, $closing->status);
        $this->assertSame($this->awa->id, $closing->user_id);

        foreach ([['jour' => '2026-11-16'], ['jour' => '2026-11-01'], ['jour' => 'hier'], ['declared' => 'x'], ['declared' => ['especes' => '-5', 'orange_money' => '0', 'wave' => '0']],
            ['declared' => ['especes' => ['1'], 'orange_money' => '0', 'wave' => '0']], ['counted' => ['notes' => ['10000' => -1]]]] as $bad) {
            $this->actingAs($this->awa)->post(route('admin.cash-closings.store'), array_merge(['jour' => '2026-11-15', 'declared' => ['especes' => '30000', 'orange_money' => '0', 'wave' => '0']], $bad))
                ->assertSessionHasErrors();
        }
    }

    public function test_contested_then_declared_again(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->close(['especes' => '25 000'], ['comment' => 'Je ne sais pas']);
        $closing = CashClosing::query()->sole();
        $manager = User::factory()->create();

        $this->actingAs($manager)->post(route('admin.cash-closings.contest', $closing), ['review_comment' => ''])->assertSessionHasErrors('review_comment');
        $this->actingAs($manager)->post(route('admin.cash-closings.contest', $closing), ['review_comment' => 'Recompte la caisse'])->assertRedirect();
        $this->assertSame(CashClosing::STATUS_CONTESTED, $closing->fresh()->status);

        $this->close(['especes' => '30 000'])->assertRedirect();
        $closing->refresh();
        $this->assertSame(CashClosing::STATUS_SUBMITTED, $closing->status);
        $this->assertSame('0.00', $closing->total_difference);
        $this->assertNull($closing->review_comment);
        $this->assertSame(1, CashClosing::query()->count());
    }

    public function test_the_day_stays_free_and_the_closing_shows_moved_figures(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->close(['especes' => '30 000']);
        $closing = CashClosing::query()->sole();

        // Laisser libre (client decision): another payment after the closing is still recorded…
        $this->cashed(5000, PaymentMethod::Especes);
        $this->assertSame(2, Payment::query()->count());
        // …and the closing says the figures moved.
        $manager = User::factory()->create();
        $this->actingAs($manager)->get(route('admin.cash-closings.show', $closing))->assertOk()
            ->assertSee('Les chiffres de cette journée ont changé depuis la clôture')->assertSee('35 000 F');
    }

    public function test_receptionists_only_see_their_own_closings(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->close(['especes' => '30 000']);
        $closing = CashClosing::query()->sole();
        $bintou = User::factory()->reception()->create();

        $this->actingAs($bintou)->get(route('admin.cash-closings.show', $closing))->assertNotFound();
        $this->actingAs($bintou)->get(route('admin.cash-closings.index'))->assertOk()->assertDontSee('Clôtures du personnel');
        $this->actingAs($bintou)->get(route('admin.cash-closings.export'))->assertForbidden();
        $this->actingAs(User::factory()->withoutRole()->create())->get(route('admin.cash-closings.index'))->assertForbidden();
    }

    public function test_the_manager_sees_unclosed_days_and_exports(): void
    {
        $this->cashed(12000, PaymentMethod::Especes);
        $this->travelTo('2026-11-16 18:00:00');
        $manager = User::factory()->create();

        $this->actingAs($manager)->get(route('admin.cash-closings.index'))->assertOk()
            ->assertSee('Journées non clôturées')->assertSee('15/11/2026 — Awa');

        $csv = $this->actingAs($manager)->get(route('admin.cash-closings.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Réceptionniste', $csv);
        $this->assertContains('export.cash_closings', ActivityLog::query()->pluck('action')->all());
        // Forged filters are ignored.
        $this->actingAs($manager)->get(route('admin.cash-closings.index').'?du[]=1&par[]=2&statut[]=x&jour[]=y')->assertOk();
    }

    public function test_home_link_and_net_cash_to_hand_over(): void
    {
        $this->cashed(30000, PaymentMethod::Especes);
        $this->expense(5000, ExpenseSource::Especes);

        $this->actingAs($this->awa->forceFill(['ui_mode' => 'simple']))->get('/admin')->assertOk()
            ->assertSee('Clôturer ma caisse')
            ->assertSee('Dépenses payées en espèces')
            ->assertSee('25 000 F');
    }
}
