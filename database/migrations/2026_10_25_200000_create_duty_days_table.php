<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * « Prise de service » (client request, 2026-10-25): one receptionist per business day. Every
 * sale of that day — payments and affectations, whoever typed them — is attributed to her:
 * `attributed_to` on payments and reservations, next to the unchanged `created_by` / `checked_in_by`
 * (who actually typed it). Past rows keep their author as attributee, so no figure changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('taken_at');
            // Set when the Gérant designated / corrected the receptionist of the day.
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('attributed_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('attributed_to')->nullable()->after('checked_in_by')->constrained('users')->nullOnDelete();
        });

        DB::table('payments')->update(['attributed_to' => DB::raw('created_by')]);
        // Stays: whoever gave the room (check-in), else the affectation's creator.
        DB::table('reservations')->whereNotNull('checked_in_by')->update(['attributed_to' => DB::raw('checked_in_by')]);
        DB::table('reservations')->whereNull('checked_in_by')->where('origin', 'affectation')->update(['attributed_to' => DB::raw('created_by')]);

        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');
        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'duty.take')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'duty.take']);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->where('permission', 'duty.take')->delete();

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attributed_to');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attributed_to');
        });
        Schema::dropIfExists('duty_days');
    }
};
