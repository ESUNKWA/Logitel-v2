<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An event of the hotel (concert, soirée, brunch…) announced on the website, managed in the
 * application (« Site web → Événements »). Shown while upcoming or in progress: until its end,
 * or until the end of its day when it has none (`visible_until`, derived on save). Past events
 * leave the website by themselves and stay in the application.
 */
class SiteEvent extends Model
{
    protected $fillable = ['title', 'starts_at', 'ends_at', 'location', 'description', 'price_label', 'booking_info', 'is_published'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'visible_until' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteEvent $event) {
            $event->visible_until = $event->ends_at ?? $event->starts_at->copy()->endOfDay();
        });

        // The poster goes with the event.
        static::deleted(function (SiteEvent $event) {
            if ($event->photo) {
                Storage::disk('public')->delete($event->photo);
            }
        });
    }

    /**
     * Published and not over yet, soonest first: what the website shows.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('visible_until', '>=', now())->orderBy('starts_at')->orderBy('id');
    }

    public function isOver(): bool
    {
        return $this->visible_until->isPast();
    }

    public function isOngoing(): bool
    {
        return $this->starts_at->isPast() && ! $this->isOver();
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    /**
     * « Samedi 24 octobre 2026 à 20h00 », « Du 24 au 26 octobre 2026 », « Samedi 24 octobre, 20h00 – 23h00 ».
     */
    public function whenLabel(): string
    {
        $start = $this->starts_at;
        $time = fn ($d) => $d->format('H\hi');

        if (! $this->ends_at) {
            return ucfirst($start->translatedFormat('l j F Y')).' à '.$time($start);
        }
        if ($start->isSameDay($this->ends_at)) {
            return ucfirst($start->translatedFormat('l j F Y')).', '.$time($start).' – '.$time($this->ends_at);
        }

        return 'Du '.$start->translatedFormat('j F Y').' à '.$time($start).' au '.$this->ends_at->translatedFormat('j F Y').' à '.$time($this->ends_at);
    }

    /**
     * The booking info as a link: [href, label] — an http(s) URL, a phone (tel:) or null for plain text.
     *
     * @return array{0: string|null, 1: string}|null
     */
    public function bookingLink(): ?array
    {
        $info = trim((string) $this->booking_info);
        if ($info === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $info)) {
            return [$info, 'Réserver / s\'inscrire'];
        }
        if (preg_match('/^\+?[0-9][0-9 .\-\/]{6,24}$/', $info)) {
            return ['tel:'.preg_replace('/[^0-9+]/', '', $info), $info];
        }

        return [null, $info];
    }
}
