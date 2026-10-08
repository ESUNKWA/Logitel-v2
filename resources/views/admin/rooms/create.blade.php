@extends('admin.layouts.admin')

@section('title', 'Nouvelle chambre')

@section('content')
    <div class="admin-page-header">
        <h1>Nouvelle chambre</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.rooms.store') }}" enctype="multipart/form-data">
            @include('admin.rooms._form')
        </form>
    </div>
@endsection
