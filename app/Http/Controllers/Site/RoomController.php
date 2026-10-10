<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public room pages, built from what the application publishes: rooms ticked « publiée » (of an
 * active type), with their presentation, photos and the type's rates. Read only.
 */
class RoomController extends Controller
{
    public function index(): View
    {
        $rooms = Room::query()
            ->published()
            ->with(['roomType', 'images'])
            ->get()
            // By type, then naturally by number (2 before 10).
            ->sort(fn (Room $a, Room $b) => strnatcasecmp($a->roomType->name, $b->roomType->name) ?: strnatcasecmp((string) $a->number, (string) $b->number))
            ->values();

        return view('site.pages.chambres', [
            'rooms' => $rooms,
            // Filter tabs: the room types that have published rooms.
            'types' => $rooms->pluck('roomType')->unique('id')->sortBy('name')->values(),
        ]);
    }

    public function show(Room $room, ?string $slug = null): View|RedirectResponse
    {
        $room->load(['roomType', 'images']);

        abort_unless($room->isPubliclyVisible(), 404);

        // One address per room: /chambres/12/suite-lagune (old or missing slug → redirect).
        if ($slug !== $room->publicSlug()) {
            return redirect()->to($room->publicUrl(), 301);
        }

        return view('site.pages.chambre', [
            'room' => $room,
            'rates' => $room->publicRates(),
            // A guest already at the hotel can ask for it right away if it's free now.
            'requestable' => $room->isAvailableNow(),
            'otherRooms' => Room::query()->published()->whereKeyNot($room->id)->with(['roomType', 'images'])->inRandomOrder()->limit(3)->get(),
        ]);
    }

    /**
     * Type slug used by the list's filter tabs.
     */
    public static function typeFilter(RoomType $type): string
    {
        return 'type-'.$type->id;
    }
}
