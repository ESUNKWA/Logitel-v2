@extends('admin.layouts.admin')

@section('title', 'Personnel')

@section('content')
    <div class="admin-page-header">
        <h1>Personnel</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="far fa-plus"></i> Nouveau compte
        </a>
    </div>

    <div class="admin-card">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th>Téléphone</th>
                    <th>Rôle</th>
                    <th>Statut</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            {{ $user->name }}
                            @if ($user->is($currentUser))
                                <span class="text-muted small">(vous)</span>
                            @endif
                        </td>
                        <td>{{ $user->email ?: '—' }}</td>
                        <td>{{ $user->phone ?: '—' }}</td>
                        <td>{{ $user->role?->name ?? '—' }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge bg-success">Actif</span>
                            @else
                                <span class="badge bg-secondary">Désactivé</span>
                            @endif
                            @if ($user->must_change_password)
                                <span class="badge bg-warning text-dark">Mot de passe provisoire</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($currentUser->canManageUser($user))
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Aucun compte pour l'instant.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
