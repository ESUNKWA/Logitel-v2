<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fourth pricing formula: "journée", a fixed block of config('hotel.day_hours') hours (6 h).
 * Optional like the other rates (null = not offered for the type).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->decimal('day_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('day_rate');
        });
    }
};
