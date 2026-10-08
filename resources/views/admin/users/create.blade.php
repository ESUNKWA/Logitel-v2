@extends('admin.layouts.admin')

@section('title', 'Nouveau compte')

@section('content')
    <div class="admin-page-header">
        <h1>Nouveau compte</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @include('admin.users._form')
        </form>
    </div>
@endsection
