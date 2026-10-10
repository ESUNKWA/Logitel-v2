{{-- Photo strip (home + gallery): the latest published gallery photos, hidden when there are none.
     Titled « Suivez-nous sur Instagram » only when the hotel's Instagram link is set (Configuration → Hôtel). --}}
@php($stripPhotos = \App\Models\GalleryPhoto::query()->published()->ordered()->limit(10)->get())
@if ($stripPhotos->isNotEmpty())
@php($instagram = $hotelInfo->get('instagram'))
<section class="luxivo-instagram-sec pt-110 pb-120">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="text-box text-center mb-55">
                    @if ($instagram !== '')
                        <h5><a href="{{ $instagram }}" target="_blank" rel="noopener">Suivez-nous sur Instagram</a></h5>
                    @else
                        <h5>Quelques images de l'hôtel</h5>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="gallery-slider" data-aos="fade-up" data-aos-duration="1000">
            @foreach ($stripPhotos as $photo)
            <div class="luxivo-gallery-item style-one">
                <div class="thumbnail">
                    <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?: $hotelInfo->name() }}">
                    <div class="hover-content">
                        @if ($instagram !== '')
                            <a href="{{ $instagram }}" target="_blank" rel="noopener" class="icon-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        @else
                            <a href="{{ route('gallery') }}" class="icon-btn" aria-label="Voir la galerie"><i class="far fa-images"></i></a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
