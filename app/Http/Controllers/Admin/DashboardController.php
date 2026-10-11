<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * "Today" and "this month" follow the hotel's business day (journée commerciale, 06:00 → 05:59
 * by default — see BusinessDay), not the calendar day. Money figures are only computed for
 * users holding `dashboard.revenue`.
 */
class DashboardController extends Controller
{
    private const TREND_DAYS = 14;

    /**
     * /admin: the front-desk home in "Mode simple", the figures dashboard in "Mode complet".
     */
    public function home(Request $request): View
    {
        return $request->user()->usesSimpleUi()
            ? app(FrontDeskController::class)->home($request)
            : $this->index($request);
    }

    /**
     * Rooms per display state (Room::DISPLAY_STATES keys).
     *
     * @return array<string, int>
     */
    public static function roomCounts(): array
    {
        return [
            'disponible' => Room::query()->availableNow()->count(),
            'reservee' => Room::query()->reservedNow()->count(),
            'demande' => Room::query()->requestedNow()->count(),
            'occupee' => Room::query()->where('status', RoomStatus::Occupee)->count(),
            'nettoyage' => Room::query()->where('status', RoomStatus::Nettoyage)->count(),
            'hors_service' => Room::query()->where('status', RoomStatus::HorsService)->count(),
        ];
    }

    public function index(Request $request): View
    {
        $today = BusinessDay::today();
        [$dayStart, $dayEnd] = BusinessDay::current();

        $roomCounts = self::roomCounts();
        $totalRooms = array_sum($roomCounts);
        $sellableRooms = $totalRooms - $roomCounts['hors_service'];

        $data = [
            'today' => $today,
            'totalRooms' => $totalRooms,
            'availableRooms' => $roomCounts['disponible'],
            'occupiedRooms' => $roomCounts['occupee'],
            'reservedRooms' => $roomCounts['reservee'],
            'roomCounts' => $roomCounts,
            'occupancyRate' => $sellableRooms > 0 ? (int) round($roomCounts['occupee'] / $sellableRooms * 100) : 0,

            'todayCheckIns' => Reservation::query()
                ->where('check_in', '>=', $dayStart)
                ->where('check_in', '<', $dayEnd)
                ->whereIn('status', [ReservationStatus::Confirmee, ReservationStatus::Enregistree])
                ->count(),
            'todayCheckOuts' => Reservation::query()
                ->where('check_out', '>=', $dayStart)
                ->where('check_out', '<', $dayEnd)
                ->where('status', ReservationStatus::Enregistree)
                ->count(),
            'upcomingReservations' => Reservation::query()
                ->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
                ->where('check_in', '>', now())
                ->count(),
            'overdueCheckouts' => Reservation::query()
                ->where('status', ReservationStatus::Enregistree)
                ->where('check_out', '<=', now())
                ->count(),

            'upcomingArrivals' => Reservation::query()
                ->with(['guest', 'room'])
                ->whereIn('status', [ReservationStatus::Confirmee, ReservationStatus::EnAttente])
                ->where('check_in', '>=', $dayStart)
                ->orderBy('check_in')
                ->limit(6)
                ->get(),
            // In-house stays leaving by the end of the business day (overdue ones first).
            'todayDepartures' => Reservation::query()
                ->with(['guest', 'room'])
                ->where('status', ReservationStatus::Enregistree)
                ->where('check_out', '<', $dayEnd)
                ->orderBy('check_out')
                ->limit(6)
                ->get(),
        ];

        if ($request->user()->can('dashboard.revenue')) {
            $data += $this->moneyFigures($today, $dayStart, $dayEnd);
            $data['staffCash'] = FinanceStats::cashByUser($dayStart, $dayEnd);
        }

        // Staff who cash but don't see the hotel's revenue get their own takings of the day.
        $data['myCash'] = $request->user()->can('payments.create') && $request->user()->cannot('dashboard.revenue')
            ? FrontDeskController::myCash($request->user()->id)
            : null;

        return view('admin.dashboard', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function moneyFigures(Carbon $today, Carbon $dayStart, Carbon $dayEnd): array
    {
        [$monthStart, $monthEnd] = BusinessDay::monthBounds($today);

        // Same elapsed span of the previous business month, for a like-for-like delta.
        $previousStart = BusinessDay::monthBounds($today->copy()->subMonthNoOverflow())[0];
        $previousEnd = $previousStart->copy()->addSeconds((int) $monthStart->diffInSeconds(now()));

        $cashedThisMonth = (float) Payment::query()->where('paid_at', '>=', $monthStart)->where('paid_at', '<', $monthEnd)->sum('amount');
        $cashedPrevious = (float) Payment::query()->where('paid_at', '>=', $previousStart)->where('paid_at', '<', $previousEnd)->sum('amount');

        $openInvoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Payee)
            ->withSum('payments', 'amount')
            ->get(['id', 'amount']);

        return [
            // Cash actually received (payments), per business day / month.
            'revenueToday' => (float) Payment::query()->where('paid_at', '>=', $dayStart)->where('paid_at', '<', $dayEnd)->sum('amount'),
            'revenueMonth' => $cashedThisMonth,
            'revenueMonthDelta' => $cashedPrevious > 0 ? (int) round(($cashedThisMonth - $cashedPrevious) / $cashedPrevious * 100) : null,
            // Billed: stays starting this business month (not cancelled).
            'monthlyRevenue' => Reservation::query()
                ->where('check_in', '>=', $monthStart)
                ->where('check_in', '<', $monthEnd)
                ->where('status', '!=', ReservationStatus::Annulee)
                ->sum('total_amount'),
            // Discounts granted on those same stays (the figure above is after discounts).
            'monthlyDiscounts' => (float) Reservation::query()
                ->where('check_in', '>=', $monthStart)
                ->where('check_in', '<', $monthEnd)
                ->where('status', '!=', ReservationStatus::Annulee)
                ->sum('discount_amount'),
            'outstandingAmount' => (float) $openInvoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->amount - (float) $invoice->payments_sum_amount)),
            'outstandingCount' => $openInvoices->count(),
            'dailyRevenue' => $this->dailyRevenue($today, $dayEnd),
            'revenueByMode' => $this->revenueByMode($monthStart, $monthEnd),
            // Cash received per payment method (espèces / Orange Money / Wave), today and this month.
            'cashByMethodToday' => FinanceStats::cashByMethod($dayStart, $dayEnd),
            'cashByMethodMonth' => FinanceStats::cashByMethod($monthStart, $monthEnd),
        ];
    }

    /**
     * Cash received per business day over the last TREND_DAYS days (oldest first, zero-filled).
     *
     * @return list<array{date: Carbon, amount: float}>
     */
    private function dailyRevenue(Carbon $today, Carbon $dayEnd): array
    {
        $first = $today->copy()->subDays(self::TREND_DAYS - 1);
        $totals = [];

        Payment::query()
            ->where('paid_at', '>=', BusinessDay::bounds($first)[0])
            ->where('paid_at', '<', $dayEnd)
            ->get(['amount', 'paid_at'])
            ->each(function (Payment $payment) use (&$totals) {
                $key = BusinessDay::dateOf($payment->paid_at)->toDateString();
                $totals[$key] = ($totals[$key] ?? 0) + (float) $payment->amount;
            });

        $series = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $date = $first->copy()->addDays($i);
            $series[] = ['date' => $date, 'amount' => $totals[$date->toDateString()] ?? 0.0];
        }

        return $series;
    }

    /**
     * Billed amount and stay count per pricing mode for stays starting this business month.
     *
     * @return list<array{mode: PricingMode, amount: float, count: int}>
     */
    private function revenueByMode(Carbon $monthStart, Carbon $monthEnd): array
    {
        $rows = Reservation::query()
            ->where('check_in', '>=', $monthStart)
            ->where('check_in', '<', $monthEnd)
            ->where('status', '!=', ReservationStatus::Annulee)
            ->selectRaw('pricing_mode, sum(total_amount) as amount, count(*) as stays')
            ->groupBy('pricing_mode')
            ->get()
            ->keyBy(fn ($row) => $row->pricing_mode instanceof PricingMode ? $row->pricing_mode->value : $row->pricing_mode);

        return array_map(fn (PricingMode $mode) => [
            'mode' => $mode,
            'amount' => (float) ($rows[$mode->value]->amount ?? 0),
            'count' => (int) ($rows[$mode->value]->stays ?? 0),
        ], PricingMode::cases());
    }
}
