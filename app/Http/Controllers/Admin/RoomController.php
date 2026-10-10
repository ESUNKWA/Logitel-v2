<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Exceptions\DeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
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

        return view('admin.rooms.board', compact('rooms', 'groups', 'rateGrid'));
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
     * JSON list of rooms available for a stay, used by the reservation form's dynamic room
     * <select> (public/js/admin.js). The stay is pricing_mode (default nuitée) + check_in +
     * duration, or check_out for date-based nuitées. `rate` is the room type's rate for that mode.
     */
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'pricing_mode' => ['nullable', new Enum(PricingMode::class)],
            'check_in' => ['required', 'date'],
            'check_out' => ['required_without:duration', 'nullable', 'date', 'after:check_in'],
            'duration' => ['required_without:check_out', 'nullable', 'integer', 'min:1', 'max:365'],
            'exclude_reservation_id' => ['nullable', 'integer'],
        ]);

        $mode = PricingMode::tryFrom($request->string('pricing_mode')->toString()) ?? PricingMode::Nuitee;

        [$start, $end] = $mode->resolve(
            Carbon::parse($request->string('check_in')->toString()),
            $request->integer('duration') ?: null,
            $request->filled('check_out') ? Carbon::parse($request->string('check_out')->toString()) : null,
        );

        $rooms = Room::query()
            ->with('roomType')
            ->where('status', '!=', RoomStatus::HorsService)
            ->availableBetween($start, $end, $request->integer('exclude_reservation_id') ?: null)
            ->orderBy('number')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => "N°{$room->number} — {$room->roomType?->name}",
                'rate' => $mode->rateFor($room->roomType),
            ]);

        return response()->json($rooms);
    }
}
