<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Site web » section of the application: home page contents and services.
 */
class SiteContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_the_manager_edits_the_home_page_texts(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.site.home.edit'))->assertOk()->assertSee('Bannière');

        $this->put(route('admin.site.home.update'), [
            'hero_title' => '  Bienvenue chez nous  ',
            'about_text' => 'Un hôtel au calme.',
            'stat_1_value' => '25', 'stat_1_label' => 'Chambres',
            'testimonial_1_name' => 'Awa', 'testimonial_1_text' => 'Parfait.',
        ])->assertRedirect(route('admin.site.home.edit'))->assertSessionHasNoErrors();

        $this->assertSame('Bienvenue chez nous', SiteContent::get('hero_title'));
        $this->assertSame([['25', 'Chambres']], SiteContent::stats());
        $this->assertSame('Parfait.', SiteContent::testimonials()[0]['text']);

        // Emptying a field removes it from the site.
        $this->put(route('admin.site.home.update'), ['hero_title' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', SiteContent::get('hero_title'));
        $this->assertSame([], SiteContent::stats());
    }

    public function test_home_photos_are_uploaded_replaced_and_removed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('admin.site.home.update'), ['hero_background' => UploadedFile::fake()->image('fond.jpg', 1600, 900)])->assertSessionHasNoErrors();
        $first = SiteContent::get('hero_background');
        $this->assertStringStartsWith('site/', $first);
        Storage::disk('public')->assertExists($first);

        $this->put(route('admin.site.home.update'), ['hero_background' => UploadedFile::fake()->image('autre.jpg', 1600, 900)])->assertSessionHasNoErrors();
        $second = SiteContent::get('hero_background');
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->put(route('admin.site.home.update'), ['remove_hero_background' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('', SiteContent::get('hero_background'));
        Storage::disk('public')->assertMissing($second);
    }

    public function test_home_inputs_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('admin.site.home.edit'))
            ->put(route('admin.site.home.update'), [
                'hero_title' => str_repeat('a', 161),
                'hero_photo_1' => UploadedFile::fake()->create('page.pdf', 10, 'application/pdf'),
                'about_photo_1' => UploadedFile::fake()->image('petite.jpg', 100, 80),
            ])->assertSessionHasErrors(['hero_title', 'hero_photo_1', 'about_photo_1']);

        $this->assertSame('', SiteContent::get('hero_title'));
    }

    public function test_services_crud_with_photo(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.site.services.index'))->assertOk();
        $this->get(route('admin.site.services.create'))->assertOk();

        $this->post(route('admin.site.services.store'), [
            'title' => 'Restaurant', 'category' => 'Restauration', 'description' => 'Cuisine locale.',
            'icon' => 'fa-utensils', 'position' => 3, 'is_published' => '1', 'show_on_home' => '1',
            'photo' => UploadedFile::fake()->image('resto.jpg', 800, 600),
        ])->assertRedirect(route('admin.site.services.index'))->assertSessionHasNoErrors();

        $service = Service::sole();
        $this->assertTrue($service->is_published);
        $this->assertTrue($service->show_on_home);
        $this->assertSame(3, $service->position);
        Storage::disk('public')->assertExists($service->photo);
        $photo = $service->photo;

        $this->get(route('admin.site.services.edit', $service))->assertOk()->assertSee('Restaurant');

        // Unticked checkboxes are not sent: unpublished; photo removed.
        $this->put(route('admin.site.services.update', $service), ['title' => 'Restaurant Baobab', 'remove_photo' => '1'])->assertSessionHasNoErrors();
        $service->refresh();
        $this->assertSame('Restaurant Baobab', $service->title);
        $this->assertFalse($service->is_published);
        $this->assertFalse($service->show_on_home);
        $this->assertNull($service->photo);
        Storage::disk('public')->assertMissing($photo);

        $this->put(route('admin.site.services.update', $service), ['title' => 'Restaurant Baobab', 'photo' => UploadedFile::fake()->image('n.jpg', 800, 600)]);
        $photo = $service->refresh()->photo;
        $this->delete(route('admin.site.services.destroy', $service))->assertRedirect(route('admin.site.services.index'));
        $this->assertDatabaseCount('services', 0);
        Storage::disk('public')->assertMissing($photo);
    }

    public function test_service_inputs_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.site.services.store'), [
                'title' => '', 'icon' => 'fa-not-an-icon', 'position' => -1,
                'photo' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
            ])->assertSessionHasErrors(['title', 'icon', 'position', 'photo']);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_the_site_section_needs_its_permission(): void
    {
        $service = Service::create(['title' => 'Bar']);
        $this->actingAs(User::factory()->reception()->create());

        $this->get(route('admin.site.home.edit'))->assertForbidden();
        $this->put(route('admin.site.home.update'), ['hero_title' => 'Piraté'])->assertForbidden();
        $this->get(route('admin.site.services.index'))->assertForbidden();
        $this->post(route('admin.site.services.store'), ['title' => 'X'])->assertForbidden();
        $this->delete(route('admin.site.services.destroy', $service))->assertForbidden();

        $this->assertSame('', SiteContent::get('hero_title'));
        $this->assertDatabaseCount('services', 1);
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.site.home.edit'));
    }
}
