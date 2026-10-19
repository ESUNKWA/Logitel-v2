{{-- Advertising video capsules in the home banner (config/site.php → App\Support\VideoCapsules).
     Landscape screens: full-bleed background behind the title (the banner keeps its size: no
     layout shift); portrait phones: the whole 16:9 frame at the top of the banner (a background
     would crop a landscape video by more than half). Sound on by default — muted here only so
     that iOS / Android accept the autoplay fallback (see hero-video.js) —, inline, nothing downloaded before
     the page has loaded (preload="none"); played one after the other by public/site/js/hero-video.js,
     which also handles the controls, the fallbacks (reduced motion, data saver, autoplay refused)
     and pausing when off screen. Without JavaScript the native controls stay. --}}
@php($first = $capsules[0])
<div class="hero-video" data-hero-video data-capsules="{{ json_encode($capsules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
    role="region" aria-roledescription="carrousel" aria-label="Capsules vidéo du complexe ({{ count($capsules) }})">
    <div class="hero-video__screen">
        <video class="hero-video__media" src="{{ $first['src'] }}" @if ($first['poster']) poster="{{ $first['poster'] }}" @endif
            muted playsinline preload="none" controls disablepictureinpicture width="848" height="478"
            aria-label="{{ $first['title'] }} — 1 sur {{ count($capsules) }}" data-hero-video-media>
            @if ($first['captions'])
                <track kind="captions" srclang="fr" label="Français" src="{{ $first['captions'] }}" default>
            @endif
        </video>
        <button type="button" class="hero-video__start" data-hero-video-start hidden aria-label="Lire la vidéo">
            <i class="fas fa-play" aria-hidden="true"></i>
        </button>
    </div>
    <div class="hero-video__controls" data-hero-video-controls hidden>
        @if (count($capsules) > 1)
            <button type="button" class="hero-video__btn" data-action="prev" aria-label="Capsule précédente"><i class="fas fa-step-backward" aria-hidden="true"></i></button>
        @endif
        <button type="button" class="hero-video__btn" data-action="toggle" aria-label="Lire la vidéo"><i class="fas fa-play" aria-hidden="true"></i></button>
        <button type="button" class="hero-video__btn hero-video__btn--sound" data-action="mute" aria-label="Activer le son" aria-pressed="false"><i class="fas fa-volume-mute" aria-hidden="true"></i><span class="hero-video__btn-text" aria-hidden="true">Activer le son</span></button>
        @if (count($capsules) > 1)
            <button type="button" class="hero-video__btn" data-action="next" aria-label="Capsule suivante"><i class="fas fa-step-forward" aria-hidden="true"></i></button>
            <div class="hero-video__dots" role="group" aria-label="Choisir une capsule">
                @foreach ($capsules as $i => $capsule)
                    <button type="button" class="hero-video__dot" data-index="{{ $i }}" aria-label="{{ $capsule['title'] }} — {{ $i + 1 }} sur {{ count($capsules) }}" @if ($i === 0) aria-current="true" @endif></button>
                @endforeach
            </div>
        @endif
        <span class="visually-hidden" aria-live="polite" data-hero-video-status></span>
    </div>
</div>

@push('scripts')
    <script src="{{ \App\Support\Asset::url('site/js/hero-video.js') }}"></script>
@endpush
