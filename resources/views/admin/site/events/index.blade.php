@extends('admin.layouts.admin')

@section('title', 'Site web — Événements')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1>Événements</h1>
            <div class="dash-subtitle">Annoncés sur le site : bandeau en haut de l'accueil (le plus proche), cartes sur l'accueil et page « Événements ». Un événement passé disparaît du site tout seul.</div>
        </div>
        <div class="d-flex gap-2">
            @if ($upcoming->where('is_published', true)->isNotEmpty())
                <a href="{{ route('events.index') }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> Voir la page</a>
            @endif
            <a href="{{ route('admin.site.events.create') }}" class="btn btn-primary"><i class="far fa-plus"></i> Nouvel événement</a>
        </div>
    </div>

    <div class="admin-card p-0 mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th></th><th>À venir</th><th>Quand</th><th>Sur le site</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @include('admin.site.events._rows', ['events' => $upcoming])
                    @if ($upcoming->isEmpty())
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun événement à venir : le site n'en affiche aucun.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @if ($past->isNotEmpty())
        <h2 class="h6 text-muted text-uppercase mb-2">Passés (plus affichés sur le site)</h2>
        <div class="admin-card p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <tbody>
                        @include('admin.site.events._rows', ['events' => $past])
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
