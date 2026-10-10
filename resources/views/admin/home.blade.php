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

    @if ($finance || $roomCounts)
        @php
            $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
            $occupied = $roomCounts['occupee'] ?? 0;
            $sellable = $roomCounts ? array_sum($roomCounts) - ($roomCounts['hors_service'] ?? 0) : 0;
            $occupancy = $sellable > 0 ? (int) round($occupied / $sellable * 100) : 0;
        @endphp
        <section class="home-stats" aria-label="Chiffres du jour">
            @if ($finance)
                <div class="home-stat home-stat--primary">
                    <span class="home-stat__label"><i class="fas fa-coins"></i> Recette du jour</span>
                    <span class="home-stat__value">{{ $fcfa($finance['today']) }} <small>FCFA</small></span>
                    <span class="home-stat__hint">
                        {{ $finance['todayCount'] }} encaissement(s)
                        @if ($finance['discountsToday'] > 0)
                            · {{ $fcfa($finance['discountsToday']) }} F de remises
                        @endif
                    </span>
                </div>
            @endif
            @if ($roomCounts)
                <div class="home-stat">
                    <span class="home-stat__label"><i class="fas fa-bed"></i> Chambres occupées</span>
                    <span class="home-stat__value">{{ $occupied }} <small>/ {{ $sellable }}</small></span>
                    <span class="home-stat__hint">Taux d'occupation {{ $occupancy }} % · {{ $roomCounts['disponible'] ?? 0 }} libre(s)</span>
                </div>
            @endif
            @if ($finance)
                <div class="home-stat">
                    <span class="home-stat__label"><i class="fas fa-calendar-check"></i> Recette du mois</span>
                    <span class="home-stat__value">{{ $fcfa($finance['month']) }} <small>FCFA</small></span>
                    <span class="home-stat__hint">Encaissé depuis le début du mois</span>
                </div>
                <a href="{{ route('admin.front-desk.collect') }}" class="home-stat {{ $finance['outstanding'] > 0 ? 'home-stat--danger' : '' }}">
                    <span class="home-stat__label"><i class="fas fa-hand-holding-usd"></i> Reste à encaisser</span>
                    <span class="home-stat__value">{{ $fcfa($finance['outstanding']) }} <small>FCFA</small></span>
                    <span class="home-stat__hint">{{ $finance['outstandingCount'] }} facture(s) non soldée(s)</span>
                </a>

                <div class="home-methods">
                    <div class="home-methods__title">Recette du jour par mode de paiement</div>
                    @foreach ($finance['byMethod'] as $row)
                        <div class="home-methods__row">
                            <span class="home-methods__name"><i class="fas {{ $row['method']->icon() }}"></i> {{ $row['method']->getLabel() }}</span>
                            <span class="home-methods__bar" role="img" aria-label="{{ $row['share'] }} %"><span style="width: {{ $row['share'] }}%"></span></span>
                            <span class="home-methods__amount">{{ $fcfa($row['amount']) }} F <small>{{ $row['share'] }} %</small></span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

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
