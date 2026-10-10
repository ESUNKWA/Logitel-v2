<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public website's pages answer, even with nothing published yet. Room pages are covered
 * by PublicRoomsTest, /demande by StayRequestTest.
 */
class PublicSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function routes(): array
    {
        return [
            ['/'],
            ['/chambres'],
            ['/services'],
            ['/galerie'],
            ['/contact'],
        ];
    }

    #[DataProvider('routes')]
    public function test_public_route_returns_200(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    /**
     * Pages of the original template that the hotel doesn't use (no real content).
     *
     * @return array<int, array<int, string>>
     */
    public static function removedRoutes(): array
    {
        return [['/a-propos'], ['/equipe'], ['/equipe/detail'], ['/offres'], ['/offres/detail'], ['/tarifs'], ['/faq'], ['/blog'], ['/blog/detail'], ['/services/detail']];
    }

    #[DataProvider('removedRoutes')]
    public function test_removed_template_pages_are_gone(string $uri): void
    {
        $this->get($uri)->assertNotFound();
    }

    public function test_the_menu_only_links_existing_pages_and_the_room_request(): void
    {
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Accueil', 'Chambres', 'Services', 'Galerie', 'Contact'])
            ->assertSee(route('stay-request.index'), false)
            ->assertSee('site/css/style.css', false)
            ->assertDontSee('app/css/admin.css', false);
    }

    public function test_unknown_route_returns_404(): void
    {
        $this->get('/route-inexistante')->assertNotFound();
    }
}
