<?php

/*
 * Hotel business rules. Pricing formulas match the four optional rates of a room type
 * (room_types.hourly_rate / day_rate / night_rate / stay_rate).
 *
 * These are only the defaults: every value below can be changed by the manager in
 * Configuration → Paramètres (stored in the `settings` table and applied over this config at
 * boot by App\Support\HotelSettings). Always read them through config('hotel.*').
 */
return [

    // "Journée commerciale" (business day): starts at this time and ends one minute before
    // it the next day (06:00 → 05:59). Used for "today" figures, monthly revenue, invoice dates.
    'business_day_start' => '06:00',

    // "Nuitée": arrival from this time, departure by this time the next day.
    'night' => [
        'from' => '22:00',
        'until' => '12:00',
    ],

    // "Journée": a fixed block of this many hours (e.g. in at 08:00, out at 14:00).
    'day_hours' => 6,

    // "Séjour": priced per period of at least this many hours.
    'stay_min_hours' => 24,

    // Mandatory cleaning time between two stays in the same room (minutes; 0 = none): a stay
    // ending at 12:00 lets the next one start at 12:30 at the earliest.
    'cleaning_buffer_minutes' => 30,

    // A guest's room request made from their phone (public page /demande) holds the room for
    // this many minutes; past that, if the réception hasn't handled it, the room is released.
    'request_expiry_minutes' => 30,

    // Hotel identity & contact details (Configuration → Hôtel), read through App\Support\HotelInfo:
    // receipt, back-office, guest request pages and the public site's contact blocks.
    // Empty values are simply not displayed.
    'info' => [
        'name' => 'Hôtel Evasion',
        'tagline' => '',
        'phone' => '',
        'phone2' => '',
        'whatsapp' => '',
        'email' => '',
        'website' => '',
        'address' => '',
        'city' => 'Bouaké',
        'country' => "Côte d'Ivoire",
        'rccm' => '',
        'ncc' => '',
        'tax_regime' => '',
        'legal_other' => '',
        'facebook' => '',
        'instagram' => '',
        'logo' => '',                  // path on the public disk (uploaded in Configuration → Hôtel)
        'login_background' => '',      // staff login page photo, same place (empty = navy gradient)
    ],

    // Customer receipt (thermal ticket printed after an affectation / a réservation).
    'receipt' => [
        'width' => 80,                 // roll width in mm: 58 or 80
        'footer' => 'Merci de votre visite et à bientôt !',
    ],

];
