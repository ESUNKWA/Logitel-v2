<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List tables become DataTables (admin.js): the vendored libraries are loaded on the
 * application's pages — never from a CDN, never on the website.
 */
class DataTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_loads_the_vendored_datatables(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.guests.index'))->assertOk()
            ->assertSeeInOrder([
                'vendor/datatables/dataTables.bootstrap5.min.css',
                'vendor/jquery/jquery-3.7.1.min.js',
                'vendor/datatables/dataTables.min.js',
                'vendor/datatables/dataTables.bootstrap5.min.js',
                'vendor/datatables/dataTables.rowGroup.min.js',
                'app/js/admin.js',
            ], false)
            ->assertDontSee('cdn.datatables.net', false);

        foreach (['jquery/jquery-3.7.1.min.js', 'datatables/dataTables.min.js', 'datatables/dataTables.bootstrap5.min.js',
            'datatables/dataTables.rowGroup.min.js', 'datatables/dataTables.bootstrap5.min.css', 'datatables/rowGroup.bootstrap5.min.css'] as $file) {
            $this->assertFileExists(public_path('vendor/'.$file));
        }
    }

    public function test_the_website_does_not_load_them(): void
    {
        $this->get('/')->assertOk()->assertDontSee('vendor/datatables', false);
    }
}
