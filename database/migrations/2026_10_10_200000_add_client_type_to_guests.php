<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client is a natural person ("physique") or a legal entity ("morale": company, NGO,
 * administration…) with its company name and RCCM / NCC number; for a company, first/last
 * name are the contact person. Existing clients are natural persons. Names stay non-null
 * columns: an unknown name is stored as an empty string (only the phone is required when
 * booking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('type', 20)->default('physique');
            $table->string('company_name')->nullable();
            $table->string('tax_id', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['type', 'company_name', 'tax_id']);
        });
    }
};
