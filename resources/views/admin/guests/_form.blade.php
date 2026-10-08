@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="first_name">Prénom</label>
        <input type="text" class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $guest->first_name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Nom</label>
        <input type="text" class="form-control" id="last_name" name="last_name" value="{{ old('last_name', $guest->last_name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Téléphone</label>
        <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $guest->phone) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">E-mail</label>
        <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $guest->email) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="id_document_type">Type de pièce d'identité</label>
        <select class="form-select" id="id_document_type" name="id_document_type">
            <option value="">—</option>
            @foreach (['cni' => "Carte Nationale d'Identité", 'passeport' => 'Passeport', 'permis' => 'Permis de conduire'] as $value => $label)
                <option value="{{ $value }}" @selected(old('id_document_type', $guest->id_document_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="id_document_number">Numéro de pièce</label>
        <input type="text" class="form-control" id="id_document_number" name="id_document_number" value="{{ old('id_document_number', $guest->id_document_number) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="nationality">Nationalité</label>
        <input type="text" class="form-control" id="nationality" name="nationality" value="{{ old('nationality', $guest->nationality ?? 'Ivoirienne') }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="address">Adresse</label>
        <textarea class="form-control" id="address" name="address" rows="2">{{ old('address', $guest->address) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $guest->notes) }}</textarea>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.guests.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
