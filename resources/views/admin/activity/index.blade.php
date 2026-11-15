@extends('admin.layouts.admin')

@section('title', "Journal d'activité")

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-history text-primary me-2"></i>Journal d'activité</h1>
            <div class="dash-subtitle">Qui a fait quoi, et quand. Le journal ne peut être ni modifié ni effacé.</div>
        </div>
        <a href="{{ route('admin.activity.export', request()->query()) }}" class="btn btn-outline-primary phone-hidden"><i class="far fa-file-excel"></i> Exporter (Excel)</a>
    </div>

    <form method="GET" action="{{ route('admin.activity.index') }}" class="admin-card activity-filters">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small" for="du">Du</label>
                <input type="date" class="form-control form-control-sm" id="du" name="du" value="{{ $filters['from']->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="au">Au</label>
                <input type="date" class="form-control form-control-sm" id="au" name="au" value="{{ $filters['to']->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="par">Utilisateur</label>
                <select class="form-select form-select-sm" id="par" name="par">
                    <option value="">Tous</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($filters['user'] === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                    <option value="systeme" @selected($filters['user'] === 'systeme')>Système / non connecté</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small" for="categorie">Catégorie</label>
                <select class="form-select form-select-sm" id="categorie" name="categorie">
                    <option value="">Toutes</option>
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small" for="q">Rechercher</label>
                <input type="search" class="form-control form-control-sm" id="q" name="q" maxlength="100" value="{{ $filters['q'] }}" placeholder="AFF-…, FAC-…, nom…">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="far fa-filter"></i> Filtrer</button>
                <a href="{{ route('admin.activity.index') }}" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="far fa-undo"></i></a>
            </div>
        </div>
    </form>

    <div class="admin-card">
        <p class="small text-muted mb-2">{{ $logs->total() }} ligne(s) du {{ $filters['from']->format('d/m/Y') }} au {{ $filters['to']->format('d/m/Y') }}.</p>
        <table class="table align-middle activity-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th data-card-title>Action</th>
                    <th>Dossier</th>
                    <th>Détails</th>
                    <th class="cell-hide-sm">Adresse IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap" data-order="{{ $log->created_at->format('YmdHis') }}">{{ $log->created_at->format('d/m/Y H\hi') }}<span class="text-muted small">:{{ $log->created_at->format('s') }}</span></td>
                        <td>{{ $log->user_name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-light text-dark activity-cat activity-cat--{{ $log->category }}">{{ $log->categoryLabel() }}</span>
                            <div>{{ $log->description }}</div>
                        </td>
                        <td class="text-nowrap">
                            @if ($log->reference)
                                @if ($url = $log->subjectUrl(auth()->user()))
                                    <a href="{{ $url }}" class="file-ref">{{ $log->reference }}</a>
                                @else
                                    <span class="file-ref">{{ $log->reference }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if (! empty($log->changes))
                                <details class="activity-changes">
                                    <summary>{{ count($log->changes) }} détail(s)</summary>
                                    <ul class="list-unstyled small mb-0">
                                        @foreach ($log->changes as $change)
                                            <li>
                                                <strong>{{ $change['label'] ?? '' }}</strong> :
                                                @if (! empty($change['masked']))
                                                    <em class="text-muted">modifié</em>
                                                @else
                                                    @if (($change['old'] ?? null) !== null)
                                                        <span class="text-muted">{{ $change['old'] }}</span> →
                                                    @endif
                                                    {{ $change['new'] ?? '—' }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                —
                            @endif
                        </td>
                        <td class="cell-hide-sm small text-muted">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucune activité sur cette période avec ces filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links() }}
    </div>
@endsection
