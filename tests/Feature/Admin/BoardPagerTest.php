<?php

namespace Tests\Feature\Admin;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Room board on phones: 5 rooms per page in each state tab (client request, 2026-11-10). The
 * paging is done in the browser (admin.js); the server still renders every room, for every screen.
 */
class BoardPagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_room_is_rendered_and_paged_on_phones_only(): void
    {
        $type = RoomType::factory()->create();
        Room::factory()->count(12)->sequence(fn ($s) => ['number' => 'P'.($s->index + 1), 'room_type_id' => $type->id])->create();

        $page = $this->actingAs(User::factory()->create())->get('/admin/rooms/board')->assertOk()->getContent();
        $this->assertSame(12, preg_match_all('/data-assign-room\s/', $page));

        $js = file_get_contents(public_path('app/js/admin.js'));
        $this->assertStringContainsString("var perPage = 5;", $js);
        $this->assertStringContainsString("window.matchMedia('(max-width: 860px)')", $js);

        $css = file_get_contents(public_path('app/css/admin.css'));
        $this->assertStringContainsString('.room-card.is-paged-out, .room-board.is-paged-out, .room-type-heading.is-paged-out { display: none; }', $css);
        $this->assertStringContainsString('@media (min-width: 861px) { .room-pager { display: none; } }', $css);
    }
}
