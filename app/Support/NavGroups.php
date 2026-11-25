<?php

namespace App\Support;

use App\Enums\Department;
use App\Models\CatalogItem;
use App\Models\User;

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
        'admin.catalog.*',
        'admin.venues.*',
    ];

    /**
     * Sales services in the menu for `$user`: those she may sell for, once they have an active
     * catalogue item — or all of them for whoever manages the catalogue (to set them up).
     *
     * @return list<Department>
     */
    public static function saleServices(?User $user): array
    {
        if (! $user?->can('sales.create')) {
            return [];
        }
        $manager = $user->can('catalog.manage');
        $active = $manager ? [] : CatalogItem::query()->active()->distinct()->pluck('department')
            ->map(fn ($d) => $d instanceof Department ? $d->value : (string) $d)->all();

        return array_values(array_filter(Department::offeredCounterServices(), fn ($d) => $manager || in_array($d->value, $active, true)));
    }
}
