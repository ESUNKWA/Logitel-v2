<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Site web » and « Configuration » fold, in the sidebar (mode complet) and the « Plus » menu
 * (mode simple), one open at a time (client request, 2026-11-24): closed by default, the group of
 * the current page open, every link and permission unchanged.
 */
class NavGroupsTest extends TestCase
{
    use RefreshDatabase;

    private const SITE_LINKS = ['admin.site.stats', 'admin.site.home.edit', 'admin.site.services.index', 'admin.site.events.index', 'admin.site.gallery.index'];

    private const CONFIG_LINKS = ['admin.room-types.index', 'admin.special-rates.index', 'admin.perks.index', 'admin.hotel.edit', 'admin.settings.edit',
        'admin.users.index', 'admin.roles.index', 'admin.activity.index'];

    private function manager(string $ui): User
    {
        return User::factory()->create(['ui_mode' => $ui]);
    }

    private function page(User $user, string $url)
    {
        return $this->actingAs($user)->get($url)->assertOk();
    }

    /** [toggle id prefix] per layout. */
    public static function layouts(): array
    {
        return ['mode complet' => ['complet', 'sidebar-group'], 'mode simple' => ['simple', 'plus-group']];
    }

    /**
     * @dataProvider layouts
     */
    public function test_both_groups_closed_by_default_with_every_link(string $ui, string $prefix): void
    {
        $response = $this->page($this->manager($ui), route('admin.dashboard'));

        foreach (['site', 'config'] as $group) {
            $response->assertSee('aria-expanded="false" aria-controls="'.$prefix.'-'.$group.'"', false)
                ->assertSee('id="'.$prefix.'-'.$group.'"  hidden', false);
        }
        foreach ([...self::SITE_LINKS, ...self::CONFIG_LINKS] as $name) {
            $response->assertSee('href="'.route($name).'"', false);
        }
        // Site web before Configuration, each link inside its own group.
        $response->assertSeeInOrder(['id="'.$prefix.'-site"', route('admin.site.gallery.index'), 'id="'.$prefix.'-config"', route('admin.activity.index')], false);
    }

    /**
     * @dataProvider layouts
     */
    public function test_the_group_of_the_current_page_starts_open(string $ui, string $prefix): void
    {
        $user = $this->manager($ui);

        $this->page($user, route('admin.room-types.index'))
            ->assertSee('aria-expanded="true" aria-controls="'.$prefix.'-config"', false)
            ->assertSee('aria-expanded="false" aria-controls="'.$prefix.'-site"', false)
            ->assertDontSee('id="'.$prefix.'-config"  hidden', false);

        $this->page($user, route('admin.site.services.index'))
            ->assertSee('aria-expanded="true" aria-controls="'.$prefix.'-site"', false)
            ->assertSee('aria-expanded="false" aria-controls="'.$prefix.'-config"', false);

        $this->page($user, route('admin.activity.index'))->assertSee('aria-expanded="true" aria-controls="'.$prefix.'-config"', false);
    }

    /**
     * @dataProvider layouts
     */
    public function test_the_reception_sees_no_configuration_group(string $ui, string $prefix): void
    {
        $reception = User::factory()->reception()->create(['ui_mode' => $ui]);

        $response = $this->page($reception, route('admin.dashboard'))->assertDontSee($prefix.'-config', false);
        foreach (self::CONFIG_LINKS as $name) {
            $response->assertDontSee('href="'.route($name).'"', false);
        }
    }

    /**
     * @dataProvider layouts
     */
    public function test_a_group_only_holds_the_links_its_user_may_open(string $ui, string $prefix): void
    {
        // Only « Personnel »: the Configuration group shows (it used to lack its heading in mode complet).
        $role = Role::create(['name' => 'RH']);
        $role->syncPermissions(['users.manage']);
        $user = User::factory()->create(['ui_mode' => $ui]);
        $user->forceFill(['role_id' => $role->id])->save();

        $response = $this->page($user, route('admin.users.index'))
            ->assertSee('aria-expanded="true" aria-controls="'.$prefix.'-config"', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee($prefix.'-site', false);
        foreach (array_diff(self::CONFIG_LINKS, ['admin.users.index']) as $name) {
            $response->assertDontSee('href="'.route($name).'"', false);
        }
    }

    public function test_the_script_keeps_one_group_open_and_the_plus_menu_open(): void
    {
        $js = file_get_contents(public_path('app/js/admin.js'));
        $css = file_get_contents(public_path('app/css/admin.css'));

        $this->assertStringContainsString("document.querySelectorAll('[data-nav-group]')", $js);
        $this->assertStringContainsString('event.stopPropagation();', $js);
        $this->assertStringContainsString("other.hasAttribute('data-nav-group')", $js);
        // Fly-out on computers with a mouse only; phones / tablets keep unfolding in place.
        $this->assertStringContainsString("matchMedia('(hover: hover) and (pointer: fine) and (min-width: 861px)')", $js);
        $this->assertStringContainsString("group.addEventListener('mouseenter'", $js);
        $this->assertStringContainsString('.nav-flyout .simple-nav__menu.has-flyout { overflow: visible; }', $css);
    }
}
