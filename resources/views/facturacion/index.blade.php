@extends('layouts.app')

@section('content')
@php
    $vistaActiva = $vista ?? 'pendientes';
    $hayFiltrosFacturas = $request->filled('estado') || $request->filled('cotizacion') || $request->filled('fecha_desde') || $request->filled('fecha_hasta');
@endphp

<div class="container py-4 facturacion-page">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold">Facturación</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary stats-card-facturacion {{ $vistaActiva === 'facturas' ? 'active' : '' }}"
                 data-vista="facturas" role="button" tabindex="0" aria-label="Ver facturas emitidas">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Facturas emitidas</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['total_facturas'], 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-document-text style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">${{ number_format($estadisticas['monto_facturado'], 2, ',', '.') }} facturado</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning stats-card-facturacion {{ $vistaActiva === 'pendientes' ? 'active' : '' }}"
                 data-vista="pendientes" role="button" tabindex="0" aria-label="Ver muestras por facturar">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Muestras por facturar</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['facturas_pendientes'], 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-clock style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">{{ $informesPorCotizacion->count() }} cotizaciones con pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">
                                {{ $vistaActiva === 'pendientes' ? 'Monto por facturar' : 'Monto facturado' }}
                            </h6>
                            <h3 class="card-text mb-0 fw-bold">
                                ${{ number_format($vistaActiva === 'pendientes' ? $estadisticas['monto_por_facturar'] : $estadisticas['monto_facturado'], 2, ',', '.') }}
                            </h3>
                        </div>
                        <x-heroicon-o-banknotes style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">
                        @if($vistaActiva === 'pendientes')
                            Total histórico facturado: ${{ number_format($estadisticas['monto_facturado'], 2, ',', '.') }}
                        @else
                            Pendiente de facturar: ${{ number_format($estadisticas['monto_por_facturar'], 2, ',', '.') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('facturacion.index') }}" id="filterFormFacturacion">
                <input type="hidden" name="vista" id="vistaInput" value="{{ $vistaActiva }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3 filtros-facturas {{ $vistaActiva === 'pendientes' ? 'd-none' : '' }}">
                        <label for="estado" class="form-label small fw-semibold text-muted">Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="pendiente" {{ $request->estado == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                            <option value="aprobada" {{ $request->estado == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                            <option value="rechazada" {{ $request->estado == 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                            <option value="anulada" {{ $request->estado == 'anulada' ? 'selected' : '' }}>Anulada</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="cotizacion" class="form-label small fw-semibold text-muted">Cotización</label>
                        <input type="number" name="cotizacion" id="cotizacion" class="form-control"
                               value="{{ $request->cotizacion }}" placeholder="Núm. cotización">
                    </div>
                    <div class="col-md-3 filtros-facturas {{ $vistaActiva === 'pendientes' ? 'd-none' : '' }}">
                        <label for="fecha_desde" class="form-label small fw-semibold text-muted">Fecha desde</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                               value="{{ $request->fecha_desde }}">
                    </div>
                    <div class="col-md-3 filtros-facturas {{ $vistaActiva === 'pendientes' ? 'd-none' : '' }}">
                        <label for="fecha_hasta" class="form-label small fw-semibold text-muted">Fecha hasta</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                               value="{{ $request->fecha_hasta }}">
                    </div>
                    <div class="col-md-auto ms-md-auto d-flex gap-2">
                        <a href="{{ route('facturacion.index', ['vista' => $vistaActiva]) }}"
                           class="btn btn-outline-secondary {{ $hayFiltrosFacturas || $request->filled('cotizacion') ? '' : 'd-none' }}"
                           id="btnLimpiarFiltros">
                            Limpiar
                        </a>
                        <button type="submit" class="btn btn-primary px-4">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Selector de workspace --}}
    <div class="fact-workspace-nav mb-4">
        <div class="fact-segmented" role="tablist" aria-label="Sección de facturación">
            <button type="button" class="fact-segmented__btn {{ $vistaActiva === 'pendientes' ? 'active' : '' }}"
                    data-vista="pendientes" role="tab" aria-selected="{{ $vistaActiva === 'pendientes' ? 'true' : 'false' }}">
                <x-heroicon-o-clipboard-document-list style="width: 18px; height: 18px;" />
                Por facturar
                @if($estadisticas['facturas_pendientes'] > 0)
                    <span class="badge rounded-pill bg-warning text-dark ms-1">{{ $estadisticas['facturas_pendientes'] }}</span>
                @endif
            </button>
            <button type="button" class="fact-segmented__btn {{ $vistaActiva === 'facturas' ? 'active' : '' }}"
                    data-vista="facturas" role="tab" aria-selected="{{ $vistaActiva === 'facturas' ? 'true' : 'false' }}">
                <x-heroicon-o-document-check style="width: 18px; height: 18px;" />
                Facturas emitidas
                @if($estadisticas['total_facturas'] > 0)
                    <span class="badge rounded-pill bg-primary ms-1">{{ $estadisticas['total_facturas'] }}</span>
                @endif
            </button>
        </div>
    </div>

    {{-- Workspace: Muestras por facturar --}}
    <div class="fact-workspace-panel {{ $vistaActiva === 'pendientes' ? '' : 'd-none' }}" id="panelPendientes" role="tabpanel">
        @if($informesPorCotizacion->isEmpty())
            <div class="fact-empty-state">
                <div class="fact-empty-state__icon">
                    <x-heroicon-o-check-badge style="width: 48px; height: 48px;" />
                </div>
                <h5 class="fw-semibold mb-2">No hay muestras pendientes</h5>
                <p class="text-muted mb-0">
                    @if($request->filled('cotizacion'))
                        No se encontraron muestras por facturar para la cotización #{{ $request->cotizacion }}.
                    @else
                        Todas las muestras con informe habilitado ya fueron facturadas.
                    @endif
                </p>
            </div>
        @else
            <div class="row g-3">
                @foreach($informesPorCotizacion as $numCoti => $informeData)
                    @php
                        $coti = $informeData['cotizacion'];
                        $muestras = $informeData['muestras'];
                        $matrizNombre = optional(optional($coti)->matriz)->matriz_descripcion ?? '—';
                    @endphp
                    <div class="col-12">
                        <article class="fact-coti-card">
                            <div class="fact-coti-card__main">
                                <div class="fact-coti-card__info">
                                    <span class="fact-coti-card__badge">#{{ $numCoti }}</span>
                                    <div>
                                        <h6 class="fact-coti-card__title mb-0">
                                            {{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}
                                        </h6>
                                        <p class="fact-coti-card__meta mb-0">
                                            {{ $matrizNombre }}
                                            · {{ $muestras->count() }} {{ $muestras->count() === 1 ? 'muestra' : 'muestras' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="fact-coti-card__actions">
                                    <button type="button" class="btn btn-sm btn-light fact-toggle-muestras"
                                            data-bs-toggle="collapse" data-bs-target="#muestras-{{ $numCoti }}"
                                            aria-expanded="false" aria-controls="muestras-{{ $numCoti }}">
                                        <x-heroicon-o-chevron-down style="width: 16px; height: 16px;" class="fact-chevron" />
                                        Detalle
                                    </button>
                                    <a href="{{ route('facturacion.facturar', ['cotizacion' => $numCoti]) }}"
                                       class="btn btn-sm btn-primary fact-btn-facturar">
                                        <x-heroicon-o-currency-dollar style="width: 16px; height: 16px;" />
                                        Facturar
                                    </a>
                                </div>
                            </div>
                            <div class="collapse" id="muestras-{{ $numCoti }}">
                                <div class="fact-coti-card__muestras">
                                    @foreach($muestras as $muestra)
                                        <div class="fact-muestra-row">
                                            <div class="fact-muestra-row__id">
                                                <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="text-muted" />
                                                <span>{{ $muestra->cotio_identificacion ?: '—' }}</span>
                                            </div>
                                            <div class="fact-muestra-row__desc">
                                                {{ $muestra->cotio_descripcion }}
                                                <span class="text-muted">#{{ $muestra->instance_number }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            @if($muestrasPagination->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $muestrasPagination->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Workspace: Facturas emitidas --}}
    <div class="fact-workspace-panel {{ $vistaActiva === 'facturas' ? '' : 'd-none' }}" id="panelFacturas" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fw-semibold">Facturas generadas</h5>
                    @if($hayFiltrosFacturas)
                        <small class="text-muted">{{ $facturas->total() }} resultado{{ $facturas->total() !== 1 ? 's' : '' }} con filtros aplicados</small>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#exportIvaModal">
                    <x-heroicon-o-document-chart-bar style="width: 18px; height: 18px;" class="me-1" />
                    Exportar IVA Ventas
                </button>
            </div>
            <div class="card-body p-0">
                @if($facturas->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fact-table">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Cotización</th>
                                    <th>Muestra</th>
                                    <th>Emisión</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center" style="width: 100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($facturas as $factura)
                                    @php
                                        $divisa = optional($factura->cotizacion)->divisa_codigo ?? 'PES';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $factura->numero_factura }}</div>
                                            <small class="text-muted">CAE {{ Str::limit($factura->cae, 14) }}</small>
                                        </td>
                                        <td>
                                            <div>{{ Str::limit($factura->cliente_razon_social ?? 'N/A', 35) }}</div>
                                            <small class="text-muted">{{ $factura->cliente_cuit ?? '—' }}</small>
                                        </td>
                                        <td>
                                            @if($factura->cotizacion)
                                                <a href="{{ route('facturacion.show', $factura->cotizacion_id) }}"
                                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                                    #{{ $factura->cotizacion_id }}
                                                </a>
                                            @else
                                                <span class="text-muted">#{{ $factura->cotizacion_id }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 160px;"
                                                  title="{{ $factura->cotio_descripcion }}">
                                                {{ $factura->cotio_descripcion ?? 'N/A' }}
                                            </span>
                                            @if($factura->instance_number)
                                                <small class="text-muted">#{{ $factura->instance_number }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $factura->fecha_emision->format('d/m/Y') }}</div>
                                            <small class="text-muted">{{ $factura->fecha_emision->format('H:i') }}</small>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-semibold">{{ $divisa }} {{ number_format($factura->monto_total, 2, ',', '.') }}</span>
                                        </td>
                                        <td>
                                            <div class="fact-table-actions">
                                                <a href="{{ route('facturacion.ver', $factura->id) }}"
                                                   class="btn btn-sm btn-light" title="Ver detalle">
                                                    <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                                </a>
                                                <a href="{{ route('facturacion.descargar', $factura->id) }}"
                                                   class="btn btn-sm btn-light descargar-pdf {{ $factura->pdf_url ? '' : 'text-muted' }}"
                                                   title="Descargar PDF"
                                                   data-factura-id="{{ $factura->id }}"
                                                   target="_blank">
                                                    <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">
                        {{ $facturas->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="fact-empty-state py-5">
                        <div class="fact-empty-state__icon">
                            <x-heroicon-o-document style="width: 48px; height: 48px;" />
                        </div>
                        <h5 class="fw-semibold mb-2">Sin facturas</h5>
                        <p class="text-muted mb-3">
                            @if($hayFiltrosFacturas)
                                No hay facturas que coincidan con los filtros seleccionados.
                            @else
                                Aún no se emitieron facturas en el sistema.
                            @endif
                        </p>
                        @if($estadisticas['facturas_pendientes'] > 0)
                            <button type="button" class="btn btn-primary btn-sm" data-vista="pendientes">
                                Ver {{ $estadisticas['facturas_pendientes'] }} muestras por facturar
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .facturacion-page {
        --fact-radius: 12px;
        --fact-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }

    .stats-card-facturacion {
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 2px solid transparent;
        border-radius: var(--fact-radius);
    }

    .stats-card-facturacion:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .stats-card-facturacion.active {
        border-color: rgba(255, 255, 255, 0.85);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .fact-segmented {
        display: inline-flex;
        background: #fff;
        border-radius: 999px;
        padding: 4px;
        box-shadow: var(--fact-shadow);
        border: 1px solid #e9ecef;
        gap: 4px;
    }

    .fact-segmented__btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        background: transparent;
        padding: 10px 20px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #6c757d;
        transition: all 0.2s ease;
    }

    .fact-segmented__btn:hover {
        color: #495057;
        background: #f8f9fa;
    }

    .fact-segmented__btn.active {
        background: var(--primary-color, #4e73df);
        color: #fff;
        box-shadow: 0 2px 8px rgba(78, 115, 223, 0.35);
    }

    .fact-segmented__btn.active .badge {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #fff !important;
    }

    .fact-coti-card {
        background: #fff;
        border-radius: var(--fact-radius);
        box-shadow: var(--fact-shadow);
        border: 1px solid #eef0f3;
        overflow: hidden;
        transition: box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .fact-coti-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border-color: #dee2e6;
    }

    .fact-coti-card__main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        flex-wrap: wrap;
    }

    .fact-coti-card__info {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .fact-coti-card__badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 52px;
        padding: 6px 10px;
        background: #eef2ff;
        color: var(--primary-color, #4e73df);
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .fact-coti-card__title {
        font-weight: 600;
        font-size: 1rem;
    }

    .fact-coti-card__meta {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 2px;
    }

    .fact-coti-card__actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    .fact-coti-card__muestras {
        border-top: 1px solid #eef0f3;
        background: #f8f9fc;
        padding: 0.5rem 1.25rem 1rem;
    }

    .fact-muestra-row {
        display: grid;
        grid-template-columns: minmax(120px, 180px) 1fr;
        gap: 1rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid #eef0f3;
        font-size: 0.9rem;
    }

    .fact-muestra-row:last-child {
        border-bottom: none;
    }

    .fact-muestra-row__id {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
        color: #495057;
    }

    .fact-muestra-row__desc {
        color: #212529;
    }

    .fact-toggle-muestras[aria-expanded="true"] .fact-chevron {
        transform: rotate(180deg);
    }

    .fact-chevron {
        transition: transform 0.2s ease;
    }

    .fact-empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        background: #fff;
        border-radius: var(--fact-radius);
        box-shadow: var(--fact-shadow);
        border: 1px dashed #dee2e6;
    }

    .fact-empty-state__icon {
        color: #adb5bd;
        margin-bottom: 1rem;
    }

    .fact-table thead th {
        background: #f8f9fc;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #6c757d;
        font-weight: 600;
        border-bottom-width: 1px;
        padding: 0.85rem 1rem;
    }

    .fact-table tbody td {
        padding: 0.85rem 1rem;
        vertical-align: middle;
    }

    .fact-table-actions {
        display: flex;
        justify-content: center;
        gap: 4px;
    }

    @media (max-width: 768px) {
        .fact-coti-card__main {
            flex-direction: column;
            align-items: stretch;
        }

        .fact-coti-card__actions {
            justify-content: flex-end;
        }

        .fact-muestra-row {
            grid-template-columns: 1fr;
            gap: 0.25rem;
        }

        .fact-segmented {
            display: flex;
            width: 100%;
        }

        .fact-segmented__btn {
            flex: 1;
            justify-content: center;
            padding: 10px 12px;
            font-size: 0.82rem;
        }
    }
</style>

<script>
function logFacturacionRequest(payload) {
    try {
        console.log('[facturacion-api] request', JSON.stringify(payload, null, 2));
    } catch (e) {
        console.log('[facturacion-api] request', payload);
    }
}

function cambiarVistaFacturacion(vista) {
    const url = new URL(window.location.href);
    url.searchParams.set('vista', vista);
    url.searchParams.delete('tipo_filtro');
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

document.querySelectorAll('[data-vista]').forEach(el => {
    el.addEventListener('click', function () {
        const vista = this.getAttribute('data-vista');
        if (vista) {
            cambiarVistaFacturacion(vista);
        }
    });
    el.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const vista = this.getAttribute('data-vista');
            if (vista) {
                cambiarVistaFacturacion(vista);
            }
        }
    });
});

document.querySelectorAll('.descargar-pdf').forEach(link => {
    link.addEventListener('click', function () {
        const href = this.getAttribute('href');
        const facturaId = this.getAttribute('data-factura-id');

        logFacturacionRequest({
            action: 'facturacion.descargar',
            method: 'GET',
            url: href,
            params: { factura_id: facturaId ? Number(facturaId) : null },
            timestamp: new Date().toISOString(),
        });

        const icon = this.querySelector('svg');
        if (icon) icon.style.opacity = '0.5';
        setTimeout(() => { if (icon) icon.style.opacity = '1'; }, 3000);
    });
});

document.querySelectorAll('.fact-btn-facturar').forEach(link => {
    link.addEventListener('click', function () {
        const href = this.getAttribute('href');
        if (!href) return;
        try {
            const url = new URL(href, window.location.origin);
            logFacturacionRequest({
                action: 'facturacion.facturar',
                method: 'GET',
                url: url.toString(),
                params: { cotizacion: Number(url.pathname.split('/').pop()) },
                timestamp: new Date().toISOString(),
            });
        } catch (e) {
            logFacturacionRequest({ action: 'facturacion.facturar', method: 'GET', url: href, params: {}, timestamp: new Date().toISOString() });
        }
    });
});
</script>

<!-- Modal Exportar IVA Ventas -->
<div class="modal fade" id="exportIvaModal" tabindex="-1" aria-labelledby="exportIvaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="exportIvaModalLabel">Exportar IVA Ventas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('facturacion.exportar-iva') }}" method="GET">
                <div class="modal-body">
                    <p class="text-muted small mb-4">Seleccione el rango de fechas para el reporte de IVA Ventas en formato Excel.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="exp_fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="exp_fecha_desde" class="form-control"
                                   value="{{ now()->startOfMonth()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="exp_fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="exp_fecha_hasta" class="form-control"
                                   value="{{ now()->endOfMonth()->format('Y-m-d') }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        Generar Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
