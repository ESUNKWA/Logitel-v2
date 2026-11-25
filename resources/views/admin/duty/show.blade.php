@extends('admin.layouts.admin')

@section('title', 'Prise de service')

@section('content')
    @php
        $mine = $current && $current->user_id === auth()->id();
    @endphp

    <div class="duty-take">
        <div class="admin-card duty-take__card">
            <div class="duty-take__icon"><i class="far fa-user-clock" aria-hidden="true"></i></div>
            <h1 class="h3">{{ $current && ! $mine ? 'Prendre la relève ?' : 'Prise de service' }}</h1>

            @if ($mine)
                <div class="alert alert-success"><i class="far fa-check-circle"></i> Vous êtes de service depuis le {{ $current->started_at->format('d/m à H\hi') }} : vos encaissements vous sont attribués.</div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Continuer</a>
                <div class="duty-take__defer">
                    <p class="small text-muted mb-2">Vous partez ? Terminez votre service : la prochaine réceptionniste le prendra à sa connexion.</p>
                    <form method="POST" action="{{ route('admin.duty.end') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="far fa-sign-out-alt"></i> Terminer mon service</button>
                    </form>
                </div>
            @elseif ($current)
                {{-- Relief with confirmation (client request, 2026-11-25). --}}
                <p class="duty-take__day"><strong>{{ $current->user?->name }}</strong> est de service depuis le <strong>{{ $current->started_at->format('d/m à H\hi') }}</strong>.</p>
                <p>Si vous la remplacez, son service se termine maintenant et <strong>vos encaissements vous sont attribués à partir de maintenant</strong>. Les siens restent les siens.</p>
                <form method="POST" action="{{ route('admin.duty.take') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="far fa-user-clock"></i> Oui, je prends la relève</button>
                </form>

                <div class="duty-take__defer">
                    <form method="POST" action="{{ route('admin.duty.decline') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Non, je viens en renfort</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">{{ $current->user?->name }} reste alors de service : vos encaissements lui sont attribués.</p>
                </div>
            @else
                <p>Personne n'est de service. Prenez votre service : <strong>vos encaissements vous seront attribués</strong>, ainsi que ceux faits depuis le début de la journée quand personne n'était de service.</p>
                <form method="POST" action="{{ route('admin.duty.take') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="far fa-user-clock"></i> Je prends mon service</button>
                </form>
            @endif
        </div>
    </div>
@endsection
