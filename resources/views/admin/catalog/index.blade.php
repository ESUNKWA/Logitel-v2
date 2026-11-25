@extends('admin.layouts.admin')

@section('title', 'Catalogue des prestations')
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($slug = $department->slug())

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-list text-primary me-2"></i>Catalogue des prestations</h1>
            <div class="dash-subtitle">Ce que chaque service vend et son prix. Une prestation déjà vendue ne se supprime pas : désactivez-la, les ventes passées gardent leur prix.</div>
        </div>
        @if ($department === \App\Enums\Department::Evenements)
            @can('events.manage')
                <a href="{{ route('admin.events.index') }}" class="btn btn-outline-secondary"><i class="far fa-briefcase"></i> Réservations de salles</a>
            @endcan
            @can('catalog.manage')
                <a href="{{ route('admin.venues.index') }}" class="btn btn-outline-secondary"><i class="far fa-door-open"></i> Salles</a>
            @endcan
        @else
            @can('sales.create')
                <a href="{{ route('admin.sales.index', $slug) }}" class="btn btn-outline-secondary"><i class="far {{ $department->icon() }}"></i> Ventes {{ $department->getLabel() }}</a>
            @endcan
        @endif
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach ($departments as $d)
            <li class="nav-item">
                <a class="nav-link {{ $d === $department ? 'active' : '' }}" @if ($d === $department) aria-current="page" @endif href="{{ route('admin.catalog.index', ['service' => $d->slug()]) }}"><i class="far {{ $d->icon() }}"></i> {{ $d->getLabel() }}</a>
            </li>
        @endforeach
    </ul>

    <datalist id="catalog-categories">
        @foreach ($categories as $category)
            <option value="{{ $category }}"></option>
        @endforeach
    </datalist>

    <div class="admin-card">
        <h5>Ajouter une prestation</h5>
        <form method="POST" action="{{ route('admin.catalog.store') }}" class="row g-2 align-items-end">
            @csrf
            <input type="hidden" name="service" value="{{ $slug }}">
            <div class="col-md-4">
                <label class="form-label small" for="new-name">Nom</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="new-name" name="name" maxlength="120" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="new-category">Catégorie <span class="text-muted">(facultatif)</span></label>
                <input type="text" class="form-control" id="new-category" name="category" maxlength="60" list="catalog-categories" value="{{ old('category') }}" placeholder="Boissons, Plats…">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="new-price">Prix</label>
                <div class="input-group">
                    <input type="text" inputmode="numeric" data-money class="form-control @error('price') is-invalid @enderror" id="new-price" name="price" value="{{ old('price') }}" required>
                    <span class="input-group-text">F</span>
                </div>
                @error('price')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            @if ($department->countsEntries())
                <div class="col-md-2">
                    <div class="form-check">
                        <input type="hidden" name="counts_entry" value="0">
                        <input class="form-check-input" type="checkbox" id="new-entry" name="counts_entry" value="1" @checked(old('counts_entry'))>
                        <label class="form-check-label small" for="new-entry">Compte une entrée</label>
                    </div>
                </div>
            @endif
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="far fa-plus"></i></button>
            </div>
        </form>
    </div>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 catalog-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Ordre</th>
                        @if ($department->countsEntries())<th>Entrée</th>@endif
                        <th>Active</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="{{ $item->is_active ? '' : 'text-muted' }}">
                            <td colspan="{{ $department->countsEntries() ? 6 : 5 }}">
                                <form method="POST" action="{{ route('admin.catalog.update', $item) }}" class="row g-2 align-items-center catalog-row" id="item-form-{{ $item->id }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name" maxlength="120" value="{{ $item->name }}" aria-label="Nom" required></div>
                                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="category" maxlength="60" list="catalog-categories" value="{{ $item->category }}" aria-label="Catégorie"></div>
                                    <div class="col-md-2"><input type="text" inputmode="numeric" data-money class="form-control form-control-sm" name="price" value="{{ $fcfa($item->price) }}" aria-label="Prix" required></div>
                                    <div class="col-md-1"><input type="number" class="form-control form-control-sm" name="position" min="0" max="9999" value="{{ $item->position }}" aria-label="Ordre"></div>
                                    @if ($department->countsEntries())
                                        <div class="col-auto">
                                            <input type="hidden" name="counts_entry" value="0">
                                            <input class="form-check-input" type="checkbox" name="counts_entry" value="1" @checked($item->counts_entry) aria-label="Compte une entrée">
                                        </div>
                                    @endif
                                    <div class="col-auto">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked($item->is_active) aria-label="Active">
                                    </div>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="submit" form="item-form-{{ $item->id }}" class="btn btn-sm btn-outline-primary">Enregistrer</button>
                                <form method="POST" action="{{ route('admin.catalog.destroy', $item) }}" class="d-inline" data-confirm="La prestation « {{ $item->name }} » sera supprimée." data-confirm-button="Oui, supprimer" data-confirm-danger>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer « {{ $item->name }} »"><i class="far fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Aucune prestation pour ce service : ajoutez la première ci-dessus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
