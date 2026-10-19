@extends('site.layouts.app')

@section('title', $room->displayName().' - '.$hotelInfo->name())
@section('meta_description', $room->tagline ?: \Illuminate\Support\Str::limit((string) $room->description, 150))

@section('content')
@php
    $type = $room->roomType;
    $images = $room->images;
    $lowest = $room->lowestRate();
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

<x-site::page-hero :title="$room->displayName()" :subtitle="$room->tagline" breadcrumb="Chambres" />

<section class="luxivo-room-details-sec pt-110 pb-120">
    <div class="container">
        <div class="room-details-wrapper">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="room-content-area mb-30">
                        <h3 class="title">{{ $room->displayName() }}</h3>
                        <div class="rooms-ratings-quote d-flex align-items-center flex-wrap gap-2">
                            <span class="room-type-label">{{ $type->name }}</span>
                            @if ($hotelInfo->get('city'))
                                <span><i class="far fa-map-marker-alt mx-2"></i>{{ $hotelInfo->get('city') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-md-end mb-40">
                        <a href="{{ route('rooms.index') }}" class="theme-btn style-two"><i class="far fa-arrow-left me-2"></i>Toutes les chambres</a>
                    </div>
                </div>
            </div>

            <div class="rooms-meta-info mb-25">
                @if ($type->capacity_adults)
                    <div class="meta-box text-center mb-30">
                        <img src="{{ asset('site/images/innerpage/icon/icon-12.png') }}" class="mb-15" alt="">
                        <h5>{{ $type->capacity_adults }} adulte{{ $type->capacity_adults > 1 ? 's' : '' }}</h5>
                    </div>
                @endif
                @if ($type->capacity_children)
                    <div class="meta-box text-center mb-30">
                        <img src="{{ asset('site/images/innerpage/icon/icon-13.png') }}" class="mb-15" alt="">
                        <h5>{{ $type->capacity_children }} enfant{{ $type->capacity_children > 1 ? 's' : '' }}</h5>
                    </div>
                @endif
                @if ($room->surface)
                    <div class="meta-box text-center mb-30">
                        <img src="{{ asset('site/images/innerpage/icon/icon-14.png') }}" class="mb-15" alt="">
                        <h5>{{ $room->surface }} m²</h5>
                    </div>
                @endif
                @if ($room->beds)
                    <div class="meta-box text-center mb-30">
                        <img src="{{ asset('site/images/innerpage/icon/icon-15.png') }}" class="mb-15" alt="">
                        <h5>{{ $room->beds }} lit{{ $room->beds > 1 ? 's' : '' }}</h5>
                    </div>
                @endif
                @if ($room->bathrooms)
                    <div class="meta-box text-center mb-30">
                        <img src="{{ asset('site/images/innerpage/icon/icon-16.png') }}" class="mb-15" alt="">
                        <h5>{{ $room->bathrooms }} salle{{ $room->bathrooms > 1 ? 's' : '' }} de bain</h5>
                    </div>
                @endif
                <div class="meta-box text-center mb-30">
                    <img src="{{ asset('site/images/innerpage/icon/icon-17.png') }}" class="mb-15" alt="">
                    <h5>{{ $lowest ? 'dès '.$fcfa($lowest['rate']).' FCFA '.$lowest['mode']->rateUnit() : 'Tarif sur demande' }}</h5>
                </div>
            </div>

            <div class="room-image mb-40">
                @if ($images->isNotEmpty())
                    <img src="{{ $images->first()->url() }}" class="mb-15" alt="{{ $room->displayName() }}">
                @else
                    <div class="room-photo-placeholder room-photo-placeholder--large" role="img" aria-label="{{ $room->displayName() }}"><i class="far fa-bed"></i></div>
                @endif
            </div>

            <div class="row">
                <div class="col-xl-8">
                    @if ($room->description)
                        <div class="room-content-box">
                            <h3>Description de la chambre</h3>
                            @foreach (preg_split('/\R{2,}/', trim($room->description)) as $paragraph)
                                <p>{!! nl2br(e($paragraph)) !!}</p>
                            @endforeach
                        </div>
                    @endif

                    @if ($rates)
                        <div class="room-content-box mb-25">
                            <h3>Tarifs</h3>
                            <div class="table-responsive">
                                <table class="table site-rates">
                                    <tbody>
                                        @foreach ($rates as $rate)
                                            <tr>
                                                <th>{{ $rate['mode']->getLabel() }}</th>
                                                {{-- The séjour only (Room::publicRates(), PricingMode::WEBSITE). --}}
                                                <td>par période de {{ config('hotel.stay_min_hours') }} heures</td>
                                                <td class="text-end"><strong>{{ $fcfa($rate['rate']) }} FCFA</strong> <small>{{ $rate['mode']->rateUnit() }}</small></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="small">Paiement à la réception : espèces, Orange Money ou Wave.</p>
                        </div>
                    @endif

                    @if ($room->amenities && $room->amenities->isNotEmpty())
                        <div class="room-content-box mb-25">
                            <h3>Services & équipements</h3>
                            <div class="room-amenities-box">
                                <div class="row">
                                    @foreach ($room->amenities as $amenity)
                                        <div class="col-md-4"><div class="box"><h5><i class="far {{ $amenity->icon() }}"></i>{{ $amenity->getLabel() }}</h5></div></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($room->features)
                        <div class="room-content-box mb-25">
                            <h3>Caractéristiques de la chambre</h3>
                            <ul class="check-list style-two mb-20">
                                @foreach ($room->features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($room->rules)
                        <div class="room-content-box mb-25">
                            <h3>Règles de la chambre</h3>
                            <ul class="check-list style-two">
                                @foreach ($room->rules as $rule)
                                    <li>{{ $rule }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($room->cancellation_policy)
                        <div class="room-content-box">
                            <h3>Politique d'annulation</h3>
                            <p>{!! nl2br(e($room->cancellation_policy)) !!}</p>
                        </div>
                    @endif

                    @if ($images->count() > 1)
                        <div class="room-content-box">
                            <h3>Galerie de la chambre</h3>
                            <div class="site-room-gallery">
                                @foreach ($images->skip(1) as $image)
                                    <a href="{{ $image->url() }}" class="site-room-gallery__item" target="_blank" rel="noopener"><img src="{{ $image->url() }}" alt="{{ $room->displayName() }}" loading="lazy"></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-xl-4">
                    <div class="room-sidebar-area sticky-widget mt-5 mt-xl-0">
                        <div class="room-sidebar-widget room-support-widget mb-40">
                            <h4 class="widget-title">Cette chambre vous intéresse ?</h4>
                            <div class="widget-content">
                                @if ($requestable)
                                    <p><span class="site-badge-free"><i class="fas fa-circle"></i> Libre en ce moment</span></p>
                                @endif
                                <a href="{{ route('booking.create', ['type' => $room->room_type_id]) }}" class="theme-btn style-one w-100 mb-3">Réserver cette chambre</a>
                                @if ($hotelInfo->phones())
                                    <p class="mb-2">Ou appelez la réception :</p>
                                    <ul>
                                        @foreach ($hotelInfo->phones() as $phone)
                                            <li><a href="{{ $hotelInfo->telHref($phone) }}">Tél : {{ $phone }}</a></li>
                                        @endforeach
                                        @if ($hotelInfo->whatsappUrl())
                                            <li><a href="{{ $hotelInfo->whatsappUrl() }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> WhatsApp</a></li>
                                        @endif
                                        @if ($hotelInfo->get('email'))
                                            <li><a href="mailto:{{ $hotelInfo->get('email') }}">{{ $hotelInfo->get('email') }}</a></li>
                                        @endif
                                    </ul>
                                @endif
                                <a href="{{ route('contact') }}" class="theme-btn style-two w-100 mt-2">Nous contacter</a>
                                @if ($hotelInfo->fullAddress())
                                    <h3 class="mt-4">Adresse</h3>
                                    <p>{{ implode(', ', array_filter([$hotelInfo->name(), $hotelInfo->fullAddress()])) }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($otherRooms->isNotEmpty())
    <section class="luxivo-room-sec pb-80">
        <div class="container">
            <div class="section-title text-center mb-50">
                <span class="sub-title">À DÉCOUVRIR AUSSI</span>
                <h2>Nos autres chambres</h2>
            </div>
            <div class="row justify-content-center">
                @foreach ($otherRooms as $other)
                    <div class="col-xl-4 col-md-6"><x-site::room-card :room="$other" /></div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@endsection
