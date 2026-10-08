<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A room type now has three optional rates (one per pricing formula, see config/hotel.php):
 * hourly, night (22:00 → 12:00) and stay (per 24 h). The former single `base_price` was the
 * night rate, so it's renamed rather than dropped — existing prices are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->renameColumn('base_price', 'night_rate');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->decimal('night_rate', 10, 2)->nullable()->change();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('stay_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'stay_rate']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->renameColumn('night_rate', 'base_price');
        });

        // base_price was mandatory: types left without a night rate fall back to 0.
        DB::table('room_types')->whereNull('base_price')->update(['base_price' => 0]);

        Schema::table('room_types', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable(false)->change();
        });
    }
};
