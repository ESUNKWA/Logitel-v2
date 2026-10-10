@extends('site.layouts.app')

@section('title', 'Nos chambres - '.$hotelInfo->name())

@section('content')

<x-site::page-hero title="Nos chambres" :subtitle="'Choisissez votre chambre à '.$hotelInfo->name()" breadcrumb="Chambres" />

<section class="luxivo-room-sec pt-110 pb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-7">
                <div class="section-title text-center mb-60">
                    <span class="line d-block mb-10"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                    <span class="sub-title">NOS CHAMBRES</span>
                    <h2>Des chambres pour chaque séjour</h2>
                    <p class="mt-3">À l'heure, à la journée, pour la nuit ou pour plusieurs jours : découvrez nos chambres et leurs tarifs.</p>
                </div>
            </div>
        </div>
    </div>

    @if ($rooms->isEmpty())
        <div class="container">
            <div class="site-empty text-center">
                <i class="far fa-bed"></i>
                <h4>Nos chambres seront bientôt présentées ici.</h4>
                <p>En attendant, contactez-nous ou, si vous êtes déjà sur place, faites votre demande depuis votre téléphone.</p>
                <a href="{{ route('stay-request.index') }}" class="theme-btn style-one me-2">Demander une chambre <i class="far fa-arrow-right"></i></a>
                <a href="{{ route('contact') }}" class="theme-btn style-two">Nous contacter</a>
            </div>
        </div>
    @else
        @if ($types->count() > 1)
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="luxivo-filter-nav text-center mb-60">
                            <ul class="filter-nav-items">
                                <li class="active" data-filter="*">Toutes</li>
                                @foreach ($types as $type)
                                    <li data-filter=".{{ \App\Http\Controllers\Site\RoomController::typeFilter($type) }}">{{ $type->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif
        <div class="container">
            <div class="luxivo-isotope">
                <div class="row isotope-grid">
                    @foreach ($rooms as $room)
                        <div class="col-xl-4 col-md-6 col-sm-12 isotope-filter-item {{ \App\Http\Controllers\Site\RoomController::typeFilter($room->roomType) }}">
                            <x-site::room-card :room="$room" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</section>

<section class="site-cta pb-120">
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

@endsection
