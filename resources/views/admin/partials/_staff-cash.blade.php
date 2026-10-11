{{-- Manager (dashboard.revenue): takings of the business day per staff member who recorded them
     (FinanceStats::cashByUser()). --}}
@php
    $fcfa = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp
<section class="staff-cash admin-card mb-3" aria-label="Recettes du jour par réceptionniste">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h2 class="h6 mb-0"><i class="fas fa-users text-primary me-1"></i> Recettes du jour par réceptionniste</h2>
        <a href="{{ route('admin.revenue.index') }}" class="btn btn-sm btn-outline-primary">Voir les recettes</a>
    </div>
    @if ($staffCash)
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Encaissé par</th>
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <th class="text-end">{{ $method->getLabel() }}</th>
                        @endforeach
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($staffCash as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.revenue.index', ['par' => $row['user_id'] ?? 'aucun']) }}">{{ $row['name'] }}</a>
                                <span class="text-muted small">· {{ $row['count'] }} encaiss.</span>
                            </td>
                            @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                <td class="text-end">{{ $fcfa($row['byMethod'][$method->value]) }}</td>
                            @endforeach
                            <td class="text-end fw-bold">{{ $fcfa($row['total']) }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-muted mb-0">Aucun encaissement depuis le début de la journée.</p>
    @endif
</section>
