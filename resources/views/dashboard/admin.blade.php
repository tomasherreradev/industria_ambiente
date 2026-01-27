@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Panel de Control</h1>
        <div class="text-muted">{{ now()->format('l, d F Y') }}</div>
    </div>

    {{-- Resumen General --}}
    <div class="row mb-4 g-4">
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('cotizaciones.index') }}" class="text-decoration-none">
                <div class="card bg-primary bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Cotizaciones</h5>
                                <p class="card-text display-6 fw-bold">{{ $totalCotizaciones }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle"
                                 style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-document-currency-dollar style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Total registradas</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('muestras.index') }}" class="text-decoration-none">
                <div class="card bg-info bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Muestras Procesadas</h5>
                                <p class="card-text display-6 fw-bold">{{ $muestrasTotales }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle"
                                 style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-beaker style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Total en sistema</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('ordenes.index') }}" class="text-decoration-none">
                <div class="card bg-success bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Análisis Procesados</h5>
                                <p class="card-text display-6 fw-bold">{{ $analisisTotales }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle"
                                 style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-cube-transparent style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Total completados</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('informes.index') }}" class="text-decoration-none">
                <div class="card bg-warning bg-gradient text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Informes</h5>
                                <p class="card-text display-6 fw-bold">{{ $informesTotales }}</p>
                            </div>
                            <div class="bg-dark bg-opacity-25 p-3 rounded-circle"
                                 style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-document-chart-bar style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">En operación actual</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
    

    {{-- Gráficos y Muestras Próximas --}}
    <div class="row mb-4 g-4">
        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-white border-bottom-0 pb-0">
                            <h5 class="mb-0">Estado de Muestras</h5>
                            <p class="text-muted small mb-0">Distribución actual</p>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="muestrasChart"></canvas>
                            </div>
                            <div class="mt-auto pt-3">
                                <div class="d-flex justify-content-between small">
                                    <span><span class="badge bg-warning me-1"></span> Pendientes: {{ $muestrasPendientes }}</span>
                                    <span><span class="badge bg-info me-1"></span> En Proceso: {{ $muestrasEnProceso }}</span>
                                    <span><span class="badge bg-success me-1"></span> Finalizadas: {{ $muestrasFinalizadas }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-white border-bottom-0 pb-0">
                            <h5 class="mb-0">Estado de Análisis</h5>
                            <p class="text-muted small mb-0">Distribución actual</p>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="analisisChart"></canvas>
                            </div>
                            <div class="mt-auto pt-3">
                                <div class="d-flex justify-content-between small">
                                    <span><span class="badge bg-warning me-1"></span> Pendientes: {{ $analisisPendientes }}</span>
                                    <span><span class="badge bg-info me-1"></span> En Proceso: {{ $analisisEnProceso }}</span>
                                    <span><span class="badge bg-success me-1"></span> Finalizados: {{ $analisisFinalizados }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Muestras Próximas</h5>
                            <p class="text-muted small mb-0">Próximos muestreos programados</p>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Cotización</th>
                                            <th>Descripción</th>
                                            <th>Fecha Muestreo</th>
                                            <th>Ubicación</th>
                                            <th class="pe-4">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($muestrasProximas as $muestra)
                                        <tr>
                                            <td class="ps-4 fw-bold">{{ $muestra->cotizacion->coti_num ?? 'N/A' }}</td>
                                            <td class="" style="max-width: 200px;" title="{{ $muestra->cotio_descripcion }}">
                                                <a href="{{ route('muestras.ver', [
                                                    'cotizacion' => $muestra->cotizacion->coti_num,
                                                    'item' => $muestra->cotio_item,
                                                    'instance' => $muestra->instance_number
                                                ]) }}">{{ $muestra->cotio_descripcion ?? 'N/A' }}</a>
                                            </td>
                                            <td>
                                                <span class="d-block">{{ $muestra->fecha_inicio_muestreo->format('d/m/Y') }}</span>
                                                <small class="text-muted">{{ $muestra->fecha_inicio_muestreo->format('H:i') }}</small>
                                            </td>
                                            <td>
                                                @if($muestra->latitud && $muestra->longitud)
                                                <a href="#" class="show-location text-primary" data-lat="{{ $muestra->latitud }}" data-lng="{{ $muestra->longitud }}">
                                                    <i class="fas fa-map-marker-alt me-1"></i> Ver mapa
                                                </a>
                                                @else
                                                <span class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> Sin ubicación</span>
                                                @endif
                                            </td>
                                            <td class="pe-4">
                                                <span class="badge rounded-pill bg-{{ $muestra->cotio_estado == 'coordinado muestreo' ? 'warning text-dark' : ($muestra->cotio_estado == 'en revision muestreo' ? 'info text-dark' : 'success') }}">
                                                    {{ Str::title(str_replace('_', ' ', $muestra->cotio_estado)) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                                <p class="mb-0">No hay muestras próximas programadas</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Cotizaciones Recientes --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cotizaciones Recientes</h5>
                    </div>
                    <p class="text-muted small mb-0">Últimas cotizaciones registradas</p>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($cotizacionesRecientes as $cotizacion)
                        <a href="{{ route('cotizaciones.ver-detalle', $cotizacion->coti_num) }}" class="list-group-item border-0 py-3 px-4">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-bold text-primary">#{{ $cotizacion->coti_num }}</span>
                                <span class="badge bg-{{ trim($cotizacion->coti_estado) == 'A' ? 'success' : 'secondary' }} text-white">
                                    {{ $cotizacion->coti_estado }}
                                </span>
                            </div>
                            <p class="mb-1">{{ $cotizacion->coti_empresa }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">{{ $cotizacion->coti_fechaalta }}</small>
                                <span class="badge bg-light text-dark">
                                    {{ $cotizacion->instancias()->where('cotio_subitem', 0)->count() }} muestras procesadas
                                </span>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal de ubicación --}}
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-map-marked-alt me-2"></i> Ubicación de Muestra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="map" style="height: 400px; width: 100%;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

<script>
    // Configuración común para gráficos
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                enabled: true,
                callbacks: {
                    label: function(context) {
                        return `${context.label}: ${context.raw} (${context.formattedValue}%)`;
                    }
                }
            },
            datalabels: {
                formatter: (value, ctx) => {
                    const total = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const percentage = Math.round((value / total) * 100);
                    return percentage > 5 ? `${percentage}%` : '';
                },
                color: '#fff',
                font: {
                    weight: 'bold',
                    size: 12
                }
            }
        },
        cutout: '70%',
        borderRadius: 8,
        spacing: 4
    };

    // Chart.js - Estado de Muestras
    const muestrasCtx = document.getElementById('muestrasChart').getContext('2d');
    new Chart(muestrasCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'En Proceso', 'Finalizadas'],
            datasets: [{
                data: [{{ $muestrasPendientes }}, {{ $muestrasEnProceso }}, {{ $muestrasFinalizadas }}],
                backgroundColor: ['#ffc107', '#17a2b8', '#28a745'],
                borderWidth: 0
            }]
        },
        options: chartOptions,
        plugins: [ChartDataLabels]
    });

    // Chart.js - Estado de Análisis
    const analisisCtx = document.getElementById('analisisChart').getContext('2d');
    new Chart(analisisCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'En Proceso', 'Finalizados'],
            datasets: [{
                data: [{{ $analisisPendientes }}, {{ $analisisEnProceso }}, {{ $analisisFinalizados }}],
                backgroundColor: ['#ffc107', '#17a2b8', '#28a745'],
                borderWidth: 0
            }]
        },
        options: chartOptions,
        plugins: [ChartDataLabels]
    });

    // Mostrar ubicación en modal
    function loadGoogleMaps() {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${config('app.GOOGLE_API_KEY')}&callback=initMap`;
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }

    // Mostrar ubicación en modal
    document.querySelectorAll('.show-location').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);

            const modal = new bootstrap.Modal(document.getElementById('locationModal'));
            modal.show();

            // Inicializar el mapa después de que el modal se muestre
            setTimeout(() => {
                const map = new google.maps.Map(document.getElementById('map'), {
                    center: { lat: lat, lng: lng },
                    zoom: 15,
                    mapTypeId: 'roadmap',
                    disableDefaultUI: false,
                    zoomControl: true,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                    styles: [
                        {
                            featureType: "poi",
                            elementType: "labels",
                            stylers: [{ visibility: "off" }]
                        }
                    ]
                });

                // Añadir marcador
                new google.maps.Marker({
                    position: { lat: lat, lng: lng },
                    map: map,
                    title: 'Ubicación de muestra',
                    icon: {
                        url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png"
                    }
                });
            }, 500);
        });
    });

    // Cargar Google Maps cuando el documento esté listo
    document.addEventListener('DOMContentLoaded', loadGoogleMaps);
</script>

@endsection