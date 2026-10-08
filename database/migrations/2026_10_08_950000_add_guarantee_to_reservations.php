<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booked-ahead réservation only holds its room once it's fully paid ("garantie"):
 * `is_guaranteed` is maintained by Reservation::refreshGuarantee() whenever its invoice changes.
 * Stays in progress always hold their room. The PostgreSQL no-overlap constraint is narrowed
 * to exactly those holding rows, so unpaid bookings may overlap each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_guaranteed')->default(false)->index();
        });

        // Existing bookings: guaranteed when their invoice is fully paid (or free).
        foreach (DB::table('reservations')->whereIn('status', ['en_attente', 'confirmee'])->get(['id']) as $row) {
            $invoice = DB::table('invoices')->where('reservation_id', $row->id)->first(['id', 'amount']);
            $paid = $invoice ? (float) DB::table('payments')->where('invoice_id', $invoice->id)->sum('amount') : 0;
            if ($invoice && $paid >= (float) $invoice->amount) {
                DB::table('reservations')->where('id', $row->id)->update(['is_guaranteed' => true]);
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
            DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap
                EXCLUDE USING gist (room_id WITH =, tsrange(check_in, check_out, '[)') WITH &&)
                WHERE (status = 'enregistree' OR (status IN ('en_attente', 'confirmee') AND is_guaranteed))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
            DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap
                EXCLUDE USING gist (room_id WITH =, tsrange(check_in, check_out, '[)') WITH &&)
                WHERE (status IN ('en_attente', 'confirmee', 'enregistree'))");
        }

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['is_guaranteed']);
            $table->dropColumn('is_guaranteed');
        });
    }
};
