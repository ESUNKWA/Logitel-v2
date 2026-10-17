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
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * « Recettes »: the payments actually received over a period (business days), one line per
 * payment with who cashed it and who gave the room, split into affectations and réservations
 * (a réservation's payment recorded before the guest arrived — Payment::isBookingPayment()). The manager (dashboard.revenue) sees every
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
        // Option B: réservation payments = recorded before the guest arrived (Payment::scopeBookingPayments()).
        // The two kind cards ignore the kind filter (both always shown, to compare and switch).
        $bothKinds = $this->query($request, ['kind' => null] + $filters);
        $bookingIds = (clone $bothKinds)->bookingPayments()->pluck('id')->flip();
        [$booking, $stay] = $bothKinds->get(['id', 'amount', 'method', 'settlement'])->partition(fn (Payment $p) => $bookingIds->has($p->id));

        return view('admin.revenue.index', [
            'filters' => $filters,
            'manager' => $this->isManager($request),
            'payments' => $payments->with(['creator', 'invoice.reservation.guest', 'invoice.reservation.room', 'invoice.reservation.checkedInBy', 'invoice.reservation.creator'])
                ->orderBy('paid_at')->orderBy('id')->paginate(100)->withQueryString(),
            'summary' => $this->summary($all),
            'byKind' => [Payment::KIND_STAY => $this->summary($stay), Payment::KIND_BOOKING => $this->summary($booking)],
            'staff' => $this->isManager($request) ? FinanceStats::cashByUser($filters['start'], $filters['end'], $filters['kind']) : [],
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

        // Text typed by people (guest names…) must never be read by Excel as a formula
        // (CSV injection): a cell starting with = + - @ or a tab/CR gets a leading apostrophe.
        $cell = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($payments, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Type', 'Dossier', 'Chambre', 'Client', 'Facture', 'Mode', 'Montant', 'Reçu', 'Rendu', 'Encaissé par', 'Affecté par'], ';');
            $payments->chunk(500, function ($chunk) use ($out, $cell) {
                foreach ($chunk as $p) {
                    $file = $p->invoice?->reservation;
                    fputcsv($out, array_map($cell, [
                        $p->paid_at->format('d/m/Y'), $p->paid_at->format('H:i'), $p->isBookingPayment() ? 'Réservation' : 'Affectation',
                        $file?->reference() ?? '', $file?->room?->number ?? '',
                        $file?->guest?->fullName() ?? '', $p->invoice?->number ?? '', $p->method->getLabel(), (int) round((float) $p->amount),
                        $p->amount_received !== null ? (int) round((float) $p->amount_received) : '', $p->change_given !== null ? (int) round((float) $p->change_given) : '',
                        $p->creator?->name ?? 'Non attribué', $file?->assignedBy()?->name ?? '',
                    ]), ';');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Total, number of settlements (a mixed payment counts once) and amount per method.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array{total: float, count: int, byMethod: list<array{method: PaymentMethod, amount: float}>}
     */
    private function summary($payments): array
    {
        return [
            'total' => (float) $payments->sum('amount'),
            'count' => $payments->map(fn (Payment $p) => $p->settlement ?? 'p'.$p->id)->unique()->count(),
            'byMethod' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $m) => ['method' => $m, 'amount' => (float) $payments->where('method', $m)->sum('amount')])->all(),
        ];
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
            ->when($filters['method'], fn (Builder $q, PaymentMethod $m) => $q->where('method', $m))
            ->ofKind($filters['kind']);
    }

    /**
     * Sanitised filters: a shortcut, one day (?jour=) or a custom range (?du=&au=), in business
     * days (future dates are brought back to today, a range is capped at 366 days); staff
     * (?par=ID|aucun, manager only), payment method (?mode=) and kind (?dossier=affectation|
     * reservation, option B). Unknown values are ignored.
     *
     * @return array{period: string, from: Carbon, to: Carbon, start: Carbon, end: Carbon, staff: string, method: PaymentMethod|null, kind: string|null, single: bool}
     */
    private function filters(Request $request): array
    {
        $today = BusinessDay::today();
        // Query values are only ever read as plain strings: a forged ?mode[]=x (array) is ignored
        // instead of crashing the page.
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $date = function (string $key) use ($text, $today): ?Carbon {
            try {
                $d = $text($key) !== '' ? Carbon::createFromFormat('!Y-m-d', $text($key)) : null;
            } catch (Throwable) {
                return null;
            }

            return $d?->min($today);
        };

        $period = $text('periode');
        if ($text('du') !== '' || $text('au') !== '') {
            $from = $date('du') ?? $date('au') ?? $today;
            $to = $date('au') ?? $from;
            [$from, $to] = $from->greaterThan($to) ? [$to, $from] : [$from, $to];
            $from = $from->max($to->copy()->subDays(365));
            $period = 'personnalisee';
        } elseif ($text('jour') !== '' && ($day = $date('jour'))) {
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

        $staff = $text('par');

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'start' => BusinessDay::bounds($from)[0],
            'end' => BusinessDay::bounds($to)[1],
            'staff' => $staff === 'aucun' || ctype_digit($staff) ? $staff : '',
            'method' => PaymentMethod::tryFrom($text('mode')),
            // ?dossier=affectation|reservation (whitelist; anything else = both).
            'kind' => in_array($text('dossier'), [Payment::KIND_STAY, Payment::KIND_BOOKING], true) ? $text('dossier') : null,
            'single' => $from->isSameDay($to),
            'isToday' => $to->isSameDay($today),
        ];
    }
}
