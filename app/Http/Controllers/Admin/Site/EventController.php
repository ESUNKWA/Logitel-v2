<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteEventRequest;
use App\Models\SiteEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * « Site web → Événements »: the hotel's events announced on the website (home banner and
 * cards, /evenements). Past events leave the website by themselves and stay listed here.
 */
class EventController extends Controller
{
    public function index(): View
    {
        $events = SiteEvent::query()->orderBy('starts_at')->get();

        return view('admin.site.events.index', [
            'upcoming' => $events->reject->isOver()->values(),
            'past' => $events->filter->isOver()->sortByDesc('starts_at')->values(),
        ]);
    }

    public function create(): View
    {
        return view('admin.site.events.create', ['event' => new SiteEvent(['is_published' => true])]);
    }

    public function store(SiteEventRequest $request): RedirectResponse
    {
        $event = new SiteEvent($request->eventAttributes());
        $this->savePhoto($request, $event);
        $event->save();

        return redirect()->route('admin.site.events.index')->with('status', "Événement « {$event->title} » ajouté.");
    }

    public function edit(SiteEvent $event): View
    {
        return view('admin.site.events.edit', ['event' => $event]);
    }

    public function update(SiteEventRequest $request, SiteEvent $event): RedirectResponse
    {
        $event->fill($request->eventAttributes());
        $this->savePhoto($request, $event);
        $event->save();

        return redirect()->route('admin.site.events.index')->with('status', "Événement « {$event->title} » enregistré.");
    }

    public function destroy(SiteEvent $event): RedirectResponse
    {
        $event->delete(); // the model removes its poster

        return redirect()->route('admin.site.events.index')->with('status', "Événement « {$event->title} » supprimé.");
    }

    private function savePhoto(SiteEventRequest $request, SiteEvent $event): void
    {
        if ($request->hasFile('photo') || $request->boolean('remove_photo')) {
            if ($event->photo) {
                Storage::disk('public')->delete($event->photo);
            }
            $event->photo = $request->hasFile('photo') ? $request->file('photo')->store('site/events', 'public') : null;
        }
    }
}
