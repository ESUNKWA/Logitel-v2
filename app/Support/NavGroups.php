<?php

namespace App\Support;

/**
 * The folding menu groups (client request, 2026-11-24): « Site web » and « Configuration » in the
 * « Plus » menu (mode simple) and the sidebar (mode complet). Route patterns of each group's pages:
 * the group of the current page starts open. One list for both layouts.
 */
final class NavGroups
{
    /** @var list<string> */
    public const SITE = ['admin.site.*'];

    /** @var list<string> */
    public const CONFIGURATION = [
        'admin.hotel.*',
        'admin.room-types.*',
        'admin.special-rates.*',
        'admin.perks.*',
        'admin.settings.*',
        'admin.users.*',
        'admin.roles.*',
        'admin.activity.*',
    ];
}
