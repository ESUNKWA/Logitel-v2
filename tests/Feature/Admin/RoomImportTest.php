<?php

namespace Tests\Feature\Admin;

use App\Enums\RoomStatus;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Room numbers and names unique (case and spaces ignored) in forms, the database and the CSV
 * import; the import analyses first (nothing saved), creates only new valid lines, never
 * modifies existing rooms, is one-time and manager-only.
 */
class RoomImportTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $deluxe;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deluxe = RoomType::factory()->create(['name' => 'Chambre Deluxe']);
        RoomType::factory()->create(['name' => 'Suite']);
        $this->manager = User::factory()->create();
    }

    private function csv(string $content, string $name = 'chambres.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function preview(string $content)
    {
        return $this->actingAs($this->manager)->post(route('admin.rooms.import.preview'), ['file' => $this->csv($content)]);
    }

    private function roomForm(array $data = []): array
    {
        return ['room_type_id' => $this->deluxe->id, 'number' => '201', 'status' => 'disponible', ...$data];
    }

    // ---- Uniqueness in the forms and the database -------------------------------------------

    public function test_room_numbers_and_names_are_unique_ignoring_case_and_spaces(): void
    {
        $this->actingAs($this->manager);
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => 'A-1', 'public_name' => 'Suite Royale']);

        $this->post(route('admin.rooms.store'), $this->roomForm(['number' => ' a-1 ']))->assertSessionHasErrors('number');
        $this->post(route('admin.rooms.store'), $this->roomForm(['presentation' => 1, 'public_name' => ' suite ROYALE ']))->assertSessionHasErrors('public_name');
        $this->assertSame(1, Room::count());

        // A blank name never clashes; names are stored trimmed.
        $this->post(route('admin.rooms.store'), $this->roomForm(['number' => '201', 'presentation' => 1, 'public_name' => '']))->assertSessionHasNoErrors();
        $this->post(route('admin.rooms.store'), $this->roomForm(['number' => '202', 'presentation' => 1, 'public_name' => '  Vue Lagune  ']))->assertSessionHasNoErrors();
        $this->assertNull(Room::where('number', '201')->value('public_name'));
        $this->assertSame('Vue Lagune', Room::where('number', '202')->value('public_name'));

        // Editing a room keeps its own number and name.
        $room = Room::where('number', 'A-1')->first();
        $this->put(route('admin.rooms.update', $room), $this->roomForm(['number' => 'A-1', 'presentation' => 1, 'public_name' => 'Suite Royale']))->assertSessionHasNoErrors();
        $this->put(route('admin.rooms.update', $room), $this->roomForm(['number' => 'A-1', 'presentation' => 1, 'public_name' => 'vue lagune']))->assertSessionHasErrors('public_name');
    }

    public function test_the_database_refuses_duplicate_names_too(): void
    {
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'public_name' => 'Suite Royale']);

        // Accented capitals and repeated spaces too (SQL lower() would miss « É » with a C collation).
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'public_name' => 'Côté Été']);
        foreach (['SUITE ROYALE', 'CÔTÉ ÉTÉ', '  côté   été '] as $name) {
            try {
                Room::factory()->create(['room_type_id' => $this->deluxe->id, 'public_name' => $name]);
                $this->fail("« {$name} » should have been refused.");
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(2, Room::count());
            }
        }
        $this->assertTrue(Room::nameTaken('CÔTÉ ÉTÉ'));
        $this->assertTrue(Room::numberTaken(' '.Room::first()->number.' '));
    }

    public function test_a_race_on_the_same_name_gives_a_message_not_an_error_page(): void
    {
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => 'X-1', 'public_name' => 'Suite Royale']);
        // The form check passes (another request « wins » just after it): the database still says no.
        $this->app->bind(RoomRequest::class, fn () => new class extends RoomRequest
        {
            public function rules(): array
            {
                return collect(parent::rules())->map(fn ($rules) => array_values(array_filter((array) $rules, fn ($r) => ! $r instanceof \Closure)))->all();
            }
        });

        $this->actingAs($this->manager)->from(route('admin.rooms.create'))
            ->post(route('admin.rooms.store'), $this->roomForm(['number' => 'X-2', 'presentation' => 1, 'public_name' => 'Suite Royale']))
            ->assertRedirect(route('admin.rooms.create'))
            ->assertSessionHasErrors('number');
    }

    public function test_the_migration_renames_existing_duplicates_instead_of_failing(): void
    {
        $migration = require database_path('migrations/2026_10_18_100000_unique_room_names.php');
        $migration->down();
        DB::table('rooms')->insert([
            ['room_type_id' => $this->deluxe->id, 'number' => '1', 'public_name' => 'Suite Royale', 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => '2', 'public_name' => ' suite royale ', 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => '3', 'public_name' => '   ', 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => '4', 'public_name' => null, 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => '5', 'public_name' => 'Côté Été', 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => '6', 'public_name' => 'CÔTÉ  ÉTÉ', 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => 'a-7', 'public_name' => null, 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
            ['room_type_id' => $this->deluxe->id, 'number' => 'A-7', 'public_name' => null, 'status' => 'disponible', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration->up(); // never fails on existing duplicates

        $this->assertSame(
            ['1' => 'Suite Royale', '2' => 'suite royale (N°2)', '3' => null, '4' => null, '5' => 'Côté Été', '6' => 'CÔTÉ  ÉTÉ (N°6)', 'a-7' => null, 'A-7' => null],
            DB::table('rooms')->orderBy('id')->pluck('public_name', 'number')->all(),
        );
        $this->assertSame(['1' => '1', 'a-7' => 'a-7', 'A-7' => null], DB::table('rooms')->whereIn('number', ['1', 'a-7', 'A-7'])->orderBy('id')->pluck('number_key', 'number')->all());
        $this->assertSame('côté été', DB::table('rooms')->where('number', '5')->value('name_key'));

        $this->expectException(UniqueConstraintViolationException::class);
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => '8', 'public_name' => 'côté été']);
    }

    // ---- CSV import -----------------------------------------------------------------------------

    public function test_the_preview_classifies_every_line_and_saves_nothing(): void
    {
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => '101', 'public_name' => 'Suite Royale']);

        $page = $this->preview(implode("\n", [
            'Numéro;Type;Nom;Étage;Statut;Notes',
            '101;Chambre Deluxe;;1;;',                 // exists → skip
            ' 102 ;chambre deluxe;Vue Lagune;1;;Calme', // new (case/spaces ignored)
            '102;Suite;;2;;',                           // repeated in the file → skip
            '103;Inconnu;;1;;',                         // unknown type → error
            '104;Suite;;1;occupée;',                    // « occupée » not allowed → error
            '105;Suite;suite royale;1;;',               // name taken in the database → error
            '106;Suite;VUE LAGUNE;1;;',                 // name repeated in the file → error
            ';Suite;;;;',                               // no number → error
            '107;Suite;;;Hors service;',                // new, status label accepted
            '',                                         // blank line ignored
        ]))->assertOk()->assertViewIs('admin.rooms.import');

        $rows = collect($page->viewData('analysis')['rows'])->keyBy('line');
        $this->assertSame(['skip', 'create', 'skip', 'error', 'error', 'error', 'error', 'error', 'create'], $rows->pluck('state')->values()->all());
        $this->assertSame(['102', 'Chambre Deluxe', 'Vue Lagune'], [$rows[3]['number'], $rows[3]['type'], $rows[3]['name']]);
        $this->assertSame('hors_service', $rows[10]['status']);
        $this->assertStringContainsString('inconnu', $rows[5]['messages'][0]);
        $this->assertSame(1, Room::count()); // nothing saved yet
        $page->assertSee('Importer 2 chambre(s)')->assertSee('Cette chambre existe déjà');
    }

    public function test_confirming_creates_the_new_rooms_once(): void
    {
        $page = $this->preview("numero,type,nom,etage,statut,notes\n201,Suite,La Lagune,2,nettoyage,Vue mer\n202,Chambre Deluxe,,2,,\n");
        $token = $page->viewData('token');

        $this->post(route('admin.rooms.import.store'), ['token' => $token])->assertRedirect(route('admin.rooms.index'))
            ->assertSessionHas('status', '2 chambre(s) importée(s).');
        $room = Room::where('number', '201')->sole();
        $this->assertSame(['La Lagune', '2', RoomStatus::Nettoyage, 'Vue mer', false], [$room->public_name, $room->floor, $room->status, $room->notes, $room->is_published]);

        // One-time: replaying the confirmation creates nothing more.
        $this->post(route('admin.rooms.import.store'), ['token' => $token])->assertRedirect(route('admin.rooms.import'))->assertSessionHasErrors('file');
        $this->assertSame(2, Room::count());
    }

    public function test_a_room_created_between_preview_and_confirmation_is_skipped_and_existing_rooms_are_untouched(): void
    {
        $existing = Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => '300', 'floor' => '3', 'notes' => 'Ne pas toucher']);
        $token = $this->preview("numero;type;etage;notes\n300;Suite;9;Écrasé ?\n301;Suite;3;\n302;Suite;3;\n")->viewData('token');
        Room::factory()->create(['room_type_id' => $this->deluxe->id, 'number' => '301']); // created meanwhile by someone else

        $this->post(route('admin.rooms.import.store'), ['token' => $token])
            ->assertSessionHas('status', '1 chambre(s) importée(s). Ignorée(s) car créée(s) entre-temps : N°301.');
        $this->assertSame(['3', 'Ne pas toucher', $this->deluxe->id], [$existing->fresh()->floor, $existing->fresh()->notes, $existing->fresh()->room_type_id]);
        $this->assertTrue(Room::where('number', '302')->exists());
    }

    public function test_excel_files_are_read_whatever_their_encoding_and_separator(): void
    {
        // Excel « CSV UTF-8 »: BOM + semicolons.
        $rows = $this->preview("\xEF\xBB\xBFnumero;type;nom\n401;Suite;Chambre Été\n")->viewData('analysis')['rows'];
        $this->assertSame(['create', 'Chambre Été'], [$rows[0]['state'], $rows[0]['name']]);

        // Excel « CSV » on a French Windows: Windows-1252.
        $rows = $this->preview(mb_convert_encoding("numéro;type;nom\n402;Suite;Côté jardin\n", 'Windows-1252', 'UTF-8'))->viewData('analysis')['rows'];
        $this->assertSame(['402', 'Côté jardin'], [$rows[0]['number'], $rows[0]['name']]);

        // Quoted values with separators inside; a value looking like a formula is plain text.
        $rows = $this->preview("numero,type,notes\n403,Suite,\"Vue, calme\"\n404,Suite,=1+1\n")->viewData('analysis')['rows'];
        $this->assertSame(['Vue, calme', '=1+1'], [$rows[0]['notes'], $rows[1]['notes']]);
    }

    public function test_bad_files_are_refused_with_a_clear_message(): void
    {
        $this->preview("numero;etage\n101;1\n")->assertSessionHasErrors(['file' => 'Colonnes obligatoires absentes de la première ligne : « type ». Téléchargez le modèle pour voir le format attendu.']);
        $this->preview('')->assertSessionHasErrors('file');
        $this->preview("numero;type\n".str_repeat("1;Suite\n", 1001))->assertSessionHasErrors(['file' => 'Trop de lignes : 1001 (maximum 1000 par import).']);
        $this->preview("numero;type\n\0\0binary")->assertSessionHasErrors('file');
        $this->actingAs($this->manager)->post(route('admin.rooms.import.preview'), ['file' => UploadedFile::fake()->image('photo.png')])->assertSessionHasErrors('file');
        $this->actingAs($this->manager)->post(route('admin.rooms.import.preview'), ['file' => UploadedFile::fake()->create('gros.csv', 2048, 'text/csv')])->assertSessionHasErrors('file');
        $this->assertSame(0, Room::count());
    }

    public function test_the_page_template_and_permissions(): void
    {
        $this->actingAs($this->manager)->get(route('admin.rooms.index'))->assertOk()->assertSee(route('admin.rooms.import'), false);
        $this->get(route('admin.rooms.import'))->assertOk()->assertSee('« Chambre Deluxe »', false)->assertSee('Télécharger le modèle');
        $template = $this->get(route('admin.rooms.import.template'))->assertOk();
        $this->assertStringContainsString('modele-import-chambres.csv', $template->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBFnumero;type;nom;etage;statut;notes", $template->getContent());

        // The template imports as is.
        $rows = $this->preview(substr($template->getContent(), 0))->viewData('analysis')['rows'];
        $this->assertSame(['create', 'create'], array_column($rows, 'state'));

        // Réception (rooms.edit only): no import.
        $this->actingAs(User::factory()->reception()->create());
        $this->get(route('admin.rooms.import'))->assertForbidden();
        $this->get(route('admin.rooms.import.template'))->assertForbidden();
        $this->post(route('admin.rooms.import.preview'), ['file' => $this->csv("numero;type\n1;Suite\n")])->assertForbidden();
        $this->post(route('admin.rooms.import.store'), ['token' => 'x'])->assertForbidden();
        $this->get(route('admin.rooms.index'))->assertDontSee(route('admin.rooms.import'), false);
    }
}
