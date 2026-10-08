<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room photos (max 5 per room, first = cover), stored on the "public" disk under rooms/{id}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_images');
    }
};
