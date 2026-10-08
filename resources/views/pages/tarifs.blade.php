@extends('layouts.hotel')

@section('title', "Nos tarifs - Espace Katioka")

@section('content')

<x-page-hero title="Nos tarifs" subtitle="Réservez dès maintenant votre séjour" breadcrumb="Tarifs" />

<section class="luxivo-pricing-sec pt-115 pb-80">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="section-title text-center mb-60">
                    <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title">Grille tarifaire</span>
                    <h2>Choisissez le séjour qui vous convient</h2>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            @foreach([
                ['plan' => 'Chambre Deluxe', 'price' => '75 000', 'text' => 'Idéale pour les voyageurs seuls ou en couple, à la recherche d\'un confort élégant.', 'items' => ['Lit Queen size', 'Contrôle intelligent de la chambre', 'Wi-Fi gratuit', 'Petit-déjeuner offert', 'Ménage quotidien', 'Climatisation']],
                ['plan' => 'Suite Signature', 'price' => '115 000', 'text' => 'Une expérience haut de gamme avec des équipements de luxe et plus d\'espace.', 'items' => ['Lit King size', 'Balcon privé avec vue', 'Salon séparé', 'Accès par serrure connectée', 'Boisson de bienvenue', 'Tous les avantages Deluxe']],
                ['plan' => 'Chambre Deluxe', 'price' => '75 000', 'text' => 'Idéale pour les voyageurs seuls ou en couple, à la recherche d\'un confort élégant.', 'items' => ['Lit Queen size', 'Contrôle intelligent de la chambre', 'Wi-Fi gratuit', 'Petit-déjeuner offert', 'Ménage quotidien', 'Climatisation']],
            ] as $plan)
            <div class="col-xl-4 col-md-6 col-sm-12">
                <div class="luxivo-pricing-item style-one mb-40">
                    <div class="pricing-header text-center">
                        <span class="plan">{{ $plan['plan'] }}</span>
                        <h3 class="price">{{ $plan['price'] }} FCFA/<span class="time">nuit</span></h3>
                        <p>{{ $plan['text'] }}</p>
                    </div>
                    <div class="pricing-body">
                        <ul class="check-list style-one">
                            @foreach($plan['items'] as $item)
                            <li><i class="fas fa-badge-check"></i>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ route('contact') }}" class="theme-btn style-one">RÉSERVER<i class="far fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
