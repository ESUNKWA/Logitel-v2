@extends('layouts.hotel')

@section('title', "Notre équipe - Espace Katioka")

@section('content')

<x-page-hero title="Notre équipe" subtitle="Découvrez les visages qui font Espace Katioka" breadcrumb="Équipe" />

<section class="luxivo-team-sec gray-color pt-115 pb-90">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="section-title text-center mb-60">
                    <span class="line d-block mb-10"><img src="{{ asset('assets/images/home-one/gallery/d-line.png') }}" alt="ligne"></span>
                    <span class="sub-title">L'équipe de l'hôtel</span>
                    <h2>Des professionnels à votre écoute</h2>
                </div>
            </div>
        </div>
        <div class="row">
            @foreach([
                ['img' => 'team-1.jpg', 'name' => 'Awa Traoré', 'role' => 'Directrice Générale'],
                ['img' => 'team-2.jpg', 'name' => 'Kouassi N\'Guessan', 'role' => 'Directeur des opérations'],
                ['img' => 'team-3.jpg', 'name' => 'Fatou Diabaté', 'role' => 'Responsable relations clients'],
                ['img' => 'team-4.jpg', 'name' => 'Yves Kouamé', 'role' => 'Responsable restauration'],
                ['img' => 'team-5.jpg', 'name' => 'Mariam Ouattara', 'role' => 'Chef de réception'],
                ['img' => 'team-6.jpg', 'name' => 'Jonas Kouadio', 'role' => 'Chef cuisinier'],
            ] as $member)
            <div class="col-xl-4 col-md-6 col-sm-12">
                <div class="luxivo-team-item style-one text-center mb-30">
                    <div class="member-inner-content">
                        <div class="hover-shape bg_cover" style="background-image: url({{ asset('assets/images/innerpage/team/shape.png') }});"></div>
                        <div class="member-image">
                            <img src="{{ asset('assets/images/stock/'.$member['img']) }}" alt="{{ $member['name'] }}">
                        </div>
                        <div class="member-info">
                            <h4><a href="{{ route('team.show') }}">{{ $member['name'] }}</a></h4>
                            <span class="position">{{ $member['role'] }}</span>
                            <div class="social-box">
                                <a href="#"><i class="fab fa-facebook-f"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                                <a href="#"><i class="fab fa-instagram"></i></a>
                                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endsection
