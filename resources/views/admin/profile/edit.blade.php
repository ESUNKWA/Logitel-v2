@extends('admin.layouts.admin')

@section('title', 'Mon profil')

@section('content')
    <div class="admin-page-header">
        <h1>Mon profil</h1>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-card">
                <h5>Mes informations</h5>
                <form method="POST" action="{{ route('admin.profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="profile-name">Nom</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="profile-name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="profile-email">E-mail de connexion</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="profile-email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="profile-phone">Téléphone de connexion <span class="text-muted fw-normal">(10 chiffres)</span></label>
                        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="profile-phone" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="numeric" maxlength="20" placeholder="07 12 34 56 78" autocomplete="tel">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">L'e-mail ou le téléphone (au moins l'un des deux) permet de vous connecter.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Mot de passe actuel <span class="text-muted fw-normal">(seulement pour changer l'e-mail ou le téléphone)</span></label>
                        <input type="password" class="form-control @error('confirm_password') is-invalid @enderror" id="confirm_password" name="confirm_password" autocomplete="current-password">
                        @error('confirm_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <dl class="row mb-3 small">
                        <dt class="col-5 text-muted fw-normal">Rôle</dt>
                        <dd class="col-7 mb-0">{{ $user->role?->name ?? 'Aucun' }} <span class="text-muted">(modifiable par un responsable, menu Personnel)</span></dd>
                    </dl>
                    <button type="submit" class="btn btn-primary">Enregistrer mes informations</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="admin-card">
                <h5>Changer mon mot de passe</h5>
                @if ($user->must_change_password)
                    <div class="alert alert-warning">Votre mot de passe est provisoire : choisissez-en un nouveau pour accéder à l'application.</div>
                @endif
                <form method="POST" action="{{ route('admin.profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mot de passe actuel</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                        <div class="form-text">4 caractères minimum.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
                </form>
            </div>
        </div>
    </div>
@endsection
