<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A picture of the website gallery, uploaded in the application (« Site web → Galerie »).
 * Shown on /galerie (with category filter tabs) and in the photo strip of the home page.
 */
class GalleryPhoto extends Model
{
    /** Photos the gallery can hold in total, and per upload (PHP caps a request at 8 MB). */
    public const MAX_TOTAL = 60;

    public const MAX_PER_UPLOAD = 10;

    protected $fillable = ['caption', 'category', 'position', 'is_published'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The file goes with the photo.
        static::deleted(function (GalleryPhoto $photo) {
            Storage::disk('public')->delete($photo->path);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    /**
     * CSS class used by the website's filter tabs (« cat-chambres »).
     */
    public static function categoryFilter(string $category): string
    {
        return 'cat-'.(Str::slug($category) ?: substr(md5($category), 0, 8));
    }

    /**
     * Distinct categories of the given photos, in order of first appearance.
     *
     * @param  Collection<int, GalleryPhoto>  $photos
     * @return Collection<int, string>
     */
    public static function categoriesOf(Collection $photos): Collection
    {
        return $photos->pluck('category')->filter(fn ($c) => trim((string) $c) !== '')->map(fn ($c) => trim($c))
            ->unique(fn ($c) => self::categoryFilter($c))->values();
    }
}
