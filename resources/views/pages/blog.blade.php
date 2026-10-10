@extends('layouts.hotel')

@section('title', "Blog - Hôtel Evasion")

@section('content')

<x-page-hero title="Blog" subtitle="Un blog où luxe, confort et découvertes se rencontrent" breadcrumb="Blog" />

<section class="luxivo-blog-grid-sec pt-120 pb-120">
    <div class="container">
        <div class="row">
            @foreach([
                ['img' => 'gallery-1.jpg', 'title' => "L'expérience de luxe : ce qui rend notre hôtel unique"],
                ['img' => 'about-1.jpg', 'title' => 'Découvrez le charme de notre expérience signature'],
                ['img' => 'gallery-2.jpg', 'title' => "L'histoire d'Hôtel Evasion, où le luxe rencontre l'authenticité"],
                ['img' => 'choose-1.jpg', 'title' => 'Explorez la différence au cœur de notre confort'],
                ['img' => 'gallery-3.jpg', 'title' => 'Une touche au-dessus, les particularités de notre hôtel'],
                ['img' => 'gallery-4.jpg', 'title' => "L'expérience qui donne envie de revenir"],
            ] as $post)
            <div class="col-xl-4 col-md-6">
                <div class="luxivo-blog-post-item style-one mb-30">
                    <div class="post-thumbnail"><img src="{{ asset('assets/images/stock/'.$post['img']) }}" alt="{{ $post['title'] }}"></div>
                    <div class="post-content">
                        <div class="post-meta">
                            <span><i class="far fa-user"></i>Par <a href="#">Admin</a></span>
                            <span><i class="far fa-comments"></i><a href="#">(03) commentaires</a></span>
                        </div>
                        <h4><a href="{{ route('blog.show') }}">{{ $post['title'] }}</a></h4>
                        <a href="{{ route('blog.show') }}" class="theme-btn style-two">Lire la suite<i class="far fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="theme-pagination text-center mt-30">
                    <ul>
                        <li><a href="#"><i class="far fa-arrow-left"></i></a></li>
                        <li><a href="#">01</a></li>
                        <li><a href="#">02</a></li>
                        <li><a href="#">03</a></li>
                        <li><a href="#"><i class="far fa-arrow-right"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
