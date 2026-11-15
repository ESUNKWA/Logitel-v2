<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * « Dépenses » (V2, client request 2026-11-15): money going out, by category, each one with a
 * mandatory receipt and the Gérant's validation. Never deleted: refused or cancelled with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->index();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('source', 20);
            $table->string('beneficiary', 120)->nullable();
            $table->string('description', 500);
            // Private disk (storage/app/private), random name; served only through the application.
            $table->string('receipt_path', 255);
            $table->string('receipt_mime', 60);
            $table->string('status', 20)->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // Whose till it comes out of (the receptionist of that business day — prise de service).
            $table->foreignId('attributed_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['business_date', 'attributed_to', 'status']);
        });

        $now = now();
        foreach (['Achats et fournitures', 'Carburant', 'Entretien et réparations', 'Électricité et eau', 'Salaires', 'Avance au personnel', 'Loyer', 'Autres'] as $i => $name) {
            DB::table('expense_categories')->insert(['name' => $name, 'position' => $i + 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // The Réception may request expenses (the Gérant validates them).
        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');
        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'expenses.create')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'expenses.create']);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->where('permission', 'expenses.create')->delete();
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
