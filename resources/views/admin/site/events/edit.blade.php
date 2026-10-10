@extends('admin.layouts.admin')

@section('title', 'Modifier l\'événement')

@section('content')
    <div class="admin-page-header"><h1>{{ $event->title }}</h1></div>
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.site.events.update', $event) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('admin.site.events._form')
        </form>
    </div>
@endsection
