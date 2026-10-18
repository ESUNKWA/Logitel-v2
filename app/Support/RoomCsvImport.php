<?php

namespace App\Support;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Str;

/**
 * Reads a rooms CSV (Configuration → Chambres → Importer) and classifies every line — nothing is
 * written here. Columns (header line, any order, accents/case ignored): numero*, type*, nom,
 * etage, statut, notes. Separator « ; », « , » or tab; UTF-8 (with or without BOM) or
 * Windows-1252 (Excel FR). Every value is read as plain text, never evaluated.
 *
 * A line is « create » (valid and new), « skip » (the room number already exists — existing
 * rooms are never modified, client decision 2026-10-18 — or is repeated in the file) or
 * « error » (with the reasons). Uniqueness ignores case and surrounding spaces
 * (Room::uniqueKey()), for numbers and names, against the database and within the file.
 */
class RoomCsvImport
{
    public const MAX_BYTES = 1024 * 1024;

    public const MAX_ROWS = 1000;

    /** Accepted header names (normalised) → field. */
    private const HEADERS = [
        'numero' => 'number', 'num' => 'number', 'n' => 'number', 'number' => 'number', 'chambre' => 'number',
        'type' => 'type', 'typedechambre' => 'type', 'roomtype' => 'type',
        'nom' => 'name', 'nomaffiche' => 'name', 'nompublic' => 'name', 'name' => 'name',
        'etage' => 'floor', 'floor' => 'floor',
        'statut' => 'status', 'etat' => 'status', 'status' => 'status',
        'notes' => 'notes', 'note' => 'notes', 'remarques' => 'notes',
    ];

    /** Statuses an import may set (never « occupée »: that comes from a stay). */
    private const STATUSES = [
        'disponible' => RoomStatus::Disponible, 'libre' => RoomStatus::Disponible,
        'nettoyage' => RoomStatus::Nettoyage, 'ennettoyage' => RoomStatus::Nettoyage,
        'horsservice' => RoomStatus::HorsService,
    ];

    /**
     * @return array{error: string|null, rows: list<array{line: int, number: string, type: string, type_id: int|null, name: string, floor: string, status: string, status_label: string, notes: string, state: string, messages: list<string>}>}
     */
    public static function analyse(string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            return self::fail('Le fichier dépasse 1 Mo.');
        }
        if (str_contains($content, "\0")) {
            return self::fail("Ce fichier n'est pas un fichier texte CSV.");
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // Excel « CSV » on a French Windows
        }

        $lines = self::parse($content);
        if ($lines === []) {
            return self::fail('Le fichier est vide.');
        }

        $map = [];
        foreach (array_shift($lines) as $index => $header) {
            $field = self::HEADERS[self::normalise($header)] ?? null;
            if ($field !== null && ! in_array($field, $map, true)) {
                $map[$index] = $field;
            }
        }
        $missing = array_diff(['number', 'type'], $map);
        if ($missing !== []) {
            return self::fail('Colonnes obligatoires absentes de la première ligne : '
                .implode(', ', array_map(fn ($f) => $f === 'number' ? '« numero »' : '« type »', $missing))
                .'. Téléchargez le modèle pour voir le format attendu.');
        }

        $lines = array_values(array_filter($lines, fn (array $cells) => implode('', array_map('trim', $cells)) !== ''));
        if (count($lines) > self::MAX_ROWS) {
            return self::fail('Trop de lignes : '.count($lines).' (maximum '.self::MAX_ROWS.' par import).');
        }

        $types = RoomType::query()->get(['id', 'name'])->keyBy(fn (RoomType $t) => Room::uniqueKey($t->name));
        $existingNumbers = Room::query()->pluck('number')->map(fn ($n) => Room::uniqueKey($n))->flip();
        $existingNames = Room::query()->whereNotNull('public_name')->pluck('public_name')->map(fn ($n) => Room::uniqueKey($n))->flip();
        $seenNumbers = [];
        $seenNames = [];
        $rows = [];

        foreach ($lines as $i => $cells) {
            $value = function (string $field) use ($map, $cells): string {
                $index = array_search($field, $map, true);

                return $index === false ? '' : trim((string) ($cells[$index] ?? ''));
            };
            $row = [
                'line' => $i + 2, // header = line 1
                'number' => $value('number'), 'type' => $value('type'), 'type_id' => null,
                'name' => $value('name'), 'floor' => $value('floor'), 'status' => RoomStatus::Disponible->value,
                'status_label' => RoomStatus::Disponible->getLabel(), 'notes' => $value('notes'),
                'state' => 'create', 'messages' => [],
            ];
            $errors = [];

            $numberKey = Room::uniqueKey($row['number']);
            if ($row['number'] === '') {
                $errors[] = 'Numéro manquant.';
            } elseif (mb_strlen($row['number']) > 50) {
                $errors[] = 'Numéro trop long (50 caractères au plus).';
            }

            $type = $types->get(Room::uniqueKey($row['type']));
            if ($row['type'] === '') {
                $errors[] = 'Type manquant.';
            } elseif (! $type) {
                $errors[] = "Type « {$row['type']} » inconnu : créez-le d'abord dans Configuration → Types de chambre.";
            } else {
                $row['type_id'] = $type->id;
                $row['type'] = $type->name;
            }

            if (mb_strlen($row['name']) > 100) {
                $errors[] = 'Nom trop long (100 caractères au plus).';
            }
            if (mb_strlen($row['floor']) > 50) {
                $errors[] = 'Étage trop long (50 caractères au plus).';
            }
            if (mb_strlen($row['notes']) > 1000) {
                $errors[] = 'Notes trop longues (1 000 caractères au plus).';
            }

            $statusText = $value('status');
            if ($statusText !== '') {
                $status = self::STATUSES[self::normalise($statusText)] ?? null;
                if ($status) {
                    $row['status'] = $status->value;
                    $row['status_label'] = $status->getLabel();
                } else {
                    $errors[] = "Statut « {$statusText} » non accepté : disponible, nettoyage ou hors service (laissez vide pour « disponible »).";
                }
            }

            if ($row['number'] !== '' && isset($existingNumbers[$numberKey])) {
                $row['state'] = 'skip';
                $row['messages'][] = 'Cette chambre existe déjà : elle est laissée telle quelle.';
            } elseif ($row['number'] !== '' && isset($seenNumbers[$numberKey])) {
                $row['state'] = 'skip';
                $row['messages'][] = "Numéro déjà présent ligne {$seenNumbers[$numberKey]} du fichier : ligne ignorée.";
            } else {
                $nameKey = Room::uniqueKey($row['name']);
                if ($nameKey !== '' && isset($existingNames[$nameKey])) {
                    $errors[] = "Une chambre s'appelle déjà « {$row['name']} » : le nom doit être unique (laissez-le vide ou changez-le).";
                } elseif ($nameKey !== '' && isset($seenNames[$nameKey])) {
                    $errors[] = "Nom « {$row['name']} » déjà utilisé ligne {$seenNames[$nameKey]} du fichier.";
                }

                if ($errors !== []) {
                    $row['state'] = 'error';
                    $row['messages'] = $errors;
                } else {
                    $seenNumbers[$numberKey] = $row['line'];
                    if ($nameKey !== '') {
                        $seenNames[$nameKey] = $row['line'];
                    }
                }
            }

            $rows[] = $row;
        }

        return ['error' => null, 'rows' => $rows];
    }

    /**
     * The template offered for download (same columns, two examples).
     */
    public static function template(): string
    {
        $type = RoomType::query()->orderBy('name')->value('name') ?? 'Chambre Standard';

        return "\xEF\xBB\xBF".implode("\r\n", [
            'numero;type;nom;etage;statut;notes',
            "101;{$type};;1;disponible;",
            "102;{$type};;1;;Vue piscine",
        ])."\r\n";
    }

    /**
     * @return list<list<string>>
     */
    private static function parse(string $content): array
    {
        $first = strtok($content, "\n") ?: '';
        $delimiter = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($first, $d))->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $lines = [];
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $lines[] = array_map(fn ($c) => (string) $c, $cells);
        }
        fclose($stream);

        return $lines;
    }

    private static function normalise(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(trim($value))));
    }

    private static function fail(string $message): array
    {
        return ['error' => $message, 'rows' => []];
    }
}
