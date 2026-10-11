<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\StayRequest;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mode simple": the front-desk home (big action tiles with live counts) and the short task
 * lists behind them — departures, today's arrivals, amounts to collect, one search box. Read
 * only: every action posts to the existing routes (check-out, check-in, invoice payments…),
 * so the business rules stay in one place.
 */
class FrontDeskController extends Controller
{
    public function home(Request $request): View
    {
        $user = $request->user();

        return view('admin.home', [
            'tiles' => [
                'affecter' => $user->can('rooms.view') ? Room::query()->availableNow()->count() : null,
                'departs' => $user->can('affectations.view') ? [
                    'today' => $this->departuresQuery()->count(),
                    'overdue' => Reservation::query()->where('status', ReservationStatus::Enregistree)->where('check_out', '<=', now())->count(),
                ] : null,
                'demandes' => $user->can('affectations.view') ? StayRequest::query()->holding()->count() : null,
                'arrivees' => $user->can('reservations.view') ? $this->arrivalsQuery()->count() : null,
                'encaisser' => $user->can('invoices.view') ? $this->toCollectQuery()->count() : null,
            ],
            'roomCounts' => $user->can('rooms.view') ? DashboardController::roomCounts() : null,
            'finance' => $user->can('dashboard.revenue') ? $this->financeFigures() : null,
            // The manager sees every staff member's takings; the others only their own.
            'staffCash' => $user->can('dashboard.revenue') ? FinanceStats::cashByUser(...BusinessDay::current()) : null,
            'myCash' => $user->can('payments.create') && $user->cannot('dashboard.revenue') ? self::myCash($user->id) : null,
        ]);
    }

    /**
     * « Ma recette du jour »: what this staff member cashed during the business day (the
     * payments they recorded), per method; espèces = what they must hand over.
     *
     * @return array{total: float, count: int, byMethod: list<array{method: PaymentMethod, amount: float, share: int}>, cash: float}
     */
    public static function myCash(int $userId, ?CarbonInterface $day = null): array
    {
        [$from, $to] = BusinessDay::bounds($day ?? BusinessDay::today());
        $byMethod = FinanceStats::cashByMethod($from, $to, $userId);

        return [
            'total' => FinanceStats::cashBetween($from, $to, $userId),
            'count' => FinanceStats::paymentsCountBetween($from, $to, $userId),
            'byMethod' => $byMethod,
            'cash' => (float) collect($byMethod)->firstWhere('method', PaymentMethod::Especes)['amount'],
        ];
    }

    /**
     * Today's money at a glance (cash received by method, month to date, open balances).
     * Only computed for staff holding dashboard.revenue.
     *
     * @return array<string, mixed>
     */
    private function financeFigures(): array
    {
        [$dayStart, $dayEnd] = BusinessDay::current();
        [$monthStart, $monthEnd] = BusinessDay::monthBounds(BusinessDay::today());
        [$outstanding, $outstandingCount] = FinanceStats::outstanding();

        return [
            'today' => FinanceStats::cashBetween($dayStart, $dayEnd),
            'todayCount' => FinanceStats::paymentsCountBetween($dayStart, $dayEnd),
            'byMethod' => FinanceStats::cashByMethod($dayStart, $dayEnd),
            'month' => FinanceStats::cashBetween($monthStart, $monthEnd),
            'outstanding' => $outstanding,
            'outstandingCount' => $outstandingCount,
            'discountsToday' => (float) Reservation::query()
                ->where('discount_at', '>=', $dayStart)->where('discount_at', '<', $dayEnd)
                ->where('status', '!=', ReservationStatus::Annulee)
                ->sum('discount_amount'),
        ];
    }

    /**
     * Stays to check out by the end of the business day (overdue ones first).
     */
    public function departures(): View
    {
        $stays = $this->departuresQuery()
            ->with(['guest', 'room.roomType', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')])
            ->orderBy('check_out')
            ->get();

        return view('admin.front-desk.departures', [
            'stays' => $stays,
            'inProgress' => Reservation::query()->where('status', ReservationStatus::Enregistree)->count(),
        ]);
    }

    /**
     * Bookings expected today (or late, as long as their period isn't over), not checked in yet.
     */
    public function arrivals(): View
    {
        return view('admin.front-desk.arrivals', [
            'bookings' => $this->arrivalsQuery()
                ->with(['guest', 'room.roomType', 'invoice' => fn ($q) => $q->withSum('payments', 'amount')])
                ->orderBy('check_in')
                ->get(),
        ]);
    }

    /**
     * Open balances of live files (stays in progress or finished, bookings not cancelled).
     */
    public function collect(): View
    {
        return view('admin.front-desk.collect', [
            'invoices' => $this->toCollectQuery()
                ->with(['reservation.guest', 'reservation.room'])
                ->withSum('payments', 'amount')
                ->orderBy('issued_at')
                ->orderBy('id')
                ->paginate(30),
        ]);
    }

    /**
     * One box: room number, guest name/phone or invoice number.
     */
    public function search(Request $request): View
    {
        $q = trim(mb_substr($request->string('q')->toString(), 0, 60));
        $user = $request->user();
        $rooms = collect();
        $guests = collect();
        $invoices = collect();

        if ($q !== '') {
            $needle = '%'.mb_strtolower($q).'%';

            if ($user->can('rooms.view')) {
                $rooms = Room::query()
                    ->with(['roomType', 'currentStay.guest', 'pendingArrival', 'pendingRequest'])
                    ->whereRaw('lower(number) like ?', [$needle])
                    ->get()
                    ->sortBy(fn (Room $room) => $room->number, SORT_NATURAL)
                    ->take(20);
            }

            $guests = Guest::query()
                ->where(fn (Builder $w) => $w
                    ->whereRaw('lower(first_name) like ?', [$needle])
                    ->orWhereRaw('lower(last_name) like ?', [$needle])
                    ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$needle])
                    ->orWhereRaw('lower(company_name) like ?', [$needle])
                    ->orWhere('phone', 'like', '%'.$q.'%'))
                ->with(['reservations' => fn ($r) => $r->with('room')->latest('check_in')->limit(50)])
                ->orderBy('last_name')
                ->limit(20)
                ->get();

            if ($user->can('invoices.view')) {
                $invoices = Invoice::query()->with('reservation.guest')->whereRaw('lower(number) like ?', [$needle])->limit(10)->get();
            }
        }

        return view('admin.front-desk.search', compact('q', 'rooms', 'guests', 'invoices'));
    }

    private function departuresQuery(): Builder
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Enregistree)
            ->where('check_out', '<', BusinessDay::current()[1]);
    }

    private function arrivalsQuery(): Builder
    {
        return Reservation::query()
            ->bookings()
            ->whereIn('status', [ReservationStatus::EnAttente, ReservationStatus::Confirmee])
            ->where('check_in', '<', BusinessDay::current()[1])
            ->where('check_out', '>', now());
    }

    private function toCollectQuery(): Builder
    {
        return Invoice::query()
            ->where('status', '!=', InvoiceStatus::Payee)
            ->whereHas('reservation', fn (Builder $r) => $r->where('status', '!=', ReservationStatus::Annulee));
    }
}
