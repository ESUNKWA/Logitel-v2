<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Salles » (catalog.manage, the Gérant): the rooms to hire for meetings & events, capacity and
 * rates. A venue already booked is never deleted (deactivate it); its past bookings keep the
 * rate they were billed.
 */
class VenueController extends Controller
{
    public function index(): View
    {
        return view('admin.events.venues', ['venues' => Venue::query()->ordered()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = Venue::create($this->validated($request));

        return redirect()->route('admin.venues.index')->with('status', 'Salle « '.$venue->name.' » ajoutée.');
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $venue->update($this->validated($request, $venue));

        return redirect()->route('admin.venues.index')->with('status', 'Salle « '.$venue->name.' » mise à jour.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        if ($venue->bookings()->exists()) {
            return back()->withErrors(['venue' => 'La salle « '.$venue->name.' » a déjà été réservée : désactivez-la plutôt.']);
        }
        $venue->delete();

        return redirect()->route('admin.venues.index')->with('status', 'Salle « '.$venue->name.' » supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Venue $venue = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('venues', 'name')->ignore($venue?->id)],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'half_day_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'day_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], ['name.unique' => 'Une salle porte déjà ce nom.'], [
            'name' => 'nom', 'capacity' => 'capacité', 'half_day_rate' => 'tarif demi-journée', 'day_rate' => 'tarif journée',
        ]);

        return [
            'name' => trim($data['name']),
            'capacity' => (int) $data['capacity'],
            'half_day_rate' => $data['half_day_rate'],
            'day_rate' => $data['day_rate'],
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : ($venue?->is_active ?? true),
        ];
    }
}
