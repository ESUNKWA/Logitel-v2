@extends('admin.layouts.admin')

@section('title', 'Nouvel événement')

@section('content')
    <div class="admin-page-header"><h1>Nouvel événement</h1></div>
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.site.events.store') }}" enctype="multipart/form-data">
            @include('admin.site.events._form')
        </form>
    </div>
@endsection
