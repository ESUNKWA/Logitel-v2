{{--
    One "encaissement" made of payment lines (mixed payment = several lines), wired by
    public/js/admin.js ([data-payment-lines]). Posted as payments[i][method|amount|received] (received: cash only).
    $due      : amount still due (FCFA) — null when computed client-side (assignment modal)
    $required : whether at least one line must be filled
    $full     : the due amount must be cashed in full (affectation): first line pre-filled with it
    $cashReceivedRequired : the "Reçu du client" field is mandatory on cash lines (at the desk)
    $sharedReceived : one « Montant reçu » field under the actions instead of one per line (there is
                      at most one cash line: methods are distinct); admin.js copies it into the
                      cash line's hidden payments[i][received]
    $afterActions   : HTML shown right under « Ajouter un mode » (the discount fields, at the desk)
    $idPrefix       : prefix for the shared field's id
--}}
@php
    $sharedReceived ??= false;
    $oldLines = old('payments');
    $lines = is_array($oldLines) && $oldLines !== [] ? array_values($oldLines) : [['method' => 'especes', 'amount' => '', 'received' => '']];
@endphp
<div
    class="payment-lines {{ $sharedReceived ? 'has-shared-received' : '' }}"
    data-payment-lines
    data-due="{{ $due ?? '' }}"
    data-required="{{ $required ? '1' : '0' }}"
    data-full="{{ ($full ?? false) ? '1' : '0' }}"
    data-received-required="{{ ($cashReceivedRequired ?? false) ? '1' : '0' }}"
>
    <div data-lines>
        @foreach ($lines as $i => $line)
            @include('admin.payments._line', ['index' => $i, 'line' => $line, 'required' => $required, 'sharedReceived' => $sharedReceived])
        @endforeach
    </div>

    <template data-line-template>
        @include('admin.payments._line', ['index' => '__INDEX__', 'line' => ['method' => '', 'amount' => '', 'received' => ''], 'required' => true, 'sharedReceived' => $sharedReceived])
    </template>

    <div class="payment-lines__actions">
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-line>
            <i class="far fa-plus"></i> Ajouter un mode (paiement mixte)
        </button>
        <button type="button" class="btn btn-sm btn-link" data-fill-due>Tout encaisser</button>
    </div>

    {!! $afterActions ?? '' !!}

    @if ($sharedReceived)
        {{-- Cash received from the guest (shown while there is a cash line); the change is computed. --}}
        <div class="payment-received" data-received-shared-box hidden>
            <label class="form-label" for="{{ $idPrefix ?? 'p' }}-received">Montant reçu du client <span class="text-muted fw-normal">(espèces)</span></label>
            <div class="input-group">
                <input type="text" inputmode="numeric" autocomplete="off" data-money class="form-control" id="{{ $idPrefix ?? 'p' }}-received" placeholder="Ex. 10 000" data-received-shared>
                <span class="input-group-text">FCFA</span>
            </div>
            <div class="payment-line__change" data-change-shared aria-live="polite"></div>
        </div>
    @endif

    <div class="payment-lines__summary" data-payment-summary aria-live="polite"></div>
</div>
