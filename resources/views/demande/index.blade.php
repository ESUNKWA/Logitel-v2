@extends('demande.layout')

@section('title', 'Chambres libres')

@section('content')
    @if ($currentRequestUrl)
        <a href="{{ $currentRequestUrl }}" class="dm-current">
            <i class="far fa-clipboard-check"></i>
            <span>Vous avez déjà envoyé une demande.<br><strong>Voir où elle en est</strong></span>
            <i class="far fa-chevron-right ms-auto"></i>
        </a>
    @endif

    <h1 class="dm-title">Bienvenue !</h1>
    <p class="dm-lead">Choisissez une chambre libre, indiquez vos informations et envoyez votre demande. La réception la valide et encaisse votre séjour.</p>

    @forelse ($rooms as $room)
        <article class="dm-room">
            <div class="dm-room__photo">
                @if ($room->images->first())
                    <img src="{{ $room->images->first()->url() }}" alt="{{ $room->displayName() }}" loading="lazy">
                @else
                    <i class="far fa-bed"></i>
                @endif
            </div>
            <div class="dm-room__body">
                <div class="dm-room__head">
                    <h2>{{ $room->displayName() }}</h2>
                    <span class="dm-room__number">N°{{ $room->number }}</span>
                </div>
                <div class="dm-room__meta">
                    @if ($room->displayName() !== $room->roomType->name)
                        {{ $room->roomType->name }} ·
                    @endif
                    @if ($room->roomType->capacity_adults)
                        <i class="far fa-user"></i> {{ $room->roomType->capacity_adults }} adulte(s)
                    @endif
                </div>
                <ul class="dm-rates">
                    @foreach ($modes[$room->id] as $mode)
                        <li><span>{{ $mode['label'] }}</span> <strong>{{ number_format($mode['rate'], 0, ',', ' ') }} FCFA</strong> <small>{{ $mode['unit'] }}</small></li>
                    @endforeach
                </ul>
                <a href="{{ route('stay-request.create', $room) }}" class="btn dm-btn w-100">Choisir cette chambre</a>
            </div>
        </article>
    @empty
        <div class="dm-empty">
            <i class="far fa-door-closed"></i>
            <p><strong>Aucune chambre libre pour le moment.</strong><br>Adressez-vous à la réception, nous allons vous aider.</p>
            <a href="{{ route('stay-request.index') }}" class="btn btn-outline-secondary">Actualiser</a>
        </div>
    @endforelse
@endsection
