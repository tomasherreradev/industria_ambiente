@php
    $backUrl = $backUrl ?? null;
    $backLabel = $backLabel ?? 'Volver';
@endphp

<header class="ucrud-header">
    <div class="ucrud-header__titles">
        <h1 class="ucrud-title">{{ $title }}</h1>
        @if(!empty($subtitle))
            <p class="ucrud-subtitle">{{ $subtitle }}</p>
        @endif
    </div>

    @if(!empty($actions))
        <div class="ucrud-header__actions">{!! $actions !!}</div>
    @elseif($backUrl)
        <div class="ucrud-header__actions">
            <a href="{{ $backUrl }}" class="ucrud-btn ucrud-btn--ghost">
                <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
                {{ $backLabel }}
            </a>
        </div>
    @endif
</header>
