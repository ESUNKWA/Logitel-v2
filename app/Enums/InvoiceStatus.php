<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Impayee = 'impayee';
    case PartiellementPayee = 'partiellement_payee';
    case Payee = 'payee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Impayee => 'Impayée',
            self::PartiellementPayee => 'Partiellement payée',
            self::Payee => 'Payée',
        };
    }

    /**
     * Bootstrap color token (used as `bg-{color}` for status badges).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Impayee => 'danger',
            self::PartiellementPayee => 'warning',
            self::Payee => 'success',
        };
    }
}
