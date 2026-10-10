@extends('admin.layouts.admin')

@section('title', 'Site web — Services')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Services de l'hôtel</h1>
            <div class="dash-subtitle">Affichés sur la page « Services » du site (avec photo : grande carte ; sans photo : vignette avec icône) et, si cochés, sur l'accueil.</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('services.index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> Voir la page</a>
            <a href="{{ route('admin.site.services.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouveau service</a>
        </div>
    </div>

    <div class="admin-card p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Ordre</th><th></th><th>Service</th><th>Sur le site</th><th>Accueil</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td>{{ $service->position }}</td>
                            <td>
                                @if ($service->photoUrl())
                                    <img src="{{ $service->photoUrl() }}" alt="" class="service-thumb">
                                @else
                                    <span class="service-icon-thumb"><i class="fas {{ $service->iconClass() }}"></i></span>
                                @endif
                            </td>
                            <td><strong>{{ $service->title }}</strong>@if ($service->category)<div class="small text-muted">{{ $service->category }}</div>@endif</td>
                            <td>{!! $service->is_published ? '<span class="badge bg-success">Publié</span>' : '<span class="badge bg-secondary">Masqué</span>' !!}</td>
                            <td>{!! $service->show_on_home ? '<i class="fas fa-check text-success" title="Affiché sur l\'accueil"></i>' : '—' !!}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.site.services.edit', $service) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                <form method="POST" action="{{ route('admin.site.services.destroy', $service) }}" class="d-inline" onsubmit="return confirm('Supprimer ce service ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun service pour l'instant. La page « Services » du site affiche un message d'attente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
