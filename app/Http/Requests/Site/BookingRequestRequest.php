<?php

namespace App\Http\Requests\Site;

use App\Enums\PricingMode;
use App\Enums\RoomStatus;
use App\Models\BookingRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * An online booking request (public, no account). Only the phone is required. The rate is the
 * room type's (indicative), the departure and total are derived by the model, and the request is
 * refused when no room of the type is free over the period — with a message that reveals nothing
 * about other guests.
 */
class BookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name', 'phone', 'email', 'message'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer'],
            'pricing_mode' => ['required', new Enum(PricingMode::class)],
            'check_in' => ['required', 'date'],
            'duration' => ['required', 'integer', 'min:1', 'max:30'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['required', 'integer', 'min:0', 'max:10'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-\/]{6,24}$/'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
            // Honeypot: hidden from people, filled in by most spam bots.
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['room_type_id', 'pricing_mode', 'check_in', 'duration'])) {
                    return;
                }

                $type = $this->roomType();
                $mode = $this->pricingMode();
                if (! $type) {
                    $validator->errors()->add('room_type_id', 'Choisissez un type de chambre.');

                    return;
                }
                if ($mode->rateFor($type) === null) {
                    $validator->errors()->add('pricing_mode', "La formule « {$mode->getLabel()} » n'est pas proposée pour ce type de chambre.");

                    return;
                }

                [$start, $end] = $this->period();
                if ($start->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('check_in', 'Choisissez une arrivée à venir.');

                    return;
                }
                if ($start->greaterThan(now()->addYear())) {
                    $validator->errors()->add('check_in', 'Les réservations sont ouvertes sur un an.');

                    return;
                }
                if ($mode !== PricingMode::Nuitee && ! $mode->canStartAt($start)) {
                    $validator->errors()->add('pricing_mode', 'Cette formule ne peut pas commencer à cette heure.');
                }

                // At least one room of the type free over the period (same availability rule as
                // the réception's own bookings). Nothing is held: the réception confirms.
                $free = Room::query()
                    ->where('room_type_id', $type->id)
                    ->where('status', '!=', RoomStatus::HorsService)
                    ->availableBetween($start, $end)
                    ->exists();
                if (! $free) {
                    $validator->errors()->add('check_in', 'Aucune chambre de ce type n\'est disponible sur cette période. Essayez d\'autres dates ou un autre type de chambre.');
                }

                $phone = StayRequest::normalizePhone((string) $this->input('phone'));
                if ($phone !== '' && BookingRequest::query()->pending()->where('check_in', '>', now())->get(['phone'])
                    ->contains(fn (BookingRequest $r) => StayRequest::normalizePhone($r->phone) === $phone)) {
                    $validator->errors()->add('phone', 'Une demande de réservation est déjà en cours avec ce numéro : la réception vous rappelle.');
                }
            },
        ];
    }

    public function roomType(): ?RoomType
    {
        return BookingRequest::offeredRoomTypes()->firstWhere('id', (int) $this->input('room_type_id'));
    }

    public function pricingMode(): PricingMode
    {
        return PricingMode::tryFrom((string) $this->input('pricing_mode')) ?? PricingMode::Nuitee;
    }

    /**
     * A nuitée arrival is a date (the night starts at 22:00); otherwise a date and time.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int} [start, end, duration]
     */
    public function period(): array
    {
        $arrival = Carbon::parse((string) $this->input('check_in'));
        if ($this->pricingMode() === PricingMode::Nuitee) {
            $arrival = $arrival->startOfDay();
        }

        return $this->pricingMode()->resolve($arrival, (int) $this->input('duration'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'room_type_id' => 'type de chambre',
            'pricing_mode' => 'formule',
            'check_in' => 'arrivée',
            'duration' => 'durée',
            'adults' => 'adultes',
            'children' => 'enfants',
            'phone' => 'téléphone',
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'email' => 'e-mail',
            'message' => 'message',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Indiquez votre numéro de téléphone : la réception vous rappelle pour confirmer.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide (chiffres uniquement, ex. 07 00 00 00 00).',
            'website.max' => 'Votre demande n\'a pas pu être envoyée.',
        ];
    }
}
