<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * « Formation » menu entry: link to the training (UAT) environment, set in Paramètres.
 */
class TrainingLinkTest extends TestCase
{
    use RefreshDatabase;

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'business_day_start' => '06:00',
            'night_from' => '22:00',
            'night_until' => '12:00',
            'day_hours' => 6,
            'stay_min_hours' => 24,
            'cleaning_buffer_minutes' => 30,
        ], $overrides);
    }

    public function test_no_menu_entry_while_no_address_is_set(): void
    {
        foreach ([User::factory()->create(), User::factory()->simpleUi()->create(), User::factory()->reception()->simpleUi()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-training-link', false);
        }
    }

    public function test_manager_sets_the_address_and_every_user_sees_the_entry(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->put(route('admin.settings.update'), $this->settings(['training_url' => 'uat.hotel-evasion.ci/admin']))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://uat.hotel-evasion.ci/admin', DB::table('settings')->where('key', 'training_url')->value('value'));

        // Full layout (sidebar) and simple layout (« Plus » menu), manager and receptionist alike.
        foreach ([$manager, User::factory()->simpleUi()->create(), User::factory()->reception()->create(), User::factory()->reception()->simpleUi()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee('href="https://uat.hotel-evasion.ci/admin" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" data-training-link', false)
                ->assertSee('Formation');
        }

        $this->actingAs($manager)->get(route('admin.settings.edit'))->assertSee('value="https://uat.hotel-evasion.ci/admin"', false);
    }

    public function test_computers_only_never_on_phones(): void
    {
        config(['hotel.training_url' => 'https://uat.hotel-evasion.ci']);

        // Simple layout on a computer: the « Plus » entry carries the wide-screen-only class.
        $this->actingAs(User::factory()->simpleUi()->create())->get(route('admin.dashboard'))
            ->assertSee('<li class="simple-nav__wide-only"><a class="dropdown-item" href="https://uat.hotel-evasion.ci"', false);

        foreach ([User::factory()->create(), User::factory()->reception()->simpleUi()->create()] as $user) {
            $this->actingAs($user)->withUnencryptedCookie(User::PHONE_COOKIE, '1')->get(route('admin.dashboard'))
                ->assertOk()
                ->assertDontSee('data-training-link', false);
        }
    }

    public function test_emptying_the_address_removes_the_entry(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings(['training_url' => 'https://uat.hotel-evasion.ci']));
        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings(['training_url' => '']))->assertSessionHasNoErrors();

        $this->actingAs($manager)->get(route('admin.dashboard'))->assertDontSee('data-training-link', false);
    }

    public function test_an_older_form_without_the_field_keeps_the_address(): void
    {
        $manager = User::factory()->create();
        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings(['training_url' => 'https://uat.hotel-evasion.ci']));
        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings())->assertSessionHasNoErrors();

        $this->assertSame('https://uat.hotel-evasion.ci', config('hotel.training_url'));
    }

    public function test_dangerous_or_invalid_addresses_are_refused(): void
    {
        $manager = User::factory()->create();

        foreach (['https://admin:secret@uat.hotel-evasion.ci', 'https://user@uat.hotel-evasion.ci', 'https://uat.hotel-evasion.ci/a"onmouseover="x', 'https://uat.hotel-evasion.ci/<script>', 'javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'https://exa mple.ci', 'ftp://uat.hotel-evasion.ci', str_repeat('a', 250).'.ci'] as $bad) {
            $this->actingAs($manager)
                ->put(route('admin.settings.update'), $this->settings(['training_url' => $bad]))
                ->assertSessionHasErrors('training_url');
        }

        // A forged array is a validation error, never a crash.
        $this->actingAs($manager)
            ->put(route('admin.settings.update'), $this->settings(['training_url' => ['x']]))
            ->assertSessionHasErrors('training_url');

        $this->assertNull(DB::table('settings')->where('key', 'training_url')->value('value'));
    }

    public function test_a_value_planted_in_the_database_is_never_rendered_unless_http(): void
    {
        config(['hotel.training_url' => 'javascript:alert(1)']);

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
            ->assertDontSee('data-training-link', false)
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_hidden_when_already_on_the_training_environment(): void
    {
        config(['hotel.training_url' => 'http://localhost/admin']);

        $this->actingAs(User::factory()->create())->get('http://localhost/admin')->assertDontSee('data-training-link', false);
    }

    public function test_only_settings_managers_can_change_it(): void
    {
        $this->actingAs(User::factory()->reception()->create())
            ->put(route('admin.settings.update'), $this->settings(['training_url' => 'https://evil.example']))
            ->assertForbidden();

        $this->assertNull(DB::table('settings')->where('key', 'training_url')->value('value'));
    }

    public function test_a_planted_address_with_credentials_is_never_rendered(): void
    {
        config(['hotel.training_url' => 'https://admin:secret@uat.hotel-evasion.ci']);

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
            ->assertDontSee('data-training-link', false)
            ->assertDontSee('secret', false);
    }

    public function test_link_leaks_no_referrer_to_the_training_environment(): void
    {
        config(['hotel.training_url' => 'https://uat.hotel-evasion.ci']);

        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))
            ->assertSee('rel="noopener noreferrer" referrerpolicy="no-referrer" data-training-link', false);
    }

    public function test_changes_are_logged_with_their_author(): void
    {
        $manager = User::factory()->create();
        Log::spy();

        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings(['training_url' => 'https://uat.hotel-evasion.ci']));
        $this->actingAs($manager)->put(route('admin.settings.update'), $this->settings(['training_url' => 'https://uat.hotel-evasion.ci']));

        Log::shouldHaveReceived('notice')->once()->withArgs(fn ($message, $context) => $message === 'Lien « Formation » modifié'
            && $context === ['by' => $manager->id, 'from' => '', 'to' => 'https://uat.hotel-evasion.ci']);
    }

    public function test_settings_warn_about_an_unencrypted_address(): void
    {
        $manager = User::factory()->create();

        config(['hotel.training_url' => 'https://uat.hotel-evasion.ci']);
        $this->actingAs($manager)->get(route('admin.settings.edit'))->assertDontSee('la connexion n\'est pas chiffrée', false);

        config(['hotel.training_url' => 'http://203.0.113.10:8080/admin']);
        $this->actingAs($manager)->get(route('admin.settings.edit'))->assertSee('la connexion n\'est pas chiffrée', false);
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertSee('href="http://203.0.113.10:8080/admin"', false);
    }
}
