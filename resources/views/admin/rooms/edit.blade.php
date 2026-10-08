@extends('admin.layouts.admin')

@section('title', 'Modifier la chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier la chambre N°{{ $room->number }}</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.rooms.update', $room) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
