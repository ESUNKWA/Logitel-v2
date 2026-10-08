<?php

namespace App\Enums;

/**
 * How a guest pays. A single settlement ("encaissement") can mix several methods — e.g. part in
 * cash, part by Wave — stored as one Payment row per method sharing a `settlement` id.
 */
enum PaymentMethod: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Wave = 'wave';

    public function getLabel(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::OrangeMoney => 'Orange Money',
            self::Wave => 'Wave',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Especes => 'fa-money-bill-wave',
            self::OrangeMoney, self::Wave => 'fa-mobile-alt',
        };
    }
}
