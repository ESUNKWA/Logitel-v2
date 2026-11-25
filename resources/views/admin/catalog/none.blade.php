@extends('admin.layouts.admin')

@section('title', 'Catalogue des prestations')

@section('content')
    <div class="admin-page-header">
        <div>
            <h1><i class="far fa-list text-primary me-2"></i>Catalogue des prestations</h1>
        </div>
    </div>
    <div class="alert alert-info">
        L'établissement ne propose aucun service en plus de l'hébergement.
        @can('settings.manage')
            Cochez les services proposés (restauration, plage et piscine, réunions et événements) dans <a href="{{ route('admin.hotel.edit') }}#services">Configuration → Hôtel</a>.
        @endcan
    </div>
@endsection
