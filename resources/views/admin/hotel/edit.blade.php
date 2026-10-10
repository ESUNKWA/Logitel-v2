@extends('admin.layouts.admin')

@section('title', 'Hôtel')

@section('content')
    @php
        $field = fn (string $key) => old('hotel_'.$key, $info[$key] ?? '');
    @endphp

    <div class="admin-page-header">
        <div>
            <h1>Informations de l'hôtel</h1>
            <div class="dash-subtitle">Saisies une seule fois, elles apparaissent sur les reçus, dans l'application, sur les pages clients (/demande) et dans les coordonnées du site web. Un champ laissé vide n'est affiché nulle part.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.hotel.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="admin-card">
            <h5><i class="far fa-hotel me-2 text-primary"></i>Identité</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="hotel_name">Nom de l'hôtel <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('hotel_name') is-invalid @enderror" id="hotel_name" name="hotel_name" maxlength="120" value="{{ $field('name') }}" required>
                    @error('hotel_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hotel_tagline">Slogan</label>
                    <input type="text" class="form-control @error('hotel_tagline') is-invalid @enderror" id="hotel_tagline" name="hotel_tagline" maxlength="160" value="{{ $field('tagline') }}" placeholder="Ex. Confort et hospitalité au cœur de Bouaké">
                    @error('hotel_tagline')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo (PNG, JPG ou WebP, 1 Mo max)</label>
                    <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Conservé dans les informations de l'hôtel (il n'est pas imprimé sur le reçu).</div>
                </div>
                <div class="col-md-6">
                    @if ($hotelInfo->logoUrl())
                        <label class="form-label d-block">Logo actuel</label>
                        <div class="d-flex align-items-center gap-3">
                            <img src="{{ $hotelInfo->logoUrl() }}" alt="Logo actuel" class="hotel-logo-preview">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label" for="remove_logo">Retirer le logo</label>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-secondary mb-0 py-2 mt-md-4"><i class="far fa-image me-1"></i> Aucun logo : le nom de l'hôtel est affiché à la place.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-image me-2 text-primary"></i>Page de connexion</h5>
            <p class="text-muted small">Photo affichée en fond de la page de connexion du personnel (assombrie pour que le formulaire reste lisible). Par défaut, une photo d'hôtel avec piscine ; envoyez une photo de votre établissement pour la remplacer.</p>
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <label class="form-label" for="login_background">Image de fond (JPG, PNG ou WebP, 2 Mo max, 800 × 450 px min.)</label>
                    <input type="file" class="form-control @error('login_background') is-invalid @enderror" id="login_background" name="login_background" accept="image/jpeg,image/png,image/webp">
                    @error('login_background')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Idéalement une photo de l'hôtel en paysage (façade, réception, chambre).</div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-3">
                        <img src="{{ $hotelInfo->loginBackgroundUrl() }}" alt="Image de fond actuelle" class="login-bg-preview">
                        <div>
                            @if ($hotelInfo->hasCustomLoginBackground())
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remove_login_background" value="1" id="remove_login_background">
                                    <label class="form-check-label" for="remove_login_background">Retirer mon image (revenir à l'image par défaut)</label>
                                </div>
                            @else
                                <div class="small text-muted">Image par défaut</div>
                            @endif
                            <a href="{{ route('admin.login') }}" target="_blank" rel="noopener" class="small">Voir la page de connexion</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-phone me-2 text-primary"></i>Contact</h5>
            <div class="row g-3">
                @foreach ([
                    ['phone', 'Téléphone principal', 'tel', '+225 07 00 00 00 00', true],
                    ['phone2', 'Deuxième téléphone', 'tel', '+225 27 00 00 00 00', false],
                    ['whatsapp', 'WhatsApp', 'tel', '+225 07 00 00 00 00', false],
                    ['email', 'E-mail', 'email', 'contact@…', false],
                    ['website', 'Site web', 'text', 'www.…', false],
                ] as [$key, $label, $type, $placeholder, $required])
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="hotel_{{ $key }}">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                        <input type="{{ $type }}" class="form-control @error('hotel_'.$key) is-invalid @enderror" id="hotel_{{ $key }}" name="hotel_{{ $key }}" value="{{ $field($key) }}" placeholder="{{ $placeholder }}" @required($required)>
                        @error('hotel_'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-map-marker-alt me-2 text-primary"></i>Adresse</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="hotel_address">Adresse (quartier, rue, repère)</label>
                    <input type="text" class="form-control @error('hotel_address') is-invalid @enderror" id="hotel_address" name="hotel_address" maxlength="200" value="{{ $field('address') }}" placeholder="Ex. Quartier Commerce, près de …">
                    @error('hotel_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hotel_city">Ville</label>
                    <input type="text" class="form-control @error('hotel_city') is-invalid @enderror" id="hotel_city" name="hotel_city" maxlength="80" value="{{ $field('city') }}">
                    @error('hotel_city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="hotel_country">Pays</label>
                    <input type="text" class="form-control @error('hotel_country') is-invalid @enderror" id="hotel_country" name="hotel_country" maxlength="80" value="{{ $field('country') }}">
                    @error('hotel_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-balance-scale me-2 text-primary"></i>Mentions légales</h5>
            <p class="text-muted small">Imprimées en en-tête du reçu.</p>
            <div class="row g-3">
                @foreach ([
                    ['rccm', 'RCCM', 'CI-BKE-2026-B-…', 60],
                    ['ncc', 'NCC (compte contribuable)', '…', 60],
                    ['tax_regime', 'Régime fiscal', 'Ex. Taxe synthétique', 80],
                    ['legal_other', 'Autres mentions', '', 200],
                ] as [$key, $label, $placeholder, $max])
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="hotel_{{ $key }}">{{ $label }}</label>
                        <input type="text" class="form-control @error('hotel_'.$key) is-invalid @enderror" id="hotel_{{ $key }}" name="hotel_{{ $key }}" maxlength="{{ $max }}" value="{{ $field($key) }}" placeholder="{{ $placeholder }}">
                        @error('hotel_'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="admin-card">
            <h5><i class="far fa-share-alt me-2 text-primary"></i>Réseaux sociaux</h5>
            <p class="text-muted small">Affichés sur le site web (le lien WhatsApp est créé à partir du numéro ci-dessus).</p>
            <div class="row g-3">
                @foreach ([['facebook', 'Page Facebook', 'facebook.com/…'], ['instagram', 'Compte Instagram', 'instagram.com/…']] as [$key, $label, $placeholder])
                    <div class="col-md-6">
                        <label class="form-label" for="hotel_{{ $key }}">{{ $label }}</label>
                        <input type="text" class="form-control @error('hotel_'.$key) is-invalid @enderror" id="hotel_{{ $key }}" name="hotel_{{ $key }}" maxlength="200" value="{{ $field($key) }}" placeholder="{{ $placeholder }}">
                        @error('hotel_'.$key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer les informations</button>
    </form>
@endsection
