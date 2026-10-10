<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\View\View;

/**
 * Public services page, from the services published in the application (« Site web → Services »).
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        $services = Service::query()->published()->ordered()->get();

        return view('site.pages.services', [
            // With a photo: large cards; without: icon tiles.
            'withPhoto' => $services->filter(fn (Service $s) => $s->photo)->values(),
            'iconOnly' => $services->reject(fn (Service $s) => $s->photo)->values(),
        ]);
    }
}
