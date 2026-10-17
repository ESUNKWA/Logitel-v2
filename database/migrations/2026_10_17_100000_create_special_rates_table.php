<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Special rates ("forfaits") for affectations: N units of a pricing mode for a fixed price
 * (e.g. 2 heures = 5 000 FCFA), optionally limited to some days of the week and/or a period.
 * The stay keeps a snapshot of the forfait it got (name + price), so editing or deleting a
 * forfait later never changes an existing invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('pricing_mode', 20);
            $table->unsignedSmallInteger('duration');
            $table->decimal('price', 12, 2);
            $table->boolean('all_room_types')->default(false);
            $table->json('days')->nullable(); // ISO days 1 (Mon) … 7 (Sun); null = every day
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['pricing_mode', 'duration', 'is_active']);
        });

        Schema::create('room_type_special_rate', function (Blueprint $table) {
            $table->foreignId('special_rate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['special_rate_id', 'room_type_id']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('special_rate_id')->nullable()->after('unit_rate')->constrained()->nullOnDelete();
            $table->string('special_rate_name', 100)->nullable()->after('special_rate_id');
            $table->decimal('special_price', 12, 2)->nullable()->after('special_rate_name');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('special_rate_id');
            $table->dropColumn(['special_rate_name', 'special_price']);
        });
        Schema::dropIfExists('room_type_special_rate');
        Schema::dropIfExists('special_rates');
    }
};
