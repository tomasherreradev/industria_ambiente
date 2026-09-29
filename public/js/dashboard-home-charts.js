/**
 * Gráficos de segmentos en /dashboard/analisis y /dashboard/muestreo.
 * Config: window.dashboardSegmentCharts[chartId] = { labels, data, colors, segmentLinks }
 * Canvas: #{chartId}Chart — p. ej. subAnalisisChart
 */
(function () {
    'use strict';

    const EMPTY_COLOR = { base: '#e2e5ef', hover: '#d5d9e4' };

    function pct(value, total) {
        if (!total) {
            return 0;
        }
        return Math.round((value / total) * 100);
    }

    function formatNum(n) {
        return new Intl.NumberFormat('es-AR').format(n);
    }

    function setCenter(centerEl, valueText, labelText) {
        if (!centerEl) {
            return;
        }
        const valEl = centerEl.querySelector('[data-center-value]');
        const labEl = centerEl.querySelector('[data-center-label]');
        if (valEl) {
            valEl.textContent = valueText;
        }
        if (labEl) {
            labEl.textContent = labelText;
        }
    }

    function highlightLegend(legendEl, activeIndex) {
        if (!legendEl) {
            return;
        }
        legendEl.querySelectorAll('[data-segment-index]').forEach(function (btn) {
            const i = parseInt(btn.getAttribute('data-segment-index'), 10);
            btn.classList.toggle('is-active', i === activeIndex);
        });
    }

    function initSegmentChart(chartKey, cfg) {
        const canvas = document.getElementById(chartKey + 'Chart');
        if (!canvas || typeof Chart === 'undefined') {
            return;
        }

        const centerEl = document.getElementById(chartKey + 'Center');
        const legendEl = document.getElementById(chartKey + 'Legend');

        const labels = cfg.labels || [];
        const values = (cfg.data || []).map(function (v) {
            return Number(v) || 0;
        });
        const colorPairs = cfg.colors || [];
        const links = cfg.segmentLinks || [];
        const total = values.reduce(function (a, b) {
            return a + b;
        }, 0);
        const isEmpty = total === 0;

        const bases = colorPairs.map(function (c) {
            return c.base || '#4e73df';
        });
        const hovers = colorPairs.map(function (c) {
            return c.hover || c.base || '#6789e3';
        });

        while (bases.length < labels.length) {
            bases.push('#4e73df');
        }
        while (hovers.length < labels.length) {
            hovers.push('#6789e3');
        }

        setCenter(centerEl, isEmpty ? '0' : formatNum(total), 'Total');

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: isEmpty ? ['Sin datos'] : labels,
                datasets: [{
                    data: isEmpty ? [1] : values,
                    backgroundColor: isEmpty ? [EMPTY_COLOR.base] : bases,
                    hoverBackgroundColor: isEmpty ? [EMPTY_COLOR.hover] : hovers,
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
                cutout: '68%',
                layout: { padding: 4 },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 850,
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
                        callbacks: {
                            label: function (ctx) {
                                const v = values[ctx.dataIndex] ?? 0;
                                return ' ' + formatNum(v) + ' (' + pct(v, total) + '%)';
                            },
                        },
                    },
                },
                onHover: function (event, elements) {
                    const target = event.native ? event.native.target : canvas;
                    target.style.cursor = !isEmpty && elements.length ? 'pointer' : 'default';
                    if (!isEmpty && elements.length) {
                        const i = elements[0].index;
                        setCenter(centerEl, formatNum(values[i]), labels[i]);
                        highlightLegend(legendEl, i);
                    } else if (!isEmpty) {
                        setCenter(centerEl, formatNum(total), 'Total');
                        highlightLegend(legendEl, -1);
                    }
                },
                onClick: function (_event, elements) {
                    if (isEmpty || !elements.length) {
                        return;
                    }
                    const url = links[elements[0].index];
                    if (url) {
                        window.location.href = url;
                    }
                },
            },
        });

        if (!legendEl || isEmpty) {
            return;
        }

        legendEl.innerHTML = '';
        labels.forEach(function (label, i) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dash-chart-legend__item';
            btn.setAttribute('data-segment-index', String(i));
            btn.innerHTML =
                '<span class="dash-chart-legend__dot" style="background:' + bases[i] + '"></span>' +
                '<span class="dash-chart-legend__label">' + label + '</span>' +
                '<span class="dash-chart-legend__value">' + formatNum(values[i]) + '</span>' +
                '<span class="dash-chart-legend__pct">' + pct(values[i], total) + '%</span>';

            btn.addEventListener('mouseenter', function () {
                chart.setActiveElements([{ datasetIndex: 0, index: i }]);
                if (chart.tooltip) {
                    chart.tooltip.setActiveElements([{ datasetIndex: 0, index: i }], { x: 0, y: 0 });
                }
                chart.update('none');
                setCenter(centerEl, formatNum(values[i]), label);
                highlightLegend(legendEl, i);
            });

            btn.addEventListener('mouseleave', function () {
                chart.setActiveElements([]);
                if (chart.tooltip) {
                    chart.tooltip.setActiveElements([]);
                }
                chart.update('none');
                setCenter(centerEl, formatNum(total), 'Total');
                highlightLegend(legendEl, -1);
            });

            btn.addEventListener('click', function () {
                const url = links[i];
                if (url) {
                    window.location.href = url;
                }
            });

            legendEl.appendChild(btn);
        });
    }

    function initDashboardSegmentCharts() {
        const configs = window.dashboardSegmentCharts;
        if (!configs) {
            return;
        }
        Object.keys(configs).forEach(function (key) {
            initSegmentChart(key, configs[key]);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardSegmentCharts);
    } else {
        initDashboardSegmentCharts();
    }
})();
