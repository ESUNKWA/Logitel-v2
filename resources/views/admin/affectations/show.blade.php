@extends('admin.layouts.admin')

@section('title', 'Affectation')
@section('sweetalert-confirm', '1')

@section('content')
    @php
        $inProgress = $affectation->status === \App\Enums\ReservationStatus::Enregistree;
        $invoice = $affectation->invoice;
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $remaining = (float) ($invoice?->remainingAmount() ?? 0);
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><x-admin::kind-icon kind="affectation" />Chambre N°{{ $affectation->room?->number }} — {{ $affectation->guest?->fullName() }}</h1>
            <div class="dash-subtitle">
                <x-admin::file-badge :file="$affectation" />
                <span class="badge bg-{{ $affectation->status->affectationColor() }} ms-1">{{ $affectation->status->affectationLabel() }}</span>
                @if ($affectation->isBooking())
                    · issue de la réservation <span class="file-ref">{{ $affectation->bookingReference() }}</span> (client arrivé)
                @endif
                @if ($affectation->stayRequest)
                    · issue de la <a href="{{ route('admin.stay-requests.show', $affectation->stayRequest) }}">demande client N°{{ $affectation->stayRequest->id }}</a>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('invoices.view')
                <a href="{{ route('admin.receipts.show', $affectation) }}" class="btn btn-outline-primary phone-hidden" target="_blank" rel="noopener"><i class="fas fa-print"></i> Imprimer le reçu</a>
            @endcan
            @if ($inProgress)
                @can('affectations.manage')
                    <form method="POST" action="{{ route('admin.affectations.check-out', $affectation) }}" id="check-out"
                        data-confirm-title="Sortie — chambre N°{{ $affectation->room?->number }}"
                        data-confirm="{{ $remaining > 0 ? 'Attention : il reste '.$fcfa($remaining).' FCFA à encaisser. ' : '' }}Confirmer le départ du client ? La chambre redeviendra libre."
                        data-confirm-button="Oui, valider la sortie"
                        @if ($remaining > 0) data-confirm-danger @endif>
                        @csrf
                        <button type="submit" class="btn btn-warning"><i class="fas fa-sign-out-alt"></i> Sortie</button>
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
                    <dd class="col-7"><x-admin::mode-badge :mode="$affectation->pricing_mode" /> : {{ $affectation->billingLabel() }}</dd>
                    @if ($affectation->hasDiscount())
                        <dt class="col-5">Remise</dt>
                        <dd class="col-7"><span class="text-success fw-semibold">− {{ number_format((float) $affectation->discount_amount, 0, ',', ' ') }} FCFA</span> <span class="small text-muted">({{ $affectation->discountLabel() }}@if ($affectation->discountedBy) · par {{ $affectation->discountedBy->name }}@endif)</span></dd>
                    @endif
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
                    @if ($affectation->perkLabels() !== [])
                        <dt class="col-5">Avantages inclus</dt>
                        <dd class="col-7"><i class="far fa-gift text-muted"></i> {{ implode(', ', $affectation->perkLabels()) }}</dd>
                    @endif
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
                        <li><span>Sortie</span> par <strong>{{ $affectation->checkedOutBy?->name ?? '—' }}</strong> le {{ $affectation->checked_out_at->format('d/m/Y à H\hi') }}</li>
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
                    {{-- Extension at the guest's request, any time (client request, 2026-10-25): + N units
                         of the same formula; the supplement is cashed now or left due. --}}
                    <div class="admin-card" id="prolonger">
                        <h5><i class="far fa-clock text-muted me-1"></i> Prolonger le séjour</h5>
                        <p class="text-muted small">À la demande du client, même avant l'heure de sortie. La formule reste <x-admin::mode-badge :mode="$affectation->pricing_mode" /> ; le supplément peut être encaissé maintenant ou rester dû.</p>
                        <form method="POST" action="{{ route('admin.affectations.extend', $affectation) }}"
                            data-extend-form
                            data-quotes="{{ json_encode((object) $extensionQuotes) }}"
                            data-paid="{{ (float) ($invoice?->amountPaid() ?? 0) }}">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-sm-5">
                                    <label class="form-label" for="extend-extra">Ajouter ({{ $affectation->pricing_mode->unitPlural() }})</label>
                                    <input type="number" min="1" max="{{ max(1, 365 - $affectation->duration) }}" step="1" class="form-control @error('extra') is-invalid @enderror" id="extend-extra" name="extra" value="{{ old('extra', 1) }}" data-extend-extra required>
                                    @error('extra')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-7">
                                    <div class="small text-muted">Sortie actuelle : <strong class="text-body">{{ $affectation->check_out->format('d/m/Y à H\hi') }}</strong></div>
                                </div>
                                @if ($forfaits !== [])
                                    <div class="col-12 form-text mt-1">
                                        <i class="far fa-tags"></i> Forfaits (jour d'entrée) :
                                        {{ collect($forfaits)->map(fn ($price, $units) => $affectation->pricing_mode->durationLabel((int) $units).' = '.number_format((float) $price, 0, ',', ' ').' FCFA')->implode(' · ') }}
                                        — appliqué automatiquement si la durée totale correspond.
                                    </div>
                                @endif
                            </div>
                            <div class="assign-pay mt-3">
                                <div class="assign-pay__total mode--{{ $affectation->pricing_mode->value }}" data-extend-preview>
                                    Nouvelle sortie et supplément calculés à l'enregistrement.
                                </div>
                                @can('payments.create')
                                    <div class="assign-pay__label">Encaissement <span class="text-muted fw-normal">— facultatif : laissez vide pour le régler plus tard</span></div>
                                    @include('admin.payments._lines', ['due' => null, 'required' => false, 'full' => false, 'cashReceivedRequired' => true,
                                        'sharedReceived' => true, 'idPrefix' => 'extend'])
                                @endcan
                            </div>
                            <button type="submit" class="btn btn-primary mt-3"><i class="far fa-clock"></i> Prolonger</button>
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
