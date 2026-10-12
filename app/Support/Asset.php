<?php

namespace App\Support;

/**
 * URL of a file under public/ with a version stamp (its modification time), so browsers and the
 * application's service worker fetch the new file right after a deployment even though Caddy
 * lets them cache /app/* and /vendor/* for a week.
 */
class Asset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
