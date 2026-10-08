<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            // System role = every permission, cannot be edited or deleted (see Role::permissionValues()).
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 100);
            $table->primary(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        $now = now();

        $managerId = DB::table('roles')->insertGetId([
            'name' => 'Gérant',
            'slug' => 'gerant',
            'description' => 'Accès complet à la gestion hôtelière et à l\'administration.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $receptionId = DB::table('roles')->insertGetId([
            'name' => 'Réception',
            'slug' => 'reception',
            'description' => 'Accueil des clients : réservations, affectation des chambres, encaissements.',
            'is_system' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Hardcoded on purpose: a migration is a snapshot and must not change if the enum does later.
        $receptionPermissions = [
            'rooms.view', 'rooms.edit',
            'guests.view', 'guests.manage',
            'reservations.view', 'reservations.manage',
            'invoices.view', 'payments.create',
        ];

        DB::table('permission_role')->insert(array_map(
            fn (string $permission) => ['role_id' => $receptionId, 'permission' => $permission],
            $receptionPermissions,
        ));

        // Every account that existed before roles had full access: keep it that way.
        DB::table('users')->update(['role_id' => $managerId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['is_active', 'must_change_password']);
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
    }
};
