<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout alarm: when a checked-in stay reaches its departure time, staff holding the
 * `alerts.checkout` permission get an in-app alarm until someone acknowledges it ("Vu").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('overdue_acknowledged_at')->nullable();
            $table->foreignId('overdue_acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overdue_acknowledged_by');
            $table->dropColumn('overdue_acknowledged_at');
        });
    }
};
