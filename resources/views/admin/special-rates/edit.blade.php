@extends('admin.layouts.admin')

@section('title', 'Modifier le forfait')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier le forfait</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.special-rates.update', $specialRate) }}">
            @method('PUT')
            @include('admin.special-rates._form')
        </form>
    </div>
@endsection
