@csrf
<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="title">Titre <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="160" value="{{ old('title', $event->title) }}" required placeholder="Ex. Soirée karaoké, Brunch du dimanche">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="starts_at">Début <span class="text-danger">*</span></label>
        <input type="datetime-local" class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" name="starts_at" value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}" required>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ends_at">Fin <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="datetime-local" class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" name="ends_at" value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}">
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Sans fin, l'événement reste affiché jusqu'au soir du jour même.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="location">Lieu <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control" id="location" name="location" maxlength="120" value="{{ old('location', $event->location) }}" placeholder="Ex. Restaurant, Bord de piscine">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description <span class="text-muted fw-normal">(facultatif)</span></label>
        <textarea class="form-control" id="description" name="description" rows="4" maxlength="2000" placeholder="Programme, artistes, menu…">{{ old('description', $event->description) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="price_label">Prix <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control" id="price_label" name="price_label" maxlength="80" value="{{ old('price_label', $event->price_label) }}" placeholder="Ex. Entrée libre, 10 000 FCFA">
    </div>
    <div class="col-md-8">
        <label class="form-label" for="booking_info">Réservation / inscription <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control @error('booking_info') is-invalid @enderror" id="booking_info" name="booking_info" maxlength="255" value="{{ old('booking_info', $event->booking_info) }}" placeholder="Un lien (https://…) ou un numéro de téléphone">
        @error('booking_info')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        @include('admin.site._photo-field', ['name' => 'photo', 'label' => 'Affiche ou photo (facultatif)', 'url' => $event->photoUrl(), 'hint' => 'JPG, PNG ou WebP, 2 Mo max.'])
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch">
            <input type="hidden" name="is_published" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" @checked(old('is_published', $event->is_published))>
            <label class="form-check-label" for="is_published">Publié sur le site</label>
        </div>
    </div>
</div>
<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.site.events.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
