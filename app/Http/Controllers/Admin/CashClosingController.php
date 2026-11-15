<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CashClosingRequest;
use App\Models\CashClosing;
use App\Models\Expense;
use App\Models\User;
use App\Support\Audit;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Clôture de caisse » (V2). A till owner (cash.close) closes her own till; reviewers
 * (cash.review, the Gérant) see every closing, validate or contest it, and the days left unclosed.
 */
class CashClosingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = BusinessDay::today();
        $data = ['today' => $today, 'canClose' => $user->can('cash.close'), 'canReview' => $user->can('cash.review')];

        if ($data['canClose']) {
            $day = $this->dayParam($request, 'jour') ?? $today->copy();
            $day = $day->lessThan($today->copy()->subDays(CashClosingRequest::DAYS_BACK)) ? $today->copy() : $day;
            $expected = CashClosing::expectedFor($day, $user->id);
            // The Gérant holds every permission but rarely a till: his own form only shows when
            // money went through his hands that day (or he asks for a day explicitly).
            if ($data['canReview'] && ! $user->takesDuty() && ! $request->has('jour')
                && collect($expected)->every(fn ($row) => $row['received'] == 0 && $row['expenses'] == 0)
                && ! CashClosing::query()->where('user_id', $user->id)->exists()) {
                $data['canClose'] = false;
            }
        }

        if ($data['canClose']) {
            $data += [
                'day' => $day,
                'expected' => $expected,
                'closing' => CashClosing::query()->whereDate('business_date', $day->toDateString())->where('user_id', $user->id)->first(),
                'history' => CashClosing::query()->where('user_id', $user->id)->orderByDesc('business_date')->limit(31)->get(),
                'pendingExpenses' => Expense::query()->pending()->whereDate('business_date', $day->toDateString())->where('attributed_to', $user->id)->where('source', '!=', 'hors_caisse')->sum('amount'),
            ];
        }

        if ($data['canReview']) {
            $filters = $this->filters($request);
            $data += [
                'filters' => $filters,
                'closings' => $this->reviewQuery($filters)->with(['user', 'reviewer'])->orderByDesc('business_date')->orderBy('user_id')->paginate(50)->withQueryString(),
                'toReview' => CashClosing::query()->where('status', CashClosing::STATUS_SUBMITTED)->count(),
                'monthGap' => (float) CashClosing::query()->whereDate('business_date', '>=', $today->copy()->startOfMonth()->toDateString())->sum('total_difference'),
                'missing' => $this->missing($filters['from'], $filters['to']),
                'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            ];
        }

        return view('admin.cash-closings.index', $data);
    }

    public function store(CashClosingRequest $request): RedirectResponse
    {
        $day = $request->day();
        $closing = CashClosing::submit($day, $request->user(), $request->declared(), $request->counted(), $request->validated('comment'), $request->user());
        $gap = (float) $closing->total_difference;

        return redirect()->route('admin.cash-closings.show', $closing)->with('status', 'Caisse du '.$day->format('d/m/Y').' clôturée'
            .(abs($gap) < 0.01 ? ' : tout correspond.' : ' avec un écart de '.number_format($gap, 0, ',', ' ').' F.').' Le gérant va la vérifier.');
    }

    public function show(Request $request, CashClosing $closing): View
    {
        $user = $request->user();
        abort_unless($user->can('cash.review') || $closing->user_id === $user->id, 404);

        return view('admin.cash-closings.show', [
            'closing' => $closing->load(['user', 'submitter', 'reviewer']),
            'canReview' => $user->can('cash.review'),
            'figuresChanged' => $closing->figuresChanged(),
            'current' => CashClosing::expectedFor($closing->business_date, $closing->user_id),
        ]);
    }

    public function validateClosing(Request $request, CashClosing $closing): RedirectResponse
    {
        $data = $request->validate(['review_comment' => ['nullable', 'string', 'max:1000']]);
        $closing->review($request->user(), true, $data['review_comment'] ?? null);

        return back()->with('status', 'Clôture du '.$closing->business_date->format('d/m/Y').' validée.');
    }

    public function contest(Request $request, CashClosing $closing): RedirectResponse
    {
        $data = $request->validate(['review_comment' => ['required', 'string', 'min:3', 'max:1000']], ['review_comment.required' => 'Indiquez pourquoi vous contestez cette clôture.'], ['review_comment' => 'motif']);
        $closing->review($request->user(), false, $data['review_comment']);

        return back()->with('status', 'Clôture du '.$closing->business_date->format('d/m/Y').' contestée : la réceptionniste pourra la déclarer à nouveau.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        Audit::record('export.cash_closings', 'export', 'Export Excel des clôtures de caisse du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y'));
        $rows = $this->reviewQuery($filters)->with(['user', 'reviewer'])->orderBy('business_date')->orderBy('user_id');
        $cell = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($rows, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Journée', 'Réceptionniste', 'Attendu', 'Déclaré', 'Écart', 'Espèces attendues', 'Espèces remises', 'Orange Money attendu', 'Orange Money remis', 'Wave attendu', 'Wave remis', 'Statut', 'Commentaire', 'Vérifiée par', 'Commentaire du gérant'], ';');
            $rows->chunk(500, function ($chunk) use ($out, $cell) {
                foreach ($chunk as $c) {
                    $money = fn ($v) => (int) round((float) $v);
                    fputcsv($out, array_map($cell, [
                        $c->business_date->format('d/m/Y'), $c->user?->name ?? '', $money($c->total_expected), $money($c->total_declared), $money($c->total_difference),
                        $money($c->expected['especes']['expected'] ?? 0), $money($c->declared['especes'] ?? 0),
                        $money($c->expected['orange_money']['expected'] ?? 0), $money($c->declared['orange_money'] ?? 0),
                        $money($c->expected['wave']['expected'] ?? 0), $money($c->declared['wave'] ?? 0),
                        $c->statusLabel(), $c->comment ?? '', $c->reviewer?->name ?? '', $c->review_comment ?? '',
                    ]), ';');
                }
            });
            fclose($out);
        }, 'clotures-caisse-'.$filters['from']->format('Y-m-d').'-au-'.$filters['to']->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reviewQuery(array $filters): Builder
    {
        return CashClosing::query()
            ->whereDate('business_date', '>=', $filters['from']->toDateString())->whereDate('business_date', '<=', $filters['to']->toDateString())
            ->when($filters['user'], fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($filters['status'], fn (Builder $q, string $s) => $q->where('status', $s));
    }

    /**
     * Days of the period (31 at most, before today) where someone had money in her till — payments
     * attributed to her or validated till expenses — but no closing. Today is still open.
     *
     * @return list<array{day: Carbon, user: string, amount: float}>
     */
    private function missing(Carbon $from, Carbon $to): array
    {
        $today = BusinessDay::today();
        $last = $to->copy()->min($today->copy()->subDay());
        $first = $from->copy()->max($last->copy()->subDays(30));
        $closed = CashClosing::query()->whereDate('business_date', '>=', $first->toDateString())->whereDate('business_date', '<=', $last->toDateString())
            ->get(['business_date', 'user_id'])->map(fn ($c) => $c->business_date->toDateString().'|'.$c->user_id)->flip();
        $names = User::query()->pluck('name', 'id');
        $missing = [];

        for ($day = $last->copy(); $day->greaterThanOrEqualTo($first); $day->subDay()) {
            [$start, $end] = BusinessDay::bounds($day);
            $owners = collect(FinanceStats::cashByUser($start, $end))->filter(fn ($row) => $row['user_id'] !== null)->mapWithKeys(fn ($row) => [$row['user_id'] => $row['total']]);
            Expense::query()->where('status', ExpenseStatus::Validee)->whereDate('business_date', $day->toDateString())->where('source', '!=', 'hors_caisse')
                ->whereNotNull('attributed_to')->pluck('attributed_to')->each(fn ($id) => $owners[$id] = $owners[$id] ?? 0);
            foreach ($owners as $userId => $amount) {
                if (! isset($closed[$day->toDateString().'|'.$userId])) {
                    $missing[] = ['day' => $day->copy(), 'user' => $names[$userId] ?? '?', 'amount' => (float) $amount];
                }
            }
        }

        return $missing;
    }

    private function dayParam(Request $request, string $key): ?Carbon
    {
        $value = $request->query($key);
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->min(BusinessDay::today());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Plain strings only, unknown values ignored: ?du=&au= business days (default: the last 7 days,
     * swapped, capped at 366 days), ?par=ID, ?statut=.
     *
     * @return array{from: Carbon, to: Carbon, user: int|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        $to = $this->dayParam($request, 'au') ?? BusinessDay::today();
        $from = $this->dayParam($request, 'du') ?? $to->copy()->subDays(6);
        [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
        $user = is_string($request->query('par')) ? $request->query('par') : '';
        $status = is_string($request->query('statut')) ? $request->query('statut') : '';

        return [
            'from' => $from->max($to->copy()->subDays(365)),
            'to' => $to,
            'user' => ctype_digit($user) ? (int) $user : null,
            'status' => array_key_exists($status, CashClosing::STATUSES) ? $status : null,
        ];
    }
}
