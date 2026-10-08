<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Last line of defence against double bookings, under the application checks (Room::lockAvailable):
 * PostgreSQL itself refuses two active stays/bookings of the same room whose periods overlap
 * ([check_in, check_out), so back-to-back is fine). Needs the btree_gist extension.
 * PostgreSQL only — the test suite (SQLite) relies on the application-level lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap
            EXCLUDE USING gist (room_id WITH =, tsrange(check_in, check_out, '[)') WITH &&)
            WHERE (status IN ('en_attente', 'confirmee', 'enregistree'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
    }
};
