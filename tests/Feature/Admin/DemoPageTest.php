<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class DemoPageTest extends TestCase
{
    public function test_the_demo_page_is_public(): void
    {
        $this->get(route('admin.demo'))
            ->assertOk()
            ->assertSee('Hôtel Les Palmiers')
            ->assertSee('data-act="goto-rooms"', false);
    }
}
