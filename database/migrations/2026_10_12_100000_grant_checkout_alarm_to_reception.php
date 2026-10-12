<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Client request (2026-10-12): the end-of-stay alarm rings at the réception — and not for the
 * Gérant (see User::receivesCheckoutAlarm()). Grants `alerts.checkout` to the Réception role.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');

        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'alerts.checkout')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'alerts.checkout']);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');

        if ($roleId) {
            DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'alerts.checkout')->delete();
        }
    }
};
