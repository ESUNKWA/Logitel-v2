@extends('site.demande.layout')

@php
    $pending = $stayRequest->isPending() && ! $stayRequest->isExpired();
@endphp

@section('title', 'Ma demande')
@section('subtitle', 'Suivi de votre demande')

@if ($pending)
    {{-- Refreshes itself until the réception has handled the request. --}}
    @push('head')
        <meta http-equiv="refresh" content="20">
    @endpush
@endif

@section('content')
    @if ($pending)
        <div class="dm-status dm-status--pending">
            <div class="dm-status__icon"><i class="far fa-concierge-bell"></i></div>
            <h1>Demande envoyée</h1>
            <p>Présentez-vous à la <strong>réception</strong> avec ce numéro :</p>
            <div class="dm-ref">N° {{ $stayRequest->id }}</div>
            <p class="small text-muted mb-0">La chambre vous est réservée jusqu'à <strong>{{ $stayRequest->expires_at->format('H\hi') }}</strong>. Cette page se met à jour toute seule.</p>
        </div>
    @elseif ($stayRequest->status === \App\Enums\StayRequestStatus::Validee)
        <div class="dm-status dm-status--ok">
            <div class="dm-status__icon"><i class="far fa-check"></i></div>
            <h1>Votre chambre est prête</h1>
            <p>Chambre <strong>N°{{ $stayRequest->room?->number }}</strong>.
                @if ($stayRequest->reservation)
                    Départ prévu le <strong>{{ $stayRequest->reservation->check_out->format('d/m/Y à H\hi') }}</strong>.
                @endif
            </p>
            <p class="mb-0">Bon séjour parmi nous, à {{ $hotelInfo->name() }} !</p>
        </div>
    @elseif ($stayRequest->status === \App\Enums\StayRequestStatus::Refusee)
        <div class="dm-status dm-status--ko">
            <div class="dm-status__icon"><i class="far fa-times"></i></div>
            <h1>Demande non retenue</h1>
            @if ($stayRequest->refusal_reason)
                <p>{{ $stayRequest->refusal_reason }}</p>
            @endif
            <p class="mb-0">Adressez-vous à la réception, nous allons vous aider.</p>
        </div>
    @else
        <div class="dm-status dm-status--ko">
            <div class="dm-status__icon"><i class="far fa-hourglass-end"></i></div>
            <h1>Délai dépassé</h1>
            <p class="mb-0">La chambre n'est plus réservée pour vous. Adressez-vous à la réception ou faites une nouvelle demande.</p>
        </div>
    @endif

    <section class="dm-card">
        <h2 class="dm-card__plain">Récapitulatif</h2>
        <dl class="dm-recap">
            <dt>Nom</dt><dd>{{ $stayRequest->fullName() }}</dd>
            <dt>Téléphone</dt><dd>{{ $stayRequest->phone ?: '—' }}</dd>
            <dt>Chambre</dt><dd>N°{{ $stayRequest->room?->number }} — {{ $stayRequest->room?->displayName() }}</dd>
            <dt>Formule</dt><dd>{{ $stayRequest->pricing_mode->getLabel() }} : {{ $stayRequest->billingLabel() }}</dd>
            <dt>Total</dt><dd><strong>{{ number_format((float) $stayRequest->total_amount, 0, ',', ' ') }} FCFA</strong></dd>
        </dl>
        <p class="small text-muted mb-0">Le montant final est confirmé par la réception au moment de la validation.</p>
    </section>

    @unless ($pending || $stayRequest->status === \App\Enums\StayRequestStatus::Validee)
        <a href="{{ route('stay-request.index') }}" class="btn dm-btn w-100">Voir les chambres libres</a>
    @endunless
@endsection
