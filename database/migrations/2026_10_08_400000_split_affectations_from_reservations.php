<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affectations (guest moves in now, from the room board) and réservations (booked ahead, from
 * the reservations module) become two separate features. They still share one record per
 * guest file — so the invoice created at booking and its deposit follow the guest into the
 * stay — told apart by `origin`. Existing records: created within 5 minutes of their start =
 * affectation, otherwise réservation.
 *
 * Roles keep their abilities: the former reservation permissions also covered the board and
 * the stays, so they're mirrored onto the new affectation permissions.
 */
return new class extends Migration
{
    private const MIRROR = [
        'reservations.view' => ['affectations.view'],
        'reservations.manage' => ['affectations.view', 'affectations.manage'],
        'reservations.cancel' => ['affectations.cancel'],
    ];

    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('origin')->default('reservation')->index();
        });

        foreach (DB::table('reservations')->get(['id', 'check_in', 'created_at']) as $row) {
            if ($row->created_at && abs(Carbon::parse($row->created_at)->diffInMinutes(Carbon::parse($row->check_in))) <= 5) {
                DB::table('reservations')->where('id', $row->id)->update(['origin' => 'affectation']);
            }
        }

        foreach (DB::table('permission_role')->get() as $grant) {
            foreach (self::MIRROR[$grant->permission] ?? [] as $permission) {
                DB::table('permission_role')->insertOrIgnore(['role_id' => $grant->role_id, 'permission' => $permission]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->whereIn('permission', ['affectations.view', 'affectations.manage', 'affectations.cancel'])->delete();

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};
