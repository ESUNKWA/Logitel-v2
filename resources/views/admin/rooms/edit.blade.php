@extends('admin.layouts.admin')

@section('title', 'Modifier la chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier la chambre N°{{ $room->number }}</h1>
        @if ($room->isPubliclyVisible())
            <a href="{{ $room->publicUrl() }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> Voir sur le site</a>
        @elseif ($room->is_published)
            <span class="text-muted small">Cochée « Afficher sur le site », mais son type de chambre est inactif : elle n'apparaît pas.</span>
        @endif
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
