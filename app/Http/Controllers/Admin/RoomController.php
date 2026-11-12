<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Exceptions\DeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Perk;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\SpecialRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(Request $request): View
    {
        // "reservee" isn't a RoomStatus: it's a disponible room already assigned for tonight.
        $filter = $request->string('status')->toString();
        $status = RoomStatus::tryFrom($filter);
        $reservedOnly = $filter === 'reservee';
        $requestedOnly = $filter === 'demande';

        // Grouped by state (occupied first…), so sorted in PHP — "réservée" isn't a column —
        // then paginated by hand. A hotel has at most a few hundred rooms.
        $sorted = Room::sortForDisplay(Room::query()
            ->with(['roomType', 'currentStay.guest', 'pendingArrival.guest', 'pendingRequest'])
            ->when($reservedOnly, fn ($query) => $query->reservedNow())
            ->when($requestedOnly, fn ($query) => $query->requestedNow())
            ->when($status === RoomStatus::Disponible, fn ($query) => $query->availableNow())
            ->when($status && $status !== RoomStatus::Disponible, fn ($query) => $query->where('status', $status))
            ->get());

        $page = Paginator::resolveCurrentPage();
        $rooms = new LengthAwarePaginator($sorted->forPage($page, 20)->values(), $sorted->count(), 20, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $statusCounts = Room::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reservedCount = Room::query()->reservedNow()->count();
        $requestedCount = Room::query()->requestedNow()->count();
        $statusCounts[RoomStatus::Disponible->value] = ($statusCounts[RoomStatus::Disponible->value] ?? 0) - $reservedCount - $requestedCount;

        return view('admin.rooms.index', [
            'rooms' => $rooms,
            'filter' => $status?->value ?? ($reservedOnly ? 'reservee' : ($requestedOnly ? 'demande' : null)),
            'statusCounts' => $statusCounts,
            'reservedCount' => $reservedCount,
            'requestedCount' => $requestedCount,
        ]);
    }

    public function board(): View
    {
        $rooms = Room::sortForDisplay(Room::query()
            ->with(['roomType', 'currentStay.guest', 'pendingArrival.guest', 'nextArrival', 'unguaranteedArrival.guest', 'pendingRequest'])
            ->get());

        // One section per state, in Room::DISPLAY_STATES order (empty states are skipped in the view).
        $groups = collect(Room::DISPLAY_STATES)->map(fn (array $meta, string $state) => [
            'label' => $meta[0],
            'color' => $meta[1],
            'rooms' => $rooms->filter(fn (Room $room) => $room->displayState() === $state)->values(),
        ]);

        // Rate grid (one row per active room type offering at least one formula), with how many
        // of its rooms are free right now — to inform guests at the desk or on the phone.
        $freeByType = $rooms->filter(fn (Room $room) => $room->isAvailableNow())->countBy('room_type_id');
        $rateGrid = RoomType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (RoomType $type) => collect(PricingMode::cases())->contains(fn (PricingMode $mode) => $mode->rateFor($type) !== null))
            ->map(fn (RoomType $type) => ['type' => $type, 'free' => $freeByType->get($type->id, 0)])
            ->values();

        // Today's forfaits per room type, for the assignment modal's live total (the server
        // applies them again on save).
        $forfaits = SpecialRate::menusForNow($rooms->pluck('roomType')->filter()->unique('id')->values());
        // Included perks per room type and mode (« Inclus : … » in the modal; snapshotted on save).
        $perks = Perk::menusFor($rooms->pluck('roomType')->filter()->unique('id')->values());

        return view('admin.rooms.board', compact('rooms', 'groups', 'rateGrid', 'forfaits', 'perks'));
    }

    /**
     * "Chambre prête": a cleaned room goes back to "disponible" in one click.
     */
    public function markReady(Room $room): RedirectResponse
    {
        if ($room->status !== RoomStatus::Nettoyage) {
            return back()->withErrors(['room' => "La chambre N°{$room->number} n'est pas en nettoyage (« {$room->status->getLabel()} »)."]);
        }

        $room->update(['status' => RoomStatus::Disponible]);

        return back()->with('status', "Chambre N°{$room->number} prête : elle est de nouveau disponible.");
    }

    public function create(): View
    {
        return view('admin.rooms.create', [
            'room' => new Room,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $this->authorizeImages($request);
        $room = Room::create($this->roomAttributes($request));
        $this->syncImages($request, $room);

        return redirect()
            ->route('admin.rooms.index')
            ->with('status', 'Chambre créée.');
    }

    public function edit(Room $room): View
    {
        $room->load('images');

        return view('admin.rooms.edit', [
            'room' => $room,
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $this->authorizeImages($request);
        $room->update($this->roomAttributes($request));
        $this->syncImages($request, $room);

        return redirect()
            ->route('admin.rooms.index')
            ->with('status', 'Chambre mise à jour.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        try {
            $room->delete();
        } catch (DeletionBlocked $blocked) {
            return back()->withErrors(['delete' => $blocked->getMessage()]);
        }

        return redirect()
            ->route('admin.rooms.index')
            ->with('status', 'Chambre supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function roomAttributes(RoomRequest $request): array
    {
        return collect($request->validated())->except(['images', 'remove_images', 'presentation'])->all();
    }

    /**
     * Photos and the public presentation are a manager matter: rooms.edit alone (e.g. réception
     * changing a room's status) can't touch them. Checked before anything is saved.
     */
    private function authorizeImages(RoomRequest $request): void
    {
        abort_if(($request->touchesImages() || $request->touchesPresentation()) && $request->user()->cannot('rooms.manage'), 403);
    }

    /**
     * Removes the ticked photos and stores the new ones (random names under rooms/{id}/ on the
     * public disk).
     */
    private function syncImages(RoomRequest $request, Room $room): void
    {
        if (! $request->touchesImages()) {
            return;
        }

        $room->images()->whereIn('id', array_map('intval', (array) $request->validated('remove_images', [])))->get()->each->delete();

        $position = (int) $room->images()->max('position');
        foreach ((array) $request->file('images', []) as $file) {
            $room->images()->create([
                'path' => $file->store("rooms/{$room->id}", RoomImage::DISK),
                'position' => ++$position,
            ]);
        }
    }

    /**
     * JSON list of rooms for the réservation form's dynamic room <select> (public/app/js/admin.js).
     *
     * - With an arrival (check_in + duration, or check_out for date-based nuitées): the rooms free
     *   over that stay — and, réservations being this endpoint's only user, every pending booking
     *   blocks, paid or not (client decision A, 2026-11-11). `checked` = true.
     * - Without an arrival but with a room type (client request, 2026-11-11: the rooms show as soon
     *   as the type is chosen): that type's rooms that aren't hors service, availability not
     *   checked yet (`checked` = false); the form asks for the arrival to keep only free ones.
     *
     * `rate` is the room type's rate for the mode (default nuitée).
     */
    public function available(Request $request): JsonResponse
    {
        $withArrival = $request->filled('check_in');

        $request->validate([
            'pricing_mode' => ['nullable', new Enum(PricingMode::class)],
            'check_in' => ['nullable', 'date'],
            'check_out' => $withArrival ? ['required_without:duration', 'nullable', 'date', 'after:check_in'] : ['nullable'],
            'duration' => $withArrival ? ['required_without:check_out', 'nullable', 'integer', 'min:1', 'max:365'] : ['nullable'],
            'exclude_reservation_id' => ['nullable', 'integer'],
            'room_type_id' => $withArrival ? ['nullable', 'integer'] : ['required', 'integer'],
        ]);

        $mode = PricingMode::tryFrom($request->string('pricing_mode')->toString()) ?? PricingMode::Nuitee;

        $query = Room::query()
            ->with('roomType')
            ->where('status', '!=', RoomStatus::HorsService)
            ->when($request->integer('room_type_id'), fn ($query, $typeId) => $query->where('room_type_id', $typeId));

        if ($withArrival) {
            [$start, $end] = $mode->resolve(
                Carbon::parse($request->string('check_in')->toString()),
                $request->integer('duration') ?: null,
                $request->filled('check_out') ? Carbon::parse($request->string('check_out')->toString()) : null,
            );
            $query->availableBetween($start, $end, $request->integer('exclude_reservation_id') ?: null, null, bookingsBlock: true);
        }

        $rooms = $query->get()
            ->sortBy('number', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => "N°{$room->number} — {$room->roomType?->name}",
                'room_type_id' => $room->room_type_id,
                'rate' => $mode->rateFor($room->roomType),
                'checked' => $withArrival,
            ]);

        return response()->json($rooms);
    }
}
