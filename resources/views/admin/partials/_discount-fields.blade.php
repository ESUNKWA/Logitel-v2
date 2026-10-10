{{--
    Optional discount ("remise"): an amount in FCFA or a percentage of the subtotal, with an
    optional reason. Read by public/js/admin.js ([data-discount]) for the live total; the server
    recomputes it (Reservation::discountFor()).
    $file : the Reservation being edited (current discount pre-filled), or null.
--}}
@php
    $file ??= null;
    $currentValue = $file && $file->hasDiscount()
        ? ($file->discount_type === \App\Models\Reservation::DISCOUNT_PERCENT ? (float) $file->discount_value : number_format((float) $file->discount_value, 0, ',', ' '))
        : '';
@endphp
<div class="discount-fields" data-discount>
    <label class="form-label" for="{{ $idPrefix ?? 'd' }}-discount-value">Remise <span class="text-muted fw-normal">(facultatif)</span></label>
    <div class="row g-2">
        <div class="col-7 col-sm-5">
            <div class="input-group">
                <input type="text" inputmode="numeric" autocomplete="off" data-money data-discount-value
                    class="form-control @error('discount_value') is-invalid @enderror"
                    id="{{ $idPrefix ?? 'd' }}-discount-value" name="discount_value" placeholder="0"
                    value="{{ old('discount_value', $currentValue) }}">
                <select class="form-select discount-fields__type" name="discount_type" data-discount-type aria-label="Type de remise">
                    <option value="montant" @selected(old('discount_type', $file?->discount_type ?? 'montant') !== 'pourcentage')>FCFA</option>
                    <option value="pourcentage" @selected(old('discount_type', $file?->discount_type) === 'pourcentage')>%</option>
                </select>
            </div>
        </div>
        <div class="col-5 col-sm-7">
            <input type="text" class="form-control @error('discount_reason') is-invalid @enderror" name="discount_reason" maxlength="255"
                placeholder="Motif (facultatif)" aria-label="Motif de la remise" value="{{ old('discount_reason', $file?->discount_reason) }}" data-discount-reason>
        </div>
    </div>
    @error('discount_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
