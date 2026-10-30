@extends('admin.layouts.admin')

@section('title', 'Prise de service')

@section('content')
    @php
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $label = $days[$today->dayOfWeek].' '.$today->format('d/m/Y');
        $mine = $duty && $duty->user_id === auth()->id();
    @endphp

    <div class="duty-take">
        <div class="admin-card duty-take__card">
            <div class="duty-take__icon"><i class="far fa-user-clock" aria-hidden="true"></i></div>
            <h1 class="h3">Prise de service</h1>
            <p class="duty-take__day">Journée du <strong>{{ $label }}</strong><br>
                <span class="text-muted small">de {{ $bounds[0]->format('H\hi') }} à {{ $bounds[1]->copy()->subMinute()->format('H\hi') }} le lendemain</span></p>

            @if ($mine)
                <div class="alert alert-success mb-0"><i class="far fa-check-circle"></i> Vous êtes de service depuis {{ $duty->taken_at->format('H\hi') }} : toutes les ventes de la journée vous sont attribuées.</div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary mt-3">Continuer</a>
            @elseif ($duty)
                <div class="alert alert-info mb-0"><i class="far fa-info-circle"></i> <strong>{{ $duty->user?->name }}</strong> est de service aujourd'hui : les ventes de la journée lui sont attribuées.</div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-primary mt-3">Continuer</a>
            @else
                <p>Avant de commencer, prenez votre service : <strong>toutes les ventes de la journée vous seront attribuées</strong>, y compris celles déjà faites par une collègue avant votre arrivée.</p>
                <form method="POST" action="{{ route('admin.duty.take') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg w-100"><i class="far fa-user-clock"></i> Je prends mon service</button>
                </form>

                <div class="duty-take__defer">
                    <p class="small text-muted mb-2">Vous terminez la journée précédente et attendez la relève ?</p>
                    <form method="POST" action="{{ route('admin.duty.defer') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Je termine la journée précédente</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Vos ventes seront alors attribuées à la réceptionniste qui prendra le service.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
