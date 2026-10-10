<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\GalleryPhoto;
use Illuminate\View\View;

/**
 * Public gallery, from the photos published in the application (« Site web → Galerie »).
 */
class GalleryController extends Controller
{
    public function index(): View
    {
        $photos = GalleryPhoto::query()->published()->ordered()->get();

        return view('site.pages.galerie', [
            'photos' => $photos,
            'categories' => GalleryPhoto::categoriesOf($photos),
        ]);
    }
}
