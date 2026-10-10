@props(['event', 'full' => false])
{{-- An upcoming event (« Site web → Événements »): poster or date tile, date, place, price.
     $full shows the whole description and the booking link (events page). --}}
<article class="site-event-card h-100" id="evenement-{{ $event->id }}">
    <div class="site-event-card__media">
        @if ($event->photoUrl())
            <img src="{{ $event->photoUrl() }}" alt="{{ $event->title }}">
        @else
            <div class="site-event-card__placeholder"><i class="fal fa-calendar-star"></i></div>
        @endif
        <div class="site-event-card__date" aria-hidden="true">
            <strong>{{ $event->starts_at->format('d') }}</strong>
            <span>{{ $event->starts_at->translatedFormat('M') }}</span>
        </div>
        @if ($event->isOngoing())
            <span class="site-event-card__live">En cours</span>
        @endif
    </div>
    <div class="site-event-card__body">
        <h4>{{ $event->title }}</h4>
        <ul class="site-event-card__meta">
            <li><i class="far fa-clock"></i> {{ $event->whenLabel() }}</li>
            @if ($event->location)
                <li><i class="far fa-map-marker-alt"></i> {{ $event->location }}</li>
            @endif
            @if ($event->price_label)
                <li><i class="far fa-ticket-alt"></i> {{ $event->price_label }}</li>
            @endif
        </ul>
        @if ($event->description)
            @if ($full)
                <p>{!! nl2br(e($event->description)) !!}</p>
            @else
                <p>{{ \Illuminate\Support\Str::limit($event->description, 140) }}</p>
            @endif
        @endif
        @if ($full && ($link = $event->bookingLink()))
            @if ($link[0])
                <a href="{{ $link[0] }}" class="theme-btn style-one mt-2" @if (str_starts_with($link[0], 'http')) target="_blank" rel="noopener nofollow" @endif>
                    @if (str_starts_with($link[0], 'tel:'))<i class="far fa-phone-alt me-1"></i> Réserver : @endif{{ $link[1] }}
                </a>
            @else
                <p class="site-event-card__booking"><i class="far fa-info-circle"></i> {{ $link[1] }}</p>
            @endif
        @endif
    </div>
</article>
