<?php

namespace App\Support;

/**
 * The hotel's identity and contact details (Configuration → Hôtel, stored in `settings` and
 * layered over config('hotel.info') by HotelSettings). Shared with every view as `$hotelInfo`
 * (AppServiceProvider). Reads the config lazily, so it never queries the database itself —
 * the static public site keeps working without one. Empty values are meant to be hidden.
 */
class HotelInfo
{
    public function get(string $key): string
    {
        return trim((string) config("hotel.info.{$key}", ''));
    }

    public function name(): string
    {
        return $this->get('name') !== '' ? $this->get('name') : 'Hôtel Evasion';
    }

    /**
     * Name split for the two-tone wordmark: ["Espace", "Katioka"].
     *
     * @return array{0: string, 1: string}
     */
    public function nameParts(): array
    {
        $parts = preg_split('/\s+/', $this->name(), 2) ?: [$this->name()];

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Up to two initials for the brand badge: "Hôtel Evasion" → "EK".
     */
    public function initials(): string
    {
        $words = array_slice(preg_split('/\s+/', $this->name()) ?: [], 0, 2);

        return mb_strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), $words)));
    }

    public function logoUrl(): ?string
    {
        return $this->get('logo') !== '' ? asset('storage/'.$this->get('logo')) : null;
    }

    /**
     * Phone numbers to show (main, second), non-empty only.
     *
     * @return list<string>
     */
    public function phones(): array
    {
        return array_values(array_filter([$this->get('phone'), $this->get('phone2')]));
    }

    /**
     * "tel:+2250700000000" for a displayed number.
     */
    public function telHref(string $phone): string
    {
        return 'tel:'.(preg_replace('/(?!^\+)[^0-9]/', '', $phone) ?? '');
    }

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $this->get('whatsapp')) ?? '';

        return $digits !== '' ? 'https://wa.me/'.$digits : null;
    }

    /**
     * "Bouaké, Côte d'Ivoire".
     */
    public function cityLine(): string
    {
        return implode(', ', array_filter([$this->get('city'), $this->get('country')]));
    }

    /**
     * "Quartier Commerce, Bouaké, Côte d'Ivoire".
     */
    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->get('address'), $this->get('city'), $this->get('country')]));
    }

    /**
     * "RCCM CI-BKE-… · NCC … · Régime …", for receipts.
     */
    public function legalLine(): string
    {
        return implode(' · ', array_filter([
            $this->get('rccm') !== '' ? 'RCCM '.$this->get('rccm') : '',
            $this->get('ncc') !== '' ? 'NCC '.$this->get('ncc') : '',
            $this->get('tax_regime'),
            $this->get('legal_other'),
        ]));
    }

    /**
     * Filled social links: list of [label, url, Font Awesome 5 class].
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function socials(): array
    {
        return array_values(array_filter([
            $this->get('facebook') !== '' ? ['Facebook', $this->get('facebook'), 'fab fa-facebook-f'] : null,
            $this->get('instagram') !== '' ? ['Instagram', $this->get('instagram'), 'fab fa-instagram'] : null,
            $this->whatsappUrl() ? ['WhatsApp', $this->whatsappUrl(), 'fab fa-whatsapp'] : null,
        ]));
    }
}
