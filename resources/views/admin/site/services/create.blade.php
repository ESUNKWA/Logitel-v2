@extends('admin.layouts.admin')

@section('title', 'Nouveau service')

@section('content')
    <div class="admin-page-header"><h1>Nouveau service</h1></div>
    <div class="admin-card">
        <form method="POST" action="{{ route('admin.site.services.store') }}" enctype="multipart/form-data">
            @include('admin.site.services._form')
        </form>
    </div>
@endsection
