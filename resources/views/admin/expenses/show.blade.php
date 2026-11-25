@extends('admin.layouts.admin')

@section('title', 'Dépense '.$expense->reference())
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($isOwnPending = $expense->isPending() && $expense->requested_by === auth()->id())

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-receipt text-primary me-2"></i>Dépense <span class="file-ref">{{ $expense->reference() }}</span></h1>
            <div class="dash-subtitle"><span class="badge bg-{{ $expense->status->getColor() }}">{{ $expense->status->getLabel() }}</span></div>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary"><i class="far fa-arrow-left"></i> Dépenses</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="admin-card h-100 mb-0">
                <dl class="row mb-0 expense-details">
                    <dt class="col-5">Montant</dt><dd class="col-7 fs-5 fw-bold">{{ $fcfa($expense->amount) }} FCFA</dd>
                    <dt class="col-5">Date</dt><dd class="col-7">{{ $expense->business_date->format('d/m/Y') }}</dd>
                    <dt class="col-5">Catégorie</dt><dd class="col-7">{{ $expense->category?->name }}</dd>
                    <dt class="col-5">Payée avec</dt><dd class="col-7">{{ $expense->source->getLabel() }}</dd>
                    @if ($expense->source->tillMethod())
                        <dt class="col-5">Caisse de</dt><dd class="col-7">{{ $expense->attributedTo?->name ?? '—' }}</dd>
                    @endif
                    <dt class="col-5">Bénéficiaire</dt><dd class="col-7">{{ $expense->beneficiary ?: '—' }}</dd>
                    <dt class="col-5">Description</dt><dd class="col-7">{{ $expense->description }}</dd>
                    <dt class="col-5">Demandée par</dt><dd class="col-7">{{ $expense->requester?->name ?? '—' }} <span class="text-muted small">le {{ $expense->created_at->format('d/m/Y H\hi') }}</span></dd>
                    @if ($expense->decided_at)
                        <dt class="col-5">{{ $expense->status === \App\Enums\ExpenseStatus::Refusee ? 'Refusée par' : 'Validée par' }}</dt>
                        <dd class="col-7">{{ $expense->decider?->name ?? '—' }} <span class="text-muted small">le {{ $expense->decided_at->format('d/m/Y H\hi') }}</span>
                            @if ($expense->decision_reason)<div class="small">Motif : {{ $expense->decision_reason }}</div>@endif
                        </dd>
                    @endif
                    @if ($expense->cancelled_at)
                        <dt class="col-5">Annulée par</dt>
                        <dd class="col-7">{{ $expense->canceller?->name ?? '—' }} <span class="text-muted small">le {{ $expense->cancelled_at->format('d/m/Y H\hi') }}</span>
                            @if ($expense->cancel_reason)<div class="small">Motif : {{ $expense->cancel_reason }}</div>@endif
                        </dd>
                    @endif
                </dl>

                @if ($isValidator && $expense->isPending())
                    <hr>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <form method="POST" action="{{ route('admin.expenses.approve', $expense) }}" data-confirm="Valider la dépense {{ $expense->reference() }} de {{ $fcfa($expense->amount) }} F ?" data-confirm-title="Valider la dépense" data-confirm-button="Valider">
                            @csrf
                            <button type="submit" class="btn btn-success"><i class="far fa-check"></i> Valider</button>
                        </form>
                        <form method="POST" action="{{ route('admin.expenses.refuse', $expense) }}" class="flex-grow-1 d-flex gap-2" data-confirm="Refuser la dépense {{ $expense->reference() }} ?" data-confirm-title="Refuser la dépense" data-confirm-button="Refuser" data-confirm-danger>
                            @csrf
                            <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" maxlength="500" placeholder="Motif du refus (obligatoire)" required>
                            <button type="submit" class="btn btn-outline-danger text-nowrap"><i class="far fa-times"></i> Refuser</button>
                        </form>
                    </div>
                @endif

                @if (($isValidator && in_array($expense->status, [\App\Enums\ExpenseStatus::EnAttente, \App\Enums\ExpenseStatus::Validee], true)) || $isOwnPending)
                    <hr>
                    <form method="POST" action="{{ route('admin.expenses.cancel', $expense) }}" class="d-flex gap-2" data-confirm="Annuler la dépense {{ $expense->reference() }} ? Elle restera visible, barrée." data-confirm-title="Annuler la dépense" data-confirm-button="Annuler la dépense" data-confirm-cancel="Retour" data-confirm-danger>
                        @csrf
                        <input type="text" name="reason" class="form-control form-control-sm @error('reason') is-invalid @enderror" maxlength="500"
                            placeholder="{{ $isValidator && ! $expense->isPending() ? 'Motif de l\'annulation (obligatoire)' : 'Motif (facultatif)' }}" @required($isValidator && ! $expense->isPending())>
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="far fa-ban"></i> {{ $isOwnPending && ! $isValidator ? 'Retirer ma demande' : 'Annuler la dépense' }}</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            <div class="admin-card h-100 mb-0">
                <h5 class="mb-2"><i class="far fa-paperclip me-1"></i> Justificatif</h5>
                @if ($expense->receipt_mime === 'application/pdf')
                    <a href="{{ route('admin.expenses.receipt', $expense) }}" class="btn btn-outline-primary"><i class="far fa-file-pdf"></i> Télécharger le PDF</a>
                @else
                    <a href="{{ route('admin.expenses.receipt', $expense) }}" target="_blank" rel="noopener">
                        <img src="{{ route('admin.expenses.receipt', $expense) }}" alt="Justificatif de la dépense {{ $expense->reference() }}" class="expense-receipt-preview">
                    </a>
                    <div class="small text-muted mt-1">Touchez l'image pour l'ouvrir en grand.</div>
                @endif
            </div>
        </div>
    </div>
@endsection
