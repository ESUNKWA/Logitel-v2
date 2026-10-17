@csrf
@php
    $selectedMode = old('pricing_mode', $specialRate->pricing_mode?->value);
    $selectedTypes = array_map('intval', old('room_type_ids', $specialRate->exists ? $specialRate->roomTypes->pluck('id')->all() : []));
    $selectedDays = array_map('intval', old('days', $specialRate->days ?? []));
    $price = old('forfait_price', $specialRate->price !== null ? number_format((float) $specialRate->price, 0, ',', ' ') : null);
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="name">Nom</label>
        <input type="text" class="form-control" id="name" name="name" maxlength="100" value="{{ old('name', $specialRate->name) }}" placeholder="Ex. Promo 2 heures, Dimanche nuitée…" required>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $specialRate->is_active ?? true))>
            <label class="form-check-label" for="is_active">Forfait actif</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Mode</label>
        <div class="btn-group w-100 booking-modes" role="group">
            @foreach (\App\Enums\PricingMode::cases() as $mode)
                <input type="radio" class="btn-check" name="pricing_mode" id="forfait-mode-{{ $mode->value }}" value="{{ $mode->value }}" @checked($selectedMode === $mode->value) required>
                <label class="btn mode-btn mode--{{ $mode->value }}" for="forfait-mode-{{ $mode->value }}"><i class="fas {{ $mode->icon() }}" aria-hidden="true"></i> {{ $mode->getLabel() }}</label>
            @endforeach
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label" for="duration">Durée du forfait</label>
        <input type="number" min="1" max="365" step="1" class="form-control" id="duration" name="duration" value="{{ old('duration', $specialRate->duration) }}" required>
        <div class="form-text">En heures, journées de {{ config('hotel.day_hours') }} h, nuits ou périodes de {{ config('hotel.stay_min_hours') }} h, selon le mode. Le forfait s'applique à cette durée exacte.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="forfait_price">Prix du forfait (FCFA)</label>
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="forfait_price" name="forfait_price" value="{{ $price }}" required>
        <div class="form-text">Prix total pour toute la durée. Il ne s'applique que s'il est moins cher que le tarif normal.</div>
    </div>
</div>

<h6 class="mt-4 mb-2">Types de chambre</h6>
<div class="form-check mb-2">
    <input type="hidden" name="all_room_types" value="0">
    <input type="checkbox" class="form-check-input" id="all_room_types" name="all_room_types" value="1" @checked(old('all_room_types', $specialRate->all_room_types))>
    <label class="form-check-label" for="all_room_types"><strong>Tous les types</strong> (y compris ceux créés plus tard — la liste ci-dessous est alors ignorée)</label>
</div>
<div class="amenity-grid">
    @forelse ($roomTypes as $type)
        <input type="checkbox" class="btn-check" id="forfait-type-{{ $type->id }}" name="room_type_ids[]" value="{{ $type->id }}" @checked(in_array($type->id, $selectedTypes, true))>
        <label class="amenity-chip" for="forfait-type-{{ $type->id }}"><i class="far fa-layer-group"></i> {{ $type->name }}@unless ($type->is_active) <span class="text-muted">(inactif)</span>@endunless</label>
    @empty
        <p class="text-muted mb-0">Aucun type de chambre : créez-en un d'abord.</p>
    @endforelse
</div>

<h6 class="mt-4 mb-1">Quand ? <span class="text-muted fw-normal small">(facultatif)</span></h6>
<p class="text-muted small mb-2">Le jour compté est celui de l'entrée du client (journée commerciale, à partir de {{ str_replace(':', 'h', config('hotel.business_day_start')) }}). Sans jour coché : tous les jours. Sans dates : sans limite.</p>
<div class="amenity-grid mb-3">
    @foreach (\App\Models\SpecialRate::DAYS as $number => $label)
        <input type="checkbox" class="btn-check" id="forfait-day-{{ $number }}" name="days[]" value="{{ $number }}" @checked(in_array($number, $selectedDays, true))>
        <label class="amenity-chip" for="forfait-day-{{ $number }}">{{ $label }}</label>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="starts_on">Du</label>
        <input type="date" class="form-control" id="starts_on" name="starts_on" value="{{ old('starts_on', $specialRate->starts_on?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ends_on">Au (inclus)</label>
        <input type="date" class="form-control" id="ends_on" name="ends_on" value="{{ old('ends_on', $specialRate->ends_on?->format('Y-m-d')) }}">
    </div>
</div>

<div class="alert alert-light border mt-4 mb-0 small">
    <i class="far fa-info-circle"></i> Le forfait est appliqué automatiquement aux <strong>affectations</strong> (tableau des chambres, demandes clients) et lors d'une prolongation.
    Si plusieurs forfaits conviennent, le moins cher pour le client l'emporte. Les affectations déjà enregistrées gardent leur prix ; les réservations ne sont pas concernées.
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.special-rates.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
