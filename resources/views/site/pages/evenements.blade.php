@extends('site.layouts.app')

@section('title', 'Événements - '.$hotelInfo->name())
@section('meta_description', 'Les prochains événements à '.$hotelInfo->name().'.')

@section('content')

<x-site::page-hero title="Événements" :subtitle="'À venir à '.$hotelInfo->name()" breadcrumb="Événements" />

{{-- Events: « Site web → Événements » in the application; past ones leave the page by themselves. --}}
<section class="site-events pt-110 pb-90">
    <div class="container">
        @if ($events->isEmpty())
            <div class="site-empty text-center">
                <i class="fal fa-calendar-star"></i>
                <h4>Aucun événement à venir pour le moment</h4>
                <p>Revenez bientôt : nos prochains rendez-vous seront annoncés ici.</p>
                <a href="{{ route('home') }}" class="theme-btn style-one mt-3">Retour à l'accueil <i class="far fa-arrow-right"></i></a>
            </div>
        @else
            <div class="row justify-content-center">
                @foreach ($events as $event)
                    <div class="col-lg-6 mb-30">
                        <x-site::event-card :event="$event" :full="true" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@endsection
