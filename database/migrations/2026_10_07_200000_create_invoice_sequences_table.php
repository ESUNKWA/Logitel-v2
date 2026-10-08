<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number');
        });

        // Start each year's counter after the highest number already issued, so existing
        // invoices (INV-{year}-{seq}) are never collided with.
        $lastByYear = [];

        foreach (DB::table('invoices')->pluck('number') as $number) {
            if (preg_match('/^INV-(\d{4})-(\d+)$/', $number, $m)) {
                $lastByYear[$m[1]] = max($lastByYear[$m[1]] ?? 0, (int) $m[2]);
            }
        }

        foreach ($lastByYear as $year => $last) {
            DB::table('invoice_sequences')->insert(['year' => $year, 'last_number' => $last]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
