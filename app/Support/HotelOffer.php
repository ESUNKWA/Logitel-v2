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
            $offering = $types->filter(fn (RoomType $type) => $mode->rateFor($type) !== null);
            $rates = $offering->map(fn (RoomType $type) => (float) $mode->rateFor($type));
            if ($rates->isNotEmpty()) {
                // Séjour ending the next day at a fixed time: said when every type offering it does.
                $fixed = $offering->filter(fn (RoomType $type) => PricingMode::stayUntilFor($type) !== null)->count();
                $until = $mode === PricingMode::Sejour && $fixed === $offering->count() ? (string) config('hotel.stay_checkout_time') : null;
                $mixed = $mode === PricingMode::Sejour && $fixed > 0 && $fixed < $offering->count();
                $formulas[] = ['mode' => $mode, 'from' => $rates->min(), 'until' => $until, 'window' => self::window($mode, $until, $mixed)];
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
    public static function window(PricingMode $mode, ?string $stayUntil = null, bool $mixed = false): string
    {
        if ($mode === PricingMode::Sejour && ($stayUntil || $mixed)) {
            $at = PricingMode::clock($stayUntil ?? (string) config('hotel.stay_checkout_time'));

            return $mixed
                ? 'Par période de '.config('hotel.stay_min_hours')." h ou jusqu'au lendemain à ".$at.' selon la chambre'
                : "Jusqu'au lendemain à ".$at;
        }

        $hour = fn (string $time) => str_replace(':', 'h', $time);

        return match ($mode) {
            PricingMode::Heure => "À l'heure, quand vous voulez",
            PricingMode::Journee => 'Bloc de '.config('hotel.day_hours').' h en journée',
            PricingMode::Nuitee => 'De '.$hour((string) config('hotel.night.from')).' à '.$hour((string) config('hotel.night.until')).' le lendemain',
            PricingMode::Sejour => 'Par période de '.config('hotel.stay_min_hours').' h',
        };
    }
}
