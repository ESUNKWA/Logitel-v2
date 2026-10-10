<?php

namespace Tests\Feature\Admin;

use App\Models\GalleryPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Site web → Galerie »: upload, edit, delete, limits, permission.
 */
class GalleryAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function upload(int $count = 1, array $extra = [])
    {
        $images = array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg", 800, 600), range(1, $count));

        return $this->post(route('admin.site.gallery.store'), ['images' => $images, ...$extra]);
    }

    public function test_photos_are_uploaded_with_random_names_and_published(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.site.gallery.index'))->assertOk()->assertSee('Ajouter des photos');

        $this->upload(3, ['category' => ' Piscine '])
            ->assertRedirect(route('admin.site.gallery.index'))
            ->assertSessionHas('status', '3 photos ajoutées à la galerie.');

        $photos = GalleryPhoto::query()->ordered()->get();
        $this->assertCount(3, $photos);
        $this->assertSame([1, 2, 3], $photos->pluck('position')->all());
        foreach ($photos as $photo) {
            $this->assertTrue($photo->is_published);
            $this->assertSame('Piscine', $photo->category);
            $this->assertStringStartsWith('site/gallery/', $photo->path);
            $this->assertStringNotContainsString('photo1', $photo->path);
            Storage::disk('public')->assertExists($photo->path);
        }

        // New uploads go after the existing ones.
        $this->upload(1);
        $this->assertSame(4, GalleryPhoto::query()->latest('id')->first()->position);
        $this->get(route('admin.site.gallery.index'))->assertOk()->assertSee($photos[0]->url(), false);
    }

    public function test_captions_categories_order_and_publication_are_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $this->upload(2);
        [$a, $b] = GalleryPhoto::query()->ordered()->get()->all();

        $this->put(route('admin.site.gallery.update'), ['photos' => [
            $a->id => ['caption' => ' Vue du jardin ', 'category' => 'Extérieur', 'position' => 5, 'is_published' => '0'],
            $b->id => ['caption' => '', 'category' => '', 'position' => 1, 'is_published' => '1'],
            999 => ['caption' => 'inconnue', 'position' => 1],
        ]])->assertRedirect(route('admin.site.gallery.index'))->assertSessionHasNoErrors();

        $a->refresh();
        $b->refresh();
        $this->assertSame('Vue du jardin', $a->caption);
        $this->assertSame('Extérieur', $a->category);
        $this->assertSame(5, $a->position);
        $this->assertFalse($a->is_published);
        $this->assertNull($b->caption);
        $this->assertNull($b->category);
        $this->assertTrue($b->is_published);
        $this->assertDatabaseCount('gallery_photos', 2);

        $this->get('/galerie')->assertDontSee($a->url(), false)->assertSee($b->url(), false);
    }

    public function test_deleting_a_photo_removes_its_file(): void
    {
        $this->actingAs(User::factory()->create());
        $this->upload(1);
        $photo = GalleryPhoto::sole();

        $this->delete(route('admin.site.gallery.destroy', $photo))->assertRedirect(route('admin.site.gallery.index'));

        $this->assertDatabaseCount('gallery_photos', 0);
        Storage::disk('public')->assertMissing($photo->path);
    }

    public function test_invalid_or_malicious_files_are_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $script = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "x"; ?>');
        $pdf = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $tiny = UploadedFile::fake()->image('tiny.jpg', 100, 80);
        $huge = UploadedFile::fake()->image('big.jpg', 800, 600)->size(3000);

        $this->post(route('admin.site.gallery.store'), ['images' => [$svg, $script, $pdf, $tiny, $huge]])
            ->assertSessionHasErrors(['images.0', 'images.1', 'images.2', 'images.3', 'images.4']);
        $this->post(route('admin.site.gallery.store'), [])->assertSessionHasErrors('images');
        $this->upload(1, ['category' => str_repeat('a', 61)])->assertSessionHasErrors('category');

        $this->assertDatabaseCount('gallery_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_and_total_limits(): void
    {
        $this->actingAs(User::factory()->create());

        $this->upload(GalleryPhoto::MAX_PER_UPLOAD + 1)->assertSessionHasErrors('images');
        $this->assertDatabaseCount('gallery_photos', 0);

        foreach (range(1, GalleryPhoto::MAX_TOTAL - 2) as $i) {
            $photo = new GalleryPhoto;
            $photo->path = "site/gallery/existing{$i}.jpg";
            $photo->save();
        }

        $this->upload(3)->assertSessionHasErrors(['images' => 'La galerie est limitée à 60 photos : vous pouvez encore en ajouter 2.']);
        $this->upload(2)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('gallery_photos', GalleryPhoto::MAX_TOTAL);

        $this->get(route('admin.site.gallery.index'))->assertOk()->assertSee('La galerie est complète')->assertDontSee('data-photo-input', false);
        $this->upload(1)->assertSessionHasErrors('images');
    }

    public function test_the_gallery_needs_the_site_permission(): void
    {
        $photo = new GalleryPhoto;
        $photo->path = 'site/gallery/a.jpg';
        $photo->save();
        $this->actingAs(User::factory()->reception()->create());

        $this->get(route('admin.site.gallery.index'))->assertForbidden();
        $this->upload(1)->assertForbidden();
        $this->put(route('admin.site.gallery.update'), ['photos' => [$photo->id => ['caption' => 'Piraté']]])->assertForbidden();
        $this->delete(route('admin.site.gallery.destroy', $photo))->assertForbidden();

        $this->assertDatabaseCount('gallery_photos', 1);
        $this->assertNull($photo->refresh()->caption);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
