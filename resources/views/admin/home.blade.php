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
