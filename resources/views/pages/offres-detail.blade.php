@extends('layouts.hotel')

@section('title', "Séjour détente, deux fois plus de plaisir - Hôtel Evation")

@section('content')

<x-page-hero title="Séjour détente, deux fois plus de plaisir" subtitle="Profitez de belles réductions chez Hôtel Evation" breadcrumb="Détails d'une offre" />

<section class="luxivo-offer-details-sec pt-120 pb-75">
    <div class="container">
        <div class="row">
            <div class="col-xl-8">
                <div class="offer-details-wrapper">
                    <div class="offer-content">
                        <div class="content mb-35">
                            <h3 class="title">Nous rendons votre séjour encore meilleur ! Économisez et profitez d'un surclassement gratuit ce mois-ci.</h3>
                            <p>Profitez de notre offre exceptionnelle, valable uniquement en semaine, pour vous évader au calme au meilleur tarif. Bénéficiez des avantages inclus dans ce forfait pour améliorer votre séjour, avec une politique d'annulation flexible.</p>
                            <span>Période de réservation : maintenant – 20 septembre 2025</span>
                            <span>Période de séjour : maintenant – 31 octobre 2025</span>
                        </div>
                        <div class="content-box mb-35">
                            <h3>Avantages inclus :</h3>
                            <ul class="check-list style-one">
                                <li><i class="fas fa-badge-check"></i>Boisson de bienvenue à l'arrivée</li>
                                <li><i class="fas fa-badge-check"></i>Fruits de saison offerts en chambre</li>
                                <li><i class="fas fa-badge-check"></i>-30% sur la blanchisserie</li>
                                <li><i class="fas fa-badge-check"></i>-25% sur le service en chambre, la restauration et le thé de l'après-midi</li>
                                <li><i class="fas fa-badge-check"></i>Wi-Fi rapide gratuit</li>
                                <li><i class="fas fa-badge-check"></i>Accès offert à la piscine</li>
                                <li><i class="fas fa-badge-check"></i>Surclassement gratuit selon disponibilité</li>
                            </ul>
                        </div>
                        <div class="content-box mb-35">
                            <h3>Conditions générales :</h3>
                            <ul class="check-list style-two">
                                <li>Annulation gratuite jusqu'à 24 heures avant la date d'arrivée.</li>
                                <li>Annulation à moins de 24 heures : une nuit sera facturée.</li>
                                <li>En cas de non-présentation, le montant total sera débité.</li>
                                <li>Les avantages du forfait ne sont ni transférables ni remboursables.</li>
                                <li>Cette offre est valable pour les séjours jusqu'au 31 octobre 2025.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="offer-sidebar-area sticky-widget">
                    <div class="offer-widget-box mb-40">
                        <h3>Pourquoi choisir Hôtel Evation</h3>
                        <ul class="check-list style-one">
                            <li><i class="fas fa-badge-check"></i>Transparence – aucun frais caché</li>
                            <li><i class="fas fa-badge-check"></i>Transfert aéroport offert</li>
                            <li><i class="fas fa-badge-check"></i>Un rapport qualité-prix imbattable</li>
                            <li><i class="fas fa-badge-check"></i>Des chambres pensées pour votre confort</li>
                            <li><i class="fas fa-badge-check"></i>Remboursement simplifié en ligne</li>
                        </ul>
                    </div>
                    <div class="offer-widget-box mb-40">
                        <h3>Assistance réservation</h3>
                        <div class="info-box">
                            @foreach ($hotelInfo->phones() as $phone)
                                <p><a href="{{ $hotelInfo->telHref($phone) }}">Tél : {{ $phone }}</a></p>
                            @endforeach
                            @if ($hotelInfo->get('email'))
                                <p><a href="mailto:{{ $hotelInfo->get('email') }}">{{ $hotelInfo->get('email') }}</a></p>
                            @endif
                        </div>
                        <p>{{ implode(', ', array_filter([$hotelInfo->name(), $hotelInfo->fullAddress()])) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="luxivo-room-sec pb-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title mb-60">
                    <h3>Disponible dans ces suites & chambres</h3>
                </div>
            </div>
        </div>
        <div class="room-slider-three">
            @foreach(['room-3.jpg','room-4.jpg','room-5.jpg','room-8.jpg'] as $img)
            <x-room-card image="assets/images/stock/{{ $img }}" title="Chambres Deluxe Familiales" price="75 000 FCFA" sqm="250 sqm" beds="3 lits" baths="3 salles de bain" />
            @endforeach
        </div>
    </div>
</section>

@endsection
