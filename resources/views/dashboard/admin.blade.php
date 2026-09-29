@extends('layouts.app')

@section('content')
@include('partials.ucrud-styles')
<link rel="stylesheet" href="{{ asset('css/dashboard-home.css') }}?v={{ filemtime(public_path('css/dashboard-home.css')) }}">

@php
    $hora = (int) now()->format('H');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $nombreUsuario = trim((string) (Auth::user()->usu_descripcion ?? Auth::user()->usu_codigo ?? ''));

    $vehiculosOcupadosCount = isset($vehiculosOcupados) ? $vehiculosOcupados->count() : 0;
@endphp

<div class="container-fluid px-3 px-lg-4 py-4 ucrud dash-home">
    <header class="dash-home__hero">
        <div>
            <h1 class="dash-home__hero-title">{{ $saludo }}, {{ $nombreUsuario }}</h1>
            <p class="dash-home__hero-sub">Vista general de cotizaciones, muestreo, análisis e informes.</p>
        </div>
        <div class="dash-home__hero-meta">
            <span class="dash-home__date">{{ fechaActualLargaEs() }}</span>
            @if(Auth::user() && (int) Auth::user()->usu_nivel >= 900)
                <a href="{{ route('admin.volumen-costeos') }}" class="ucrud-btn ucrud-btn--outline-primary ucrud-btn--sm">
                    Volumetría / costeos
                </a>
            @endif
        </div>
    </header>

    @include('dashboard.partials.modulos-acceso')

    <div class="dash-home__kpi-grid">
        <a href="{{ route('cotizaciones.index') }}" class="dash-kpi dash-kpi--cotizaciones">
            <span class="dash-kpi__icon" aria-hidden="true">
                <x-heroicon-o-document-currency-dollar />
            </span>
            <span>
                <span class="dash-kpi__label">Cotizaciones</span>
                <span class="dash-kpi__value">{{ number_format($totalCotizaciones, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Total registradas</span>
            </span>
        </a>
        <a href="{{ route('muestras.index') }}" class="dash-kpi dash-kpi--muestreo">
            <span class="dash-kpi__icon" aria-hidden="true">
                <x-heroicon-o-beaker />
            </span>
            <span>
                <span class="dash-kpi__label">Muestras</span>
                <span class="dash-kpi__value">{{ number_format($muestrasTotales, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Instancias en sistema</span>
            </span>
        </a>
        <a href="{{ route('ordenes.index') }}" class="dash-kpi dash-kpi--analisis">
            <span class="dash-kpi__icon" aria-hidden="true">
                <x-heroicon-o-cube-transparent />
            </span>
            <span>
                <span class="dash-kpi__label">Análisis (OT)</span>
                <span class="dash-kpi__value">{{ number_format($analisisTotales, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Con orden de trabajo</span>
            </span>
        </a>
        <a href="{{ route('informes.index') }}" class="dash-kpi dash-kpi--informes">
            <span class="dash-kpi__icon" aria-hidden="true">
                <x-heroicon-o-document-chart-bar />
            </span>
            <span>
                <span class="dash-kpi__label">Informes</span>
                <span class="dash-kpi__value">{{ number_format($informesTotales, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Habilitados en operación</span>
            </span>
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-8">
            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Operación en curso</h2>
                        <p class="dash-panel__subtitle">Distribución de estados de muestreo y análisis</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('dashboard.muestreo') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Dashboard muestreo</a>
                        <a href="{{ route('dashboard.analisis') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Dashboard análisis</a>
                    </div>
                </div>
                <div class="dash-panel__body">
                    @if($vehiculosOcupadosCount > 0)
                        <div class="dash-alert-inline" role="status">
                            <x-heroicon-o-truck />
                            <span>
                                <strong>{{ $vehiculosOcupadosCount }}</strong>
                                {{ $vehiculosOcupadosCount === 1 ? 'vehículo ocupado' : 'vehículos ocupados' }} en este momento.
                                <a href="{{ route('vehiculos.index') }}" class="ms-1">Ver flota</a>
                            </span>
                        </div>
                    @endif

                    @include('dashboard.partials.home-operacion-charts')
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Cotizaciones recientes</h2>
                        <p class="dash-panel__subtitle">Últimas altas en el sistema</p>
                    </div>
                    <a href="{{ route('cotizaciones.index') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Ver todas</a>
                </div>
                <ul class="dash-coti-list">
                    @foreach($cotizacionesRecientes as $cotizacion)
                        <li class="dash-coti-list__item">
                            <a href="{{ route('cotizaciones.ver-detalle', $cotizacion->coti_num) }}" class="dash-coti-list__link">
                                <div class="dash-coti-list__row">
                                    <span class="dash-coti-list__num">
                                        #{{ $cotizacion->coti_num }}
                                        @if($cotizacion->coti_cuotas)
                                            <span class="dash-badge-cuotas">CUOTAS</span>
                                        @endif
                                    </span>
                                    <span class="ucrud-chip ucrud-chip--{{ trim($cotizacion->coti_estado) == 'A' ? 'green' : 'slate' }}">
                                        {{ $cotizacion->coti_estado }}
                                    </span>
                                </div>
                                <p class="dash-coti-list__client">{{ \App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion) }}</p>
                                <div class="dash-coti-list__foot">
                                    <span>{{ $cotizacion->coti_fechaalta }}</span>
                                    <span>{{ $cotizacion->instancias()->where('cotio_subitem', 0)->count() }} muestras</span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div class="dash-panel">
        <div class="dash-panel__head">
            <div>
                <h2 class="dash-panel__title">Muestreos en los próximos 7 días</h2>
                <p class="dash-panel__subtitle">Fechas de fin de muestreo dentro de la semana</p>
            </div>
            <a href="{{ route('muestras.index') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">Ir a muestras</a>
        </div>
        <div class="ucrud-tablewrap">
            <table class="ucrud-table mb-0">
                <thead>
                    <tr>
                        <th>Cotización</th>
                        <th>Descripción</th>
                        <th>Inicio</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($muestrasProximas as $muestra)
                        @php
                            $estadoM = (string) $muestra->cotio_estado;
                            $pillClass = $estadoM === 'coordinado muestreo' ? 'warn' : ($estadoM === 'en revision muestreo' ? 'info' : 'ok');
                        @endphp
                        <tr>
                            <td class="fw-semibold">
                                {{ $muestra->cotizacion->coti_num ?? 'N/A' }}
                                @if($muestra->cotizacion && $muestra->cotizacion->coti_cuotas)
                                    <span class="dash-badge-cuotas">CUOTAS</span>
                                @endif
                            </td>
                            <td style="max-width: 220px;">
                                <a href="{{ route('muestras.ver', [
                                    'cotizacion' => $muestra->cotizacion->coti_num,
                                    'item' => $muestra->cotio_item,
                                    'instance' => $muestra->instance_number
                                ]) }}" class="text-decoration-none fw-semibold">
                                    {{ Str::limit($muestra->cotio_descripcion ?? 'N/A', 48) }}
                                </a>
                            </td>
                            <td>
                                <span class="d-block">{{ $muestra->fecha_inicio_muestreo->format('d/m/Y') }}</span>
                                <small class="text-muted">{{ $muestra->fecha_inicio_muestreo->format('H:i') }}</small>
                            </td>
                            <td>
                                @if($muestra->latitud && $muestra->longitud)
                                    <a href="#" class="dash-link-map show-location" data-lat="{{ $muestra->latitud }}" data-lng="{{ $muestra->longitud }}">
                                        <x-heroicon-o-map-pin style="width: 14px; height: 14px;" />
                                        Ver mapa
                                    </a>
                                @else
                                    <span class="text-muted small">Sin ubicación</span>
                                @endif
                            </td>
                            <td>
                                <span class="dash-estado-pill dash-estado-pill--{{ $pillClass }}">
                                    {{ Str::title(str_replace('_', ' ', $estadoM)) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="ucrud-empty">
                                    <div class="ucrud-empty__icon">
                                        <x-heroicon-o-calendar-days />
                                    </div>
                                    <p class="ucrud-empty__title">Nada programado</p>
                                    <p class="ucrud-empty__text">No hay muestras con fin de muestreo en los próximos 7 días.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <x-heroicon-o-map-pin style="width: 20px; height: 20px;" />
                    Ubicación de muestra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="dashMap" style="height: 400px; width: 100%;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('.show-location').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);
            const modalEl = document.getElementById('locationModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

            modal.show();

            modalEl.addEventListener('shown.bs.modal', function onShown() {
                modalEl.removeEventListener('shown.bs.modal', onShown);
                if (typeof google === 'undefined' || !google.maps) {
                    return;
                }
                const mapEl = document.getElementById('dashMap');
                const map = new google.maps.Map(mapEl, {
                    center: { lat: lat, lng: lng },
                    zoom: 15,
                    mapTypeId: 'roadmap',
                    zoomControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                });
                new google.maps.Marker({
                    position: { lat: lat, lng: lng },
                    map: map,
                    title: 'Ubicación de muestra',
                });
            }, { once: true });
        });
    });
</script>
@endsection
