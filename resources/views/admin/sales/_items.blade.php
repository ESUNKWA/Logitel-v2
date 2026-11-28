{{-- Catalogue items of a sale, grouped by category ($groups), with their quantity steppers
     (admin.js [data-sale-form]). Used for today's menu and the folded « Hors menu » block. --}}
        @foreach ($groups as $category => $group)
            @if ($category !== '')
                <h6 class="text-uppercase text-muted small fw-bold mt-3">{{ $category }}</h6>
            @endif
            <div class="sale-items">
                @foreach ($group as $item)
                    <div class="sale-item">
                        <label class="sale-item__name" for="item-{{ $item->id }}">
                            {{ $item->name }}
                            <span class="sale-item__price">{{ $fcfa($item->price) }} F</span>
                        </label>
                        <div class="input-group input-group-sm sale-item__qty">
                            <button type="button" class="btn btn-outline-secondary" data-qty-step="-1" aria-label="Retirer un « {{ $item->name }} »">−</button>
                            <input type="number" class="form-control text-center" id="item-{{ $item->id }}" name="items[{{ $item->id }}]"
                                min="0" max="{{ \App\Models\Sale::MAX_QUANTITY }}" step="1" inputmode="numeric"
                                value="{{ (int) ($oldItems[$item->id] ?? 0) }}" data-sale-qty data-price="{{ (float) $item->price }}">
                            <button type="button" class="btn btn-outline-secondary" data-qty-step="1" aria-label="Ajouter un « {{ $item->name }} »">+</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
