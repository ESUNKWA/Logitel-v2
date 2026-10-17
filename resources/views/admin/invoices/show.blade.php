@extends('admin.layouts.admin')

@section('title', "Facture {$invoice->number}")

@section('content')
    <div class="admin-page-header">
        <h1>Facture {{ $invoice->number }}</h1>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-{{ $invoice->status->getColor() }} fs-6">{{ $invoice->status->getLabel() }}</span>
            @if ($invoice->reservation)
                <a href="{{ route('admin.receipts.show', $invoice->reservation) }}" class="btn btn-outline-primary phone-hidden" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="admin-card">
                <h5>Détails</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Dossier</dt>
                    <dd class="col-7"><x-admin::file-badge :file="$invoice->reservation" :link="true" /></dd>
                    <dt class="col-5">Client</dt>
                    <dd class="col-7">{{ $invoice->reservation?->guest?->fullName() }}</dd>
                    <dt class="col-5">Chambre</dt>
                    <dd class="col-7">N°{{ $invoice->reservation?->room?->number }}</dd>
                    <dt class="col-5">Séjour</dt>
                    <dd class="col-7">
                        {{ $invoice->reservation?->check_in->format('d/m/Y H\hi') }}
                        →
                        {{ $invoice->reservation?->check_out->format('d/m/Y H\hi') }}
                    </dd>
                    @if ($invoice->reservation)
                        <dt class="col-5">Formule</dt>
                        <dd class="col-7"><x-admin::mode-badge :mode="$invoice->reservation->pricing_mode" /> : {{ $invoice->reservation->billingLabel() }}</dd>
                        @if ($invoice->reservation->hasDiscount())
                            <dt class="col-5">Sous-total</dt>
                            <dd class="col-7">{{ number_format((float) $invoice->reservation->subtotal(), 0, ',', ' ') }} FCFA</dd>
                            <dt class="col-5">Remise</dt>
                            <dd class="col-7"><span class="text-success fw-semibold">− {{ number_format((float) $invoice->reservation->discount_amount, 0, ',', ' ') }} FCFA</span> <span class="small text-muted">({{ $invoice->reservation->discountLabel() }})</span></dd>
                        @endif
                    @endif
                    <dt class="col-5">Émise le</dt>
                    <dd class="col-7">{{ $invoice->issued_at->format('d/m/Y') }}</dd>
                    <dt class="col-5">Montant total</dt>
                    <dd class="col-7"><strong>{{ number_format((float) $invoice->amount, 0, ',', ' ') }} FCFA</strong></dd>
                    <dt class="col-5">Payé</dt>
                    <dd class="col-7">{{ number_format((float) $invoice->amountPaid(), 0, ',', ' ') }} FCFA</dd>
                    <dt class="col-5">Reste à payer</dt>
                    <dd class="col-7"><strong class="{{ (float) $invoice->remainingAmount() > 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $invoice->remainingAmount(), 0, ',', ' ') }} FCFA</strong></dd>
                </dl>
                <div class="mt-3">
                    @if ($invoice->reservation?->isStay())
                        @can('affectations.view')
                            <a href="{{ route('admin.affectations.show', $invoice->reservation) }}" class="btn btn-sm btn-outline-secondary">Voir l'affectation</a>
                        @endcan
                    @elseif ($invoice->reservation)
                        @can('reservations.manage')
                            <a href="{{ route('admin.reservations.edit', $invoice->reservation) }}" class="btn btn-sm btn-outline-secondary">Voir la réservation</a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>

        @can('payments.create')
            <div class="col-md-6">
                <div class="admin-card">
                    <h5>Encaisser un paiement</h5>
                    @if ((float) $invoice->remainingAmount() > 0)
                        <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}">
                            @csrf
                            <p class="text-muted small mb-2">
                                Reste dû : <strong>{{ number_format((float) $invoice->remainingAmount(), 0, ',', ' ') }} FCFA</strong>.
                                Pour un paiement mixte (ex. espèces + Wave), ajoutez une ligne par mode.
                            </p>
                            @include('admin.payments._lines', ['due' => $invoice->remainingAmount(), 'required' => true])
                            <div class="row g-2 mt-1">
                                <div class="col-sm-6">
                                    <label class="form-label" for="paid_at">Date</label>
                                    <input type="datetime-local" class="form-control" id="paid_at" name="paid_at" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="payment-notes">Note</label>
                                    <input type="text" class="form-control" id="payment-notes" name="notes" value="{{ old('notes') }}">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-cash-register"></i> Enregistrer l'encaissement</button>
                        </form>
                    @else
                        <div class="alert alert-success mb-0"><i class="fas fa-check-circle me-1"></i> Facture soldée : rien à encaisser.</div>
                    @endif
                </div>
            </div>
        @endcan
    </div>

    <div class="admin-card">
        <h5>Historique des encaissements</h5>
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Détail</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->payments->groupBy(fn ($payment) => $payment->settlement ?? 'single-'.$payment->id) as $lines)
                    @php
                        $first = $lines->first();
                    @endphp
                    <tr>
                        <td>
                            {{ $first->paid_at->format('d/m/Y H:i') }}
                            <div class="small text-muted">par {{ $first->creator?->name ?? '—' }}</div>
                        </td>
                        <td>
                            <strong>{{ number_format((float) $lines->sum('amount'), 0, ',', ' ') }} FCFA</strong>
                            @if ($lines->count() > 1)
                                <span class="badge bg-info ms-1">Mixte</span>
                            @endif
                        </td>
                        <td>
                            @foreach ($lines as $line)
                                <div class="payment-chip">
                                    <i class="fas {{ $line->method->icon() }}"></i>
                                    {{ $line->method->getLabel() }} · {{ number_format((float) $line->amount, 0, ',', ' ') }} FCFA
                                    @if ($line->amount_received !== null && (float) $line->change_given > 0)
                                        <span class="text-muted small">(reçu {{ number_format((float) $line->amount_received, 0, ',', ' ') }}, monnaie rendue {{ number_format((float) $line->change_given, 0, ',', ' ') }})</span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-end">
                            @can('payments.delete')
                                <form method="POST" action="{{ route('admin.invoices.payments.destroy', [$invoice, $first]) }}" onsubmit="return confirm('Supprimer cet encaissement{{ $lines->count() > 1 ? ' (toutes ses lignes)' : '' }} ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Aucun paiement enregistré.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
