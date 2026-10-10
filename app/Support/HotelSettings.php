<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Manager-editable hotel settings, stored in the `settings` table and layered over
 * config/hotel.php, so the rest of the app keeps reading config('hotel.*') unchanged.
 */
class HotelSettings
{
    private const CACHE_KEY = 'hotel.settings';

    /**
     * Setting key => config path.
     */
    public const KEYS = [
        'business_day_start' => 'hotel.business_day_start',
        'night_from' => 'hotel.night.from',
        'night_until' => 'hotel.night.until',
        'day_hours' => 'hotel.day_hours',
        'stay_min_hours' => 'hotel.stay_min_hours',
        'cleaning_buffer_minutes' => 'hotel.cleaning_buffer_minutes',
        'request_expiry_minutes' => 'hotel.request_expiry_minutes',
        'receipt_width' => 'hotel.receipt.width',
        'receipt_footer' => 'hotel.receipt.footer',
        // Hotel identity & contact (Configuration → Hôtel)
        'hotel_name' => 'hotel.info.name',
        'hotel_tagline' => 'hotel.info.tagline',
        'hotel_phone' => 'hotel.info.phone',
        'hotel_phone2' => 'hotel.info.phone2',
        'hotel_whatsapp' => 'hotel.info.whatsapp',
        'hotel_email' => 'hotel.info.email',
        'hotel_website' => 'hotel.info.website',
        'hotel_address' => 'hotel.info.address',
        'hotel_city' => 'hotel.info.city',
        'hotel_country' => 'hotel.info.country',
        'hotel_rccm' => 'hotel.info.rccm',
        'hotel_ncc' => 'hotel.info.ncc',
        'hotel_tax_regime' => 'hotel.info.tax_regime',
        'hotel_legal_other' => 'hotel.info.legal_other',
        'hotel_facebook' => 'hotel.info.facebook',
        'hotel_instagram' => 'hotel.info.instagram',
        'hotel_logo' => 'hotel.info.logo',
        'hotel_login_background' => 'hotel.info.login_background',
    ];

    /**
     * Applies stored values over the config. Called at boot; silently keeps the config/hotel.php
     * defaults when the table isn't there yet (fresh install before migrating, test bootstrap).
     */
    public static function apply(): void
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')->all());
        } catch (Throwable) {
            return;
        }

        self::put($stored);
    }

    /**
     * Current values, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_map(fn (string $path) => config($path), self::KEYS);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $values = array_intersect_key($values, self::KEYS);

        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string) $value, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
        self::put($values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::KEYS) as $key => $value) {
            config([self::KEYS[$key] => in_array($key, ['day_hours', 'stay_min_hours', 'cleaning_buffer_minutes', 'request_expiry_minutes', 'receipt_width'], true) ? (int) $value : (string) $value]);
        }
    }
}
