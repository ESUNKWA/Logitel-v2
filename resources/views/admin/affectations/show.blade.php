@extends('admin.layouts.admin')

@section('title', 'Affectation')

@section('content')
    @php
        $inProgress = $affectation->status === \App\Enums\ReservationStatus::Enregistree;
        $invoice = $affectation->invoice;
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Chambre N°{{ $affectation->room?->number }} — {{ $affectation->guest?->fullName() }}</h1>
            <div class="dash-subtitle">
                Affectation #{{ $affectation->id }}
                <span class="badge bg-{{ $affectation->status->affectationColor() }} ms-1">{{ $affectation->status->affectationLabel() }}</span>
                @if ($affectation->isBooking())
                    · issue d'une réservation (client arrivé)
                @endif
                @if ($affectation->stayRequest)
                    · issue de la <a href="{{ route('admin.stay-requests.show', $affectation->stayRequest) }}">demande client N°{{ $affectation->stayRequest->id }}</a>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('invoices.view')
                <a href="{{ route('admin.receipts.show', $affectation) }}" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>
            @endcan
            @if ($inProgress)
                @can('affectations.manage')
                    <form method="POST" action="{{ route('admin.affectations.check-out', $affectation) }}" onsubmit="return confirm('Confirmer le départ du client (check-out) ?');">
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fas fa-sign-out-alt"></i> Check-out</button>
                    </form>
                @endcan
                @can('affectations.cancel')
                    <form method="POST" action="{{ route('admin.affectations.cancel', $affectation) }}" onsubmit="return confirm('Annuler cette affectation ?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Annuler l'affectation</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="admin-card">
                <h5>Séjour</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Chambre</dt>
                    <dd class="col-7">N°{{ $affectation->room?->number }} — {{ $affectation->room?->roomType?->name }}</dd>
                    <dt class="col-5">Client</dt>
                    <dd class="col-7">{{ $affectation->guest?->fullName() }}@if ($affectation->guest?->phone) — {{ $affectation->guest->phone }}@endif</dd>
                    <dt class="col-5">Formule</dt>
                    <dd class="col-7">{{ $affectation->pricing_mode->getLabel() }} : {{ $affectation->billingLabel() }}</dd>
                    <dt class="col-5">Entrée</dt>
                    <dd class="col-7">{{ $affectation->check_in->format('d/m/Y à H\hi') }}</dd>
                    <dt class="col-5">Sortie prévue</dt>
                    <dd class="col-7">
                        {{ $affectation->check_out->format('d/m/Y à H\hi') }}
                        @if ($inProgress && $affectation->check_out->isPast())
                            <span class="badge bg-danger">Dépassé</span>
                        @endif
                    </dd>
                    <dt class="col-5">Occupants</dt>
                    <dd class="col-7">{{ $affectation->adults }} adulte(s), {{ $affectation->children }} enfant(s)</dd>
                </dl>
            </div>

            <div class="admin-card">
                <h5>Historique</h5>
                <ul class="trail">
                    <li><span>{{ $affectation->isBooking() ? 'Réservation créée' : 'Affectation enregistrée' }}</span> par <strong>{{ $affectation->creator?->name ?? '—' }}</strong> le {{ $affectation->created_at?->format('d/m/Y à H\hi') }}</li>
                    @if ($affectation->checked_in_at)
                        <li><span>Check-in</span> par <strong>{{ $affectation->checkedInBy?->name ?? '—' }}</strong> le {{ $affectation->checked_in_at->format('d/m/Y à H\hi') }}</li>
                    @endif
                    @if ($affectation->checked_out_at)
                        <li><span>Check-out</span> par <strong>{{ $affectation->checkedOutBy?->name ?? '—' }}</strong> le {{ $affectation->checked_out_at->format('d/m/Y à H\hi') }}</li>
                    @endif
                    @if ($affectation->cancelled_at)
                        <li><span>Annulation</span> par <strong>{{ $affectation->cancelledBy?->name ?? '—' }}</strong> le {{ $affectation->cancelled_at->format('d/m/Y à H\hi') }}</li>
                    @endif
                </ul>
            </div>

            @if ($invoice)
                <div class="admin-card">
                    <h5>Facturation</h5>
                    <dl class="row mb-3">
                        <dt class="col-5">Facture</dt>
                        <dd class="col-7">{{ $invoice->number }} <span class="badge bg-{{ $invoice->status->getColor() }} ms-1">{{ $invoice->status->getLabel() }}</span></dd>
                        <dt class="col-5">Montant</dt>
                        <dd class="col-7"><strong>{{ $fcfa($invoice->amount) }} FCFA</strong></dd>
                        <dt class="col-5">Encaissé</dt>
                        <dd class="col-7">{{ $fcfa($invoice->amountPaid()) }} FCFA</dd>
                        <dt class="col-5">Reste à payer</dt>
                        <dd class="col-7"><strong class="{{ (float) $invoice->remainingAmount() > 0 ? 'text-danger' : 'text-success' }}">{{ $fcfa($invoice->remainingAmount()) }} FCFA</strong></dd>
                    </dl>
                    @can('invoices.view')
                        <a href="{{ route('admin.invoices.show', $invoice) }}" class="btn btn-outline-primary"><i class="fas fa-cash-register"></i> Voir la facture / encaisser</a>
                    @endcan
                </div>
            @endif
        </div>

        <div class="col-lg-6">
            @if ($inProgress)
                @can('affectations.manage')
                    <div class="admin-card">
                        <h5>Prolonger ou modifier le séjour</h5>
                        <form method="POST" action="{{ route('admin.affectations.update', $affectation) }}">
                            @csrf
                            @method('PUT')
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label" for="duration">Durée (nombre de {{ $affectation->pricing_mode->unitPlural() }})</label>
                                    <input type="number" min="1" max="365" class="form-control" id="duration" name="duration" value="{{ old('duration', $affectation->duration) }}" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="unit_rate">Tarif {{ $affectation->pricing_mode->rateUnit() }} (FCFA)</label>
                                    <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="unit_rate" name="unit_rate" value="{{ old('unit_rate', number_format((float) $affectation->unit_rate, 0, ',', ' ')) }}" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="adults">Adultes</label>
                                    <input type="number" min="1" class="form-control" id="adults" name="adults" value="{{ old('adults', $affectation->adults) }}" required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="children">Enfants</label>
                                    <input type="number" min="0" class="form-control" id="children" name="children" value="{{ old('children', $affectation->children) }}" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="notes">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $affectation->notes) }}</textarea>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">L'heure de sortie et le montant sont recalculés automatiquement ; la facture suit.</p>
                            <button type="submit" class="btn btn-primary mt-3">Enregistrer</button>
                        </form>
                    </div>
                @endcan
            @else
                <div class="admin-card">
                    <h5>Séjour {{ $affectation->status === \App\Enums\ReservationStatus::Annulee ? 'annulé' : 'terminé' }}</h5>
                    <p class="text-muted mb-0">Ce séjour n'est plus modifiable.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
