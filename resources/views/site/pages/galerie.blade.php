@extends('site.layouts.app')

@section('title', 'Galerie - '.$hotelInfo->name())

@section('content')

<x-site::page-hero title="Galerie" :subtitle="'Découvrez '.$hotelInfo->name().' en images'" breadcrumb="Galerie" />

{{-- Photos: « Site web → Galerie » in the application. --}}
<section class="luxivo-gallery pt-120 pb-90">
    @if ($photos->isEmpty())
        <div class="container">
            <div class="site-empty text-center">
                <i class="fal fa-images"></i>
                <h4>Galerie bientôt disponible</h4>
                <p>Nos photos seront bientôt présentées ici. En attendant, découvrez nos chambres.</p>
                <a href="{{ route('rooms.index') }}" class="theme-btn style-one mt-3">Nos chambres <i class="far fa-arrow-right"></i></a>
            </div>
        </div>
    @else
        @if ($categories->count() > 1)
            <div class="container">
                <div class="luxivo-filter-nav text-center mb-50">
                    <ul class="filter-nav-items">
                        <li class="active" data-filter="*">Toutes</li>
                        @foreach ($categories as $category)
                            <li data-filter=".{{ \App\Models\GalleryPhoto::categoryFilter($category) }}">{{ $category }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        <div class="container">
            <div class="luxivo-isotope">
                <div class="row isotope-grid">
                    @foreach ($photos as $photo)
                        <div class="col-lg-4 col-md-6 col-sm-12 isotope-filter-item {{ $photo->category ? \App\Models\GalleryPhoto::categoryFilter(trim($photo->category)) : '' }}">
                            <figure class="luxivo-gallery-item style-two site-gallery-item mb-30">
                                <div class="thumbnail">
                                    <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?: $hotelInfo->name() }}">
                                    <div class="hover-content">
                                        <a href="{{ $photo->url() }}" class="img-popup icon-btn" title="{{ $photo->caption }}" aria-label="Agrandir"><i class="fas fa-plus"></i></a>
                                    </div>
                                </div>
                                @if ($photo->caption)
                                    <figcaption>{{ $photo->caption }}</figcaption>
                                @endif
                            </figure>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</section>

@include('site.partials.photo-strip')

@endsection
