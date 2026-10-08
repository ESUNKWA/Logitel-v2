<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, RecordsCreator;

    protected $fillable = [
        'invoice_id',
        'amount',
        'method',
        'paid_at',
        'reference',
        'notes',
        'settlement',
        'amount_received',
        'change_given',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'amount_received' => 'decimal:2',
            'change_given' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        static::created(fn (Payment $payment) => $payment->invoice->recalculateStatus());
        static::deleted(fn (Payment $payment) => $payment->invoice->recalculateStatus());
    }
}
