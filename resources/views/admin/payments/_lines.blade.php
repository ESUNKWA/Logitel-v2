{{--
    One "encaissement" made of payment lines (mixed payment = several lines), wired by
    public/js/admin.js ([data-payment-lines]). Posted as payments[i][method|amount|received] (received: cash only).
    $due      : amount still due (FCFA) — null when computed client-side (assignment modal)
    $required : whether at least one line must be filled
    $full     : the due amount must be cashed in full (affectation): first line pre-filled with it
    $cashReceivedRequired : the "Reçu du client" field is mandatory on cash lines (at the desk)
--}}
@php
    $oldLines = old('payments');
    $lines = is_array($oldLines) && $oldLines !== [] ? array_values($oldLines) : [['method' => 'especes', 'amount' => '', 'received' => '']];
@endphp
<div
    class="payment-lines"
    data-payment-lines
    data-due="{{ $due ?? '' }}"
    data-required="{{ $required ? '1' : '0' }}"
    data-full="{{ ($full ?? false) ? '1' : '0' }}"
    data-received-required="{{ ($cashReceivedRequired ?? false) ? '1' : '0' }}"
>
    <div data-lines>
        @foreach ($lines as $i => $line)
            @include('admin.payments._line', ['index' => $i, 'line' => $line, 'required' => $required])
        @endforeach
    </div>

    <template data-line-template>
        @include('admin.payments._line', ['index' => '__INDEX__', 'line' => ['method' => '', 'amount' => '', 'received' => ''], 'required' => true])
    </template>

    <div class="payment-lines__actions">
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-line>
            <i class="far fa-plus"></i> Ajouter un mode (paiement mixte)
        </button>
        <button type="button" class="btn btn-sm btn-link" data-fill-due>Tout encaisser</button>
    </div>

    <div class="payment-lines__summary" data-payment-summary aria-live="polite"></div>
</div>
