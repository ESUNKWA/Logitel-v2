@extends('admin.layouts.admin')

@section('title', 'Nouveau rôle')

@section('content')
    <div class="admin-page-header">
        <h1>Nouveau rôle</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @include('admin.roles._form')
        </form>
    </div>
@endsection
