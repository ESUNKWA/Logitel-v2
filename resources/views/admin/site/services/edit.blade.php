@extends('admin.layouts.admin')

@section('title', 'Modifier un service')

@section('content')
    <div class="admin-page-header"><h1>Modifier « {{ $service->title }} »</h1></div>
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.site.services.update', $service) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.site.services._form')
        </form>
    </div>
@endsection
