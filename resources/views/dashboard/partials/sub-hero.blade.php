@props([
    'title',
    'subtitle' => null,
    'backUrl' => null,
    'backLabel' => 'Panel principal',
])

<div class="mb-3">
    @if($backUrl)
        <a href="{{ $backUrl }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm mb-2">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
            {{ $backLabel }}
        </a>
    @endif
    <header class="dash-home__hero mb-0">
        <div>
            <h1 class="dash-home__hero-title">{{ $title }}</h1>
            @if($subtitle)
                <p class="dash-home__hero-sub">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="dash-home__hero-meta">
            <span class="dash-home__date">{{ fechaActualLargaEs() }}</span>
        </div>
    </header>
</div>
