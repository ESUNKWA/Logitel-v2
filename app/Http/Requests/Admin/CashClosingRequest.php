<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Models\CashClosing;
use App\Support\BusinessDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * A cash closing (V2): every payment method declared (client decision), optional note / coin count
 * (which then sets the cash amount, server side), a comment mandatory when something doesn't match.
 * Expected amounts are never read from the form.
 */
class CashClosingRequest extends FormRequest
{
    /** How many business days back a till may still be closed. */
    public const DAYS_BACK = 7;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Amounts typed with thousands separators (« 15 000 »).
        $declared = $this->input('declared');
        if (is_array($declared)) {
            $this->merge(['declared' => array_map(fn ($v) => is_string($v) ? preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $v) : $v, $declared)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = BusinessDay::today();
        $rules = [
            'jour' => ['required', 'string', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString(), 'after_or_equal:'.$today->copy()->subDays(self::DAYS_BACK)->toDateString()],
            'declared' => ['required', 'array'],
            'counted' => ['nullable', 'array'],
            'counted.notes' => ['nullable', 'array'],
            'counted.coins' => ['nullable', 'array'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
        foreach (PaymentMethod::cases() as $method) {
            $rules['declared.'.$method->value] = ['required', 'integer', 'min:0', 'max:1000000000'];
        }
        foreach (CashClosing::NOTES as $v) {
            $rules['counted.notes.'.$v] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }
        foreach (CashClosing::COINS as $v) {
            $rules['counted.coins.'.$v] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $expected = CashClosing::expectedFor($this->day(), $this->user()->id);
                $declared = $this->declared();
                $gap = collect($expected)->contains(fn ($row, $method) => abs(($declared[$method] ?? 0) - $row['expected']) >= 0.01);
                if ($gap && trim((string) $this->input('comment')) === '') {
                    $validator->errors()->add('comment', 'Ce que vous remettez ne correspond pas à ce qui est attendu : expliquez l\'écart dans le commentaire.');
                }
            },
        ];
    }

    public function day(): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', (string) $this->input('jour'));
    }

    /**
     * The note / coin count, only the positive ones, or null when none was typed.
     *
     * @return array{notes: array<int, int>, coins: array<int, int>}|null
     */
    public function counted(): ?array
    {
        $clean = ['notes' => [], 'coins' => []];
        foreach (['notes' => CashClosing::NOTES, 'coins' => CashClosing::COINS] as $kind => $values) {
            foreach ($values as $value) {
                $n = (int) ($this->validated('counted.'.$kind.'.'.$value) ?? 0);
                if ($n > 0) {
                    $clean[$kind][$value] = $n;
                }
            }
        }

        return $clean['notes'] === [] && $clean['coins'] === [] ? null : $clean;
    }

    /**
     * Declared amounts per method; with a note / coin count, cash = its total (computed here).
     *
     * @return array<string, int>
     */
    public function declared(): array
    {
        $declared = [];
        foreach (PaymentMethod::cases() as $method) {
            $declared[$method->value] = (int) $this->input('declared.'.$method->value);
        }
        if ($counted = $this->counted()) {
            $declared[PaymentMethod::Especes->value] = CashClosing::countedTotal($counted);
        }

        return $declared;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'jour' => 'journée',
            'declared.especes' => 'espèces remises',
            'declared.orange_money' => 'Orange Money remis',
            'declared.wave' => 'Wave remis',
            'comment' => 'commentaire',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'jour.after_or_equal' => 'Cette journée est trop ancienne pour être clôturée ('.self::DAYS_BACK.' jours au plus).',
            'jour.before_or_equal' => 'On ne clôture pas une journée future.',
        ];
    }
}
