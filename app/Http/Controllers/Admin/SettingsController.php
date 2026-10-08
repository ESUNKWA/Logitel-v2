<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Support\BusinessDay;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
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
        HotelSettings::save($request->validated());

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Paramètres enregistrés. Ils s\'appliquent immédiatement.');
    }
}
