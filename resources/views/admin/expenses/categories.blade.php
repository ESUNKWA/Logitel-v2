@extends('admin.layouts.admin')

@section('title', 'Catégories de dépenses')
@section('sweetalert-confirm', '1')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-tags text-primary me-2"></i>Catégories de dépenses</h1>
            <div class="dash-subtitle">Une catégorie déjà utilisée ne peut pas être supprimée : désactivez-la pour ne plus la proposer.</div>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary"><i class="far fa-arrow-left"></i> Dépenses</a>
    </div>

    <form method="POST" action="{{ route('admin.expense-categories.store') }}" class="admin-card">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="new-name">Nouvelle catégorie</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="new-name" name="name" maxlength="60" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="new-position">Ordre</label>
                <input type="number" class="form-control" id="new-position" name="position" min="0" max="999" value="{{ old('position', $categories->max('position') + 1) }}">
            </div>
            <div class="col-6 col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="far fa-plus"></i> Ajouter</button>
            </div>
        </div>
    </form>

    <div class="admin-card">
        <table class="table align-middle table-keep">
            <thead>
                <tr><th>Nom</th><th>Ordre</th><th>Active</th><th>Dépenses</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('admin.expense-categories.update', $category) }}" id="cat-{{ $category->id }}">
                                @csrf
                                @method('PUT')
                                <input type="text" class="form-control form-control-sm" name="name" maxlength="60" value="{{ $category->name }}" required aria-label="Nom">
                            </form>
                        </td>
                        <td style="width: 7rem"><input type="number" class="form-control form-control-sm" name="position" min="0" max="999" value="{{ $category->position }}" form="cat-{{ $category->id }}" aria-label="Ordre"></td>
                        <td>
                            <input type="hidden" name="is_active" value="0" form="cat-{{ $category->id }}">
                            <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked($category->is_active) form="cat-{{ $category->id }}" aria-label="Active">
                        </td>
                        <td>{{ $category->expenses_count }}</td>
                        <td class="text-end text-nowrap">
                            <button type="submit" class="btn btn-sm btn-outline-primary" form="cat-{{ $category->id }}"><i class="far fa-save"></i> Enregistrer</button>
                            @if ($category->expenses_count === 0)
                                <form method="POST" action="{{ route('admin.expense-categories.destroy', $category) }}" class="d-inline" data-confirm="Supprimer la catégorie « {{ $category->name }} » ?" data-confirm-danger data-confirm-button="Supprimer">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="far fa-trash-alt"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
