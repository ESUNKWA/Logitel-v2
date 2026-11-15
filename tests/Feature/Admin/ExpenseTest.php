<?php

namespace Tests\Feature\Admin;

use App\Enums\ExpenseStatus;
use App\Models\ActivityLog;
use App\Models\DutyDay;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Dépenses » (V2): mandatory receipt, the Gérant's validation, categories, figures, security.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 10:00:00');
        Storage::fake('local');
    }

    private function category(): ExpenseCategory
    {
        return ExpenseCategory::query()->where('name', 'Carburant')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_date' => '2026-11-15',
            'expense_category_id' => $this->category()->id,
            'amount' => '15 000',
            'source' => 'especes',
            'beneficiary' => 'Station Total',
            'description' => 'Carburant du groupe électrogène',
            'receipt' => UploadedFile::fake()->image('recu.jpg', 800, 600),
        ], $overrides);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('recu.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_seeded_categories_and_reception_permission(): void
    {
        $this->assertSame(['Achats et fournitures', 'Carburant', 'Entretien et réparations', 'Électricité et eau', 'Salaires', 'Avance au personnel', 'Loyer', 'Autres'],
            ExpenseCategory::query()->ordered()->pluck('name')->all());
        $reception = User::factory()->reception()->create();
        $this->assertTrue($reception->can('expenses.create'));
        $this->assertFalse($reception->can('expenses.validate'));
    }

    public function test_a_receptionist_request_waits_for_the_manager_who_validates_it(): void
    {
        $reception = User::factory()->reception()->create();
        $this->actingAs($reception)->post(route('admin.expenses.store'), $this->payload())->assertRedirect();

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseStatus::EnAttente, $expense->status);
        $this->assertSame('15000.00', $expense->amount);
        $this->assertSame($reception->id, $expense->requested_by);
        $this->assertSame($reception->id, $expense->attributed_to);
        $this->assertNull($expense->decided_by);
        Storage::disk('local')->assertExists($expense->receipt_path);
        $this->assertStringStartsWith('expenses/2026/11/', $expense->receipt_path);
        $this->assertStringNotContainsString('recu', $expense->receipt_path); // random name
        $this->assertSame([0.0, 0.0, 0.0], array_values(Expense::tillOutflows(now(), $reception->id))); // not counted yet

        // The receptionist can't validate her own request.
        $this->actingAs($reception)->post(route('admin.expenses.approve', $expense))->assertForbidden();

        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('admin.expenses.approve', $expense))->assertRedirect();
        $expense->refresh();
        $this->assertSame(ExpenseStatus::Validee, $expense->status);
        $this->assertSame($manager->id, $expense->decided_by);
        $this->assertSame(15000.0, Expense::tillOutflows(now(), $reception->id)['especes']);

        // Handled once.
        $this->actingAs($manager)->post(route('admin.expenses.refuse', $expense), ['reason' => 'Trop tard'])->assertSessionHasErrors('expense');
        $this->assertSame(['expense.requested', 'expense.approved'], ActivityLog::query()->where('category', 'depense')->orderBy('id')->pluck('action')->all());
    }

    public function test_the_manager_own_expense_is_validated_at_once_and_refusal_needs_a_reason(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['receipt' => $this->pdf(), 'source' => 'hors_caisse']))->assertRedirect();
        $this->assertSame(ExpenseStatus::Validee, Expense::query()->sole()->status);
        $this->assertSame('application/pdf', Expense::query()->sole()->receipt_mime);

        $this->actingAs(User::factory()->reception()->create())->post(route('admin.expenses.store'), $this->payload());
        $pending = Expense::query()->pending()->sole();
        $this->actingAs($manager)->post(route('admin.expenses.refuse', $pending), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($manager)->post(route('admin.expenses.refuse', $pending), ['reason' => 'Pas de reçu lisible'])->assertRedirect();
        $this->assertSame(ExpenseStatus::Refusee, $pending->fresh()->status);
        $this->assertSame('Pas de reçu lisible', $pending->fresh()->decision_reason);
    }

    public function test_the_receipt_is_mandatory_and_must_be_a_real_image_or_pdf(): void
    {
        $reception = User::factory()->reception()->create();
        $bad = [
            null,
            UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pirate"; ?>'),
            UploadedFile::fake()->createWithContent('recu.pdf', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('enorme.jpg', 800, 600)->size(3000),
            UploadedFile::fake()->create('macro.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
        foreach ($bad as $file) {
            $this->actingAs($reception)->post(route('admin.expenses.store'), $this->payload(['receipt' => $file]))->assertSessionHasErrors('receipt');
        }
        $this->assertSame(0, Expense::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_fields_are_checked_server_side(): void
    {
        $reception = User::factory()->reception()->create();
        $inactive = ExpenseCategory::query()->create(['name' => 'Ancienne', 'is_active' => false]);

        foreach ([
            ['amount' => '0'], ['amount' => '-500'], ['amount' => 'abc'], ['amount' => '1000000000'],
            ['business_date' => '2026-11-16'], ['business_date' => '2026-09-01'], ['business_date' => 'demain'],
            ['expense_category_id' => $inactive->id], ['expense_category_id' => 99999],
            ['source' => 'carte'], ['description' => ''], ['beneficiary' => str_repeat('x', 121)],
            ['amount' => ['1']], ['source' => ['especes']],
        ] as $override) {
            $this->actingAs($reception)->post(route('admin.expenses.store'), $this->payload($override))->assertSessionHasErrors();
        }
        $this->assertSame(0, Expense::query()->count());

        // Status, people and decision are never taken from input.
        $this->actingAs($reception)->post(route('admin.expenses.store'), $this->payload(['status' => 'validee', 'decided_by' => 1, 'requested_by' => 999, 'attributed_to' => 999]));
        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseStatus::EnAttente, $expense->status);
        $this->assertSame($reception->id, $expense->requested_by);
    }

    public function test_receptionists_only_see_their_own_expenses_and_receipts(): void
    {
        $awa = User::factory()->reception()->create();
        $bintou = User::factory()->reception()->create();
        $this->actingAs($awa)->post(route('admin.expenses.store'), $this->payload(['description' => 'Dépense de Awa']));
        $expense = Expense::query()->sole();

        $this->actingAs($awa)->get(route('admin.expenses.index', ['onglet' => 'toutes']))->assertOk()->assertSee('Dépense de Awa');
        $this->actingAs($awa)->get(route('admin.expenses.receipt', $expense))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($bintou)->get(route('admin.expenses.index', ['onglet' => 'toutes']))->assertOk()->assertDontSee('Dépense de Awa');
        $this->actingAs($bintou)->get(route('admin.expenses.show', $expense))->assertNotFound();
        $this->actingAs($bintou)->get(route('admin.expenses.receipt', $expense))->assertNotFound();
        $this->actingAs($bintou)->post(route('admin.expenses.cancel', $expense))->assertNotFound();
        $this->assertSame(ExpenseStatus::EnAttente, $expense->fresh()->status);

        // Without any expense permission: no access at all.
        $nobody = User::factory()->withoutRole()->create();
        $this->actingAs($nobody)->get(route('admin.expenses.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('admin.expenses.receipt', $expense))->assertForbidden();
        // The receipt is never on the public disk.
        $this->assertFileDoesNotExist(public_path('storage/'.$expense->receipt_path));
    }

    public function test_pdf_receipts_are_downloaded_not_displayed(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['receipt' => $this->pdf()]));
        $response = $this->actingAs($manager)->get(route('admin.expenses.receipt', Expense::query()->sole()));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_cancelling(): void
    {
        $reception = User::factory()->reception()->create();
        $manager = User::factory()->create();
        $this->actingAs($reception)->post(route('admin.expenses.store'), $this->payload());
        $expense = Expense::query()->sole();

        // Her own pending request: she may withdraw it.
        $this->actingAs($reception)->post(route('admin.expenses.cancel', $expense))->assertRedirect();
        $this->assertSame(ExpenseStatus::Annulee, $expense->fresh()->status);

        // A validated one: only the manager, with a reason.
        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload());
        $validated = Expense::query()->validated()->sole();
        $this->actingAs($reception)->post(route('admin.expenses.cancel', $validated))->assertNotFound();
        $this->actingAs($manager)->post(route('admin.expenses.cancel', $validated), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($manager)->post(route('admin.expenses.cancel', $validated), ['reason' => 'Saisie en double'])->assertRedirect();
        $this->assertSame(ExpenseStatus::Annulee, $validated->fresh()->status);
        $this->assertSame(2, Expense::query()->count()); // never deleted
    }

    public function test_the_till_of_the_receptionist_on_duty_and_the_dashboard(): void
    {
        $onDuty = User::factory()->reception()->create();
        DutyDay::assign(now(), $onDuty);
        $manager = User::factory()->create();

        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['amount' => '20 000']));
        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['amount' => '5 000', 'source' => 'hors_caisse']));
        $this->assertSame(20000.0, Expense::tillOutflows(now(), $onDuty->id)['especes']);

        $this->actingAs($manager)->get(route('admin.stats'))->assertOk()
            ->assertSee('Dépenses du mois')->assertSee('25 000')->assertSee('Résultat du mois');
        $this->actingAs($manager)->get(route('admin.expenses.index', ['onglet' => 'validees']))->assertOk()->assertSee('25 000');
    }

    public function test_categories_are_managed_by_validators_and_used_ones_kept(): void
    {
        $manager = User::factory()->create();
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.expense-categories.index'))->assertForbidden();

        $this->actingAs($manager)->post(route('admin.expense-categories.store'), ['name' => 'Internet'])->assertRedirect();
        $this->actingAs($manager)->post(route('admin.expense-categories.store'), ['name' => 'Internet'])->assertSessionHasErrors('name');
        $internet = ExpenseCategory::query()->where('name', 'Internet')->sole();
        $this->assertTrue($internet->is_active);

        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['expense_category_id' => $internet->id]));
        $this->actingAs($manager)->delete(route('admin.expense-categories.destroy', $internet))->assertSessionHasErrors('category');
        $this->actingAs($manager)->put(route('admin.expense-categories.update', $internet), ['name' => 'Internet', 'position' => 3, 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($internet->fresh()->is_active);
        $this->actingAs($manager)->get(route('admin.expenses.create'))->assertDontSee('>Internet<', false);
    }

    public function test_pages_escape_typed_text_and_forged_filters_are_ignored(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->post(route('admin.expenses.store'), $this->payload(['description' => '<script>alert(1)</script>']));
        $expense = Expense::query()->sole();

        $this->actingAs($manager)->get(route('admin.expenses.show', $expense))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($manager)->get(route('admin.expenses.index').'?onglet[]=x&du[]=1&categorie[]=2&source[]=w')->assertOk();
        $this->actingAs($manager)->get(route('admin.expenses.index', ['du' => 'nimporte', 'categorie' => '1 or 1=1', 'source' => 'x']))->assertOk();
        $csv = $this->actingAs($manager)->get(route('admin.expenses.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('DEP-', $csv);
    }

    public function test_menus(): void
    {
        $this->actingAs(User::factory()->reception()->simpleUi()->create())->get('/admin')->assertSee(route('admin.expenses.index'), false);
        $this->actingAs(User::factory()->withoutRole()->simpleUi()->create())->get('/admin')->assertDontSee(route('admin.expenses.index'), false);
    }
}
