<?php

namespace App\Models;

use App\Exceptions\DeletionBlocked;
use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory, RecordsCreator;

    /**
     * Name given to a guest checked in without any details ("Client de passage").
     */
    public const WALK_IN_FIRST_NAME = 'Client';

    public const WALK_IN_LAST_NAME = 'de passage';

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'id_document_type',
        'id_document_number',
        'nationality',
        'address',
        'notes',
    ];

    protected static function booted(): void
    {
        // A guest with stays keeps their history (stays, invoices, payments).
        static::deleting(function (Guest $guest) {
            $count = $guest->reservations()->count();
            if ($count > 0) {
                throw new DeletionBlocked("Ce client a {$count} séjour(s) ou réservation(s) : il ne peut pas être supprimé, pour conserver l'historique et les factures.");
            }
        });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
