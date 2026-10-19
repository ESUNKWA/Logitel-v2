<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationRequest;
use App\Models\BookingRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * Filter value => statuses.
     */
    private const FILTERS = [
        'en_attente' => [ReservationStatus::EnAttente],
        'confirmee' => [ReservationStatus::Confirmee],
        'honoree' => [ReservationStatus::Enregistree, ReservationStatus::Terminee],
        'annulee' => [ReservationStatus::Annulee],
    ];

    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString();

        $reservations = Reservation::query()
            ->bookings()
            ->with(['guest', 'room', 'invoice', 'creator'])
            ->when(self::FILTERS[$filter] ?? null, fn ($query, $statuses) => $query->whereIn('status', $statuses))
            ->when($request->string('room')->toString(), fn ($query, $number) => $query->whereHas('room', fn ($q) => $q->where('number', $number)))
            ->orderByDesc('check_in')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'filters' => ['en_attente' => 'En attente', 'confirmee' => 'Confirmée', 'honoree' => 'Honorée (client arrivé)', 'annulee' => 'Annulée'],
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

        return view('admin.reservations.create', [
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
            Room::lockAvailable((int) $request->validated('room_id'), $start, $end, null, 'room_id');

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

        DB::transaction(function () use ($request, $reservation) {
            [$start, $end] = $request->period();
            Room::lockAvailable((int) $request->validated('room_id'), $start, $end, $reservation->id, 'room_id');

            $reservation->update([...$request->reservationAttributes(), 'guest_id' => $request->resolveGuestId()]);
        });

        return redirect()
            ->route('admin.reservations.index')
            ->with('status', 'Réservation mise à jour.');
    }

    /**
     * The guest arrives: the réservation is checked in and becomes an affectation (the model
     * event puts the room in "occupee").
     */
    public function checkIn(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->isBooking() && in_array($reservation->status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true), 404);

        if ($reservation->check_out->lessThanOrEqualTo(now())) {
            return back()->withErrors(['check_in' => 'La période réservée est déjà terminée : reportez la réservation à une nouvelle date avant d\'enregistrer l\'arrivée du client.']);
        }

        if ($reservation->room->status !== RoomStatus::Disponible) {
            return back()->withErrors(['room_id' => "La chambre N°{$reservation->room->number} est « {$reservation->room->status->getLabel()} » : elle doit être disponible pour accueillir le client."]);
        }

        // An unpaid booking doesn't hold its room: someone may have taken the slot meanwhile.
        DB::transaction(function () use ($reservation) {
            Room::lockAvailable($reservation->room_id, $reservation->check_in, $reservation->check_out, $reservation->id, 'room_id');
            $reservation->update(['status' => ReservationStatus::Enregistree]);
        });

        return redirect()
            ->route('admin.affectations.show', $reservation)
            ->with('receipt', $reservation->id)
            ->with('status', 'Client arrivé : la réservation est devenue une affectation (check-in effectué).');
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->isBooking() && in_array($reservation->status, [ReservationStatus::EnAttente, ReservationStatus::Confirmee], true), 404);

        $reservation->update(['status' => ReservationStatus::Annulee]);

        return back()->with('status', 'Réservation annulée.');
    }
}
