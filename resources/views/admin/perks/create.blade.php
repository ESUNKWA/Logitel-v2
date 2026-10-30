@extends('admin.layouts.admin')

@section('title', 'Nouvel avantage')

@section('content')
    <div class="admin-page-header">
        <h1>Nouvel avantage</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.perks.store') }}">
            @include('admin.perks._form')
        </form>
    </div>
@endsection
