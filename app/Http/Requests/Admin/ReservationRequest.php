<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\Admin\Concerns\ValidatesDiscount;
use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Rules\RoomIsAvailableForDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * A réservation: a stay booked ahead, in any of the four pricing modes (arrival + duration;
 * a nuitée arrival is a date — the night starts at 22:00). Arrivals now or in the past are
 * affectations, made from the room board, not réservations. An optional deposit ("acompte")
 * can be taken when booking. The legacy date-based shape (check_in → check_out, as nuitées)
 * is still accepted: the duration is then inferred. The rate is the room type's (not editable),
 * kept as booked when neither the room nor the mode changes; reductions are discounts.
 */
class ReservationRequest extends FormRequest
{
    use ValidatesDiscount;
    use ValidatesPaymentLines;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentLines();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reservation = $this->route('reservation');

        return [
            // The client: an existing one, or a new one entered here — only the phone is
            // required (client request); natural person or company.
            'guest_id' => ['nullable', 'exists:guests,id'],
            'new_client_type' => ['nullable', 'in:'.Guest::TYPE_PERSON.','.Guest::TYPE_COMPANY],
            'new_first_name' => ['nullable', 'string', 'max:255'],
            'new_last_name' => ['nullable', 'string', 'max:255'],
            'new_company_name' => ['nullable', 'string', 'max:255'],
            'new_tax_id' => ['nullable', 'string', 'max:100'],
            'new_phone' => ['required_without:guest_id', 'nullable', 'string', 'max:50'],
            'pricing_mode' => ['nullable', new Enum(PricingMode::class)],
            'check_in' => ['required', 'date'],
            'duration' => ['required_without:check_out', 'nullable', 'integer', 'min:1', 'max:365'],
            'check_out' => ['required_without:duration', 'nullable', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([ReservationStatus::EnAttente->value, ReservationStatus::Confirmee->value])],
            'room_id' => ['required', 'exists:rooms,id', new RoomIsAvailableForDates($reservation?->id)],
            'notes' => ['nullable', 'string'],
            // Created from an online booking request (« Demandes en ligne »), marked handled on save.
            'booking_request_id' => ['nullable', 'integer', 'exists:booking_requests,id'],
            ...$this->discountRules(),
            // Deposit (acompte) at booking only; later payments go through the invoice.
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['check_in', 'duration', 'check_out', 'room_id', 'pricing_mode'])) {
                    return;
                }

                $mode = $this->pricingMode();
                [$start, , $duration] = $this->period();
                $existing = $this->route('reservation');

                // A réservation is for later; booking (or moving) it into the past is refused.
                $startChanged = ! $existing instanceof Reservation
                    || ! $existing->check_in->equalTo($start) || $existing->pricing_mode !== $mode;
                if ($startChanged && $start->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('check_in', 'Une réservation porte sur une arrivée à venir. Pour un client qui arrive maintenant, affectez la chambre depuis le tableau des chambres.');
                }

                $room = Room::with('roomType')->find($this->input('room_id'));
                if ($room && $room->status === RoomStatus::HorsService) {
                    $validator->errors()->add('room_id', "La chambre N°{$room->number} est hors service : elle ne peut pas être réservée.");
                }
                if ($room && $mode->rateFor($room->roomType) === null) {
                    $validator->errors()->add('pricing_mode', "Le mode « {$mode->getLabel()} » n'est pas proposé pour ce type de chambre (aucun tarif défini).");

                    return;
                }

                $subtotal = bcmul($this->unitRate(), (string) $duration, 2);
                $this->checkDiscount($validator, $subtotal);
                $total = $this->totalAfterDiscount($subtotal);

                if ($existing instanceof Reservation) {
                    $paid = $existing->invoice?->amountPaid() ?? '0';
                    if (bccomp($total, $paid, 2) < 0) {
                        $validator->errors()->add('duration', 'Le nouveau montant ('.number_format((float) $total, 0, ',', ' ').' FCFA) serait inférieur à l\'acompte déjà encaissé ('
                            .number_format((float) $paid, 0, ',', ' ').' FCFA).');
                    }
                } else {
                    // Deposit optional (none, partial or full), never above the total.
                    $this->checkNotAboveDue($validator, $total);
                    $this->checkCashReceivedEntered($validator);
                }
            },
        ];
    }

    /**
     * The rate billed: unchanged for an existing booking keeping its room and mode (its price
     * stays the one agreed when booking), otherwise the room type's current rate for the mode.
     */
    public function unitRate(): string
    {
        $existing = $this->route('reservation');
        $mode = $this->pricingMode();

        if ($existing instanceof Reservation && (int) $existing->room_id === (int) $this->input('room_id') && $existing->pricing_mode === $mode) {
            return (string) $existing->unit_rate;
        }

        return (string) ($mode->rateFor(Room::with('roomType')->find($this->input('room_id'))?->roomType) ?? '0');
    }

    public function pricingMode(): PricingMode
    {
        return PricingMode::tryFrom((string) $this->input('pricing_mode')) ?? PricingMode::Nuitee;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: int} [start, end, duration]
     */
    public function period(): array
    {
        return $this->pricingMode()->resolve(
            Carbon::parse($this->input('check_in')),
            filled($this->input('duration')) ? (int) $this->input('duration') : null,
            filled($this->input('check_out')) ? Carbon::parse($this->input('check_out')) : null,
        );
    }

    /**
     * Validated attributes ready for Reservation::create()/update(): mode, normalised arrival
     * and duration (the model derives check_out and the amount). Deposit lines excluded.
     *
     * @return array<string, mixed>
     */
    public function reservationAttributes(): array
    {
        [$start, , $duration] = $this->period();

        $data = collect($this->validated())->except([
            'check_out', 'payments', 'booking_request_id', 'discount_type', 'discount_value', 'discount_reason',
            'new_client_type', 'new_first_name', 'new_last_name', 'new_company_name', 'new_tax_id', 'new_phone',
        ])->all();

        return [...$data, ...$this->discountAttributes(), 'unit_rate' => $this->unitRate(), 'pricing_mode' => $this->pricingMode(), 'check_in' => $start, 'duration' => $duration];
    }

    /**
     * The chosen client, or a new one created from the fields typed here. Call inside the
     * write transaction.
     */
    public function resolveGuestId(): int
    {
        if (filled($this->validated('guest_id'))) {
            return (int) $this->validated('guest_id');
        }

        $company = $this->validated('new_client_type') === Guest::TYPE_COMPANY;

        return Guest::create([
            'type' => $company ? Guest::TYPE_COMPANY : Guest::TYPE_PERSON,
            'first_name' => trim((string) $this->validated('new_first_name')),
            'last_name' => trim((string) $this->validated('new_last_name')),
            'company_name' => $company ? $this->validated('new_company_name') : null,
            'tax_id' => $company ? $this->validated('new_tax_id') : null,
            'phone' => trim((string) $this->validated('new_phone')),
        ])->id;
    }

    public function messages(): array
    {
        return [
            ...$this->paymentLineMessages(),
            'new_phone.required_without' => 'Choisissez un client existant ou saisissez au moins le téléphone du nouveau client.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->paymentLineAttributes(),
            'payments' => 'acompte',
            'duration' => 'durée',
            'unit_rate' => 'tarif',
            'check_in' => 'arrivée',
            'new_phone' => 'téléphone du client',
            'new_company_name' => 'raison sociale',
            'new_tax_id' => 'n° RCCM / NCC',
            ...$this->discountAttributeNames(),
        ];
    }
}
