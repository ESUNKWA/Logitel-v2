@extends('admin.layouts.admin')

@section('title', 'Demande N°'.$stayRequest->id)

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        [$statusLabel, $statusColor] = $stayRequest->displayStatus();
        $room = $stayRequest->room;
        $type = $room?->roomType;
        $nightOpen = \App\Enums\PricingMode::Nuitee->canStartAt(now());
        $selectedMode = old('pricing_mode', $stayRequest->pricing_mode->value);
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Demande N°{{ $stayRequest->id }} — Chambre N°{{ $room?->number }}</h1>
            <div class="dash-subtitle">
                <span class="badge bg-{{ $statusColor }}">{{ $statusLabel }}</span>
                envoyée par le client le {{ $stayRequest->created_at->format('d/m/Y à H\hi') }}
                @if ($stayRequest->isPending() && ! $stayRequest->isExpired())
                    · chambre retenue jusqu'à <strong>{{ $stayRequest->expires_at->format('H\hi') }}</strong>
                @endif
            </div>
        </div>
        <a href="{{ route('admin.stay-requests.index') }}" class="btn btn-outline-secondary"><i class="far fa-arrow-left"></i> Demandes</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="admin-card">
                <h5>Demande du client</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Client</dt>
                    <dd class="col-7">{{ $stayRequest->fullName() }}</dd>
                    <dt class="col-5">Téléphone</dt>
                    <dd class="col-7">{{ $stayRequest->phone ?: '—' }}</dd>
                    <dt class="col-5">Personnes</dt>
                    <dd class="col-7">{{ $stayRequest->adults }} adulte(s), {{ $stayRequest->children }} enfant(s)</dd>
                    <dt class="col-5">Chambre</dt>
                    <dd class="col-7">N°{{ $room?->number }} — {{ $type?->name }}</dd>
                    <dt class="col-5">Formule choisie</dt>
                    <dd class="col-7">{{ $stayRequest->pricing_mode->getLabel() }} : {{ $stayRequest->billingLabel() }}</dd>
                    <dt class="col-5">Total annoncé</dt>
                    <dd class="col-7"><strong>{{ $fcfa($stayRequest->total_amount) }} FCFA</strong></dd>
                </dl>
            </div>

            @unless ($stayRequest->isPending())
                <div class="admin-card">
                    <h5>Traitement</h5>
                    <dl class="row mb-0">
                        <dt class="col-5">Par</dt>
                        <dd class="col-7">{{ $stayRequest->handler?->name ?? '—' }}</dd>
                        <dt class="col-5">Le</dt>
                        <dd class="col-7">{{ $stayRequest->handled_at?->format('d/m/Y à H\hi') ?? '—' }}</dd>
                        @if ($stayRequest->refusal_reason)
                            <dt class="col-5">Motif</dt>
                            <dd class="col-7">{{ $stayRequest->refusal_reason }}</dd>
                        @endif
                    </dl>
                    @if ($stayRequest->reservation)
                        <a href="{{ route('admin.affectations.show', $stayRequest->reservation) }}" class="btn btn-sm btn-outline-primary mt-3">Voir l'affectation {{ $stayRequest->reservation->reference() }}</a>
                    @endif
                </div>
            @endunless

            @if ($canHandle)
                <div class="admin-card">
                    <h5>Refuser la demande</h5>
                    <form method="POST" action="{{ route('admin.stay-requests.refuse', $stayRequest) }}" onsubmit="return confirm('Refuser cette demande ? La chambre sera libérée.');">
                        @csrf
                        <label class="form-label" for="refusal_reason">Motif (affiché au client, facultatif)</label>
                        <input type="text" class="form-control mb-2" id="refusal_reason" name="refusal_reason" maxlength="255" value="{{ old('refusal_reason') }}" placeholder="Ex. client non présenté à la réception">
                        <button type="submit" class="btn btn-outline-danger"><i class="far fa-times"></i> Refuser et libérer la chambre</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            @if ($canApprove && $type)
                <form method="POST" action="{{ route('admin.stay-requests.approve', $stayRequest) }}" class="admin-card"
                    data-stay-standalone
                    data-hourly-rate="{{ $type->hourly_rate }}"
                    data-day-rate="{{ $type->day_rate }}"
                    data-night-rate="{{ $type->night_rate }}"
                    data-stay-rate="{{ $type->stay_rate }}"
                >
                    @csrf
                    <h5>Valider et encaisser</h5>
                    <p class="text-muted small">Le client est à la réception : vérifiez sa pièce d'identité, ajustez si besoin la formule, puis encaissez la totalité. L'entrée compte à partir de la validation.</p>

                    @if ($roomProblem)
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i> {{ $roomProblem }}</div>
                    @endif

                    <div class="mb-3">
                        @if ($matchingGuest)
                            <div class="alert alert-secondary py-2 mb-2">
                                <i class="far fa-user-check me-1"></i>
                                Client déjà connu avec ce numéro : <strong>{{ $matchingGuest->fullName() }}</strong> ({{ $matchingGuest->phone }}). Le séjour sera rattaché à sa fiche.
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="new_guest" value="1" id="new_guest" @checked(old('new_guest'))>
                                <label class="form-check-label" for="new_guest">Ce n'est pas la même personne : créer une nouvelle fiche client</label>
                            </div>
                        @else
                            <div class="alert alert-secondary py-2 mb-0"><i class="far fa-user-plus me-1"></i> Nouveau client : sa fiche sera créée à la validation.</div>
                        @endif
                    </div>

                    <div
                        class="row g-3"
                        data-stay-form
                        data-night-from="{{ config('hotel.night.from') }}"
                        data-night-until="{{ config('hotel.night.until') }}"
                        data-stay-hours="{{ config('hotel.stay_min_hours') }}"
                        data-day-hours="{{ config('hotel.day_hours') }}"
                        data-night-open="{{ $nightOpen ? '1' : '0' }}"
                    >
                        <div class="col-12">
                            <label class="form-label d-block">Mode</label>
                            <div class="btn-group w-100" role="group">
                                @foreach (\App\Enums\PricingMode::cases() as $mode)
                                    <input type="radio" class="btn-check" name="pricing_mode" id="mode-{{ $mode->value }}" value="{{ $mode->value }}" data-mode-option
                                        @checked($selectedMode === $mode->value)
                                        @disabled($mode->rateFor($type) === null || ($mode === \App\Enums\PricingMode::Nuitee && ! $nightOpen))>
                                    <label class="btn btn-outline-primary" for="mode-{{ $mode->value }}">{{ $mode->getLabel() }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="assign-duration" data-duration-label>Durée</label>
                            <input type="number" min="1" max="365" step="1" class="form-control" id="assign-duration" name="duration" value="{{ old('duration', $stayRequest->duration) }}" data-duration-input required>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="adults">Adultes</label>
                            <input type="number" min="1" class="form-control" id="adults" name="adults" value="{{ old('adults', $stayRequest->adults) }}" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="children">Enfants</label>
                            <input type="number" min="0" class="form-control" id="children" name="children" value="{{ old('children', $stayRequest->children) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="assign-rate">Tarif <span data-rate-unit></span> (FCFA)</label>
                            <input type="text" class="form-control rate-locked" id="assign-rate" value="{{ number_format((float) $stayRequest->pricing_mode->rateFor($type), 0, ',', ' ') }}" data-money data-unit-rate-input readonly tabindex="-1">
                            <div class="form-text">Tarif du type de chambre (non modifiable). Pour une réduction, utilisez la remise.</div>
                        </div>
                        {{-- « Règlement », in the order it's typed: discount, total to pay, encaissement. --}}
                        <div class="col-12">
                            <div class="assign-pay">
                                <h6 class="text-uppercase text-muted small fw-bold mb-3">Règlement</h6>
                                <div class="assign-pay__total" data-stay-preview>Choisissez un mode.</div>
                                <div class="assign-pay__label">Encaissement <span class="text-muted fw-normal">— totalité du séjour, en un ou plusieurs modes</span></div>
                                @include('admin.payments._lines', ['due' => null, 'required' => true, 'full' => true, 'cashReceivedRequired' => true,
                                    'sharedReceived' => true, 'idPrefix' => 'approve',
                                    'afterActions' => view('admin.partials._discount-fields', ['idPrefix' => 'approve'])->render()])
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check"></i> Valider l'affectation et encaisser</button>
                    </div>
                </form>
            @elseif ($canHandle)
                <div class="admin-card">
                    <div class="alert alert-warning mb-0">Valider une demande implique d'encaisser le séjour : votre compte n'a pas le droit d'enregistrer un paiement. Faites appel à un collègue habilité.</div>
                </div>
            @endif
        </div>
    </div>
@endsection
