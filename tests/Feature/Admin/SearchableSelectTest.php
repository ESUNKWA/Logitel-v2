<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every <select> of the application gets a search field (Tom Select, public/app/js/select-search.js).
 * The library is vendored — never a CDN —, pinned to the files checked against the npm registry
 * (public/vendor/tom-select/README.txt), loaded before admin.js, and never on the website.
 */
class SearchableSelectTest extends TestCase
{
    use RefreshDatabase;

    /** SHA-256 of the served files (README.txt): changing the library must be deliberate. */
    private const FILES = [
        'tom-select.complete.min.js' => 'b797005cf23378fb38448b80dc57a33313a5c577b841765743bb1f289c4d53e6',
        'tom-select.bootstrap5.min.css' => '95032d7f381d6c6f2e7cc094e544e15c6eb95acbf90885c690714608703af0c1',
    ];

    public function test_the_application_loads_the_vendored_tom_select_before_admin_js(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.guests.create'))->assertOk()
            ->assertSee('<select', false)
            ->assertSeeInOrder([
                'vendor/tom-select/tom-select.bootstrap5.min.css',
                'app/css/admin.css',
                'vendor/tom-select/tom-select.complete.min.js',
                'app/js/select-search.js',
                'app/js/admin.js',
            ], false)
            ->assertDontSee('cdn.jsdelivr.net/npm/tom-select', false)
            ->assertDontSee('unpkg.com/tom-select', false);
    }

    public function test_the_vendored_files_are_the_verified_ones(): void
    {
        foreach (self::FILES as $file => $sha256) {
            $path = public_path('vendor/tom-select/'.$file);
            $this->assertFileExists($path);
            $this->assertSame($sha256, hash_file('sha256', $path), "$file differs from the verified Tom Select 2.4.3 file");
        }
        $this->assertFileExists(public_path('vendor/tom-select/LICENSE'));
        $this->assertFileExists(public_path('app/js/select-search.js'));
    }

    public function test_the_enhancement_keeps_the_safe_defaults(): void
    {
        $js = file_get_contents(public_path('app/js/select-search.js'));

        // Only existing options can be chosen, and labels go through Tom Select's escaping templates.
        $this->assertStringContainsString('create: false', $js);
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('eval(', $js);
        $this->assertDoesNotMatchRegularExpression('/render:\s*\{[^}]*\b(option|item)\s*:/s', $js, 'a custom option/item template must escape() its labels');
    }

    public function test_the_website_does_not_load_it(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('tom-select', false)
            ->assertDontSee('select-search.js', false);
    }
}
