<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::query()
            ->withCount('rooms')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.room-types.index', compact('roomTypes'));
    }

    public function create(): View
    {
        return view('admin.room-types.create', [
            'roomType' => new RoomType,
        ]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        RoomType::create($request->validated());

        return redirect()
            ->route('admin.room-types.index')
            ->with('status', 'Type de chambre créé.');
    }

    public function edit(RoomType $room_type): View
    {
        return view('admin.room-types.edit', [
            'roomType' => $room_type,
        ]);
    }

    public function update(RoomTypeRequest $request, RoomType $room_type): RedirectResponse
    {
        $room_type->update($request->validated());

        return redirect()
            ->route('admin.room-types.index')
            ->with('status', 'Type de chambre mis à jour.');
    }

    public function destroy(RoomType $room_type): RedirectResponse
    {
        try {
            $room_type->delete();
        } catch (DeletionBlocked $blocked) {
            return back()->withErrors(['delete' => $blocked->getMessage()]);
        }

        return redirect()
            ->route('admin.room-types.index')
            ->with('status', 'Type de chambre supprimé.');
    }
}
