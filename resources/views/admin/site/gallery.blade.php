@extends('admin.layouts.admin')

@section('title', 'Site web — Galerie')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Galerie photos</h1>
            <div class="dash-subtitle">Affichées sur la page « Galerie » du site (onglets par catégorie) et dans le bandeau photos de l'accueil. {{ $photos->count() }} / {{ \App\Models\GalleryPhoto::MAX_TOTAL }} photos.</div>
        </div>
        <a href="{{ route('gallery') }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> Voir la page</a>
    </div>

    {{-- Upload: pictures are downsized in the browser (admin.js, [data-image-uploader]) before sending. --}}
    <div class="admin-card mb-4">
        @if ($remaining > 0)
            <form method="POST" action="{{ route('admin.site.gallery.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="room-photos" data-image-uploader data-max="{{ min($remaining, \App\Models\GalleryPhoto::MAX_PER_UPLOAD) }}" data-limit-label="par envoi">
                    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
                        <div>
                            <h6 class="mb-0">Ajouter des photos</h6>
                            <div class="text-muted small">JPG, PNG ou WebP, jusqu'à {{ \App\Models\GalleryPhoto::MAX_PER_UPLOAD }} par envoi. Elles sont publiées aussitôt ; vous pouvez ensuite les légender, les classer ou les masquer.</div>
                        </div>
                        <span class="badge bg-light text-dark" data-photo-count></span>
                    </div>
                    <div class="room-photos__grid" data-photo-grid>
                        <label class="room-photo room-photo--add" data-add-tile>
                            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input>
                            <i class="far fa-camera"></i>
                            <span>Choisir des photos</span>
                        </label>
                    </div>
                    <div class="small text-muted mt-2" data-photo-status aria-live="polite"></div>
                </div>
                <div class="row g-2 align-items-end mt-2">
                    <div class="col-sm-6 col-lg-4">
                        <label class="form-label" for="category">Catégorie (facultatif)</label>
                        <input type="text" class="form-control" id="category" name="category" maxlength="60" list="gallery-categories" value="{{ old('category') }}" placeholder="Ex. Chambres, Restaurant, Piscine">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="far fa-upload"></i> Envoyer</button>
                    </div>
                </div>
            </form>
        @else
            <div class="text-muted"><i class="far fa-info-circle"></i> La galerie est complète ({{ \App\Models\GalleryPhoto::MAX_TOTAL }} photos) : supprimez-en pour en ajouter.</div>
        @endif
    </div>

    <datalist id="gallery-categories">
        @foreach ($categories as $category)
            <option value="{{ $category }}">
        @endforeach
    </datalist>

    @if ($photos->isEmpty())
        <div class="admin-card text-center text-muted py-5">Aucune photo pour l'instant. La page « Galerie » du site affiche un message d'attente.</div>
    @else
        <form method="POST" action="{{ route('admin.site.gallery.update') }}" id="gallery-form">
            @csrf
            @method('PUT')
        </form>
        <div class="gallery-admin-grid">
            @foreach ($photos as $photo)
                <div class="gallery-admin-item {{ $photo->is_published ? '' : 'is-hidden' }}">
                    <a href="{{ $photo->url() }}" target="_blank" rel="noopener" class="gallery-admin-item__img"><img src="{{ $photo->url() }}" alt="{{ $photo->caption }}" loading="lazy"></a>
                    <div class="gallery-admin-item__fields">
                        <input type="text" class="form-control form-control-sm" form="gallery-form" name="photos[{{ $photo->id }}][caption]" maxlength="160" value="{{ old("photos.{$photo->id}.caption", $photo->caption) }}" placeholder="Légende" aria-label="Légende">
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" form="gallery-form" name="photos[{{ $photo->id }}][category]" maxlength="60" list="gallery-categories" value="{{ old("photos.{$photo->id}.category", $photo->category) }}" placeholder="Catégorie" aria-label="Catégorie">
                            <input type="number" class="form-control form-control-sm gallery-admin-item__order" form="gallery-form" name="photos[{{ $photo->id }}][position]" min="0" max="999" value="{{ old("photos.{$photo->id}.position", $photo->position) }}" title="Ordre d'affichage" aria-label="Ordre">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" form="gallery-form" name="photos[{{ $photo->id }}][is_published]" value="0">
                                <input class="form-check-input" type="checkbox" form="gallery-form" id="pub-{{ $photo->id }}" name="photos[{{ $photo->id }}][is_published]" value="1" @checked(old("photos.{$photo->id}.is_published", $photo->is_published))>
                                <label class="form-check-label small" for="pub-{{ $photo->id }}">Publiée</label>
                            </div>
                            <form method="POST" action="{{ route('admin.site.gallery.destroy', $photo) }}" onsubmit="return confirm('Supprimer définitivement cette photo ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="far fa-trash-alt"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="gallery-admin-save">
            <button type="submit" form="gallery-form" class="btn btn-primary"><i class="far fa-save"></i> Enregistrer les modifications</button>
        </div>
    @endif
@endsection
