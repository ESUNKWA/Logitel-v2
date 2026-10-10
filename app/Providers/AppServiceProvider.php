<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Support\HotelInfo;
use App\Support\HotelSettings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Manager-editable settings (Configuration → Paramètres) layered over config/hotel.php.
        HotelSettings::apply();

        // Hotel name, contact details and logo (Configuration → Hôtel), available in every view.
        View::share('hotelInfo', new HotelInfo);

        // Each half has its own Blade components: <x-site::page-hero>, <x-admin::file-badge>…
        Blade::anonymousComponentPath(resource_path('views/site/components'), 'site');
        Blade::anonymousComponentPath(resource_path('views/admin/components'), 'admin');

        // Deny by default: an ability only passes if the user's role explicitly grants it.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }

        // Client request: at least 4 characters, no other constraint.
        Password::defaults(fn () => Password::min(4));
    }
}
