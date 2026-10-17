@extends('admin.layouts.admin')

@section('title', 'Nouveau forfait')

@section('content')
    <div class="admin-page-header">
        <h1>Nouveau forfait</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.special-rates.store') }}">
            @include('admin.special-rates._form')
        </form>
    </div>
@endsection
