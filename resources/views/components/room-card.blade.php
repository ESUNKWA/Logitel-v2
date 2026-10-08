@props(['image', 'title', 'price', 'sqm', 'beds', 'baths'])

<div class="luxivo-room-item style-four mb-40">
    <div class="room-image">
        <img src="{{ asset($image) }}" alt="{{ $title }}">
        <div class="hover-content-wrap">
            <div class="hover-content">
                <div class="ratings">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                </div>
                <h4><a href="{{ route('rooms.show') }}">{{ $title }}</a></h4>
                <div class="price">{{ $price }}<span class="time">/ nuit</span></div>
                <div class="room-meta">
                    <span><i class="far fa-expand-arrows-alt"></i>{{ $sqm }}</span>
                    <span><i class="far fa-bed-alt"></i>{{ $beds }}</span>
                    <span><i class="far fa-bath"></i>{{ $baths }}</span>
                </div>
                <a href="{{ route('rooms.show') }}" class="theme-btn style-two">VOIR DÉTAILS <i class="far fa-arrow-right"></i></a>
            </div>
            <div class="content">
                <h4><a href="{{ route('rooms.show') }}">{{ $title }}</a></h4>
                <a href="{{ route('rooms.show') }}" class="theme-btn style-two">VOIR DÉTAILS <i class="far fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
