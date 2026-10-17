<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Money figures shared by the front-desk home and the figures dashboard. The hotel's totals are
 * only ever computed for staff holding `dashboard.revenue`; with `$byUser`, the same figures
 * restricted to the payments one staff member recorded (« Ma recette du jour »). Cash =
 * payments actually received, by `paid_at`; "today" and "this month" follow the business day.
 */
class FinanceStats
{
    private static function payments(CarbonInterface $from, CarbonInterface $to, ?int $byUser, ?string $kind = null): Builder
    {
        return Payment::query()->where('paid_at', '>=', $from)->where('paid_at', '<', $to)
            ->when($byUser !== null, fn (Builder $q) => $q->where('created_by', $byUser))
            ->ofKind($kind);
    }

    /**
     * `$kind` (Payment::KIND_BOOKING / KIND_STAY) keeps one side of the option-B split
     * (« dont réservations » on the home page and dashboard).
     */
    public static function cashBetween(CarbonInterface $from, CarbonInterface $to, ?int $byUser = null, ?string $kind = null): float
    {
        return (float) self::payments($from, $to, $byUser, $kind)->sum('amount');
    }

    public static function paymentsCountBetween(CarbonInterface $from, CarbonInterface $to, ?int $byUser = null): int
    {
        // One "encaissement" may be several lines (mixed payment): count settlements, not rows.
        return self::payments($from, $to, $byUser)
            ->selectRaw('count(distinct coalesce(cast(settlement as varchar(40)), cast(id as varchar(40)))) as n')->value('n') ?? 0;
    }

    /**
     * Cash received per payment method, every method listed (0 when none), with its share.
     *
     * @return list<array{method: PaymentMethod, amount: float, share: int}>
     */
    public static function cashByMethod(CarbonInterface $from, CarbonInterface $to, ?int $byUser = null): array
    {
        $sums = self::payments($from, $to, $byUser)
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
     * Cash received per staff member who recorded it (« Recettes par réceptionniste »), largest
     * first; payments without an author (recorded before authorship existed) are grouped apart.
     * `$kind` (Payment::KIND_*) keeps only réservation or affectation payments (Recettes filter).
     *
     * @return list<array{user_id: int|null, name: string, total: float, count: int, booking: float, byMethod: array<string, float>}>
     */
    public static function cashByUser(CarbonInterface $from, CarbonInterface $to, ?string $kind = null): array
    {
        $amounts = self::payments($from, $to, null, $kind)
            ->selectRaw('created_by, method, sum(amount) as amount')
            ->groupBy('created_by', 'method')
            ->toBase()->get();
        // One "encaissement" may be several lines (mixed payment): count settlements, not rows.
        $counts = self::payments($from, $to, null, $kind)
            ->selectRaw('created_by, count(distinct coalesce(cast(settlement as varchar(40)), cast(id as varchar(40)))) as n')
            ->groupBy('created_by')
            ->toBase()->pluck('n', 'created_by');
        $names = User::query()->whereIn('id', $amounts->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        // Each one's réservation payments (option B), for the « dont réservations » column.
        $bookings = self::payments($from, $to, null, $kind)->bookingPayments()
            ->selectRaw('created_by, sum(amount) as amount')
            ->groupBy('created_by')
            ->toBase()->pluck('amount', 'created_by');

        return $amounts->groupBy(fn ($r) => (string) $r->created_by)
            ->map(fn ($lines, $userId) => [
                'user_id' => $userId === '' ? null : (int) $userId,
                'name' => $userId === '' ? 'Non attribué (anciens paiements)' : ($names[$userId] ?? 'Compte supprimé'),
                'total' => (float) $lines->sum('amount'),
                'count' => (int) ($counts[$userId] ?? 0),
                'booking' => (float) ($bookings[$userId] ?? 0),
                'byMethod' => collect(PaymentMethod::cases())->mapWithKeys(fn (PaymentMethod $m) => [$m->value => (float) $lines->where('method', $m->value)->sum('amount')])->all(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
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
