<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use App\Support\PeriodFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Recettes »: the payments actually received over a period (business days), one line per
 * payment with whose sale it is (the receptionist of the day — prise de service — else who
 * recorded it; « saisi par » when someone else typed it) and who gave the room, split into affectations and réservations
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
            // Per service (other services' sales: restaurant, beach & pool) — ignores the service filter.
            'byService' => $this->byService($this->query($request, ['service' => null] + $filters)),
            'payments' => $payments->with(['creator', 'attributedTo', 'invoice.sale', 'invoice.reservation.guest', 'invoice.reservation.room', 'invoice.reservation.attributedTo', 'invoice.reservation.checkedInBy', 'invoice.reservation.creator'])
                ->orderBy('paid_at')->orderBy('id')->paginate(100)->withQueryString(),
            'summary' => $this->summary($all),
            'byKind' => [Payment::KIND_STAY => $this->summary($stay), Payment::KIND_BOOKING => $this->summary($booking)],
            'staff' => $this->isManager($request) ? FinanceStats::cashByUser($filters['start'], $filters['end'], $filters['kind'], $filters['formula']) : [],
            'staffOptions' => $this->isManager($request) ? User::query()->whereIn('id', Payment::query()->whereNotNull('attributed_to')->distinct()->pluck('attributed_to'))->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /**
     * Same lines as the page, as a spreadsheet (CSV, semicolons, UTF-8 — opens in Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $payments = $this->query($request, $filters)
            ->with(['creator', 'attributedTo', 'invoice.sale', 'invoice.reservation.guest', 'invoice.reservation.room', 'invoice.reservation.attributedTo', 'invoice.reservation.checkedInBy', 'invoice.reservation.creator'])
            ->orderBy('paid_at')->orderBy('id');
        Audit::record('export.revenue', 'export', 'Export Excel des recettes du '.$filters['from']->format('d/m/Y').($filters['single'] ? '' : ' au '.$filters['to']->format('d/m/Y')));
        $name = 'recettes-'.$filters['from']->format('Y-m-d').($filters['from']->equalTo($filters['to']) ? '' : '-au-'.$filters['to']->format('Y-m-d')).'.csv';

        // Text typed by people (guest names…) must never be read by Excel as a formula
        // (CSV injection): a cell starting with = + - @ or a tab/CR gets a leading apostrophe.
        $cell = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;

        return response()->streamDownload(function () use ($payments, $cell) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Type', 'Dossier', 'Formule', 'Chambre', 'Client', 'Facture', 'Mode', 'Montant', 'Reçu', 'Rendu', 'Encaissé par', 'Affecté par', 'Saisi par'], ';');
            $payments->chunk(500, function ($chunk) use ($out, $cell) {
                foreach ($chunk as $p) {
                    $file = $p->invoice?->reservation;
                    $sale = $file ? null : $p->invoice?->sale;
                    fputcsv($out, array_map($cell, [
                        $p->paid_at->format('d/m/Y'), $p->paid_at->format('H:i'),
                        $sale ? $sale->department->getLabel() : ($p->isBookingPayment() ? 'Réservation' : 'Affectation'),
                        $file?->reference() ?? '', $file?->pricing_mode?->getLabel() ?? '', $file?->room?->number ?? '',
                        $file?->guest?->fullName() ?? ($sale?->label ?? ''), $p->invoice?->number ?? '', $p->method->getLabel(), (int) round((float) $p->amount),
                        $p->amount_received !== null ? (int) round((float) $p->amount_received) : '', $p->change_given !== null ? (int) round((float) $p->change_given) : '',
                        $p->attributedTo?->name ?? 'Non attribué', $file?->assignedBy()?->name ?? '', $p->creator?->name ?? '',
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

    /**
     * Cash per service over the same payments (amount and encaissements).
     *
     * @return list<array{department: Department, total: float, count: int}>
     */
    private function byService(Builder $payments): array
    {
        $rows = $payments->get(['id', 'amount', 'settlement', 'department']);

        return array_map(fn (Department $d) => [
            'department' => $d,
            'total' => (float) $rows->filter(fn (Payment $p) => $p->department === $d)->sum('amount'),
            'count' => $rows->filter(fn (Payment $p) => $p->department === $d)->map(fn (Payment $p) => $p->settlement ?? 'p'.$p->id)->unique()->count(),
        ], Department::cases());
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
            // Attributed sales (« prise de service »: the receptionist of the day), not who typed them.
            ->when($staff === 'aucun', fn (Builder $q) => $q->whereNull('attributed_to'))
            ->when(ctype_digit($staff), fn (Builder $q) => $q->where('attributed_to', (int) $staff))
            ->when($filters['method'], fn (Builder $q, PaymentMethod $m) => $q->where('method', $m))
            ->ofKind($filters['kind'])
            ->ofPricingMode($filters['formula'])
            ->ofDepartment($filters['service'] ?? null);
    }

    /**
     * Sanitised filters: a shortcut, one day (?jour=) or a custom range (?du=&au=), in business
     * days (future dates are brought back to today, a range is capped at 366 days); staff
     * (?par=ID|aucun, manager only), payment method (?mode=), kind (?dossier=affectation|
     * reservation, option B) and the file's formula (?formule=heure|journee|nuitee|sejour).
     * Unknown values are ignored.
     *
     * @return array{period: string, from: Carbon, to: Carbon, start: Carbon, end: Carbon, staff: string, method: PaymentMethod|null, kind: string|null, formula: PricingMode|null, single: bool}
     */
    private function filters(Request $request): array
    {
        // Query values are only ever read as plain strings: a forged ?mode[]=x (array) is ignored
        // instead of crashing the page.
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        ['period' => $period, 'from' => $from, 'to' => $to] = PeriodFilter::fromRequest($request, self::PERIODS);
        $today = BusinessDay::today();

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
            // ?formule= : the file's pricing mode (« mode d'affectation »), not the payment method (?mode=).
            'formula' => PricingMode::tryFrom($text('formule')),
            // ?service= : hebergement | restauration | plage_piscine (whitelist; anything else = all).
            'service' => Department::tryFrom($text('service')),
            'single' => $from->isSameDay($to),
            'isToday' => $to->isSameDay($today),
        ];
    }
}
