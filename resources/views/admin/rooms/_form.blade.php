@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="room_type_id">Type de chambre</label>
        <select class="form-select" id="room_type_id" name="room_type_id" required>
            <option value="">— Choisir —</option>
            @foreach ($roomTypes as $type)
                <option value="{{ $type->id }}" @selected(old('room_type_id', $room->room_type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="number">Numéro</label>
        <input type="text" class="form-control" id="number" name="number" value="{{ old('number', $room->number) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="floor">Étage</label>
        <input type="text" class="form-control" id="floor" name="floor" value="{{ old('floor', $room->floor) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="status">Statut</label>
        <select class="form-select" id="status" name="status" required>
            @foreach (\App\Enums\RoomStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(old('status', $room->status?->value ?? 'disponible') === $status->value)>{{ $status->getLabel() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $room->notes) }}</textarea>
    </div>
</div>

@can('rooms.manage')
    {{-- Public presentation: what the website's room page shows (capacity and price come from the
         room type, photos from the section below). --}}
    @php
        $amenities = collect(old('amenities', $room->amenities?->map->value->all() ?? []));
        $features = old('features', implode("\n", $room->features ?? []));
        $rules = old('rules', implode("\n", $room->rules ?? []));
    @endphp
    <input type="hidden" name="presentation" value="1">
    <div class="room-presentation mt-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h6 class="mb-0">Présentation sur le site web</h6>
                <div class="text-muted small">Affichée sur la page de la chambre du site public. La capacité et le prix viennent du type de chambre{{ $room->roomType ? ' (« '.$room->roomType->name.' » : '.$room->roomType->capacity_adults.' adulte(s), '.$room->roomType->capacity_children.' enfant(s))' : '' }}.</div>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" class="form-check-input" role="switch" id="is_published" name="is_published" value="1" @checked(old('is_published', $room->is_published))>
                <label class="form-check-label fw-semibold" for="is_published">Afficher sur le site</label>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="public_name">Nom affiché</label>
                <input type="text" maxlength="100" class="form-control" id="public_name" name="public_name" value="{{ old('public_name', $room->public_name) }}" placeholder="{{ $room->roomType?->name ?? 'Ex. Chambre Standard' }} (par défaut : le type)">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="tagline">Phrase d'accroche</label>
                <input type="text" maxlength="160" class="form-control" id="tagline" name="tagline" value="{{ old('tagline', $room->tagline) }}" placeholder="Ex. Un excellent choix pour un séjour reposant en famille ou entre amis">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="surface">Surface</label>
                <div class="input-group">
                    <input type="number" min="1" max="2000" class="form-control" id="surface" name="surface" value="{{ old('surface', $room->surface) }}">
                    <span class="input-group-text">m²</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="beds">Nombre de lits</label>
                <input type="number" min="0" max="20" class="form-control" id="beds" name="beds" value="{{ old('beds', $room->beds) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="bathrooms">Salles de bain</label>
                <input type="number" min="0" max="10" class="form-control" id="bathrooms" name="bathrooms" value="{{ old('bathrooms', $room->bathrooms) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description de la chambre</label>
                <textarea class="form-control" id="description" name="description" rows="4" maxlength="5000" placeholder="Décoration, literie, vue, ambiance… (un paragraphe par ligne vide)">{{ old('description', $room->description) }}</textarea>
            </div>

            <div class="col-12">
                <label class="form-label d-block">Services & équipements</label>
                <div class="amenity-grid">
                    @foreach (\App\Enums\RoomAmenity::cases() as $amenity)
                        <input type="checkbox" class="btn-check" name="amenities[]" id="amenity-{{ $amenity->value }}" value="{{ $amenity->value }}" @checked($amenities->contains($amenity->value))>
                        <label class="amenity-chip" for="amenity-{{ $amenity->value }}"><i class="far {{ $amenity->icon() }}"></i> {{ $amenity->getLabel() }}</label>
                    @endforeach
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="features">Caractéristiques de la chambre <span class="text-muted fw-normal">(une par ligne)</span></label>
                <textarea class="form-control" id="features" name="features" rows="5" placeholder="Lits d'appoint pour enfants&#10;Bouilloire / cafetière&#10;Climatisation réglable&#10;Literie haut de gamme">{{ $features }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="rules">Règles de la chambre <span class="text-muted fw-normal">(une par ligne)</span></label>
                <textarea class="form-control" id="rules" name="rules" rows="5" placeholder="Heure d'arrivée : à partir de 15h00&#10;Heure de départ : avant 11h00&#10;Chambre non-fumeur&#10;Ménage quotidien sauf demande contraire">{{ $rules }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="cancellation_policy">Politique d'annulation</label>
                <textarea class="form-control" id="cancellation_policy" name="cancellation_policy" rows="2" maxlength="2000" placeholder="Ex. Annulation gratuite jusqu'à 48 heures avant l'arrivée.">{{ old('cancellation_policy', $room->cancellation_policy) }}</textarea>
            </div>
        </div>
    </div>

    {{-- Photos (max 5, first = cover). New files are downsized and previewed in the browser
         (admin.js, [data-image-uploader]) before upload; existing ones can be ticked for removal. --}}
    <div class="room-photos mt-4" data-image-uploader data-max="{{ \App\Models\RoomImage::MAX_PER_ROOM }}">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
            <div>
                <h6 class="mb-0">Photos de la chambre</h6>
                <div class="text-muted small">Jusqu'à {{ \App\Models\RoomImage::MAX_PER_ROOM }} photos (JPG, PNG ou WebP). La première sert de photo principale. Cliquez sur une photo pour l'agrandir.</div>
            </div>
            <span class="badge bg-light text-dark" data-photo-count></span>
        </div>

        <div class="room-photos__grid" data-photo-grid>
            @foreach ($room->images ?? [] as $image)
                <figure class="room-photo" data-existing-photo>
                    <img src="{{ $image->url() }}" alt="Photo {{ $loop->iteration }} de la chambre {{ $room->number }}" data-preview-src="{{ $image->url() }}">
                    @if ($loop->first)
                        <span class="room-photo__cover">Principale</span>
                    @endif
                    <label class="room-photo__remove" title="Supprimer cette photo à l'enregistrement">
                        <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" data-remove-existing @checked(in_array($image->id, (array) old('remove_images', [])))>
                        <i class="far fa-trash-alt"></i>
                    </label>
                </figure>
            @endforeach

            <label class="room-photo room-photo--add" data-add-tile>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input>
                <i class="far fa-camera"></i>
                <span>Ajouter des photos</span>
            </label>
        </div>
        <div class="small text-muted mt-2" data-photo-status aria-live="polite"></div>
    </div>

    {{-- Lightbox --}}
    <div class="photo-lightbox" data-lightbox hidden>
        <button type="button" class="photo-lightbox__close" data-lightbox-close aria-label="Fermer">&times;</button>
        <img alt="" data-lightbox-img>
    </div>
@endcan

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.rooms.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
