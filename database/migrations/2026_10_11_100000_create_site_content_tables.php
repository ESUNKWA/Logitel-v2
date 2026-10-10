<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website content managed from the application (« Site web »):
 * - site_contents: the home page's texts and photos (key / value, see App\Models\SiteContent);
 * - services: the hotel's services and facilities (services page + featured on the home page).
 * Nothing is pre-filled: an empty field shows nothing on the website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_contents', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->nullable();      // small label above the title, e.g. « Restauration »
            $table->text('description')->nullable();
            $table->string('icon', 40)->nullable();      // Font Awesome 5 icon, used when there's no photo
            $table->string('photo')->nullable();         // path on the public disk
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('show_on_home')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('site_contents');
    }
};
