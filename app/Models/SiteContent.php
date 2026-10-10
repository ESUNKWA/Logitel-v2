<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Home page texts and photos of the website, edited in the application (« Site web → Accueil »).
 * Stored as key / value rows; read once per request. An empty value means "show nothing" — the
 * website never falls back to sample content.
 */
class SiteContent extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Number of key figures and testimonials the form offers. */
    public const STATS = 4;

    public const TESTIMONIALS = 6;

    /** Photo fields (paths on the public disk, uploaded under site/). */
    public const PHOTOS = ['hero_photo_1', 'hero_photo_2', 'hero_background', 'about_photo_1', 'about_photo_2'];

    /** Container key of the per-request copy (the container is fresh for each request and test). */
    private const CACHE = 'site.contents';

    /**
     * Every text key the home form edits.
     *
     * @return list<string>
     */
    public static function textKeys(): array
    {
        $keys = ['hero_title', 'hero_text', 'about_subtitle', 'about_title', 'about_text', 'about_highlights'];
        foreach (range(1, self::STATS) as $i) {
            array_push($keys, "stat_{$i}_value", "stat_{$i}_label");
        }
        foreach (range(1, self::TESTIMONIALS) as $i) {
            array_push($keys, "testimonial_{$i}_name", "testimonial_{$i}_role", "testimonial_{$i}_text");
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    public static function values(): array
    {
        if (! app()->bound(self::CACHE)) {
            try {
                $values = DB::table('site_contents')->pluck('value', 'key')->map(fn ($v) => (string) $v)->all();
            } catch (Throwable) {
                $values = []; // table not migrated yet
            }
            app()->instance(self::CACHE, $values);
        }

        return app(self::CACHE);
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = trim(self::values()[$key] ?? '');

        return $value !== '' ? $value : $default;
    }

    public static function photoUrl(string $key): ?string
    {
        $path = self::get($key);

        return $path !== '' ? asset('storage/'.$path) : null;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function store(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                DB::table('site_contents')->updateOrInsert(['key' => $key], ['value' => (string) ($value ?? ''), 'updated_at' => now(), 'created_at' => now()]);
            }
        });

        self::flush();
    }

    public static function flush(): void
    {
        app()->forgetInstance(self::CACHE);
    }

    /**
     * Filled key figures: list of [value, label].
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function stats(): array
    {
        return collect(range(1, self::STATS))
            ->map(fn ($i) => [self::get("stat_{$i}_value"), self::get("stat_{$i}_label")])
            ->filter(fn ($s) => $s[0] !== '' && $s[1] !== '')
            ->values()->all();
    }

    /**
     * Filled testimonials: list of [name, role, text].
     *
     * @return list<array{name: string, role: string, text: string}>
     */
    public static function testimonials(): array
    {
        return collect(range(1, self::TESTIMONIALS))
            ->map(fn ($i) => ['name' => self::get("testimonial_{$i}_name"), 'role' => self::get("testimonial_{$i}_role"), 'text' => self::get("testimonial_{$i}_text")])
            ->filter(fn ($t) => $t['text'] !== '' && $t['name'] !== '')
            ->values()->all();
    }

    /**
     * « À propos » highlights, one per line.
     *
     * @return list<string>
     */
    public static function highlights(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', self::get('about_highlights')) ?: [])));
    }
}
