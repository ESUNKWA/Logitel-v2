@extends('admin.layouts.admin')

@section('title', 'Réservations')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Réservations</h1>
            <div class="dash-subtitle">Séjours réservés à l'avance. Les clients déjà installés sont dans <a href="{{ route('admin.affectations.index') }}">Affectations</a>.</div>
        </div>
        @can('reservations.manage')
            <a href="{{ route('admin.reservations.create') }}" class="btn btn-primary">
                <i class="far fa-plus"></i> Nouvelle réservation
            </a>
        @endcan
    </div>

    <div class="admin-card">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    @foreach ($filters as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <input type="text" name="room" class="form-control" placeholder="N° de chambre" value="{{ request('room') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
                @if (request('status') || request('room'))
                    <a href="{{ route('admin.reservations.index') }}" class="btn btn-link">Réinitialiser</a>
                @endif
            </div>
        </form>

        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Chambre</th>
                    <th>Formule</th>
                    <th>Arrivée</th>
                    <th>Départ</th>
                    <th>Statut</th>
                    <th>Montant</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>{{ $reservation->guest?->fullName() }}</td>
                        <td>N°{{ $reservation->room?->number }}</td>
                        <td>{{ $reservation->pricing_mode->getLabel() }}<div class="small text-muted">{{ $reservation->pricing_mode->durationLabel($reservation->duration) }}</div></td>
                        <td>
                            {{ $reservation->check_in->format('d/m/Y H\hi') }}
                            @if (in_array($reservation->status->value, ['en_attente', 'confirmee']) && $reservation->check_in->isPast())
                                <div><span class="badge bg-danger">Client non arrivé</span></div>
                            @endif
                        </td>
                        <td>{{ $reservation->check_out->format('d/m/Y H\hi') }}</td>
                        <td>
                            <span class="badge bg-{{ $reservation->status->reservationColor() }}">{{ $reservation->status->reservationLabel() }}</span>
                            @if ($reservation->isPendingBooking())
                                @if ($reservation->is_guaranteed)
                                    <div><span class="badge bg-success">Soldée · chambre garantie</span></div>
                                @elseif ($reservation->takenBy())
                                    <div><span class="badge bg-danger">Non garantie · chambre prise</span></div>
                                @else
                                    <div><span class="badge bg-warning">Non soldée · non garantie</span></div>
                                @endif
                            @endif
                            <div class="small text-muted">par {{ $reservation->creator?->name ?? '—' }}</div>
                        </td>
                        <td>
                            {{ number_format((float) $reservation->total_amount, 0, ',', ' ') }} FCFA
                            @if ($reservation->hasDiscount())
                                <div class="small text-success" title="{{ $reservation->discountLabel() }}">remise − {{ number_format((float) $reservation->discount_amount, 0, ',', ' ') }}</div>
                            @endif
                            @if ($reservation->invoice && (float) $reservation->invoice->amountPaid() > 0)
                                <div class="small text-muted">acompte {{ number_format((float) $reservation->invoice->amountPaid(), 0, ',', ' ') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($reservation->hasStarted())
                                @can('affectations.view')
                                    <a href="{{ route('admin.affectations.show', $reservation) }}" class="btn btn-sm btn-outline-secondary">Voir l'affectation</a>
                                @endcan
                            @else
                                @can('reservations.manage')
                                    <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Aucune réservation pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $reservations->links() }}
@endsection
