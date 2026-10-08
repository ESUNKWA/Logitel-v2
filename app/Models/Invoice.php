<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Support\BusinessDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'number',
        'issued_at',
        'amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function amountPaid(): string
    {
        return (string) $this->payments()->sum('amount');
    }

    public function remainingAmount(): string
    {
        $remaining = bcsub((string) $this->amount, $this->amountPaid(), 2);

        return bccomp($remaining, '0', 2) > 0 ? $remaining : '0.00';
    }

    /**
     * Records one "encaissement": one Payment per line (a mixed payment has several lines, e.g.
     * cash + Wave), all sharing a settlement id. Never more than what is still due: checked
     * under a row lock so two concurrent settlements can't both pass the check.
     *
     * @param  list<array{method: PaymentMethod|string, amount: int|float|string, received?: int|float|string|null}>  $lines
     * @return Collection<int, Payment>
     *
     * @throws ValidationException
     */
    public function settle(array $lines, CarbonInterface $paidAt, ?string $notes = null): Collection
    {
        return DB::transaction(function () use ($lines, $paidAt, $notes) {
            $methods = array_map(fn (array $line) => $line['method'] instanceof PaymentMethod ? $line['method']->value : (string) $line['method'], $lines);
            if (count($methods) !== count(array_unique($methods))) {
                throw ValidationException::withMessages(['payments' => 'Chaque mode de paiement ne peut apparaître qu\'une fois par encaissement.']);
            }

            $invoice = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $total = array_reduce($lines, fn (string $sum, array $line) => bcadd($sum, (string) $line['amount'], 2), '0');
            $remaining = $invoice->remainingAmount();

            if (bccomp($total, $remaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'payments' => 'Le montant encaissé ('.number_format((float) $total, 0, ',', ' ').' FCFA) dépasse le reste dû ('
                        .number_format((float) $remaining, 0, ',', ' ').' FCFA).',
                ]);
            }

            // Fully paying a pending booking guarantees its room: first paid wins — refused if
            // its slot is already held by another stay or guaranteed booking.
            $booking = $invoice->reservation;
            if ($booking?->isPendingBooking() && ! $booking->is_guaranteed && bccomp($total, $remaining, 2) === 0) {
                $room = Room::query()->lockForUpdate()->find($booking->room_id);
                if ($holder = $room?->conflictingReservation($booking->check_in, $booking->check_out, $booking->id)) {
                    throw ValidationException::withMessages([
                        'payments' => 'Impossible de solder cette réservation : son créneau est déjà garanti. '.Room::conflictMessage($holder)
                            .' Reportez-la ou changez de chambre, ou encaissez seulement un acompte.',
                    ]);
                }
            }

            $settlement = (string) Str::uuid();

            $payments = collect($lines)->map(function (array $line) use ($invoice, $paidAt, $notes, $settlement) {
                $isCash = ($line['method'] instanceof PaymentMethod ? $line['method'] : PaymentMethod::from($line['method'])) === PaymentMethod::Especes;
                $amount = (string) $line['amount'];
                // Cash: what the guest handed over (default: the exact amount) and the change given back.
                $received = $isCash ? (string) ($line['received'] ?? $amount) : null;

                if ($isCash && bccomp($received, $amount, 2) < 0) {
                    throw ValidationException::withMessages(['payments' => 'Le montant reçu en espèces est inférieur au montant encaissé.']);
                }

                return $invoice->payments()->create([
                    'method' => $line['method'],
                    'amount' => $amount,
                    'amount_received' => $received,
                    'change_given' => $isCash ? bcsub($received, $amount, 2) : null,
                    'paid_at' => $paidAt,
                    'notes' => $notes,
                    'settlement' => $settlement,
                ]);
            });

            $this->refresh();

            return $payments;
        });
    }

    public function recalculateStatus(): void
    {
        $paid = $this->amountPaid();

        $status = match (true) {
            bccomp($paid, '0', 2) <= 0 => InvoiceStatus::Impayee,
            bccomp($paid, (string) $this->amount, 2) >= 0 => InvoiceStatus::Payee,
            default => InvoiceStatus::PartiellementPayee,
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }

        // A booking holds its room only once fully paid.
        $this->reservation?->refreshGuarantee();
    }

    /**
     * Next number from a per-year counter row, locked for the duration of the transaction.
     * Never derived from counting invoices: deletions (e.g. cascading from a deleted guest)
     * would make that reuse an existing number, and concurrent requests could read the same count.
     * Issued numbers are never reused. Call inside the same transaction as the invoice insert.
     */
    public static function generateNumber(): string
    {
        return DB::transaction(function () {
            // Business-day year: an invoice issued on Jan 1 at 03:00 belongs to Dec 31.
            $year = (int) BusinessDay::today()->format('Y');

            DB::table('invoice_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

            $last = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->value('last_number');
            $next = $last + 1;

            DB::table('invoice_sequences')->where('year', $year)->update(['last_number' => $next]);

            return sprintf('INV-%d-%04d', $year, $next);
        });
    }
}
