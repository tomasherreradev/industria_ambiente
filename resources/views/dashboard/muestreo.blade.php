@extends('layouts.app')

@section('content')
@include('partials.ucrud-styles')
<link rel="stylesheet" href="{{ asset('css/dashboard-home.css') }}?v={{ filemtime(public_path('css/dashboard-home.css')) }}">
<link rel="stylesheet" href="{{ asset('css/dashboard-sub.css') }}?v={{ filemtime(public_path('css/dashboard-sub.css')) }}">

@php
    $estadoFiltro = $estadoFiltro ?? request('estado', 'all');
    $kpiActivo = fn (string $estado) => $estadoFiltro === $estado ? ' dash-kpi--active' : '';
@endphp

<div class="container-fluid px-3 px-lg-4 py-4 ucrud dash-home dash-sub">
    @include('dashboard.partials.sub-hero', [
        'title' => 'Dashboard de muestreo',
        'subtitle' => 'Planificación de campo, responsables, vehículos y estados.',
        'backUrl' => route('dashboard'),
    ])

    <div class="dash-home__kpi-grid dash-home__kpi-grid--5 mb-3">
        <a href="{{ route('cotizaciones.index', ['estado' => 'A']) }}" class="dash-kpi dash-kpi--primary">
            <span class="dash-kpi__icon"><x-heroicon-o-document-check /></span>
            <span>
                <span class="dash-kpi__label">Cotiz. aprobadas</span>
                <span class="dash-kpi__value">{{ number_format($cotizacionesAprobadas ?? 0, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Total aprobadas</span>
            </span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado muestreo']) }}" class="dash-kpi dash-kpi--informes{{ $kpiActivo('coordinado muestreo') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-clock /></span>
            <span>
                <span class="dash-kpi__label">Coordinados</span>
                <span class="dash-kpi__value">{{ number_format($pendientes, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Por muestrear</span>
            </span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'en revision muestreo']) }}" class="dash-kpi dash-kpi--muestreo{{ $kpiActivo('en revision muestreo') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-arrow-path /></span>
            <span>
                <span class="dash-kpi__label">En revisión</span>
                <span class="dash-kpi__value">{{ number_format($enProceso, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Muestreado en revisión</span>
            </span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'muestreado']) }}" class="dash-kpi dash-kpi--analisis{{ $kpiActivo('muestreado') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-check-circle /></span>
            <span>
                <span class="dash-kpi__label">Finalizadas</span>
                <span class="dash-kpi__value">{{ number_format($finalizadas, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Completadas</span>
            </span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'suspension']) }}" class="dash-kpi dash-kpi--danger{{ $kpiActivo('suspension') }}">
            <span class="dash-kpi__icon"><x-heroicon-o-x-circle /></span>
            <span>
                <span class="dash-kpi__label">Suspendidas</span>
                <span class="dash-kpi__value">{{ number_format($suspendidas ?? 0, 0, ',', '.') }}</span>
                <span class="dash-kpi__hint">Fuera de operación</span>
            </span>
        </a>
    </div>

    {{-- Tabla + gráfico en la misma fila --}}
    <div class="row g-3 align-items-start dash-sub-table-chart">
        <div class="col-12 col-xl-8 dash-sub-table-chart__main">
            <div class="dash-panel h-100">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Muestras asignadas</h2>
                        <p class="dash-panel__subtitle mb-0">
                            Muestras asignadas a mi o a mi equipo
                            @if(isset($esDiaUno) && $esDiaUno)
                                <span class="ucrud-chip ucrud-chip--cyan ms-1">Mes actual (día 1)</span>
                            @endif
                        </p>
                    </div>
                    <div class="dash-filter-bar">
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle" type="button" id="filterEstadoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-filter me-1"></i> Estado
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterEstadoDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'all', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Todos</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado muestreo', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Coordinados para Muestrear</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'en revision muestreo', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Muestreado en Revisión</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'muestreado', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Finalizados</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'suspension', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Suspendidas</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'proximos', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Próximos 3 días</a></li>
                                </ul>
                            </div>
                            
                            <!-- Filtro de Muestreador -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle" type="button" id="filterMuestreadorDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i> Muestreador
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterMuestreadorDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['muestreador' => 'all', 'estado' => request('estado', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Todos</a></li>
                                    @foreach($muestreadores as $muestreador)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['muestreador' => $muestreador->usu_codigo, 'estado' => request('estado', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">{{ $muestreador->usu_descripcion }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Filtro de Vehículo -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle" type="button" id="filterVehiculoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-car me-1"></i> Vehículo
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterVehiculoDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['vehiculo' => 'all', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Todos</a></li>
                                    @foreach($vehiculosDisponibles as $vehiculo)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['vehiculo' => $vehiculo->id, 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'zona' => request('zona', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">{{ $vehiculo->patente }} - {{ $vehiculo->marca }} {{ $vehiculo->modelo }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Filtro de Zona -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle" type="button" id="filterZonaDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-map-marker-alt me-1"></i> Zona
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterZonaDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['zona' => 'all', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">Todas</a></li>
                                    @foreach($zonasDisponibles as $zona)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['zona' => $zona->zon_codigo, 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'cuotas' => request('cuotas', 'all')]) }}">{{ $zona->zon_descripcion }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Filtro de Cuotas -->
                            <div class="dropdown">
                                <button class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm dropdown-toggle" type="button" id="filterCuotasDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-money-bill-wave me-1"></i> Cuotas
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterCuotasDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['cuotas' => 'all', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Todas</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['cuotas' => 'yes', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Con cuotas</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['cuotas' => 'no', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Sin cuotas</a></li>
                                </ul>
                            </div>
                            
                            <!-- Botón para limpiar filtros -->
                            @if(request('estado') != 'all' || request('muestreador') != 'all' || request('vehiculo') != 'all' || request('zona') != 'all' || request('cuotas') != 'all')
                            <a href="{{ route('dashboard.muestreo') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm text-danger">
                                Limpiar filtros
                            </a>
                            @endif
                            <button type="button" class="ucrud-btn ucrud-btn--success ucrud-btn--sm" data-bs-toggle="modal" data-bs-target="#modalExportar">
                                <x-heroicon-o-arrow-down-tray style="width: 14px; height: 14px;" />
                                Exportar
                            </button>
                    </div>
                </div>
                <div class="ucrud-tablewrap dash-muestreo-tablewrap">
                        <table class="ucrud-table ucrud-table--dash-muestreo mb-0">
                            <colgroup>
                                <col class="dash-muestreo-col dash-muestreo-col--coti">
                                <col class="dash-muestreo-col dash-muestreo-col--cliente">
                                <col class="dash-muestreo-col dash-muestreo-col--desc">
                                <col class="dash-muestreo-col dash-muestreo-col--fecha">
                                <col class="dash-muestreo-col dash-muestreo-col--resp">
                                <col class="dash-muestreo-col dash-muestreo-col--lab">
                                <col class="dash-muestreo-col dash-muestreo-col--estado">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Cotiz.</th>
                                    <th>Cliente</th>
                                    <th>Muestra</th>
                                    <th>Fecha</th>
                                    <th>Resp.</th>
                                    <th class="text-center" title="Pasada a laboratorio (OT) o documentación (mediciones)">Lab.</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($muestras as $muestra)
                                <tr>
                                    <td class="fw-semibold">
                                        <div class="dash-muestreo-table__coti">
                                            <a href="/show/{{ $muestra->cotizacion->coti_num }}" class="text-primary text-nowrap">
                                                {{ $muestra->cotizacion->coti_num ?? 'N/A' }}
                                            </a>
                                            @if($muestra->cotizacion && $muestra->cotizacion->coti_cuotas)
                                                <span class="badge dash-muestreo-table__badge bg-[#0dcaf0] text-white rounded-pill" style="background-color: #0dcaf0;" title="Cuotas">C</span>
                                            @endif
                                            @if($muestra->cotizacion)
                                                @include('muestras.partials.canal-especial-badge', ['coti' => $muestra->cotizacion])
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $cliM = $muestra->cotizacion->cliente ?? null;
                                            $nombreClienteM = trim((string) (optional($cliM)->cli_razonsocial ?? ''));
                                            if ($nombreClienteM === '') {
                                                $nombreClienteM = trim((string) (optional($cliM)->cli_fantasia ?? ''));
                                            }
                                            if ($nombreClienteM === '') {
                                                $nombreClienteM = \App\Support\CotizacionClienteEtiqueta::paraLista($muestra->cotizacion);
                                                if ($nombreClienteM === '—') {
                                                    $nombreClienteM = '';
                                                }
                                            }
                                        @endphp
                                        <span class="dash-muestreo-table__clip" title="{{ $nombreClienteM }}">{{ $nombreClienteM !== '' ? $nombreClienteM : '—' }}</span>
                                    </td>
                                    <td title="{{ $muestra->cotio_descripcion }}">
                                        <a href="{{ route('muestras.ver', [
                                            'cotizacion' => $muestra->cotizacion->coti_num,
                                            'item' => $muestra->cotio_item,
                                            'instance' => $muestra->instance_number
                                        ]) }}" class="text-primary dash-muestreo-table__clip">
                                            {{ $muestra->cotio_descripcion }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        @if($muestra->fecha_muestreo)
                                            <span>{{ $muestra->fecha_muestreo->format('d/m/y H:i') }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($muestra->responsablesMuestreo->count() > 0)
                                            <div class="avatar-group dash-muestreo-table__avatars">
                                                @foreach($muestra->responsablesMuestreo->take(3) as $responsable)
                                                <span class="avatar avatar-xs" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{ $responsable->usu_descripcion }}">
                                                    {{ substr($responsable->usu_descripcion, 0, 1) }}{{ substr(strstr($responsable->usu_descripcion, ' '), 1, 1) }}
                                                </span>
                                                @endforeach
                                                @if($muestra->responsablesMuestreo->count() > 3)
                                                    <span class="dash-muestreo-table__more">+{{ $muestra->responsablesMuestreo->count() - 3 }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        @php
                                            $matrizDashboard = optional($muestra->cotizacion?->matriz)->matriz_descripcion;
                                            $pasadaLab = \App\Support\CotizacionCanalEnsayo::instanciaPasadaALaboratorio($muestra);
                                            $pendientePasarLab = \App\Support\CotizacionCanalEnsayo::instanciaPendientePasarALaboratorio($muestra, $matrizDashboard);
                                            $enDocumentacion = \App\Support\CotizacionCanalEnsayo::instanciaMedicionesEnDocumentacion($muestra);
                                        @endphp
                                        @if($pasadaLab)
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold"
                                                  style="width: 1.25rem; height: 1.25rem; font-size: 0.72rem;"
                                                  title="Pasada a laboratorio"
                                                  aria-label="Pasada a laboratorio">✓</span>
                                        @elseif($enDocumentacion)
                                            <span class="text-muted small" title="En documentación (mediciones; no aplica laboratorio)">—</span>
                                        @elseif($pendientePasarLab)
                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger text-white fw-bold"
                                                  style="width: 1.25rem; height: 1.25rem; font-size: 0.72rem;"
                                                  title="Muestreada, pendiente de pasar a laboratorio"
                                                  aria-label="Pendiente de pasar a laboratorio">✕</span>
                                        @else
                                            <span class="d-inline-block border rounded"
                                                  style="width: 1.1rem; height: 1.1rem; background: #fff;"
                                                  title="Aún no aplica"
                                                  aria-label="No aplica"></span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $estadoM = (string) $muestra->cotio_estado;
                                            $pillClass = match($estadoM) {
                                                'coordinado muestreo' => 'warn',
                                                'en revision muestreo' => 'info',
                                                'muestreado' => 'ok',
                                                default => 'warn',
                                            };
                                        @endphp
                                        @php
                                            $estadoMuestreoCorto = match ($estadoM) {
                                                'coordinado muestreo' => 'Coord.',
                                                'en revision muestreo' => 'Revisión',
                                                'muestreado' => 'Listo',
                                                'suspension' => 'Susp.',
                                                default => Str::limit(str_replace('_', ' ', $estadoM), 10, '…'),
                                            };
                                        @endphp
                                        @if($estadoM === 'suspension')
                                            <span class="dash-estado-pill dash-estado-pill--compact" style="background:#fdeef2;color:#bc3a5c;border:1px solid #f8d3dd;" title="Suspendida">{{ $estadoMuestreoCorto }}</span>
                                        @else
                                            <span class="dash-estado-pill dash-estado-pill--compact dash-estado-pill--{{ $pillClass }}" title="{{ str_replace('_', ' ', $estadoM) }}">{{ $estadoMuestreoCorto }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                        <p class="mb-0">No hay muestras asignadas actualmente</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="dash-panel__foot">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="text-muted small">
                                Mostrando {{ $muestras->firstItem() ?? 0 }} a {{ $muestras->lastItem() ?? 0 }} de {{ $muestras->total() }} muestras
                            </div>
                            <div class="ucrud-pagination">{{ $muestras->links() }}</div>
                        </div>
                    </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 dash-sub-table-chart__aside">
            <div class="dash-sub-table-chart__sticky">
                @include('dashboard.partials.sub-segment-chart', [
                    'chartId' => 'subMuestreo',
                    'title' => 'Estados de muestras',
                    'subtitle' => 'Clic en segmento o leyenda para filtrar la tabla',
                ])
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start dash-sub-sidebar-row">
        <div class="col-12 col-xl-8"></div>
        <div class="col-12 col-xl-4 dash-sub-table-chart__aside">
            <div class="dash-panel mb-3">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Muestras próximas</h2>
                        <p class="dash-panel__subtitle">Próximos 3 días</p>
                    </div>
                </div>
                <div class="dash-sidebar-list">
                        @forelse($muestrasProximas as $muestra)
                        <div class="dash-sidebar-list__item">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-bold">#{{ $muestra->cotizacion->coti_num ?? 'N/A' }}</span>
                                @if($muestra->cotizacion && $muestra->cotizacion->coti_cuotas)
                                    <span class="badge bg-[#0dcaf0] text-white px-1 py-0 rounded-pill ms-1" style="font-size: 0.6rem; background-color: #0dcaf0;">CUOTAS</span>
                                @endif
                                @if($muestra->cotizacion)
                                    @include('muestras.partials.canal-especial-badge', ['coti' => $muestra->cotizacion])
                                @endif
                                <small class="text-muted">{{ $muestra->fecha_muestreo ? $muestra->fecha_muestreo->format('d/m H:i') : 'Sin fecha' }}</small>
                            </div>
                            <p class="mb-1 small text-truncate">{{ $muestra->cotio_descripcion }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-{{ $muestra->cotio_estado == 'coordinado muestreo' ? 'warning text-dark' : ($muestra->cotio_estado == 'en revision muestreo' ? 'info text-dark' : 'success') }} small">
                                    {{ Str::title(str_replace('_', ' ', $muestra->cotio_estado)) }}
                                </span>
                                @if($muestra->vehiculo)
                                <span class="badge bg-light text-dark small">
                                    <i class="fas fa-car me-1"></i> {{ $muestra->vehiculo->patente }}
                                </span>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="ucrud-empty py-4">
                            <p class="ucrud-empty__text mb-0">No hay muestras programadas para los próximos 3 días</p>
                        </div>
                        @endforelse
                </div>
            </div>

            <div class="dash-panel mb-3">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Vehículos asignados</h2>
                        <p class="dash-panel__subtitle">En uso por tu equipo</p>
                    </div>
                </div>
                <div class="p-0">
                    @forelse($vehiculosAsignados as $vehiculo)
                    <div class="dash-resource">
                        <div class="dash-resource__icon"><x-heroicon-o-truck /></div>
                        <div class="dash-resource__body">
                            <p class="dash-resource__title">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</p>
                            <p class="dash-resource__meta mb-0">Patente: {{ $vehiculo->patente }}</p>
                        </div>
                    </div>
                    @empty
                    <div class="ucrud-empty py-4">
                        <p class="ucrud-empty__text mb-0">No hay vehículos asignados actualmente</p>
                    </div>
                    @endforelse
                </div>
            </div>

            <div class="dash-panel">
                <div class="dash-panel__head">
                    <div>
                        <h2 class="dash-panel__title">Herramientas en uso</h2>
                        <p class="dash-panel__subtitle">Equipamiento asignado</p>
                    </div>
                </div>
                <div class="p-0">
                    @forelse($herramientasEnUso as $herramienta)
                    <div class="dash-resource">
                        <div class="dash-resource__icon"><x-heroicon-o-wrench-screwdriver /></div>
                        <div class="dash-resource__body">
                            <p class="dash-resource__title">{{ $herramienta->nombre }}</p>
                            <p class="dash-resource__meta mb-0">Serial: {{ $herramienta->serial }}</p>
                        </div>
                        <span class="ucrud-chip ucrud-chip--slate">{{ $herramienta->cotio_instancias_count }} uso(s)</span>
                    </div>
                    @empty
                    <div class="ucrud-empty py-4">
                        <p class="ucrud-empty__text mb-0">No hay herramientas en uso actualmente</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal para Exportar --}}
<div class="modal fade" id="modalExportar" tabindex="-1" aria-labelledby="modalExportarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExportarLabel">
                    <i class="fas fa-file-excel me-2"></i>Exportar Muestras a Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.muestreo.exportar') }}" method="POST" id="formExportar">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">Seleccione el período para exportar las muestras:</p>
                    
                    <div class="mb-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>El archivo incluirá: N° Cotización, Nombre de la Muestra, Descripción, Responsables, Estado, Fechas de Inicio y Fin, Vehículo, Zona y Cliente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@php
    $dashboardSegmentChartsConfig = [
        'subMuestreo' => [
            'labels' => ['Coordinados', 'En revisión', 'Finalizadas', 'Suspendidas'],
            'data' => [
                (int) $pendientes,
                (int) $enProceso,
                (int) $finalizadas,
                (int) ($suspendidas ?? 0),
            ],
            'colors' => [
                ['base' => '#f0ad4e', 'hover' => '#f7c774'],
                ['base' => '#36b9cc', 'hover' => '#5ccfe0'],
                ['base' => '#1cc88a', 'hover' => '#3dd9a4'],
                ['base' => '#e74a3b', 'hover' => '#ef6f63'],
            ],
            'segmentLinks' => [
                request()->fullUrlWithQuery(['estado' => 'coordinado muestreo']),
                request()->fullUrlWithQuery(['estado' => 'en revision muestreo']),
                request()->fullUrlWithQuery(['estado' => 'muestreado']),
                request()->fullUrlWithQuery(['estado' => 'suspension']),
            ],
        ],
    ];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    window.dashboardSegmentCharts = @json($dashboardSegmentChartsConfig);
</script>
<script src="{{ asset('js/dashboard-home-charts.js') }}?v={{ filemtime(public_path('js/dashboard-home-charts.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
            });
        });

        const currentEstado = '{{ $estadoFiltro }}';
        const currentMuestreador = '{{ $muestreadorFiltro }}';
        const currentVehiculo = '{{ $vehiculoFiltro }}';
        const currentZona = '{{ $zonaFiltro }}';
        const currentCuotas = '{{ $cuotasFiltro }}';
        
        // Actualizar botón de Estado
        const filterEstadoDropdown = document.getElementById('filterEstadoDropdown');
        if (filterEstadoDropdown) {
            const estadoOptions = filterEstadoDropdown.nextElementSibling.querySelectorAll('.filter-option');
            estadoOptions.forEach(option => {
                if (option.getAttribute('href').includes(`estado=${currentEstado}`)) {
                    filterEstadoDropdown.innerHTML = `<i class="fas fa-filter me-1"></i> Estado: ${option.textContent}`;
                }
            });
        }
        
        // Actualizar botón de Muestreador
        const filterMuestreadorDropdown = document.getElementById('filterMuestreadorDropdown');
        if (filterMuestreadorDropdown && currentMuestreador !== 'all') {
            const muestreadorOptions = filterMuestreadorDropdown.nextElementSibling.querySelectorAll('.filter-option');
            muestreadorOptions.forEach(option => {
                if (option.getAttribute('href').includes(`muestreador=${currentMuestreador}`)) {
                    filterMuestreadorDropdown.innerHTML = `<i class="fas fa-user me-1"></i> ${option.textContent}`;
                }
            });
        }
        
        // Actualizar botón de Vehículo
        const filterVehiculoDropdown = document.getElementById('filterVehiculoDropdown');
        if (filterVehiculoDropdown && currentVehiculo !== 'all') {
            const vehiculoOptions = filterVehiculoDropdown.nextElementSibling.querySelectorAll('.filter-option');
            vehiculoOptions.forEach(option => {
                if (option.getAttribute('href').includes(`vehiculo=${currentVehiculo}`)) {
                    filterVehiculoDropdown.innerHTML = `<i class="fas fa-car me-1"></i> ${option.textContent.split(' - ')[0]}`;
                }
            });
        }
        
        // Actualizar botón de Zona
        const filterZonaDropdown = document.getElementById('filterZonaDropdown');
        if (filterZonaDropdown && currentZona !== 'all') {
            const zonaOptions = filterZonaDropdown.nextElementSibling.querySelectorAll('.filter-option');
            zonaOptions.forEach(option => {
                if (option.getAttribute('href').includes(`zona=${currentZona}`)) {
                    filterZonaDropdown.innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${option.textContent}`;
                }
            });
        }

        // Actualizar botón de Cuotas
        const filterCuotasDropdown = document.getElementById('filterCuotasDropdown');
        if (filterCuotasDropdown && currentCuotas !== 'all') {
            const cuotasOptions = filterCuotasDropdown.nextElementSibling.querySelectorAll('.filter-option');
            cuotasOptions.forEach(option => {
                if (option.getAttribute('href').includes(`cuotas=${currentCuotas}`)) {
                    filterCuotasDropdown.innerHTML = `<i class="fas fa-money-bill-wave me-1"></i> ${option.textContent}`;
                }
            });
        }

        // Validación del formulario de exportación
        const formExportar = document.getElementById('formExportar');
        if (formExportar) {
            formExportar.addEventListener('submit', function(e) {
                const fechaDesde = document.getElementById('fecha_desde').value;
                const fechaHasta = document.getElementById('fecha_hasta').value;
                
                if (!fechaDesde || !fechaHasta) {
                    e.preventDefault();
                    alert('Por favor, complete ambas fechas.');
                    return false;
                }
                
                if (new Date(fechaDesde) > new Date(fechaHasta)) {
                    e.preventDefault();
                    alert('La fecha desde debe ser anterior o igual a la fecha hasta.');
                    return false;
                }
            });
        }

        // Establecer valores por defecto en el modal (mes actual)
        const modalExportar = document.getElementById('modalExportar');
        if (modalExportar) {
            modalExportar.addEventListener('show.bs.modal', function() {
                const hoy = new Date();
                const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                
                document.getElementById('fecha_desde').value = primerDiaMes.toISOString().split('T')[0];
                document.getElementById('fecha_hasta').value = ultimoDiaMes.toISOString().split('T')[0];
            });
        }
    });
</script>
@endpush

@endsection