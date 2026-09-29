@props([
    'chartId',
    'title',
    'subtitle' => 'Clic en un segmento para filtrar',
])

<div class="dash-panel dash-panel--chart">
    <div class="dash-panel__head">
        <div>
            <h2 class="dash-panel__title">{{ $title }}</h2>
            <p class="dash-panel__subtitle">{{ $subtitle }}</p>
        </div>
    </div>
    <div class="dash-panel__body">
        <div class="dash-chart-wrap dash-chart-wrap--sidebar">
            <canvas id="{{ $chartId }}Chart" role="img" aria-label="{{ $title }}"></canvas>
            <div class="dash-chart-center" id="{{ $chartId }}Center" aria-live="polite">
                <span class="dash-chart-center__value" data-center-value>—</span>
                <span class="dash-chart-center__label" data-center-label>Total</span>
            </div>
        </div>
        <div class="dash-chart-legend" id="{{ $chartId }}Legend"></div>
    </div>
</div>
