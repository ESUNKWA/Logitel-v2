@extends('admin.layouts.admin')

@section('title', 'Dépenses')
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($period = ['du' => $filters['from']->format('Y-m-d'), 'au' => $filters['to']->format('Y-m-d'), 'categorie' => $filters['category'], 'source' => $filters['source']?->value])

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-receipt text-primary me-2"></i>Dépenses</h1>
            <div class="dash-subtitle">
                @if ($isValidator)
                    Argent sorti de l'hôtel. Chaque dépense a un justificatif ; celles de la réception attendent votre validation.
                @else
                    Vos demandes de dépense, avec leur justificatif. Elles comptent une fois validées par le gérant.
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvelle dépense</a>
            @if ($isValidator)
                <a href="{{ route('admin.expense-categories.index') }}" class="btn btn-outline-secondary"><i class="far fa-tags"></i> Catégories</a>
                <a href="{{ route('admin.expenses.export', array_filter($period)) }}" class="btn btn-outline-primary phone-hidden"><i class="far fa-file-excel"></i> Exporter (Excel)</a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('admin.expenses.index') }}" class="admin-card">
        @if ($filters['tab'])
            <input type="hidden" name="onglet" value="{{ $filters['tab'] }}">
        @endif
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small" for="du">Du</label>
                <input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $period['du'] }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="au">Au</label>
                <input type="date" class="form-control form-control-sm" id="au" name="au" value="{{ $period['au'] }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small" for="categorie">Catégorie</label>
                <select class="form-select form-select-sm" id="categorie" name="categorie">
                    <option value="">Toutes</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected($filters['category'] === $c->id)>{{ $c->name }}@unless ($c->is_active) (inactive)@endunless</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small" for="source">Source</label>
                <select class="form-select form-select-sm" id="source" name="source">
                    <option value="">Toutes</option>
                    @foreach (\App\Enums\ExpenseSource::cases() as $s)
                        <option value="{{ $s->value }}" @selected($filters['source'] === $s)>{{ $s->getLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="far fa-filter"></i> Filtrer</button>
                <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="far fa-undo"></i></a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="admin-card h-100 mb-0">
                <div class="text-muted small">Dépenses validées du {{ $filters['from']->format('d/m/Y') }} au {{ $filters['to']->format('d/m/Y') }}</div>
                <div class="fs-3 fw-bold" data-expense-total>{{ $fcfa($total) }} <small class="fs-6 text-muted">FCFA</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card h-100 mb-0">
                <div class="text-muted small mb-1">Par catégorie</div>
                @forelse ($categories->filter(fn ($c) => ($byCategory[$c->id] ?? 0) > 0) as $c)
                    <div class="d-flex justify-content-between small"><span>{{ $c->name }}</span><strong>{{ $fcfa($byCategory[$c->id]) }} F</strong></div>
                @empty
                    <div class="small text-muted">—</div>
                @endforelse
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-card h-100 mb-0">
                <div class="text-muted small mb-1">Par source</div>
                @forelse (collect(\App\Enums\ExpenseSource::cases())->filter(fn ($s) => ($bySource[$s->value] ?? 0) > 0) as $s)
                    <div class="d-flex justify-content-between small"><span>{{ $s->getLabel() }}</span><strong>{{ $fcfa($bySource[$s->value]) }} F</strong></div>
                @empty
                    <div class="small text-muted">—</div>
                @endforelse
            </div>
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (\App\Http\Controllers\Admin\ExpenseController::TABS as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.expenses.index', array_filter([...$period, 'onglet' => $key])) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }} <span class="badge {{ $key === 'attente' && $counts['attente'] ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $counts[$key] }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="admin-card">
        @if ($tab === 'attente')
            <p class="small text-muted">Toutes les demandes en attente, quelle que soit la période.</p>
        @endif
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Date</th>
                    <th>Catégorie</th>
                    <th class="text-end" data-card-title>Montant</th>
                    <th>Source</th>
                    <th class="cell-hide-sm">Description</th>
                    @if ($isValidator)
                        <th>Demandée par</th>
                    @endif
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('admin.expenses.show', $expense) }}" class="file-ref">{{ $expense->reference() }}</a></td>
                        <td class="text-nowrap" data-order="{{ $expense->business_date->format('Ymd') }}">{{ $expense->business_date->format('d/m/Y') }}</td>
                        <td>{{ $expense->category?->name }}</td>
                        <td class="text-end text-nowrap" data-order="{{ (float) $expense->amount }}"><strong>{{ $fcfa($expense->amount) }} F</strong></td>
                        <td class="small">{{ $expense->source->getLabel() }}</td>
                        <td class="cell-hide-sm small">{{ \Illuminate\Support\Str::limit($expense->description, 80) }}@if ($expense->beneficiary)<div class="text-muted">{{ $expense->beneficiary }}</div>@endif</td>
                        @if ($isValidator)
                            <td class="small">{{ $expense->requester?->name ?? '—' }}</td>
                        @endif
                        <td><span class="badge bg-{{ $expense->status->getColor() }}">{{ $expense->status->getLabel() }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.expenses.show', $expense) }}" class="btn btn-sm btn-outline-primary"><i class="far fa-eye"></i> Voir</a>
                            @if ($isValidator && $expense->isPending())
                                <form method="POST" action="{{ route('admin.expenses.approve', $expense) }}" class="d-inline" data-confirm="Valider la dépense {{ $expense->reference() }} de {{ $fcfa($expense->amount) }} F ?">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success"><i class="far fa-check"></i> Valider</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isValidator ? 9 : 8 }}" class="text-center text-muted py-4">Aucune dépense ici.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $expenses->links() }}
    </div>
@endsection
