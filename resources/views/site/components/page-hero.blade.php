@props(['title', 'subtitle' => null, 'breadcrumb'])

<section class="page-hero bg_cover p-r z-1" data-src="{{ asset('site/images/stock/page-hero-bg.jpg') }}">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="page-content text-center">
                    <h1>{{ $title }}</h1>
                    @if($subtitle)
                        <h6>{{ $subtitle }}</h6>
                    @endif
                    <ul>
                        <li><a href="{{ route('home') }}">Accueil</a></li>
                        <li>{{ $breadcrumb }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
