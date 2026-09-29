@php
    $muestrasChartValues = [(int) $muestrasPendientes, (int) $muestrasEnProceso, (int) $muestrasFinalizadas];
    $analisisChartValues = [(int) $analisisPendientes, (int) $analisisEnProceso, (int) $analisisFinalizados];
    $muestrasChartTotal = array_sum($muestrasChartValues);
    $analisisChartTotal = array_sum($analisisChartValues);

    $muestrasChartLinks = [
        route('dashboard.muestreo', ['estado' => 'coordinado muestreo']),
        route('dashboard.muestreo', ['estado' => 'en revision muestreo']),
        route('dashboard.muestreo', ['estado' => 'muestreado']),
    ];
    $analisisChartLinks = [
        route('dashboard.analisis', ['estado' => 'coordinado analisis']),
        route('dashboard.analisis', ['estado' => 'en revision analisis']),
        route('dashboard.analisis', ['estado' => 'analizado']),
    ];
@endphp

<div class="dash-charts-grid">
    <article class="dash-chart-card" data-dash-chart-card="muestreo">
        <div class="dash-chart-card__head">
            <div>
                <h3 class="dash-chart-card__title">Muestreo</h3>
                <p class="dash-chart-card__hint">Clic en un segmento para abrir el dashboard filtrado</p>
            </div>
            <a href="{{ route('dashboard.muestreo') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Ver todo</a>
        </div>
        <div class="dash-chart-wrap">
            <canvas id="dashChartMuestreo" role="img" aria-label="Gráfico de estados de muestreo"></canvas>
        </div>
        <p class="dash-chart-meta" id="dashChartMuestreoMeta" aria-live="polite">
            @if($muestrasChartTotal > 0)
                Total en pipeline: <strong>{{ number_format($muestrasChartTotal, 0, ',', '.') }}</strong> — pasá el cursor sobre el gráfico
            @else
                Sin muestras en pipeline todavía
            @endif
        </p>
        <ul class="dash-chart-stats" id="dashChartMuestreoStats">
            <li data-index="0" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--warn"></span>
                <span class="dash-chart-stats__label">Pendientes</span>
                <span class="dash-chart-stats__value">{{ $muestrasPendientes }}</span>
            </li>
            <li data-index="1" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--info"></span>
                <span class="dash-chart-stats__label">En proceso</span>
                <span class="dash-chart-stats__value">{{ $muestrasEnProceso }}</span>
            </li>
            <li data-index="2" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--ok"></span>
                <span class="dash-chart-stats__label">Finalizadas</span>
                <span class="dash-chart-stats__value">{{ $muestrasFinalizadas }}</span>
            </li>
        </ul>
    </article>

    <article class="dash-chart-card" data-dash-chart-card="analisis">
        <div class="dash-chart-card__head">
            <div>
                <h3 class="dash-chart-card__title">Análisis</h3>
                <p class="dash-chart-card__hint">Clic en un segmento para abrir el dashboard filtrado</p>
            </div>
            <a href="{{ route('dashboard.analisis') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Ver todo</a>
        </div>
        <div class="dash-chart-wrap">
            <canvas id="dashChartAnalisis" role="img" aria-label="Gráfico de estados de análisis"></canvas>
        </div>
        <p class="dash-chart-meta" id="dashChartAnalisisMeta" aria-live="polite">
            @if($analisisChartTotal > 0)
                Total en pipeline: <strong>{{ number_format($analisisChartTotal, 0, ',', '.') }}</strong> — pasá el cursor sobre el gráfico
            @else
                Sin análisis en pipeline todavía
            @endif
        </p>
        <ul class="dash-chart-stats" id="dashChartAnalisisStats">
            <li data-index="0" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--warn"></span>
                <span class="dash-chart-stats__label">Pendientes</span>
                <span class="dash-chart-stats__value">{{ $analisisPendientes }}</span>
            </li>
            <li data-index="1" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--info"></span>
                <span class="dash-chart-stats__label">En proceso</span>
                <span class="dash-chart-stats__value">{{ $analisisEnProceso }}</span>
            </li>
            <li data-index="2" tabindex="0" role="button">
                <span class="dash-chart-stats__dot dash-chart-stats__dot--ok"></span>
                <span class="dash-chart-stats__label">Finalizados</span>
                <span class="dash-chart-stats__value">{{ $analisisFinalizados }}</span>
            </li>
        </ul>
    </article>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const palette = {
        warn: { base: '#f0ad4e', hover: '#e89b2e', soft: 'rgba(240, 173, 78, 0.18)' },
        info: { base: '#36b9cc', hover: '#2aa3b5', soft: 'rgba(54, 185, 204, 0.18)' },
        ok: { base: '#1cc88a', hover: '#17a673', soft: 'rgba(28, 200, 138, 0.18)' },
        empty: { base: '#e2e5ef', hover: '#d5d9e4', soft: 'rgba(226, 229, 239, 0.5)' },
    };

    const centerTotalPlugin = {
        id: 'dashCenterTotal',
        afterDraw(chart, _args, opts) {
            const total = opts.total ?? 0;
            const subtitle = opts.subtitle ?? 'total';
            const { ctx, chartArea } = chart;
            if (!chartArea) return;

            const cx = (chartArea.left + chartArea.right) / 2;
            const cy = (chartArea.top + chartArea.bottom) / 2;

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#2f3245';
            ctx.font = '700 1.65rem system-ui, sans-serif';
            ctx.fillText(new Intl.NumberFormat('es-AR').format(total), cx, cy - 6);
            ctx.fillStyle = '#7b7f95';
            ctx.font = '600 0.72rem system-ui, sans-serif';
            ctx.fillText(subtitle, cx, cy + 16);
            ctx.restore();
        },
    };

    Chart.register(centerTotalPlugin);

    function pct(value, total) {
        if (!total) return 0;
        return Math.round((value / total) * 100);
    }

    function createInteractiveDoughnut(config) {
        const {
            canvasId,
            metaId,
            statsId,
            values,
            labels,
            links,
            centerSubtitle,
        } = config;

        const canvas = document.getElementById(canvasId);
        const metaEl = document.getElementById(metaId);
        const statsEl = document.getElementById(statsId);
        if (!canvas) return;

        const total = values.reduce((a, b) => a + b, 0);
        const colors = [palette.warn.base, palette.info.base, palette.ok.base];
        const hovers = [palette.warn.hover, palette.info.hover, palette.ok.hover];
        const isEmpty = total === 0;
        const chartLabels = isEmpty ? ['Sin datos'] : labels;
        const chartValues = isEmpty ? [1] : values;
        const chartColors = isEmpty ? [palette.empty.base] : colors;
        const chartHovers = isEmpty ? [palette.empty.hover] : hovers;

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartValues,
                    backgroundColor: chartColors,
                    hoverBackgroundColor: chartHovers,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderColor: '#ffffff',
                    hoverOffset: isEmpty ? 0 : 14,
                    spacing: isEmpty ? 0 : 3,
                    borderRadius: isEmpty ? 0 : 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                layout: { padding: 4 },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 900,
                    easing: 'easeOutQuart',
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: !isEmpty,
                        backgroundColor: 'rgba(47, 50, 69, 0.92)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label(ctx) {
                                const v = values[ctx.dataIndex] ?? 0;
                                return ` ${v.toLocaleString('es-AR')} (${pct(v, total)}%)`;
                            },
                        },
                    },
                    dashCenterTotal: {
                        total: total,
                        subtitle: centerSubtitle,
                    },
                },
                onHover(event, elements) {
                    const target = event.native ? event.native.target : canvas;
                    target.style.cursor = !isEmpty && elements.length ? 'pointer' : 'default';
                    if (!isEmpty && elements.length && metaEl) {
                        const i = elements[0].index;
                        const v = values[i];
                        metaEl.innerHTML = `<strong>${labels[i]}</strong>: ${v.toLocaleString('es-AR')} (${pct(v, total)}%) — clic para ver detalle`;
                        highlightStat(statsEl, i);
                    } else if (metaEl && !isEmpty) {
                        metaEl.innerHTML = `Total en pipeline: <strong>${total.toLocaleString('es-AR')}</strong> — pasá el cursor sobre el gráfico`;
                        highlightStat(statsEl, -1);
                    }
                },
                onClick(_event, elements) {
                    if (isEmpty || !elements.length) return;
                    const link = links[elements[0].index];
                    if (link) window.location.href = link;
                },
            },
        });

        if (statsEl) {
            statsEl.querySelectorAll('li[data-index]').forEach(function (li) {
                const index = parseInt(li.getAttribute('data-index'), 10);

                li.addEventListener('mouseenter', function () {
                    if (isEmpty) return;
                    highlightStat(statsEl, index);
                    chart.setActiveElements([{ datasetIndex: 0, index }]);
                    if (chart.tooltip) {
                        chart.tooltip.setActiveElements([{ datasetIndex: 0, index }], { x: 0, y: 0 });
                    }
                    if (metaEl) {
                        const v = values[index];
                        metaEl.innerHTML = `<strong>${labels[index]}</strong>: ${v.toLocaleString('es-AR')} (${pct(v, total)}%) — clic para ir al listado`;
                    }
                    chart.update('none');
                });

                li.addEventListener('mouseleave', function () {
                    chart.setActiveElements([]);
                    chart.tooltip.setActiveElements([]);
                    highlightStat(statsEl, -1);
                    if (metaEl && total > 0) {
                        metaEl.innerHTML = `Total en pipeline: <strong>${total.toLocaleString('es-AR')}</strong> — pasá el cursor sobre el gráfico`;
                    }
                    chart.update('none');
                });

                li.addEventListener('click', function () {
                    if (isEmpty) return;
                    const link = links[index];
                    if (link) window.location.href = link;
                });

                li.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        li.click();
                    }
                });
            });
        }

        return chart;
    }

    function highlightStat(statsEl, activeIndex) {
        if (!statsEl) return;
        statsEl.querySelectorAll('li[data-index]').forEach(function (li) {
            const i = parseInt(li.getAttribute('data-index'), 10);
            li.classList.toggle('is-active', i === activeIndex);
        });
    }

    createInteractiveDoughnut({
        canvasId: 'dashChartMuestreo',
        metaId: 'dashChartMuestreoMeta',
        statsId: 'dashChartMuestreoStats',
        values: @json($muestrasChartValues),
        labels: @json(['Pendientes', 'En proceso', 'Finalizadas']),
        links: @json($muestrasChartLinks),
        centerSubtitle: 'muestreo',
    });

    createInteractiveDoughnut({
        canvasId: 'dashChartAnalisis',
        metaId: 'dashChartAnalisisMeta',
        statsId: 'dashChartAnalisisStats',
        values: @json($analisisChartValues),
        labels: @json(['Pendientes', 'En proceso', 'Finalizados']),
        links: @json($analisisChartLinks),
        centerSubtitle: 'análisis',
    });
});
</script>
@endpush
