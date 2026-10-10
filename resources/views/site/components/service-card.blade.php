@props(['service'])
{{-- A service managed in the application (« Site web → Services »): photo if any, else its icon. --}}
<div class="site-service-card h-100">
    @if ($service->photoUrl())
        <div class="site-service-card__photo"><img src="{{ $service->photoUrl() }}" alt="{{ $service->title }}"></div>
    @else
        <div class="site-service-card__icon"><i class="fas {{ $service->iconClass() }}"></i></div>
    @endif
    <div class="site-service-card__body">
        @if ($service->category)
            <span class="site-service-card__category">{{ $service->category }}</span>
        @endif
        <h4>{{ $service->title }}</h4>
        @if ($service->description)
            <p>{{ \Illuminate\Support\Str::limit($service->description, 160) }}</p>
        @endif
    </div>
</div>
