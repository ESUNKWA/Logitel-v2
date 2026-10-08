@extends('admin.layouts.admin')

@section('title', 'Nouvelle réservation')

@section('content')
    <div class="admin-page-header">
        <h1>Nouvelle réservation</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.reservations.store') }}">
            @include('admin.reservations._form')
        </form>
    </div>
@endsection
