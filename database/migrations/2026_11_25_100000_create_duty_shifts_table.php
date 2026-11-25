<?php

use App\Support\BusinessDay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * « Prise de service » by periods (client request, 2026-11-25): several teams a day, each with its
 * own sales, relief with confirmation. A `duty_shifts` row = one receptionist on duty from
 * `started_at` until `ended_at` (null = on duty now); at most one at a time. Replaces `duty_days`
 * (one receptionist per business day): each past day becomes one period covering the whole day
 * (today's stays open), so no attribution changes — `attributed_to` columns are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable()->index();
            // Set when the Gérant designated / corrected the receptionist of the period.
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            // Who ended it: herself, the colleague taking over, or the Gérant.
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('duty_days')) {
            $today = BusinessDay::today()->toDateString();
            foreach (DB::table('duty_days')->orderBy('business_date')->get() as $day) {
                $date = Carbon::parse($day->business_date)->startOfDay();
                [$start, $end] = BusinessDay::bounds($date);
                DB::table('duty_shifts')->insert([
                    'user_id' => $day->user_id,
                    'started_at' => $start,
                    'ended_at' => $date->toDateString() >= $today ? null : $end,
                    'assigned_by' => $day->assigned_by,
                    'created_at' => $day->created_at ?? now(),
                    'updated_at' => $day->updated_at ?? now(),
                ]);
            }
            Schema::drop('duty_days');
        }
    }

    public function down(): void
    {
        Schema::create('duty_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('taken_at');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // One receptionist per business day again: the first period of each day.
        foreach (DB::table('duty_shifts')->orderBy('started_at')->get() as $shift) {
            $date = BusinessDay::dateOf(Carbon::parse($shift->started_at))->toDateString();
            if (! DB::table('duty_days')->where('business_date', $date)->exists()) {
                DB::table('duty_days')->insert([
                    'business_date' => $date, 'user_id' => $shift->user_id, 'taken_at' => $shift->started_at,
                    'assigned_by' => $shift->assigned_by, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('duty_shifts');
    }
};
