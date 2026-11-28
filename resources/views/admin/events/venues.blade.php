@extends('admin.layouts.admin')

@section('title', 'Salles')
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-door-open text-primary me-2"></i>Salles</h1>
            <div class="dash-subtitle">Les salles à louer pour les réunions et événements : capacité et tarifs. Une salle déjà réservée ne se supprime pas : désactivez-la.</div>
        </div>
        <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary"><i class="far fa-briefcase"></i> Réservations</a>
    </div>

    <div class="admin-card">
        <h5>Ajouter une salle</h5>
        <form method="POST" action="{{ route('admin.venues.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small" for="new-name">Nom</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="new-name" name="name" maxlength="120" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="new-capacity">Capacité</label>
                <input type="number" class="form-control @error('capacity') is-invalid @enderror" id="new-capacity" name="capacity" min="1" max="10000" value="{{ old('capacity') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="new-half">Demi-journée</label>
                <input type="text" inputmode="numeric" data-money class="form-control @error('half_day_rate') is-invalid @enderror" id="new-half" name="half_day_rate" value="{{ old('half_day_rate') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="new-day">Journée</label>
                <input type="text" inputmode="numeric" data-money class="form-control @error('day_rate') is-invalid @enderror" id="new-day" name="day_rate" value="{{ old('day_rate') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="new-description">Description</label>
                <input type="text" class="form-control" id="new-description" name="description" maxlength="500" value="{{ old('description') }}">
            </div>
            <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="far fa-plus"></i></button></div>
            @foreach (['capacity', 'half_day_rate', 'day_rate'] as $field)
                @error($field)<div class="col-12 text-danger small">{{ $message }}</div>@enderror
            @endforeach
        </form>
    </div>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-keep table-stack">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th class="catalog-table__small">Capacité</th>
                        <th class="catalog-table__price">Demi-journée (F)</th>
                        <th class="catalog-table__price">Journée (F)</th>
                        <th class="catalog-table__small">Ordre</th>
                        <th class="text-center">Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($venues as $venue)
                        @php($f = 'venue-form-'.$venue->id)
                        <tr class="{{ $venue->is_active ? '' : 'catalog-table__inactive' }}">
                            <td>
                                <input type="text" class="form-control form-control-sm" form="{{ $f }}" name="name" maxlength="120" value="{{ $venue->name }}" aria-label="Nom" required>
                                <input type="hidden" form="{{ $f }}" name="description" value="{{ $venue->description }}">
                            </td>
                            <td><input type="number" class="form-control form-control-sm" form="{{ $f }}" name="capacity" min="1" max="10000" value="{{ $venue->capacity }}" aria-label="Capacité" required></td>
                            <td><input type="text" inputmode="numeric" data-money class="form-control form-control-sm text-end" form="{{ $f }}" name="half_day_rate" value="{{ $fcfa($venue->half_day_rate) }}" aria-label="Tarif demi-journée" required></td>
                            <td><input type="text" inputmode="numeric" data-money class="form-control form-control-sm text-end" form="{{ $f }}" name="day_rate" value="{{ $fcfa($venue->day_rate) }}" aria-label="Tarif journée" required></td>
                            <td><input type="number" class="form-control form-control-sm" form="{{ $f }}" name="position" min="0" max="9999" value="{{ $venue->position }}" aria-label="Ordre"></td>
                            <td class="text-center">
                                <input type="hidden" form="{{ $f }}" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" form="{{ $f }}" name="is_active" value="1" @checked($venue->is_active) aria-label="Active">
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('admin.venues.update', $venue) }}" id="{{ $f }}" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Enregistrer</button>
                                </form>
                                <form method="POST" action="{{ route('admin.venues.destroy', $venue) }}" class="d-inline" data-confirm="La salle « {{ $venue->name }} » sera supprimée." data-confirm-button="Oui, supprimer" data-confirm-danger>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer « {{ $venue->name }} »"><i class="far fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune salle : ajoutez la première ci-dessus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
