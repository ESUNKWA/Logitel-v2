@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Nom</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $roomType->name) }}" required>
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $roomType->is_active ?? true))>
            <label class="form-check-label" for="is_active">Type actif</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $roomType->description) }}</textarea>
    </div>
</div>

<h6 class="mt-4 mb-1">Tarifs (FCFA)</h6>
<p class="text-muted small">Tous facultatifs : laissez vide une formule qui n'est pas proposée pour ce type de chambre.</p>

<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <label class="form-label" for="hourly_rate">Tarif à l'heure</label>
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="hourly_rate" name="hourly_rate" value="{{ old('hourly_rate', $roomType->hourly_rate !== null ? number_format((float) $roomType->hourly_rate, 0, ',', ' ') : null) }}">
        <div class="form-text">Prix d'une heure.</div>
    </div>
    <div class="col-md-6 col-xl-3">
        <label class="form-label" for="day_rate">Tarif journée</label>
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="day_rate" name="day_rate" value="{{ old('day_rate', $roomType->day_rate !== null ? number_format((float) $roomType->day_rate, 0, ',', ' ') : null) }}">
        <div class="form-text">Forfait de {{ config('hotel.day_hours') }} h (ex. entrée 8h00, sortie {{ \Illuminate\Support\Carbon::createFromTime(8)->addHours((int) config('hotel.day_hours'))->format('H\hi') }}).</div>
    </div>
    <div class="col-md-6 col-xl-3">
        <label class="form-label" for="night_rate">Tarif nuitée</label>
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="night_rate" name="night_rate" value="{{ old('night_rate', $roomType->night_rate !== null ? number_format((float) $roomType->night_rate, 0, ',', ' ') : null) }}">
        <div class="form-text">De {{ str_replace(':', 'h', config('hotel.night.from')) }} à {{ str_replace(':', 'h', config('hotel.night.until')) }} le lendemain.</div>
    </div>
    <div class="col-md-6 col-xl-3">
        <label class="form-label" for="stay_rate">Tarif séjour</label>
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="stay_rate" name="stay_rate" value="{{ old('stay_rate', $roomType->stay_rate !== null ? number_format((float) $roomType->stay_rate, 0, ',', ' ') : null) }}">
        <div class="form-text">Par période de {{ config('hotel.stay_min_hours') }} h (séjour d'au moins {{ config('hotel.stay_min_hours') }} h).</div>
    </div>
</div>

<h6 class="mt-4">Capacité</h6>

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="capacity_adults">Adultes</label>
        <input type="number" min="1" class="form-control" id="capacity_adults" name="capacity_adults" value="{{ old('capacity_adults', $roomType->capacity_adults ?? 2) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="capacity_children">Enfants</label>
        <input type="number" min="0" class="form-control" id="capacity_children" name="capacity_children" value="{{ old('capacity_children', $roomType->capacity_children ?? 0) }}" required>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.room-types.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
