@extends('admin.layouts.admin')

@section('title', 'Accès refusé')

@section('content')
    <div class="admin-card text-center py-5">
        <h1 class="h3">Accès refusé</h1>
        <p class="text-muted mb-4">Votre rôle ne vous permet pas d'accéder à cette page ou d'effectuer cette action.</p>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">Retour au tableau de bord</a>
    </div>
@endsection
