<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SpecialRateRequest;
use App\Models\RoomType;
use App\Models\SpecialRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Configuration → Tarifs spéciaux: forfaits applied automatically to affectations
 * (SpecialRate::best(), Reservation's saving event). Existing stays keep the forfait they got.
 */
class SpecialRateController extends Controller
{
    public function index(): View
    {
        $rates = SpecialRate::query()->with('roomTypes')->get()
            ->sortBy(fn (SpecialRate $r) => [$r->isPast() ? 1 : 0, array_search($r->pricing_mode, PricingMode::cases(), true), $r->duration, (float) $r->price])
            ->values();

        return view('admin.special-rates.index', [
            'current' => $rates->reject->isPast()->values(),
            'past' => $rates->filter->isPast()->values(),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.special-rates.create', [
            'specialRate' => new SpecialRate(['is_active' => true, 'pricing_mode' => PricingMode::Heure, 'duration' => 2]),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SpecialRateRequest $request): RedirectResponse
    {
        $rate = DB::transaction(function () use ($request) {
            $rate = SpecialRate::create($request->rateAttributes());
            $rate->roomTypes()->sync($request->roomTypeIds());

            return $rate;
        });

        return redirect()->route('admin.special-rates.index')
            ->with('status', 'Tarif spécial « '.$rate->name.' » créé.'.$this->noSavingNotice($rate));
    }

    public function edit(SpecialRate $specialRate): View
    {
        return view('admin.special-rates.edit', [
            'specialRate' => $specialRate->load('roomTypes'),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function update(SpecialRateRequest $request, SpecialRate $specialRate): RedirectResponse
    {
        DB::transaction(function () use ($request, $specialRate) {
            $specialRate->update($request->rateAttributes());
            $specialRate->roomTypes()->sync($request->roomTypeIds());
        });

        return redirect()->route('admin.special-rates.index')
            ->with('status', 'Tarif spécial « '.$specialRate->name.' » mis à jour. Les affectations déjà enregistrées gardent leur prix.'.$this->noSavingNotice($specialRate));
    }

    public function destroy(SpecialRate $specialRate): RedirectResponse
    {
        $specialRate->delete(); // stays keep their snapshot (special_rate_id is set to null)

        return redirect()->route('admin.special-rates.index')
            ->with('status', 'Tarif spécial supprimé. Les affectations déjà enregistrées gardent leur prix.');
    }

    /**
     * " Attention : …" when the forfait isn't cheaper than the normal price for some of its
     * room types (it will never apply to them).
     */
    private function noSavingNotice(SpecialRate $rate): string
    {
        $types = $rate->all_room_types ? RoomType::query()->orderBy('name')->get() : $rate->roomTypes()->orderBy('name')->get();

        $useless = $types->filter(function (RoomType $type) use ($rate) {
            $unit = $rate->pricing_mode->rateFor($type);

            return $unit === null || bccomp((string) $rate->price, bcmul((string) $unit, (string) $rate->duration, 2), 2) >= 0;
        })->pluck('name');

        return $useless->isEmpty() ? ''
            : ' Attention : il ne s\'appliquera pas pour '.$useless->implode(', ').' (pas moins cher que le tarif normal, ou mode sans tarif).';
    }
}
