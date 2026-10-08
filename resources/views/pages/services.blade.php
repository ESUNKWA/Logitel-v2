@extends('layouts.hotel')

@section('title', "Nos services - Espace Katioka")

@section('content')

<x-page-hero title="Nos services" subtitle="Découvrez l'expérience Espace Katioka" breadcrumb="Services" />

<section class="luxivo-features_three pt-115">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8">
                <div class="section-title text-center mb-50">
                    <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title">Équipements de l'hôtel</span>
                    <h2>Confort et commodités à votre service</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row justify-content-center">
            @foreach([
                ['icon' => 'icon-18.png', 'title' => 'Climatisation'],
                ['icon' => 'icon-19.png', 'title' => 'Internet haut débit'],
                ['icon' => 'icon-20.png', 'title' => 'Serrure connectée'],
                ['icon' => 'icon-21.png', 'title' => 'Salle de sport'],
                ['icon' => 'icon-22.png', 'title' => 'Petit-déjeuner'],
                ['icon' => 'icon-23.png', 'title' => 'Piscine'],
            ] as $f)
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                <div class="luxivo-iconic-box style-one mb-40 text-center">
                    <div class="icon"><img src="{{ asset('assets/images/innerpage/icon/'.$f['icon']) }}" alt="icône"></div>
                    <div class="content"><span class="line"></span><h5>{{ $f['title'] }}</h5></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="luxivo-service-sec pt-80 pb-60">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="service-card-wrapper">
                    @foreach([
                        ['num' => '01', 'sub' => 'Baignade', 'title' => 'Piscine couverte', 'img' => 'hero-2.jpg', 'text' => "Détendez-vous toute l'année dans notre piscine couverte chauffée, accessible à tous les hôtes."],
                        ['num' => '02', 'sub' => 'Espace forme', 'title' => 'Salle de sport équipée', 'img' => 'facilities-1.jpg', 'text' => "Restez actif pendant votre séjour grâce à notre salle de sport moderne, ouverte 24h/24."],
                        ['num' => '03', 'sub' => 'Restauration', 'title' => 'Café & Restaurant', 'img' => 'about-1.jpg', 'text' => "Savourez une cuisine soignée dans notre restaurant, du petit-déjeuner au dîner."],
                    ] as $s)
                    <div class="luxivo-service-card style-one mb-60">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <div class="service-content">
                                    <div class="sn-number">{{ $s['num'] }}</div>
                                    <span class="sub-title">{{ $s['sub'] }}</span>
                                    <h3 class="title"><a href="{{ route('services.show') }}">{{ $s['title'] }}</a></h3>
                                    <span class="line"></span>
                                    <p>{{ $s['text'] }}</p>
                                    <a href="{{ route('services.show') }}" class="icon-btn"><i class="far fa-arrow-right"></i></a>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="service-thumbnail"><img src="{{ asset('assets/images/stock/'.$s['img']) }}" alt="{{ $s['title'] }}"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
