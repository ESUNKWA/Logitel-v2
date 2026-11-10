@extends('admin.layouts.admin')

@section('title', 'Réservations')

{{-- Messages, errors and confirmations as SweetAlert pop-ups (admin.js). --}}
@section('sweetalert', '1')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="reservation" />Réservations</h1>
            <div class="dash-subtitle">Séjours réservés à l'avance. Les clients déjà installés sont dans <a href="{{ route('admin.affectations.index') }}">Affectations</a>.</div>
        </div>
        @can('reservations.manage')
            <a href="{{ route('admin.reservations.create') }}" class="btn btn-primary">
                <i class="far fa-plus"></i> Nouvelle réservation
            </a>
        @endcan
    </div>

    @php($pendingBookings = \App\Models\BookingRequest::query()->pending()->count())
    @if ($pendingBookings)
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="far fa-globe"></i> <strong>{{ $pendingBookings }} demande{{ $pendingBookings > 1 ? 's' : '' }} de réservation en ligne</strong> en attente de rappel.</span>
            <a href="{{ route('admin.booking-requests.index') }}" class="btn btn-sm btn-warning">Voir les demandes</a>
        </div>
    @endif

    {{-- Tabs (client request, 2026-11-10): bookings still to assign (default, nearest arrival
         first) apart from those already assigned (the guest arrived) and the cancelled ones. --}}
    <ul class="nav nav-pills mb-3 reservation-tabs">
        @foreach ($tabs as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.reservations.index', array_filter(['onglet' => $key, 'room' => $room])) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
                    {{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="admin-card">
        <form method="GET" class="row g-2 mb-3">
            <input type="hidden" name="onglet" value="{{ $tab }}">
            @if ($tab === 'a-affecter')
                <div class="col-auto">
                    <select name="status" class="form-select" onchange="this.form.submit()" aria-label="Statut">
                        <option value="">Tous les statuts</option>
                        @foreach ($filters as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-auto">
                <input type="text" name="room" class="form-control" placeholder="N° de chambre" value="{{ $room }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary">Filtrer</button>
                @if ($status !== '' || $room !== '')
                    <a href="{{ route('admin.reservations.index', ['onglet' => $tab]) }}" class="btn btn-link">Réinitialiser</a>
                @endif
            </div>
        </form>

        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Client</th>
                    {{-- The client's number, tap to call (client request, 2026-11-10: phones, then PC too). --}}
                    <th>Téléphone</th>
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
                        <td>
                            {{ $reservation->guest?->fullName() }}
                            <div>
                                <span class="file-ref">{{ $reservation->bookingReference() }}</span>
                                @if ($reservation->hasStarted())
                                    <span class="small text-muted">→</span>
                                    <a href="{{ route('admin.affectations.show', $reservation) }}" class="file-ref">{{ $reservation->reference() }}</a>
                                @endif
                            </div>
                        </td>
                        {{-- data-search on every cell (DataTables reads it from the first row): the digits too. --}}
                        <td class="text-nowrap" data-search="{{ \App\Support\PhoneFormat::searchText($reservation->guest?->phone) }}">@if ($reservation->guest?->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $reservation->guest->phone) }}"><i class="far fa-phone-alt"></i> {{ \App\Support\PhoneFormat::display($reservation->guest->phone) }}</a>@else — @endif</td>
                        <td>N°{{ $reservation->room?->number }}</td>
                        <td><x-admin::mode-badge :mode="$reservation->pricing_mode" /><div class="small text-muted">{{ $reservation->pricing_mode->durationLabel($reservation->duration) }}</div></td>
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
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        {{-- Guest due by the end of the business day, room free, period not over:
                                             assign straight from the list (same action as « Arrivée du client »). --}}
                                        @if ($reservation->isPendingBooking() && $reservation->check_in->lessThan($todayEnd)
                                            && $reservation->check_out->isFuture() && $reservation->room?->status === \App\Enums\RoomStatus::Disponible)
                                            <form method="POST" action="{{ route('admin.reservations.check-in', $reservation) }}" data-confirm="La réservation deviendra une affectation et la chambre N°{{ $reservation->room->number }} passera en « occupée »." data-confirm-title="Le client est arrivé ?" data-confirm-button="Oui, affecter la chambre">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-sign-in-alt"></i> Affecter</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.reservations.edit', $reservation) }}" class="btn btn-sm btn-outline-primary">Ouvrir</a>
                                        {{-- Cancel straight from the list (client request, 2026-11-10), same action as the booking's page. --}}
                                        @if ($reservation->isPendingBooking())
                                            @can('reservations.cancel')
                                                <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm="La réservation {{ $reservation->bookingReference() }} (chambre N°{{ $reservation->room?->number }}) sera annulée. Les acomptes déjà encaissés restent sur la facture." data-confirm-title="Annuler cette réservation ?" data-confirm-button="Oui, annuler la réservation" data-confirm-cancel="Non, la garder" data-confirm-danger>
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="far fa-times"></i> Annuler</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">{{ match ($tab) {
                            'a-affecter' => 'Aucune réservation à affecter.',
                            'affectees' => 'Aucune réservation affectée pour l\'instant.',
                            default => 'Aucune réservation annulée.',
                        } }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $reservations->links() }}
@endsection
