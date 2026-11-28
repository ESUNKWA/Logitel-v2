<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The bar becomes its own service, apart from the restaurant (client request, 2026-11-28):
 * Restauration = food, Bar = drinks.
 *  - the restaurant's drinks (category « Boissons ») move to the bar's catalogue — their days of
 *    service are dropped (the bar serves everything every day). Past sales are left as they are:
 *    sold as Restauration, they stay in the restaurant's figures and their invoices don't change;
 *  - wherever the restaurant is offered, the bar is ticked too (Configuration → Hôtel), so the
 *    drinks stay sellable without anything to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        $drinks = DB::table('catalog_items')
            ->where('department', 'restauration')
            ->whereRaw("lower(trim(category)) in ('boissons', 'boisson')")
            ->get(['id', 'name']);
        foreach ($drinks as $drink) {
            // A bar item of the same name already (unique per service): leave this one at the restaurant.
            if (DB::table('catalog_items')->where('department', 'bar')->where('name', $drink->name)->exists()) {
                continue;
            }
            DB::table('catalog_items')->where('id', $drink->id)->update(['department' => 'bar', 'days' => null, 'updated_at' => now()]);
        }

        $services = DB::table('settings')->where('key', 'hotel_services')->value('value');
        if ($services !== null) {
            $list = array_values(array_filter(array_map('trim', explode(',', (string) $services))));
            if (in_array('restauration', $list, true) && ! in_array('bar', $list, true)) {
                array_splice($list, array_search('restauration', $list, true) + 1, 0, ['bar']);
                DB::table('settings')->where('key', 'hotel_services')->update(['value' => implode(',', $list), 'updated_at' => now()]);
            }
        }
        Cache::forget('hotel.settings'); // App\Support\HotelSettings::CACHE_KEY
    }

    public function down(): void
    {
        // Back to a single restaurant: the bar's items and sales return to it.
        DB::table('catalog_items')->where('department', 'bar')->whereNull('category')->update(['category' => 'Boissons']);
        foreach (DB::table('catalog_items')->where('department', 'bar')->get(['id', 'name']) as $item) {
            if (! DB::table('catalog_items')->where('department', 'restauration')->where('name', $item->name)->exists()) {
                DB::table('catalog_items')->where('id', $item->id)->update(['department' => 'restauration']);
            }
        }
        foreach (['sales', 'sale_lines', 'invoices', 'payments'] as $table) {
            if (DB::getSchemaBuilder()->hasColumn($table, 'department')) {
                DB::table($table)->where('department', 'bar')->update(['department' => 'restauration']);
            }
        }

        $services = DB::table('settings')->where('key', 'hotel_services')->value('value');
        if ($services !== null) {
            $list = array_values(array_filter(array_map('trim', explode(',', (string) $services)), fn ($v) => $v !== '' && $v !== 'bar'));
            DB::table('settings')->where('key', 'hotel_services')->update(['value' => implode(',', $list), 'updated_at' => now()]);
        }
        Cache::forget('hotel.settings');
    }
};
