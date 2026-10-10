<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money figures shared by the front-desk home and the figures dashboard (only ever computed for
 * staff holding `dashboard.revenue`). Cash = payments actually received, by `paid_at`; "today"
 * and "this month" follow the business day (BusinessDay).
 */
class FinanceStats
{
    public static function cashBetween(CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Payment::query()->where('paid_at', '>=', $from)->where('paid_at', '<', $to)->sum('amount');
    }

    public static function paymentsCountBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        // One "encaissement" may be several lines (mixed payment): count settlements, not rows.
        return Payment::query()->where('paid_at', '>=', $from)->where('paid_at', '<', $to)
            ->selectRaw('count(distinct coalesce(cast(settlement as varchar(40)), cast(id as varchar(40)))) as n')->value('n') ?? 0;
    }

    /**
     * Cash received per payment method, every method listed (0 when none), with its share.
     *
     * @return list<array{method: PaymentMethod, amount: float, share: int}>
     */
    public static function cashByMethod(CarbonInterface $from, CarbonInterface $to): array
    {
        $sums = Payment::query()
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $to)
            ->selectRaw('method, sum(amount) as amount')
            ->groupBy('method')
            ->pluck('amount', 'method');
        $total = (float) $sums->sum();

        return array_map(fn (PaymentMethod $method) => [
            'method' => $method,
            'amount' => $amount = (float) ($sums[$method->value] ?? 0),
            'share' => $total > 0 ? (int) round($amount / $total * 100) : 0,
        ], PaymentMethod::cases());
    }

    /**
     * Open balances of live files (cancelled ones excluded): [amount, number of invoices].
     *
     * @return array{0: float, 1: int}
     */
    public static function outstanding(): array
    {
        $open = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Payee)
            ->whereHas('reservation', fn (Builder $r) => $r->where('status', '!=', ReservationStatus::Annulee))
            ->withSum('payments', 'amount')
            ->get(['id', 'amount']);

        return [(float) $open->sum(fn (Invoice $i) => max(0, (float) $i->amount - (float) $i->payments_sum_amount)), $open->count()];
    }
}
