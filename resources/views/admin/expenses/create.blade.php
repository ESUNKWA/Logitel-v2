@extends('admin.layouts.admin')

@section('title', 'Nouvelle dépense')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-receipt text-primary me-2"></i>Nouvelle dépense</h1>
            <div class="dash-subtitle">
                @if ($isValidator)
                    Une dépense que vous enregistrez est validée d'office.
                @else
                    Elle sera envoyée au gérant, qui la validera ou la refusera.
                @endif
            </div>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary"><i class="far fa-arrow-left"></i> Dépenses</a>
    </div>

    <form method="POST" action="{{ route('admin.expenses.store') }}" enctype="multipart/form-data" class="admin-card expense-form" data-expense-form>
        @csrf
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <label class="form-label" for="business_date">Date</label>
                <input type="date" class="form-control @error('business_date') is-invalid @enderror" id="business_date" name="business_date" value="{{ old('business_date', $today->format('Y-m-d')) }}" max="{{ $today->format('Y-m-d') }}" required>
                @error('business_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="amount">Montant (FCFA)</label>
                <input type="text" inputmode="numeric" data-money class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" placeholder="Ex. 15 000" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="expense_category_id">Catégorie</label>
                <select class="form-select @error('expense_category_id') is-invalid @enderror" id="expense_category_id" name="expense_category_id" required>
                    <option value="">Choisir…</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) old('expense_category_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label d-block">Payée avec</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (\App\Enums\ExpenseSource::cases() as $s)
                        <input type="radio" class="btn-check" name="source" id="source-{{ $s->value }}" value="{{ $s->value }}" @checked(old('source', 'especes') === $s->value) required>
                        <label class="btn btn-outline-primary btn-sm" for="source-{{ $s->value }}">{{ $s->getLabel() }}</label>
                    @endforeach
                </div>
                <div class="form-text">« Caisse » : l'argent sort de la caisse de la réception et sera déduit à la clôture. « Hors caisse » : payée par le gérant ou la banque.</div>
                @error('source')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="beneficiary">Bénéficiaire <span class="text-muted">(facultatif)</span></label>
                <input type="text" class="form-control @error('beneficiary') is-invalid @enderror" id="beneficiary" name="beneficiary" maxlength="120" value="{{ old('beneficiary') }}" placeholder="Ex. Station Total, plombier…">
                @error('beneficiary')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="description">Description</label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" maxlength="500" value="{{ old('description') }}" placeholder="Ex. Carburant du groupe électrogène" required>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="receipt">Justificatif <span class="text-danger">(obligatoire)</span></label>
                <input type="file" class="form-control @error('receipt') is-invalid @enderror" id="receipt" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required data-receipt-input>
                <div class="form-text">Photo du reçu ou PDF, 2 Mo au maximum (les photos sont réduites automatiquement).</div>
                <div class="small mt-1" data-receipt-status aria-live="polite"></div>
                <img alt="Aperçu du justificatif" class="expense-receipt-preview mt-2" data-receipt-preview hidden>
                @error('receipt')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="far fa-paper-plane"></i> {{ $isValidator ? 'Enregistrer la dépense' : 'Envoyer la demande' }}</button>
            <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
