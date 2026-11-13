<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Séjour ending the next day at a fixed time (client request, 2026-11-13): ticked per room type
 * (`room_types.stay_fixed_checkout`); the time is one hotel setting (`hotel.stay_checkout_time`,
 * 12:00). Each file keeps the rule it was made with (`stay_until` = « HH:MM », null = periods of
 * 24 h), so changing the setting or the type never moves an existing departure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->boolean('stay_fixed_checkout')->default(false);
        });

        foreach (['reservations', 'stay_requests', 'booking_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('stay_until', 5)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['reservations', 'stay_requests', 'booking_requests'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('stay_until');
            });
        }

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('stay_fixed_checkout');
        });
    }
};
