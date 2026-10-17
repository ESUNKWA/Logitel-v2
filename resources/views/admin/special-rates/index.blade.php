@extends('admin.layouts.admin')

@section('title', 'Tarifs spéciaux')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Tarifs spéciaux</h1>
            <div class="dash-subtitle">Forfaits appliqués automatiquement aux affectations (ex. « 2 heures = 5 000 FCFA »). Le plus avantageux pour le client l'emporte ; les réservations n'en bénéficient pas.</div>
        </div>
        <a href="{{ route('admin.special-rates.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouveau forfait</a>
    </div>

    @foreach (['En cours et à venir' => $current, 'Terminés' => $past] as $title => $rates)
        @if ($title === 'En cours et à venir' || $rates->isNotEmpty())
            <h2 class="h6 text-uppercase text-muted fw-bold mt-4 mb-2">{{ $title }}</h2>
            <div class="admin-card">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Forfait</th>
                            <th>Types de chambre (tarif normal → forfait)</th>
                            <th>Jours</th>
                            <th>Période</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rates as $rate)
                            @php($types = $rate->all_room_types ? $roomTypes : $rate->roomTypes->sortBy('name'))
                            <tr>
                                <td><strong>{{ $rate->name }}</strong></td>
                                <td>
                                    <x-admin::mode-badge :mode="$rate->pricing_mode" />
                                    <div class="small">{{ $rate->pricing_mode->durationLabel($rate->duration) }} = <strong>{{ $fcfa($rate->price) }} FCFA</strong></div>
                                </td>
                                <td class="small">
                                    @if ($rate->all_room_types)
                                        <div class="text-muted">Tous les types</div>
                                    @endif
                                    @foreach ($types as $type)
                                        @php($unit = $rate->pricing_mode->rateFor($type))
                                        @php($normal = $unit !== null ? (float) $unit * $rate->duration : null)
                                        <div>
                                            {{ $type->name }} :
                                            @if ($normal === null)
                                                <span class="text-muted">mode sans tarif — ne s'applique pas</span>
                                            @elseif ((float) $rate->price < $normal)
                                                <s class="text-muted">{{ $fcfa($normal) }}</s> → <strong>{{ $fcfa($rate->price) }} F</strong>
                                                <span class="text-success">(− {{ $fcfa($normal - (float) $rate->price) }})</span>
                                            @else
                                                <span class="text-danger">{{ $fcfa($normal) }} F normalement — ne s'applique pas</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td class="small">{{ $rate->daysLabel() }}</td>
                                <td class="small">{{ $rate->periodLabel() }}</td>
                                <td>
                                    @if ($rate->isPast())
                                        <span class="badge bg-secondary">Terminé</span>
                                    @elseif (! $rate->is_active)
                                        <span class="badge bg-secondary">Inactif</span>
                                    @elseif ($rate->starts_on && $rate->starts_on->gt(\App\Support\BusinessDay::today()))
                                        <span class="badge bg-info">À venir</span>
                                    @else
                                        <span class="badge bg-success">Actif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.special-rates.edit', $rate) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                    <form method="POST" action="{{ route('admin.special-rates.destroy', $rate) }}" class="d-inline" onsubmit="return confirm('Supprimer ce forfait ? Les affectations déjà enregistrées gardent leur prix.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Aucun forfait pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
@endsection
