@extends('admin.layouts.admin')

@section('title', 'Nouvelle réservation')

{{-- Messages, errors and confirmations as SweetAlert pop-ups (admin.js). --}}
@section('sweetalert', '1')

@section('content')
    <div class="admin-page-header">
        <h1><x-admin::kind-icon kind="reservation" />Nouvelle réservation</h1>
    </div>

    @if ($bookingRequest)
        <div class="alert alert-info">
            <i class="far fa-globe"></i> Depuis la <strong>demande en ligne n°{{ $bookingRequest->id }}</strong>
            ({{ $bookingRequest->fullName() }}, {{ $bookingRequest->phone }}) : {{ $bookingRequest->roomType?->name ?? 'type supprimé' }}, {{ $bookingRequest->pricing_mode->getLabel() }}.
            Choisissez la chambre et vérifiez les informations ; la demande passera en « Réservation créée » à l'enregistrement.
        </div>
    @endif

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.reservations.store') }}">
            @include('admin.reservations._form')
        </form>
    </div>
@endsection
