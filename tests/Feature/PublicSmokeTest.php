<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicSmokeTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function routes(): array
    {
        return [
            ['/'],
            ['/a-propos'],
            ['/chambres'],
            ['/chambres/detail'],
            ['/services'],
            ['/services/detail'],
            ['/equipe'],
            ['/equipe/detail'],
            ['/offres'],
            ['/offres/detail'],
            ['/tarifs'],
            ['/galerie'],
            ['/faq'],
            ['/contact'],
            ['/blog'],
            ['/blog/detail'],
        ];
    }

    #[DataProvider('routes')]
    public function test_public_route_returns_200(string $uri): void
    {
        $this->get($uri)->assertOk();
    }

    public function test_unknown_route_returns_404(): void
    {
        $this->get('/route-inexistante')->assertNotFound();
    }
}
