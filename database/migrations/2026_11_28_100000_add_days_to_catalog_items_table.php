<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant menu by day (client request, 2026-11-28): the days of the week a dish is served
 * (ISO numbers, 1 = Monday … 7 = Sunday, like special_rates.days); null = every day, so the
 * existing catalogue is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->json('days')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
};
