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
        'stay_checkout_time' => 'hotel.stay_checkout_time',
        'cleaning_buffer_minutes' => 'hotel.cleaning_buffer_minutes',
        'request_expiry_minutes' => 'hotel.request_expiry_minutes',
        'receipt_width' => 'hotel.receipt.width',
        'receipt_footer' => 'hotel.receipt.footer',
        'training_url' => 'hotel.training_url',
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
        'hotel_services' => 'hotel.services',
    ];

    /** French names of the settings, for the journal d'activité. */
    public const LABELS = [
        'business_day_start' => 'Début de la journée commerciale', 'night_from' => 'Début de la nuitée', 'night_until' => 'Fin de la nuitée',
        'day_hours' => 'Durée de la journée (h)', 'stay_min_hours' => 'Durée du séjour (h)', 'stay_checkout_time' => 'Heure de fin du séjour à heure fixe',
        'cleaning_buffer_minutes' => 'Délai de ménage (min)', 'request_expiry_minutes' => 'Délai de traitement des demandes (min)',
        'receipt_width' => 'Largeur du rouleau', 'receipt_footer' => 'Message de bas de reçu', 'training_url' => 'Lien Formation',
        'hotel_name' => 'Nom de l\'hôtel', 'hotel_tagline' => 'Slogan', 'hotel_phone' => 'Téléphone', 'hotel_phone2' => 'Téléphone 2',
        'hotel_whatsapp' => 'WhatsApp', 'hotel_email' => 'E-mail', 'hotel_website' => 'Site web', 'hotel_address' => 'Adresse', 'hotel_city' => 'Ville',
        'hotel_country' => 'Pays', 'hotel_rccm' => 'RCCM', 'hotel_ncc' => 'NCC', 'hotel_tax_regime' => 'Régime fiscal', 'hotel_legal_other' => 'Autres mentions',
        'hotel_facebook' => 'Facebook', 'hotel_instagram' => 'Instagram', 'hotel_logo' => 'Logo', 'hotel_login_background' => 'Photo de la page de connexion',
        'hotel_services' => 'Services proposés',
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
        $before = self::all();

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

        $changes = [];
        foreach ($values as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changes[] = ['label' => self::LABELS[$key] ?? $key, 'old' => Audit::format($before[$key] ?? null), 'new' => Audit::format($value)];
            }
        }
        if ($changes !== []) {
            $hotel = array_filter(array_keys($values), fn ($k) => str_starts_with($k, 'hotel_')) !== [];
            Audit::record($hotel ? 'settings.hotel' : 'settings.updated', 'configuration', $hotel ? 'Informations de l\'hôtel modifiées' : 'Paramètres modifiés', null, null, null, $changes);
        }
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
