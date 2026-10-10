@extends('site.layouts.app')

@section('title', $hotelInfo->name())
@section('meta_description', \App\Models\SiteContent::get('hero_text', $hotelInfo->get('tagline') ?: $hotelInfo->name()))

@section('content')

{{-- Texts and photos: « Site web → Accueil » in the application (SiteContent). --}}
<section class="luxivo-hero">
    <div class="luxivo-hero_one p-r z-1 bg_cover" data-src="{{ \App\Models\SiteContent::photoUrl('hero_background') ?? asset('site/images/stock/hero-bg.jpg') }}">
        @if (\App\Models\SiteContent::photoUrl('hero_photo_1'))
            <div class="hero-image hero-image_one" data-aos="fade-up" data-aos-duration="2500" data-aos-delay="10"><img src="{{ \App\Models\SiteContent::photoUrl('hero_photo_1') }}" alt="{{ $hotelInfo->name() }}"></div>
        @endif
        @if (\App\Models\SiteContent::photoUrl('hero_photo_2'))
            <div class="hero-image hero-image_two" data-aos="fade-down" data-aos-duration="3000" data-aos-delay="20"><img src="{{ \App\Models\SiteContent::photoUrl('hero_photo_2') }}" alt="{{ $hotelInfo->name() }}"></div>
        @endif
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="hero-content text-center text-xl-start mb-25">
                        <h1 data-aos="fade-left" data-aos-duration="2500">{{ \App\Models\SiteContent::get('hero_title', 'Bienvenue à '.$hotelInfo->name()) }}</h1>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="text-box" data-aos="fade-up" data-aos-duration="2600">
                        @if (\App\Models\SiteContent::get('hero_text', $hotelInfo->get('tagline')))
                            <p>{{ \App\Models\SiteContent::get('hero_text', $hotelInfo->get('tagline')) }}</p>
                        @endif
                        <div class="hero-button">
                            <a href="{{ route('rooms.index') }}" class="theme-btn style-one">Découvrir nos chambres<i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Guests already at the hotel: room request from their phone. --}}
<section class="site-cta pt-60 pb-60">
    <div class="container">
        <div class="site-cta__box">
            <div>
                <h3>Vous êtes déjà à l'hôtel ?</h3>
                <p>Choisissez une chambre libre depuis votre téléphone : la réception valide votre demande et vous accueille.</p>
            </div>
            <a href="{{ route('stay-request.index') }}" class="theme-btn style-one">Demander une chambre <i class="far fa-arrow-right"></i></a>
        </div>
    </div>
</section>

@php($stats = \App\Models\SiteContent::stats())
@if (\App\Models\SiteContent::get('about_text'))
<section class="luxivo-about_one pt-120 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            @if (\App\Models\SiteContent::photoUrl('about_photo_1'))
            <div class="col-xl-6 col-lg-9">
                <div class="luxivo-image-box {{ \App\Models\SiteContent::photoUrl('about_photo_2') ? '' : 'site-about--single' }}">
                    <div class="luxivo-image image_one" data-aos="fade-up" data-aos-duration="800">
                        <img src="{{ \App\Models\SiteContent::photoUrl('about_photo_1') }}" alt="{{ $hotelInfo->name() }}">
                    </div>
                    @if (\App\Models\SiteContent::photoUrl('about_photo_2'))
                        <div class="luxivo-image image_two" data-aos="fade-up" data-aos-duration="1000">
                            <img src="{{ \App\Models\SiteContent::photoUrl('about_photo_2') }}" alt="{{ $hotelInfo->name() }}">
                        </div>
                    @endif
                    @if (isset($stats[0]))
                        <div class="luxivo-customer-box" data-aos="fade-up" data-aos-duration="1000">
                            <div class="content"><h3>{{ $stats[0][0] }}</h3><p>{{ $stats[0][1] }}</p></div>
                        </div>
                    @endif
                    @if (isset($stats[1]))
                        <div class="luxivo-experience-box" data-aos="fade-down" data-aos-duration="1000">
                            <div class="content"><h3>{{ $stats[1][0] }}</h3><p>{{ $stats[1][1] }}</p></div>
                        </div>
                    @endif
                </div>
            </div>
            @endif
            <div class="{{ \App\Models\SiteContent::photoUrl('about_photo_1') ? 'col-xl-6' : 'col-xl-8 text-center' }} col-lg-9">
                <div class="luxivo-content-box mt-5 mt-xl-0">
                    <div class="section-title">
                        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">{{ \App\Models\SiteContent::get('about_subtitle', 'À propos de l\'hôtel') }}</span>
                        @if (\App\Models\SiteContent::get('about_title'))
                            <h2 data-aos="fade-up" data-aos-duration="1200">{{ \App\Models\SiteContent::get('about_title') }}</h2>
                        @endif
                    </div>
                    @foreach (preg_split('/\R{2,}/', \App\Models\SiteContent::get('about_text')) as $paragraph)
                        <p>{!! nl2br(e($paragraph)) !!}</p>
                    @endforeach
                    @if (\App\Models\SiteContent::highlights())
                        <ul class="check-list style-one mb-40" data-aos="fade-up" data-aos-duration="1400">
                            @foreach (\App\Models\SiteContent::highlights() as $highlight)
                                <li><i class="fas fa-badge-check"></i>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="luxivo-button-wrap" data-aos="fade-up" data-aos-duration="1600">
                        <div class="luxivo-button">
                            <a href="{{ route('rooms.index') }}" class="theme-btn style-one">NOS CHAMBRES <i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- Rooms published from the application (hidden while none is published). --}}
@if ($rooms->isNotEmpty())
<section class="luxivo-room_one p-r z-1 pt-120 pb-120">
    <div class="room-bg" data-src="{{ asset('site/images/stock/hero-bg.jpg') }}"></div>
    <div class="container">
        <div class="row align-items-end">
            <div class="col-xl-6 col-lg-8">
                <div class="section-title mb-45">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Nos chambres</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Reposez-vous dans le confort : choisissez votre chambre</h2>
                </div>
            </div>
            <div class="col-xl-6 col-lg-4">
                <div class="room-arrows mb-60"></div>
            </div>
        </div>
        <div class="room-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach ($rooms as $room)
                @php($lowest = $room->lowestRate())
                <div class="luxivo-room-item style-one">
                    <div class="room-image">
                        @if ($room->coverUrl())
                            <img src="{{ $room->coverUrl() }}" alt="{{ $room->displayName() }}">
                        @else
                            <div class="room-photo-placeholder" role="img" aria-label="{{ $room->displayName() }}"><i class="far fa-bed"></i></div>
                        @endif
                    </div>
                    <div class="room-content">
                        <h4 class="title"><a href="{{ $room->publicUrl() }}">{{ $room->displayName() }}</a></h4>
                        <p>{{ $room->tagline ?: \Illuminate\Support\Str::limit((string) $room->description, 110) ?: $room->roomType->name }}</p>
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
                        <div class="room-bottom">
                            @if ($lowest)
                                <div class="price">{{ number_format($lowest['rate'], 0, ',', ' ') }} FCFA<span>{{ $lowest['mode']->rateUnit() }}</span></div>
                            @endif
                            <a href="{{ $room->publicUrl() }}" class="theme-btn style-two">VOIR DÉTAILS<i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Services featured on the home page (« Site web → Services », « Mettre en avant sur l'accueil »). --}}
@if ($services->isNotEmpty())
<section class="site-services pt-120 pb-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-7">
                <div class="section-title text-center mb-50">
                    <span class="line d-block mb-10"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                    <span class="sub-title">Nos services</span>
                    <h2>Tout pour un séjour agréable</h2>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            @foreach ($services as $service)
                <div class="col-xl-4 col-md-6 mb-30">
                    <x-site::service-card :service="$service" />
                </div>
            @endforeach
        </div>
        <div class="text-center mt-2">
            <a href="{{ route('services.index') }}" class="theme-btn style-two">Tous nos services <i class="far fa-arrow-right"></i></a>
        </div>
    </div>
</section>
@endif



@if ($stats)
<section class="luxivo-counter_one bg_cover pt-110 pb-70" data-src="{{ asset('site/images/stock/footer-bg.jpg') }}">
    <div class="container">
        <div class="row justify-content-center">
            @foreach ($stats as [$value, $label])
                <div class="col-lg-3 col-sm-6">
                    <div class="luxivo-counter-item style-one mb-40">
                        <div class="content">
                            <h2>{{ $value }}</h2>
                            <p>{{ $label }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@php($testimonials = \App\Models\SiteContent::testimonials())
@if ($testimonials)
<section class="luxivo-testimonial-one pt-110 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6">
                <div class="section-title mb-50 text-center">
                    <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                    <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Avis clients</span>
                    <h2 data-aos="fade-up" data-aos-duration="1200">Ce que disent nos clients</h2>
                </div>
            </div>
        </div>
        <div class="testimonial-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach ($testimonials as $testimonial)
                <div class="luxivo-testimonial-item style-one">
                    <div class="testimonial-content">
                        <div class="ratings-quote-wrap">
                            <div class="quote"><i class="fas fa-quote-left"></i></div>
                        </div>
                        <p>« {{ $testimonial['text'] }} »</p>
                        <div class="author-thumb-item">
                            <div class="author-thumb site-initials">{{ mb_strtoupper(mb_substr($testimonial['name'], 0, 1)) }}</div>
                            <div class="author-info">
                                <h5>{{ $testimonial['name'] }}</h5>
                                @if ($testimonial['role'])
                                    <span class="position">{{ $testimonial['role'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="luxivo-contact_one pt-60 pb-60">
    <div class="contact-bg-left bg_cover" data-src="{{ asset('site/images/stock/about-1.jpg') }}" data-aos="fade-up" data-aos-duration="800">
        <div class="luxivo-info-box">
            <div class="content">
                <span class="line d-block mb-10"><img src="{{ asset('site/images/home-one/contact/d-line.png') }}" alt="ligne"></span>
                @if ($hotelInfo->get('phone'))
                    <span>Appelez-nous pour toute demande</span>
                    <h3><a href="{{ $hotelInfo->telHref($hotelInfo->get('phone')) }}">{{ $hotelInfo->get('phone') }}</a></h3>
                @else
                    <span>Une question, une demande ?</span>
                    <h3><a href="{{ route('contact') }}">Contactez-nous</a></h3>
                @endif
            </div>
        </div>
    </div>
    <div class="contact-bg-right bg_cover" data-src="{{ asset('site/images/stock/gallery-2.jpg') }}"></div>
    <div class="container">
        <div class="row justify-content-end">
            <div class="col-xl-7">
                <div class="booking-wrapper">
                    <div class="section-title mb-50">
                        <span class="line d-block mb-10" data-aos="fade-up" data-aos-duration="800"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title" data-aos="fade-up" data-aos-duration="1000">Contactez-nous</span>
                        <h2 data-aos="fade-up" data-aos-duration="1200">Faisons de votre séjour un moment inoubliable</h2>
                        <p data-aos="fade-up" data-aos-duration="1400">Une question, besoin d'aide pour une réservation ou envie d'une expérience sur mesure ? Notre équipe n'est qu'à un message.</p>
                    </div>
                    <form class="booking-form" data-aos="fade-up" data-aos-duration="1600">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Nom</label>
                                    <input type="text" class="form_control" placeholder="Votre nom" name="name">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>E-mail</label>
                                    <input type="email" class="form_control" placeholder="Votre e-mail" name="email">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Arrivée</label>
                                    <input type="date" class="form_control" name="CheckIn">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Départ</label>
                                    <input type="date" class="form_control" name="CheckOut">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <label>Message</label>
                                <div class="form-group">
                                    <textarea name="message" class="form_control" rows="4" placeholder="Écrivez votre message"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <button class="theme-btn style-one" type="button">VÉRIFIER LES DISPONIBILITÉS <i class="far fa-arrow-right"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="luxivo-instagram-sec pt-110 pb-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-box text-center mb-55">
                    <h5>Suivez-nous sur Instagram</h5>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="gallery-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach(['hero-bg.jpg','gallery-1.jpg','gallery-2.jpg','gallery-3.jpg','gallery-4.jpg','about-2.jpg','choose-1.jpg'] as $img)
            <div class="luxivo-gallery-item style-one">
                <div class="thumbnail">
                    <img src="{{ asset('site/images/stock/'.$img) }}" alt="galerie">
                    <div class="hover-content">
                        <a href="https://www.instagram.com/" target="_blank" class="icon-btn"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
