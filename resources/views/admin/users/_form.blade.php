@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Nom complet</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required>
    </div>
    {{-- Login identifiers: an e-mail and/or a 10-digit phone, at least one (either signs in). --}}
    <div class="col-md-6">
        <label class="form-label" for="email">E-mail <span class="text-muted fw-normal">(connexion)</span></label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="off">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Téléphone <span class="text-muted fw-normal">(connexion, 10 chiffres)</span></label>
        <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" inputmode="numeric" maxlength="20" placeholder="07 12 34 56 78" autocomplete="off">
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Renseignez au moins l'e-mail ou le téléphone : l'un ou l'autre permet de se connecter.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="role_id">Rôle</label>
        <select class="form-select" id="role_id" name="role_id" required>
            <option value="">—</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((int) old('role_id', $user->role_id) === $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    @if ($user->exists)
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                <label class="form-check-label" for="is_active">Compte actif</label>
            </div>
        </div>
    @endif

    <div class="col-12">
        <hr>
        <h6 class="mb-1">{{ $user->exists ? 'Réinitialiser le mot de passe' : 'Mot de passe provisoire' }}</h6>
        <p class="text-muted small mb-0">
            @if ($user->exists)
                Laissez vide pour ne pas le changer. Un nouveau mot de passe sera provisoire : la personne devra le changer à sa prochaine connexion, et ses sessions ouvertes seront fermées.
            @else
                La personne devra le changer à sa première connexion. 4 caractères minimum.
            @endif
        </p>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password">Mot de passe</label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" @required(! $user->exists)>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password_confirmation">Confirmer le mot de passe</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" @required(! $user->exists)>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
