@extends('layouts.app')

@section('title', 'Informes de Muestras')

@section('content')
<link rel="stylesheet" href="{{ asset('css/informes-index.css') }}?v={{ filemtime(public_path('css/informes-index.css')) }}">

@php
    $vistaActiva = $vistaActiva ?? 'pendientes';
    $estadisticas = $estadisticas ?? [
        'pendientes_firma' => 0,
        'firmados' => 0,
        'total' => 0,
        'cotizaciones_pendientes' => 0,
    ];
    $viewType = $viewType ?? 'lista';
    $hayFiltros = request()->filled('search')
        || request()->filled('tipo_informe')
        || request()->filled('matriz')
        || request()->filled('fecha_inicio')
        || request()->filled('fecha_fin');

    $informesViewQuery = array_merge(
        request()->except(['view', 'page']),
        ['view' => $viewType, 'vista' => $vistaActiva]
    );

    $datosVista = $viewType === 'calendario'
        ? ($events ?? collect())
        : ($informesPorCotizacion ?? collect());
@endphp

<div class="container py-4 informes-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="inf-title mb-1">Informes de Muestras</h1>
            <p class="inf-subtitle mb-0">
                @if($vistaActiva === 'pendientes')
                    Bandeja de ingreso — informes pendientes de firma digital
                @elseif($vistaActiva === 'firmados')
                    Informes ya firmados y disponibles para descarga
                @else
                    Todos los informes aprobados del sistema
                @endif
            </p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            @if(userCanEditInformeProtocoloPdf())
                <a href="{{ route('informes.notas.index') }}" class="btn btn-sm btn-outline-secondary" title="Notas reutilizables para informes">
                    <x-heroicon-o-document-text style="width: 16px; height: 16px;" class="me-1"/>
                    Notas
                </a>
            @endif

            <div class="inf-view-switch" role="group" aria-label="Tipo de vista">
                <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'lista'])) }}"
                   class="inf-view-switch__btn {{ $viewType === 'lista' ? 'active' : '' }}"
                   title="Lista">
                    <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'calendario'])) }}"
                   class="inf-view-switch__btn {{ $viewType === 'calendario' ? 'active' : '' }}"
                   title="Calendario">
                    <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'documento'])) }}"
                   class="inf-view-switch__btn {{ $viewType === 'documento' ? 'active' : '' }}"
                   title="Documento">
                    <x-heroicon-o-document style="width: 20px; height: 20px;" />
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-warning stats-card-informes {{ $vistaActiva === 'pendientes' ? 'active' : '' }}"
                 data-vista="pendientes" role="button" tabindex="0" aria-label="Ver pendientes de firma">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Pendientes de firma</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['pendientes_firma'], 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-pencil-square style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">{{ $estadisticas['cotizaciones_pendientes'] }} cotizaciones con pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success stats-card-informes {{ $vistaActiva === 'firmados' ? 'active' : '' }}"
                 data-vista="firmados" role="button" tabindex="0" aria-label="Ver informes firmados">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Firmados</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['firmados'], 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-check-badge style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">Listos para entrega al cliente</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-primary stats-card-informes {{ $vistaActiva === 'todos' ? 'active' : '' }}"
                 data-vista="todos" role="button" tabindex="0" aria-label="Ver todos los informes">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1 opacity-75">Total informes</h6>
                            <h3 class="card-text mb-0 fw-bold">{{ number_format($estadisticas['total'], 0, ',', '.') }}</h3>
                        </div>
                        <x-heroicon-o-document-text style="width: 28px; height: 28px;" class="opacity-50" />
                    </div>
                    <small class="opacity-75">Aprobados y habilitados para informe</small>
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
            <form method="GET" action="{{ route('informes.index') }}" id="filterFormInformes">
                <input type="hidden" name="view" value="{{ $viewType }}">
                <input type="hidden" name="vista" id="vistaInputInformes" value="{{ $vistaActiva }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label small fw-semibold text-muted">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search"
                               placeholder="Número, empresa o establecimiento"
                               value="{{ request('search') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="tipo_informe" class="form-label small fw-semibold text-muted">Tipo</label>
                        <select class="form-select" id="tipo_informe" name="tipo_informe">
                            <option value="">Todos</option>
                            <option value="final" @selected(request('tipo_informe') === 'final')>Final</option>
                            <option value="parcial" @selected(request('tipo_informe') === 'parcial')>Parcial</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="matriz" class="form-label small fw-semibold text-muted">Matriz</label>
                        <select class="form-select" id="matriz" name="matriz">
                            <option value="">Todas</option>
                            @foreach($matrices as $matriz)
                                <option value="{{ $matriz->matriz_codigo }}" @selected(request('matriz') == $matriz->matriz_codigo)>
                                    {{ $matriz->matriz_descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_inicio" class="form-label small fw-semibold text-muted">Desde</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="{{ request('fecha_inicio') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_fin" class="form-label small fw-semibold text-muted">Hasta</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="{{ request('fecha_fin') }}">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        @if($hayFiltros)
                            <a href="{{ route('informes.index', ['view' => $viewType, 'vista' => $vistaActiva]) }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        @endif
                        <button type="submit" class="btn btn-primary px-4">Filtrar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Selector de bandeja --}}
    @if(in_array($viewType, ['lista', 'documento'], true))
        <div class="mb-4">
            <div class="inf-segmented" role="tablist" aria-label="Bandeja de informes">
                <button type="button" class="inf-segmented__btn {{ $vistaActiva === 'pendientes' ? 'active' : '' }}"
                        data-vista="pendientes" role="tab" aria-selected="{{ $vistaActiva === 'pendientes' ? 'true' : 'false' }}">
                    <x-heroicon-o-inbox style="width: 18px; height: 18px;" />
                    Bandeja de ingreso
                    @if($estadisticas['pendientes_firma'] > 0)
                        <span class="badge rounded-pill bg-warning text-dark ms-1">{{ $estadisticas['pendientes_firma'] }}</span>
                    @endif
                </button>
                <button type="button" class="inf-segmented__btn {{ $vistaActiva === 'firmados' ? 'active' : '' }}"
                        data-vista="firmados" role="tab" aria-selected="{{ $vistaActiva === 'firmados' ? 'true' : 'false' }}">
                    <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
                    Firmados
                    @if($estadisticas['firmados'] > 0)
                        <span class="badge rounded-pill bg-success ms-1">{{ $estadisticas['firmados'] }}</span>
                    @endif
                </button>
                <button type="button" class="inf-segmented__btn {{ $vistaActiva === 'todos' ? 'active' : '' }}"
                        data-vista="todos" role="tab" aria-selected="{{ $vistaActiva === 'todos' ? 'true' : 'false' }}">
                    <x-heroicon-o-queue-list style="width: 18px; height: 18px;" />
                    Todos
                </button>
            </div>
        </div>
    @endif

    @if($datosVista->isEmpty())
        <div class="inf-empty-state">
            <div class="inf-empty-state__icon">
                @if($vistaActiva === 'pendientes')
                    <x-heroicon-o-check-badge style="width: 48px; height: 48px;" />
                @else
                    <x-heroicon-o-document style="width: 48px; height: 48px;" />
                @endif
            </div>
            <h5 class="fw-semibold mb-2">
                @if($vistaActiva === 'pendientes')
                    No hay informes pendientes de firma
                @elseif($vistaActiva === 'firmados')
                    No hay informes firmados
                @else
                    No hay informes disponibles
                @endif
            </h5>
            <p class="text-muted mb-0">
                @if($hayFiltros)
                    Probá ajustar los filtros o limpiarlos para ver más resultados.
                @elseif($vistaActiva === 'pendientes')
                    Cuando un informe se firma, desaparece de esta bandeja y pasa a la sección «Firmados».
                @else
                    Los informes aparecerán aquí cuando estén aprobados y habilitados.
                @endif
            </p>
        </div>
    @else
        @switch($viewType)
            @case('lista')
                @include('informes.partials.lista')
                @break
            @case('calendario')
                @include('informes.partials.calendario')
                @break
            @case('documento')
                @include('informes.partials.documento')
                @break
        @endswitch
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function cambiarVistaInformes(vista) {
        const input = document.getElementById('vistaInputInformes');
        if (input) {
            input.value = vista;
        }
        const form = document.getElementById('filterFormInformes');
        if (form) {
            form.submit();
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('vista', vista);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    }

    document.querySelectorAll('.informes-page [data-vista]').forEach(el => {
        el.addEventListener('click', function () {
            const vista = this.getAttribute('data-vista');
            if (vista) {
                cambiarVistaInformes(vista);
            }
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const vista = this.getAttribute('data-vista');
                if (vista) {
                    cambiarVistaInformes(vista);
                }
            }
        });
    });

    function logInformeFirmaRequest(payload) {
        try {
            console.log('[informes-firma] request', JSON.stringify(payload, null, 2));
        } catch (e) {
            console.log('[informes-firma] request', payload);
        }
    }

    document.querySelectorAll('a[href*="/informes/"][href$="/firmar"]').forEach(link => {
        link.addEventListener('click', function () {
            const href = this.getAttribute('href');
            if (!href) return;

            try {
                const url = new URL(href, window.location.origin);
                const match = url.pathname.match(/\/informes\/(\d+)\/(\d+)\/(\d+)\/firmar$/);
                logInformeFirmaRequest({
                    action: 'informes.firmar',
                    method: 'GET',
                    url: url.toString(),
                    params: {
                        cotio_numcoti: match ? Number(match[1]) : null,
                        cotio_item: match ? Number(match[2]) : null,
                        instance_number: match ? Number(match[3]) : null,
                    },
                    timestamp: new Date().toISOString(),
                });
            } catch (e) {
                logInformeFirmaRequest({
                    action: 'informes.firmar',
                    method: 'GET',
                    url: href,
                    params: {},
                    timestamp: new Date().toISOString(),
                });
            }
        });
    });
});
</script>
@endpush
