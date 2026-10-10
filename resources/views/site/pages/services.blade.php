@extends('site.layouts.app')

@section('title', 'Nos services - '.$hotelInfo->name())

@section('content')

<x-site::page-hero title="Nos services" subtitle="Découvrez l'expérience {{ $hotelInfo->name() }}" breadcrumb="Services" />

{{-- Contents: « Site web → Services » in the application. With a photo: large cards; without: icon tiles. --}}
@if ($withPhoto->isEmpty() && $iconOnly->isEmpty())
<section class="pt-115 pb-90">
    <div class="container">
        <div class="site-empty text-center">
            <i class="fal fa-concierge-bell"></i>
            <h4>Nos services seront bientôt présentés ici</h4>
            <p>Contactez la réception pour toute information.</p>
            <a href="{{ route('contact') }}" class="theme-btn style-one mt-3">Nous contacter <i class="far fa-arrow-right"></i></a>
        </div>
    </div>
</section>
@endif

@if ($iconOnly->isNotEmpty())
<section class="luxivo-features_three pt-115 {{ $withPhoto->isEmpty() ? 'pb-80' : '' }}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-6 col-lg-8">
                <div class="section-title text-center mb-50">
                    <span class="line d-block mb-10"><img src="{{ asset('site/images/home-one/gallery/d-line.png') }}" alt=""></span>
                    <span class="sub-title">Équipements de l'hôtel</span>
                    <h2>Confort et commodités à votre service</h2>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            @foreach ($iconOnly as $service)
                <div class="col-xl-4 col-md-6 mb-30">
                    <x-site::service-card :service="$service" />
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($withPhoto->isNotEmpty())
<section class="luxivo-service-sec pt-80 pb-60">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="service-card-wrapper">
                    @foreach ($withPhoto as $service)
                    <div class="luxivo-service-card style-one mb-60">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <div class="service-content">
                                    <div class="sn-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                                    @if ($service->category)
                                        <span class="sub-title">{{ $service->category }}</span>
                                    @endif
                                    <h3 class="title">{{ $service->title }}</h3>
                                    <span class="line"></span>
                                    @if ($service->description)
                                        <p>{!! nl2br(e($service->description)) !!}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div class="service-thumbnail"><img src="{{ $service->photoUrl() }}" alt="{{ $service->title }}"></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif

@endsection
