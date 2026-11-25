<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * « Clôture de caisse » removed from the project (client request, 2026-11-24): its permissions are
 * taken back from every role, and the `cash_closings` table is dropped — only when it is empty, so
 * no closing already declared is ever lost (a non-empty table is left untouched and unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permission_role')->whereIn('permission', ['cash.close', 'cash.review'])->delete();

        if (! Schema::hasTable('cash_closings')) {
            return;
        }

        if (DB::table('cash_closings')->exists()) {
            Log::warning('Clôture de caisse retirée : la table cash_closings contient des données, elle est conservée (non utilisée).');

            return;
        }

        Schema::drop('cash_closings');
    }

    public function down(): void
    {
        // The feature no longer exists: nothing to restore.
    }
};
