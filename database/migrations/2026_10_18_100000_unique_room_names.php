<?php

use App\Models\Room;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/*
 * Room numbers and names (public_name) unique ignoring case, accents' case and spaces (client
 * request, 2026-10-18). The comparison key is computed in PHP (Room::uniqueKey()) and stored in
 * number_key / name_key, each with a unique index: SQL lower() can't be trusted for this (with
 * a « C » collation, PostgreSQL leaves « É » as is). Safe on existing data — this runs on
 * deployment:
 * - blank names become NULL (« no name »: the room shows its type's name), others are trimmed;
 * - duplicate names already there are renamed « Name (N°number) » (the oldest room keeps it);
 * - a number whose key repeats an older room's keeps a NULL key (logged): the existing exact
 *   unique index on `number` stays, and the forms check keys anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('number_key', 50)->nullable()->after('number');
            $table->string('name_key', 100)->nullable()->after('public_name');
        });

        $numbers = [];
        $names = [];
        foreach (DB::table('rooms')->orderBy('id')->get(['id', 'number', 'public_name']) as $room) {
            $numberKey = Room::uniqueKey($room->number);
            if (isset($numbers[$numberKey])) {
                Log::warning("Numéro de chambre en double (casse/espaces) : N°{$room->number}, à corriger à la main.");
                $numberKey = null;
            } else {
                $numbers[$numberKey] = true;
            }

            $name = trim((string) $room->public_name) === '' ? null : trim((string) $room->public_name);
            if ($name !== null && isset($names[Room::uniqueKey($name)])) {
                $base = mb_substr($name, 0, 80).' (N°'.$room->number.')';
                $renamed = $base;
                for ($i = 2; isset($names[Room::uniqueKey($renamed)]); $i++) {
                    $renamed = $base.' '.$i;
                }
                Log::warning("Nom de chambre en double renommé : chambre N°{$room->number} « {$name} » → « {$renamed} ».");
                $name = $renamed;
            }
            if ($name !== null) {
                $names[Room::uniqueKey($name)] = true;
            }

            DB::table('rooms')->where('id', $room->id)->update([
                'number_key' => $numberKey,
                'public_name' => $name,
                'name_key' => $name === null ? null : Room::uniqueKey($name),
            ]);
        }

        Schema::table('rooms', function (Blueprint $table) {
            $table->unique('number_key');
            $table->unique('name_key');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique(['number_key']);
            $table->dropUnique(['name_key']);
            $table->dropColumn(['number_key', 'name_key']);
        });
    }
};
