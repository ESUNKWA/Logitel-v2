@extends('admin.layouts.admin')

@section('title', 'Modifier l\'avantage')

@section('content')
    <div class="admin-page-header">
        <h1>Modifier l'avantage</h1>
    </div>

    <div class="admin-card">
        <form method="POST" action="{{ route('admin.perks.update', $perk) }}">
            @method('PUT')
            @include('admin.perks._form')
        </form>
    </div>
@endsection
