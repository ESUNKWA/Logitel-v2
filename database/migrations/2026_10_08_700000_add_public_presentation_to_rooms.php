<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the public room page (/chambres/detail) shows about a room, entered in the back-office
 * room form. Capacity and price come from the room type; photos from room_images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_published')->default(false);
            $table->string('public_name')->nullable();
            $table->string('tagline')->nullable();
            $table->unsignedSmallInteger('surface')->nullable();
            $table->unsignedTinyInteger('beds')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->json('features')->nullable();
            $table->json('rules')->nullable();
            $table->text('cancellation_policy')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'public_name', 'tagline', 'surface', 'beds', 'bathrooms', 'description', 'amenities', 'features', 'rules', 'cancellation_policy']);
        });
    }
};
