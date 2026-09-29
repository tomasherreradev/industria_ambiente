@extends('layouts.app')

@section('content')
<div class="container py-4 facturacion-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-0 fw-bold">Revisión de facturación</h2>
            <p class="text-muted mb-0 mt-1 small">
                Revise cada muestra, cargue orden de compra, remito y demás referencias de facturación por cotización, y apruebe para que pase a facturación.
            </p>
        </div>
        <a href="{{ route('facturacion.index') }}" class="btn btn-outline-secondary btn-sm">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" />
            Ir a facturación
        </a>
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

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-warning stats-card-facturacion active">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Pendientes de revisión</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($pendientesTotal, 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-clipboard-document-check style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">{{ $muestrasPorCotizacion->count() }} cotizaciones en pantalla</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h5 class="mb-0 fw-semibold">Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('facturacion-revision.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="cotizacion" class="form-label small fw-semibold text-muted">Cotización</label>
                    <input type="number" name="cotizacion" id="cotizacion" class="form-control"
                           value="{{ $request->cotizacion }}" placeholder="Núm. cotización">
                </div>
                <div class="col-md-3">
                    <label for="canal" class="form-label small fw-semibold text-muted">Canal</label>
                    <select name="canal" id="canal" class="form-select">
                        <option value="">Todos</option>
                        <option value="laboratorio" @selected($request->canal === 'laboratorio')>Laboratorio</option>
                        <option value="mediciones" @selected($request->canal === 'mediciones')>Mediciones</option>
                        <option value="consultoria" @selected($request->canal === 'consultoria')>Consultoría</option>
                        <option value="asp" @selected($request->canal === 'asp')>ASP</option>
                        <option value="clarke_fire" @selected($request->canal === 'clarke_fire')>Clarke Fire</option>
                    </select>
                </div>
                <div class="col-md-auto ms-md-auto d-flex gap-2">
                    @if($request->filled('cotizacion') || $request->filled('canal'))
                        <a href="{{ route('facturacion-revision.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    @endif
                    <button type="submit" class="btn btn-primary px-4">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    @if($muestrasPorCotizacion->isEmpty())
        <div class="fact-empty-state">
            <div class="fact-empty-state__icon">
                <x-heroicon-o-check-badge style="width: 48px; height: 48px;" />
            </div>
            <h5 class="fw-semibold mb-2">No hay muestras pendientes de revisión</h5>
            <p class="text-muted mb-0">
                @if($request->filled('cotizacion'))
                    No se encontraron muestras para la cotización #{{ $request->cotizacion }}.
                @else
                    Las muestras aparecerán aquí al pasar a informes.
                @endif
            </p>
        </div>
    @else
        <div class="row g-3">
            @foreach($muestrasPorCotizacion as $numCoti => $grupo)
                @php
                    $coti = $grupo['cotizacion'];
                    $muestras = $grupo['muestras'];
                    $refsFacturacion = $grupo['refs_facturacion'] ?? [];
                    $matrizNombre = optional(optional($coti)->matriz)->matriz_descripcion ?? '—';
                @endphp
                <div class="col-12">
                    <article class="fact-coti-card">
                        <div class="fact-coti-card__main">
                            <div class="fact-coti-card__info">
                                <span class="fact-coti-card__badge">{{ etiquetaNumeroCotizacion($numCoti) }}</span>
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
                                <button type="button" class="btn btn-sm btn-light fact-toggle-muestras collapsed"
                                        data-bs-toggle="collapse" data-bs-target="#muestras-rev-{{ $numCoti }}"
                                        aria-expanded="false">
                                    <x-heroicon-o-chevron-down style="width: 16px; height: 16px;" class="fact-chevron" />
                                    Detalle
                                </button>
                                <form method="POST" action="{{ route('facturacion-revision.aprobar-cotizacion', $numCoti) }}" class="d-inline js-aprobar-facturacion"
                                      data-swal-title="Aprobar cotización"
                                      data-swal-html="¿Aprobar <strong>todas las muestras</strong> de la cotización <strong>#{{ $numCoti }}</strong> para facturación?">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <x-heroicon-o-check-circle style="width: 16px; height: 16px;" />
                                        Aprobar cotización
                                    </button>
                                </form>
                            </div>
                        </div>
                        @if($coti)
                            @include('facturacion.partials.referencias-facturacion-editable', [
                                'refsFacturacion' => $refsFacturacion,
                                'cotiNum' => $numCoti,
                                'updateUrl' => route('facturacion-revision.update-referencias', ['cotio_numcoti' => $numCoti]),
                                'suffix' => 'rev-' . $numCoti,
                                'layout' => 'compact',
                                'swalAlGuardar' => true,
                            ])
                        @endif
                        <div class="collapse" id="muestras-rev-{{ $numCoti }}">
                            <div class="fact-coti-card__muestras">
                                @foreach($muestras as $muestra)
                                    <div class="fact-muestra-row fact-muestra-row--revision">
                                        <div class="fact-muestra-row__id">
                                            <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="text-muted" />
                                            <span>{{ $muestra->cotio_identificacion ?: '—' }}</span>
                                        </div>
                                        <div class="fact-muestra-row__desc">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                <div>
                                                    <span class="fw-medium">{{ $muestra->cotio_descripcion }}</span>
                                                    <span class="text-muted">#{{ $muestra->instance_number }}</span>
                                                    <span class="badge bg-light text-dark border ms-1">{{ $muestra->canal_etiqueta }}</span>
                                                    @if($muestra->aprobado_informe)
                                                        <span class="badge bg-success ms-1">Informe OK</span>
                                                    @else
                                                        <span class="badge bg-warning text-dark ms-1">Informe pend.</span>
                                                    @endif
                                                    @if($muestra->otn)
                                                        <span class="badge bg-secondary ms-1">OT {{ $muestra->otn }}</span>
                                                    @endif
                                                </div>
                                                <div class="d-flex gap-2 flex-shrink-0">
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-primary btn-ver-resumen"
                                                            data-url="{{ route('facturacion-revision.resumen', $muestra) }}"
                                                            data-titulo="{{ $muestra->cotio_descripcion }} — Inst. {{ $muestra->instance_number }}">
                                                        <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                                        Ver resumen
                                                    </button>
                                                    <form method="POST" action="{{ route('facturacion-revision.aprobar', $muestra) }}" class="d-inline js-aprobar-facturacion"
                                                          data-swal-title="Aprobar muestra"
                                                          data-swal-html="¿Aprobar la muestra <strong>{{ $muestra->cotio_descripcion }}</strong> (inst. {{ $muestra->instance_number }}) para facturación?">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-primary">Aprobar</button>
                                                    </form>
                                                </div>
                                            </div>
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

<div class="modal fade" id="resumenRevisionModal" tabindex="-1" aria-labelledby="resumenRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="resumenRevisionModalLabel">Resumen de muestra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="resumenRevisionModalBody">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2 mb-0">Cargando resumen…</p>
                </div>
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
        border-radius: var(--fact-radius);
        border: 2px solid transparent;
    }

    .stats-card-facturacion.active {
        border-color: rgba(255, 255, 255, 0.85);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    .fact-coti-card {
        background: #fff;
        border-radius: var(--fact-radius);
        box-shadow: var(--fact-shadow);
        border: 1px solid #eef0f3;
        overflow: hidden;
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
        min-width: 0;
        max-width: 100%;
        padding: 6px 10px;
        background: #eef2ff;
        color: var(--primary-color, #4e73df);
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.72rem;
        line-height: 1.2;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fact-coti-card__title { font-weight: 600; font-size: 1rem; }
    .fact-coti-card__meta { font-size: 0.85rem; color: #6c757d; margin-top: 2px; }
    .fact-coti-card__actions { display: flex; gap: 8px; flex-shrink: 0; }

    .fact-coti-card__muestras {
        border-top: 1px solid #eef0f3;
        background: #f8f9fc;
        padding: 0.5rem 1.25rem 1rem;
    }

    .fact-muestra-row {
        display: grid;
        grid-template-columns: minmax(120px, 180px) 1fr;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eef0f3;
        font-size: 0.9rem;
    }

    .fact-muestra-row:last-child { border-bottom: none; }

    .fact-muestra-row__id {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        font-weight: 500;
        color: #495057;
    }

    .fact-toggle-muestras[aria-expanded="false"] .fact-chevron { transform: rotate(-90deg); }
    .fact-chevron { transition: transform 0.2s ease; }

    .fact-empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        background: #fff;
        border-radius: var(--fact-radius);
        box-shadow: var(--fact-shadow);
        border: 1px dashed #dee2e6;
    }

    .fact-empty-state__icon { color: #adb5bd; margin-bottom: 1rem; }

    @media (max-width: 768px) {
        .fact-coti-card__main { flex-direction: column; align-items: stretch; }
        .fact-coti-card__actions { justify-content: flex-end; }
        .fact-muestra-row { grid-template-columns: 1fr; gap: 0.5rem; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('resumenRevisionModal');
    const modalBody = document.getElementById('resumenRevisionModalBody');
    const modalTitle = document.getElementById('resumenRevisionModalLabel');
    if (!modalEl || !modalBody) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.querySelectorAll('.btn-ver-resumen').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const url = btn.dataset.url;
            const titulo = btn.dataset.titulo || 'Resumen de muestra';
            modalTitle.textContent = titulo;
            modalBody.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Cargando resumen…</p></div>';
            modal.show();

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    },
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('No se pudo cargar el resumen.');
                }

                modalBody.innerHTML = await response.text();
            } catch (error) {
                modalBody.innerHTML = '<div class="alert alert-danger mb-0">' + (error.message || 'Error al cargar.') + '</div>';
            }
        });
    });

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest('.js-aprobar-facturacion');
        if (!form || form.dataset.swalConfirmed === '1') {
            return;
        }

        event.preventDefault();

        if (typeof Swal === 'undefined') {
            if (window.confirm(form.dataset.swalHtml?.replace(/<[^>]*>/g, '') || '¿Confirmar aprobación?')) {
                form.dataset.swalConfirmed = '1';
                form.submit();
            }
            return;
        }

        const result = await Swal.fire({
            title: form.dataset.swalTitle || 'Confirmar aprobación',
            html: form.dataset.swalHtml || '¿Aprobar para facturación?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754',
            reverseButtons: true,
            focusCancel: true,
        });

        if (result.isConfirmed) {
            form.dataset.swalConfirmed = '1';
            form.submit();
        }
    });
});
</script>
@endsection
