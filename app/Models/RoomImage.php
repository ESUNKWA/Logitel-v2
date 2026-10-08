<?php

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RoomImage extends Model
{
    use RecordsCreator;

    public const DISK = 'public';

    public const MAX_PER_ROOM = 5;

    protected $fillable = [
        'path',
        'position',
    ];

    protected static function booted(): void
    {
        // The file goes with the row.
        static::deleted(fn (RoomImage $image) => Storage::disk(self::DISK)->delete($image->path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Relative to the current host (asset()), so it works whatever APP_URL says.
     */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }
}
