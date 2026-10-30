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
            // Affectations and réservations counted apart (same table, never mixed in the UI).
            ->withCount([
                'reservations as stays_count' => fn ($q) => $q->stays(),
                'reservations as bookings_count' => fn ($q) => $q->notStartedBookings(),
            ])
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('company_name', 'like', "%{$q}%")
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
        $guest = Guest::create($request->guestAttributes());

        return $this->afterSave($request, $guest)->with('status', 'Client créé.');
    }

    public function edit(Guest $guest): View
    {
        return view('admin.guests.edit', compact('guest'));
    }

    public function update(GuestRequest $request, Guest $guest): RedirectResponse
    {
        $guest->update($request->guestAttributes());

        return $this->afterSave($request, $guest)->with('status', 'Client mis à jour.');
    }

    public function destroy(Request $request, Guest $guest): RedirectResponse
    {
        try {
            $guest->delete();
        } catch (DeletionBlocked $blocked) {
            return back()->withErrors(['delete' => $blocked->getMessage()]);
        }

        return ($request->user()->can('guests.list') ? redirect()->route('admin.guests.index') : redirect()->route('admin.dashboard'))
            ->with('status', 'Client supprimé.');
    }

    /**
     * The clients list is the Gérant's (guests.list, client request 2026-10-25): anyone else who
     * may edit a client (e.g. from the front-desk search) stays on the client's file.
     */
    private function afterSave(Request $request, Guest $guest): RedirectResponse
    {
        return $request->user()->can('guests.list')
            ? redirect()->route('admin.guests.index')
            : redirect()->route('admin.guests.edit', $guest);
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
            ->orWhere('company_name', 'like', "%{$q}%")
            ->orWhere('phone', 'like', "%{$q}%")
            ->limit(15)
            ->get()
            ->map(fn (Guest $guest) => [
                'id' => $guest->id,
                'label' => ($guest->isCompany() ? '🏢 ' : '').$guest->fullName().($guest->phone ? " — {$guest->phone}" : ''),
            ]);

        return response()->json($guests);
    }
}
