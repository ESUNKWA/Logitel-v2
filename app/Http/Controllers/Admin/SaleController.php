<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaleRequest;
use App\Models\CatalogItem;
use App\Models\Reservation;
use App\Models\Sale;
use App\Support\BusinessDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Sales of the other services (client request, 2026-11-25): restaurant, beach & pool — simple
 * entry from the service's catalogue, cashed by the Réception (« la réception encaisse tout »:
 * same payment form, same till, same prise de service as the stays), or charged to an
 * in-progress stay (« report sur la chambre »). The service comes from the URL (whitelisted),
 * never from the form.
 */
class SaleController extends Controller
{
    public function index(Request $request, string $department): View
    {
        $department = $this->department($department);
        $day = $this->day($request);
        [$start, $end] = BusinessDay::bounds($day);
        $status = $request->query('statut') === Sale::STATUS_CANCELLED ? Sale::STATUS_CANCELLED : Sale::STATUS_VALID;

        $base = Sale::query()->where('department', $department->value)->where('sold_at', '>=', $start)->where('sold_at', '<', $end);
        $valid = (clone $base)->valid()->with('lines')->get();

        return view('admin.sales.index', [
            'department' => $department,
            'day' => $day,
            'today' => BusinessDay::today(),
            'status' => $status,
            'sales' => (clone $base)->where('status', $status)
                ->with(['invoice' => fn ($q) => $q->withSum('payments', 'amount'), 'reservation.room', 'creator'])
                ->orderByDesc('sold_at')->orderByDesc('id')->paginate(50)->withQueryString(),
            'totals' => [
                'count' => $valid->count(),
                'amount' => (float) $valid->sum('total_amount'),
                'entries' => (int) $valid->sum(fn (Sale $sale) => $sale->entries()),
                'cancelled' => (clone $base)->where('status', Sale::STATUS_CANCELLED)->count(),
            ],
        ]);
    }

    public function create(Request $request, string $department): View
    {
        $department = $this->department($department);
        $items = CatalogItem::query()->ofDepartment($department)->active()->ordered()->get();
        $byCategory = fn ($list) => $list->groupBy(fn (CatalogItem $i) => $i->category ?? '');
        // Restaurant: today's menu first (business day); the other dishes stay sellable in a folded
        // « Hors menu » block (client request, 2026-11-28).
        $day = BusinessDay::today();

        return view('admin.sales.create', [
            'department' => $department,
            'items' => $byCategory($items->filter(fn (CatalogItem $i) => $i->servedOn($day))),
            'offMenu' => $byCategory($items->reject(fn (CatalogItem $i) => $i->servedOn($day))),
            'menuDay' => $department->servesByDay() ? $day : null,
            'stays' => $this->staysInProgress(),
            // « Ajouter une consommation » from an affectation pre-selects its room.
            'preselectedStay' => ctype_digit((string) $request->query('chambre')) ? (int) $request->query('chambre') : null,
        ]);
    }

    public function store(SaleRequest $request, string $department): RedirectResponse
    {
        $department = $request->department();

        // Cashing at once needs the payment permission too (the route only checks sales.create).
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        $sale = DB::transaction(function () use ($request, $department) {
            $stay = $request->filled('reservation_id') ? Reservation::query()->find((int) $request->validated('reservation_id')) : null;
            $sale = Sale::record($department, $request->quantities(), $request->validated('label'), $stay, $request->discount(), $request->validated('discount_reason'));

            if ($request->paymentLines() !== []) {
                $sale->invoice->settle($request->paymentLines(), now(), 'Vente '.$department->getLabel());
            }

            return $sale;
        });

        $invoice = $sale->invoice->refresh();
        $change = (float) $invoice->payments()->sum('change_given');
        $message = 'Vente enregistrée : '.number_format((float) $sale->total_amount, 0, ',', ' ').' FCFA'
            .($sale->isChargedToRoom() ? ', reportée sur la chambre N°'.$sale->reservation?->room?->number.' (réglée avec le séjour).'
                : ((float) $invoice->remainingAmount() > 0 ? ' — reste dû '.number_format((float) $invoice->remainingAmount(), 0, ',', ' ').' FCFA.' : ' (soldée).'))
            .($change > 0 ? ' Monnaie à rendre : '.number_format($change, 0, ',', ' ').' FCFA.' : '');

        return redirect()->route('admin.sales.show', [$department->slug(), $sale])->with('status', $message);
    }

    public function show(string $department, Sale $sale): View
    {
        $department = $this->department($department);
        abort_unless($sale->department === $department, 404);

        $sale->load(['lines', 'invoice.payments' => fn ($q) => $q->with('creator')->orderBy('paid_at'), 'reservation.room', 'reservation.guest', 'creator', 'cancelledBy']);

        return view('admin.sales.show', ['department' => $department, 'sale' => $sale, 'invoice' => $sale->invoice]);
    }

    public function cancel(Request $request, string $department, Sale $sale): RedirectResponse
    {
        $department = $this->department($department);
        abort_unless($sale->department === $department, 404);

        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:500']], [], ['cancel_reason' => 'motif']);
        $sale->cancel($request->user(), $data['cancel_reason']);

        return redirect()->route('admin.sales.show', [$department->slug(), $sale])->with('status', 'Vente '.$sale->reference().' annulée.');
    }

    public function receipt(Request $request, string $department, Sale $sale): View
    {
        $department = $this->department($department);
        abort_unless($sale->department === $department, 404);

        $sale->load(['lines', 'reservation.room', 'invoice.payments' => fn ($q) => $q->orderBy('paid_at')->orderBy('id')]);

        return view('admin.receipts.sale', [
            'sale' => $sale,
            'invoice' => $sale->invoice,
            'embedded' => $request->boolean('apercu'),
            'width' => (int) config('hotel.receipt.width') === 58 ? 58 : 80,
        ]);
    }

    /** Counter services only (whitelist): stays and room bookings have their own pages. */
    private function department(string $slug): Department
    {
        $department = Department::fromSlug($slug);
        abort_unless($department !== null && in_array($department, Department::counterServices(), true), 404);

        return $department;
    }

    /** ?jour=Y-m-d (a business day, never in the future), else today. */
    private function day(Request $request): Carbon
    {
        $today = BusinessDay::today();
        $value = $request->query('jour');
        try {
            $day = is_string($value) && $value !== '' ? Carbon::createFromFormat('!Y-m-d', $value) : null;
        } catch (Throwable) {
            $day = null;
        }

        return $day ? $day->min($today) : $today;
    }

    /** Stays in progress a sale can be charged to (room number order). */
    private function staysInProgress()
    {
        return Reservation::query()->where('status', ReservationStatus::Enregistree)
            ->with(['room', 'guest'])->get()
            ->filter(fn (Reservation $r) => $r->isStay())
            ->sortBy(fn (Reservation $r) => (string) $r->room?->number, SORT_NATURAL)->values();
    }
}
