@extends('admin.layouts.admin')

@section('title', $booking->exists ? 'Modifier la réservation de salle' : 'Nouvelle réservation de salle')

@section('content')
    @php
        $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $editing = $booking->exists;
        $value = fn (string $key, $default = null) => old($key, $default);
        $venueId = (string) $value('venue_id', $booking->venue_id ?? ($prefill['venue_id'] ?? ''));
        $date = (string) $value('event_date', $booking->start_at?->toDateString() ?? ($prefill['event_date'] ?? ''));
        $formula = (string) $value('formula', $booking->formula ?? \App\Models\VenueBooking::FORMULA_HALF);
        $oldItems = (array) old('items', $quantities);
        $discount = $value('discount_amount', $editing && (float) $booking->sale->discount_amount > 0 ? $fcfa($booking->sale->discount_amount) : '');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-briefcase text-primary me-2"></i>{{ $editing ? 'Modifier la réservation '.$booking->reference() : 'Nouvelle réservation de salle' }}</h1>
            <div class="dash-subtitle">Le prix de la salle dépend de la formule ; les prestations sont celles du catalogue « Réunions et événements ».</div>
        </div>
        <a href="{{ $editing ? route('admin.events.show', $booking) : route('admin.events.index') }}" class="btn btn-outline-secondary">Annuler</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.events.update', $booking) : route('admin.events.store') }}" data-event-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="admin-card">
                    <h5>Salle et créneau</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="venue_id">Salle</label>
                            <select class="form-select @error('venue_id') is-invalid @enderror" id="venue_id" name="venue_id" required data-event-venue>
                                <option value="">Choisir…</option>
                                @foreach ($venues as $venue)
                                    <option value="{{ $venue->id }}" data-half="{{ (float) $venue->half_day_rate }}" data-day="{{ (float) $venue->day_rate }}" data-capacity="{{ $venue->capacity }}" @selected($venueId === (string) $venue->id)>
                                        {{ $venue->name }} — {{ $venue->capacity }} pers.@unless ($venue->is_active) (inactive)@endunless
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Formule</label>
                            <div class="btn-group" role="group" aria-label="Formule">
                                @foreach (\App\Models\VenueBooking::FORMULAS as $key => $label)
                                    <input type="radio" class="btn-check" name="formula" id="formula-{{ $key }}" value="{{ $key }}" data-event-formula @checked($formula === $key)>
                                    <label class="btn btn-outline-primary" for="formula-{{ $key }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            <div class="form-text" data-event-rate></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="event_date">Date</label>
                            <input type="date" class="form-control @error('event_date') is-invalid @enderror" id="event_date" name="event_date" value="{{ $date }}" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="start_time">Début</label>
                            <input type="time" class="form-control @error('start_time') is-invalid @enderror" id="start_time" name="start_time" value="{{ $value('start_time', $booking->start_at?->format('H:i') ?? '08:00') }}" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label" for="end_time">Fin</label>
                            <input type="time" class="form-control @error('end_time') is-invalid @enderror" id="end_time" name="end_time" value="{{ $value('end_time', $booking->end_at?->format('H:i') ?? '13:00') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="participants">Participants</label>
                            <input type="number" class="form-control @error('participants') is-invalid @enderror" id="participants" name="participants" min="1" max="10000" value="{{ $value('participants', $booking->participants ?? '') }}" required data-event-participants>
                            <div class="form-text" data-event-capacity></div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="title">Objet <span class="text-muted fw-normal">(facultatif : séminaire, mariage, formation…)</span></label>
                            <input type="text" class="form-control" id="title" name="title" maxlength="160" value="{{ $value('title', $booking->title) }}">
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <h5>Client</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="client_name">Nom</label>
                            <input type="text" class="form-control @error('client_name') is-invalid @enderror" id="client_name" name="client_name" maxlength="120" value="{{ $value('client_name', $booking->client_name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="client_phone">Téléphone</label>
                            <input type="tel" class="form-control @error('client_phone') is-invalid @enderror" id="client_phone" name="client_phone" maxlength="50" value="{{ $value('client_phone', $booking->client_phone) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="organization">Organisation <span class="text-muted fw-normal">(facultatif)</span></label>
                            <input type="text" class="form-control" id="organization" name="organization" maxlength="160" value="{{ $value('organization', $booking->organization) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="1" maxlength="2000">{{ $value('notes', $booking->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="admin-card">
                    <h5>Prestations</h5>
                    @if ($items->isEmpty())
                        <p class="text-muted small mb-0">Aucune prestation au catalogue « Réunions et événements » (pause-café, déjeuner, matériel…).
                            @can('catalog.manage')<a href="{{ route('admin.catalog.index', ['service' => 'evenements']) }}">Ajoutez-en</a>.@endcan</p>
                    @endif
                    @foreach ($items as $category => $group)
                        @if ($category !== '')
                            <h6 class="text-uppercase text-muted small fw-bold mt-3">{{ $category }}</h6>
                        @endif
                        <div class="sale-items">
                            @foreach ($group as $item)
                                <div class="sale-item">
                                    <label class="sale-item__name" for="item-{{ $item->id }}">{{ $item->name }} <span class="sale-item__price">{{ $fcfa($item->price) }} F</span></label>
                                    <div class="input-group input-group-sm sale-item__qty">
                                        <button type="button" class="btn btn-outline-secondary" data-qty-step="-1" aria-label="Retirer un « {{ $item->name }} »">−</button>
                                        <input type="number" class="form-control text-center" id="item-{{ $item->id }}" name="items[{{ $item->id }}]" min="0" max="{{ \App\Models\Sale::MAX_QUANTITY }}" step="1" inputmode="numeric"
                                            value="{{ (int) ($oldItems[$item->id] ?? 0) }}" data-event-qty data-price="{{ (float) $item->price }}">
                                        <button type="button" class="btn btn-outline-secondary" data-qty-step="1" aria-label="Ajouter un « {{ $item->name }} »">+</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-lg-5">
                <div class="admin-card">
                    <h5>Règlement</h5>
                    @unless ($editing)
                        <div class="mb-3">
                            <label class="form-label d-block">Statut</label>
                            <div class="btn-group" role="group" aria-label="Statut">
                                <input type="radio" class="btn-check" name="status" id="status-option" value="option" @checked(old('status', 'option') === 'option')>
                                <label class="btn btn-outline-primary" for="status-option">Option (devis)</label>
                                <input type="radio" class="btn-check" name="status" id="status-confirmee" value="confirmee" @checked(old('status') === 'confirmee')>
                                <label class="btn btn-outline-primary" for="status-confirmee">Confirmée</label>
                            </div>
                        </div>
                    @endunless
                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label" for="discount_amount">Remise</label>
                            <div class="input-group">
                                <input type="text" inputmode="numeric" data-money class="form-control" id="discount_amount" name="discount_amount" value="{{ $discount }}" placeholder="0" data-event-discount>
                                <span class="input-group-text">F</span>
                            </div>
                        </div>
                        <div class="col-7">
                            <label class="form-label" for="discount_reason">Motif</label>
                            <input type="text" class="form-control" id="discount_reason" name="discount_reason" maxlength="255" value="{{ $value('discount_reason', $booking->sale?->discount_reason) }}">
                        </div>
                    </div>
                    <div class="assign-pay">
                        <div class="assign-pay__total" data-event-total>Total : —</div>
                        @if (! $editing)
                            @can('payments.create')
                                <div class="assign-pay__label">Acompte <span class="text-muted fw-normal">— facultatif : tout, une partie ou rien pour l'instant</span></div>
                                @include('admin.payments._lines', ['due' => null, 'required' => false, 'prefill' => false, 'cashReceivedRequired' => true, 'sharedReceived' => true, 'idPrefix' => 'event'])
                            @endcan
                        @else
                            <p class="small text-muted mb-0">Déjà encaissé : {{ $fcfa($booking->sale->invoice?->amountPaid()) }} F. La suite s'encaisse depuis la facture ; le nouveau total ne peut pas être inférieur à ce montant.</p>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="far fa-check"></i> {{ $editing ? 'Enregistrer les modifications' : 'Enregistrer la réservation' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection

@section('inline-errors', '1')
