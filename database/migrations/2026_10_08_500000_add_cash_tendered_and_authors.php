<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash handling: for a cash payment line, what the guest handed over and the change given
 * back (computed server-side). Authorship: who created each file/payment/guest, and who did
 * the check-in, check-out and cancellation of a file (and when). Existing rows stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_received', 10, 2)->nullable();
            $table->decimal('change_given', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('reservations', function (Blueprint $table) {
            foreach (['created_by', 'checked_in_by', 'checked_out_by', 'cancelled_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['checked_in_at', 'checked_out_at', 'cancelled_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['amount_received', 'change_given']);
        });
    }
};
