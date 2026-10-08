<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Demandes": a guest who just arrived picks a free room from their phone (public page /demande)
 * and submits their details. The request holds the room until it's handled by the réception
 * (validated into an affectation, paid in full) or refused, or until it expires
 * (config hotel.request_expiry_minutes). Kept apart from `reservations` on purpose: a request
 * isn't a stay, gets no invoice and may never become one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 50);
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->string('pricing_mode');
            $table->unsignedSmallInteger('duration');
            $table->decimal('unit_rate', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->dateTime('check_in');
            $table->dateTime('check_out');
            $table->string('status')->default('en_attente');
            $table->dateTime('expires_at');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->string('refusal_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_requests');
    }
};
