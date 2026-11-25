@extends('admin.layouts.admin')

@section('title', 'Vente '.$sale->reference())
@section('sweetalert-confirm', '1')

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($slug = $department->slug())
    @php($paid = (float) ($invoice?->amountPaid() ?? 0))
    @php($remaining = (float) ($invoice?->remainingAmount() ?? 0))

    <div class="admin-page-header">
        <div>
            <h1><i class="far {{ $department->icon() }} text-primary me-2"></i>Vente {{ $sale->reference() }}</h1>
            <div class="dash-subtitle">
                {{ $department->getLabel() }} · {{ $sale->sold_at->format('d/m/Y à H\hi') }} · saisie par {{ $sale->creator?->name ?? '—' }}
                @if ($sale->isCancelled())
                    · <span class="badge bg-secondary">Annulée</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.sales.receipt', [$slug, $sale]) }}" class="btn btn-outline-primary phone-hidden" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>
            @if ($invoice && ! $sale->isCancelled() && $remaining > 0)
                @can('payments.create')
                    <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-primary"><i class="fas fa-coins"></i> Encaisser</a>
                @endcan
            @endif
            <a href="{{ route('admin.sales.create', $slug) }}" class="btn btn-outline-secondary"><i class="far fa-plus"></i> Nouvelle vente</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="admin-card">
                <h5>Prestations</h5>
                <table class="table align-middle mb-0">
                    <thead>
                        <tr><th>Prestation</th><th class="text-end">Prix</th><th class="text-end">Qté</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->lines as $line)
                            <tr>
                                <td>{{ $line->label }}@if ($line->counts_entry) <span class="badge bg-light text-dark border">entrée</span>@endif</td>
                                <td class="text-end text-nowrap">{{ $fcfa($line->unit_price) }} F</td>
                                <td class="text-end">{{ $line->quantity }}</td>
                                <td class="text-end text-nowrap">{{ $fcfa($line->line_total) }} F</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        @if ((float) $sale->discount_amount > 0)
                            <tr><td colspan="3">Sous-total</td><td class="text-end text-nowrap">{{ $fcfa($sale->subtotal) }} F</td></tr>
                            <tr><td colspan="3">Remise @if ($sale->discount_reason)<span class="text-muted small">— {{ $sale->discount_reason }}</span>@endif</td><td class="text-end text-nowrap text-success">− {{ $fcfa($sale->discount_amount) }} F</td></tr>
                        @endif
                        <tr class="fw-bold"><td colspan="3">Total</td><td class="text-end text-nowrap">{{ $fcfa($sale->total_amount) }} F</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="admin-card">
                <h5>Règlement</h5>
                <dl class="row mb-0">
                    @if ($sale->label)
                        <dt class="col-5">Libellé</dt><dd class="col-7">{{ $sale->label }}</dd>
                    @endif
                    @if ($sale->reservation)
                        <dt class="col-5">Reportée sur</dt>
                        <dd class="col-7">
                            Chambre N°{{ $sale->reservation->room?->number }} — {{ $sale->reservation->guest?->fullName() }}
                            @can('affectations.view')
                                <div><a href="{{ route('admin.affectations.show', $sale->reservation) }}" class="small">Voir le séjour</a></div>
                            @endcan
                        </dd>
                    @endif
                    <dt class="col-5">Facture</dt>
                    <dd class="col-7">
                        @can('invoices.view')
                            <a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice?->number }}</a>
                        @else
                            {{ $invoice?->number }}
                        @endcan
                    </dd>
                    <dt class="col-5">Encaissé</dt><dd class="col-7">{{ $fcfa($paid) }} F</dd>
                    <dt class="col-5">Reste dû</dt>
                    <dd class="col-7">
                        @if ($sale->isCancelled())
                            —
                        @elseif ($remaining > 0)
                            <strong class="text-danger">{{ $fcfa($remaining) }} F</strong>
                        @else
                            <span class="badge bg-success">Soldée</span>
                        @endif
                    </dd>
                </dl>
            </div>

            @if ($sale->isCancelled())
                <div class="admin-card">
                    <h5>Annulation</h5>
                    <p class="mb-0">Annulée par <strong>{{ $sale->cancelledBy?->name ?? '—' }}</strong> le {{ $sale->cancelled_at?->format('d/m/Y à H\hi') }}.<br>Motif : {{ $sale->cancel_reason }}</p>
                </div>
            @else
                @can('sales.cancel')
                    <div class="admin-card">
                        <h5>Annuler la vente</h5>
                        @if ($paid > 0)
                            <p class="small text-muted mb-0">Cette vente a été encaissée : supprimez d'abord l'encaissement depuis la facture pour pouvoir l'annuler.</p>
                        @else
                            <form method="POST" action="{{ route('admin.sales.cancel', [$slug, $sale]) }}" data-confirm="La vente {{ $sale->reference() }} sera annulée. Continuer ?" data-confirm-button="Oui, annuler" data-confirm-danger>
                                @csrf
                                <label class="form-label" for="cancel_reason">Motif</label>
                                <input type="text" class="form-control @error('cancel_reason') is-invalid @enderror" id="cancel_reason" name="cancel_reason" maxlength="500" required>
                                @error('cancel_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <button type="submit" class="btn btn-outline-danger mt-2">Annuler la vente</button>
                            </form>
                        @endif
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endsection
