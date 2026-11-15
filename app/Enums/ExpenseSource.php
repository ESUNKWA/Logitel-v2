<?php

namespace App\Enums;

/**
 * Where an expense's money comes from. The first three come out of the receptionist's till
 * (cash drawer, the hotel's Orange Money / Wave phones) and reduce what she hands over at the
 * cash closing; « hors caisse » (paid by the Gérant, the bank…) only counts in the month's result.
 */
enum ExpenseSource: string
{
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';
    case Wave = 'wave';
    case HorsCaisse = 'hors_caisse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Especes => 'Espèces (caisse)',
            self::OrangeMoney => 'Orange Money (caisse)',
            self::Wave => 'Wave (caisse)',
            self::HorsCaisse => 'Hors caisse (gérant, banque…)',
        };
    }

    /** The till method it comes out of, null for « hors caisse ». */
    public function tillMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom($this->value);
    }
}
