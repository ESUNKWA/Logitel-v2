<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckInRequest;
use App\Http\Requests\Admin\ReservationRequest;
use App\Models\BookingRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\BusinessDay;
use App\Support\RoomAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Réservations: stays booked ahead (origin "reservation"). The invoice is created with the
 * réservation and a deposit can be taken; when the guest arrives (checkIn) the file becomes an
 * affectation, handled by AffectationController from then on. A no-show is left to the manager:
 * cancel it, or edit it to move ("reporter") it to a later date.
 */
class ReservationController extends Controller
{
    /**
     * List tabs (client request, 2026-11-10: bookings still to assign apart from those already
     * assigned, to make arrivals easier): key => label, statuses, order of the arrival date.
     */
    public const TABS = [
        'a-affecter' => ['label' => 'À affecter', 'statuses' => [ReservationStatus::EnAttente, ReservationStatus::Confirmee], 'order' => 'asc'],
        'affectees' => ['label' => 'Déjà affectées', 'statuses' => [ReservationStatus::Enregistree, ReservationStatus::Terminee], 'order' => 'desc'],
        'annulees' => ['label' => 'Annulées', 'statuses' => [ReservationStatus::Annulee], 'order' => 'desc'],
    ];

    /**
     * Status filter inside the « À affecter » tab.
     */
    private const FILTERS = [
        'en_attente' => [ReservationStatus::EnAttente],
        'confirmee' => [ReservationStatus::Confirmee],
    ];

    public function index(Request $request): View
    {
        $text = fn (string $key): string => is_string($value = $request->query($key)) ? $value : '';
        $status = $text('status');
        // Older links (?status=honoree|annulee) open the matching tab.
        $tab = $text('onglet');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = match ($status) {
                'honoree' => 'affectees',
                'annulee' => 'annulees',
                default => 'a-affecter',
            };
        }
        $status = $tab === 'a-affecter' && array_key_exists($status, self::FILTERS) ? $status : '';
        $room = $text('room');

        $base = Reservation::query()
            ->bookings()
            ->when($room !== '', fn ($query) => $query->whereHas('room', fn ($q) => $q->where('number', $room)));

        $reservations = (clone $base)
            ->with(['guest', 'room', 'invoice', 'creator'])
            ->whereIn('status', self::FILTERS[$status] ?? self::TABS[$tab]['statuses'])
            // To assign: the nearest arrival first; the others: the latest first.
            ->orderBy('check_in', self::TABS[$tab]['order'])
            ->orderBy('id', self::TABS[$tab]['order'])
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn (array $t) => $t['label']),
            'counts' => collect(self::TABS)->map(fn (array $t) => (clone $base)->whereIn('status', $t['statuses'])->count()),
            'status' => $status,
            'room' => $room,
            'filters' => ['en_attente' => 'En attente', 'confirmee' => 'Confirmée'],
            'todayEnd' => BusinessDay::current()[1],
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $reservation = new Reservation;
        $bookingRequest = null;
        $prefillClient = [];

        // From an online booking request (« Demandes en ligne »): pre-fill the form with it.
        if ($request->filled('demande')) {
            $bookingRequest = BookingRequest::query()->with('roomType')->find($request->integer('demande'));
            if (! $bookingRequest || ! $bookingRequest->isPending()) {
                return redirect()->route('admin.booking-requests.index')->withErrors(['demande' => 'Cette demande en ligne a déjà été traitée.']);
            }

            $reservation->fill([
                'pricing_mode' => $bookingRequest->pricing_mode,
                'check_in' => $bookingRequest->check_in,
                'duration' => $bookingRequest->duration,
                'adults' => $bookingRequest->adults,
                'children' => $bookingRequest->children,
                'notes' => trim('Demande en ligne n°'.$bookingRequest->id.($bookingRequest->email ? " — e-mail : {$bookingRequest->email}" : '')
                    .($bookingRequest->message ? "\n".$bookingRequest->message : '')),
            ]);
            if ($guest = $bookingRequest->matchingGuest()) {
                $reservation->guest_id = $guest->id;
                $reservation->setRelation('guest', $guest);
            } else {
                $prefillClient = ['phone' => $bookingRequest->phone, 'first_name' => $bookingRequest->first_name, 'last_name' => $bookingRequest->last_name];
            }
        }

        // From the planning (V2): a free spot clicked → room, date and time pre-filled. The
        // values only pre-fill the form; the usual checks run when it's sent.
        $prefillRoomId = null;
        $roomParam = $request->query('chambre');
        if (! $bookingRequest && is_string($roomParam) && ctype_digit($roomParam)) {
            $room = Room::query()->with('roomType')->whereKey((int) $roomParam)->where('status', '!=', RoomStatus::HorsService)->first();
            if ($room && $room->roomType?->is_active) {
                $reservation->setRelation('room', $room);
                $prefillRoomId = $room->id;
                $date = $request->query('date');
                $time = $request->query('heure');
                if (is_string($date) && is_string($time) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)) {
                    try {
                        $arrival = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time);
                        if ($arrival->isFuture()) {
                            $reservation->check_in = $arrival;
                        }
                    } catch (\Throwable) {
                        // ignored: the form simply starts without an arrival
                    }
                }
            }
        }

        return view('admin.reservations.create', [
            'prefillRoomId' => $prefillRoomId,
            'reservation' => $reservation,
            'statuses' => [ReservationStatus::EnAttente, ReservationStatus::Confirmee],
            'bookingRequest' => $bookingRequest,
            'prefillClient' => $prefillClient,
            'roomTypes' => $this->roomTypeOptions(),
        ]);
    }

    public function store(ReservationRequest $request): RedirectResponse
    {
        // Taking a deposit needs the payment permission too.
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        $reservation = DB::transaction(function () use ($request) {
            // Serialise concurrent bookings of this room: lock it and re-check before writing.
            [$start, $end] = $request->period();
            Room::lockAvailable((int) $request->validated('room_id'), $start, $end, null, 'room_id', null, bookingsBlock: true);

            // Created from an online booking request: it's handled once (locked), in this transaction.
            $bookingRequest = null;
            if ($request->filled('booking_request_id')) {
                $bookingRequest = BookingRequest::query()->lockForUpdate()->find((int) $request->validated('booking_request_id'));
                if (! $bookingRequest?->isPending()) {
                    throw ValidationException::withMessages(['booking_request_id' => 'Cette demande en ligne a déjà été traitée.']);
                }
            }

            $reservation = Reservation::create([...$request->reservationAttributes(), 'guest_id' => $request->resolveGuestId(), 'origin' => Reservation::ORIGIN_RESERVATION]);

            $bookingRequest?->forceFill([
                'status' => BookingRequest::STATUS_HANDLED,
                'reservation_id' => $reservation->id,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ])->save();

            if ($request->paymentLines() !== []) {
                $reservation->invoice->settle($request->paymentLines(), now(), 'Acompte à la réservation');
            }

            return $reservation;
        });

        $deposit = (float) $reservation->invoice->refresh()->amountPaid();
        $change = (float) $reservation->invoice->payments()->sum('change_given');

        return redirect()
            ->route('admin.reservations.index')
            ->with('receipt', $reservation->id)
            ->with('status', 'Réservation créée : chambre N°'.$reservation->room->number.' le '.$reservation->check_in->format('d/m/Y à H\hi')
                .' ('.$reservation->billingLabel().').'.($deposit > 0 ? ' Acompte encaissé : '.number_format($deposit, 0, ',', ' ').' FCFA.' : '')
                .($change > 0 ? ' Monnaie à rendre : '.number_format($change, 0, ',', ' ').' FCFA.' : ''));
    }

    public function edit(Reservation $reservation): View|RedirectResponse
    {
        abort_unless($reservation->isBooking(), 404);

        // Once the guest has arrived, the file is handled on the affectations side.
        if ($reservation->hasStarted()) {
            return redirect()->route('admin.affectations.show', $reservation);
        }

        $reservation->load(['guest', 'room.roomType', 'invoice', 'creator']);

        return view('admin.reservations.edit', [
            'reservation' => $reservation,

            'statuses' => [ReservationStatus::EnAttente, ReservationStatus::Confirmee],
            'currentRoomOption' => $reservation->room ? [
                'id' => $reservation->room->id,
                'label' => "N°{$reservation->room->number} — {$reservation->room->roomType?->name}",
                'rate' => $reservation->unit_rate, // the rate agreed when booking (kept while room and mode don't change)
            ] : null,
            'roomTypes' => $this->roomTypeOptions($reservation->room?->room_type_id),
        ]);
    }

    /**
     * Room types offered in the form's first choice (client request, 2026-10-19: the type comes
     * first, then only its modes): active types with at least one rate, natural name order — plus
     * the booking's own type when editing, even if it was deactivated since.
     *
     * @return Collection<int, RoomType>
     */
    private function roomTypeOptions(?int $keepTypeId = null): Collection
    {
        return RoomType::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($keepTypeId, fn ($q) => $q->orWhere('id', $keepTypeId)))
            ->get()
            ->filter(fn (RoomType $type) => $type->id === $keepTypeId
                || collect(PricingMode::cases())->contains(fn (PricingMode $mode) => $mode->rateFor($type) !== null))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function update(ReservationRequest $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->isBooking() && ! $reservation->hasStarted(), 404);

        // A further deposit needs the payment permission too.
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        $settled = collect();

        DB::transaction(function () use ($request, $reservation, &$settled) {
            [$start, $end] = $request->period();
            Room::lockAvailable((int) $request->validated('room_id'), $start, $end, $reservation->id, 'room_id', null, $request->bookingsBlock());

            // The booking's own client, corrected in place (before a possible replacement is resolved).
            if ($client = $request->currentClientAttributes()) {
                $reservation->guest->update($client);
            }

            $reservation->update([...$request->reservationAttributes(), 'guest_id' => $request->resolveGuestId()]);

            if ($request->paymentLines() !== []) {
                $settled = $reservation->invoice->refresh()->settle($request->paymentLines(), now(), 'Acompte à la modification de la réservation');
            }
        });

        $deposit = (float) $settled->sum('amount');
        $change = (float) $settled->sum('change_given');

        $redirect = redirect()->route('admin.reservations.index');
        if ($deposit > 0) {
            $redirect->with('receipt', $reservation->id);
        }

        return $redirect
            ->with('status', 'Réservation mise à jour.'
                .($deposit > 0 ? ' Acompte encaissé : '.number_format($deposit, 0, ',', ' ').' FCFA.' : '')
                .($change > 0 ? ' Monnaie à rendre : '.number_format($change, 0, ',', ' ').' FCFA.' : ''));
    }

    /**
     * « Arrivée du client »: the file, its deposit and what is left to pay, with an optional
     * encaissement posted together with the check-in (checkIn).
     */
    public function arrival(Reservation $reservation): View|RedirectResponse
    {
        abort_unless($this->canArrive($reservation), 404);

        if ($error = $this->arrivalError($reservation)) {
            return back()->withErrors($error);
        }

        $reservation->load(['guest', 'room.roomType', 'invoice']);

        return view('admin.reservations.arrival', [
            'reservation' => $reservation,
            'invoice' => $reservation->invoice,
        ]);
    }

    /**
     * The guest arrives: the réservation is checked in and becomes an affectation (the model
     * event puts the room in "occupee"). What is left after the deposit can be cashed at the same
     * time — all, part or nothing — in the same transaction: a refused payment checks nothing in.
     */
    public function checkIn(CheckInRequest $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($this->canArrive($reservation), 404);

        // Taking money at check-in needs the payment permission too (the route only checks reservations.manage).
        abort_if($request->paymentLines() !== [] && $request->user()->cannot('payments.create'), 403);

        if ($error = $this->arrivalError($reservation)) {
            return back()->withErrors($error)->withInput();
        }

        // An unpaid booking doesn't hold its room: someone may have taken the slot meanwhile.
        DB::transaction(function () use ($request, $reservation) {
            Room::lockAvailable($reservation->room_id, $reservation->check_in, $reservation->check_out, $reservation->id, 'room_id');
            $reservation->update(['status' => ReservationStatus::Enregistree]);

            if ($request->paymentLines() !== []) {
                $reservation->invoice->settle($request->paymentLines(), now(), 'Encaissement à l\'arrivée du client');
            }
        });

        return redirect()
            ->route('admin.affectations.show', $reservation)
            ->with('receipt', $reservation->id)
            ->with('status', 'Client arrivé : la réservation est devenue une affectation (check-in effectué). '.RoomAssignment::paymentSummary($reservation));
    }

    /**
     * Only a réservation still waiting for its guest can be checked in.
     */
    private function canArrive(Reservation $reservation): bool
    {
        return $reservation->isBooking() && in_array($reservation->status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true);
    }

    /**
     * Why the guest can't be checked in now ([field => message]), or null.
     *
     * @return array<string, string>|null
     */
    private function arrivalError(Reservation $reservation): ?array
    {
        if ($reservation->check_out->lessThanOrEqualTo(now())) {
            return ['check_in' => 'La période réservée est déjà terminée : reportez la réservation à une nouvelle date avant d\'enregistrer l\'arrivée du client.'];
        }

        if ($reservation->room->status !== RoomStatus::Disponible) {
            return ['room_id' => "La chambre N°{$reservation->room->number} est « {$reservation->room->status->getLabel()} » : elle doit être disponible pour accueillir le client."];
        }

        return null;
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->isBooking() && in_array($reservation->status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true), 404);

        $reservation->update(['status' => ReservationStatus::Annulee]);

        return back()->with('status', 'Réservation annulée.');
    }
}
