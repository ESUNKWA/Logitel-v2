@extends('site.layouts.app')

@section('title', 'Demande de réservation - '.$hotelInfo->name())

@section('content')

<x-site::page-hero title="Réserver" subtitle="Votre demande de réservation" breadcrumb="Réserver" />

<section class="site-booking pt-110 pb-110">
    <div class="container">
        <div class="site-booking__done">
            @if ($booking->status === \App\Models\BookingRequest::STATUS_REFUSED)
                <div class="site-booking__done-icon is-refused"><i class="far fa-times"></i></div>
                <h3>Demande non retenue</h3>
                <p>Nous ne pouvons pas donner suite à votre demande.@if ($booking->refusal_reason) {{ $booking->refusal_reason }}@endif</p>
            @elseif ($booking->status === \App\Models\BookingRequest::STATUS_HANDLED)
                <div class="site-booking__done-icon"><i class="far fa-check"></i></div>
                <h3>Réservation enregistrée</h3>
                <p>La réception a enregistré votre réservation. À bientôt !</p>
            @else
                <div class="site-booking__done-icon"><i class="far fa-check"></i></div>
                <h3>Demande envoyée, merci !</h3>
                <p>La réception vérifie la disponibilité et <strong>vous rappelle au {{ $booking->phone }}</strong> pour confirmer votre réservation.</p>
            @endif

            <dl class="site-booking__recap">
                <dt>Chambre</dt><dd>{{ $booking->roomType?->name ?? '—' }}</dd>
                <dt>Formule</dt><dd>{{ $booking->pricing_mode->getLabel() }} — {{ $booking->pricing_mode->durationLabel($booking->duration) }}</dd>
                <dt>Arrivée</dt><dd>{{ $booking->check_in->translatedFormat('l j F Y \à H\hi') }}</dd>
                <dt>Départ prévu</dt><dd>{{ $booking->check_out->translatedFormat('l j F Y \à H\hi') }}</dd>
                <dt>Personnes</dt><dd>{{ $booking->adults }} adulte{{ $booking->adults > 1 ? 's' : '' }}@if ($booking->children), {{ $booking->children }} enfant{{ $booking->children > 1 ? 's' : '' }}@endif</dd>
                <dt>Montant estimé</dt><dd>{{ number_format((float) $booking->total_amount, 0, ',', ' ') }} FCFA</dd>
            </dl>

            @if ($hotelInfo->phones())
                <p class="mb-4">Une question ? Appelez la réception :
                    @foreach ($hotelInfo->phones() as $phone)
                        <a href="{{ $hotelInfo->telHref($phone) }}">{{ $phone }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif
            <a href="{{ route('home') }}" class="theme-btn style-two">Retour à l'accueil</a>
        </div>
    </div>
</section>

@endsection
