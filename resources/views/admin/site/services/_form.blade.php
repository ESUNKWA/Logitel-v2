@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="title">Titre <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="120" value="{{ old('title', $service->title) }}" required placeholder="Ex. Restaurant">
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="category">Catégorie <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control" id="category" name="category" maxlength="60" value="{{ old('category', $service->category) }}" placeholder="Ex. Restauration">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="position">Ordre</label>
        <input type="number" min="0" max="999" class="form-control" id="position" name="position" value="{{ old('position', $service->position) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description <span class="text-muted fw-normal">(facultatif)</span></label>
        <textarea class="form-control" id="description" name="description" rows="3" maxlength="1500" placeholder="Ex. Cuisine ivoirienne et internationale, ouvert de 7h à 23h.">{{ old('description', $service->description) }}</textarea>
    </div>
    <div class="col-md-6">
        @include('admin.site._photo-field', ['name' => 'photo', 'label' => 'Photo (facultatif)', 'url' => $service->photoUrl(), 'hint' => 'JPG, PNG ou WebP, 2 Mo max. Avec photo, le service s\'affiche en grande carte.'])
    </div>
    <div class="col-md-6">
        <label class="form-label" for="icon">Icône <span class="text-muted fw-normal">(utilisée sans photo)</span></label>
        <select class="form-select" id="icon" name="icon">
            @foreach (\App\Models\Service::ICONS as $icon => $label)
                <option value="{{ $icon }}" @selected(old('icon', $service->icon ?? 'fa-concierge-bell') === $icon)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_published" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" @checked(old('is_published', $service->is_published))>
            <label class="form-check-label" for="is_published">Publié sur le site</label>
        </div>
        <div class="form-check form-switch">
            <input type="hidden" name="show_on_home" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="show_on_home" name="show_on_home" value="1" @checked(old('show_on_home', $service->show_on_home))>
            <label class="form-check-label" for="show_on_home">Mettre en avant sur l'accueil</label>
        </div>
    </div>
</div>
<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.site.services.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
