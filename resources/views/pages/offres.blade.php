@extends('layouts.hotel')

@section('title', "Offres exclusives - Espace Katioka")

@section('content')

<x-page-hero title="Offres exclusives" subtitle="Profitez de belles réductions chez Espace Katioka" breadcrumb="Offres" />

<section class="luxivo-offer-sec pt-110 pb-90">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-box text-center mb-60">
                    <h3>Notre équipe conçoit des séjours abordables et inoubliables. Profitez de nos offres pour vous et vos proches.</h3>
                    <div class="line"></div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                @foreach([
                    ['img' => 'gallery-1.jpg', 'title' => 'Rattrapez vos moments manqués et célébrez en beauté', 'text' => "Profitez d'un séjour de 30 heures avec surclassement, petit-déjeuner buffet, thé de l'après-midi en chambre et plus encore."],
                    ['img' => 'room-3.jpg', 'title' => 'Chambres Deluxe : -25% sur les réservations du mois d\'août', 'text' => "Profitez de réductions exclusives sur nos chambres et suites avec nos offres à durée limitée."],
                    ['img' => 'facilities-1.jpg', 'title' => 'Salle de réunion à -25%, rattrapez vos rendez-vous manqués', 'text' => "Profitez d'un séjour de 30 heures avec surclassement, petit-déjeuner buffet et bien plus encore."],
                ] as $offer)
                <div class="luxivo-offer-item style-two mb-30">
                    <div class="thumbnail"><img src="{{ asset('assets/images/stock/'.$offer['img']) }}" alt="offre"></div>
                    <div class="content">
                        <h3 class="title"><a href="{{ route('offers.show') }}">{{ $offer['title'] }}</a></h3>
                        <div class="ratings">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                            <span>4.9 avis</span>
                        </div>
                        <p>{{ $offer['text'] }}</p>
                        <div class="offer-footer">
                            <div class="price">175 000 FCFA<span>Expire le 31 août</span></div>
                            <a href="{{ route('offers.show') }}" class="theme-btn style-two">VOIR L'OFFRE <i class="far fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

@endsection
