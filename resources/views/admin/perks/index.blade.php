@extends('admin.layouts.admin')

@section('title', 'Avantages inclus')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Avantages inclus</h1>
            <div class="dash-subtitle">Services offerts selon le type de chambre et la formule (ex. « Accès piscine gratuit » pour les suites en séjour). Imprimés sur le reçu, affichés à la réception et sur la page de la chambre du site web (formule séjour).</div>
        </div>
        <a href="{{ route('admin.perks.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvel avantage</a>
    </div>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Avantage</th>
                    <th>Types de chambre</th>
                    <th>Formules</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($perks as $perk)
                    <tr>
                        <td><strong><i class="far fa-gift text-muted me-1"></i>{{ $perk->label }}</strong></td>
                        <td class="small">{{ $perk->all_room_types ? 'Tous les types' : ($perk->roomTypes->sortBy('name', SORT_NATURAL)->pluck('name')->implode(', ') ?: '—') }}</td>
                        <td class="small">{{ $perk->modesLabel() }}</td>
                        <td>
                            @if ($perk->is_active)
                                <span class="badge bg-success">Actif</span>
                            @else
                                <span class="badge bg-secondary">Inactif</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.perks.edit', $perk) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form method="POST" action="{{ route('admin.perks.destroy', $perk) }}" class="d-inline" onsubmit="return confirm('Supprimer cet avantage ? Les dossiers déjà enregistrés le gardent.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Aucun avantage pour l'instant.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
