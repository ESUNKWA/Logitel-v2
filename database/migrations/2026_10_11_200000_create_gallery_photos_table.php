<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website gallery managed from the application (« Site web → Galerie », App\Models\GalleryPhoto).
 * Nothing is pre-filled: an empty gallery shows a notice on the website, never sample pictures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->string('path');                      // on the public disk, under site/gallery/
            $table->string('caption', 160)->nullable();
            $table->string('category', 60)->nullable();  // free text, e.g. « Chambres » — filter tabs on the website
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
