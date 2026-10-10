<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website audience (App\Models\SiteVisit): one row per page viewed on the public site, recorded
 * server-side without cookies. The visitor is an anonymous daily hash (never the IP itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->date('day')->index();
            $table->dateTime('visited_at');
            $table->char('visitor', 64);           // sha256(day + IP + browser + app key): changes every day
            $table->boolean('is_entry')->default(false); // first page of this visitor on this day = one visit
            $table->string('route', 60)->nullable();
            $table->string('path', 255);
            $table->string('label', 160);          // « Accueil », « Chambre : Suite Lagune »…
            $table->string('source', 30);          // direct, google, facebook… (see SiteVisit::SOURCES)
            $table->string('referrer_host', 120)->nullable();
            $table->string('device', 10);          // mobile, tablet, desktop
            $table->index(['day', 'visitor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
