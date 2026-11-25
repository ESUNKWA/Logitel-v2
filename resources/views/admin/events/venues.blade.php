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
            <table class="table align-middle mb-0 table-keep">
                <thead><tr><th>Nom</th><th>Capacité</th><th>Demi-journée</th><th>Journée</th><th>Ordre</th><th>Active</th><th></th></tr></thead>
                <tbody>
                    @forelse ($venues as $venue)
                        <tr>
                            <td colspan="6">
                                <form method="POST" action="{{ route('admin.venues.update', $venue) }}" class="row g-2 align-items-center" id="venue-form-{{ $venue->id }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="name" maxlength="120" value="{{ $venue->name }}" aria-label="Nom" required></div>
                                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="capacity" min="1" max="10000" value="{{ $venue->capacity }}" aria-label="Capacité" required></div>
                                    <div class="col-md-2"><input type="text" inputmode="numeric" data-money class="form-control form-control-sm" name="half_day_rate" value="{{ $fcfa($venue->half_day_rate) }}" aria-label="Tarif demi-journée" required></div>
                                    <div class="col-md-2"><input type="text" inputmode="numeric" data-money class="form-control form-control-sm" name="day_rate" value="{{ $fcfa($venue->day_rate) }}" aria-label="Tarif journée" required></div>
                                    <div class="col-md-1"><input type="number" class="form-control form-control-sm" name="position" min="0" max="9999" value="{{ $venue->position }}" aria-label="Ordre"></div>
                                    <div class="col-auto">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($venue->is_active) aria-label="Active">
                                    </div>
                                    <input type="hidden" name="description" value="{{ $venue->description }}">
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="submit" form="venue-form-{{ $venue->id }}" class="btn btn-sm btn-outline-primary">Enregistrer</button>
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
