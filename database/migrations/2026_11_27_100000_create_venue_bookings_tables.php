<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Meetings & events (client request, 2026-11-25, step 2).
 * - `venues`: the rooms to hire (« salles »), capacity, half-day and full-day rates (Gérant).
 * - `venue_bookings`: a room booked for one day, from `start_at` to `end_at`, its formula and
 *   rate snapshot, the client, participants, status (option = quote / confirmed / cancelled).
 *   Billed through its sale (`sale_id`, department « evenements »): room hire line + catalogue
 *   services, its invoice, deposit and balance like any sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->unsignedInteger('capacity');
            $table->decimal('half_day_rate', 10, 2);
            $table->decimal('day_rate', 10, 2);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('venue_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('formula', 20);
            $table->decimal('venue_rate', 10, 2);
            $table->unsignedInteger('participants');
            $table->string('client_name', 120);
            $table->string('client_phone', 50);
            $table->string('organization', 160)->nullable();
            $table->string('title', 160)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('option');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'start_at']);
        });

        // The Réception takes the bookings and cashes them (« la réception encaisse tout »).
        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');
        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'events.manage')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'events.manage']);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->where('permission', 'events.manage')->delete();
        Schema::dropIfExists('venue_bookings');
        Schema::dropIfExists('venues');
    }
};
