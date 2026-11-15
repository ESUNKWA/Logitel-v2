<?php

namespace App\Enums;

/**
 * An expense request goes « en attente » until the Gérant validates or refuses it; a validated
 * one may later be cancelled (with a reason). Only validated expenses count in the figures.
 */
enum ExpenseStatus: string
{
    case EnAttente = 'en_attente';
    case Validee = 'validee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'À valider',
            self::Validee => 'Validée',
            self::Refusee => 'Refusée',
            self::Annulee => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'warning',
            self::Validee => 'success',
            self::Refusee => 'danger',
            self::Annulee => 'secondary',
        };
    }
}
