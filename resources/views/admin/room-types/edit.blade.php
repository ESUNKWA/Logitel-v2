@extends('admin.layouts.admin')

@section('title', 'Modifier le type de chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier « {{ $roomType->name }} »</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.room-types.update', $roomType) }}">
            @method('PUT')
            @include('admin.room-types._form')
        </form>
    </div>
@endsection
