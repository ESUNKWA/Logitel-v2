<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Other services of the establishment (client request, 2026-11-25): restaurant, beach & pool.
 * - `catalog_items`: what each service sells, with its price (managed by the Gérant).
 * - `sales` + `sale_lines`: a sale of a service (lines snapshot the item's name and price), paid
 *   at once, partly, left due, or charged to an in-progress stay (« report sur la chambre »).
 * - `invoices`: no longer always a stay's — a sale has its own invoice (`sale_id`), exactly one
 *   origin per invoice; `department` = the service (existing invoices: hébergement).
 * - `payments.department`: the invoice's service, stamped at creation, for the per-service
 *   turnover (existing payments: hébergement — no amount or attribution changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('department', 30)->index();
            $table->string('category', 60)->nullable();
            $table->string('name', 120);
            $table->decimal('price', 10, 2);
            // Beach / pool: one unit sold = one entry (attendance figures).
            $table->boolean('counts_entry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['department', 'name']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('department', 30);
            $table->timestamp('sold_at');
            // Free text typed at the desk (« Table 4 », a name…), and/or the stay it is charged to.
            $table->string('label', 120)->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('status', 20)->default('validee');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['department', 'sold_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots: a later change of the catalogue never changes a past sale.
            $table->string('label', 120);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->boolean('counts_entry')->default(false);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('department', 30)->default('hebergement')->index();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_id')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('department', 30)->default('hebergement');
            $table->index(['department', 'paid_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Exactly one origin per invoice: a stay or a sale.
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_single_origin CHECK ((reservation_id IS NOT NULL)::int + (sale_id IS NOT NULL)::int = 1)');
        }

        // The Réception records every sale (client decision: « la réception encaisse tout »).
        $roleId = DB::table('roles')->where('slug', 'reception')->value('id');
        if ($roleId && ! DB::table('permission_role')->where('role_id', $roleId)->where('permission', 'sales.create')->exists()) {
            DB::table('permission_role')->insert(['role_id' => $roleId, 'permission' => 'sales.create']);
        }
    }

    public function down(): void
    {
        DB::table('permission_role')->whereIn('permission', ['sales.create', 'sales.cancel', 'catalog.manage'])->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_single_origin');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['department', 'paid_at']);
            $table->dropColumn('department');
        });

        // Sales' invoices (and their payments) go with the sales.
        $saleInvoices = DB::table('invoices')->whereNotNull('sale_id')->pluck('id');
        DB::table('payments')->whereIn('invoice_id', $saleInvoices)->delete();
        DB::table('invoices')->whereIn('id', $saleInvoices)->delete();

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropIndex(['department']);
            $table->dropColumn('department');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('reservation_id')->nullable(false)->change();
        });

        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('catalog_items');
    }
};
