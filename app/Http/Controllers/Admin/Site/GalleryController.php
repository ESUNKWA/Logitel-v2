<?php

namespace App\Http\Controllers\Admin\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryUpdateRequest;
use App\Http\Requests\Admin\GalleryUploadRequest;
use App\Models\GalleryPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * « Site web → Galerie »: the pictures of the website gallery (upload, caption, category, order,
 * published, delete).
 */
class GalleryController extends Controller
{
    public function index(): View
    {
        $photos = GalleryPhoto::query()->ordered()->get();

        return view('admin.site.gallery', [
            'photos' => $photos,
            'categories' => GalleryPhoto::categoriesOf($photos),
            'remaining' => max(0, GalleryPhoto::MAX_TOTAL - $photos->count()),
        ]);
    }

    public function store(GalleryUploadRequest $request): RedirectResponse
    {
        $category = trim((string) $request->validated('category')) ?: null;
        $position = (int) GalleryPhoto::max('position');
        $files = $request->file('images');

        foreach ($files as $file) {
            $photo = new GalleryPhoto(['category' => $category, 'position' => ++$position, 'is_published' => true]);
            $photo->path = $file->store('site/gallery', 'public'); // random name
            $photo->save();
        }

        return redirect()->route('admin.site.gallery.index')
            ->with('status', count($files) > 1 ? count($files).' photos ajoutées à la galerie.' : 'Photo ajoutée à la galerie.');
    }

    public function update(GalleryUpdateRequest $request): RedirectResponse
    {
        $rows = $request->validated('photos');

        DB::transaction(function () use ($rows) {
            // Only existing photos are touched; unknown ids are ignored.
            GalleryPhoto::query()->whereIn('id', array_keys($rows))->get()->each(function (GalleryPhoto $photo) use ($rows) {
                $row = $rows[$photo->id];
                $photo->update([
                    'caption' => trim((string) ($row['caption'] ?? '')) ?: null,
                    'category' => trim((string) ($row['category'] ?? '')) ?: null,
                    'position' => (int) ($row['position'] ?? 0),
                    'is_published' => (bool) ($row['is_published'] ?? false),
                ]);
            });
        });

        return redirect()->route('admin.site.gallery.index')->with('status', 'Galerie enregistrée.');
    }

    public function destroy(GalleryPhoto $photo): RedirectResponse
    {
        $photo->delete(); // the model removes the file

        return redirect()->route('admin.site.gallery.index')->with('status', 'Photo supprimée de la galerie.');
    }
}
