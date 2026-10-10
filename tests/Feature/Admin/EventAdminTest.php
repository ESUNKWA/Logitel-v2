<?php

namespace Tests\Feature\Admin;

use App\Models\SiteEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * « Site web → Événements » in the application.
 */
class EventAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->travelTo('2026-10-10 15:00:00');
    }

    public function test_events_crud_with_poster(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('admin.site.events.index'))->assertOk()->assertSee('Aucun événement à venir');
        $this->get(route('admin.site.events.create'))->assertOk();

        $this->post(route('admin.site.events.store'), [
            'title' => ' Soirée karaoké ', 'starts_at' => '2026-10-17T20:00', 'ends_at' => '2026-10-17T23:30',
            'location' => 'Bar', 'description' => 'Micro ouvert.', 'price_label' => 'Entrée libre', 'booking_info' => 'www.exemple.ci',
            'is_published' => '1', 'photo' => UploadedFile::fake()->image('affiche.jpg', 800, 1000),
        ])->assertRedirect(route('admin.site.events.index'))->assertSessionHasNoErrors();

        $event = SiteEvent::sole();
        $this->assertSame('Soirée karaoké', $event->title);
        $this->assertSame('2026-10-17 23:30', $event->visible_until->format('Y-m-d H:i'));
        $this->assertSame('https://www.exemple.ci', $event->booking_info);
        $this->assertTrue($event->is_published);
        Storage::disk('public')->assertExists($event->photo);
        $poster = $event->photo;

        $this->get(route('admin.site.events.index'))->assertOk()->assertSee('Soirée karaoké')->assertSee('En ligne');
        $this->get(route('admin.site.events.edit', $event))->assertOk()->assertSee('value="2026-10-17T20:00"', false);

        // Unticked = hidden; no end = shown until the end of its day; poster removed.
        $this->put(route('admin.site.events.update', $event), ['title' => 'Karaoké', 'starts_at' => '2026-10-18T20:00', 'remove_photo' => '1'])->assertSessionHasNoErrors();
        $event->refresh();
        $this->assertFalse($event->is_published);
        $this->assertNull($event->ends_at);
        $this->assertSame('2026-10-18 23:59', $event->visible_until->format('Y-m-d H:i'));
        $this->assertNull($event->photo);
        Storage::disk('public')->assertMissing($poster);
        $this->get('/evenements')->assertDontSee('Karaoké');

        $this->put(route('admin.site.events.update', $event), ['title' => 'Karaoké', 'starts_at' => '2026-10-18T20:00', 'photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $poster = $event->refresh()->photo;
        $this->delete(route('admin.site.events.destroy', $event))->assertRedirect(route('admin.site.events.index'));
        $this->assertDatabaseCount('site_events', 0);
        Storage::disk('public')->assertMissing($poster);
    }

    public function test_past_events_are_listed_apart(): void
    {
        SiteEvent::create(['title' => 'Concert passé', 'starts_at' => '2026-10-01 20:00', 'is_published' => true]);
        SiteEvent::create(['title' => 'Brunch à venir', 'starts_at' => '2026-10-18 11:00', 'is_published' => true]);

        $this->actingAs(User::factory()->create())->get(route('admin.site.events.index'))->assertOk()
            ->assertSeeInOrder(['Brunch à venir', 'Passés (plus affichés sur le site)', 'Concert passé']);
    }

    public function test_inputs_are_validated(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.site.events.store'), [
            'title' => '', 'starts_at' => 'pas une date', 'booking_info' => 'javascript:alert(1)',
            'photo' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors(['title', 'starts_at', 'booking_info', 'photo']);

        $this->post(route('admin.site.events.store'), ['title' => 'A', 'starts_at' => '2026-10-17T20:00', 'ends_at' => '2026-10-17T19:00'])
            ->assertSessionHasErrors(['ends_at' => 'La fin doit être après le début.']);

        $this->assertDatabaseCount('site_events', 0);
    }

    public function test_events_need_the_site_permission(): void
    {
        $event = SiteEvent::create(['title' => 'Brunch', 'starts_at' => '2026-10-18 11:00', 'is_published' => true]);
        $this->actingAs(User::factory()->reception()->create());

        $this->get(route('admin.site.events.index'))->assertForbidden();
        $this->post(route('admin.site.events.store'), ['title' => 'X', 'starts_at' => '2026-10-20T20:00'])->assertForbidden();
        $this->put(route('admin.site.events.update', $event), ['title' => 'Piraté', 'starts_at' => '2026-10-20T20:00'])->assertForbidden();
        $this->delete(route('admin.site.events.destroy', $event))->assertForbidden();

        $this->assertSame('Brunch', $event->fresh()->title);
        $this->assertDatabaseCount('site_events', 1);
    }
}
