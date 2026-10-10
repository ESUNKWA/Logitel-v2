<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteHomeRequest;
use App\Models\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * « Site web → Accueil »: the website home page's texts and photos.
 */
class HomeContentController extends Controller
{
    public function edit(): View
    {
        return view('admin.site.home', ['content' => SiteContent::values()]);
    }

    public function update(SiteHomeRequest $request): RedirectResponse
    {
        $values = collect(SiteContent::textKeys())->mapWithKeys(fn ($key) => [$key => trim((string) $request->validated($key))])->all();

        // Photos: stored under site/ with a random name; the previous file is removed.
        foreach (SiteContent::PHOTOS as $key) {
            if ($request->hasFile($key) || $request->boolean('remove_'.$key)) {
                $old = SiteContent::get($key);
                if ($old !== '') {
                    Storage::disk('public')->delete($old);
                }
                $values[$key] = $request->hasFile($key) ? $request->file($key)->store('site', 'public') : '';
            }
        }

        SiteContent::store($values);

        return redirect()->route('admin.site.home.edit')->with('status', 'Page d\'accueil du site enregistrée.');
    }
}
