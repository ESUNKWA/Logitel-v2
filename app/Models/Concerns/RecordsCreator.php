<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps `created_by` with the logged-in staff member when the record is created (never taken
 * from input: the column isn't mass assignable). Null outside a request (seeders, console).
 */
trait RecordsCreator
{
    protected static function bootRecordsCreator(): void
    {
        static::creating(function ($model) {
            if ($model->created_by === null && Auth::id() !== null) {
                $model->created_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
