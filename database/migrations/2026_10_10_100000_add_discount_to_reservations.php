<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discounts ("remises") on affectations and réservations: an amount in FCFA or a percentage
 * of the subtotal (unit_rate × duration), with an optional reason and who granted it. The model
 * derives discount_amount and total_amount = subtotal − discount (see Reservation::booted()).
 * Existing files get no discount, so their totals don't change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable();          // montant | pourcentage
            $table->decimal('discount_value', 12, 2)->default(0);     // FCFA or %
            $table->decimal('discount_amount', 12, 2)->default(0);    // derived, FCFA
            $table->string('discount_reason')->nullable();
            $table->foreignId('discount_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('discount_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_by');
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount', 'discount_reason', 'discount_at']);
        });
    }
};
