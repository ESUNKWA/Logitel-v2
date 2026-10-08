<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['reservation.guest'])
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('issued_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['reservation.guest', 'reservation.room', 'payments' => fn ($query) => $query->with('creator')->orderByDesc('paid_at')]);

        return view('admin.invoices.show', compact('invoice'));
    }
}
