@extends('admin.layouts.admin')

@section('title', 'Clients')

@section('content')
    <div class="admin-page-header">
        <h1>Clients</h1>
        @can('guests.manage')
            <a href="{{ route('admin.guests.create') }}" class="btn btn-primary">
                <i class="far fa-plus"></i> Nouveau client
            </a>
        @endcan
    </div>

    <div class="admin-card">
        <form method="GET" class="mb-3">
            <input type="search" name="q" class="form-control" placeholder="Rechercher un client (nom, téléphone)…" value="{{ request('q') }}">
        </form>

        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>E-mail</th>
                    <th class="text-center"><span class="file-badge file-badge--affectation"><i class="fas fa-key" aria-hidden="true"></i> Affectations</span></th>
                    <th class="text-center"><span class="file-badge file-badge--reservation"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Réservations</span></th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($guests as $guest)
                    <tr>
                        <td>
                            @if ($guest->isCompany())
                                <i class="far fa-building text-muted me-1" title="Personne morale"></i>
                            @endif
                            {{ $guest->fullName() }}
                            @if ($guest->contactName())
                                <div class="small text-muted">Contact : {{ $guest->contactName() }}</div>
                            @endif
                            @if ($guest->isCompany() && $guest->tax_id)
                                <div class="small text-muted">RCCM / NCC : {{ $guest->tax_id }}</div>
                            @endif
                        </td>
                        <td>{{ $guest->phone }}</td>
                        <td>{{ $guest->email ?: '—' }}</td>
                        <td class="text-center">{{ $guest->stays_count }}</td>
                        <td class="text-center">{{ $guest->bookings_count }}</td>
                        <td class="text-end">
                            @can('guests.manage')
                                <a href="{{ route('admin.guests.edit', $guest) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            @endcan
                            @can('guests.delete')
                                <form method="POST" action="{{ route('admin.guests.destroy', $guest) }}" class="d-inline" onsubmit="return confirm('Supprimer ce client ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun client pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $guests->links() }}
@endsection
