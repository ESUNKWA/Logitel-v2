<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DeletionBlocked;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuestRequest;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuestController extends Controller
{
    public function index(Request $request): View
    {
        $guests = Guest::query()
            ->withCount('reservations')
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.guests.index', compact('guests'));
    }

    public function create(): View
    {
        return view('admin.guests.create', [
            'guest' => new Guest,
        ]);
    }

    public function store(GuestRequest $request): RedirectResponse
    {
        Guest::create($request->validated());

        return redirect()
            ->route('admin.guests.index')
            ->with('status', 'Client créé.');
    }

    public function edit(Guest $guest): View
    {
        return view('admin.guests.edit', compact('guest'));
    }

    public function update(GuestRequest $request, Guest $guest): RedirectResponse
    {
        $guest->update($request->validated());

        return redirect()
            ->route('admin.guests.index')
            ->with('status', 'Client mis à jour.');
    }

    public function destroy(Guest $guest): RedirectResponse
    {
        try {
            $guest->delete();
        } catch (DeletionBlocked $blocked) {
            return back()->withErrors(['delete' => $blocked->getMessage()]);
        }

        return redirect()
            ->route('admin.guests.index')
            ->with('status', 'Client supprimé.');
    }

    /**
     * JSON endpoint used by the guest-search autocomplete widget (admin.js).
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->string('q')->toString();

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $guests = Guest::query()
            ->where('first_name', 'like', "%{$q}%")
            ->orWhere('last_name', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->limit(15)
            ->get()
            ->map(fn (Guest $guest) => [
                'id' => $guest->id,
                'label' => $guest->fullName().($guest->phone ? " — {$guest->phone}" : ''),
            ]);

        return response()->json($guests);
    }
}
