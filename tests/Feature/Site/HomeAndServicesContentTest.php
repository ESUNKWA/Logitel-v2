<?php

namespace Tests\Feature\Site;

use App\Models\Service;
use App\Models\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home page and /services show only what the application publishes — never sample content.
 */
class HomeAndServicesContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_home_page_shows_no_sample_content(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Bienvenue à')
            ->assertSee('Une question sur nos chambres ?')
            ->assertDontSee('Notre hôtel & resort de luxe', false)
            ->assertDontSee('100k+')
            ->assertDontSee('8k+')
            ->assertDontSee('luxivo-about_one', false)
            ->assertDontSee('luxivo-counter_one', false)
            ->assertDontSee('luxivo-testimonial-one', false)
            ->assertDontSee('site-services', false)
            ->assertDontSee('hero-image_one', false);
    }

    public function test_the_home_page_shows_the_managed_texts_figures_and_testimonials(): void
    {
        SiteContent::store([
            'hero_title' => 'Votre pause à Bouaké',
            'hero_text' => 'Calme et confort.',
            'hero_photo_1' => 'site/h1.jpg',
            'about_subtitle' => 'Notre maison',
            'about_title' => 'Un hôtel familial',
            'about_text' => "Premier paragraphe.\n\nSecond paragraphe.",
            'about_highlights' => "Wi-Fi gratuit\n\nParking surveillé\n",
            'stat_1_value' => '25', 'stat_1_label' => 'Chambres',
            'stat_2_value' => '10 ans', 'stat_2_label' => '', // incomplete: hidden
            'testimonial_1_name' => 'Awa K.', 'testimonial_1_role' => 'Cliente', 'testimonial_1_text' => 'Très bon accueil.',
            'testimonial_2_name' => '', 'testimonial_2_text' => 'Sans nom : masqué.',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Votre pause à Bouaké')
            ->assertSee('Calme et confort.')
            ->assertSee('/storage/site/h1.jpg', false)
            ->assertSee('Un hôtel familial')
            ->assertSee('<p>Premier paragraphe.</p>', false)
            ->assertSee('<p>Second paragraphe.</p>', false)
            ->assertSeeInOrder(['Wi-Fi gratuit', 'Parking surveillé'])
            ->assertSee('Chambres')
            ->assertDontSee('10 ans')
            ->assertSee('Très bon accueil.')
            ->assertSee('Awa K.')
            ->assertDontSee('Sans nom : masqué.');
    }

    public function test_managed_texts_are_escaped(): void
    {
        SiteContent::store(['hero_title' => '<script>alert(1)</script>', 'about_text' => '<b>gras</b>']);

        $this->get('/')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('&lt;b&gt;gras&lt;/b&gt;', false);
    }

    public function test_only_published_featured_services_appear_on_the_home_page(): void
    {
        Service::create(['title' => 'Restaurant Le Baobab', 'icon' => 'fa-utensils', 'is_published' => true, 'show_on_home' => true]);
        Service::create(['title' => 'Blanchisserie', 'is_published' => true, 'show_on_home' => false]);
        Service::create(['title' => 'Spa caché', 'is_published' => false, 'show_on_home' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Restaurant Le Baobab')
            ->assertSee('fa-utensils', false)
            ->assertDontSee('Blanchisserie')
            ->assertDontSee('Spa caché');
    }

    public function test_the_services_page_lists_published_services_in_order(): void
    {
        Service::create(['title' => 'Piscine', 'category' => 'Détente', 'description' => 'Ouverte de 8h à 20h.', 'position' => 2, 'is_published' => true]);
        $withPhoto = Service::create(['title' => 'Restaurant', 'position' => 1, 'is_published' => true]);
        $withPhoto->forceFill(['photo' => 'site/services/resto.jpg'])->save();
        Service::create(['title' => 'Parking', 'icon' => 'fa-parking', 'position' => 0, 'is_published' => true]);
        Service::create(['title' => 'Brouillon', 'is_published' => false]);

        $this->get('/services')->assertOk()
            ->assertSeeInOrder(['Parking', 'Piscine', 'Restaurant'])
            ->assertSee('Détente')
            ->assertSee('Ouverte de 8h à 20h.')
            ->assertSee('/storage/site/services/resto.jpg', false)
            ->assertSee('fa-parking', false)
            ->assertDontSee('Brouillon')
            ->assertDontSee('Serrure connectée')
            ->assertDontSee('Piscine couverte');
    }

    public function test_the_services_page_without_services_shows_a_notice(): void
    {
        $this->get('/services')->assertOk()
            ->assertSee('Nos services seront bientôt présentés ici')
            ->assertDontSee('Salle de sport équipée');
    }
}
