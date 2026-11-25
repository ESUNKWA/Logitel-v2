<?php

namespace App\Http\Requests\Admin;

use App\Enums\Department;
use App\Http\Requests\Admin\Concerns\ValidatesPaymentLines;
use App\Models\CatalogItem;
use App\Models\Sale;
use App\Models\Venue;
use App\Models\VenueBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A room booking (meetings & events): venue, day, start and end times, formula, participants,
 * client, catalogue services (`items[id] = quantity`), discount; when created, its status
 * (option = quote / confirmed) and an optional deposit (never above the total). The total is the
 * venue's rate + the catalogue's prices, computed here and in VenueBooking — never from the form.
 */
class VenueBookingRequest extends FormRequest
{
    use ValidatesPaymentLines;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentLines();
    }

    private function creating(): bool
    {
        return ! $this->route('booking') instanceof VenueBooking;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'formula' => ['required', Rule::in(array_keys(VenueBooking::FORMULAS))],
            'participants' => ['required', 'integer', 'min:1', 'max:10000'],
            'client_name' => ['required', 'string', 'max:120'],
            'client_phone' => ['required', 'string', 'max:50'],
            'organization' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => $this->creating() ? ['required', Rule::in([VenueBooking::STATUS_OPTION, VenueBooking::STATUS_CONFIRMED])] : ['exclude'],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:'.Sale::MAX_QUANTITY],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            // Deposit when booking only; later payments go through the invoice.
            ...$this->paymentLineRules(required: false),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['venue_id', 'event_date', 'start_time', 'end_time', 'formula', 'items', 'items.*', 'discount_amount'])) {
                    return;
                }

                // A new booking (or a moved one) is for later.
                $existing = $this->route('booking');
                $moved = ! $existing instanceof VenueBooking || ! $existing->start_at->equalTo($this->startAt());
                if ($moved && $this->startAt()->lessThanOrEqualTo(now())) {
                    $validator->errors()->add('event_date', 'Une réservation de salle porte sur un créneau à venir.');

                    return;
                }

                $quantities = $this->quantities();
                if ($quantities === null) {
                    $validator->errors()->add('items', 'Prestations invalides.');

                    return;
                }
                $prices = CatalogItem::query()->ofDepartment(Department::Evenements)->active()->whereIn('id', array_keys($quantities))->pluck('price', 'id');
                if ($prices->count() !== count($quantities)) {
                    $validator->errors()->add('items', 'Une prestation choisie n\'est pas (ou plus) proposée.');

                    return;
                }

                $venue = Venue::query()->find((int) $this->input('venue_id'));
                $subtotal = $venue ? $venue->rateFor((string) $this->input('formula')) : '0';
                foreach ($quantities as $id => $quantity) {
                    $subtotal = bcadd($subtotal, bcmul((string) $prices[$id], (string) $quantity, 2), 2);
                }
                if (bccomp($this->discount(), $subtotal, 2) > 0) {
                    $validator->errors()->add('discount_amount', 'La remise ne peut pas dépasser le total ('.number_format((float) $subtotal, 0, ',', ' ').' FCFA).');

                    return;
                }

                if ((array) $this->input('payments', []) !== []) {
                    if (! $this->creating()) {
                        $validator->errors()->add('payments', 'Encaissez la suite depuis la facture de la réservation.');

                        return;
                    }
                    $this->checkNotAboveDue($validator, bcsub($subtotal, $this->discount(), 2));
                    $this->checkCashReceivedEntered($validator);
                }
            },
        ];
    }

    public function startAt(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d H:i', $this->input('event_date').' '.$this->input('start_time'));
    }

    public function endAt(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d H:i', $this->input('event_date').' '.$this->input('end_time'));
    }

    /**
     * Catalogue item id => quantity (> 0); null when a key isn't an id.
     *
     * @return array<int, int>|null
     */
    public function quantities(): ?array
    {
        $quantities = [];
        foreach ((array) $this->input('items', []) as $id => $quantity) {
            if (! is_int($id) && ! ctype_digit((string) $id)) {
                return null;
            }
            if ((int) $quantity > 0) {
                $quantities[(int) $id] = (int) $quantity;
            }
        }

        return $quantities;
    }

    public function discount(): string
    {
        return is_numeric($this->input('discount_amount')) ? bcadd((string) $this->input('discount_amount'), '0', 2) : '0';
    }

    /**
     * What VenueBooking::book() / change() need.
     *
     * @return array<string, mixed>
     */
    public function bookingData(): array
    {
        return [
            'venue_id' => (int) $this->validated('venue_id'),
            'start_at' => $this->startAt(),
            'end_at' => $this->endAt(),
            'formula' => $this->validated('formula'),
            'participants' => (int) $this->validated('participants'),
            'client_name' => $this->validated('client_name'),
            'client_phone' => $this->validated('client_phone'),
            'organization' => $this->validated('organization'),
            'title' => $this->validated('title'),
            'notes' => $this->validated('notes'),
            'status' => $this->validated('status') ?? VenueBooking::STATUS_OPTION,
            'quantities' => $this->quantities() ?? [],
            'discount' => $this->discount(),
            'discount_reason' => $this->validated('discount_reason'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...$this->paymentLineMessages(), 'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->paymentLineAttributes(),
            'payments' => 'acompte',
            'venue_id' => 'salle',
            'event_date' => 'date',
            'start_time' => 'heure de début',
            'end_time' => 'heure de fin',
            'formula' => 'formule',
            'participants' => 'nombre de participants',
            'client_name' => 'client',
            'client_phone' => 'téléphone',
            'organization' => 'organisation',
            'title' => 'objet',
            'items' => 'prestations',
            'items.*' => 'quantité',
            'discount_amount' => 'remise',
            'discount_reason' => 'motif de la remise',
        ];
    }
}
