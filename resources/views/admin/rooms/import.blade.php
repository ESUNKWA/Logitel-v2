@extends('admin.layouts.admin')

@section('title', 'Importer des chambres')
@section('sweetalert-confirm', '1')

@section('content')
    @php
        $counts = $analysis ? collect($analysis['rows'])->countBy('state') : collect();
        $states = [
            'create' => ['À créer', 'success', 'fa-check-circle'],
            'skip' => ['Déjà existante / en double', 'secondary', 'fa-minus-circle'],
            'error' => ['Erreur', 'danger', 'fa-times-circle'],
        ];
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-file-import me-2 text-primary"></i>Importer des chambres</h1>
            <div class="dash-subtitle">Depuis un fichier CSV (Excel : « Enregistrer sous… » → CSV). Les chambres déjà existantes ne sont jamais modifiées ; numéros et noms doivent être uniques.</div>
        </div>
        <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Retour aux chambres</a>
    </div>

    <div class="admin-card mb-3">
        <form method="POST" action="{{ route('admin.rooms.import.preview') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-6">
                <label class="form-label" for="file">Fichier CSV</label>
                <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept=".csv,text/csv,text/plain" required>
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">1 Mo et {{ number_format(\App\Support\RoomCsvImport::MAX_ROWS, 0, ',', ' ') }} lignes au plus. Séparateur « ; » ou « , ».</div>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary"><i class="far fa-search"></i> Analyser le fichier</button>
            </div>
            <div class="col-md-auto">
                <a href="{{ route('admin.rooms.import.template') }}" class="btn btn-outline-primary"><i class="far fa-download"></i> Télécharger le modèle</a>
            </div>
        </form>

        <details class="mt-3">
            <summary class="fw-semibold">Format attendu</summary>
            <ul class="small mt-2 mb-0">
                <li>Première ligne : les noms de colonnes. <strong>numero</strong> et <strong>type</strong> sont obligatoires ; <strong>nom</strong>, <strong>etage</strong>, <strong>statut</strong>, <strong>notes</strong> sont facultatives.</li>
                <li><strong>type</strong> : le nom exact d'un type existant (majuscules indifférentes) —
                    @forelse ($roomTypes as $name)
                        « {{ $name }} »@if (! $loop->last), @endif
                    @empty
                        aucun type pour l'instant : créez-en un d'abord.
                    @endforelse
                </li>
                <li><strong>statut</strong> : disponible (par défaut), nettoyage ou hors service.</li>
                <li><strong>nom</strong> : le nom affiché sur le site ; facultatif, mais unique s'il est rempli.</li>
                <li>Numéros et noms sont comparés sans tenir compte des majuscules ni des espaces.</li>
            </ul>
        </details>
    </div>

    @if ($analysis)
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach ($states as $state => [$label, $color, $icon])
                <span class="badge bg-{{ $color }} fs-6 fw-semibold"><i class="far {{ $icon }}"></i> {{ $label }} : {{ $counts->get($state, 0) }}</span>
            @endforeach
        </div>

        <div class="admin-card p-0 mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0 room-import-table">
                    <thead>
                        <tr>
                            <th>Ligne</th>
                            <th data-card-title>Numéro</th>
                            <th>Type</th>
                            <th>Nom</th>
                            <th>Étage</th>
                            <th>Statut</th>
                            <th>Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($analysis['rows'] as $row)
                            <tr class="room-import-row room-import-row--{{ $row['state'] }}">
                                <td>{{ $row['line'] }}</td>
                                <td class="fw-semibold">{{ $row['number'] !== '' ? $row['number'] : '—' }}</td>
                                <td>{{ $row['type'] !== '' ? $row['type'] : '—' }}</td>
                                <td>{{ $row['name'] !== '' ? $row['name'] : '—' }}</td>
                                <td>{{ $row['floor'] !== '' ? $row['floor'] : '—' }}</td>
                                <td>{{ $row['status_label'] }}</td>
                                <td>
                                    <span class="badge bg-{{ $states[$row['state']][1] }}"><i class="far {{ $states[$row['state']][2] }}"></i> {{ $states[$row['state']][0] }}</span>
                                    @foreach ($row['messages'] as $message)
                                        <div class="small text-{{ $row['state'] === 'error' ? 'danger' : 'muted' }}">{{ $message }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($counts->get('create', 0) > 0)
            <form method="POST" action="{{ route('admin.rooms.import.store') }}" class="d-flex flex-wrap gap-2 align-items-center"
                data-confirm="{{ $counts->get('create') }} chambre(s) seront créées. Les lignes en erreur ou déjà existantes sont ignorées."
                data-confirm-title="Importer les chambres ?" data-confirm-button="Oui, importer">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-success"><i class="far fa-file-import"></i> Importer {{ $counts->get('create') }} chambre(s)</button>
                @if ($counts->get('error', 0) > 0)
                    <span class="text-muted small">Les {{ $counts->get('error') }} ligne(s) en erreur ne seront pas importées : corrigez le fichier et renvoyez-le si besoin.</span>
                @endif
            </form>
        @else
            <div class="alert alert-warning mb-0">Aucune chambre à créer dans ce fichier.</div>
        @endif
    @endif
@endsection
