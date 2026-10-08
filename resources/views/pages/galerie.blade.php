@extends('layouts.hotel')

@section('title', "Galerie - Espace Katioka")

@section('content')

<x-page-hero title="Galerie" subtitle="Découvrez l'univers d'Espace Katioka en images" breadcrumb="Galerie" />

<section class="luxivo-gallery pt-120 pb-90">
    <div class="container">
        <div class="row">
            @php
                $images = ['hero-bg.jpg','about-1.jpg','about-2.jpg','choose-1.jpg','room-3.jpg','bathroom-2.jpg','gallery-1.jpg','gallery-2.jpg','facilities-1.jpg'];
                $cols = ['col-lg-3 col-md-6 col-sm-12','col-lg-3 col-md-6 col-sm-12','col-lg-6 col-sm-12','col-lg-6 col-sm-12','col-lg-3 col-md-6 col-sm-12','col-lg-3 col-md-6 col-sm-12','col-lg-3 col-md-6 col-sm-12','col-lg-3 col-md-6 col-sm-12','col-lg-6 col-sm-12'];
            @endphp
            @foreach($images as $i => $img)
            <div class="{{ $cols[$i] }}">
                <div class="luxivo-gallery-item style-two mb-30">
                    <div class="thumbnail">
                        <img src="{{ asset('assets/images/stock/'.$img) }}" alt="galerie">
                        <div class="hover-content">
                            <a href="{{ asset('assets/images/stock/'.$img) }}" class="img-popup icon-btn"><i class="fas fa-plus"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
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
        <div class="gallery-slider">
            @foreach(['room-4.jpg','room-5.jpg','room-8.jpg','bathroom-3.jpg','bathroom-4.jpg','gallery-3.jpg','gallery-4.jpg'] as $img)
            <div class="luxivo-gallery-item style-one">
                <div class="thumbnail">
                    <img src="{{ asset('assets/images/stock/'.$img) }}" alt="galerie">
                    <div class="hover-content">
                        <a href="#" class="icon-btn"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
