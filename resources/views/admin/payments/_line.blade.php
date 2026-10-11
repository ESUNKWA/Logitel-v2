<div class="payment-line" data-line>
    <select class="form-select" name="payments[{{ $index }}][method]" data-field="method" aria-label="Mode de paiement">
        @foreach (\App\Enums\PaymentMethod::cases() as $method)
            <option value="{{ $method->value }}" @selected(($line['method'] ?? '') === $method->value)>{{ $method->getLabel() }}</option>
        @endforeach
    </select>
    <div class="input-group">
        <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" name="payments[{{ $index }}][amount]" value="{{ $line['amount'] ?? '' }}" placeholder="Montant" data-field="amount" aria-label="Montant encaissé" @required($required && $index === 0)>
        <span class="input-group-text">FCFA</span>
    </div>
    @if ($sharedReceived ?? false)
        {{-- Filled by admin.js from the shared « Montant reçu » field (cash line only). --}}
        <span data-cash-only hidden></span>
        <input type="hidden" name="payments[{{ $index }}][received]" value="{{ $line['received'] ?? '' }}" data-field="received">
    @else
        {{-- Cash only (shown by admin.js): what the guest hands over; the change is computed. --}}
        <div class="input-group payment-line__received" data-cash-only>
            <span class="input-group-text">Reçu du client</span>
            <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" name="payments[{{ $index }}][received]" value="{{ $line['received'] ?? '' }}" placeholder="= montant" data-field="received" aria-label="Montant reçu du client">
        </div>
    @endif
    <button type="button" class="btn btn-sm btn-outline-danger payment-line__remove" data-remove-line aria-label="Retirer cette ligne">
        <i class="far fa-times"></i>
    </button>
    <div class="payment-line__change" data-change aria-live="polite"></div>
</div>
