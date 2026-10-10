<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * « Site web → Services »: the hotel's services and facilities shown on the website.
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.site.services.index', ['services' => Service::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.site.services.create', ['service' => new Service(['is_published' => true, 'position' => (int) Service::max('position') + 1])]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = new Service($request->serviceAttributes());
        $this->savePhoto($request, $service);
        $service->save();

        return redirect()->route('admin.site.services.index')->with('status', "Service « {$service->title} » ajouté.");
    }

    public function edit(Service $service): View
    {
        return view('admin.site.services.edit', ['service' => $service]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->fill($request->serviceAttributes());
        $this->savePhoto($request, $service);
        $service->save();

        return redirect()->route('admin.site.services.index')->with('status', "Service « {$service->title} » enregistré.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete(); // the model removes its photo file

        return redirect()->route('admin.site.services.index')->with('status', "Service « {$service->title} » supprimé.");
    }

    private function savePhoto(ServiceRequest $request, Service $service): void
    {
        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($service->photo) {
                Storage::disk('public')->delete($service->photo);
            }
            $service->photo = $request->hasFile('photo') ? $request->file('photo')->store('site/services', 'public') : null;
        }
    }
}
