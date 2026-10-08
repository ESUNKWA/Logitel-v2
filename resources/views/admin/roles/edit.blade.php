@extends('admin.layouts.admin')

@section('title', 'Modifier le rôle')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier « {{ $role->name }} »</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @method('PUT')
            @include('admin.roles._form')
        </form>
    </div>
@endsection
