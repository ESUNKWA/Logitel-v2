@extends('layouts.hotel')

@section('title', 'Nos chambres & suites - Espace Katioka')

@section('content')

<x-page-hero title="Nos chambres & suites" subtitle="Découvrez le confort Espace Katioka" breadcrumb="Chambres & Suites" />

<section class="luxivo-room-sec pt-110 pb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-7">
                <div class="section-title text-center mb-60">
                    <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title">CONFORT DE LUXE</span>
                    <h2>Nos chambres & suites en vedette</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="luxivo-filter-nav text-center mb-60">
                    <ul class="filter-nav-items">
                        <li class="active" data-filter="*">Tout afficher</li>
                        <li data-filter=".cat1">Chambres de luxe</li>
                        <li data-filter=".cat2">Suites</li>
                        <li data-filter=".cat3">Chambres Deluxe</li>
                        <li data-filter=".cat4">Chambres twin</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="luxivo-isotope">
            <div class="row isotope-grid">
                @foreach([
                    ['img' => 'assets/images/stock/room-3.jpg', 'title' => 'Suite Prestige Royale', 'cat' => 'cat1'],
                    ['img' => 'assets/images/stock/room-4.jpg', 'title' => "Escapade Exclusive du Week-end", 'cat' => 'cat2'],
                    ['img' => 'assets/images/stock/room-5.jpg', 'title' => 'Chambres Deluxe Familiales', 'cat' => 'cat3'],
                    ['img' => 'assets/images/stock/room-8.jpg', 'title' => 'Suites Signature de Luxe', 'cat' => 'cat4'],
                    ['img' => 'assets/images/stock/bathroom-2.jpg', 'title' => 'Chambres Suites Signature', 'cat' => 'cat3'],
                    ['img' => 'assets/images/stock/bathroom-3.jpg', 'title' => 'Chambre Expérience', 'cat' => 'cat2'],
                    ['img' => 'assets/images/stock/bathroom-4.jpg', 'title' => 'Chambre Jardin Tropical', 'cat' => 'cat4'],
                    ['img' => 'assets/images/stock/room-3.jpg', 'title' => 'Chambres Deluxe Exclusives', 'cat' => 'cat1'],
                ] as $room)
                <div class="col-xxl-3 col-xl-4 col-md-6 col-sm-12 isotope-filter-item {{ $room['cat'] }}">
                    <x-room-card :image="$room['img']" :title="$room['title']" price="75 000 FCFA" sqm="250 sqm" beds="3 lits" baths="3 salles de bain" />
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<div class="luxivo-counter_one bg_cover pt-110 pb-70" data-src="{{ asset('assets/images/stock/hero-bg.jpg') }}">
    <div class="container">
        <div class="row">
            @foreach([['635', '+', 'Chambres de luxe'], ['85', 'k+', 'Clients heureux'], ['2.3', 'k+', 'Avis cinq étoiles'], ['2.8', 'm', 'Petits-déjeuners servis']] as $stat)
            <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="luxivo-counter-item style-one mb-40">
                    <div class="content">
                        <h2><span class="counter">{{ $stat[0] }}</span>{{ $stat[1] }}</h2>
                        <p>{{ $stat[2] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<section class="luxivo-features_two pt-120 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-10">
                <div class="section-title text-center mb-60">
                    <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title">NOS SERVICES</span>
                    <h2>Un séjour amélioré, des attentions particulières</h2>
                </div>
            </div>
        </div>
        <div class="row align-items-center">
            <div class="col-xl-4 col-md-6 order-2 order-xl-1">
                <div class="features-list list-left mb-5 mb-lg-0">
                    <div class="luxivo-iconic-box style-two">
                        <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/icon-4.png') }}" alt="icône"></div>
                        <div class="content">
                            <h4 class="title">Transfert aéroport</h4>
                            <p>Un service de prise en charge et de dépose simple et sans stress pour un voyage serein.</p>
                        </div>
                    </div>
                    <div class="luxivo-iconic-box style-two">
                        <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/icon-5.png') }}" alt="icône"></div>
                        <div class="content">
                            <h4 class="title">Accès chambre intelligent</h4>
                            <p>Un accès sans clé et des commandes intelligentes pour la lumière et la température.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-lg-12 order-1 order-xl-2">
                <div class="luxivo-image-box mb-5 mb-xl-0">
                    <div class="shape"><img src="{{ asset('assets/images/innerpage/gallery/feat-shape.png') }}" alt="forme"></div>
                    <div class="luxivo-image"><img src="{{ asset('assets/images/stock/about-1.jpg') }}" alt="équipement"></div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 order-3">
                <div class="features-list list-right">
                    <div class="luxivo-iconic-box style-two">
                        <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/icon-5.png') }}" alt="icône"></div>
                        <div class="content">
                            <h4 class="title">Accueil disponible 24h/24</h4>
                            <p>Notre équipe est disponible à tout moment pour vos arrivées, réservations et demandes.</p>
                        </div>
                    </div>
                    <div class="luxivo-iconic-box style-two">
                        <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/icon-6.png') }}" alt="icône"></div>
                        <div class="content">
                            <h4 class="title">Service en chambre</h4>
                            <p>Détendez-vous et savourez nos plats et boissons dans le confort de votre chambre, 24h/24.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
