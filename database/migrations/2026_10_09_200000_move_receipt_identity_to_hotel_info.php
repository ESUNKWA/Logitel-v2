<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The hotel's name, address, phone, legal mentions and logo used to be receipt-only settings;
 * they now live in Configuration → Hôtel and are used everywhere. Values already entered are
 * carried over (settings rows renamed), nothing to re-type.
 */
return new class extends Migration
{
    private const RENAMES = [
        'receipt_name' => 'hotel_name',
        'receipt_address' => 'hotel_address',
        'receipt_phone' => 'hotel_phone',
        'receipt_legal' => 'hotel_legal_other',
        'receipt_logo' => 'hotel_logo',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            if (DB::table('settings')->where('key', $to)->doesntExist()) {
                DB::table('settings')->where('key', $from)->update(['key' => $to]);
            }
        }

        Cache::forget('hotel.settings');
    }
};
