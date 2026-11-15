<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Clôture de caisse » (V2, client request 2026-11-15): one per business day and till owner.
 * The expected amounts are a server-side snapshot taken at submission; the day isn't locked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('expected');            // per method: received, expenses, expected
            $table->json('declared');            // per method: what was handed over
            $table->json('counted')->nullable(); // cash count per note / coin (billetage)
            $table->decimal('total_expected', 12, 2);
            $table->decimal('total_declared', 12, 2);
            $table->decimal('total_difference', 12, 2);
            $table->text('comment')->nullable();
            $table->string('status', 20)->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_comment', 1000)->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'user_id']);
        });

        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');
        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'cash.close')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'cash.close']);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->where('permission', 'cash.close')->delete();
        Schema::dropIfExists('cash_closings');
    }
};
