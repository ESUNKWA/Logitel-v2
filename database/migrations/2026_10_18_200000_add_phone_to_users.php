<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Staff accounts get a phone number (10 digits, unique among staff) and may sign in with it
 * instead of their e-mail (client request, 2026-10-18). An account needs at least one of the
 * two (checked by the forms), so the e-mail becomes nullable; existing accounts keep theirs
 * and get a number when next edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 10)->nullable()->unique()->after('email');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });
    }
};
