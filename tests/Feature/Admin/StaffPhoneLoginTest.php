<?php

namespace Tests\Feature\Admin;

use App\Models\Guest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff phone numbers (client request, 2026-10-18): an account has an e-mail and/or a phone —
 * at least one —, the phone is exactly 10 digits (no international prefix), unique among
 * staff; either signs in from the one « E-mail ou téléphone » field, with the same generic
 * error and throttling; an older form without « phone » never erases it.
 */
class StaffPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret-2026-ok';

    private function manager(): User
    {
        return User::factory()->create();
    }

    private function createStaff(array $data)
    {
        return $this->actingAs($this->manager())->post('/admin/users', [
            'name' => 'Awa Koné', 'role_id' => Role::where('slug', 'reception')->value('id'),
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, ...$data,
        ]);
    }

    // ---- Staff form --------------------------------------------------------------------------

    public function test_an_account_needs_an_email_or_a_phone_but_not_both(): void
    {
        $this->createStaff(['email' => '', 'phone' => ''])->assertSessionHasErrors(['email' => 'Renseignez au moins un e-mail ou un numéro de téléphone : il sert à se connecter.']);
        $this->assertSame(1, User::count());

        // Phone only, typed with spaces → stored as 10 digits, no e-mail.
        $this->createStaff(['email' => '', 'phone' => '07 11 22 33 44'])->assertSessionHasNoErrors();
        $awa = User::where('phone', '0711223344')->sole();
        $this->assertNull($awa->email);
        $this->assertTrue($awa->must_change_password);

        // E-mail only still works, and both together.
        $this->createStaff(['name' => 'Koffi', 'email' => 'koffi@hotel.ci'])->assertSessionHasNoErrors();
        $this->createStaff(['name' => 'Yao', 'email' => 'yao@hotel.ci', 'phone' => '05.44.33.22.11'])->assertSessionHasNoErrors();
        $this->assertSame('0544332211', User::where('email', 'yao@hotel.ci')->value('phone'));

        $this->get(route('admin.users.index'))->assertOk()->assertSee('0711223344')->assertSee('<th>Téléphone</th>', false);
    }

    public function test_the_phone_is_exactly_ten_digits_without_international_prefix(): void
    {
        foreach (['071122334', '07112233445', '+225 07 11 22 33 44', '00225 07 11 22 33 44', '07 11 22 33 4a', '+0711223344'] as $phone) {
            $this->createStaff(['email' => 'x@hotel.ci', 'phone' => $phone])
                ->assertSessionHasErrors(['phone' => 'Le numéro de téléphone doit comporter exactement 10 chiffres, sans indicatif international (ex. 07 12 34 56 78).']);
        }
        $this->assertSame(0, User::where('email', 'x@hotel.ci')->count());
    }

    public function test_a_phone_is_unique_among_staff_but_may_match_a_client(): void
    {
        Guest::factory()->create(['phone' => '0711223344']);
        $this->createStaff(['email' => '', 'phone' => '0711223344'])->assertSessionHasNoErrors(); // same as a client: fine

        $this->createStaff(['name' => 'Autre', 'email' => '', 'phone' => '07 11 22 33 44'])
            ->assertSessionHasErrors(['phone' => 'Ce numéro de téléphone est déjà utilisé par un autre compte du personnel.']);
    }

    public function test_existing_accounts_get_a_phone_when_edited_and_a_form_without_phone_keeps_it(): void
    {
        $manager = $this->manager();
        $staff = User::factory()->reception()->create(['email' => 'ancien@hotel.ci']);
        $form = ['name' => $staff->name, 'email' => $staff->email, 'role_id' => $staff->role_id, 'is_active' => 1];

        // Edited without a phone (as before): still valid.
        $this->actingAs($manager)->put(route('admin.users.update', $staff), $form)->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->phone);

        $this->put(route('admin.users.update', $staff), [...$form, 'phone' => '0102030405'])->assertSessionHasNoErrors();
        $this->assertSame('0102030405', $staff->fresh()->phone);

        // A form that doesn't send « phone » leaves it; the e-mail can go once there's a phone.
        $this->put(route('admin.users.update', $staff), $form)->assertSessionHasNoErrors();
        $this->assertSame('0102030405', $staff->fresh()->phone);
        $this->put(route('admin.users.update', $staff), [...$form, 'email' => ''])->assertSessionHasNoErrors();
        $this->assertNull($staff->fresh()->email);

        // …but not both.
        $this->put(route('admin.users.update', $staff), [...$form, 'email' => '', 'phone' => ''])->assertSessionHasErrors('email');
        $this->assertSame('0102030405', $staff->fresh()->phone);
    }

    // ---- Login ------------------------------------------------------------------------------

    public function test_staff_sign_in_with_their_email_or_their_phone(): void
    {
        $user = User::factory()->create(['email' => 'awa@hotel.ci', 'phone' => '0711223344', 'password' => self::PASSWORD]);

        $this->get('/admin/login')->assertOk()->assertSee('E-mail ou téléphone')->assertSee('autocomplete="username"', false);

        foreach (['07 11 22 33 44', '0711223344', '07.11.22.33.44', 'awa@hotel.ci'] as $identifier) {
            $this->post('/admin/login', ['email' => $identifier, 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post('/admin/logout');
        }

        // A phone-only account.
        $phoneOnly = User::factory()->create(['email' => null, 'phone' => '0544332211', 'password' => self::PASSWORD]);
        $this->post('/admin/login', ['email' => '05 44 33 22 11', 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($phoneOnly);
    }

    public function test_failures_look_the_same_and_are_throttled_whatever_the_identifier(): void
    {
        User::factory()->create(['email' => 'awa@hotel.ci', 'phone' => '0711223344', 'password' => self::PASSWORD]);
        User::factory()->inactive()->create(['email' => 'parti@hotel.ci', 'phone' => '0799999999', 'password' => self::PASSWORD]);

        // Wrong password, unknown number, malformed or international number, deactivated account:
        // always the same message, never a hint that an account exists.
        foreach ([['0711223344', 'mauvais'], ['0700000000', self::PASSWORD], ['+225 07 11 22 33 44', self::PASSWORD], ['abc', self::PASSWORD], ['0799999999', self::PASSWORD]] as [$identifier, $password]) {
            $this->post('/admin/login', ['email' => $identifier, 'password' => $password])->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);
            $this->assertGuest();
        }

        // Five failures on a number (typed in any format) lock it, even with the right password.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $i % 2 ? '07 11 22 33 44' : '0711223344', 'password' => 'mauvais']);
        }
        $this->post('/admin/login', ['email' => '0711223344', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Arrays / missing fields are refused cleanly.
        $this->post('/admin/login', ['email' => ['0711223344'], 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['password' => self::PASSWORD])->assertSessionHasErrors(['email' => 'Saisissez votre e-mail ou votre numéro de téléphone.']);
    }

    // ---- Mon profil -------------------------------------------------------------------------

    public function test_staff_edit_their_own_phone_with_their_password(): void
    {
        $user = User::factory()->create(['email' => 'awa@hotel.ci', 'password' => self::PASSWORD]);
        $this->actingAs($user);

        $this->put(route('admin.profile.update'), ['name' => $user->name, 'email' => 'awa@hotel.ci', 'phone' => '0711223344'])
            ->assertSessionHasErrors('confirm_password');
        $this->assertNull($user->fresh()->phone);

        $this->put(route('admin.profile.update'), ['name' => $user->name, 'email' => 'awa@hotel.ci', 'phone' => '07 11 22 33 44', 'confirm_password' => self::PASSWORD])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'awa@hotel.ci ou 0711223344'));
        $this->assertSame('0711223344', $user->fresh()->phone);
        $this->get(route('admin.profile.edit'))->assertOk()->assertSee('Téléphone de connexion')->assertSee('value="0711223344"', false);

        // Same phone again: no password needed; e-mail removed once a phone exists; never both.
        $this->put(route('admin.profile.update'), ['name' => 'Awa K.', 'email' => 'awa@hotel.ci', 'phone' => '0711223344'])->assertSessionHasNoErrors();
        $this->put(route('admin.profile.update'), ['name' => 'Awa K.', 'email' => '', 'phone' => '0711223344', 'confirm_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->email);
        $this->put(route('admin.profile.update'), ['name' => 'Awa K.', 'email' => '', 'phone' => '', 'confirm_password' => self::PASSWORD])->assertSessionHasErrors('email');


        // Another staff member's number is refused.
        User::factory()->create(['phone' => '0544332211']);
        $this->put(route('admin.profile.update'), ['name' => 'Awa K.', 'email' => '', 'phone' => '0544332211', 'confirm_password' => self::PASSWORD])->assertSessionHasErrors('phone');
        $this->assertSame('0711223344', $user->fresh()->phone);
    }
}
