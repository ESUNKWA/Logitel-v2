<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Recettes »: the payments actually received over a period (business days), one line per
 * payment with who cashed it and who gave the room. The manager (dashboard.revenue) sees every
 * staff member and can filter by one; anyone else only ever sees their own payments.
 */
class RevenueController extends Controller
{
    /** Shortcut => label. */
    public const PERIODS = [
        'aujourdhui' => "Aujourd'hui",
        'hier' => 'Hier',
        'semaine' => 'Cette semaine',
        'mois' => 'Ce mois',
        'mois-dernier' => 'Mois dernier',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $payments = $this->query($request, $filters);
        $all = (clone $payments)->get(['id', 'amount', 'method', 'settlement']);

        return view('admin.revenue.index', [
            'filters' => $filters,
            'manager' => $this->isManager($request),
            'payments' => $payments->with(['creator', 'invoice.reservation.guest', 'invoice.reservation.room', 'invoice.reservation.checkedInBy', 'invoice.reservation.creator'])
                ->orderBy('paid_at')->orderBy('id')->paginate(100)->withQueryString(),
            'summary' => [
                'total' => (float) $all->sum('amount'),
                'count' => $all->map(fn (Payment $p) => $p->settlement ?? 'p'.$p->id)->unique()->count(),
                'byMethod' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => ['method' => $m, 'amount' => (float) $all->where('method', $m)->sum('amount')])->all(),
            ],
            'staff' => $this->isManager($request) ? FinanceStats::cashByUser($filters['start'], $filters['end']) : [],
            'staffOptions' => $this->isManager($request) ? User::query()->whereIn('id', Payment::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /**
     * Same lines as the page, as a spreadsheet (CSV, semicolons, UTF-8 — opens in Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $payments = $this->query($request, $filters)
            ->with(['creator', 'invoice.reservation.guest', 'invoice.reservation.room', 'invoice.reservation.checkedInBy', 'invoice.reservation.creator'])
            ->orderBy('paid_at')->orderBy('id');
        $name = 'recettes-'.$filters['from']->format('Y-m-d').($filters['from']->equalTo($filters['to']) ? '' : '-au-'.$filters['to']->format('Y-m-d')).'.csv';

        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Dossier', 'Chambre', 'Client', 'Facture', 'Mode', 'Montant', 'Reçu', 'Rendu', 'Encaissé par', 'Affecté par'], ';');
            $payments->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    $file = $p->invoice?->reservation;
                    fputcsv($out, [
                        $p->paid_at->format('d/m/Y'), $p->paid_at->format('H:i'), $file?->reference() ?? '', $file?->room?->number ?? '',
                        $file?->guest?->fullName() ?? '', $p->invoice?->number ?? '', $p->method->getLabel(), (int) round((float) $p->amount),
                        $p->amount_received !== null ? (int) round((float) $p->amount_received) : '', $p->change_given !== null ? (int) round((float) $p->change_given) : '',
                        $p->creator?->name ?? 'Non attribué', $file?->assignedBy()?->name ?? '',
                    ], ';');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function isManager(Request $request): bool
    {
        return $request->user()->can('dashboard.revenue');
    }

    /**
     * Payments of the period, restricted to the user's own unless they're the manager.
     */
    private function query(Request $request, array $filters): Builder
    {
        $staff = $this->isManager($request) ? $filters['staff'] : (string) $request->user()->id;

        return Payment::query()
            ->where('paid_at', '>=', $filters['start'])->where('paid_at', '<', $filters['end'])
            ->when($staff === 'aucun', fn (Builder $q) => $q->whereNull('created_by'))
            ->when(ctype_digit($staff), fn (Builder $q) => $q->where('created_by', (int) $staff))
            ->when($filters['method'], fn (Builder $q, PaymentMethod $m) => $q->where('method', $m));
    }

    /**
     * Sanitised filters: a shortcut, one day (?jour=) or a custom range (?du=&au=), in business
     * days (future dates are brought back to today, a range is capped at 366 days); staff
     * (?par=ID|aucun, manager only) and payment method (?mode=). Unknown values are ignored.
     *
     * @return array{period: string, from: Carbon, to: Carbon, start: Carbon, end: Carbon, staff: string, method: PaymentMethod|null, single: bool}
     */
    private function filters(Request $request): array
    {
        $today = BusinessDay::today();
        $date = function (string $key) use ($request, $today): ?Carbon {
            try {
                $d = $request->filled($key) ? Carbon::createFromFormat('!Y-m-d', $request->string($key)->toString()) : null;
            } catch (Throwable) {
                return null;
            }

            return $d?->min($today);
        };

        $period = $request->string('periode')->toString();
        if ($request->filled('du') || $request->filled('au')) {
            $from = $date('du') ?? $date('au') ?? $today;
            $to = $date('au') ?? $from;
            [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
            $from = $from->max($to->copy()->subDays(365));
            $period = 'personnalisee';
        } elseif ($request->filled('jour') && ($day = $date('jour'))) {
            [$from, $to, $period] = [$day, $day->copy(), 'jour'];
        } else {
            $period = array_key_exists($period, self::PERIODS) ? $period : 'aujourdhui';
            [$from, $to] = match ($period) {
                'hier' => [$today->copy()->subDay(), $today->copy()->subDay()],
                'semaine' => [$today->copy()->startOfWeek(), $today->copy()],
                'mois' => [$today->copy()->startOfMonth(), $today->copy()],
                'mois-dernier' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
                default => [$today->copy(), $today->copy()],
            };
        }

        $staff = $request->string('par')->toString();

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'start' => BusinessDay::bounds($from)[0],
            'end' => BusinessDay::bounds($to)[1],
            'staff' => $staff === 'aucun' || ctype_digit($staff) ? $staff : '',
            'method' => PaymentMethod::tryFrom($request->string('mode')->toString()),
            'single' => $from->isSameDay($to),
            'isToday' => $to->isSameDay($today),
        ];
    }
}
