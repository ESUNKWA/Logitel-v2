<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PerkRequest;
use App\Models\Perk;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Configuration → Avantages inclus (client request, 2026-10-25): e.g. « Accès piscine gratuit »
 * for the suites in séjour. Printed on the receipt of every new file of those room types and
 * formulas, shown at the desk and on the website's room pages. Existing files keep theirs.
 */
class PerkController extends Controller
{
    public function index(): View
    {
        return view('admin.perks.index', [
            'perks' => Perk::query()->ordered()->with('roomTypes')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.perks.create', [
            'perk' => new Perk(['is_active' => true, 'position' => 0]),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(PerkRequest $request): RedirectResponse
    {
        $perk = DB::transaction(function () use ($request) {
            $perk = Perk::create($request->perkAttributes());
            $perk->roomTypes()->sync($request->roomTypeIds());

            return $perk;
        });

        return redirect()->route('admin.perks.index')
            ->with('status', 'Avantage « '.$perk->label.' » créé.');
    }

    public function edit(Perk $perk): View
    {
        return view('admin.perks.edit', [
            'perk' => $perk->load('roomTypes'),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function update(PerkRequest $request, Perk $perk): RedirectResponse
    {
        DB::transaction(function () use ($request, $perk) {
            $perk->update($request->perkAttributes());
            $perk->roomTypes()->sync($request->roomTypeIds());
        });

        return redirect()->route('admin.perks.index')
            ->with('status', 'Avantage « '.$perk->label.' » mis à jour. Les dossiers déjà enregistrés gardent leurs avantages.');
    }

    public function destroy(Perk $perk): RedirectResponse
    {
        $perk->delete(); // files keep their snapshot (reservations.perks)

        return redirect()->route('admin.perks.index')
            ->with('status', 'Avantage supprimé. Les dossiers déjà enregistrés gardent leurs avantages.');
    }
}
