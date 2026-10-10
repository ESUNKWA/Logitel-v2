<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online booking requests sent from the website's « Réserver » page (App\Models\BookingRequest).
 * Not reservations: no invoice, no room held. The réception turns one into a réservation (from
 * the usual form, pre-filled) or refuses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pricing_mode', 20);
            $table->dateTime('check_in');
            $table->unsignedInteger('duration');
            $table->dateTime('check_out');
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->decimal('unit_rate', 12, 2);       // indicative: the type's rate when requested
            $table->decimal('total_amount', 12, 2);
            $table->string('first_name', 100)->default('');
            $table->string('last_name', 100)->default('');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('en_attente')->index();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->string('refusal_reason', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
