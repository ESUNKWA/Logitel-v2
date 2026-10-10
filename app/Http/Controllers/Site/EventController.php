<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteEvent;
use Illuminate\View\View;

/**
 * Public events page: upcoming events published in the application (« Site web → Événements »).
 */
class EventController extends Controller
{
    public function index(): View
    {
        return view('site.pages.evenements', ['events' => SiteEvent::query()->upcoming()->get()]);
    }
}
