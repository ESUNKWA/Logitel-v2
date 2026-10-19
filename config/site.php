<?php

/*
 * Website settings that live with the code (not edited in the application).
 *
 * Advertising video capsules shown in the home banner, in this order (App\Support\VideoCapsules,
 * site/partials/hero-video). Paths are relative to public/. Keep them short (≈ 10 s), MP4
 * H.264 + AAC, with the index at the start of the file (« faststart ») so playback starts
 * before the download ends, and a light JPEG poster (≈ 50-100 Ko) of the same size. A missing
 * file is simply skipped. Optional `title` (read by screen readers, default « Capsule N ») and `captions`: a WebVTT file (.vtt) shown as French subtitles.
 */
return [

    'capsules' => [
        ['video' => 'site/images/stock/videos-capsules/v1.mp4', 'poster' => 'site/images/stock/capsules/capsule-2.jpg'], // poster = a frame of this very video
        ['video' => 'site/images/stock/videos-capsules/v2.mp4', 'poster' => 'site/images/stock/capsules/capsule-3.jpg'],
    ],

];
