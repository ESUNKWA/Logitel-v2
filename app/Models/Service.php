<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A service or facility of the hotel (restaurant, pool, laundry…), managed in the application
 * (« Site web → Services ») and shown on the website: with a photo as a large card, without
 * one as an icon tile; `show_on_home` features it on the home page.
 */
class Service extends Model
{
    /**
     * Icons offered in the form (Font Awesome 5 → label).
     */
    public const ICONS = [
        'fa-concierge-bell' => 'Réception / accueil',
        'fa-utensils' => 'Restaurant',
        'fa-coffee' => 'Petit-déjeuner / café',
        'fa-glass-martini-alt' => 'Bar',
        'fa-swimmer' => 'Piscine',
        'fa-dumbbell' => 'Salle de sport',
        'fa-spa' => 'Spa / bien-être',
        'fa-wifi' => 'Wi-Fi',
        'fa-snowflake' => 'Climatisation',
        'fa-tv' => 'Télévision',
        'fa-parking' => 'Parking',
        'fa-shuttle-van' => 'Navette / transport',
        'fa-tshirt' => 'Blanchisserie',
        'fa-briefcase' => 'Salle de réunion',
        'fa-shield-alt' => 'Sécurité 24h/24',
        'fa-baby' => 'Enfants',
        'fa-bolt' => 'Groupe électrogène',
        'fa-bed' => 'Chambres',
    ];

    protected $fillable = ['title', 'category', 'description', 'icon', 'position', 'is_published', 'show_on_home'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_on_home' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // The photo file goes with the service.
        static::deleted(function (Service $service) {
            if ($service->photo) {
                Storage::disk('public')->delete($service->photo);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    public function iconClass(): string
    {
        return array_key_exists((string) $this->icon, self::ICONS) ? $this->icon : 'fa-concierge-bell';
    }
}
