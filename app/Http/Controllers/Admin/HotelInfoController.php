<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HotelInfoRequest;
use App\Support\HotelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Configuration → Hôtel: name, contact details, address, legal mentions, social links and logo,
 * used on receipts, in the back-office, on the guest request pages and on the public site.
 */
class HotelInfoController extends Controller
{
    public function edit(): View
    {
        return view('admin.hotel.edit', ['info' => config('hotel.info')]);
    }

    public function update(HotelInfoRequest $request): RedirectResponse
    {
        $values = collect($request->validated())->except(['logo', 'remove_logo'])->map(fn ($v) => $v ?? '')->all();

        // Logo: stored on the public disk under a random name; the old file is removed.
        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            $old = (string) config('hotel.info.logo');
            if ($old !== '') {
                Storage::disk('public')->delete($old);
            }
            $values['hotel_logo'] = $request->hasFile('logo') ? $request->file('logo')->store('hotel', 'public') : '';
        }

        HotelSettings::save($values);

        return redirect()
            ->route('admin.hotel.edit')
            ->with('status', 'Informations de l\'hôtel enregistrées : elles s\'appliquent partout (reçus, site, pages clients).');
    }
}
