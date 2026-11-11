<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Corbeille » of affectations deleted by the Gérant (client request, 2026-11-11). One row per
 * deleted file: a summary kept for ever (the deletion log) + an encrypted copy of every row that
 * was removed (stay, invoice, payments, client), used to restore it and erased on restoration or
 * definitive deletion (manual, or automatic after config('hotel.trash_retention_days')).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trashed_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch');
            $table->unsignedBigInteger('reservation_id')->index();
            $table->string('reference', 20);
            $table->string('booking_reference', 20)->nullable();
            $table->string('room_number', 50)->nullable();
            $table->string('room_type')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('pricing_mode', 20)->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('invoice_number', 30)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('trashed_at')->index();
            $table->foreignId('trashed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('purged_at')->nullable();
            $table->foreignId('purged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trashed_files');
    }
};
