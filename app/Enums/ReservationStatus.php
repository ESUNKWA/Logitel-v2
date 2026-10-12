<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case EnAttente = 'en_attente';
    case Confirmee = 'confirmee';
    case Enregistree = 'enregistree';
    case Terminee = 'terminee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Confirmee => 'Confirmée',
            self::Enregistree => 'Enregistrée (check-in)',
            self::Terminee => 'Terminée (sortie)',
            self::Annulee => 'Annulée',
        };
    }

    /**
     * Bootstrap color token (used as `bg-{color}` for status badges).
     */
    public function getColor(): string
    {
        return match ($this) {
            self::EnAttente => 'secondary',
            self::Confirmee => 'info',
            self::Enregistree => 'success',
            self::Terminee => 'warning',
            self::Annulee => 'danger',
        };
    }

    /**
     * Wording on the reservations side: once the guest has arrived the booking is "honoured"
     * (the stay itself lives on the affectations side).
     */
    public function reservationLabel(): string
    {
        return match ($this) {
            self::Enregistree, self::Terminee => 'Honorée (client arrivé)',
            default => $this->getLabel(),
        };
    }

    public function reservationColor(): string
    {
        return in_array($this, [self::Enregistree, self::Terminee], true) ? 'success' : $this->getColor();
    }

    /**
     * Wording on the affectations side (a stay that has started).
     */
    public function affectationLabel(): string
    {
        return match ($this) {
            self::Enregistree => 'En cours',
            self::Terminee => 'Terminée (sortie)',
            self::Annulee => 'Annulée',
            default => $this->getLabel(),
        };
    }

    public function affectationColor(): string
    {
        return match ($this) {
            self::Enregistree => 'danger',
            self::Terminee => 'secondary',
            default => $this->getColor(),
        };
    }

    /**
     * Statuses that still hold a room (block its availability). A finished stay (checked out,
     * possibly before its planned departure) frees the room; its physical state is then
     * handled by RoomStatus (nettoyage).
     */
    public function blocksAvailability(): bool
    {
        return ! in_array($this, [self::Annulee, self::Terminee], true);
    }

    /**
     * @return list<self>
     */
    public static function blockingAvailability(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status->blocksAvailability()));
    }
}
