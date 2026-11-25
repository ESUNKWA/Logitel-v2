<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Department;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VenueBookingRequest;
use App\Models\CatalogItem;
use App\Models\Venue;
use App\Models\VenueBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

/**
 * Meetings & events (client request, 2026-11-25, step 2): room bookings (events.manage — the
 * Réception takes and cashes them), their weekly planning, the quote / confirmation document;
 * cancelling is the Gérant's (sales.cancel), only while nothing is cashed.
 */
class VenueBookingController extends Controller
{
    public const TABS = ['a-venir' => 'À venir', 'passees' => 'Passées', 'annulees' => 'Annulées'];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('onglet'), self::TABS) ? (string) $request->query('onglet') : 'a-venir';
        $query = VenueBooking::query()->with(['venue', 'sale.invoice' => fn ($q) => $q->withSum('payments', 'amount')]);
        $query = match ($tab) {
            'passees' => $query->notCancelled()->where('end_at', '<=', now())->orderByDesc('start_at'),
            'annulees' => $query->where('status', VenueBooking::STATUS_CANCELLED)->orderByDesc('start_at'),
            default => $query->notCancelled()->where('end_at', '>', now())->orderBy('start_at'),
        };

        return view('admin.events.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'bookings' => $query->paginate(30)->withQueryString(),
            'hasVenues' => Venue::query()->active()->exists(),
        ]);
    }

    /** Weekly planning: one line per venue, one column per day (?semaine=Y-m-d). */
    public function planning(Request $request): View
    {
        try {
            $value = $request->query('semaine');
            $day = is_string($value) && $value !== '' ? Carbon::createFromFormat('!Y-m-d', $value) : now();
        } catch (Throwable) {
            $day = now();
        }
        $start = $day->copy()->startOfWeek()->startOfDay();
        $end = $start->copy()->addWeek();

        $bookings = VenueBooking::query()->notCancelled()->where('start_at', '<', $end)->where('end_at', '>', $start)
            ->orderBy('start_at')->get()->groupBy(fn (VenueBooking $b) => $b->venue_id.'|'.$b->start_at->toDateString());

        return view('admin.events.planning', [
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i)),
            'venues' => Venue::query()->active()->ordered()->get(),
            'bookings' => $bookings,
        ]);
    }

    public function create(Request $request): View
    {
        $date = is_string($request->query('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('date')) ? $request->query('date') : null;

        return $this->form(new VenueBooking, ['venue_id' => ctype_digit((string) $request->query('salle')) ? (int) $request->query('salle') : null, 'event_date' => $date]);
    }

    public function store(VenueBookingRequest $request): RedirectResponse
    {
        // Taking a deposit needs the payment permission too.
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        $booking = DB::transaction(function () use ($request) {
            $booking = VenueBooking::book($request->bookingData(), $request->user());
            if ($request->paymentLines() !== []) {
                $booking->sale->invoice->settle($request->paymentLines(), now(), 'Acompte — réservation de salle');
            }

            return $booking;
        });

        $paid = (float) $booking->sale->invoice->refresh()->amountPaid();

        return redirect()->route('admin.events.show', $booking)->with('status', 'Salle « '.$booking->venue->name.' » réservée le '.$booking->start_at->format('d/m/Y').' de '
            .$booking->start_at->format('H\hi').' à '.$booking->end_at->format('H\hi').' — '.number_format((float) $booking->sale->total_amount, 0, ',', ' ').' FCFA'
            .($paid > 0 ? ', acompte encaissé : '.number_format($paid, 0, ',', ' ').' FCFA.' : '.'));
    }

    public function show(VenueBooking $booking): View
    {
        $booking->load(['venue', 'creator', 'confirmedBy', 'sale.lines', 'sale.cancelledBy', 'sale.invoice.payments' => fn ($q) => $q->with('creator')->orderBy('paid_at')]);

        return view('admin.events.show', ['booking' => $booking, 'sale' => $booking->sale, 'invoice' => $booking->sale->invoice]);
    }

    public function edit(VenueBooking $booking): View|RedirectResponse
    {
        if ($booking->isCancelled()) {
            return redirect()->route('admin.events.show', $booking)->withErrors(['venue_id' => 'Cette réservation est annulée : elle ne peut plus être modifiée.']);
        }

        return $this->form($booking->load('sale.lines'), []);
    }

    public function update(VenueBookingRequest $request, VenueBooking $booking): RedirectResponse
    {
        $booking->change($request->bookingData());

        return redirect()->route('admin.events.show', $booking)->with('status', 'Réservation mise à jour : '.number_format((float) $booking->sale->fresh()->total_amount, 0, ',', ' ').' FCFA.');
    }

    public function confirm(Request $request, VenueBooking $booking): RedirectResponse
    {
        $booking->confirm($request->user());

        return back()->with('status', 'Réservation confirmée.');
    }

    public function cancel(Request $request, VenueBooking $booking): RedirectResponse
    {
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:500']], [], ['cancel_reason' => 'motif']);
        $booking->cancel($request->user(), $data['cancel_reason']);

        return redirect()->route('admin.events.show', $booking)->with('status', 'Réservation annulée.');
    }

    /** Printable quote (option) or confirmation (A4). */
    public function document(VenueBooking $booking): View
    {
        $booking->load(['venue', 'sale.lines', 'sale.invoice.payments']);

        return view('admin.events.document', ['booking' => $booking, 'sale' => $booking->sale, 'invoice' => $booking->sale->invoice]);
    }

    /**
     * @param  array<string, mixed>  $prefill
     */
    private function form(VenueBooking $booking, array $prefill): View
    {
        $quantities = $booking->exists ? $booking->sale->lines->whereNotNull('catalog_item_id')->pluck('quantity', 'catalog_item_id')->all() : [];
        $items = CatalogItem::query()->ofDepartment(Department::Evenements)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', array_keys($quantities)))
            ->ordered()->get()->groupBy(fn (CatalogItem $i) => $i->category ?? '');

        return view('admin.events.form', [
            'booking' => $booking,
            'venues' => Venue::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $booking->venue_id))->ordered()->get(),
            'items' => $items,
            'quantities' => $quantities,
            'prefill' => $prefill,
        ]);
    }
}
