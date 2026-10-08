<?php

namespace App\Enums;

/**
 * Stored status of a guest's room request ("demande"). "Expirée" isn't stored: it's a pending
 * request whose expires_at has passed (see StayRequest::displayStatus()).
 */
enum StayRequestStatus: string
{
    case EnAttente = 'en_attente';
    case Validee = 'validee';
    case Refusee = 'refusee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Validee => 'Validée',
            self::Refusee => 'Refusée',
        };
    }

    /**
     * Bootstrap color token (used as `bg-{color}` for status badges).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'primary',
            self::Validee => 'success',
            self::Refusee => 'danger',
        };
    }
}
