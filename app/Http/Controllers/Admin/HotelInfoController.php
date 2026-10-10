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
        $values = collect($request->validated())->except(['logo', 'remove_logo', 'login_background', 'remove_login_background'])->map(fn ($v) => $v ?? '')->all();

        // Images (logo, login page photo): stored on the public disk under a random name; the
        // previous file is removed when replaced or removed.
        foreach (['logo' => 'logo', 'login_background' => 'login_background'] as $field => $key) {
            if ($request->hasFile($field) || $request->boolean('remove_'.$field)) {
                $old = (string) config("hotel.info.{$key}");
                if ($old !== '') {
                    Storage::disk('public')->delete($old);
                }
                $values['hotel_'.$key] = $request->hasFile($field) ? $request->file($field)->store('hotel', 'public') : '';
            }
        }

        HotelSettings::save($values);

        return redirect()
            ->route('admin.hotel.edit')
            ->with('status', 'Informations de l\'hôtel enregistrées : elles s\'appliquent partout (reçus, site, pages clients).');
    }
}
