<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Http\Requests\Admin\Concerns\ValidatesDiscount;
use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Room;
use App\Models\SpecialRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * An "affectation": the guest moves in now. The arrival is the server's current time (never
 * submitted); staff pick the pricing mode and the duration in that mode's unit. The rate is
 * always the room type's (not editable — client request); a reduction goes through the
 * optional discount (amount or percentage), so it stays visible and traced.
 */
class RoomAssignmentRequest extends FormRequest
{
    use ValidatesDiscount;
    use ValidatesPaymentLines;

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentLines();
    }

    /**
     * An affectation is paid in full on the spot, so making one also needs the right to cash
     * (the route itself checks affectations.manage).
     */
    public function authorize(): bool
    {
        return $this->user()->can('payments.create');
    }

    /**
     * Availability for the room bound to the route is checked separately in
     * RoomAssignmentController@store — there's no `room_id` form field here,
     * the room comes from the route model binding.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The client is optional at the desk: an existing guest, a new one (any field may be
            // left blank) or nobody at all ("Client de passage", see RoomAssignmentController).
            'guest_id' => ['nullable', 'exists:guests,id'],
            'new_first_name' => ['nullable', 'string', 'max:255'],
            'new_last_name' => ['nullable', 'string', 'max:255'],
            'new_phone' => ['nullable', 'string', 'max:50'],
            'pricing_mode' => ['required', new Enum(PricingMode::class)],
            'duration' => ['required', 'integer', 'min:1', 'max:365'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            ...$this->discountRules(),
            // An affectation is paid in full at check-in (one or several methods); checked in after().
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $mode = $this->pricingMode();
                $room = $this->targetRoom();

                if (! $mode || ! $room instanceof Room) {
                    return;
                }

                if ($mode->rateFor($room->roomType) === null) {
                    $validator->errors()->add('pricing_mode', "Le mode « {$mode->getLabel()} » n'est pas proposé pour ce type de chambre (aucun tarif défini).");

                    return;
                }

                // Nothing is billed yet: the due amount is the stay's total (its forfait, else room
                // type rate × duration, − discount), and it must be cashed in full — no more, no
                // less (a free stay needs no payment).
                if (is_numeric($this->input('duration'))) {
                    $subtotal = $this->subtotal();
                    $this->checkDiscount($validator, $subtotal);
                    $total = $this->totalAfterDiscount($subtotal);
                    $this->checkNotAboveDue($validator, $total);
                    $this->checkCashReceivedEntered($validator);

                    $paid = $this->paymentLinesTotal();
                    if (bccomp($total, '0', 2) > 0 && bccomp($paid, $total, 2) < 0 && ! $validator->errors()->has('payments')) {
                        $validator->errors()->add('payments', 'Une affectation se paie en totalité : '.number_format((float) $paid, 0, ',', ' ')
                            .' FCFA saisis sur '.number_format((float) $total, 0, ',', ' ').' FCFA à encaisser.');
                    }
                }

                if (! $mode->canStartAt($this->arrival())) {
                    $validator->errors()->add('pricing_mode', 'La nuitée commence à '.str_replace(':', 'h', config('hotel.night.from')).' : choisissez « À l\'heure », « Journée » ou « Séjour » pour une entrée immédiate.');
                }
            },
        ];
    }

    /**
     * The room being assigned (route-bound here; the request's room when validating a guest's request).
     */
    public function targetRoom(): ?Room
    {
        $room = $this->route('room');

        return $room instanceof Room ? $room : null;
    }

    /**
     * The room type's rate for the chosen mode (the form's rate field is display only).
     */
    public function unitRate(): string
    {
        return (string) ($this->pricingMode()?->rateFor($this->targetRoom()?->roomType) ?? '0');
    }

    /**
     * The forfait this stay gets (same rule as the model: SpecialRate::best() on the business
     * day of its start), or null.
     */
    public function specialRate(): ?SpecialRate
    {
        $mode = $this->pricingMode();
        $duration = (int) $this->input('duration');
        if (! $mode || $duration < 1) {
            return null;
        }
        [$start] = $mode->resolve($this->arrival(), $duration);

        return SpecialRate::best($this->targetRoom()?->room_type_id, $mode, $duration, $start, $this->unitRate());
    }

    /**
     * The stay's price before discount: its forfait's, else rate × duration.
     */
    public function subtotal(): string
    {
        $special = $this->specialRate();

        return $special ? bcadd((string) $special->price, '0', 2) : bcmul($this->unitRate(), (string) (int) $this->input('duration'), 2);
    }

    public function pricingMode(): ?PricingMode
    {
        return PricingMode::tryFrom((string) $this->input('pricing_mode'));
    }

    /**
     * The guest arrives now. At exactly 00:00 a minute is added so a nuitée isn't mistaken for
     * a date-only arrival (see PricingMode::start()).
     */
    public function arrival(): Carbon
    {
        $now = now()->second(0);

        return $now->format('H:i') === '00:00' ? $now->addMinute() : $now;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int} [start, end, duration]
     */
    public function period(): array
    {
        return $this->pricingMode()->resolve($this->arrival(), (int) $this->validated('duration'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pricing_mode' => 'mode',
            'duration' => 'durée',
            'unit_rate' => 'tarif',
            ...$this->discountAttributeNames(),
            'new_first_name' => 'prénom du client',
            'new_last_name' => 'nom du client',
            'new_phone' => 'téléphone du client',
            ...$this->paymentLineAttributes(),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->paymentLineMessages(),
        ];
    }
}
