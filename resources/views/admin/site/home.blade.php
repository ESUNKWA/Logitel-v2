@extends('admin.layouts.admin')

@section('title', "Site web — Accueil")

@section('content')
    @php
        $v = fn (string $key) => old($key, $content[$key] ?? '');
        $photo = fn (string $key) => ($content[$key] ?? '') !== '' ? asset('storage/'.$content[$key]) : null;
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Page d'accueil du site</h1>
            <div class="dash-subtitle">Ce que voient les visiteurs sur l'accueil du site. <strong>Un champ laissé vide n'est pas affiché</strong> (la section disparaît si elle est vide).</div>
        </div>
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> Voir le site</a>
    </div>

    <form method="POST" action="{{ route('admin.site.home.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="admin-card">
            <h5><i class="far fa-image me-2 text-primary"></i>Bannière (haut de page)</h5>
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label" for="hero_title">Titre</label>
                    <input type="text" class="form-control" id="hero_title" name="hero_title" maxlength="160" value="{{ $v('hero_title') }}" placeholder="Bienvenue à {{ $hotelInfo->name() }}">
                    <div class="form-text">Vide : « Bienvenue à {{ $hotelInfo->name() }} ».</div>
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="hero_text">Texte d'accroche</label>
                    <textarea class="form-control" id="hero_text" name="hero_text" rows="3" maxlength="2000" placeholder="Ex. Des chambres confortables à l'heure, à la journée ou pour la nuit, au cœur de Bouaké.">{{ $v('hero_text') }}</textarea>
                </div>
                <div class="col-md-4">@include('admin.site._photo-field', ['name' => 'hero_photo_1', 'label' => 'Photo de gauche (paysage)', 'url' => $photo('hero_photo_1')])</div>
                <div class="col-md-4">@include('admin.site._photo-field', ['name' => 'hero_photo_2', 'label' => 'Photo de droite (portrait)', 'url' => $photo('hero_photo_2')])</div>
                <div class="col-md-4">@include('admin.site._photo-field', ['name' => 'hero_background', 'label' => 'Image de fond (assombrie)', 'url' => $photo('hero_background'), 'hint' => 'JPG, PNG ou WebP, 2 Mo max. Vide : fond sombre par défaut.'])</div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-hotel me-2 text-primary"></i>Section « À propos »</h5>
            <p class="text-muted small">Présentation de l'hôtel. La section ne s'affiche que si le texte est renseigné.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="about_subtitle">Sur-titre</label>
                    <input type="text" class="form-control" id="about_subtitle" name="about_subtitle" maxlength="160" value="{{ $v('about_subtitle') }}" placeholder="À propos de l'hôtel">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="about_title">Titre</label>
                    <input type="text" class="form-control" id="about_title" name="about_title" maxlength="160" value="{{ $v('about_title') }}">
                </div>
                <div class="col-lg-7">
                    <label class="form-label" for="about_text">Texte</label>
                    <textarea class="form-control" id="about_text" name="about_text" rows="6" maxlength="2000">{{ $v('about_text') }}</textarea>
                </div>
                <div class="col-lg-5">
                    <label class="form-label" for="about_highlights">Points forts <span class="text-muted fw-normal">(un par ligne)</span></label>
                    <textarea class="form-control" id="about_highlights" name="about_highlights" rows="6" maxlength="2000" placeholder="Parking sécurisé&#10;Groupe électrogène&#10;Accueil 24h/24">{{ $v('about_highlights') }}</textarea>
                </div>
                <div class="col-md-6">@include('admin.site._photo-field', ['name' => 'about_photo_1', 'label' => 'Photo principale', 'url' => $photo('about_photo_1')])</div>
                <div class="col-md-6">@include('admin.site._photo-field', ['name' => 'about_photo_2', 'label' => 'Seconde photo', 'url' => $photo('about_photo_2')])</div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-chart-bar me-2 text-primary"></i>Chiffres clés</h5>
            <p class="text-muted small">Jusqu'à {{ \App\Models\SiteContent::STATS }} chiffres réels (ex. « 24 » + « chambres climatisées », « 7j/7 » + « réception ouverte »). Les deux premiers s'affichent aussi sur les photos « À propos ».</p>
            <div class="row g-3">
                @foreach (range(1, \App\Models\SiteContent::STATS) as $i)
                    <div class="col-md-6 col-xl-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="stat_{{ $i }}_value" maxlength="160" value="{{ $v("stat_{$i}_value") }}" placeholder="Chiffre" aria-label="Chiffre {{ $i }}" style="max-width: 90px;">
                            <input type="text" class="form-control" name="stat_{{ $i }}_label" maxlength="160" value="{{ $v("stat_{$i}_label") }}" placeholder="Libellé" aria-label="Libellé {{ $i }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-comment-dots me-2 text-primary"></i>Avis clients</h5>
            <p class="text-muted small">Uniquement de vrais avis, avec l'accord des clients. La section ne s'affiche que s'il y en a au moins un.</p>
            <div class="row g-3">
                @foreach (range(1, \App\Models\SiteContent::TESTIMONIALS) as $i)
                    <div class="col-lg-6">
                        <div class="site-testimonial-field">
                            <div class="row g-2">
                                <div class="col-6"><input type="text" class="form-control form-control-sm" name="testimonial_{{ $i }}_name" maxlength="160" value="{{ $v("testimonial_{$i}_name") }}" placeholder="Nom du client" aria-label="Nom du client {{ $i }}"></div>
                                <div class="col-6"><input type="text" class="form-control form-control-sm" name="testimonial_{{ $i }}_role" maxlength="160" value="{{ $v("testimonial_{$i}_role") }}" placeholder="Ex. Voyageur d'affaires" aria-label="Précision {{ $i }}"></div>
                                <div class="col-12"><textarea class="form-control form-control-sm" name="testimonial_{{ $i }}_text" rows="2" maxlength="2000" placeholder="Avis n°{{ $i }}" aria-label="Avis {{ $i }}">{{ $v("testimonial_{$i}_text") }}</textarea></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer la page d'accueil</button>
    </form>
@endsection
