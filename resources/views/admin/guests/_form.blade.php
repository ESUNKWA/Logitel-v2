@csrf

@php($clientType = old('type', $guest->type ?? \App\Models\Guest::TYPE_PERSON))
<div class="row g-3" data-client-form>
    <div class="col-12">
        <label class="form-label d-block">Type de client</label>
        <div class="btn-group" role="group" aria-label="Type de client">
            <input type="radio" class="btn-check" name="type" id="type-physique" value="physique" data-client-type @checked($clientType !== 'morale')>
            <label class="btn btn-outline-primary" for="type-physique"><i class="far fa-user me-1"></i> Particulier (personne physique)</label>
            <input type="radio" class="btn-check" name="type" id="type-morale" value="morale" data-client-type @checked($clientType === 'morale')>
            <label class="btn btn-outline-primary" for="type-morale"><i class="far fa-building me-1"></i> Entreprise (personne morale)</label>
        </div>
    </div>
    <div class="col-md-6" data-client-only="morale">
        <label class="form-label" for="company_name">Raison sociale</label>
        <input type="text" class="form-control @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name', $guest->company_name) }}" maxlength="255" placeholder="Ex. SARL Transports du Centre">
    </div>
    <div class="col-md-6" data-client-only="morale">
        <label class="form-label" for="tax_id">N° RCCM / NCC</label>
        <input type="text" class="form-control @error('tax_id') is-invalid @enderror" id="tax_id" name="tax_id" value="{{ old('tax_id', $guest->tax_id) }}" maxlength="100">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="first_name">Prénom <span class="text-muted fw-normal" data-client-only="morale">du contact</span> <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $guest->first_name) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Nom <span class="text-muted fw-normal" data-client-only="morale">du contact</span> <span class="text-muted fw-normal">(facultatif)</span></label>
        <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $guest->last_name) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Téléphone <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $guest->phone) }}" required>
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
