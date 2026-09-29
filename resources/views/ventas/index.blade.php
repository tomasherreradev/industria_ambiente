@extends('layouts.app')

@section('title', 'Cotizaciones')

@section('content')

<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">
<link rel="stylesheet" href="{{ asset('css/ventas-page.css') }}?v={{ filemtime(public_path('css/ventas-page.css')) }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

@php
    $hayFiltrosVentas = request()->hasAny(['cliente', 'vendedor', 'sucursal', 'estado', 'fecha_desde', 'fecha_hasta', 'search']);
@endphp

<div class="container-fluid px-3 px-lg-4 py-4 ventas-page ucrud ucrud-layout--fluid" data-ucrud-root>

@if(!empty($canalVistaVentas))
    <div class="alert alert-info border-0 shadow-sm mb-3" role="status">
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

    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Cotizaciones
                <span class="ucrud-count">{{ number_format($totalCotizaciones) }}</span>
            </h1>
            <p class="ucrud-subtitle">Gestión de presupuestos, estados y montos</p>
        </div>
        <div class="ucrud-header__actions">
            <a href="{{ route('ventas.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Nueva cotización
            </a>
        </div>
    </header>

    <div class="ventas-stats-strip">
        @php
            $kpiVentas = [
                ['key' => '', 'label' => 'Total', 'value' => $totalCotizaciones, 'mod' => 'total', 'icon' => 'document-text'],
                ['key' => 'E', 'label' => 'En espera', 'value' => $enEspera, 'mod' => 'espera', 'icon' => 'clock'],
                ['key' => 'A', 'label' => 'Aprobadas', 'value' => $aprobadas, 'mod' => 'aprobada', 'icon' => 'check-circle'],
                ['key' => 'R', 'label' => 'Rechazadas', 'value' => $rechazadas, 'mod' => 'rechazada', 'icon' => 'x-circle'],
                ['key' => '_PROCESO', 'label' => 'En proceso', 'value' => $procesoDeriv, 'mod' => 'proceso', 'icon' => 'arrow-path'],
                ['key' => 'S', 'label' => 'Suspendidas', 'value' => $suspendidas, 'mod' => 'suspendida', 'icon' => 'pause-circle'],
                ['key' => '_CERRADA', 'label' => 'Cerradas', 'value' => $cerradasDeriv, 'mod' => 'cerrada', 'icon' => 'flag'],
            ];
        @endphp
        @foreach($kpiVentas as $kpi)
            @php
                $estadoReq = request('estado');
                $activo = ($kpi['key'] === '' && !$estadoReq) || ($estadoReq === $kpi['key']);
            @endphp
            <div class="ventas-stats-metric">
                <div class="stats-card-ventas stats-card-ventas--{{ $kpi['mod'] }} h-100 {{ $activo ? 'active' : '' }}"
                     onclick="filtrarPorEstado('{{ $kpi['key'] }}')"
                     role="button" tabindex="0" aria-label="Filtrar {{ $kpi['label'] }}">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="stats-card-ventas__label">{{ $kpi['label'] }}</div>
                                <p class="stats-card-ventas__value mb-0">{{ number_format($kpi['value']) }}</p>
                            </div>
                            @switch($kpi['icon'])
                                @case('document-text') <x-heroicon-o-document-text class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @case('clock') <x-heroicon-o-clock class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @case('check-circle') <x-heroicon-o-check-circle class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @case('x-circle') <x-heroicon-o-x-circle class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @case('arrow-path') <x-heroicon-o-arrow-path class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @case('pause-circle') <x-heroicon-o-pause-circle class="stats-card-ventas__icon" style="width: 24px; height: 24px;" /> @break
                                @default <x-heroicon-o-flag class="stats-card-ventas__icon" style="width: 24px; height: 24px;" />
                            @endswitch
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="ventas-stats-monto-wrap">
            <div class="stats-card-ventas stats-card-ventas--static stats-card-ventas--monto h-100">
                <div class="card-body py-3 px-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="stats-card-ventas__label">Monto total</div>
                            <p class="stats-card-ventas__value mb-0">${{ number_format($montoMostrar, 2, ',', '.') }}</p>
                        </div>
                        <x-heroicon-o-banknotes class="stats-card-ventas__icon" style="width: 24px; height: 24px;" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ucrud-filters mb-4">
        <p class="ucrud-filters__title">
            Filtros
            @if($hayFiltrosVentas)
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2">Activos</span>
            @endif
        </p>
        <form method="GET" action="{{ route('ventas.index') }}" id="filterForm">
            <div class="row g-3 ventas-filters-row ventas-filters-row--primary">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-cliente" class="form-label">Cliente</label>
                    <select id="filtro-cliente" name="cliente" class="form-select js-select2-cliente" data-placeholder="Todos los clientes">
                        <option value="">Todos los clientes</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->cli_codigo }}" {{ request('cliente') == $cliente->cli_codigo ? 'selected' : '' }}>
                                {{ trim($cliente->cli_codigo) }} - {{ trim($cliente->cli_razonsocial) }}
                            </option>
                        @endforeach
                    </select>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-vendedor" class="form-label">Vendedor</label>
                    <select id="filtro-vendedor" name="vendedor" class="form-select js-select2-vendedor" data-placeholder="Todos los vendedores">
                        <option value="">Todos los vendedores</option>
                        @foreach(($vendedores ?? []) as $vendedor)
                            <option value="{{ trim((string) $vendedor->usu_codigo) }}" {{ request('vendedor') == trim((string) $vendedor->usu_codigo) ? 'selected' : '' }}>
                                {{ trim($vendedor->usu_codigo) }} - {{ trim($vendedor->usu_descripcion ?? '') }}
                            </option>
                        @endforeach
                    </select>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-sucursal" class="form-label" title="{{ request('cliente') ? '' : 'Elegí un cliente para ver sucursales.' }}">
                        Sucursal
                        @if(!request('cliente'))
                            <span class="ventas-filter-label-hint">(elegí cliente)</span>
                        @endif
                    </label>
                    <select id="filtro-sucursal" name="sucursal" class="form-select js-select2-sucursal" data-placeholder="Todas" onchange="this.form.submit()" {{ request('cliente') ? '' : 'disabled' }}>
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
                    </div>
                </div>

                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-estado" class="form-label">Estado</label>
                    <select id="filtro-estado" name="estado" class="form-select js-select2-estado" data-placeholder="Todos los estados">
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
                </div>
            </div>

            <div class="row g-3 ventas-filters-row ventas-filters-row--secondary align-items-end">
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-fecha-desde" class="form-label">Fecha desde</label>
                    <input type="date" id="filtro-fecha-desde" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}" onchange="this.form.submit()">
                    </div>
                </div>

                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                    <div class="ventas-filter-field">
                    <label for="filtro-fecha-hasta" class="form-label">Fecha hasta</label>
                    <input type="date" id="filtro-fecha-hasta" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}" onchange="this.form.submit()">
                    </div>
                </div>

                <div class="col-xl col-lg col-md-8">
                    <div class="ventas-filter-field">
                    <label for="searchInput" class="form-label">Buscar</label>
                    <input type="text" id="searchInput" name="search" class="form-control"
                           placeholder="Nº cotización, empresa…" value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-xl-auto col-lg-auto col-md-4 ms-xl-auto d-flex gap-2 flex-wrap justify-content-md-end pb-1">
                    @if($hayFiltrosVentas)
                        <button type="button" onclick="limpiarFiltros()" class="ucrud-btn ucrud-btn--ghost">Limpiar</button>
                    @endif
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
                </div>
            </div>
        </form>
    </div>

    <div class="ventas-table-panel ucrud-panel">
        <div class="ventas-table-panel__head d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="ventas-table-panel__title">Listado de cotizaciones</h2>
            <span class="text-muted small">{{ $cotizaciones->total() }} resultado(s)</span>
        </div>
        <div class="table-responsive ucrud-tablewrap">
            <table class="ucrud-table ucrud-table--sticky-actions mb-0">
                <thead>
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
                    <tr class="{{ ($esAprobadaRow && count($detalleVentas) > 0) ? 'ventas-row-clickable' : '' }}">
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
                        <td>
                            <div class="ucrud-actions">
                                <a href="{{ route('cotizaciones.ver-detalle', $cotizacion->coti_num) }}" class="ucrud-iconbtn" title="Ver detalle" aria-label="Ver detalle">
                                    <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                </a>
                                <a href="{{ route('ventas.edit', $cotizacion->coti_num) }}" class="ucrud-iconbtn" title="Editar" aria-label="Editar">
                                    <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                </a>
                                @php
                                    $estaAprobadaRow = !$cotizacion->cancelada && !empty($cotizacion->coti_estado) && strtoupper(trim((string) $cotizacion->coti_estado))[0] === 'A';
                                @endphp
                                @if($estaAprobadaRow)
                                    <button type="button"
                                       class="ucrud-iconbtn ucrud-iconbtn--danger"
                                       title="No se puede eliminar una cotización aprobada"
                                       aria-label="Eliminar (no permitido)"
                                       disabled>
                                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                    </button>
                                @else
                                    <button type="button"
                                       class="ucrud-iconbtn ucrud-iconbtn--danger"
                                       onclick="confirmarEliminacion({{ $cotizacion->coti_num }})"
                                       title="Eliminar"
                                       aria-label="Eliminar">
                                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                    </button>
                                @endif
                            </div>
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
                        <td colspan="6" class="text-center py-5">
                            <div class="ucrud-empty py-3">
                                <div class="ucrud-empty__icon mx-auto mb-2">
                                    <x-heroicon-o-document-text style="width: 28px; height: 28px;" />
                                </div>
                                <p class="ucrud-empty__title mb-1">No hay cotizaciones</p>
                                <p class="ucrud-empty__text mb-0">{{ $hayFiltrosVentas ? 'Probá ajustar los filtros.' : 'Creá la primera cotización.' }}</p>
                            </div>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($cotizaciones->hasPages())
        <div class="p-3 border-top bg-white">
            {{ $cotizaciones->links() }}
        </div>
        @endif
    </div>
</div>{{-- .ventas-page --}}

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
    if ($sucursal.length) {
        $sucursal.select2({ ...commonOpts, placeholder: $sucursal.data('placeholder') || 'Todas' });
        if (!$sucursal.prop('disabled')) {
            bindSubmitOnChange($sucursal);
        }
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

let searchSubmitTimer = null;
const searchInput = document.getElementById('searchInput');
const filterForm = document.getElementById('filterForm');

searchInput.addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        filterForm.submit();
    }
});

searchInput.addEventListener('input', function() {
    clearTimeout(searchSubmitTimer);
    searchSubmitTimer = setTimeout(function() {
        filterForm.submit();
    }, 500);
});

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