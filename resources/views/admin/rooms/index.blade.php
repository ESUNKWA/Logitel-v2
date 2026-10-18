@extends('admin.layouts.admin')

@section('title', 'Chambres')

@section('content')
    <div class="admin-page-header">
        <h1>Chambres</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.rooms.board') }}" class="btn btn-outline-primary">
                <i class="far fa-th-large"></i> Vue grille
            </a>
            @can('rooms.manage')
                <a href="{{ route('admin.rooms.import') }}" class="btn btn-outline-primary phone-hidden">
                    <i class="far fa-file-import"></i> Importer (CSV)
                </a>
                <a href="{{ route('admin.rooms.create') }}" class="btn btn-primary">
                    <i class="far fa-plus"></i> Nouvelle chambre
                </a>
            @endcan
        </div>
    </div>

    @php
        // Filter pills, in the same order and wording as the board sections (Room::DISPLAY_STATES).
        $filters = [];
        foreach (\App\Models\Room::DISPLAY_STATES as $state => [$label, $color]) {
            $filters[$state] = [$label, $color, match ($state) {
                'reservee' => $reservedCount,
                'demande' => $requestedCount,
                default => $statusCounts[$state] ?? 0,
            }];
        }
    @endphp

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a href="{{ route('admin.rooms.index') }}" class="nav-link {{ $filter === null ? 'active' : '' }}">
                Toutes <span class="badge bg-light text-dark">{{ $statusCounts->sum() + $reservedCount + $requestedCount }}</span>
            </a>
        </li>
        @foreach ($filters as $value => [$label, $color, $count])
            <li class="nav-item">
                <a href="{{ route('admin.rooms.index', ['status' => $value]) }}" class="nav-link {{ $filter === $value ? 'active' : '' }}">
                    {{ $label }} <span class="badge bg-{{ $color }}">{{ $count }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Type</th>
                    <th>Étage</th>
                    <th>Statut</th>
                    @canany(['reservations.view', 'affectations.view'])
                        {{-- The guest's name is only shown for reserved rooms (to recognise the arriving
                             guest), never for occupied ones: no Client column on the "Occupées" tab. --}}
                        @if ($filter !== 'occupee')
                            <th>Client</th>
                        @endif
                        <th>Séjour</th>
                    @endcanany
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    @if ($loop->first || $rooms[$loop->index - 1]->displayState() !== $room->displayState())
                        <tr class="table-group-row">
                            <th colspan="7">
                                <span class="room-sections-nav__dot room-sections-nav__dot--{{ \App\Models\Room::DISPLAY_STATES[$room->displayState()][1] }}"></span>
                                {{ \App\Models\Room::DISPLAY_STATES[$room->displayState()][0] }}
                            </th>
                        </tr>
                    @endif
                    <tr>
                        <td>{{ $room->number }}@if ($room->isPubliclyVisible()) <a href="{{ $room->publicUrl() }}" target="_blank" rel="noopener" class="badge bg-success text-decoration-none ms-1" title="Visible sur le site — ouvrir la page">Publiée <i class="fas fa-external-link-alt"></i></a>@endif</td>
                        <td>{{ $room->roomType?->name }}</td>
                        <td>{{ $room->floor ?: '—' }}</td>
                        @php($stay = $room->currentStay ?? $room->pendingArrival)
                        <td>
                            @if ($room->isReserved())
                                <span class="badge bg-info">Réservée</span>
                            @elseif ($room->isRequested())
                                <span class="badge bg-primary">Demande client</span>
                                @can('affectations.view')
                                    <a href="{{ route('admin.stay-requests.show', $room->pendingRequest) }}" class="small ms-1">Traiter</a>
                                @endcan
                            @else
                                <span class="badge bg-{{ $room->status->getColor() }}">{{ $room->status->getLabel() }}</span>
                            @endif
                        </td>
                        @canany(['reservations.view', 'affectations.view'])
                            @if ($stay)
                                @if ($filter !== 'occupee')
                                    <td>{{ $room->isReserved() ? $stay->guest?->fullName() : '—' }}</td>
                                @endif
                                <td>
                                    @if ($stay->status === \App\Enums\ReservationStatus::Enregistree)
                                        <div class="small"><x-admin::mode-badge :mode="$stay->pricing_mode" /></div>
                                        <div><i class="fas fa-sign-in-alt text-muted me-1"></i>Entrée le {{ $stay->check_in->format('d/m/Y à H\hi') }}</div>
                                        <div><i class="fas fa-sign-out-alt text-muted me-1"></i>Sortie le {{ $stay->check_out->format('d/m/Y à H\hi') }}</div>
                                        @if ($stay->check_out->isPast())
                                            <span class="badge bg-danger">Dépassé</span>
                                        @elseif (\App\Support\BusinessDay::contains($stay->check_out))
                                            <span class="badge bg-warning text-dark">Aujourd'hui</span>
                                        @endif
                                    @else
                                        Arrivée le {{ $stay->check_in->format('d/m/Y à H\hi') }} → {{ $stay->check_out->format('d/m/Y à H\hi') }}
                                        @if ($stay->check_in->isPast())
                                            <span class="badge bg-danger">Arrivée en retard</span>
                                        @endif
                                    @endif
                                </td>
                            @else
                                @if ($filter !== 'occupee')
                                    <td>—</td>
                                @endif
                                <td>—</td>
                            @endif
                        @endcanany
                        <td class="text-end">
                            @if ($room->currentStay)
                                @can('affectations.view')
                                    <a href="{{ route('admin.affectations.show', $room->currentStay) }}" class="btn btn-sm btn-outline-secondary">Affectation</a>
                                @endcan
                            @elseif ($room->pendingArrival)
                                @can('reservations.manage')
                                    <a href="{{ route('admin.reservations.edit', $room->pendingArrival) }}" class="btn btn-sm btn-outline-secondary">Réservation</a>
                                @endcan
                            @endif
                            @if ($room->status->value === 'nettoyage')
                                @can('rooms.edit')
                                    <form method="POST" action="{{ route('admin.rooms.ready', $room) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Prête</button>
                                    </form>
                                @endcan
                            @endif
                            @can('rooms.edit')
                                <a href="{{ route('admin.rooms.edit', $room) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            @endcan
                            @can('rooms.manage')
                                <form method="POST" action="{{ route('admin.rooms.destroy', $room) }}" class="d-inline" onsubmit="return confirm('Supprimer cette chambre ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            {{ $filter ? 'Aucune chambre avec ce statut pour l\'instant.' : 'Aucune chambre pour l\'instant.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $rooms->links() }}
@endsection
