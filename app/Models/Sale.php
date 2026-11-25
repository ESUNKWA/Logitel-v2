<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\ReservationStatus;
use App\Models\Concerns\Audited;
use App\Models\Concerns\RecordsCreator;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sale of a service other than the accommodation (client request, 2026-11-25): restaurant,
 * beach & pool. Lines come from the service's catalogue — prices are the catalogue's, computed
 * here, never read from the form. A sale has its own invoice (its `department`), paid through
 * the usual Invoice::settle(): at once, partly, left due (« À encaisser »), or charged to an
 * in-progress stay (« report sur la chambre »: settled with the stay, the turnover stays the
 * service's). Never deleted: cancelled with a reason, only while nothing has been cashed.
 * Nothing mass-assignable.
 */
class Sale extends Model
{
    use Audited, RecordsCreator;

    public const STATUS_VALID = 'validee';

    public const STATUS_CANCELLED = 'annulee';

    /** Most units of one item on a sale. */
    public const MAX_QUANTITY = 999;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'sold_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    // ---- Journal d'activité ---------------------------------------------------------------

    public function auditFields(): array
    {
        return ['department' => 'Service', 'label' => 'Libellé', 'total_amount' => 'Total', 'discount_amount' => 'Remise', 'status' => 'Statut', 'cancel_reason' => 'Motif d\'annulation'];
    }

    public function auditKey(): string
    {
        return 'sale';
    }

    public function auditCategory(): string
    {
        return 'vente';
    }

    public function auditName(): string
    {
        return 'vente '.$this->reference().' ('.$this->department?->getLabel().')';
    }

    public function auditReference(): ?string
    {
        return $this->reference();
    }

    public function auditMoneyFields(): array
    {
        return ['total_amount', 'discount_amount'];
    }

    public function auditDisplay(string $field, mixed $value): ?string
    {
        return match ($field) {
            'department' => ($value instanceof Department ? $value : Department::tryFrom((string) $value))?->getLabel(),
            'status' => $value === self::STATUS_CANCELLED ? 'Annulée' : 'Validée',
            default => null,
        };
    }

    public function auditAction(string $event, array $changes, array $dirty): ?array
    {
        if ($event === 'created') {
            return ['sale.created', 'Vente '.$this->department?->getLabel().' : '.$this->reference().' — '.Audit::money($this->total_amount)
                .($this->reservation_id ? ' (reportée sur la chambre)' : '')];
        }
        if ($event === 'updated' && in_array('status', $dirty, true) && $this->status === self::STATUS_CANCELLED) {
            return ['sale.cancelled', 'Vente annulée : '.$this->reference().' — motif : '.$this->cancel_reason];
        }

        return null;
    }

    // ---- Relations ------------------------------------------------------------------------

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** The room booking billed by this sale (meetings & events), if any. */
    public function venueBooking(): HasOne
    {
        return $this->hasOne(VenueBooking::class);
    }

    /** The in-progress stay the sale is charged to (« report sur la chambre »), if any. */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALID);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /** Its page: the room booking for meetings & events, else the counter sale page. */
    public function url(): string
    {
        return $this->department === Department::Evenements && $this->venueBooking
            ? route('admin.events.show', $this->venueBooking)
            : route('admin.sales.show', [$this->department->slug(), $this]);
    }

    /** Its printable document: the quote / confirmation of a room booking, else the ticket. */
    public function receiptUrl(): string
    {
        return $this->department === Department::Evenements && $this->venueBooking
            ? route('admin.events.document', $this->venueBooking)
            : route('admin.sales.receipt', [$this->department->slug(), $this]);
    }

    public function isChargedToRoom(): bool
    {
        return $this->reservation_id !== null;
    }

    /** The sale is known by its invoice number. */
    public function reference(): string
    {
        return $this->invoice?->number ?? 'V-'.$this->id;
    }

    /** Units sold that count as one entry (beach / pool attendance). */
    public function entries(): int
    {
        return (int) $this->lines->where('counts_entry', true)->sum('quantity');
    }

    // ---- Writing --------------------------------------------------------------------------

    /**
     * Records a sale of `$department`: `$quantities` = catalogue item id => quantity (items of
     * that service, active; anything else is refused), optional discount (never above the
     * subtotal) and label, optionally charged to an in-progress stay. `$extraLines` are lines
     * priced by the server itself (a room booking's hire: label, unit_price, quantity) — never
     * from a form. Creates its invoice. Call inside the caller's transaction when cashing it.
     *
     * @param  array<int|string, int>  $quantities
     * @param  list<array{label: string, unit_price: string, quantity: int}>  $extraLines
     */
    public static function record(Department $department, array $quantities, ?string $label = null, ?Reservation $stay = null, string $discount = '0', ?string $discountReason = null, array $extraLines = []): self
    {
        return DB::transaction(function () use ($department, $quantities, $label, $stay, $discount, $discountReason, $extraLines) {
            [$lines, $subtotal] = self::priceLines($department, $quantities, $extraLines);

            if ($stay) {
                $stay = Reservation::query()->lockForUpdate()->find($stay->id);
                if (! $stay || ! $stay->isStay() || $stay->status !== ReservationStatus::Enregistree) {
                    throw ValidationException::withMessages(['reservation_id' => 'Le report n\'est possible que sur la chambre d\'un client actuellement en séjour.']);
                }
            }

            $discount = self::checkedDiscount($discount, $subtotal);

            $sale = new self;
            $sale->forceFill([
                'department' => $department,
                'sold_at' => now(),
                'label' => $label !== null && trim($label) !== '' ? trim($label) : null,
                'reservation_id' => $stay?->id,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'discount_reason' => bccomp($discount, '0', 2) > 0 && $discountReason !== null && trim($discountReason) !== '' ? trim($discountReason) : null,
                'total_amount' => bcsub($subtotal, $discount, 2),
                'status' => self::STATUS_VALID,
            ]);
            // Journal line written once the invoice exists: it names the sale by its number.
            Audit::muted(fn () => $sale->save());
            $sale->writeLines($lines);

            $invoice = new Invoice(['number' => Invoice::generateNumber(), 'issued_at' => now()->toDateString(), 'amount' => $sale->total_amount, 'status' => 'impayee']);
            $invoice->forceFill(['sale_id' => $sale->id, 'department' => $department->value])->save();
            // A sale of 0 F (e.g. a pool entry included in the stay) is settled from the start.
            $invoice->recalculateStatus();

            $sale->setRelation('invoice', $invoice);
            $sale->writeAudit('created');

            return $sale->load('lines');
        });
    }

    /**
     * Re-prices a valid sale (a room booking changed: room, formula, services, discount): new
     * lines, totals and invoice amount — never below what has already been cashed.
     *
     * @param  array<int|string, int>  $quantities
     * @param  list<array{label: string, unit_price: string, quantity: int}>  $extraLines
     */
    public function reprice(array $quantities, array $extraLines, string $discount = '0', ?string $discountReason = null, ?string $label = null): self
    {
        return DB::transaction(function () use ($quantities, $extraLines, $discount, $discountReason, $label) {
            $sale = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['items' => 'Cette vente est annulée : elle ne peut plus être modifiée.']);
            }
            $invoice = Invoice::query()->lockForUpdate()->where('sale_id', $sale->id)->firstOrFail();

            [$lines, $subtotal] = self::priceLines($sale->department, $quantities, $extraLines);
            $discount = self::checkedDiscount($discount, $subtotal);
            $total = bcsub($subtotal, $discount, 2);

            $paid = $invoice->amountPaid();
            if (bccomp($total, $paid, 2) < 0) {
                throw ValidationException::withMessages(['items' => 'Le nouveau montant ('.Audit::money($total).') serait inférieur à ce qui a déjà été encaissé ('.Audit::money($paid).').']);
            }

            $sale->lines()->delete();
            $sale->writeLines($lines);
            $sale->forceFill([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'discount_reason' => bccomp($discount, '0', 2) > 0 && $discountReason !== null && trim($discountReason) !== '' ? trim($discountReason) : null,
                'total_amount' => $total,
                'label' => $label !== null && trim($label) !== '' ? trim($label) : $sale->label,
            ])->save();

            $invoice->forceFill(['amount' => $total])->save();
            $invoice->recalculateStatus();

            $this->setRawAttributes($sale->getAttributes(), true);

            return $this->setRelation('invoice', $invoice)->load('lines');
        });
    }

    /**
     * Lines and subtotal: catalogue items of the service (active), then the server-priced lines.
     *
     * @param  array<int|string, int>  $quantities
     * @param  list<array{label: string, unit_price: string, quantity: int}>  $extraLines
     * @return array{0: list<array<string, mixed>>, 1: string}
     */
    private static function priceLines(Department $department, array $quantities, array $extraLines): array
    {
        $quantities = array_filter(array_map('intval', $quantities), fn (int $q) => $q > 0 && $q <= self::MAX_QUANTITY);
        $items = CatalogItem::query()->ofDepartment($department)->active()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        if ($items->count() !== count($quantities) || ($quantities === [] && $extraLines === [])) {
            throw ValidationException::withMessages(['items' => 'Choisissez au moins une prestation de ce service (les prestations désactivées ne sont plus proposées).']);
        }

        $subtotal = '0';
        $lines = [];
        foreach ($extraLines as $extra) {
            $lineTotal = bcmul((string) $extra['unit_price'], (string) $extra['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
            $lines[] = ['catalog_item_id' => null, 'label' => mb_substr($extra['label'], 0, 120), 'unit_price' => (string) $extra['unit_price'],
                'quantity' => (int) $extra['quantity'], 'line_total' => $lineTotal, 'counts_entry' => false];
        }
        foreach ($quantities as $id => $quantity) {
            $item = $items[$id];
            $lineTotal = bcmul((string) $item->price, (string) $quantity, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
            $lines[] = ['catalog_item_id' => $item->id, 'label' => $item->name, 'unit_price' => (string) $item->price,
                'quantity' => $quantity, 'line_total' => $lineTotal, 'counts_entry' => $department->countsEntries() && $item->counts_entry];
        }

        return [$lines, $subtotal];
    }

    private static function checkedDiscount(string $discount, string $subtotal): string
    {
        $discount = bccomp($discount, '0', 2) > 0 ? bcadd($discount, '0', 2) : '0.00';
        if (bccomp($discount, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount_amount' => 'La remise ne peut pas dépasser le total de la vente ('.Audit::money($subtotal).').']);
        }

        return $discount;
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function writeLines(array $lines): void
    {
        foreach ($lines as $line) {
            $saleLine = new SaleLine;
            $saleLine->forceFill(['sale_id' => $this->id, ...$line])->save();
        }
    }

    /**
     * Cancels the sale (a reason is mandatory). Refused once something has been cashed: the
     * payment must be deleted first (payments.delete), so the till always matches.
     */
    public function cancel(User $by, string $reason): void
    {
        DB::transaction(function () use ($by, $reason) {
            $sale = static::query()->lockForUpdate()->findOrFail($this->id);
            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['cancel_reason' => 'Cette vente est déjà annulée.']);
            }
            if (bccomp((string) ($sale->invoice?->amountPaid() ?? '0'), '0', 2) > 0) {
                throw ValidationException::withMessages(['cancel_reason' => 'Cette vente a déjà été encaissée : supprimez d\'abord l\'encaissement depuis la facture, puis annulez la vente.']);
            }

            $sale->forceFill(['status' => self::STATUS_CANCELLED, 'cancelled_by' => $by->id, 'cancelled_at' => now(), 'cancel_reason' => trim($reason)])->save();
            $this->setRawAttributes($sale->getAttributes(), true);
        });
    }

    /**
     * Valid sales charged to a stay, with their invoices (what the guest still owes for them).
     *
     * @return Collection<int, self>
     */
    public static function chargedTo(Reservation $stay): Collection
    {
        return static::query()->valid()->where('reservation_id', $stay->id)->with(['invoice' => fn ($q) => $q->withSum('payments', 'amount')])->orderBy('sold_at')->get();
    }

    /** What the guest still owes for the sales charged to that stay. */
    public static function dueOnStay(Reservation $stay): float
    {
        return (float) static::chargedTo($stay)->sum(fn (self $sale) => max(0, (float) $sale->invoice?->amount - (float) $sale->invoice?->payments_sum_amount));
    }
}
