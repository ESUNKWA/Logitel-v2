@extends('admin.layouts.admin')

@section('title', 'Modifier le client')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier « {{ $guest->fullName() }} »</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.guests.update', $guest) }}">
            @method('PUT')
            @include('admin.guests._form')
        </form>
    </div>
@endsection
