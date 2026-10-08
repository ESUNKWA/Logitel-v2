<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mixed payments: the Payment rows recorded together in one "encaissement" share a
 * `settlement` id. Payment methods are now especes / orange_money / wave only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $retired = DB::table('payments')->whereIn('method', ['carte', 'virement'])->count();
        if ($retired > 0) {
            throw new RuntimeException("{$retired} paiement(s) par carte/virement existent : choisissez leur nouveau mode avant de migrer.");
        }

        DB::table('payments')->where('method', 'mobile_money')->update(['method' => 'orange_money']);

        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('settlement')->nullable()->index();
        });

        // Existing payments each become their own single-line settlement.
        foreach (DB::table('payments')->pluck('id') as $id) {
            DB::table('payments')->where('id', $id)->update(['settlement' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['settlement']);
            $table->dropColumn('settlement');
        });
    }
};
