@extends('admin.layouts.admin')

@section('title', 'Rôles')

@section('content')
    <div class="admin-page-header">
        <h1>Rôles</h1>
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i class="far fa-plus"></i> Nouveau rôle
        </a>
    </div>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Rôle</th>
                    <th>Description</th>
                    <th>Permissions</th>
                    <th>Membres</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>
                            {{ $role->name }}
                            @if ($role->is_system)
                                <span class="badge bg-info">Système</span>
                            @endif
                        </td>
                        <td>{{ $role->description ?: '—' }}</td>
                        <td>{{ $role->is_system ? 'Toutes' : count($role->permissionValues()) }}</td>
                        <td>{{ $role->users_count }}</td>
                        <td class="text-end">
                            @if (\App\Http\Requests\Admin\RoleRequest::actorCanEdit($currentUser, $role))
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                @if ($role->users_count === 0)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('Supprimer ce rôle ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
