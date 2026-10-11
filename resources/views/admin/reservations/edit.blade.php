@extends('admin.layouts.admin')

@section('title', 'Modifier la réservation')

{{-- Messages, errors and confirmations as SweetAlert pop-ups (admin.js). --}}
@section('sweetalert', '1')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="reservation" />{{ $reservation->guest->fullName() }}</h1>
            <div class="dash-subtitle">
                <x-admin::file-badge :file="$reservation" /> ·
                <span class="badge bg-{{ $reservation->status->reservationColor() }}">{{ $reservation->status->reservationLabel() }}</span>
                · arrivée prévue le {{ $reservation->check_in->format('d/m/Y à H\hi') }}
                · créée par {{ $reservation->creator?->name ?? '—' }} le {{ $reservation->created_at?->format('d/m/Y à H\hi') }}
                @if ($reservation->check_in->isPast())
                    <span class="badge bg-danger">Client non arrivé</span> — annulez ou reportez la réservation.
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @can('invoices.view')
                <a href="{{ route('admin.receipts.show', $reservation) }}" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>
            @endcan
            @if (in_array($reservation->status->value, ['en_attente', 'confirmee']))
                <form method="POST" action="{{ route('admin.reservations.check-in', $reservation) }}" data-confirm="La réservation deviendra une affectation et la chambre passera en « occupée »." data-confirm-title="Le client est arrivé ?" data-confirm-button="Oui, enregistrer l'arrivée">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fas fa-sign-in-alt"></i> Arrivée du client</button>
                </form>
                @can('reservations.cancel')
                    <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm="La réservation sera annulée. Les acomptes déjà encaissés restent sur la facture." data-confirm-title="Annuler cette réservation ?" data-confirm-button="Oui, annuler la réservation" data-confirm-cancel="Non, la garder" data-confirm-danger>
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Annuler la réservation</button>
                    </form>
                @endcan
            @endif
            @can('invoices.view')
                @if ($reservation->invoice)
                    <a href="{{ route('admin.invoices.show', $reservation->invoice) }}" class="btn btn-outline-secondary">
                        Facture {{ $reservation->invoice->number }} · acompte {{ number_format((float) $reservation->invoice->amountPaid(), 0, ',', ' ') }} FCFA
                    </a>
                @endif
            @endcan
        </div>
    </div>

    @if ($reservation->isPendingBooking())
        @php
            $holder = $reservation->takenBy();
        @endphp
        @if ($reservation->is_guaranteed)
            <div class="alert alert-success"><i class="fas fa-lock me-1"></i> <strong>Chambre garantie</strong> : la réservation est soldée, la chambre est bloquée pour ce client.</div>
        @elseif ($holder)
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-1"></i> <strong>Non garantie — chambre prise.</strong>
                {{ \App\Models\Room::conflictMessage($holder, $reservation->check_in, $reservation->check_out) }}
                Reportez la réservation, changez de chambre ou annulez-la. L'arrivée du client sera refusée tant que le conflit existe.
            </div>
        @else
            <div class="alert alert-warning">
                <i class="fas fa-unlock me-1"></i> <strong>Non garantie</strong> : la chambre n'est bloquée qu'une fois la réservation <strong>soldée</strong>.
                Tant qu'elle ne l'est pas, une autre réservation soldée ou une affectation peut prendre ce créneau.
            </div>
        @endif
    @endif

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.reservations.update', $reservation) }}">
            @method('PUT')
            @include('admin.reservations._form')
        </form>
    </div>
@endsection
