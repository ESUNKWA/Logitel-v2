<?php

namespace Tests\Feature\Site;

use App\Models\SiteEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Upcoming events on the website: home banner and cards, /evenements, menu link.
 */
class EventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 15:00:00');
    }

    private function event(array $attributes = []): SiteEvent
    {
        return SiteEvent::create(['title' => 'Soirée karaoké', 'starts_at' => '2026-10-17 20:00', 'is_published' => true, ...$attributes]);
    }

    public function test_without_upcoming_events_nothing_is_shown(): void
    {
        $this->event(['title' => 'Concert passé', 'starts_at' => '2026-10-01 20:00']);
        $this->event(['title' => 'Brouillon', 'is_published' => false]);

        $this->get('/')->assertOk()
            ->assertDontSee('site-event-bar', false)
            ->assertDontSee('Événements à venir')
            ->assertDontSee(route('events.index'), false);
        $this->get('/evenements')->assertOk()
            ->assertSee('Aucun événement à venir')
            ->assertDontSee('Concert passé')
            ->assertDontSee('Brouillon');
    }

    public function test_the_next_event_is_in_the_banner_and_the_next_three_are_cards(): void
    {
        $this->event(['title' => 'Brunch du dimanche', 'starts_at' => '2026-10-18 11:00', 'location' => 'Restaurant']);
        $this->event(['title' => 'Soirée karaoké', 'starts_at' => '2026-10-12 20:00', 'location' => 'Bar', 'price_label' => 'Entrée libre']);
        $this->event(['title' => 'Concert live', 'starts_at' => '2026-10-24 21:00']);
        $this->event(['title' => 'Nouvel an', 'starts_at' => '2026-12-31 22:00']);

        $response = $this->get('/')->assertOk();
        $response->assertSeeInOrder(['site-event-bar', 'Prochain événement', 'Soirée karaoké', 'Lundi 12 octobre 2026 à 20h00', 'Bar', 'Voir les événements'], false)
            ->assertSeeInOrder(['Événements à venir', 'Soirée karaoké', 'Brunch du dimanche', 'Concert live'])
            ->assertSee('Entrée libre')
            ->assertDontSee('Nouvel an')
            ->assertSee(route('events.index'), false);

        // The banner comes right after the hero, before the rest of the page.
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'site-cta'), strpos($html, 'site-event-bar'));

        $this->get('/evenements')->assertOk()->assertSeeInOrder(['Soirée karaoké', 'Brunch du dimanche', 'Concert live', 'Nouvel an']);
    }

    public function test_an_event_stays_until_its_end_or_the_end_of_its_day(): void
    {
        $this->event(['title' => 'Atelier cocktails', 'starts_at' => '2026-10-10 10:00']); // today, no end
        $this->event(['title' => 'Festival', 'starts_at' => '2026-10-08 18:00', 'ends_at' => '2026-10-11 23:00']); // ongoing
        $this->event(['title' => 'Fini ce matin', 'starts_at' => '2026-10-09 18:00', 'ends_at' => '2026-10-10 09:00']);

        $this->get('/evenements')->assertOk()
            ->assertSee('Atelier cocktails')
            ->assertSee('Festival')
            ->assertSee('En cours')
            ->assertSee('Du 8 octobre 2026 à 18h00 au 11 octobre 2026 à 23h00')
            ->assertDontSee('Fini ce matin');
        $this->get('/')->assertOk()->assertSee('En ce moment');

        $this->travelTo('2026-10-11 00:01:00');
        $this->get('/evenements')->assertOk()->assertDontSee('Atelier cocktails')->assertSee('Festival');
    }

    public function test_the_events_page_shows_details_and_booking_links(): void
    {
        $this->event(['title' => 'Brunch', 'description' => "Buffet à volonté.\nAnimation musicale.", 'booking_info' => 'https://exemple.ci/brunch'])
            ->forceFill(['photo' => 'site/events/brunch.jpg'])->save();
        $this->event(['title' => 'Karaoké', 'starts_at' => '2026-10-18 20:00', 'booking_info' => '07 01 02 03 04']);
        $this->event(['title' => 'Quiz', 'starts_at' => '2026-10-19 20:00', 'booking_info' => 'Inscription à la réception']);

        $this->get('/evenements')->assertOk()
            ->assertSee('Buffet à volonté.<br />', false)
            ->assertSee('href="https://exemple.ci/brunch"', false)
            ->assertSee('href="tel:0701020304"', false)
            ->assertSee('Inscription à la réception')
            ->assertSee('/storage/site/events/brunch.jpg', false);
    }

    public function test_the_menu_links_the_events_page_only_when_there_are_some(): void
    {
        $this->get('/galerie')->assertOk()->assertDontSee('>Événements</a>', false);

        $this->event();
        $this->get('/galerie')->assertOk()->assertSee('>Événements</a>', false);
    }

    public function test_event_texts_are_escaped(): void
    {
        $this->event(['title' => '<script>alert(1)</script>', 'description' => '<b>gras</b>']);

        $this->get('/')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/evenements')->assertOk()->assertDontSee('<b>gras</b>', false)->assertSee('&lt;b&gt;gras&lt;/b&gt;', false);
    }
}
