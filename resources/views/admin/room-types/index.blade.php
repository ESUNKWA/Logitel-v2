@extends('admin.layouts.admin')

@section('title', 'Types de chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Types de chambre</h1>
        <a href="{{ route('admin.room-types.create') }}" class="btn btn-primary">
            <i class="far fa-plus"></i> Nouveau type
        </a>
    </div>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th class="text-end">À l'heure</th>
                    <th class="text-end">Journée ({{ config('hotel.day_hours') }} h)</th>
                    <th class="text-end">Nuitée</th>
                    <th class="text-end">Séjour (24 h)</th>
                    <th>Capacité</th>
                    <th>Chambres</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roomTypes as $roomType)
                    <tr>
                        <td>{{ $roomType->name }}</td>
                        @foreach (['hourly_rate', 'day_rate', 'night_rate', 'stay_rate'] as $rate)
                            <td class="text-end">
                                {{ $roomType->{$rate} !== null ? number_format((float) $roomType->{$rate}, 0, ',', ' ').' FCFA' : '—' }}
                            </td>
                        @endforeach
                        <td>{{ $roomType->capacity_adults }} adultes / {{ $roomType->capacity_children }} enfants</td>
                        <td>{{ $roomType->rooms_count }}</td>
                        <td>
                            @if ($roomType->is_active)
                                <span class="badge bg-success">Actif</span>
                            @else
                                <span class="badge bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.room-types.edit', $roomType) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form method="POST" action="{{ route('admin.room-types.destroy', $roomType) }}" class="d-inline" onsubmit="return confirm('Supprimer ce type de chambre ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Aucun type de chambre pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $roomTypes->links() }}
@endsection
