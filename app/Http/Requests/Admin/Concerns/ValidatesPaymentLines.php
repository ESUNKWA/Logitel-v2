<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Shared validation for an "encaissement" made of one or more payment lines
 * (`payments[i][method|amount|received]`), used by the invoice payment form and the
 * room assignment modal.
 */
trait ValidatesPaymentLines
{
    /**
     * Drops lines left empty in the form, and accepts the legacy single-payment shape
     * (method/amount at the top level) as a one-line settlement. Only the method and the amount
     * are recorded: any other key a client sends is ignored.
     */
    protected function normalizePaymentLines(): void
    {
        $lines = $this->input('payments');

        if ($lines === null && $this->filled('amount')) {
            $lines = [['method' => $this->input('method'), 'amount' => $this->input('amount')]];
        }

        if (is_array($lines)) {
            $lines = array_values(array_filter($lines, fn ($line) => is_array($line) && filled($line['amount'] ?? null)));
        }

        $this->merge(['payments' => $lines ?? []]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentLineRules(bool $required): array
    {
        return [
            'payments' => [$required ? 'required' : 'present', 'array', 'max:'.count(PaymentMethod::cases())],
            // One line per method: a mixed payment never repeats a method.
            'payments.*.method' => ['required', 'distinct', new Enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            // Cash only: what the guest handed over (the change is computed server-side).
            'payments.*.received' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * At the desk (affectation, réservation) the cashier must type what the guest handed over
     * for every cash line, so the change can be computed.
     */
    protected function checkCashReceivedEntered(Validator $validator): void
    {
        foreach ((array) $this->input('payments', []) as $i => $line) {
            if (is_array($line) && ($line['method'] ?? null) === PaymentMethod::Especes->value && blank($line['received'] ?? null)) {
                $validator->errors()->add("payments.{$i}.received", 'Saisissez le montant reçu du client en espèces, pour calculer la monnaie à rendre.');
            }
        }
    }

    /**
     * Sum of the lines (FCFA, 2 decimals).
     */
    protected function paymentLinesTotal(): string
    {
        return array_reduce((array) $this->input('payments', []), fn (string $sum, $line) => bcadd($sum, is_array($line) && is_numeric($line['amount'] ?? null) ? (string) $line['amount'] : '0', 2), '0');
    }

    protected function checkNotAboveDue(Validator $validator, string $due): void
    {
        $lines = $this->input('payments');

        if (! is_array($lines) || $validator->errors()->hasAny(['payments', 'payments.*'])) {
            return;
        }

        foreach ($lines as $i => $line) {
            $received = $line['received'] ?? null;
            if (($line['method'] ?? null) === PaymentMethod::Especes->value && is_numeric($received) && is_numeric($line['amount'] ?? null)
                && bccomp((string) $received, (string) $line['amount'], 2) < 0) {
                $validator->errors()->add("payments.{$i}.received", 'Le montant reçu ('.number_format((float) $received, 0, ',', ' ')
                    .' FCFA) est inférieur au montant encaissé en espèces ('.number_format((float) $line['amount'], 0, ',', ' ').' FCFA).');
            }
        }

        $total = array_reduce($lines, fn (string $sum, $line) => bcadd($sum, is_numeric($line['amount'] ?? null) ? (string) $line['amount'] : '0', 2), '0');

        if (bccomp($total, $due, 2) > 0) {
            $validator->errors()->add('payments', 'Le montant encaissé ('.number_format((float) $total, 0, ',', ' ').' FCFA) dépasse le reste dû ('
                .number_format((float) $due, 0, ',', ' ').' FCFA).');
        }
    }

    /**
     * @return list<array{method: string, amount: string, received: ?string}>
     */
    public function paymentLines(): array
    {
        return array_map(fn (array $line) => [
            'method' => $line['method'],
            'amount' => (string) $line['amount'],
            // Only meaningful for cash; blank = the guest gave the exact amount.
            'received' => $line['method'] === PaymentMethod::Especes->value && filled($line['received'] ?? null) ? (string) $line['received'] : null,
        ], $this->validated('payments') ?? []);
    }

    /**
     * @return array<string, string>
     */
    protected function paymentLineMessages(): array
    {
        return [
            'payments.*.method.distinct' => 'Chaque mode de paiement ne peut apparaître qu\'une fois par encaissement.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function paymentLineAttributes(): array
    {
        return [
            'payments' => 'encaissement',
            'payments.*.method' => 'mode de paiement',
            'payments.*.amount' => 'montant',
            'payments.*.received' => 'montant reçu',
        ];
    }
}
