<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PricingMode;
use App\Enums\RoomAmenity;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * « Ce que nous vous offrons » on the home page (client request, 2026-10-19 — replaces the
 * template's decorative contact form): only what the application publishes, nothing invented,
 * each part empty (and hidden) when there's nothing to show.
 * - the séjour (the only formula the website shows) with its lowest price and length;
 * - equipment ticked on the published rooms;
 * - every published service (the « Nos services » section above shows 6 in large);
 * - payment methods accepted at the desk.
 */
class HotelOffer
{
    /**
     * @return array{formulas: list<array{mode: PricingMode, from: float, window: string}>, amenities: list<RoomAmenity>, services: Collection<int, Service>, payments: list<PaymentMethod>}
     */
    public static function summary(): array
    {
        $types = RoomType::query()->where('is_active', true)
            ->whereHas('rooms', fn ($rooms) => $rooms->where('is_published', true))
            ->get();

        $formulas = [];
        // The website shows the séjour only (PricingMode::WEBSITE, client decision 2026-10-19).
        foreach ([PricingMode::WEBSITE] as $mode) {
            $rates = $types->map(fn (RoomType $type) => $mode->rateFor($type))->filter(fn ($rate) => $rate !== null)->map(fn ($rate) => (float) $rate);
            if ($rates->isNotEmpty()) {
                $formulas[] = ['mode' => $mode, 'from' => $rates->min(), 'window' => self::window($mode)];
            }
        }

        $ticked = Room::query()->published()->get(['id', 'room_type_id', 'amenities'])
            ->flatMap(fn (Room $room) => $room->amenities ?? collect())
            ->map(fn (RoomAmenity $amenity) => $amenity->value)
            ->unique()
            ->all();

        return [
            'formulas' => $formulas,
            'amenities' => array_values(array_filter(RoomAmenity::cases(), fn (RoomAmenity $a) => in_array($a->value, $ticked, true))),
            'services' => Service::query()->published()->ordered()->get(['id', 'title', 'icon']),
            'payments' => PaymentMethod::cases(),
        ];
    }

    /**
     * When the formula runs: « 22h00 → 12h00 » for the nuitée, the block length otherwise
     * (the hotel's settings, config/hotel.php / Paramètres).
     */
    public static function window(PricingMode $mode): string
    {
        $hour = fn (string $time) => str_replace(':', 'h', $time);

        return match ($mode) {
            PricingMode::Heure => "À l'heure, quand vous voulez",
            PricingMode::Journee => 'Bloc de '.config('hotel.day_hours').' h en journée',
            PricingMode::Nuitee => 'De '.$hour((string) config('hotel.night.from')).' à '.$hour((string) config('hotel.night.until')).' le lendemain',
            PricingMode::Sejour => 'Par période de '.config('hotel.stay_min_hours').' h',
        };
    }
}
