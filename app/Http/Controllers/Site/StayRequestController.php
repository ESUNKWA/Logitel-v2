<?php

namespace App\Http\Controllers\Site;

use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StayRequestRequest;
use App\Models\Room;
use App\Models\StayRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public, no account: a guest who has just arrived picks a free room from their phone, gives
 * their details and sends a request ("demande"). The request holds the room (it disappears
 * from this list for everyone else) until the réception validates it — and cashes the stay —
 * or refuses it, or until it expires. See App\Http\Controllers\Admin\StayRequestController.
 */
class StayRequestController extends Controller
{
    /**
     * Rooms a guest can take right now, with the formulas open at this time.
     */
    public function index(Request $request): View
    {
        $rooms = $this->offerableRooms();

        return view('site.demande.index', [
            'rooms' => $rooms,
            'modes' => $rooms->mapWithKeys(fn (Room $room) => [$room->id => $this->modesFor($room)]),
            'currentRequestUrl' => $request->session()->get('stay_request_url'),
        ]);
    }

    public function create(Room $room): View|RedirectResponse
    {
        if (! $this->isOfferable($room)) {
            return redirect()->route('stay-request.index')->with('notice', 'Cette chambre vient d\'être prise. Choisissez-en une autre.');
        }

        $room->load(['roomType', 'images']);

        return view('site.demande.create', [
            'room' => $room,
            'modes' => $this->modesFor($room),
        ]);
    }

    public function store(StayRequestRequest $request, Room $room): RedirectResponse
    {
        $mode = $request->pricingMode();
        [$start, $end, $duration] = $mode->resolve($request->arrival(), (int) $request->validated('duration'));

        try {
            $stayRequest = DB::transaction(function () use ($request, $room, $mode, $start, $end, $duration) {
                // Same lock as the réception's own assignments: two guests (or a guest and the
                // desk) can't take the same room at the same moment.
                $locked = Room::lockAvailable($room->id, $start, $end, null, 'room');
                if (! $this->isOfferable($locked)) {
                    throw ValidationException::withMessages(['room' => 'unavailable']);
                }

                $stayRequest = new StayRequest([
                    'room_id' => $room->id,
                    // Optional fields: stored as empty strings (the columns aren't nullable).
                    'first_name' => (string) $request->validated('first_name'),
                    'last_name' => (string) $request->validated('last_name'),
                    'phone' => (string) $request->validated('phone'),
                    'adults' => $request->validated('adults'),
                    'children' => $request->validated('children'),
                    'pricing_mode' => $mode,
                    'duration' => $duration,
                    'unit_rate' => $mode->rateFor($locked->roomType),
                    'check_in' => $start,
                ]);
                $stayRequest->ip_address = $request->ip();
                $stayRequest->save();

                return $stayRequest;
            });
        } catch (ValidationException) {
            // Never show the internal reason (it may name another guest).
            return back()->withInput()->withErrors(['duration' => 'Cette chambre n\'est pas libre pour toute la durée choisie. Réduisez la durée ou choisissez une autre chambre.']);
        }

        $url = URL::signedRoute('stay-request.show', $stayRequest);
        $request->session()->put('stay_request_url', $url);

        return redirect($url);
    }

    /**
     * Follow-up page (signed link, so only the guest who sent the request can open it).
     */
    public function show(StayRequest $stayRequest): View
    {
        $stayRequest->load(['room.roomType', 'reservation']);

        return view('site.demande.show', ['stayRequest' => $stayRequest]);
    }

    /**
     * @return Collection<int, Room>
     */
    private function offerableRooms(): Collection
    {
        return Room::query()
            ->availableNow()
            ->whereHas('roomType', fn ($q) => $q->where('is_active', true))
            ->with(['roomType', 'images'])
            ->get()
            ->filter(fn (Room $room) => $this->modesFor($room) !== [])
            ->sort(fn (Room $a, Room $b) => strnatcasecmp((string) $a->number, (string) $b->number))
            ->values();
    }

    private function isOfferable(Room $room): bool
    {
        $room->loadMissing('roomType');
        $room->unsetRelation('pendingArrival')->unsetRelation('pendingRequest');

        return $room->status === RoomStatus::Disponible
            && $room->isAvailableNow()
            && (bool) $room->roomType?->is_active
            && $this->modesFor($room) !== [];
    }

    /**
     * Formulas the guest can choose now for this room: those with a rate, the nuitée only
     * inside its window.
     *
     * @return list<array{value: string, label: string, rate: float, unit: string, units: string}>
     */
    private function modesFor(Room $room): array
    {
        $now = now();

        return collect(PricingMode::cases())
            ->filter(fn (PricingMode $mode) => $mode->rateFor($room->roomType) !== null && $mode->canStartAt($now))
            ->map(fn (PricingMode $mode) => [
                'value' => $mode->value,
                'label' => $mode->getLabel(),
                'rate' => (float) $mode->rateFor($room->roomType),
                'unit' => $mode->rateUnit(),
                'units' => $mode->unitPlural(),
            ])
            ->values()
            ->all();
    }
}
