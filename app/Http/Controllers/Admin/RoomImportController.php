<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\RoomCsvImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Rooms CSV import, in two steps: the file is analysed and shown line by line (nothing saved),
 * then « Importer » creates the valid lines in one transaction, re-checking each one against
 * the database (a room created meanwhile is skipped). Existing rooms are never modified.
 * The analysed lines wait in the session under a one-time token. `rooms.manage` only.
 */
class RoomImportController extends Controller
{
    private const SESSION_KEY = 'room_import';

    public function create(Request $request): View
    {
        return view('admin.rooms.import', ['analysis' => null, 'token' => null, 'roomTypes' => RoomType::query()->orderBy('name')->pluck('name')]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(RoomCsvImport::MAX_BYTES / 1024)]],
            ['file.required' => 'Choisissez un fichier CSV.', 'file.mimes' => 'Le fichier doit être un CSV (texte).', 'file.max' => 'Le fichier dépasse 1 Mo.'],
        );

        $analysis = RoomCsvImport::analyse((string) file_get_contents($request->file('file')->getRealPath()));
        if ($analysis['error']) {
            return back()->withErrors(['file' => $analysis['error']]);
        }

        $token = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'rows' => array_values(array_filter($analysis['rows'], fn (array $r) => $r['state'] === 'create')),
        ]);

        return view('admin.rooms.import', ['analysis' => $analysis, 'token' => $token, 'roomTypes' => RoomType::query()->orderBy('name')->pluck('name')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY); // one-time: a reload can't import twice
        if (! is_array($pending) || ! hash_equals((string) ($pending['token'] ?? ''), (string) $request->input('token'))) {
            return redirect()->route('admin.rooms.import')->withErrors(['file' => "Cet aperçu n'est plus valable : envoyez à nouveau le fichier."]);
        }

        $created = 0;
        $skipped = [];
        DB::transaction(function () use ($pending, &$created, &$skipped) {
            $typeIds = RoomType::query()->pluck('id')->flip();
            foreach ($pending['rows'] as $row) {
                // Re-checked now: someone may have created the room (or used the name) since the preview.
                if (! isset($typeIds[$row['type_id']]) || Room::numberTaken($row['number']) || Room::nameTaken($row['name'])) {
                    $skipped[] = $row['number'];

                    continue;
                }
                try {
                    DB::transaction(fn () => Room::create([
                        'room_type_id' => $row['type_id'],
                        'number' => $row['number'],
                        'public_name' => $row['name'] !== '' ? $row['name'] : null,
                        'floor' => $row['floor'] !== '' ? $row['floor'] : null,
                        'status' => RoomStatus::from($row['status']),
                        'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                        'is_published' => false,
                    ]));
                    $created++;
                } catch (UniqueConstraintViolationException) {
                    $skipped[] = $row['number']; // taken at the very same moment: the database said no
                }
            }
        });

        return redirect()->route('admin.rooms.index')->with('status', $created.' chambre(s) importée(s).'
            .($skipped ? ' Ignorée(s) car créée(s) entre-temps : N°'.implode(', N°', $skipped).'.' : ''));
    }

    public function template(): Response
    {
        return response(RoomCsvImport::template(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-chambres.csv"',
        ]);
    }
}
