<?php

/*
 * Where the two halves of the app answer.
 *
 * Today (no domain yet): leave both empty — one address, the website at "/" and the management
 * application under "/admin".
 *
 * Later, with a domain: set both to give each half its own sub-domain, e.g.
 *   SITE_DOMAIN=www.hotel-evasion.ci   → website (and the guests' /demande page)
 *   ADMIN_DOMAIN=gestion.hotel-evasion.ci → management application, then served at its root
 * Each host only answers its own routes (sessions are separate too, cookies being per host).
 */
return [

    'site_domain' => env('SITE_DOMAIN') ?: null,

    'admin_domain' => env('ADMIN_DOMAIN') ?: null,

    // The application's URL prefix: "admin" on a shared address, none on its own sub-domain.
    'admin_prefix' => env('ADMIN_DOMAIN') ? '' : 'admin',

];
