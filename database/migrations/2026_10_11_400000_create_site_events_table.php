<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upcoming events shown on the website (« Site web → Événements », App\Models\SiteEvent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('visible_until')->index(); // derived: shown on the site until then
            $table->string('location', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('photo')->nullable();        // poster, on the public disk under site/events/
            $table->string('price_label', 80)->nullable(); // « Entrée libre », « 10 000 FCFA »…
            $table->string('booking_info')->nullable();    // a link or a phone number
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_events');
    }
};
