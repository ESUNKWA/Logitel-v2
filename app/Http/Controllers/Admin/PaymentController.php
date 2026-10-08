<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $payments = $invoice->settle(
            $request->paymentLines(),
            Carbon::parse($request->validated('paid_at')),
            $request->validated('notes'),
        );

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', ($payments->count() > 1 ? 'Paiement mixte enregistré : ' : 'Paiement enregistré : ')
                .number_format((float) $payments->sum('amount'), 0, ',', ' ').' FCFA.'
                .$this->changeNotice((float) $payments->sum(fn ($p) => (float) $p->change_given)));
    }

    private function changeNotice(float $change): string
    {
        return $change > 0 ? ' Monnaie à rendre : '.number_format($change, 0, ',', ' ').' FCFA.' : '';
    }

    public function destroy(Invoice $invoice, Payment $payment): RedirectResponse
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        // A mixed payment is one encaissement: cancelling it removes all its lines together.
        DB::transaction(function () use ($invoice, $payment) {
            $lines = $payment->settlement
                ? $invoice->payments()->where('settlement', $payment->settlement)->get()
                : collect([$payment]);

            $lines->each->delete();
        });

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('status', 'Encaissement supprimé.');
    }
}
