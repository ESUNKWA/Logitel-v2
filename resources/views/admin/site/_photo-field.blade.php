{{-- Photo upload with the current one previewed and a "remove" box. $name, $label, $url (current or null), $hint --}}
<div class="site-photo-field">
    <label class="form-label" for="{{ $name }}">{{ $label }}</label>
    @if ($url)
        <div class="d-flex align-items-center gap-3 mb-2">
            <img src="{{ $url }}" alt="" class="site-photo-field__preview">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remove_{{ $name }}" value="1" id="remove_{{ $name }}">
                <label class="form-check-label small" for="remove_{{ $name }}">Retirer</label>
            </div>
        </div>
    @endif
    <input type="file" class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp">
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">{{ $hint ?? 'JPG, PNG ou WebP, 2 Mo max.' }}{{ $url ? '' : ' Aucune photo : rien ne s\'affiche.' }}</div>
</div>
