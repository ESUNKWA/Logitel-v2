{{-- « Ce que nous vous offrons » (App\Support\HotelOffer): formulas with their lowest price and
     window, equipment of the published rooms, every published service, payment methods. Only
     data from the application; each part hidden when empty. Replaces the template's decorative
     contact form (it sent nothing). --}}
@php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
<div class="booking-wrapper home-offer">
    <div class="section-title mb-40">
        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Ce que nous vous offrons</span>
        <h2 data-aos="fade-up" data-aos-duration="1200">Tout pour un séjour réussi, à votre rythme</h2>
    </div>

    @if ($offer['formulas'])
        <div class="home-offer__group" data-aos="fade-up" data-aos-duration="1300">
            <h3 class="home-offer__title">Nos tarifs</h3>
            <ul class="home-offer__formulas">
                @foreach ($offer['formulas'] as $formula)
                    <li class="home-offer__formula home-offer__formula--{{ $formula['mode']->value }}">
                        <i class="fas {{ $formula['mode']->icon() }}" aria-hidden="true"></i>
                        <span class="home-offer__formula-name">{{ $formula['mode']->getLabel() }}</span>
                        <span class="home-offer__formula-price">dès <strong>{{ $fcfa($formula['from']) }} F</strong></span>
                        <span class="home-offer__formula-window">{{ $formula['window'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($offer['amenities'])
        <div class="home-offer__group" data-aos="fade-up" data-aos-duration="1400">
            <h3 class="home-offer__title">Dans nos chambres</h3>
            <ul class="home-offer__tags">
                @foreach ($offer['amenities'] as $amenity)
                    <li><i class="far {{ $amenity->icon() }}" aria-hidden="true"></i> {{ $amenity->getLabel() }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($offer['services']->isNotEmpty())
        <div class="home-offer__group" data-aos="fade-up" data-aos-duration="1500">
            <h3 class="home-offer__title">Nos services</h3>
            <ul class="home-offer__tags">
                @foreach ($offer['services'] as $service)
                    <li><i class="far {{ $service->iconClass() }}" aria-hidden="true"></i> {{ $service->title }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="home-offer__group" data-aos="fade-up" data-aos-duration="1600">
        <h3 class="home-offer__title">Paiement à la réception</h3>
        <ul class="home-offer__tags">
            @foreach ($offer['payments'] as $method)
                <li><i class="fas {{ $method->icon() }}" aria-hidden="true"></i> {{ $method->getLabel() }}</li>
            @endforeach
        </ul>
    </div>

    <div class="home-offer__actions" data-aos="fade-up" data-aos-duration="1700">
        <a href="{{ route('booking.create') }}" class="theme-btn style-one">Réserver <i class="far fa-arrow-right"></i></a>
        @if ($offer['services']->isNotEmpty())
            <a href="{{ route('services.index') }}" class="theme-btn style-two home-offer__secondary">Voir tous nos services <i class="far fa-arrow-right"></i></a>
        @endif
    </div>
</div>
