@extends('admin.layouts.admin')

@section('title', 'Réunions et événements')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-briefcase text-primary me-2"></i>Réunions et événements</h1>
            <div class="dash-subtitle">Réservations de salles : devis (option), confirmation, acompte et solde.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($hasVenues)
                <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvelle réservation</a>
            @endif
            <a href="{{ route('admin.events.planning') }}" class="btn btn-outline-primary"><i class="far fa-calendar-week"></i> Planning des salles</a>
            @can('catalog.manage')
                <a href="{{ route('admin.venues.index') }}" class="btn btn-outline-secondary"><i class="far fa-door-open"></i> Salles</a>
                <a href="{{ route('admin.catalog.index', ['service' => 'evenements']) }}" class="btn btn-outline-secondary"><i class="far fa-list"></i> Prestations</a>
            @endcan
        </div>
    </div>

    @unless ($hasVenues)
        <div class="alert alert-info">
            Aucune salle n'est encore proposée.
            @can('catalog.manage')
                <a href="{{ route('admin.venues.index') }}">Ajoutez vos salles</a> (capacité, tarifs demi-journée et journée).
            @else
                Demandez au gérant de les ajouter.
            @endcan
        </div>
    @endunless

    <ul class="nav nav-tabs mb-3">
        @foreach ($tabs as $key => $label)
            <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif href="{{ route('admin.events.index', ['onglet' => $key]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Salle</th>
                        <th>Client</th>
                        <th class="text-end">Participants</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Reste dû</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        @php($invoice = $booking->sale?->invoice)
                        @php($remaining = max(0, (float) $invoice?->amount - (float) $invoice?->payments_sum_amount))
                        <tr>
                            <td class="text-nowrap">{{ $booking->start_at->format('d/m/Y') }}<div class="small text-muted">{{ $booking->start_at->format('H\hi') }} – {{ $booking->end_at->format('H\hi') }}</div></td>
                            <td>{{ $booking->venue?->name }}<div class="small text-muted">{{ $booking->formulaLabel() }}</div></td>
                            <td>{{ $booking->client_name }}@if ($booking->organization)<div class="small text-muted">{{ $booking->organization }}</div>@endif @if ($booking->title)<div class="small">{{ $booking->title }}</div>@endif</td>
                            <td class="text-end">{{ $booking->participants }}</td>
                            <td class="text-end text-nowrap">{{ $fcfa($booking->sale?->total_amount) }} F</td>
                            <td class="text-end text-nowrap">
                                @if ($booking->isCancelled())
                                    —
                                @elseif ($remaining > 0)
                                    <span class="text-danger">{{ $fcfa($remaining) }} F</span>
                                @else
                                    <span class="badge bg-success">Soldée</span>
                                @endif
                            </td>
                            <td><span class="badge bg-{{ $booking->isCancelled() ? 'secondary' : ($booking->isConfirmed() ? 'success' : 'warning text-dark') }}">{{ $booking->statusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ route('admin.events.show', $booking) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">Aucune réservation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $bookings->links() }}
@endsection
