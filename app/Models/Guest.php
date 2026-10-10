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

    /** Natural person (default) or legal entity (company, NGO, administration…). */
    public const TYPE_PERSON = 'physique';

    public const TYPE_COMPANY = 'morale';

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
        'type',
        'company_name',
        'tax_id',
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

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_COMPANY;
    }

    /**
     * The name shown everywhere: the company name for a legal entity, the person's name
     * otherwise — or "Client sans nom" when only a phone was given.
     */
    public function fullName(): string
    {
        $person = trim("{$this->first_name} {$this->last_name}");

        if ($this->isCompany()) {
            return trim((string) $this->company_name) ?: ($person !== '' ? $person : 'Entreprise sans nom');
        }

        return $person !== '' ? $person : 'Client sans nom';
    }

    /**
     * For a company: its contact person ("Prénom Nom"), if given.
     */
    public function contactName(): ?string
    {
        $person = trim("{$this->first_name} {$this->last_name}");

        return $this->isCompany() && $person !== '' ? $person : null;
    }

    public function typeLabel(): string
    {
        return $this->isCompany() ? 'Personne morale' : 'Personne physique';
    }
}
