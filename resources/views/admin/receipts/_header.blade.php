{{-- Hotel identity at the top of every ticket (Configuration → Hôtel). --}}
            <div class="center">
                <div class="name">{{ $hotelInfo->name() }}</div>
                @if ($hotelInfo->fullAddress())<div class="muted">{{ $hotelInfo->fullAddress() }}</div>@endif
                @if ($hotelInfo->phones())<div class="contact">Tél. {{ implode(' / ', array_map([\App\Support\PhoneFormat::class, 'display'], $hotelInfo->phones())) }}</div>@endif
                @if ($hotelInfo->websiteLabel())<div class="contact contact--web">{{ $hotelInfo->websiteLabel() }}</div>@endif
                @if ($hotelInfo->legalLine())<div class="muted">{{ $hotelInfo->legalLine() }}</div>@endif
            </div>
