@extends('layouts.hotel')

@section('title', "À propos - Hôtel Evation")

@section('content')

<x-page-hero title="À propos de l'hôtel" subtitle="Le meilleur hôtel pour vous à Bouaké" breadcrumb="À propos" />

<section class="luxivo-about-sec pt-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="luxivo-about-content-box text-center mb-60">
                    <div class="section-title mb-30">
                        <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title">Bienvenue à Hôtel Evation</span>
                        <h2>Votre expérience nous tient à cœur</h2>
                    </div>
                    <p>Bienvenue à Hôtel Evation, votre havre d'élégance, de confort et de moments inoubliables. Notre établissement vous propose bien plus qu'un simple séjour : un véritable voyage vers la détente et la découverte.</p>
                    <p>Nous nous engageons chaque jour à redéfinir l'hospitalité, en conjuguant équipements modernes et service chaleureux et personnalisé. De nos chambres et suites raffinées à notre restauration soignée, chaque détail est pensé pour rendre votre séjour exceptionnel.</p>
                    <p>Que vous soyez ici pour une escapade romantique, un séjour en famille ou un moment de ressourcement, Hôtel Evation est l'endroit où naissent les souvenirs précieux.</p>
                    <div class="author-box">
                        <h5>Le Directeur Général <span>- Hôtel Evation</span></h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-fluid">
            <div class="gallery-slider-two">
                @foreach(['about-1.jpg','about-2.jpg','choose-1.jpg','gallery-2.jpg'] as $img)
                <div class="luxivo-gallery-item style-two">
                    <div class="thumbnail">
                        <img src="{{ asset('assets/images/stock/'.$img) }}" alt="galerie">
                        <div class="hover-content">
                            <a href="{{ asset('assets/images/stock/'.$img) }}" class="img-popup icon-btn"><i class="far fa-plus"></i></a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<section class="luxivo-features-sec pb-80 pt-100">
    <div class="container">
        <div class="row justify-content-center">
            @foreach([
                ['icon' => 'icon-1.png', 'title' => 'Hôtel 5 étoiles', 'text' => 'Notre établissement de luxe pensé pour votre confort et votre bien-être.'],
                ['icon' => 'icon-2.png', 'title' => 'Votre sécurité avant tout', 'text' => 'Votre sécurité et votre bien-être sont notre priorité, avec des protocoles stricts.'],
                ['icon' => 'icon-3.png', 'title' => 'Changement de chambre', 'text' => "Vous disposez d'un droit de changement de chambre dans les 24h suivant la réservation."],
            ] as $f)
            <div class="col-xl-4 col-md-6">
                <div class="luxivo-feature-item style-two mb-40">
                    <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/'.$f['icon']) }}" alt="icône"></div>
                    <div class="content">
                        <h4>{{ $f['title'] }}</h4>
                        <p>{{ $f['text'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-choose_two pt-120 pb-120">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8 col-md-10">
                <div class="luxivo-content-box mb-5 mb-xl-0">
                    <div class="section-title">
                        <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                        <span class="sub-title">Pourquoi nous choisir</span>
                        <h2>Luxe & confort, un service qui satisfait toujours</h2>
                    </div>
                    <p class="mb-30">Chaque détail de votre séjour est pensé avec soin, du linge de toilette à l'accueil personnalisé, pour vous garantir une expérience à la hauteur de vos attentes.</p>
                    <div class="row">
                        <div class="col-lg-6">
                            <ul class="check-list style-one">
                                <li><i class="fas fa-badge-check"></i>Courtoisie de la maison</li>
                                <li><i class="fas fa-badge-check"></i>Internet haut débit</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <ul class="check-list style-one">
                                <li><i class="fas fa-badge-check"></i>Petit-déjeuner offert</li>
                                <li><i class="fas fa-badge-check"></i>Livraisons & expéditions</li>
                            </ul>
                        </div>
                    </div>
                    <div class="luxivo-button mt-40">
                        <a href="{{ route('about') }}" class="theme-btn style-one">EN SAVOIR PLUS <i class="far fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-lg-8 col-md-10">
                <div class="luxivo-image">
                    <img src="{{ asset('assets/images/stock/choose-1.jpg') }}" alt="pourquoi nous choisir">
                </div>
            </div>
        </div>
    </div>
</section>

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
    <div class="container-fluid">
        <div class="row">
            @foreach(['room-3.jpg','room-4.jpg','room-5.jpg','room-8.jpg'] as $img)
            <div class="col-xl-3 col-md-6 col-sm-12">
                <x-room-card image="assets/images/stock/{{ $img }}" title="Chambres Deluxe Familiales" price="75 000 FCFA" sqm="250 sqm" beds="3 lits" baths="3 salles de bain" />
            </div>
            @endforeach
        </div>
    </div>
</section>

<div class="luxivo-counter_one bg_cover pt-110 pb-70" data-src="{{ asset('assets/images/stock/hero-2.jpg') }}">
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

@endsection
