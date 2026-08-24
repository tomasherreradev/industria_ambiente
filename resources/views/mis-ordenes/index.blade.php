@extends('layouts.app')

@php
    $esBandejaAnalisisLab = Auth::user()->hasAnyRole(['laboratorio', 'coordinador_lab']) || Auth::user()->isAdminLab();
    $tituloMisOrdenes = $esBandejaAnalisisLab ? 'análisis' : 'muestras';
@endphp

<head>
    <title>Mis {{ $tituloMisOrdenes }}</title>
</head>

@section('content')
@php
    $misOrdenesViewQuery = array_merge(
        request()->except(['view', 'page']),
        !empty($soloMisAsignaciones) ? ['solo_mis_asignaciones' => 1] : []
    );
    $misOrdenesTieneFiltrosBusqueda = request()->hasAny([
        'search',
        'cotio_descripcion_analisis',
        'fecha_inicio_ot',
        'fecha_fin_ot',
        'estado',
    ]);
@endphp
<link rel="stylesheet" href="{{ asset('css/tareas-muestreo-mobile.css') }}?v={{ filemtime(public_path('css/tareas-muestreo-mobile.css')) }}">
<div class="container py-3 py-md-4 tareas-muestreo-page">
    {{-- Header desktop --}}
    <header class="d-none d-md-flex flex-md-row justify-content-between align-items-center align-items-md-center tareas-muestreo-header">
        <h1 class="mb-md-4">Mis {{ $tituloMisOrdenes }}</h1>

        <div class="d-flex flex-wrap gap-2 align-items-center mb-2 tareas-muestreo-toolbar">
            @include('mis-ordenes.partials.toggle-solo-mis-asignaciones', [
                'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones ?? false,
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
                'wrapperClass' => 'me-1',
            ])

            <button class="btn btn-sm btn-outline-primary me-2 btn-search-toggle {{ $misOrdenesTieneFiltrosBusqueda ? 'active' : '' }}" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseSearch" aria-expanded="{{ $misOrdenesTieneFiltrosBusqueda ? 'true' : 'false' }}" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span>Buscar</span>
            </button>

            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'lista'])) }}"
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'calendario'])) }}"
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'documento'])) }}"
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>

            @if($viewType === 'lista' && $esBandejaAnalisisLab)
                <a
                    href="{{ route('mis-ordenes', array_merge(request()->query(), ['view' => 'lista', 'print' => 1])) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-sm btn-outline-dark ms-2"
                    title="Imprimir listado">
                    <x-heroicon-o-printer style="width: 18px; height: 18px;" class="me-1" />
                    <span>Imprimir</span>
                </a>
            @endif
        </div>
    </header>

    {{-- Header móvil --}}
    <div class="tareas-mobile-header d-md-none">
        <h1>Mis {{ $tituloMisOrdenes }}</h1>

        <form method="GET" action="{{ route('mis-ordenes') }}" class="tareas-mobile-search-wrap">
            <input type="hidden" name="view" value="{{ $viewType }}">
            @if(!empty($soloMisAsignaciones))
                <input type="hidden" name="solo_mis_asignaciones" value="1">
            @endif
            <div class="input-group">
                <span class="input-group-text bg-white">
                    <x-heroicon-o-magnifying-glass style="width: 18px; height: 18px;" />
                </span>
                <input type="search"
                       class="form-control"
                       name="search"
                       placeholder="Buscar cotización, cliente o análisis..."
                       value="{{ request('search') }}">
            </div>
        </form>

        <div class="tareas-mobile-toolbar">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseSearchMobile" aria-expanded="{{ $misOrdenesTieneFiltrosBusqueda ?? false ? 'true' : 'false' }}"
                    aria-label="Filtros avanzados">
                <x-heroicon-o-funnel style="width: 20px; height: 20px;" />
            </button>
            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'lista'])) }}"
               class="btn btn-outline-secondary {{ $viewType === 'lista' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'calendario'])) }}"
               class="btn btn-outline-secondary {{ $viewType === 'calendario' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'documento'])) }}"
               class="btn btn-outline-secondary {{ $viewType === 'documento' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>

        @if($puedeAlternarVistaAsignaciones ?? false)
            <div class="mt-2">
                @include('mis-ordenes.partials.toggle-solo-mis-asignaciones', [
                    'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones ?? false,
                    'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
                    'wrapperClass' => '',
                ])
            </div>
        @endif

        <div class="collapse tareas-mobile-search-advanced {{ ($misOrdenesTieneFiltrosBusqueda ?? false) ? 'show' : '' }}" id="collapseSearchMobile">
            <form method="GET" action="{{ route('mis-ordenes') }}" class="card card-body border mt-2 p-3">
                <input type="hidden" name="view" value="{{ $viewType }}">
                @if(!empty($soloMisAsignaciones))
                    <input type="hidden" name="solo_mis_asignaciones" value="1">
                @endif
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <div class="row g-2">
                    <div class="col-12">
                        <label for="cotio_descripcion_analisis_mobile" class="form-label">Nombre de análisis</label>
                        <input type="text" class="form-control form-control-sm" id="cotio_descripcion_analisis_mobile"
                               name="cotio_descripcion_analisis" value="{{ request('cotio_descripcion_analisis') }}"
                               placeholder="Ej: pH, CONDUCTIVIDAD">
                    </div>
                    <div class="col-6">
                        <label for="fecha_inicio_ot_mobile" class="form-label">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_inicio_ot_mobile"
                               name="fecha_inicio_ot" value="{{ request('fecha_inicio_ot') }}">
                    </div>
                    <div class="col-6">
                        <label for="fecha_fin_ot_mobile" class="form-label">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_fin_ot_mobile"
                               name="fecha_fin_ot" value="{{ request('fecha_fin_ot') }}">
                    </div>
                    <div class="col-12">
                        <label for="estado_mobile" class="form-label">Estado</label>
                        <select class="form-select form-select-sm" id="estado_mobile" name="estado">
                            <option value="">Todos</option>
                            <option value="coordinado analisis" @selected(request('estado') === 'coordinado analisis')>Coordinado</option>
                            <option value="en revision analisis" @selected(request('estado') === 'en revision analisis')>En revisión</option>
                            <option value="analizado" @selected(request('estado') === 'analizado')>Analizado</option>
                            <option value="suspension" @selected(request('estado') === 'suspension')>Suspensión</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Aplicar</button>
                        <a href="{{ route('mis-ordenes', ['view' => $viewType]) }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="collapse mb-4 d-none d-md-block {{ $misOrdenesTieneFiltrosBusqueda ? 'show' : '' }}" id="collapseSearch">
        <div class="card shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2 px-3">
                <span class="small fw-semibold text-muted">Filtros de búsqueda</span>
                <button type="button"
                        class="btn btn-sm btn-link text-muted p-0"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapseSearch"
                        aria-label="Cerrar filtros">
                    <x-heroicon-o-x-mark style="width: 20px; height: 20px;" />
                </button>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('mis-ordenes') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    @if(!empty($soloMisAsignaciones))
                        <input type="hidden" name="solo_mis_asignaciones" value="1">
                    @endif
                    
                    <div class="col-md-6">
                        <label for="search" class="form-label">Buscar por cotización</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Número, empresa o establecimiento" 
                               value="{{ request('search') }}">
                    </div>

                    <div class="col-md-6">
                        <label for="cotio_descripcion_analisis" class="form-label">Nombre de análisis</label>
                        <input type="text" class="form-control" id="cotio_descripcion_analisis" name="cotio_descripcion_analisis"
                               placeholder="Ej: pH, CONDUCTIVIDAD, PCB" value="{{ request('cotio_descripcion_analisis') }}">
                        <small class="text-muted">Coincidencia parcial en el nombre del análisis. Solo muestra ítems con OT activa (como en el listado).</small>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_inicio_ot" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_ot" 
                               name="fecha_inicio_ot" value="{{ request('fecha_inicio_ot') }}">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_fin_ot" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_ot" 
                               name="fecha_fin_ot" value="{{ request('fecha_fin_ot') }}">
                    </div>

                    <div class="col-md-3" id="estadoContainer">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="coordinado analisis" {{ request('estado') === 'coordinado analisis' ? 'selected' : '' }}>Coordinado</option>
                            <option value="en revision analisis" {{ request('estado') === 'en revision analisis' ? 'selected' : '' }}>En revisión</option>
                            <option value="analizado" {{ request('estado') === 'analizado' ? 'selected' : '' }}>Analizado</option>
                            <option value="suspension" {{ request('estado') === 'suspension' ? 'selected' : '' }}>Suspensión</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route('mis-ordenes', ['view' => $viewType]) }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- SUGERENCIAS DE ANALITOS (por estado y/o nombre de análisis) --}}
    @if(isset($analitosSugeridos) && $analitosSugeridos->count() && (request('estado') || request('cotio_descripcion_analisis')))
    @php
        $estadoFiltroGlobal = request('estado');
        $bagdeClassGlobal = $estadoFiltroGlobal ? match ($estadoFiltroGlobal) {
            'coordinado analisis' => 'warning',
            'en revision analisis' => 'info',
            'analizado' => 'success',
            'suspension' => 'danger',
            default => 'primary',
        } : 'primary';

        $badgeClassPorEstado = function (?string $estadoAnalito) {
            return match (strtolower(trim((string) $estadoAnalito))) {
                'coordinado analisis', 'coordinado' => 'warning',
                'en revision analisis', 'en revision' => 'info',
                'analizado' => 'success',
                'suspension' => 'danger',
                default => 'secondary',
            };
        };
    @endphp
    <div class="card mb-3 shadow-sm border-{{ $bagdeClassGlobal }}" id="analitosSugeridosContainer">
        <div class="card-body py-2">
            <div class="mb-2 fw-bold text-dark">
                @if($estadoFiltroGlobal && request('cotio_descripcion_analisis'))
                    Análisis «{{ request('cotio_descripcion_analisis') }}» con estado "{{ ucfirst($estadoFiltroGlobal) }}":
                @elseif($estadoFiltroGlobal)
                    Análisis con estado "{{ ucfirst($estadoFiltroGlobal) }}":
                @else
                    Análisis que coinciden con «{{ request('cotio_descripcion_analisis') }}»:
                @endif
            </div>
            <ul class="list-group list-group-flush" id="listaAnalitos">
                @foreach($analitosSugeridos as $analito)
                    @php
                        $bagdeClass = $estadoFiltroGlobal
                            ? $bagdeClassGlobal
                            : $badgeClassPorEstado($analito->cotio_estado_analisis ?? null);
                    @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center table-{{ $bagdeClass }} analito-item" 
                        data-descripcion="{{ strtolower($analito->cotio_descripcion ?? '') }}">
                        <span>
                            {{ $analito->cotio_descripcion ?? 'Sin descripción' }}
                            @include('ordenes.partials.metodo-analisis-etiqueta', ['tarea' => $analito])
                            <span class="text-muted small">(Cotización N° {{ $analito->cotio_numcoti }})</span>
                            @unless($estadoFiltroGlobal)
                                <span class="badge bg-{{ $bagdeClass }} ms-1">{{ ucfirst($analito->cotio_estado_analisis ?? 'Sin estado') }}</span>
                            @endunless
                        </span>
                        <a href="/ordenes-all/{{ $analito->cotio_numcoti }}/{{ $analito->cotio_item }}/{{ $analito->cotio_subitem }}/{{ $analito->instance_number }}?openModal={{ $analito->cotio_subitem }}" 
                            class="btn bg-{{ $bagdeClass }} text-white btn-sm">Ver análisis</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    @if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($ordenesAgrupadas->isEmpty() && (empty($analitosSugeridos) || $analitosSugeridos->isEmpty()))
        <div class="alert alert-warning">
            No tienes muestras asignadas.
        </div>
    @elseif(!$ordenesAgrupadas->isEmpty())
        @switch($viewType)
            @case('lista')
                @include('mis-ordenes.partials.lista')
                @break
            @case('calendario')
                @include('mis-ordenes.partials.calendario')
                @break
            @case('documento')
                @include('mis-ordenes.partials.documento')
                @break
        @endswitch
    @endif
</div>

<style>
    #searchToggleBtn.active {
        background-color: var(--bs-primary);
        color: white;
    }
    #searchToggleBtn.active:hover {
        background-color: var(--bs-primary-dark);
    }

    @media (max-width: 768px) {
        .card-body .row {
            gap: 12px 0;
        }
        .card-body .col-md-6,
        .card-body .col-md-3 {
            width: 100%;
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchCollapse = document.getElementById('collapseSearch');
        const searchToggleBtn = document.getElementById('searchToggleBtn');

        if (searchCollapse && searchToggleBtn) {
            searchCollapse.addEventListener('show.bs.collapse', function() {
                searchToggleBtn.classList.add('active');
                searchToggleBtn.setAttribute('aria-expanded', 'true');
            });

            searchCollapse.addEventListener('hide.bs.collapse', function() {
                searchToggleBtn.classList.remove('active');
                searchToggleBtn.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('[data-view-type]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);

                @if($misOrdenesTieneFiltrosBusqueda)
                    url.searchParams.set('search', @json(request('search')));
                    url.searchParams.set('cotio_descripcion_analisis', @json(request('cotio_descripcion_analisis')));
                    url.searchParams.set('fecha_inicio_ot', @json(request('fecha_inicio_ot')));
                    url.searchParams.set('fecha_fin_ot', @json(request('fecha_fin_ot')));
                    url.searchParams.set('estado', @json(request('estado')));
                @endif

                window.location.href = url.toString();
            });
        });
    });
</script>
@endpush