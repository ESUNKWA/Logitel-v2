<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Service;
use App\Models\SiteEvent;
use Illuminate\View\View;

/**
 * Website home page: texts/photos edited in « Site web → Accueil » (SiteContent), rooms
 * published from the application, the services featured on the home page and upcoming events.
 */
class HomeController extends Controller
{
    public function index(): View
    {
        return view('site.pages.accueil', [
            'rooms' => Room::query()->published()->with(['roomType', 'images'])->latest('updated_at')->limit(6)->get(),
            // Texts and photos come from App\Models\SiteContent (read in the view).
            'services' => Service::query()->published()->where('show_on_home', true)->ordered()->limit(6)->get(),
            // Upcoming events: the soonest in the banner under the hero, the next three as cards.
            'events' => SiteEvent::query()->upcoming()->limit(3)->get(),
        ]);
    }
}
