@extends('admin.layouts.admin')

@section('title', 'Corbeille des affectations')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($inTrashTab = $tab === 'corbeille')

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-trash-alt text-danger me-2"></i>Corbeille des affectations</h1>
            <div class="dash-subtitle">Affectations supprimées par erreur ? Restaurez-les ici. Elles sont supprimées définitivement après {{ $retentionDays }} jours.</div>
        </div>
        <a href="{{ route('admin.affectations.index') }}" class="btn btn-outline-secondary"><i class="far fa-key"></i> Affectations</a>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (\App\Http\Controllers\Admin\TrashController::TABS as $key => $label)
            <li class="nav-item">
                <a href="{{ route('admin.trash.index', ['onglet' => $key]) }}" class="nav-link {{ $tab === $key ? 'active' : '' }}">
                    {{ $label }} <span class="badge bg-light text-dark">{{ $key === 'corbeille' ? $inTrashCount : $historyCount }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($inTrashTab && $entries->isNotEmpty())
        <form id="trashForm" method="POST" action="{{ route('admin.trash.restore') }}" class="bulk-bar" data-bulk-form>
            @csrf
            <label class="form-check mb-0 bulk-bar__all">
                <input type="checkbox" class="form-check-input" data-bulk-all aria-label="Tout cocher sur cette page">
                <span class="form-check-label">Tout cocher</span>
            </label>
            <span class="bulk-bar__count" data-bulk-status aria-live="polite">Aucune ligne cochée</span>
            <div class="d-flex flex-wrap gap-2 ms-auto">
                <button type="button" class="btn btn-success btn-sm" data-bulk-open="#restoreModal" disabled><i class="far fa-undo"></i> Restaurer (<span data-bulk-n>0</span>)</button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bulk-open="#purgeModal" disabled><i class="far fa-times-circle"></i> Supprimer définitivement (<span data-bulk-n>0</span>)</button>
            </div>
        </form>
    @endif

    <div class="admin-card">
        <table class="table align-middle trash-table">
            <thead>
                <tr>
                    <th>Dossier</th>
                    <th>Client</th>
                    <th>Séjour</th>
                    <th>Montants</th>
                    <th>Supprimé</th>
                    <th>{{ $inTrashTab ? 'Suppression définitive' : 'Devenir' }}</th>
                    @if ($inTrashTab)
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>
                            @if ($inTrashTab)
                                <input type="checkbox" class="form-check-input bulk-check" name="ids[]" value="{{ $entry->id }}" form="trashForm" data-bulk-item data-label="{{ $entry->reference }} — chambre N°{{ $entry->room_number }} — {{ $entry->guest_name }}" data-amount="{{ (float) $entry->paid_amount }}" aria-label="Sélectionner {{ $entry->reference }}">
                            @endif
                            <strong class="file-ref">{{ $entry->reference }}</strong>
                            @if ($entry->booking_reference)
                                <div class="small text-muted">sur réservation {{ $entry->booking_reference }}</div>
                            @endif
                            <div class="small text-muted">Chambre N°{{ $entry->room_number ?? '—' }} @if ($entry->room_type)· {{ $entry->room_type }}@endif</div>
                        </td>
                        <td>{{ $entry->guest_name ?: '—' }}</td>
                        <td class="text-nowrap">
                            @if ($entry->pricingMode())
                                <x-admin::mode-badge :mode="$entry->pricingMode()" />
                            @endif
                            <div class="small text-muted">{{ $entry->check_in?->format('d/m/Y H\hi') }} → {{ $entry->check_out?->format('d/m/Y H\hi') }}</div>
                            @if ($entry->status && ($status = \App\Enums\ReservationStatus::tryFrom($entry->status)))
                                <span class="badge bg-{{ $status->affectationColor() }}">{{ $status->affectationLabel() }}</span>
                            @endif
                        </td>
                        <td class="text-nowrap" data-order="{{ (float) $entry->paid_amount }}">
                            <strong>{{ $fcfa($entry->total_amount) }} FCFA</strong>
                            <div class="small text-muted">encaissé {{ $fcfa($entry->paid_amount) }} FCFA</div>
                            @if ($entry->invoice_number)
                                <div class="small text-muted">{{ $entry->invoice_number }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $entry->trashed_at->format('d/m/Y H\hi') }}
                            <div class="small text-muted">par {{ $entry->trashedBy?->name ?? '—' }}</div>
                            @if ($entry->reason)
                                <div class="small fst-italic">« {{ $entry->reason }} »</div>
                            @endif
                        </td>
                        <td>
                            @if ($entry->isInTrash())
                                @php($daysLeft = max(0, (int) ceil(now()->diffInHours($entry->purgeDueAt(), false) / 24)))
                                @if (! $inTrashTab)
                                    <span class="badge bg-warning text-dark">Dans la corbeille</span>
                                @endif
                                <div class="small">le {{ $entry->purgeDueAt()->format('d/m/Y') }}</div>
                                <div class="small text-muted">dans {{ $daysLeft }} jour(s)</div>
                            @elseif ($entry->restored_at)
                                <span class="badge bg-success">Restauré</span>
                                <div class="small text-muted">le {{ $entry->restored_at->format('d/m/Y H\hi') }} par {{ $entry->restoredBy?->name ?? '—' }}</div>
                            @else
                                <span class="badge bg-secondary">Supprimé définitivement</span>
                                <div class="small text-muted">le {{ $entry->purged_at->format('d/m/Y H\hi') }} {{ $entry->purgedBy ? 'par '.$entry->purgedBy->name : '(automatiquement)' }}</div>
                            @endif
                        </td>
                        @if ($inTrashTab)
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-success" data-bulk-row="{{ $entry->id }}" data-bulk-form-id="trashForm" data-bulk-open="#restoreModal"><i class="far fa-undo"></i> Restaurer</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bulk-row="{{ $entry->id }}" data-bulk-form-id="trashForm" data-bulk-open="#purgeModal"><i class="far fa-times-circle"></i> Supprimer définitivement</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $inTrashTab ? 7 : 6 }}" class="text-center text-muted py-4">
                            {{ $inTrashTab ? 'La corbeille est vide.' : 'Aucune affectation n\'a encore été supprimée.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $entries->links() }}

    @if ($inTrashTab && $entries->isNotEmpty())
        <div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreTitle" aria-hidden="true" data-bulk-modal data-bulk-form-id="trashForm">
            <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header bg-success-subtle">
                        <h2 class="modal-title fs-5" id="restoreTitle"><i class="far fa-undo"></i> Restaurer <span data-bulk-n>0</span> dossier(s)</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Ces dossiers reviennent exactement comme avant leur suppression (facture, paiements, client) :</p>
                        <ul class="bulk-list" data-bulk-list></ul>
                        <div class="alert alert-success py-2"><strong data-bulk-total>0 FCFA</strong> encaissé(s) seront de nouveau comptés dans les recettes, à leurs dates d'origine.</div>
                        <p class="small text-muted mb-0">Un séjour en cours n'est restauré que si sa chambre est toujours libre.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success" form="trashForm" formaction="{{ route('admin.trash.restore') }}"><i class="far fa-undo"></i> Restaurer</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="purgeModal" tabindex="-1" aria-labelledby="purgeTitle" aria-hidden="true" data-bulk-modal data-bulk-form-id="trashForm">
            <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header bg-danger-subtle">
                        <h2 class="modal-title fs-5" id="purgeTitle"><i class="far fa-times-circle"></i> Supprimer définitivement <span data-bulk-n>0</span> dossier(s)</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="bulk-list" data-bulk-list></ul>
                        <div class="alert alert-danger py-2"><strong>Action irréversible :</strong> ces dossiers ne pourront plus jamais être restaurés. Seule la trace de la suppression reste dans l'historique.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger" form="trashForm" formaction="{{ route('admin.trash.purge') }}"><i class="far fa-times-circle"></i> Supprimer définitivement</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
