<?php

namespace Tests\Feature\Admin;

use App\Models\Room;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(RoomImage::DISK);
    }

    private function photos(int $count): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("photo{$i}.jpg", 1200, 900), range(1, $count));
    }

    private function payload(array $extra = []): array
    {
        return ['room_type_id' => RoomType::factory()->create()->id, 'number' => '501', 'floor' => '5', 'status' => 'disponible', ...$extra];
    }

    public function test_photos_are_stored_with_the_new_room(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/rooms', $this->payload(['images' => $this->photos(3)]))
            ->assertRedirect(route('admin.rooms.index'))->assertSessionHasNoErrors();

        $room = Room::where('number', '501')->firstOrFail();
        $this->assertCount(3, $room->images);
        $this->assertSame([1, 2, 3], $room->images->pluck('position')->all());
        foreach ($room->images as $image) {
            Storage::disk(RoomImage::DISK)->assertExists($image->path);
            $this->assertStringStartsWith("rooms/{$room->id}/", $image->path);
            $this->assertStringNotContainsString('photo1', $image->path, 'Stored under a random name.');
            $this->assertSame($manager->id, $image->created_by);
        }

        $this->actingAs($manager)->get("/admin/rooms/{$room->id}/edit")->assertOk()
            ->assertSee('data-image-uploader', false)
            ->assertSee($room->images->first()->url(), false)
            ->assertSee('Principale');
    }

    public function test_no_more_than_five_photos_per_room(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->post('/admin/rooms', $this->payload(['images' => $this->photos(6)]))->assertSessionHasErrors('images');
        $this->assertSame(0, Room::count());

        $room = Room::factory()->create();
        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number, 'images' => $this->photos(4)]))->assertSessionHasNoErrors();
        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number, 'images' => $this->photos(2)]))
            ->assertSessionHasErrors(['images' => 'Une chambre peut avoir au maximum 5 photos (4 conservée(s) + 2 ajoutée(s)).']);
        $this->assertSame(4, $room->images()->count());
    }

    public function test_removing_photos_frees_slots_and_deletes_the_files(): void
    {
        $manager = User::factory()->create();
        $room = Room::factory()->create();
        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number, 'images' => $this->photos(5)]));
        $removed = $room->images()->take(2)->get();

        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload([
            'number' => $room->number,
            'remove_images' => $removed->pluck('id')->all(),
            'images' => $this->photos(2),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(5, $room->images()->count());
        foreach ($removed as $image) {
            Storage::disk(RoomImage::DISK)->assertMissing($image->path);
            $this->assertModelMissing($image);
        }
    }

    public function test_photos_of_another_room_cannot_be_removed(): void
    {
        $manager = User::factory()->create();
        $mine = Room::factory()->create();
        $other = Room::factory()->create();
        $this->actingAs($manager)->put("/admin/rooms/{$other->id}", $this->payload(['number' => $other->number, 'images' => $this->photos(1)]));
        $foreign = $other->images()->firstOrFail();

        $this->actingAs($manager)->put("/admin/rooms/{$mine->id}", $this->payload(['number' => $mine->number, 'remove_images' => [$foreign->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelExists($foreign);
        Storage::disk(RoomImage::DISK)->assertExists($foreign->path);
    }

    public function test_only_real_images_are_accepted(): void
    {
        $manager = User::factory()->create();
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $pdf = UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf');
        $fakeJpg = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned"; ?>');
        $huge = UploadedFile::fake()->image('big.jpg')->size(3000);

        foreach ([$svg, $pdf, $fakeJpg, $huge] as $file) {
            $this->actingAs($manager)->post('/admin/rooms', $this->payload(['images' => [$file]]))->assertSessionHasErrors('images.0');
        }

        $this->assertSame(0, Room::count());
        $this->assertSame([], Storage::disk(RoomImage::DISK)->allFiles());
    }

    public function test_reception_can_change_the_status_but_not_the_photos(): void
    {
        $reception = User::factory()->reception()->create();
        $room = Room::factory()->create(['status' => 'nettoyage']);

        $this->actingAs($reception)->get("/admin/rooms/{$room->id}/edit")->assertOk()->assertDontSee('data-image-uploader', false);
        $this->actingAs($reception)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number, 'images' => $this->photos(1)]))
            ->assertForbidden();
        $this->assertSame('nettoyage', $room->fresh()->status->value, 'Nothing saved when the request is refused.');

        $this->actingAs($reception)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number]))->assertSessionHasNoErrors();
        $this->assertSame('disponible', $room->fresh()->status->value);
    }

    public function test_deleting_a_room_deletes_its_photo_files(): void
    {
        $manager = User::factory()->create();
        $room = Room::factory()->create();
        $this->actingAs($manager)->put("/admin/rooms/{$room->id}", $this->payload(['number' => $room->number, 'images' => $this->photos(2)]));
        $paths = $room->images()->pluck('path');

        $this->actingAs($manager)->delete("/admin/rooms/{$room->id}");

        $this->assertSame(0, RoomImage::count());
        foreach ($paths as $path) {
            Storage::disk(RoomImage::DISK)->assertMissing($path);
        }
    }
}
