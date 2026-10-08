<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stays get a pricing mode (heure / nuitee / sejour) and a duration in that mode's unit, and
 * check_in/check_out become timestamps (hourly stays need the time of day).
 * `rate_per_night` becomes `unit_rate` (rate per hour, night or 24 h depending on the mode).
 * Every existing reservation was a nightly one: it becomes a nuitée, D 22:00 → D+N 12:00,
 * with its amount unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('rate_per_night', 'unit_rate');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('check_in')->change();
            $table->dateTime('check_out')->change();
            $table->string('pricing_mode')->default('nuitee');
            $table->unsignedInteger('duration')->default(1);
        });

        foreach (DB::table('reservations')->get(['id', 'check_in', 'check_out']) as $row) {
            $in = Carbon::parse($row->check_in)->startOfDay();
            $nights = max(1, (int) $in->diffInDays(Carbon::parse($row->check_out)->startOfDay()));

            DB::table('reservations')->where('id', $row->id)->update([
                'check_in' => $in->copy()->setTime(22, 0),
                'check_out' => $in->copy()->addDays($nights)->setTime(12, 0),
                'duration' => $nights,
            ]);
        }
    }

    public function down(): void
    {
        // Lossy for hourly/24 h stays: only the dates survive.
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'duration']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->date('check_in')->change();
            $table->date('check_out')->change();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->renameColumn('unit_rate', 'rate_per_night');
        });
    }
};
