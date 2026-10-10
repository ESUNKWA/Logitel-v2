<?php

namespace App\Http\Requests\Site;

use App\Enums\PricingMode;
use App\Models\Room;
use App\Models\StayRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * A guest's room request, sent from their phone (public, no account). The guest only picks the
 * mode and the duration: the arrival is the server's current time, the rate is the room type's,
 * and availability is re-checked under a lock in the controller. Error messages never reveal
 * anything about other guests.
 */
class StayRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => is_string($this->input('first_name')) ? trim($this->input('first_name')) : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? trim($this->input('last_name')) : $this->input('last_name'),
            'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pricing_mode' => ['required', new Enum(PricingMode::class)],
            'duration' => ['required', 'integer', 'min:1', 'max:30'],
            // All optional (client request): the réception completes the guest's details at the desk.
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-\/]{6,24}$/'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['required', 'integer', 'min:0', 'max:10'],
            // Honeypot: hidden from people, filled in by most spam bots.
            'website' => ['nullable', 'max:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $room = $this->route('room');
                $mode = $this->pricingMode();

                if (! $room instanceof Room || ! $mode) {
                    return;
                }

                if ($mode->rateFor($room->roomType) === null) {
                    $validator->errors()->add('pricing_mode', "La formule « {$mode->getLabel()} » n'est pas proposée pour cette chambre.");
                }

                if (! $mode->canStartAt($this->arrival())) {
                    $validator->errors()->add('pricing_mode', 'La nuitée est proposée à partir de '.str_replace(':', 'h', config('hotel.night.from')).'. Choisissez une autre formule.');
                }

                if (filled($this->input('phone')) && StayRequest::query()->holding()->get(['phone'])
                    ->contains(fn (StayRequest $r) => StayRequest::normalizePhone($r->phone) === StayRequest::normalizePhone($this->input('phone')))) {
                    $validator->errors()->add('phone', 'Une demande est déjà en cours avec ce numéro. Présentez-vous à la réception.');
                }
            },
        ];
    }

    public function pricingMode(): ?PricingMode
    {
        return PricingMode::tryFrom((string) $this->input('pricing_mode'));
    }

    /**
     * The guest is here now (same rule as an affectation, see RoomAssignmentRequest::arrival()).
     */
    public function arrival(): Carbon
    {
        $now = now()->second(0);

        return $now->format('H:i') === '00:00' ? $now->addMinute() : $now;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pricing_mode' => 'formule',
            'duration' => 'durée',
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'phone' => 'téléphone',
            'adults' => 'adultes',
            'children' => 'enfants',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Saisissez un numéro de téléphone valide (chiffres uniquement, ex. 07 00 00 00 00).',
            'website.max' => 'Votre demande n\'a pas pu être envoyée.',
        ];
    }
}
