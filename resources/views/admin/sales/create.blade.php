@extends('admin.layouts.admin')

@section('title', 'Nouvelle vente — '.$department->getLabel())

@section('content')
    @php($fcfa = fn ($v) => number_format((float) $v, 0, ',', ' '))
    @php($slug = $department->slug())
    @php($oldItems = (array) old('items', []))
    @php($chosenStay = old('reservation_id', $preselectedStay))

    <div class="admin-page-header">
        <div>
            <h1><i class="far {{ $department->icon() }} text-primary me-2"></i>Nouvelle vente — {{ $department->getLabel() }}</h1>
            <div class="dash-subtitle">Choisissez les prestations et leurs quantités. Les prix sont ceux du catalogue.</div>
        </div>
        <a href="{{ route('admin.sales.index', $slug) }}" class="btn btn-outline-secondary">Retour aux ventes</a>
    </div>

    @if ($items->isEmpty())
        <div class="alert alert-info">
            Aucune prestation n'est proposée pour ce service.
            @can('catalog.manage')
                <a href="{{ route('admin.catalog.index', ['service' => $slug]) }}">Ajoutez-en dans le catalogue</a>.
            @else
                Demandez au gérant de compléter le catalogue.
            @endcan
        </div>
    @else
        <form method="POST" action="{{ route('admin.sales.store', $slug) }}" data-sale-form>
            @csrf
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="admin-card">
                        <h5>Prestations</h5>
                        @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                        @foreach ($items as $category => $group)
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
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="admin-card">
                        <h5>Vente</h5>
                        <div class="mb-3">
                            <label class="form-label" for="label">Libellé <span class="text-muted fw-normal">(facultatif : table, nom du client…)</span></label>
                            <input type="text" class="form-control @error('label') is-invalid @enderror" id="label" name="label" maxlength="120" value="{{ old('label') }}">
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="reservation_id">Reporter sur la chambre <span class="text-muted fw-normal">(client hébergé : réglé avec son séjour)</span></label>
                            <select class="form-select @error('reservation_id') is-invalid @enderror" id="reservation_id" name="reservation_id" data-sale-room>
                                <option value="">Non — encaisser maintenant ou laisser dû</option>
                                @foreach ($stays as $stay)
                                    <option value="{{ $stay->id }}" @selected((string) $chosenStay === (string) $stay->id)>N°{{ $stay->room?->number }} — {{ $stay->guest?->fullName() }}</option>
                                @endforeach
                            </select>
                            @error('reservation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-5">
                                <label class="form-label" for="discount_amount">Remise</label>
                                <div class="input-group">
                                    <input type="text" inputmode="numeric" data-money class="form-control @error('discount_amount') is-invalid @enderror" id="discount_amount" name="discount_amount" value="{{ old('discount_amount') }}" placeholder="0" data-sale-discount>
                                    <span class="input-group-text">F</span>
                                </div>
                            </div>
                            <div class="col-7">
                                <label class="form-label" for="discount_reason">Motif</label>
                                <input type="text" class="form-control" id="discount_reason" name="discount_reason" maxlength="255" value="{{ old('discount_reason') }}">
                            </div>
                            @error('discount_amount')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="assign-pay">
                            <div class="assign-pay__total" data-sale-total>Total : 0 F</div>
                            @can('payments.create')
                                <div data-sale-payment>
                                    <div class="assign-pay__label">Encaissement <span class="text-muted fw-normal">— facultatif : tout, une partie, ou rien (le reste reste dû)</span></div>
                                    @error('payments')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                                    @include('admin.payments._lines', ['due' => null, 'required' => false, 'prefill' => true, 'cashReceivedRequired' => true,
                                        'sharedReceived' => true, 'idPrefix' => 'sale'])
                                </div>
                                <p class="small text-muted mb-0" data-sale-room-note hidden><i class="far fa-bed"></i> Reportée sur la chambre : rien à encaisser maintenant, le client réglera avec son séjour.</p>
                            @endcan
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 mb-0">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="far fa-check"></i> Enregistrer la vente</button>
                    </div>
                </div>
            </div>
        </form>
    @endif
@endsection

@section('inline-errors', '1')
