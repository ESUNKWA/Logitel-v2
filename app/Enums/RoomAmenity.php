<?php

namespace App\Enums;

/**
 * Services & équipements of a room, shown with their icon on the public room page
 * (the list mirrors the public template's "Services & équipements" block, plus air-conditioning).
 * Icons are Font Awesome 5 names (the vendored version).
 */
enum RoomAmenity: string
{
    case Wifi = 'wifi';
    case Climatisation = 'climatisation';
    case Television = 'television';
    case Douche = 'douche';
    case Refrigerateur = 'refrigerateur';
    case LitKingSize = 'lit_king_size';
    case Piscine = 'piscine';
    case SalleDeSport = 'salle_de_sport';
    case Assistance = 'assistance';
    case TransfertAeroport = 'transfert_aeroport';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wifi => 'Wi-Fi gratuit',
            self::Climatisation => 'Climatisation',
            self::Television => 'Télévision connectée',
            self::Douche => 'Douche',
            self::Refrigerateur => 'Réfrigérateur',
            self::LitKingSize => 'Lit King Size',
            self::Piscine => 'Piscine',
            self::SalleDeSport => 'Salle de sport',
            self::Assistance => 'Assistance 24/7',
            self::TransfertAeroport => 'Transfert aéroport',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Wifi => 'fa-wifi',
            self::Climatisation => 'fa-snowflake',
            self::Television => 'fa-tv-alt',
            self::Douche => 'fa-shower',
            self::Refrigerateur => 'fa-refrigerator',
            self::LitKingSize => 'fa-bed',
            self::Piscine => 'fa-swimmer',
            self::SalleDeSport => 'fa-dumbbell',
            self::Assistance => 'fa-headset',
            self::TransfertAeroport => 'fa-plane-arrival',
        };
    }
}
