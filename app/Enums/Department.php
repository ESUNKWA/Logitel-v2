<?php

namespace App\Enums;

/**
 * The establishment's revenue-generating services (client request, 2026-11-25): the existing
 * accommodation and the new sales services. Stamped on every invoice and payment (`department`),
 * so the turnover can be read globally or per service. Hébergement is billed through stays
 * (reservations); the others through sales (App\Models\Sale) from their catalogue.
 */
enum Department: string
{
    case Hebergement = 'hebergement';
    case Restauration = 'restauration';
    case PlagePiscine = 'plage_piscine';
    case Evenements = 'evenements';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hebergement => 'Hébergement',
            self::Restauration => 'Restauration',
            self::PlagePiscine => 'Plage et piscine',
            self::Evenements => 'Réunions et événements',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Hebergement => 'fa-bed',
            self::Restauration => 'fa-utensils',
            self::PlagePiscine => 'fa-swimmer',
            self::Evenements => 'fa-briefcase',
        };
    }

    /** URL segment of the sales pages (/admin/ventes/{slug}). */
    public function slug(): string
    {
        return match ($this) {
            self::Hebergement => 'hebergement',
            self::Restauration => 'restauration',
            self::PlagePiscine => 'plage-piscine',
            self::Evenements => 'evenements',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Services billed through sales (their own catalogue and invoices), i.e. all but the
     * accommodation. Meetings & events are sold as room bookings (VenueBooking → its sale).
     */
    public static function salesServices(): array
    {
        return [self::Restauration, self::PlagePiscine, self::Evenements];
    }

    /** Services sold over the counter (« Nouvelle vente » pages): restaurant, beach & pool. */
    public static function counterServices(): array
    {
        return [self::Restauration, self::PlagePiscine];
    }

    /**
     * Services the hotel offers (Configuration → Hôtel, client request 2026-11-27): the
     * accommodation always, then the ticked ones. A service not offered is hidden everywhere and
     * its pages are blocked (EnsureServiceOffered); its past sales stay in the figures.
     *
     * @return list<self>
     */
    public static function offered(): array
    {
        $ticked = array_filter(array_map('trim', explode(',', (string) config('hotel.services', ''))));

        return [self::Hebergement, ...array_values(array_filter(self::salesServices(), fn (self $d) => in_array($d->value, $ticked, true)))];
    }

    public function isOffered(): bool
    {
        return in_array($this, self::offered(), true);
    }

    /** @return list<self> offered services billed through sales (catalogue, Pilotage activity). */
    public static function offeredSalesServices(): array
    {
        return array_values(array_filter(self::salesServices(), fn (self $d) => $d->isOffered()));
    }

    /** @return list<self> offered counter services (restaurant, beach & pool sales pages). */
    public static function offeredCounterServices(): array
    {
        return array_values(array_filter(self::counterServices(), fn (self $d) => $d->isOffered()));
    }

    /** Only for the beach / pool: a catalogue item may count as one entry (attendance). */
    public function countsEntries(): bool
    {
        return $this === self::PlagePiscine;
    }
}
