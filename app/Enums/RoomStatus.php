<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Disponible = 'disponible';
    case Occupee = 'occupee';
    case Nettoyage = 'nettoyage';
    case HorsService = 'hors_service';

    public function getLabel(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Occupee => 'Occupée',
            self::Nettoyage => 'En nettoyage',
            self::HorsService => 'Hors service',
        };
    }

    /**
     * Bootstrap color token (used as `bg-{color}` for status badges).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Disponible => 'success',
            self::Occupee => 'danger',
            self::Nettoyage => 'warning',
            self::HorsService => 'secondary',
        };
    }
}
