@csrf

@php($selected = old('permissions', $role->exists ? $role->permissionValues() : []))

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Nom du rôle</label>
        <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $role->name) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="description">Description</label>
        <input type="text" class="form-control" id="description" name="description" value="{{ old('description', $role->description) }}">
    </div>
</div>

<h6 class="mt-4">Permissions</h6>
<p class="text-muted small">Seules les permissions que vous possédez vous-même peuvent être accordées.</p>

<div class="row g-3">
    @foreach ($groupedPermissions as $group => $permissions)
        <div class="col-md-6 col-xl-4">
            <div class="border rounded p-3 h-100">
                <strong class="d-block mb-2">{{ $group }}</strong>
                @foreach ($permissions as $permission)
                    <div class="form-check">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="perm-{{ $permission->value }}"
                            name="permissions[]"
                            value="{{ $permission->value }}"
                            @checked(in_array($permission->value, (array) $selected, true))
                            @disabled(! in_array($permission->value, $grantable, true))
                        >
                        <label class="form-check-label" for="perm-{{ $permission->value }}">{{ $permission->getLabel() }}</label>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary">Enregistrer</button>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Annuler</a>
</div>
