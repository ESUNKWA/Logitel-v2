<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Included perks ("avantages inclus", client request 2026-10-25): a text such as « Accès piscine
 * gratuit » granted to some (or all) room types, optionally for some pricing modes only (e.g.
 * SUITE + séjour). Printed on the receipt, shown at the desk and on the website's room page.
 * Each file keeps a snapshot of the perks it got (reservations.perks), so editing or deleting a
 * perk later never changes what was promised on an existing stay or booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perks', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->boolean('all_room_types')->default(false);
            $table->json('modes')->nullable(); // PricingMode values; null = every formula
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('perk_room_type', function (Blueprint $table) {
            $table->foreignId('perk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['perk_id', 'room_type_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->json('perks')->nullable()->after('special_price');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('perks');
        });
        Schema::dropIfExists('perk_room_type');
        Schema::dropIfExists('perks');
    }
};
