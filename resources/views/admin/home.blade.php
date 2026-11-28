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

    {{-- Receptionists: screen split in two halves — everything else on the left, free rooms on the right (stacked on small screens). --}}
    <div class="{{ $freeRooms !== null ? 'home-layout' : '' }}">
    <div class="home-layout__main">

    {{-- Money blocks refreshed live (admin.js, [data-live-block] → LiveFiguresController) so a
         payment recorded by someone else shows up without reloading. --}}
    @if ($myCash)
        <div data-live-block="my-cash">@include('admin.partials._my-cash')</div>
    @endif

    <div data-live-block="home-figures">@include('admin.partials._home-figures')</div>

    @if ($staffCash !== null)
        <div data-live-block="staff-cash">@include('admin.partials._staff-cash')</div>
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
                <span class="home-tile__label">Sorties</span>
                <span class="home-tile__count">
                    {{ $plural($t['departs']['today'], 'sortie', 'sorties') }} aujourd'hui
                    @if ($t['departs']['overdue'])
                        <span class="badge bg-danger">{{ $plural($t['departs']['overdue'], 'dépassée', 'dépassées') }}</span>
                    @endif
                </span>
            </a>
        @endif

        @if ($t['arrivees'] !== null)
            <a href="{{ route('admin.front-desk.arrivals') }}" class="home-tile home-tile--info">
                <span class="home-tile__icon"><i class="fas fa-sign-in-alt"></i></span>
                <span class="home-tile__label">Arrivées du jour</span>
                <span class="home-tile__count">{{ $t['arrivees'] ? $plural($t['arrivees'], 'réservation', 'réservations') : 'Aucune arrivée prévue' }}</span>
            </a>
        @endif


    </div>

    {{-- « Autres services » (réception only, FrontDeskController::otherServices): today's figures
         and a one-click « Nouvelle vente » instead of going through the « Plus » menu. --}}
    @if ($services)
        @php
            $fcfaService = fn ($v) => number_format((float) $v, 0, ',', ' ');
        @endphp
        <section class="home-services" aria-label="Autres services">
            <h2 class="home-services__title">Autres services</h2>
            <div class="home-services__grid">
                @foreach ($services['sales'] as $service)
                    @php
                        $slug = $service['department']->slug();
                    @endphp
                    <div class="home-service">
                        <a href="{{ route('admin.sales.index', $slug) }}" class="home-service__main">
                            <span class="home-service__icon"><i class="far {{ $service['department']->icon() }}"></i></span>
                            <span class="home-service__text">
                                <span class="home-service__name">{{ $service['department']->getLabel() }}</span>
                                <span class="home-service__figures">
                                    {{ $plural($service['count'], 'vente', 'ventes') }} · <strong>{{ $fcfaService($service['amount']) }} F</strong>
                                    @if ($service['entries'] !== null)
                                        · {{ $plural($service['entries'], 'entrée', 'entrées') }}
                                    @endif
                                </span>
                                <span class="home-service__hint">aujourd'hui</span>
                            </span>
                        </a>
                        <a href="{{ route('admin.sales.create', $slug) }}" class="btn btn-primary home-service__action"><i class="far fa-plus"></i> Nouvelle vente</a>
                    </div>
                @endforeach
                @if ($services['events'])
                    @php
                        $events = $services['events'];
                    @endphp
                    <div class="home-service">
                        <a href="{{ route('admin.events.index') }}" class="home-service__main">
                            <span class="home-service__icon"><i class="far {{ \App\Enums\Department::Evenements->icon() }}"></i></span>
                            <span class="home-service__text">
                                <span class="home-service__name">{{ \App\Enums\Department::Evenements->getLabel() }}</span>
                                <span class="home-service__figures">
                                    <strong>{{ $plural($events['today'], 'événement', 'événements') }}</strong> aujourd'hui
                                </span>
                                <span class="home-service__hint">{{ $events['upcoming'] ? $plural($events['upcoming'], 'réservation', 'réservations').' à venir' : 'Aucune réservation à venir' }}</span>
                            </span>
                        </a>
                        @if ($events['canBook'])
                            <a href="{{ route('admin.events.create') }}" class="btn btn-primary home-service__action"><i class="far fa-plus"></i> Nouvelle réservation</a>
                        @else
                            <a href="{{ route('admin.events.index') }}" class="btn btn-outline-primary home-service__action">Voir les réservations</a>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

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

    </div>
    @if ($freeRooms !== null)
        <aside class="home-layout__side" data-live-block="free-rooms">@include('admin.partials._free-rooms')</aside>
    @endif
    </div>
@endsection
