@extends('admin.layouts.admin')

@section('title', 'Modifier le compte')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier « {{ $user->name }} »</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @method('PUT')
            @include('admin.users._form')
        </form>
    </div>
@endsection
