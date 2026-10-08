@extends('admin.layouts.admin')

@section('title', 'Nouveau type de chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Nouveau type de chambre</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.room-types.store') }}">
            @include('admin.room-types._form')
        </form>
    </div>
@endsection
