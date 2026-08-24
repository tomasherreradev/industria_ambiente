@php
    $routeName = $modulo['route'] ?? null;
    $href = $routeName && \Illuminate\Support\Facades\Route::has($routeName)
        ? route($routeName)
        : ($modulo['url'] ?? '#');
    $color = $modulo['color'] ?? '#6c757d';
    $icono = $modulo['icono'] ?? 'squares-2x2';
    $tileIndex = $tileIndex ?? 0;
@endphp
<a href="{{ $href }}"
   class="dashboard-apps-tile"
   style="--tile-index: {{ (int) $tileIndex }};"
   title="{{ $modulo['nombre'] }}">
    <span class="dashboard-apps-tile__icon" style="background-color: {{ $color }};">
        @include('dashboard.partials.modulo-acceso-icon', ['icono' => $icono])
    </span>
    <span class="dashboard-apps-tile__label">{{ $modulo['nombre'] }}</span>
</a>
