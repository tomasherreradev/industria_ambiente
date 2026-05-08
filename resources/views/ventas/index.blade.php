@extends('layouts.app')

@section('content')

@if(!empty($canalVistaVentas))
    <div class="alert alert-info mb-3">
        @if($canalVistaVentas === 'consultoria')
            Solo se listan cotizaciones que incluyen al menos un ensayo de <strong>consultoría</strong>.
        @elseif($canalVistaVentas === 'asp')
            Solo se listan cotizaciones que incluyen al menos un ensayo <strong>ASP</strong>.
        @elseif($canalVistaVentas === 'clarke_fire')
            Solo se listan cotizaciones que incluyen al menos un ensayo <strong>Clarke Fire</strong>.
        @else
            Solo se listan cotizaciones que incluyen al menos un ensayo de su <strong>área</strong>.
        @endif
        Al abrir una cotización verá únicamente esos ensayos y sus componentes (solo lectura).
    </div>
@endif

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.dashboard-header {
    background-color: #0d6efd;
    color: white;
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.stats-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stats-card.active {
    border: 2px solid #0d6efd;
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(13, 110, 253, 0.1) 100%);
}

.stats-card-monto {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: default;
}

.stats-card-monto:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.table-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    background: #f8f9fa;
    padding: 1rem;
    border-bottom: 2px solid #dee2e6;
}

.badge-estado {
    padding: 0.35rem 0.65rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.estado-E { background: #ffc107; color: #000; }
.estado-A { background: #28a745; color: #fff; }
.estado-R { background: #dc3545; color: #fff; }
.estado-P { background: #17a2b8; color: #fff; }
.estado-C { background: #6c757d; color: #fff; }
.estado-S { background: #6f42c1; color: #fff; }
.estado-CERR { background: #155724; color: #fff; }
.estado-PROC { background: #0d6efd; color: #fff; }

.badge-counter {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    min-width: 2rem;
    text-align: center;
}

.search-filter-bar {
    padding: 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.action-buttons {
    white-space: nowrap;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.filter-active {
    background: #e7f3ff;
    padding: 0.5rem;
    border-radius: 5px;
}

.ventas-det-icon {
    transition: transform 0.2s ease;
    font-size: 0.75rem;
}
.ventas-det-toggle[aria-expanded="true"] .ventas-det-icon {
    transform: rotate(180deg);
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .row.g-2 > div {
        margin-bottom: 0.5rem;
    }
}

/* Tarjetas de resumen /ventas: una sola franja flexible (evita filas extra por wrap del grid) */
.ventas-stats-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.ventas-stats-strip .ventas-stats-metric {
    flex: 1 1 9.5rem;
    min-width: 9rem;
    max-width: 14rem;
}
.ventas-stats-strip .ventas-stats-monto-wrap {
    flex: 1 1 18rem;
    min-width: 16rem;
}
</style>

@php
    $cv = $conteosVentasTarjetas ?? [];
    $totalCotizaciones = (int) ($cv['total'] ?? 0);
    $enEspera = (int) ($cv['enEspera'] ?? 0);
    $aprobadas = (int) ($cv['aprobadas'] ?? 0);
    $rechazadas = (int) ($cv['rechazadas'] ?? 0);
    $suspendidas = (int) ($cv['suspendidas'] ?? 0);
    $cerradasDeriv = (int) ($cv['cerrada'] ?? 0);
    $procesoDeriv = (int) ($cv['procesoDeriv'] ?? 0);
@endphp

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="mb-1"><x-heroicon-o-chart-bar class="me-2" style="width: 16px; height: 16px;" />Dashboard de Cotizaciones</h1>
                <p class="mb-0 opacity-75">Gestión y análisis de cotizaciones</p>
            </div>
            <a href="{{ route('ventas.create') }}" class="btn btn-light btn-lg" style="font-size: 14px;">
                <x-heroicon-o-plus class="me-2" style="width: 16px; height: 16px;" /> Nueva Cotización
            </a>
        </div>
    </div>

    <!-- Estadísticas (una sola tira; sin segunda fila duplicada Cerrada/Proceso) -->
    <div class="ventas-stats-strip">
        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ !request('estado') ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('')" 
                 data-estado="">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total</h6>
                            <h3 class="mb-0 text-primary">{{ number_format($totalCotizaciones) }}</h3>
                        </div>
                        <div class="stats-icon text-primary">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == 'E' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('E')" 
                 data-estado="E">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">En Espera</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($enEspera) }}</h3>
                        </div>
                        <div class="stats-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == 'A' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('A')" 
                 data-estado="A">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Aprobadas</h6>
                            <h3 class="mb-0 text-success">{{ number_format($aprobadas) }}</h3>
                        </div>
                        <div class="stats-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == 'R' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('R')" 
                 data-estado="R">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Rechazadas</h6>
                            <h3 class="mb-0 text-danger">{{ number_format($rechazadas) }}</h3>
                        </div>
                        <div class="stats-icon text-danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == '_PROCESO' ? 'active' : '' }}"
                 onclick="filtrarPorEstado('_PROCESO')"
                 data-estado="_PROCESO">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">En Proceso</h6>
                            <h3 class="mb-0 text-info">{{ number_format($procesoDeriv) }}</h3>
                        </div>
                        <div class="stats-icon text-info">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == 'S' ? 'active' : '' }}"
                 onclick="filtrarPorEstado('S')"
                 data-estado="S">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Suspendidas</h6>
                            <h3 class="mb-0" style="color: #6f42c1;">{{ number_format($suspendidas) }}</h3>
                        </div>
                        <div class="stats-icon" style="color: #6f42c1;">
                            <i class="fas fa-pause-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ventas-stats-metric">
            <div class="card stats-card h-100 {{ request('estado') == '_CERRADA' ? 'active' : '' }}"
                 onclick="filtrarPorEstado('_CERRADA')"
                 data-estado="_CERRADA">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Cerradas</h6>
                            <h3 class="mb-0 text-success">{{ number_format($cerradasDeriv) }}</h3>
                        </div>
                        <div class="stats-icon text-success">
                            <i class="fas fa-flag-checkered"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ventas-stats-monto-wrap">
            <div class="card stats-card-monto bg-white text-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 opacity-75">Monto Total</h6>
                            <h2 class="mb-0 fw-bold" style="font-size: 26px;">${{ number_format($montoMostrar, 2, ',', '.') }}</h2>
                        </div>
                        <div class="stats-icon" style="opacity: 0.3;">
                            <i class="fas fa-dollar-sign" style="font-size: 3rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Cotizaciones -->
    <div class="table-container">
        <div class="table-header">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Cotizaciones</h5>
                    @if(request()->hasAny(['cliente', 'vendedor', 'sucursal', 'estado', 'fecha_desde', 'fecha_hasta']))
                        <small class="text-muted">
                            <i class="fas fa-filter me-1"></i>Filtros activos
                        </small>
                    @endif
                </div>
                
                <!-- Filtros -->
                <form method="GET" action="{{ route('ventas.index') }}" id="filterForm">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Cliente</label>
                            <select name="cliente" class="form-select form-select-sm js-select2-cliente" data-placeholder="Todos los clientes">
                                <option value="">Todos los clientes</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->cli_codigo }}" {{ request('cliente') == $cliente->cli_codigo ? 'selected' : '' }}>
                                        {{ trim($cliente->cli_codigo) }} - {{ trim($cliente->cli_razonsocial) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Vendedor</label>
                            <select name="vendedor" class="form-select form-select-sm js-select2-vendedor" data-placeholder="Todos los vendedores">
                                <option value="">Todos los vendedores</option>
                                @foreach(($vendedores ?? []) as $vendedor)
                                    <option value="{{ trim((string) $vendedor->usu_codigo) }}" {{ request('vendedor') == trim((string) $vendedor->usu_codigo) ? 'selected' : '' }}>
                                        {{ trim($vendedor->usu_codigo) }} - {{ trim($vendedor->usu_descripcion ?? '') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Sucursal</label>
                            <select name="sucursal" class="form-select form-select-sm js-select2-sucursal" data-placeholder="Todas" onchange="this.form.submit()" {{ request('cliente') ? '' : 'disabled' }}>
                                <option value="">Todas</option>
                                @if(request('cliente'))
                                    <option value="_SIN_" {{ request('sucursal') === '_SIN_' ? 'selected' : '' }}>Sin sucursal</option>
                                    @foreach(($sucursales ?? collect()) as $suc)
                                        @php
                                            $cod = trim((string) ($suc->cli_codigo ?? ''));
                                            $rs = trim((string) ($suc->cli_razonsocial ?? 'Sucursal'));
                                            $dir = trim((string) ($suc->cli_direccion ?? ''));
                                            $loc = trim((string) ($suc->cli_localidad ?? ''));
                                            $part = trim((string) ($suc->cli_partido ?? ''));
                                            $locLine = $loc;
                                            if ($part !== '') {
                                                $locLine = $locLine !== '' ? ($locLine . ' - ' . $part) : $part;
                                            }
                                            $detalle = trim(implode(' · ', array_filter([$dir, $locLine])));
                                            $label = $detalle !== '' ? ($cod . ' - ' . $rs . ' · ' . $detalle) : ($cod . ' - ' . $rs);
                                        @endphp
                                        <option value="{{ $cod }}" {{ request('sucursal') === $cod ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @if(!request('cliente'))
                                <small class="text-muted">Elegí un cliente para ver sucursales.</small>
                            @endif
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Estado</label>
                            <select name="estado" class="form-select form-select-sm js-select2-estado" data-placeholder="Todos los estados">
                                <option value="">Todos los estados</option>
                                <option value="E" {{ request('estado') == 'E' ? 'selected' : '' }}>En Espera</option>
                                <option value="A" {{ request('estado') == 'A' ? 'selected' : '' }}>Aprobado</option>
                                <option value="P" {{ request('estado') == 'P' ? 'selected' : '' }}>En Proceso</option>
                                <option value="R" {{ request('estado') == 'R' ? 'selected' : '' }}>Rechazado</option>
                                <option value="S" {{ request('estado') == 'S' ? 'selected' : '' }}>Suspendida</option>
                                <option value="_CERRADA" {{ request('estado') == '_CERRADA' ? 'selected' : '' }}>Cerrada (operativa)</option>
                                <option value="_PROCESO" {{ request('estado') == '_PROCESO' ? 'selected' : '' }}>Proceso (operativa)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ request('fecha_desde') }}" onchange="this.form.submit()">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ request('fecha_hasta') }}" onchange="this.form.submit()">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" onclick="limpiarFiltros()" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </button>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar..." value="{{ request('search') }}">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Empresa</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Evitar "Undefined variable" en vistas cacheadas/edge cases
                        $nombreCliente = null;
                        $paraNombre = null;
                    @endphp
                    @foreach($cotizaciones as $cotizacion)
                    @php
                        $estCotiRow = trim((string) ($cotizacion->coti_estado ?? ''));
                        $esAprobadaRow = $estCotiRow !== '' && strtoupper($estCotiRow[0]) === 'A';
                        $detalleVentas = $esAprobadaRow ? (($detalleMuestrasVentas ?? [])[$cotizacion->coti_num] ?? []) : [];
                    @endphp
                    <tr class="{{ ($esAprobadaRow && count($detalleVentas) > 0) ? 'ventas-row-clickable' : '' }}" style="{{ ($esAprobadaRow && count($detalleVentas) > 0) ? 'cursor:pointer;' : '' }}">
                        <td>
                            @if($esAprobadaRow && count($detalleVentas) > 0)
                                <button type="button"
                                        class="btn btn-link btn-sm p-0 me-1 align-baseline text-decoration-none ventas-det-toggle"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#ventas-det-{{ $cotizacion->coti_num }}"
                                        aria-expanded="false"
                                        aria-controls="ventas-det-{{ $cotizacion->coti_num }}"
                                        title="Ver muestras y ensayos">
                                    <i class="fas fa-chevron-down ventas-det-icon"></i>
                                </button>
                            @endif
                            <strong>#{{ $cotizacion->coti_num }}@if($cotizacion->coti_version != 1).{{ $cotizacion->coti_version ?? 1 }} @endif</strong>
                        </td>
                        <td>{{ Str::limit($cotizacion->coti_descripcion, 50) ?: 'Sin descripción' }}</td>
                        <td>
                            {{ Str::limit(\App\Support\CotizacionClienteEtiqueta::paraLista($cotizacion), 50) ?: '-' }}
                        </td>
                        <td>
                            @php
                                $badge = ($ventasBadgesListado ?? [])[$cotizacion->coti_num] ?? ['texto' => 'En Espera', 'class' => 'estado-E'];
                                $estadoClass = $badge['class'];
                                $estadoTexto = $badge['texto'];
                            @endphp
                            <span class="badge {{ $estadoClass }} badge-estado">{{ $estadoTexto }}</span>
                            @if(!empty($cotizacion->cancelada))
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary ms-2"
                                        title="Ver razones de cancelación"
                                        data-reason="{{ $cotizacion->razon_cancelada }}"
                                        onclick="verRazonCancelacion(this)">
                                    ?
                                </button>
                            @endif
                        </td>
                        <td>{{ $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('d/m/Y') : '-' }}</td>
                        <td class="text-end action-buttons">
                            <a href="{{ route('cotizaciones.ver-detalle', $cotizacion->coti_num) }}" class="btn btn-sm btn-outline-info" title="Ver">
                                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                            </a>
                            <a href="{{ route('ventas.edit', $cotizacion->coti_num) }}" class="btn btn-sm btn-outline-primary" title="Ver/Editar">
                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                            </a>
                            @php
                                $estaAprobadaRow = !$cotizacion->cancelada && !empty($cotizacion->coti_estado) && strtoupper(trim((string) $cotizacion->coti_estado))[0] === 'A';
                            @endphp
                            @if($estaAprobadaRow)
                                <button type="button"
                                   class="btn btn-sm btn-outline-danger"
                                   title="No se puede eliminar una cotización aprobada"
                                   disabled>
                                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                </button>
                            @else
                                <button type="button" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="confirmarEliminacion({{ $cotizacion->coti_num }})"
                                   title="Eliminar">
                                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                </button>
                            @endif
                        </td>
                    </tr>
                    @if($esAprobadaRow && count($detalleVentas) > 0)
                    <tr class="collapse table-light" id="ventas-det-{{ $cotizacion->coti_num }}">
                        <td colspan="6" class="p-0 border-0">
                            <div class="p-3 border-bottom bg-white">
                                <div class="small text-muted mb-2">Muestras y ensayos</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 7rem;">Tipo</th>
                                                <th>Descripción</th>
                                                <th style="min-width: 12rem;">Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($detalleVentas as $linea)
                                            <tr>
                                                <td>{{ ($linea['tipo'] ?? '') === 'muestra' ? 'Muestra' : 'Ensayo' }}</td>
                                                <td>
                                                    {{ $linea['titulo'] ?? '-' }}
                                                    @if(!empty($linea['copia']))
                                                        <span class="text-muted small ms-1">({{ $linea['copia'] }})</span>
                                                    @endif
                                                </td>
                                                <td><small class="text-break">{{ $linea['estado'] ?? '-' }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @endforeach
                    
                    @if($cotizaciones->isEmpty())
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <x-heroicon-o-document-text class="me-2" style="width: 32px; height: 32px; color: #6c757d;" />
                            <p class="text-muted">No hay cotizaciones registradas</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($cotizaciones->hasPages())
        <div class="p-3 border-top">
            {{ $cotizaciones->links() }}
        </div>
        @endif
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Select2 en filtros (Cliente / Vendedor / Estado / Sucursal)
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
        return;
    }

    const $form = jQuery('#filterForm');
    const bindSubmitOnChange = ($el) => {
        if (!$el || !$el.length) return;
        $el.on('change', function () { $form.trigger('submit'); });
    };

    const commonOpts = {
        width: '100%',
        allowClear: true,
    };

    const $cliente = jQuery('.js-select2-cliente');
    if ($cliente.length) {
        $cliente.select2({ ...commonOpts, placeholder: $cliente.data('placeholder') || 'Todos los clientes' });
        bindSubmitOnChange($cliente);
    }

    const $vendedor = jQuery('.js-select2-vendedor');
    if ($vendedor.length) {
        $vendedor.select2({ ...commonOpts, placeholder: $vendedor.data('placeholder') || 'Todos los vendedores' });
        bindSubmitOnChange($vendedor);
    }

    const $estado = jQuery('.js-select2-estado');
    if ($estado.length) {
        $estado.select2({ ...commonOpts, placeholder: $estado.data('placeholder') || 'Todos los estados', minimumResultsForSearch: 0 });
        bindSubmitOnChange($estado);
    }

    const $sucursal = jQuery('.js-select2-sucursal');
    if ($sucursal.length && !$sucursal.prop('disabled')) {
        $sucursal.select2({ ...commonOpts, placeholder: $sucursal.data('placeholder') || 'Todas' });
        bindSubmitOnChange($sucursal);
    }
});

// Hacer toda la fila clickeable para desplegar/ocultar el detalle
document.addEventListener('click', function (e) {
    const row = e.target.closest('tr.ventas-row-clickable');
    if (!row) {
        return;
    }

    // No interceptar clicks en controles interactivos (acciones, links, inputs, etc.)
    const interactive = e.target.closest('a, button, input, select, textarea, label, .dropdown-menu, .btn');
    if (interactive) {
        return;
    }

    const btn = row.querySelector('.ventas-det-toggle');
    if (btn) {
        btn.click();
    }
});

// Función para filtrar por estado desde las tarjetas de estadísticas
function filtrarPorEstado(estado) {
    const url = new URL(window.location.href);
    const baseUrl = url.origin + url.pathname;
    
    // Construir parámetros de consulta manteniendo otros filtros
    const params = new URLSearchParams();
    
    // Mantener filtros existentes (excepto estado)
    if (url.searchParams.get('cliente')) {
        params.set('cliente', url.searchParams.get('cliente'));
    }
    if (url.searchParams.get('vendedor')) {
        params.set('vendedor', url.searchParams.get('vendedor'));
    }
    if (url.searchParams.get('sucursal')) {
        params.set('sucursal', url.searchParams.get('sucursal'));
    }
    if (url.searchParams.get('fecha_desde')) {
        params.set('fecha_desde', url.searchParams.get('fecha_desde'));
    }
    if (url.searchParams.get('fecha_hasta')) {
        params.set('fecha_hasta', url.searchParams.get('fecha_hasta'));
    }
    if (url.searchParams.get('search')) {
        params.set('search', url.searchParams.get('search'));
    }
    
    // Agregar o quitar el filtro de estado
    if (estado) {
        params.set('estado', estado);
    }
    
    // Construir URL final
    const finalUrl = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    
    // Redirigir
    window.location.href = finalUrl;
}

// Función para limpiar filtros
function limpiarFiltros() {
    window.location.href = '{{ route("ventas.index") }}';
}

// Búsqueda simple en tabla (filtra resultados visibles)
document.getElementById('searchInput').addEventListener('keyup', function() {
    const search = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Mostrar contador de resultados
function actualizarContador() {
    const filasVisibles = document.querySelectorAll('tbody tr:not([style*="display: none"])').length;
    const total = {{ $cotizaciones->count() }};
    // Puedes agregar un contador visual aquí si lo necesitas
}

// Llamar después de la búsqueda
document.getElementById('searchInput').addEventListener('keyup', actualizarContador);

// Función para confirmar eliminación con SweetAlert
function confirmarEliminacion(cotiNum) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción eliminará la cotización. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Crear formulario para enviar DELETE
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/ventas/${cotiNum}`;
            
            // Agregar token CSRF
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            // Agregar método DELETE
            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            form.appendChild(method);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function verRazonCancelacion(btn) {
    const reason = (btn?.getAttribute('data-reason') || '').toString().trim();
    Swal.fire({
        icon: 'info',
        title: 'Razones de cancelación',
        text: reason || 'Sin motivo informado.'
    });
}

// Notificaciones de sesión (para crear/editar)
@if(session('success'))
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: '{{ session("success") }}',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
@endif

@if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '{{ session("error") }}',
        confirmButtonColor: '#dc3545'
    });
@endif

@if(session('warning'))
    Swal.fire({
        icon: 'warning',
        title: 'Atención',
        text: '{{ session("warning") }}',
        confirmButtonColor: '#ffc107'
    });
@endif
</script>

@endsection