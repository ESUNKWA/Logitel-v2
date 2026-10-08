@extends('admin.layouts.admin')

@section('title', 'Nouveau client')

@section('content')
    <div class="admin-page-header">
        <h1>Nouveau client</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.guests.store') }}">
            @include('admin.guests._form')
        </form>
    </div>
@endsection
