<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Expense;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Sale;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBooking;
use App\Support\BusinessDay;
use App\Support\FinanceStats;
use App\Support\NavGroups;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                'arrivees' => $user->can('reservations.view') ? $this->arrivalsQuery()->count() : null,
            ],
            'services' => self::otherServices($user),
            ...self::moneyBlocks($user),
        ]);
    }

    /**
     * « Autres services » on the réception's home (client request, 2026-11-28): one tile per
     * service the user may sell for — the same list as the « Plus » menu (NavGroups) — with
     * today's figures (as on the sales page: valid sales of the business day, amount, beach
     * entries) and a direct « Nouvelle vente » button; the venue bookings with today's and
     * upcoming events. Réception only: null for the manager (dashboard.revenue).
     *
     * @return array{sales: list<array{department: Department, count: int, amount: float, entries: int|null, menu: list<string>}>, events: array{today: int, upcoming: int, canBook: bool}|null}|null
     */
    public static function otherServices(User $user): ?array
    {
        if ($user->can('dashboard.revenue')) {
            return null;
        }
        [$dayStart, $dayEnd] = BusinessDay::current();

        $departments = NavGroups::saleServices($user);
        $sold = $departments === [] ? collect() : Sale::query()->valid()
            ->whereIn('department', array_map(fn (Department $d) => $d->value, $departments))
            ->where('sold_at', '>=', $dayStart)->where('sold_at', '<', $dayEnd)
            ->with('lines')->get()
            ->groupBy(fn (Sale $sale) => $sale->department instanceof Department ? $sale->department->value : (string) $sale->department);

        $today = BusinessDay::today();
        $sales = array_map(function (Department $department) use ($sold, $today) {
            $day = $sold->get($department->value, collect());

            return [
                'department' => $department,
                'count' => $day->count(),
                'amount' => (float) $day->sum('total_amount'),
                'entries' => $department->countsEntries() ? (int) $day->sum(fn (Sale $sale) => $sale->entries()) : null,
                // Restaurant: today's dishes of the weekly menu (client request, 2026-11-28).
                'menu' => $department->servesByDay() ? CatalogItem::query()->ofDepartment($department)->active()->ordered()->get()
                    ->filter(fn (CatalogItem $i) => $i->isOnWeeklyMenu() && $i->servedOn($today))->pluck('name')->values()->all() : [],
            ];
        }, $departments);

        $events = Department::Evenements->isOffered() && $user->can('events.manage') ? [
            'today' => VenueBooking::query()->notCancelled()->where('start_at', '<', $dayEnd)->where('end_at', '>', $dayStart)->count(),
            'upcoming' => VenueBooking::query()->notCancelled()->where('start_at', '>=', $dayEnd)->count(),
            'canBook' => Venue::query()->active()->exists(),
        ] : null;

        return $sales === [] && $events === null ? null : ['sales' => $sales, 'events' => $events];
    }

    /**
     * The home page's live figures for this user — the same data the page renders and
     * LiveFiguresController refreshes (each only computed when the user may see it):
     * room counts, the hotel's money (manager), every staff member's takings (manager) and
     * the user's own takings (anyone else who cashes), and the free rooms (réception only).
     *
     * @return array{roomCounts: array<string, int>|null, finance: array<string, mixed>|null, staffCash: list<array<string, mixed>>|null, myCash: array<string, mixed>|null, freeRooms: Collection<string, Collection<int, Room>>|null, dueToday: array{0: float, 1: int}|null}
     */
    public static function moneyBlocks(User $user): array
    {
        return [
            'roomCounts' => $user->can('rooms.view') ? DashboardController::roomCounts() : null,
            'finance' => $user->can('dashboard.revenue') ? self::financeFigures() : null,
            // The manager sees every staff member's takings; the others only their own.
            'staffCash' => $user->can('dashboard.revenue') ? FinanceStats::cashByUser(...BusinessDay::current()) : null,
            'myCash' => $user->can('payments.create') && $user->cannot('dashboard.revenue') ? self::myCash($user->id) : null,
            // Receptionists (not the manager) see the free rooms themselves on their home (client request).
            'freeRooms' => $user->can('rooms.view') && $user->cannot('dashboard.revenue') ? self::freeRooms() : null,
            // Réception: what is still to cash by the end of the business day (the manager has the full balance in `finance`).
            'dueToday' => $user->can('invoices.view') && $user->cannot('dashboard.revenue') ? FinanceStats::outstandingDueBy(BusinessDay::current()[1]) : null,
        ];
    }

    /**
     * Rooms free right now (availableNow), grouped by room type name, types and numbers in natural order.
     *
     * @return Collection<string, Collection<int, Room>>
     */
    public static function freeRooms(): Collection
    {
        return Room::query()->availableNow()->with('roomType')->get()
            ->sort(fn (Room $a, Room $b) => strnatcasecmp((string) $a->number, (string) $b->number))
            ->groupBy(fn (Room $room) => $room->roomType?->name ?? 'Sans type')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($rooms) => $rooms->values());
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
            'booking' => FinanceStats::cashBetween($from, $to, $userId, Payment::KIND_BOOKING),
            'count' => FinanceStats::paymentsCountBetween($from, $to, $userId),
            'byMethod' => $byMethod,
            'cash' => (float) collect($byMethod)->firstWhere('method', PaymentMethod::Especes)['amount'],
            // Validated expenses paid in cash from this till (V2): deducted from the cash to hand over.
            'cashExpenses' => Expense::tillOutflows($day ?? BusinessDay::today(), $userId)[PaymentMethod::Especes->value],
        ];
    }

    /**
     * Today's money at a glance (cash received by method, month to date, open balances).
     * Only computed for staff holding dashboard.revenue.
     *
     * @return array<string, mixed>
     */
    public static function financeFigures(): array
    {
        [$dayStart, $dayEnd] = BusinessDay::current();
        [$monthStart, $monthEnd] = BusinessDay::monthBounds(BusinessDay::today());
        [$outstanding, $outstandingCount] = FinanceStats::outstanding();

        return [
            'today' => FinanceStats::cashBetween($dayStart, $dayEnd),
            'todayBooking' => FinanceStats::cashBetween($dayStart, $dayEnd, null, Payment::KIND_BOOKING),
            'todayCount' => FinanceStats::paymentsCountBetween($dayStart, $dayEnd),
            'byMethod' => FinanceStats::cashByMethod($dayStart, $dayEnd),
            'month' => FinanceStats::cashBetween($monthStart, $monthEnd),
            'monthBooking' => FinanceStats::cashBetween($monthStart, $monthEnd, null, Payment::KIND_BOOKING),
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
     * « Aujourd'hui » (?periode=aujourdhui, the home card's link): only what is due by the end
     * of the business day (Invoice::scopeDueBy); « Tout » (default): every open balance.
     */
    public function collect(Request $request): View
    {
        $dayEnd = BusinessDay::current()[1];
        $period = $request->query('periode') === 'aujourdhui' ? 'aujourdhui' : 'tout';
        // Free-text search (Invoice::scopeSearch), combined with the tab; the counts follow it.
        $q = trim(mb_substr($request->string('q')->toString(), 0, 80));
        $base = fn () => $this->toCollectQuery()->when($q !== '', fn (Builder $query) => $query->search($q));

        return view('admin.front-desk.collect', [
            'period' => $period,
            'q' => $q,
            'counts' => [
                'aujourdhui' => $base()->dueBy($dayEnd)->count(),
                'tout' => $base()->count(),
            ],
            'invoices' => $base()
                ->when($period === 'aujourdhui', fn (Builder $query) => $query->dueBy($dayEnd))
                ->with(['reservation.guest', 'reservation.room', 'sale.reservation.room'])
                ->withSum('payments', 'amount')
                ->orderBy('issued_at')
                ->orderBy('id')
                ->paginate(30)
                ->withQueryString(),
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
            ->live();
    }
}
