@csrf
@php
    $selectedTypes = array_map('intval', old('room_type_ids', $perk->exists ? $perk->roomTypes->pluck('id')->all() : []));
    $selectedModes = old('modes', $perk->modes ?? []);
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="label">Texte imprimé sur le reçu</label>
        <input type="text" class="form-control @error('label') is-invalid @enderror" id="label" name="label" maxlength="120" value="{{ old('label', $perk->label) }}" placeholder="Ex. Accès piscine gratuit" required>
        @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label" for="position">Ordre</label>
        <input type="number" min="0" max="999" class="form-control" id="position" name="position" value="{{ old('position', $perk->position ?? 0) }}">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $perk->is_active ?? true))>
            <label class="form-check-label" for="is_active">Actif</label>
        </div>
    </div>
</div>

<h6 class="mt-4 mb-2">Types de chambre</h6>
<div class="form-check mb-2">
    <input type="hidden" name="all_room_types" value="0">
    <input type="checkbox" class="form-check-input" id="all_room_types" name="all_room_types" value="1" @checked(old('all_room_types', $perk->all_room_types))>
    <label class="form-check-label" for="all_room_types"><strong>Tous les types</strong> (y compris ceux créés plus tard — la liste ci-dessous est alors ignorée)</label>
</div>
<div class="amenity-grid">
    @forelse ($roomTypes as $type)
        <input type="checkbox" class="btn-check" id="perk-type-{{ $type->id }}" name="room_type_ids[]" value="{{ $type->id }}" @checked(in_array($type->id, $selectedTypes, true))>
        <label class="amenity-chip" for="perk-type-{{ $type->id }}"><i class="far fa-layer-group"></i> {{ $type->name }}@unless ($type->is_active) <span class="text-muted">(inactif)</span>@endunless</label>
    @empty
        <p class="text-muted mb-0">Aucun type de chambre : créez-en un d'abord.</p>
    @endforelse
</div>
@error('room_type_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

<h6 class="mt-4 mb-1">Formules</h6>
<p class="text-muted small mb-2">Sans formule cochée : toutes les formules.</p>
<div class="amenity-grid">
    @foreach (\App\Enums\PricingMode::cases() as $mode)
        <input type="checkbox" class="btn-check" id="perk-mode-{{ $mode->value }}" name="modes[]" value="{{ $mode->value }}" @checked(in_array($mode->value, $selectedModes, true))>
        <label class="amenity-chip" for="perk-mode-{{ $mode->value }}"><i class="fas {{ $mode->icon() }}"></i> {{ $mode->getLabel() }}</label>
    @endforeach
</div>

<div class="alert alert-light border mt-4 mb-0 small">
    <i class="far fa-info-circle"></i> L'avantage est attaché aux <strong>affectations et réservations</strong> enregistrées ensuite pour ces types et formules, imprimé sur leur reçu et affiché à la réception.
    Sur le site web, la page de la chambre l'affiche s'il concerne la formule <strong>séjour</strong>. Les dossiers déjà enregistrés gardent leurs avantages.
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.perks.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
