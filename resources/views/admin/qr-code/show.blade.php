@extends('admin.layouts.admin')

@section('title', 'QR code client')

@push('styles')
    <style>
        /* Only the poster is printed, on one A4 page. */
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { background: #fff !important; }
            .admin-sidebar, .admin-topbar, .simple-tabbar, .qr-toolbar, .alert, .checkout-alerts, .request-alerts { display: none !important; }
            .admin-content { padding: 0 !important; max-width: none !important; }
            .qr-poster { box-shadow: none !important; border: 0 !important; margin: 0 auto !important; }
        }
    </style>
@endpush

@section('content')
    <div class="qr-toolbar">
        <div class="admin-page-header">
            <div>
                <h1>QR code pour les clients</h1>
                <div class="dash-subtitle">À imprimer et afficher à l'accueil : le client le scanne avec son téléphone et arrive sur la page pour choisir sa chambre et envoyer sa demande.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary phone-hidden" onclick="window.print()"><i class="fas fa-print"></i> Imprimer l'affiche</button>
                <a href="{{ route('admin.stay-requests.qr.download') }}" class="btn btn-outline-primary phone-hidden"><i class="fas fa-download"></i> Télécharger le QR code (SVG)</a>
            </div>
        </div>

        @if ($localOnly)
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Ce QR code pointe vers <strong>{{ $url }}</strong> : cette adresse n'existe que sur cet ordinateur, <strong>les téléphones des clients ne pourront pas l'ouvrir</strong>.
                Ouvrez cette page depuis l'adresse réelle de l'application (adresse IP du serveur ou nom de domaine) avant d'imprimer.
            </div>
        @else
            <div class="alert alert-secondary py-2">
                <i class="fas fa-link me-1"></i> Adresse encodée : <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a>.
                Si l'adresse de l'application change (nom de domaine, HTTPS), réimprimez l'affiche.
            </div>
        @endif
    </div>

    <div class="qr-poster">
        <div class="qr-poster__brand">{{ $hotelInfo->name() }}</div>
        @if ($hotelInfo->get('tagline'))
            <div class="qr-poster__tagline">{{ $hotelInfo->get('tagline') }}</div>
        @endif

        <h2 class="qr-poster__title">Choisissez votre chambre</h2>
        <p class="qr-poster__lead">Scannez ce code avec l'appareil photo de votre téléphone</p>

        <div class="qr-poster__code" role="img" aria-label="QR code vers {{ $url }}">
            {!! preg_replace('/^<\?xml[^>]*>\s*/', '', $svg) !!}
        </div>

        <ol class="qr-poster__steps">
            <li><span>1</span> Scannez le code</li>
            <li><span>2</span> Choisissez une chambre libre et votre formule</li>
            <li><span>3</span> Présentez-vous à la réception pour valider et régler</li>
        </ol>

        <div class="qr-poster__url">{{ preg_replace('#^https?://#', '', $url) }}</div>
        <div class="qr-poster__foot">
            Paiement à la réception : espèces, Orange Money, Wave
            @if ($hotelInfo->get('phone'))
                · Réception : {{ $hotelInfo->get('phone') }}
            @endif
        </div>
    </div>
@endsection
