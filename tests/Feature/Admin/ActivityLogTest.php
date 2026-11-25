<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\TrashedFile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * « Journal d'activité » (V2): who did what, when — append-only, gated by activity.view.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 10:00:00');
    }

    private function assign(User $user, Room $room, int $hours = 2): Reservation
    {
        $this->actingAs($user)->post("/admin/rooms/{$room->id}/assign", [
            'new_first_name' => 'Awa', 'new_last_name' => 'Koné', 'new_phone' => '0102030405',
            'pricing_mode' => 'heure', 'duration' => $hours, 'adults' => 2, 'children' => 0,
            'payments' => [['method' => 'especes', 'amount' => 5000 * $hours, 'received' => 5000 * $hours]],
        ])->assertRedirect(route('admin.rooms.board'));

        return Reservation::query()->latest('id')->firstOrFail();
    }

    private function actions(): array
    {
        return ActivityLog::query()->orderBy('id')->pluck('action')->all();
    }

    public function test_an_affectation_its_payment_extension_and_check_out_are_journalled_with_their_author(): void
    {
        $user = User::factory()->create(['name' => 'Awa Réception']);
        $room = Room::factory()->create(['number' => '101', 'room_type_id' => RoomType::factory()->create(['hourly_rate' => 5000])->id]);

        $stay = $this->assign($user, $room);
        $actions = $this->actions();
        $this->assertContains('affectation.created', $actions);
        $this->assertContains('affectation.check_in', $actions);
        $this->assertContains('payment.created', $actions);
        $this->assertNotContains('room.updated', $actions, 'the automatic « Occupée » is not a separate line');

        $line = ActivityLog::query()->where('action', 'payment.created')->sole();
        $this->assertSame($user->id, $line->user_id);
        $this->assertSame('Awa Réception', $line->user_name);
        $this->assertSame('encaissement', $line->category);
        $this->assertSame($stay->reference(), $line->reference);
        $this->assertStringContainsString('10 000 F', $line->description);
        $this->assertStringContainsString('Espèces', $line->description);
        $this->assertSame('127.0.0.1', $line->ip);

        $this->actingAs($user)->post(route('admin.affectations.extend', $stay), ['extra' => 1, 'payments' => [['method' => 'wave', 'amount' => 5000]]])->assertRedirect();
        $ext = ActivityLog::query()->where('action', 'affectation.extended')->sole();
        $this->assertStringContainsString('3 heures', $ext->description);
        $this->assertContains(['label' => 'Durée', 'old' => '2 heures', 'new' => '3 heures'], $ext->changes);

        $this->actingAs($user)->post(route('admin.affectations.check-out', $stay))->assertRedirect();
        $this->assertStringContainsString('Sortie : '.$stay->reference(), ActivityLog::query()->where('action', 'affectation.check_out')->sole()->description);
        $this->assertNotContains('room.updated', $this->actions());
    }

    public function test_logins_are_journalled_and_the_password_never_is(): void
    {
        $user = User::factory()->create(['email' => 'awa@hotel.ci', 'password' => 'secret-1234', 'name' => 'Awa']);

        $this->post(route('admin.login.attempt'), ['email' => 'awa@hotel.ci', 'password' => 'mauvais-mdp']);
        $this->post(route('admin.login.attempt'), ['email' => 'awa@hotel.ci', 'password' => 'secret-1234'])->assertRedirect();
        $this->post(route('admin.logout'));

        $this->assertSame(['auth.failed', 'auth.login', 'auth.logout'], array_values(array_filter($this->actions(), fn ($a) => str_starts_with($a, 'auth.'))));
        $this->assertSame($user->id, ActivityLog::query()->where('action', 'auth.login')->value('user_id'));
        $everything = ActivityLog::query()->get()->toJson();
        $this->assertStringNotContainsString('mauvais-mdp', $everything);
        $this->assertStringNotContainsString('secret-1234', $everything);
    }

    public function test_configuration_changes_keep_before_and_after(): void
    {
        $manager = User::factory()->create();
        $type = RoomType::factory()->create(['name' => 'Deluxe', 'hourly_rate' => 5000]);

        $this->actingAs($manager)->put(route('admin.room-types.update', $type), [
            'name' => 'Deluxe', 'hourly_rate' => '6 000', 'day_rate' => $type->day_rate, 'night_rate' => $type->night_rate, 'stay_rate' => $type->stay_rate,
            'capacity_adults' => 2, 'capacity_children' => 0, 'is_active' => 1,
        ])->assertRedirect();
        $line = ActivityLog::query()->where('action', 'room_type.updated')->sole();
        $this->assertSame('Modification : type de chambre « Deluxe »', $line->description);
        $this->assertContains(['label' => 'Tarif passage', 'old' => '5 000 F', 'new' => '6 000 F'], $line->changes);

        $this->actingAs($manager)->put(route('admin.settings.update'), [
            'business_day_start' => '06:00', 'night_from' => '22:00', 'night_until' => '12:00', 'day_hours' => 6, 'stay_min_hours' => 24, 'cleaning_buffer_minutes' => 45,
        ])->assertRedirect();
        $this->assertContains(['label' => 'Délai de ménage (min)', 'old' => '30', 'new' => '45'], ActivityLog::query()->where('action', 'settings.updated')->sole()->changes);
    }

    public function test_staff_password_and_role_permission_changes(): void
    {
        $user = User::factory()->reception()->create(['name' => 'Bintou']);
        $user->forceFill(['password' => 'nouveau-mdp'])->save();
        $line = ActivityLog::query()->where('action', 'user.password')->sole();
        $this->assertSame('Mot de passe modifié : compte de Bintou', $line->description);
        $this->assertStringNotContainsString('$2y$', json_encode($line->changes));

        $role = Role::query()->where('is_system', false)->firstOrFail();
        $role->syncPermissions([...$role->permissionValues(), 'activity.view']);
        $perm = ActivityLog::query()->where('action', 'role.permissions')->sole();
        $this->assertContains(['label' => 'Ajoutée', 'old' => null, 'new' => "Consulter le journal d'activité (qui a fait quoi, quand) et l'exporter"], $perm->changes);
    }

    public function test_client_personal_data_is_never_written_in_the_journal(): void
    {
        $guest = Guest::factory()->create(['phone' => '0700000001']);
        $guest->update(['phone' => '0799999999']);

        $line = ActivityLog::query()->where('action', 'guest.updated')->sole();
        $this->assertSame([['label' => 'Téléphone', 'masked' => true]], $line->changes);
        $this->assertStringNotContainsString('0799999999', ActivityLog::query()->get()->toJson());
    }

    public function test_trash_and_restore_are_journalled(): void
    {
        $manager = User::factory()->create();
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['hourly_rate' => 5000])->id]);
        $stay = $this->assign($manager, $room);
        $ref = $stay->reference();

        $this->actingAs($manager)->delete(route('admin.affectations.destroy-many'), ['ids' => [$stay->id], 'reason' => 'Essai'])->assertRedirect();
        $this->assertStringContainsString($ref.' mise à la corbeille', ActivityLog::query()->where('action', 'trash.deleted')->sole()->description);
        $this->assertStringContainsString('motif : Essai', ActivityLog::query()->where('action', 'trash.deleted')->sole()->description);

        $entry = TrashedFile::query()->sole();
        $this->actingAs($manager)->post(route('admin.trash.restore'), ['ids' => [$entry->id]])->assertRedirect();
        $this->assertSame($ref, ActivityLog::query()->where('action', 'trash.restored')->sole()->reference);
    }

    public function test_journal_page_access_filters_and_escaping(): void
    {
        $manager = User::factory()->create();
        Audit::record('test.xss', 'client', 'Création : client « <script>alert(1)</script> »', null, null, 'AFF-000777');

        $this->actingAs(User::factory()->reception()->create())->get(route('admin.activity.index'))->assertForbidden();
        $this->actingAs(User::factory()->reception()->create())->get(route('admin.activity.export'))->assertForbidden();

        $this->actingAs($manager)->get(route('admin.activity.index'))->assertOk()
            ->assertSee('AFF-000777')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);

        $this->actingAs($manager)->get(route('admin.activity.index', ['q' => 'aff-000777']))->assertSee('AFF-000777');
        $this->actingAs($manager)->get(route('admin.activity.index', ['categorie' => 'connexion']))->assertDontSee('AFF-000777');
        // Forged values: ignored, never a crash.
        $this->actingAs($manager)->get(route('admin.activity.index').'?du[]=x&par[]=1&categorie[]=a&q[]=b')->assertOk();
        $this->actingAs($manager)->get(route('admin.activity.index', ['du' => 'pas-une-date', 'par' => '1 or 1=1', 'categorie' => 'inconnue']))->assertOk()->assertSee('AFF-000777');
    }

    public function test_a_role_granted_by_the_manager_can_read_it(): void
    {
        $role = Role::query()->where('is_system', false)->firstOrFail();
        $role->syncPermissions([...$role->permissionValues(), 'activity.view']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($user->fresh())->get(route('admin.activity.index'))->assertOk();
    }

    public function test_export_is_journalled_and_safe_for_excel(): void
    {
        $manager = User::factory()->create();
        Audit::record('test.csv', 'client', '=HYPERLINK("http://evil")');

        $csv = $this->actingAs($manager)->get(route('admin.activity.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertContains('export.activity', $this->actions());
    }

    public function test_the_journal_cannot_be_changed_nor_deleted(): void
    {
        Audit::record('test.line', 'configuration', 'Ligne');
        $line = ActivityLog::query()->sole();

        try {
            $line->forceFill(['description' => 'Falsifiée'])->save();
            $this->fail('update allowed');
        } catch (LogicException) {
        }
        try {
            $line->delete();
            $this->fail('delete allowed');
        } catch (LogicException) {
        }
        $this->assertSame('Ligne', $line->fresh()->description);

        $this->actingAs(User::factory()->create())->put('/admin/journal/'.$line->id)->assertStatus(404);
        $this->actingAs(User::factory()->create())->delete('/admin/journal/'.$line->id)->assertStatus(404);
    }

    public function test_a_journal_failure_never_breaks_the_action(): void
    {
        Schema::drop('activity_logs');
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['hourly_rate' => 5000])->id]);

        $stay = $this->assign(User::factory()->create(), $room);
        $this->assertSame(RoomStatus::Occupee, $room->fresh()->status);
        $this->assertNotNull($stay->invoice);
    }
}
