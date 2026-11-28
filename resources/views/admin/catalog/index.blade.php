@extends('admin.layouts.admin')

@section('title', 'Catalogue des prestations')
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($slug = $department->slug())
    {{-- Restaurant: menu by day (client request, 2026-11-28). --}}
    @php($byDay = $department->servesByDay())

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
                {{-- The service's categories in a list (client request, 2026-11-28); a new one is typed in
                     the list's search and added (« Ajouter « … » », select-search.js data-allow-create). --}}
                <select class="form-select" id="new-category" name="category" data-allow-create>
                    <option value="">Sans catégorie</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                    @endforeach
                    @if (filled(old('category')) && ! $categories->contains(old('category')))
                        <option value="{{ old('category') }}" selected>{{ old('category') }}</option>
                    @endif
                </select>
                <div class="form-text">Pour une nouvelle catégorie, tapez son nom dans la liste.</div>
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
            @if ($byDay)
                <div class="col-12">
                    <span class="form-label small d-block mb-1">Jours de service <span class="text-muted">(aucun coché = tous les jours : boissons, desserts…)</span></span>
                    @include('admin.catalog._days', ['idPrefix' => 'new-day', 'selected' => array_map('intval', (array) old('days', [])), 'form' => null])
                </div>
            @endif
        </form>
    </div>

    @if ($byDay)
        {{-- The week at a glance: the dishes served on some days only, per day; today highlighted. --}}
        @php($today = \App\Support\BusinessDay::today()->isoWeekday())
        @php($weekly = $items->filter(fn ($i) => $i->is_active && $i->isOnWeeklyMenu()))
        <section class="admin-card menu-week-card" aria-label="Menu de la semaine">
            <h5 class="mb-1">Menu de la semaine</h5>
            <p class="small text-muted mb-3">Les plats servis certains jours seulement. À la vente, seuls les plats du jour sont proposés (les autres restent dans « Hors menu »).</p>
            <div class="menu-week">
                @foreach (\App\Models\CatalogItem::WEEKDAYS as $number => [$short, $long])
                    @php($dishes = $weekly->filter(fn ($i) => in_array($number, $i->servedDays(), true)))
                    <div class="menu-week__day {{ $number === $today ? 'is-today' : '' }}">
                        <div class="menu-week__name">{{ ucfirst($long) }} @if ($number === $today)<span class="badge bg-success">Aujourd'hui</span>@endif</div>
                        @forelse ($dishes as $dish)
                            <div class="menu-week__dish">{{ $dish->name }}<span>{{ $fcfa($dish->price) }} F</span></div>
                        @empty
                            <div class="menu-week__none">Pas de plat du jour</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
            @php($everyDay = $items->filter(fn ($i) => $i->is_active && ! $i->isOnWeeklyMenu())->count())
            <p class="small text-muted mb-0 mt-3">{{ $everyDay }} autre{{ $everyDay > 1 ? 's' : '' }} prestation{{ $everyDay > 1 ? 's' : '' }} servie{{ $everyDay > 1 ? 's' : '' }} tous les jours (aucun jour coché).</p>
        </section>
    @endif

    <div class="admin-card p-0">
        <div class="table-responsive">
            {{-- One cell per column; each line's fields belong to its form through form="…" (a form
                 can't wrap a table row). Kept out of DataTables / phone cards (.table-keep). --}}
            <table class="table align-middle mb-0 catalog-table table-keep table-stack">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th class="catalog-table__price">Prix (F)</th>
                        <th class="catalog-table__small">Ordre</th>
                        @if ($byDay)<th>Jours</th>@endif
                        @if ($department->countsEntries())<th class="text-center">Entrée</th>@endif
                        <th class="text-center">Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php($f = 'item-form-'.$item->id)
                        <tr class="{{ $item->is_active ? '' : 'catalog-table__inactive' }}">
                            <td><input type="text" class="form-control form-control-sm" form="{{ $f }}" name="name" maxlength="120" value="{{ $item->name }}" aria-label="Nom" required></td>
                            <td>
                                <select class="form-select form-select-sm" form="{{ $f }}" name="category" aria-label="Catégorie" data-allow-create>
                                    <option value="">Sans catégorie</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected($item->category === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" inputmode="numeric" data-money class="form-control form-control-sm text-end" form="{{ $f }}" name="price" value="{{ $fcfa($item->price) }}" aria-label="Prix" required></td>
                            <td><input type="number" class="form-control form-control-sm" form="{{ $f }}" name="position" min="0" max="9999" value="{{ $item->position }}" aria-label="Ordre"></td>
                            @if ($byDay)
                                <td>@include('admin.catalog._days', ['idPrefix' => 'day-'.$item->id, 'selected' => $item->servedDays(), 'form' => $f])</td>
                            @endif
                            @if ($department->countsEntries())
                                <td class="text-center">
                                    <input type="hidden" form="{{ $f }}" name="counts_entry" value="0">
                                    <input class="form-check-input" type="checkbox" form="{{ $f }}" name="counts_entry" value="1" @checked($item->counts_entry) aria-label="Compte une entrée">
                                </td>
                            @endif
                            <td class="text-center">
                                <input type="hidden" form="{{ $f }}" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" form="{{ $f }}" name="is_active" value="1" @checked($item->is_active) aria-label="Active">
                            </td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('admin.catalog.update', $item) }}" id="{{ $f }}" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Enregistrer</button>
                                </form>
                                <form method="POST" action="{{ route('admin.catalog.destroy', $item) }}" class="d-inline" data-confirm="La prestation « {{ $item->name }} » sera supprimée." data-confirm-button="Oui, supprimer" data-confirm-danger>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Supprimer « {{ $item->name }} »"><i class="far fa-trash-alt"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 6 + ($department->countsEntries() ? 1 : 0) + ($byDay ? 1 : 0) }}" class="text-center text-muted py-4">Aucune prestation pour ce service : ajoutez la première ci-dessus.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
