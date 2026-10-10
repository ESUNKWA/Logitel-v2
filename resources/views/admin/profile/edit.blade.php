@extends('admin.layouts.admin')

@section('title', 'Mon profil')

@section('content')
    <div class="admin-page-header">
        <h1>Mon profil</h1>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="admin-card">
                <h5>Mon compte</h5>
                <dl class="row mb-0">
                    <dt class="col-5">Nom</dt>
                    <dd class="col-7">{{ $user->name }}</dd>
                    <dt class="col-5">E-mail</dt>
                    <dd class="col-7">{{ $user->email }}</dd>
                    <dt class="col-5">Rôle</dt>
                    <dd class="col-7">{{ $user->role?->name ?? 'Aucun' }}</dd>
                </dl>
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
