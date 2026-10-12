<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_invoice_does_not_cause_a_duplicate_number(): void
    {
        $first = Reservation::factory()->create();
        Reservation::factory()->count(2)->create();

        // Deleting a stay cascades to its invoice (guests with stays can no longer be deleted).
        $first->delete();

        $next = Reservation::factory()->create();

        $this->assertSame(sprintf('FAC-%s-0004', now()->format('Y')), $next->invoice->number);
    }

    public function test_numbers_are_sequential_within_a_year(): void
    {
        $numbers = Reservation::factory()->count(3)->create()->map(fn (Reservation $r) => $r->invoice->number)->all();

        $year = now()->format('Y');
        $this->assertSame(["FAC-{$year}-0001", "FAC-{$year}-0002", "FAC-{$year}-0003"], $numbers);
    }

    public function test_numbering_restarts_each_year(): void
    {
        $this->travelTo('2026-12-31 23:00:00');
        $this->assertSame('FAC-2026-0001', Reservation::factory()->create()->invoice->number);
        $this->assertSame('FAC-2026-0002', Reservation::factory()->create()->invoice->number);

        $this->travelTo('2027-01-01 08:00:00');
        $this->assertSame('FAC-2027-0001', Reservation::factory()->create()->invoice->number);
    }

    public function test_a_deleted_last_invoice_number_is_never_reissued(): void
    {
        Reservation::factory()->create();
        $last = Reservation::factory()->create();
        $lastNumber = $last->invoice->number;

        $last->delete();

        $this->assertNotSame($lastNumber, Reservation::factory()->create()->invoice->number);
    }

    public function test_numbering_continues_after_invoices_issued_with_the_former_inv_prefix(): void
    {
        $this->travelTo('2026-06-15 10:00:00');
        $old = Reservation::factory()->create()->invoice;
        $old->forceFill(['number' => 'INV-2026-0001'])->save();

        $this->assertSame('FAC-2026-0002', Reservation::factory()->create()->invoice->number);
        $this->assertSame('INV-2026-0001', $old->fresh()->number);
    }
}
