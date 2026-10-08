<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net under the models' `deleting` guards: the database itself refuses to delete a guest
 * or a room that has stays, or a room type that has rooms (these used to cascade and silently
 * wipe stays, invoices and payments).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['room_id']);
            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->restrictOnDelete();
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->foreign('room_type_id')->references('id')->on('room_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['guest_id']);
            $table->dropForeign(['room_id']);
            $table->foreign('guest_id')->references('id')->on('guests')->cascadeOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
        });
    }
};
