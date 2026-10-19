<?php

namespace App\Support;

/**
 * The advertising video capsules of the home banner (config/site.php → `capsules`), only those
 * whose video file exists — a missing file is skipped, never a broken player. URLs are built
 * segment by segment (the real file names contain spaces).
 */
class VideoCapsules
{
    /**
     * @return list<array{src: string, poster: string|null, captions: string|null, title: string, type: string}>
     */
    public static function all(): array
    {
        $capsules = [];
        foreach ((array) config('site.capsules', []) as $capsule) {
            $video = (string) ($capsule['video'] ?? '');
            if ($video === '' || ! is_file(public_path($video))) {
                continue;
            }
            $poster = (string) ($capsule['poster'] ?? '');
            $captions = (string) ($capsule['captions'] ?? '');
            $capsules[] = [
                'src' => self::url($video),
                'poster' => $poster !== '' && is_file(public_path($poster)) ? self::url($poster) : null,
                'captions' => $captions !== '' && is_file(public_path($captions)) ? self::url($captions) : null,
                'title' => (string) ($capsule['title'] ?? 'Capsule '.(count($capsules) + 1)),
                'type' => str_ends_with(strtolower($video), '.webm') ? 'video/webm' : 'video/mp4',
            ];
        }

        return $capsules;
    }

    private static function url(string $path): string
    {
        return asset(implode('/', array_map('rawurlencode', explode('/', $path))));
    }
}
