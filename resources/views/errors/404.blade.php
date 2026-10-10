@extends('layouts.hotel')

@section('title', "Page introuvable - Hôtel Evation")

@section('content')

<x-page-hero title="404" subtitle="Page introuvable, mais nous allons vous aider à vous y retrouver." breadcrumb="404" />

<section class="luxivo-error-sec pt-120 pb-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="luxivo-content-box text-center">
                    <div class="luxivo-image-box mb-30">
                        <span class="error-404-text">404</span>
                    </div>
                    <h1>Hélas, cette page n'est pas accessible.</h1>
                    <p>Il semble que rien n'ait été trouvé ici. Essayez une recherche ou l'un des liens ci-dessous.</p>
                    <div class="luxivo-button">
                        <a href="{{ route('home') }}" class="theme-btn style-one">Retour à l'accueil</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
