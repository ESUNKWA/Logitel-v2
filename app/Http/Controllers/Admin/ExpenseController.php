<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Audit;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Dépenses » (V2). Validators (expenses.validate, the Gérant) see and decide every expense;
 * anyone else (expenses.create) only ever sees their own — another one answers 404.
 */
class ExpenseController extends Controller
{
    public const TABS = ['attente' => 'À valider', 'validees' => 'Validées', 'refusees' => 'Refusées / annulées', 'toutes' => 'Toutes'];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $base = fn () => $this->visible($request->user())->betweenDates($filters['from'], $filters['to'])
            ->when($filters['category'], fn (Builder $q, int $id) => $q->where('expense_category_id', $id))
            ->when($filters['source'], fn (Builder $q, ExpenseSource $s) => $q->where('source', $s));

        $counts = [
            'attente' => $this->visible($request->user())->pending()->count(), // every pending one, whatever the period
            'validees' => $base()->validated()->count(),
            'refusees' => $base()->whereIn('status', [ExpenseStatus::Refusee, ExpenseStatus::Annulee])->count(),
            'toutes' => $base()->count(),
        ];
        $tab = $filters['tab'] ?? ($counts['attente'] > 0 ? 'attente' : 'validees');

        $list = match ($tab) {
            'attente' => $this->visible($request->user())->pending(),
            'validees' => $base()->validated(),
            'refusees' => $base()->whereIn('status', [ExpenseStatus::Refusee, ExpenseStatus::Annulee]),
            default => $base(),
        };

        $validated = $base()->validated();

        return view('admin.expenses.index', [
            'filters' => $filters,
            'tab' => $tab,
            'counts' => $counts,
            'expenses' => $list->with(['category', 'requester', 'decider'])->orderByDesc('business_date')->orderByDesc('id')->paginate(50)->withQueryString(),
            'total' => (float) (clone $validated)->sum('amount'),
            'byCategory' => (clone $validated)->selectRaw('expense_category_id, sum(amount) as amount')->groupBy('expense_category_id')->pluck('amount', 'expense_category_id'),
            'bySource' => (clone $validated)->selectRaw('source, sum(amount) as amount')->groupBy('source')->pluck('amount', 'source'),
            'categories' => ExpenseCategory::query()->ordered()->get(),
            'isValidator' => $request->user()->can('expenses.validate'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.expenses.create', [
            'categories' => ExpenseCategory::query()->active()->ordered()->get(),
            'today' => BusinessDay::today(),
            'isValidator' => $request->user()->can('expenses.validate'),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $mime = $request->receiptMime();
        $path = 'expenses/'.now()->format('Y/m').'/'.Str::random(40).'.'.ExpenseRequest::RECEIPT_TYPES[$mime];
        Storage::disk('local')->putFileAs(dirname($path), $request->file('receipt'), basename($path));

        try {
            $expense = Expense::request($request->safe()->except('receipt'), $path, $mime, $request->user());
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return redirect()->route('admin.expenses.show', $expense)->with('status', $expense->status === ExpenseStatus::Validee
            ? 'Dépense '.$expense->reference().' enregistrée et validée.'
            : 'Demande de dépense '.$expense->reference().' envoyée : elle attend la validation du gérant.');
    }

    public function show(Request $request, Expense $expense): View
    {
        $this->authorizeView($request->user(), $expense);

        return view('admin.expenses.show', [
            'expense' => $expense->load(['category', 'requester', 'attributedTo', 'decider', 'canceller']),
            'isValidator' => $request->user()->can('expenses.validate'),
        ]);
    }

    /**
     * The receipt, only through the application (private disk): images inline, PDFs downloaded;
     * the stored server-detected type, never sniffed, sandboxed.
     */
    public function receipt(Request $request, Expense $expense): Response
    {
        $this->authorizeView($request->user(), $expense);
        abort_unless(Storage::disk('local')->exists($expense->receipt_path), 404);

        $pdf = $expense->receipt_mime === 'application/pdf';
        $name = 'justificatif-'.$expense->reference().'.'.(ExpenseRequest::RECEIPT_TYPES[$expense->receipt_mime] ?? 'bin');

        return Storage::disk('local')->response($expense->receipt_path, $name, [
            'Content-Type' => $expense->receipt_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], $pdf ? 'attachment' : 'inline');
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $expense->approve($request->user());

        return back()->with('status', 'Dépense '.$expense->reference().' validée.');
    }

    public function refuse(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], ['reason.required' => 'Indiquez le motif du refus.'], ['reason' => 'motif']);
        $expense->refuse($request->user(), $data['reason']);

        return back()->with('status', 'Dépense '.$expense->reference().' refusée.');
    }

    /**
     * Its author may withdraw a pending request; a validator may cancel any pending or validated
     * expense, with a reason.
     */
    public function cancel(Request $request, Expense $expense): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeView($user, $expense);
        $validator = $user->can('expenses.validate');
        abort_unless($validator || ($expense->isPending() && $expense->requested_by === $user->id), 403);

        $data = $request->validate([
            'reason' => [$validator && ! $expense->isPending() ? 'required' : 'nullable', 'string', 'max:500'],
        ], ['reason.required' => 'Indiquez le motif de l\'annulation.'], ['reason' => 'motif']);
        $expense->cancel($user, $data['reason'] ?? null);

        return back()->with('status', 'Dépense '.$expense->reference().' annulée.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        Audit::record('export.expenses', 'export', 'Export Excel des dépenses du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y'));
        $rows = $this->visible($request->user())->betweenDates($filters['from'], $filters['to'])
            ->when($filters['category'], fn (Builder $q, int $id) => $q->where('expense_category_id', $id))
            ->when($filters['source'], fn (Builder $q, ExpenseSource $s) => $q->where('source', $s))
            ->with(['category', 'requester', 'decider'])->orderBy('business_date')->orderBy('id');
        $cell = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($rows, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Référence', 'Date', 'Catégorie', 'Montant', 'Source', 'Bénéficiaire', 'Description', 'Statut', 'Demandée par', 'Décidée par', 'Motif'], ';');
            $rows->chunk(500, function ($chunk) use ($out, $cell) {
                foreach ($chunk as $e) {
                    fputcsv($out, array_map($cell, [
                        $e->reference(), $e->business_date->format('d/m/Y'), $e->category?->name ?? '', (int) round((float) $e->amount), $e->source->getLabel(),
                        $e->beneficiary ?? '', $e->description, $e->status->getLabel(), $e->requester?->name ?? '', $e->decider?->name ?? '',
                        $e->cancel_reason ?? $e->decision_reason ?? '',
                    ]), ';');
                }
            });
            fclose($out);
        }, 'depenses-'.$filters['from']->format('Y-m-d').'-au-'.$filters['to']->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function visible(User $user): Builder
    {
        return Expense::query()->when(! $user->can('expenses.validate'), fn (Builder $q) => $q->where('requested_by', $user->id));
    }

    private function authorizeView(User $user, Expense $expense): void
    {
        // Someone else's expense doesn't exist for a non-validator (no enumeration).
        abort_unless($user->can('expenses.validate') || $expense->requested_by === $user->id, 404);
    }

    /**
     * Plain strings only, unknown values ignored: ?onglet=, business days ?du=&au= (default: this
     * month so far; swapped, capped at 366 days, future → today), ?categorie=ID, ?source=.
     *
     * @return array{tab: string|null, from: Carbon, to: Carbon, category: int|null, source: ExpenseSource|null}
     */
    private function filters(Request $request): array
    {
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? trim($value) : '';
        $today = BusinessDay::today();
        $date = function (string $key) use ($text, $today): ?Carbon {
            try {
                return $text($key) !== '' ? Carbon::createFromFormat('!Y-m-d', $text($key))->min($today) : null;
            } catch (Throwable) {
                return null;
            }
        };
        $to = $date('au') ?? $today->copy();
        $from = $date('du') ?? $to->copy()->startOfMonth();
        [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
        $category = $text('categorie');

        return [
            'tab' => array_key_exists($text('onglet'), self::TABS) ? $text('onglet') : null,
            'from' => $from->max($to->copy()->subDays(365)),
            'to' => $to,
            'category' => ctype_digit($category) ? (int) $category : null,
            'source' => ExpenseSource::tryFrom($text('source')),
        ];
    }
}
