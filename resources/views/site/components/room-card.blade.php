{{--
    A published room on the website (list and home page): cover photo, name, type, capacity,
    surface, beds and the séjour price (the only formula the site shows, Room::publicRates()).
    <x-site::room-card :room="$room" />
    No loading="lazy" on the photo: the template's Isotope grid waits for the images
    (imagesLoaded) to size the cards — lazy images made them collapse and overlap.
--}}
@props(['room'])
@php
    $lowest = $room->lowestRate();
    $cover = $room->coverUrl();
@endphp
<div class="luxivo-room-item style-four mb-40">
    <div class="room-image">
        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $room->displayName() }}">
        @else
            <div class="room-photo-placeholder" role="img" aria-label="{{ $room->displayName() }}"><i class="far fa-bed"></i></div>
        @endif
        <div class="hover-content-wrap">
            <div class="hover-content">
                <span class="room-type-label">{{ $room->roomType->name }}</span>
                <h4><a href="{{ $room->publicUrl() }}">{{ $room->displayName() }}</a></h4>
                @if ($lowest)
                    <div class="price"><small>à partir de</small> {{ number_format($lowest['rate'], 0, ',', ' ') }} FCFA<span class="time">{{ $lowest['mode']->rateUnit() }}</span></div>
                @else
                    <div class="price"><small>Tarif sur demande</small></div>
                @endif
                <div class="room-meta">
                    @if ($room->roomType->capacity_adults)
                        <span><i class="far fa-user"></i>{{ $room->roomType->capacity_adults }} pers.</span>
                    @endif
                    @if ($room->surface)
                        <span><i class="far fa-expand-arrows-alt"></i>{{ $room->surface }} m²</span>
                    @endif
                    @if ($room->beds)
                        <span><i class="far fa-bed-alt"></i>{{ $room->beds }} lit{{ $room->beds > 1 ? 's' : '' }}</span>
                    @endif
                </div>
                <a href="{{ $room->publicUrl() }}" class="theme-btn style-two">VOIR DÉTAILS <i class="far fa-arrow-right"></i></a>
            </div>
            <div class="content">
                <h4><a href="{{ $room->publicUrl() }}">{{ $room->displayName() }}</a></h4>
                @if ($lowest)
                    <div class="price-line">dès {{ number_format($lowest['rate'], 0, ',', ' ') }} FCFA <small>{{ $lowest['mode']->rateUnit() }}</small></div>
                @else
                    <div class="price-line">Tarif sur demande</div>
                @endif
                <a href="{{ $room->publicUrl() }}" class="theme-btn style-two">VOIR DÉTAILS <i class="far fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
