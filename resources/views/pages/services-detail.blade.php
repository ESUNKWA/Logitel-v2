@extends('layouts.hotel')

@section('title', "Salle de sport équipée - Espace Katioka")

@section('content')

<x-page-hero title="Salle de sport équipée" subtitle="Chez Espace Katioka, nous soignons chaque détail de votre expérience" breadcrumb="Détails d'un service" />

<section class="luxivo-service-details-sec pt-120 pb-95">
    <div class="container">
        <div class="service-details-wrapper">
            <div class="luxivo-service-item">
                <div class="service-thumbnail">
                    <img src="{{ asset('assets/images/stock/facilities-2.jpg') }}" alt="salle de sport">
                </div>
                <div class="service-content">
                    <h3 class="title">Salle de sport équipée</h3>
                    <p>Nous proposons une large gamme de services à nos hôtes : restaurant, bar, court de tennis, piscine, institut de beauté et salle de sport. Nous améliorons continuellement nos services pour que chaque client se sente comme chez lui, avec des restaurants et bars proposant une cuisine et des boissons variées.</p>
                    <p>Certains forfaits incluent des réductions sur les repas ou l'accès à des événements spéciaux. Notre équipe reste à votre disposition pour toute demande particulière durant votre séjour.</p>
                    <div class="content-box">
                        <h3>Aperçu du service</h3>
                        <p>Notre salle de sport est accessible 24h/24 et équipée d'appareils cardio et de musculation modernes, adaptés à tous les niveaux. Un espace pensé pour rester actif même en déplacement.</p>
                        <div class="row">
                            <div class="col-md-6"><figure><img src="{{ asset('assets/images/stock/facilities-1.jpg') }}" alt="service"></figure></div>
                            <div class="col-md-6"><figure><img src="{{ asset('assets/images/stock/gallery-1.jpg') }}" alt="service"></figure></div>
                        </div>
                        <p>Un coach est disponible sur demande pour vous accompagner pendant votre séance. Serviettes et bouteilles d'eau sont mises à disposition gratuitement.</p>
                    </div>
                </div>
            </div>
            <div class="features-box mb-40">
                <h3>Tous les avantages</h3>
                <div class="row">
                    <div class="col-lg-3 col-md-6">
                        <ul class="check-list style-one mb-40">
                            <li><i class="fas fa-badge-check"></i>Nouvelle carte</li>
                            <li><i class="fas fa-badge-check"></i>Ménage</li>
                            <li><i class="fas fa-badge-check"></i>Conciergerie</li>
                            <li><i class="fas fa-badge-check"></i>Blanchisserie</li>
                            <li><i class="fas fa-badge-check"></i>Coin café</li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <ul class="check-list style-one mb-40">
                            <li><i class="fas fa-badge-check"></i>Wi-Fi gratuit</li>
                            <li><i class="fas fa-badge-check"></i>Coffre-fort</li>
                            <li><i class="fas fa-badge-check"></i>Espace bureau</li>
                            <li><i class="fas fa-badge-check"></i>Penderie</li>
                            <li><i class="fas fa-badge-check"></i>Climatisation</li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <ul class="check-list style-one mb-40">
                            <li><i class="fas fa-badge-check"></i>Rideaux occultants</li>
                            <li><i class="fas fa-badge-check"></i>Téléphone</li>
                            <li><i class="fas fa-badge-check"></i>Salle de bain privative</li>
                            <li><i class="fas fa-badge-check"></i>Petit-déjeuner offert</li>
                            <li><i class="fas fa-badge-check"></i>Bar restaurant</li>
                        </ul>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <ul class="check-list style-one mb-40">
                            <li><i class="fas fa-badge-check"></i>Insonorisation</li>
                            <li><i class="fas fa-badge-check"></i>Peignoirs & chaussons</li>
                            <li><i class="fas fa-badge-check"></i>Ports USB</li>
                            <li><i class="fas fa-badge-check"></i>Balcon ou vue dégagée</li>
                            <li><i class="fas fa-badge-check"></i>Piscine</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="faq-wrapper">
                <h3>Questions fréquentes</h3>
                <div class="accordion" id="accordionOne">
                    @foreach([
                        ["Quelles sont les heures d'ouverture de la salle de sport ?", "La salle de sport d'Espace Katioka est accessible 24h/24 pour tous les clients de l'hôtel."],
                        ["L'accès est-il inclus dans le prix de la chambre ?", "Oui, l'accès à la salle de sport est offert à tous nos hôtes, sans frais supplémentaires."],
                        ["Un coach sportif est-il disponible ?", "Un accompagnement est possible sur demande auprès de la réception, selon disponibilité."],
                        ["Faut-il réserver un créneau à l'avance ?", "Non, l'accès est libre, mais nous recommandons d'éviter les heures de forte affluence."],
                    ] as $i => $faq)
                    <div class="accordion-card mb-25">
                        <div class="accordion-header">
                            <h5 class="accordion-title" data-bs-toggle="collapse" data-bs-target="#collapse{{ $i + 1 }}" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                {{ $faq[0] }}
                            </h5>
                        </div>
                        <div id="collapse{{ $i + 1 }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#accordionOne">
                            <div class="accordion-content"><p>{{ $faq[1] }}</p></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
