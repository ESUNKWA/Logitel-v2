<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Support\BusinessDay;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => HotelSettings::all(),
            'businessDayEnd' => BusinessDay::endTime(),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $before = (string) config('hotel.training_url');

        HotelSettings::save($request->validated());

        // The « Formation » link is shown to every staff member: keep a trace of who changed it.
        $after = (string) config('hotel.training_url');
        if ($after !== $before) {
            Log::notice('Lien « Formation » modifié', ['by' => Auth::id(), 'from' => $before, 'to' => $after]);
        }

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Paramètres enregistrés. Ils s\'appliquent immédiatement.');
    }
}
