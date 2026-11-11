<?php

namespace App\Models;

use App\Enums\PricingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An affectation deleted by the Gérant (« Corbeille »), see App\Support\FileTrash. The summary
 * columns are the deletion log (kept for ever); `payload` is the encrypted copy of the removed
 * rows, present only while the file is in the trash.
 *
 * Nothing here is mass-assignable: every column is set by FileTrash with forceFill().
 */
class TrashedFile extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'trashed_at' => 'datetime',
            'restored_at' => 'datetime',
            'purged_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            // Guest details and amounts: encrypted at rest with the application key.
            'payload' => 'encrypted:array',
        ];
    }

    /** Files still in the trash (neither restored nor erased for good). */
    public function scopeInTrash(Builder $query): Builder
    {
        return $query->whereNull('restored_at')->whereNull('purged_at');
    }

    public function isInTrash(): bool
    {
        return $this->restored_at === null && $this->purged_at === null;
    }

    public static function retentionDays(): int
    {
        return max(1, (int) config('hotel.trash_retention_days', 30));
    }

    /** When the automatic deletion erases it for good. */
    public function purgeDueAt(): Carbon
    {
        return $this->trashed_at->copy()->addDays(self::retentionDays());
    }

    public function pricingMode(): ?PricingMode
    {
        return $this->pricing_mode ? PricingMode::tryFrom($this->pricing_mode) : null;
    }

    public function trashedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trashed_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function purgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purged_by');
    }
}
