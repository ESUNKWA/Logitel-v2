@extends('admin.layouts.admin')

@section('title', 'Accueil')

@section('content')
    @php
        $t = $tiles;
        $plural = fn (int $n, string $one, string $many) => $n.' '.($n > 1 ? $many : $one);
    @endphp

    <div class="home-hello">
        <h1>Bonjour {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h1>
        <div class="text-muted">{{ ucfirst(now()->translatedFormat('l j F Y')) }} · {{ now()->format('H\hi') }}</div>
    </div>

    <div class="home-tiles">
        @if ($t['affecter'] !== null)
            <a href="{{ route('admin.rooms.board') }}#disponible" class="home-tile home-tile--success">
                <span class="home-tile__icon"><i class="fas fa-door-open"></i></span>
                <span class="home-tile__label">Affecter une chambre</span>
                <span class="home-tile__count">{{ $plural($t['affecter'], 'chambre libre', 'chambres libres') }}</span>
            </a>
        @endif

        @if ($t['departs'] !== null)
            <a href="{{ route('admin.front-desk.departures') }}" class="home-tile {{ $t['departs']['overdue'] ? 'home-tile--danger' : 'home-tile--warning' }}">
                <span class="home-tile__icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="home-tile__label">Départs</span>
                <span class="home-tile__count">
                    {{ $plural($t['departs']['today'], 'départ', 'départs') }} aujourd'hui
                    @if ($t['departs']['overdue'])
                        <span class="badge bg-danger">{{ $plural($t['departs']['overdue'], 'dépassé', 'dépassés') }}</span>
                    @endif
                </span>
            </a>
        @endif

        @if ($t['demandes'] !== null)
            <a href="{{ route('admin.stay-requests.index') }}" class="home-tile home-tile--primary {{ $t['demandes'] ? 'is-attention' : '' }}">
                <span class="home-tile__icon"><i class="fas fa-mobile-alt"></i></span>
                <span class="home-tile__label">Demandes clients</span>
                <span class="home-tile__count">{{ $t['demandes'] ? $plural($t['demandes'], 'en attente', 'en attente') : 'Aucune en attente' }}</span>
            </a>
        @endif

        @if ($t['arrivees'] !== null)
            <a href="{{ route('admin.front-desk.arrivals') }}" class="home-tile home-tile--info">
                <span class="home-tile__icon"><i class="fas fa-sign-in-alt"></i></span>
                <span class="home-tile__label">Arrivées du jour</span>
                <span class="home-tile__count">{{ $t['arrivees'] ? $plural($t['arrivees'], 'réservation', 'réservations') : 'Aucune arrivée prévue' }}</span>
            </a>
        @endif

        @if ($t['encaisser'] !== null)
            <a href="{{ route('admin.front-desk.collect') }}" class="home-tile home-tile--gold">
                <span class="home-tile__icon"><i class="fas fa-coins"></i></span>
                <span class="home-tile__label">Encaisser</span>
                <span class="home-tile__count">{{ $t['encaisser'] ? $plural($t['encaisser'], 'solde à régler', 'soldes à régler') : 'Tout est réglé' }}</span>
            </a>
        @endif

        @if ($t['chercher'])
            <form method="GET" action="{{ route('admin.front-desk.search') }}" class="home-tile home-tile--search" role="search">
                <span class="home-tile__icon"><i class="fas fa-search"></i></span>
                <label class="home-tile__label" for="home-search">Chercher</label>
                <div class="input-group">
                    <input type="search" class="form-control" id="home-search" name="q" placeholder="Client, téléphone, n° de chambre…" autocomplete="off">
                    <button class="btn btn-primary" type="submit" aria-label="Chercher"><i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
        @endif
    </div>

    @if ($roomCounts)
        <div class="home-rooms">
            <span class="home-rooms__title">Chambres</span>
            @foreach (\App\Models\Room::DISPLAY_STATES as $state => [$label, $color])
                @if ($roomCounts[$state] ?? 0)
                    <a href="{{ route('admin.rooms.board') }}#{{ $state }}" class="home-rooms__item">
                        <span class="room-sections-nav__dot room-sections-nav__dot--{{ $color }}"></span>
                        <strong>{{ $roomCounts[$state] }}</strong> {{ mb_strtolower($label) }}
                    </a>
                @endif
            @endforeach
        </div>
    @endif
@endsection
