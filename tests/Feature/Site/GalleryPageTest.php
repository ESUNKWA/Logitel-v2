<?php

namespace Tests\Feature\Site;

use App\Models\GalleryPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /galerie and the home photo strip show only the photos published in the application.
 */
class GalleryPageTest extends TestCase
{
    use RefreshDatabase;

    private function photo(string $path, array $attributes = []): GalleryPhoto
    {
        $photo = new GalleryPhoto(['is_published' => true, ...$attributes]);
        $photo->path = $path;
        $photo->save();

        return $photo;
    }

    public function test_an_empty_gallery_shows_a_notice_and_no_sample_picture(): void
    {
        $this->get('/galerie')->assertOk()
            ->assertSee('Galerie bientôt disponible')
            ->assertDontSee('stock/gallery-1.jpg', false)->assertDontSee('stock/room-3.jpg', false)
            ->assertDontSee('luxivo-instagram-sec', false);

        $this->get('/')->assertOk()
            ->assertDontSee('luxivo-instagram-sec', false)
            ->assertDontSee('instagram.com', false);
    }

    public function test_published_photos_are_shown_in_order_with_captions_and_filters(): void
    {
        $this->photo('site/gallery/b.jpg', ['caption' => 'La piscine', 'category' => 'Piscine', 'position' => 2]);
        $this->photo('site/gallery/a.jpg', ['caption' => 'Chambre double', 'category' => 'Chambres', 'position' => 1]);
        $this->photo('site/gallery/c.jpg', ['category' => ' Chambres ', 'position' => 3]);
        $this->photo('site/gallery/hidden.jpg', ['caption' => 'Brouillon', 'is_published' => false]);

        $this->get('/galerie')->assertOk()
            ->assertSeeInOrder(['/storage/site/gallery/a.jpg', '/storage/site/gallery/b.jpg', '/storage/site/gallery/c.jpg'], false)
            ->assertSee('<figcaption>La piscine</figcaption>', false)
            ->assertSee('data-filter=".cat-chambres"', false)
            ->assertSee('data-filter=".cat-piscine"', false)
            ->assertSee('isotope-filter-item cat-chambres', false)
            ->assertDontSee('hidden.jpg')
            ->assertDontSee('Brouillon')
            ->assertDontSee('stock/gallery-1.jpg', false)->assertDontSee('stock/room-3.jpg', false);
    }

    public function test_a_single_category_shows_no_filter_tabs(): void
    {
        $this->photo('site/gallery/a.jpg', ['category' => 'Chambres']);
        $this->photo('site/gallery/b.jpg');

        $this->get('/galerie')->assertOk()->assertDontSee('filter-nav-items', false);
    }

    public function test_captions_are_escaped(): void
    {
        $this->photo('site/gallery/a.jpg', ['caption' => '<script>alert(1)</script>']);

        $this->get('/galerie')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_home_strip_uses_the_gallery_and_the_real_instagram_link(): void
    {
        $this->photo('site/gallery/a.jpg');

        $this->get('/')->assertOk()
            ->assertSee('/storage/site/gallery/a.jpg', false)
            ->assertSee("Quelques images de l'hôtel", false)
            ->assertDontSee('Suivez-nous sur Instagram')
            ->assertDontSee('https://www.instagram.com/"', false);

        config(['hotel.info.instagram' => 'https://instagram.com/hotel-evasion']);

        $this->get('/')->assertOk()
            ->assertSee('Suivez-nous sur Instagram')
            ->assertSee('https://instagram.com/hotel-evasion', false);
    }
}
