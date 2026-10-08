<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each staff member picks the back-office layout: "simple" (front-desk home with big action
 * tiles, short top navigation — the default) or "complet" (sidebar + figures dashboard).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ui_mode', 10)->default('simple');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_mode');
        });
    }
};
