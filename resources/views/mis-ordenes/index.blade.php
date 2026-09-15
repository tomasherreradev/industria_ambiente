@extends('layouts.app')

@php
    $esBandejaAnalisisLab = Auth::user()->hasAnyRole(['laboratorio', 'coordinador_lab']) || Auth::user()->isAdminLab();
    $tituloMisOrdenes = $esBandejaAnalisisLab ? 'análisis' : 'muestras';
    $misOrdenesViewQuery = array_merge(
        request()->except(['view', 'page']),
        !empty($soloMisAsignaciones) ? ['solo_mis_asignaciones' => 1] : []
    );
    $hayFiltros = request()->hasAny([
        'search',
        'cotio_descripcion_analisis',
        'fecha_inicio_ot',
        'fecha_fin_ot',
        'estado',
    ]);
    $datosVista = $viewType === 'calendario'
        ? ($events ?? collect())
        : ($ordenesAgrupadas ?? collect());
    $totalRegistros = $viewType === 'calendario'
        ? $datosVista->count()
        : $datosVista->count();
    $limpiarQuery = array_merge(['view' => $viewType], !empty($soloMisAsignaciones) ? ['solo_mis_asignaciones' => 1] : []);
@endphp

@section('title', 'Mis ' . $tituloMisOrdenes)

@section('content')
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/tareas-muestreo-mobile.css') }}?v={{ filemtime(public_path('css/tareas-muestreo-mobile.css')) }}">

<div class="container py-4 ucrud ucrud-operativo tareas-muestreo-page" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Mis {{ $tituloMisOrdenes }}
                @if($viewType !== 'calendario')
                    <span class="ucrud-count">{{ number_format($totalRegistros, 0, ',', '.') }}</span>
                @endif
            </h1>
            <p class="ucrud-subtitle">Bandeja de trabajo en laboratorio y seguimiento de OTs.</p>
        </div>

        <div class="ucrud-header__actions">
            @include('mis-ordenes.partials.toggle-solo-mis-asignaciones', [
                'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones ?? false,
                'soloMisAsignaciones' => $soloMisAsignaciones ?? false,
                'wrapperClass' => 'me-2',
            ])

            <div class="ucrud-view-switch" role="group" aria-label="Tipo de vista">
                <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'lista'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'lista' ? 'active' : '' }}" title="Lista">
                    <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'calendario'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'calendario' ? 'active' : '' }}" title="Calendario">
                    <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('mis-ordenes', array_merge($misOrdenesViewQuery, ['view' => 'documento'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'documento' ? 'active' : '' }}" title="Documento">
                    <x-heroicon-o-document style="width: 20px; height: 20px;" />
                </a>
            </div>

            @if($viewType === 'lista' && $esBandejaAnalisisLab)
                <a href="{{ route('mis-ordenes', array_merge(request()->query(), ['view' => 'lista', 'print' => 1])) }}"
                   target="_blank" rel="noopener"
                   class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm ms-2" title="Imprimir listado">
                    <x-heroicon-o-printer style="width: 18px; height: 18px;" />
                    Imprimir
                </a>
            @endif
        </div>
    </header>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <x-heroicon-o-exclamation-circle style="width: 18px; height: 18px;" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form method="GET" action="{{ route('mis-ordenes') }}" class="ucrud-filters__grid">
            <input type="hidden" name="view" value="{{ $viewType }}">
            @if(!empty($soloMisAsignaciones))
                <input type="hidden" name="solo_mis_asignaciones" value="1">
            @endif

            <div class="ucrud-field">
                <label for="search">Buscar cotización</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                       placeholder="Número, empresa o establecimiento" value="{{ request('search') }}">
            </div>

            <div class="ucrud-field">
                <label for="cotio_descripcion_analisis">Nombre de análisis</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="cotio_descripcion_analisis"
                       name="cotio_descripcion_analisis" placeholder="Ej: pH, CONDUCTIVIDAD"
                       value="{{ request('cotio_descripcion_analisis') }}">
            </div>

            <div class="ucrud-field">
                <label for="fecha_inicio_ot">Desde</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_inicio_ot"
                       name="fecha_inicio_ot" value="{{ request('fecha_inicio_ot') }}">
            </div>

            <div class="ucrud-field">
                <label for="fecha_fin_ot">Hasta</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_fin_ot"
                       name="fecha_fin_ot" value="{{ request('fecha_fin_ot') }}">
            </div>

            <div class="ucrud-field">
                <label for="estado">Estado</label>
                <select class="ucrud-select" style="width: 100%;" id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="coordinado analisis" @selected(request('estado') === 'coordinado analisis')>Coordinado</option>
                    <option value="en revision analisis" @selected(request('estado') === 'en revision analisis')>En revisión</option>
                    <option value="analizado" @selected(request('estado') === 'analizado')>Analizado</option>
                    <option value="suspension" @selected(request('estado') === 'suspension')>Suspensión</option>
                </select>
            </div>

            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route('mis-ordenes', $limpiarQuery) }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
            </div>
        </form>
    </div>

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
        <div class="ucrud-panel mb-3 border-{{ $bagdeClassGlobal }}" id="analitosSugeridosContainer">
            <p class="fw-semibold mb-2">
                @if($estadoFiltroGlobal && request('cotio_descripcion_analisis'))
                    Análisis «{{ request('cotio_descripcion_analisis') }}» con estado «{{ ucfirst($estadoFiltroGlobal) }}»
                @elseif($estadoFiltroGlobal)
                    Análisis con estado «{{ ucfirst($estadoFiltroGlobal) }}»
                @else
                    Análisis que coinciden con «{{ request('cotio_descripcion_analisis') }}»
                @endif
            </p>
            <ul class="list-group list-group-flush" id="listaAnalitos">
                @foreach($analitosSugeridos as $analito)
                    @php
                        $bagdeClass = $estadoFiltroGlobal
                            ? $bagdeClassGlobal
                            : $badgeClassPorEstado($analito->cotio_estado_analisis ?? null);
                    @endphp
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2 table-{{ $bagdeClass }} analito-item"
                        data-descripcion="{{ strtolower($analito->cotio_descripcion ?? '') }}">
                        <span>
                            {{ $analito->cotio_descripcion ?? 'Sin descripción' }}
                            @include('ordenes.partials.metodo-analisis-etiqueta', ['tarea' => $analito])
                            <span class="text-muted small">(Cotización N° {{ $analito->cotio_numcoti }})</span>
                            @unless($estadoFiltroGlobal)
                                <span class="badge bg-{{ $bagdeClass }} ms-1">{{ ucfirst($analito->cotio_estado_analisis ?? 'Sin estado') }}</span>
                            @endunless
                        </span>
                        <a href="/ordenes-all/{{ $analito->cotio_numcoti }}/{{ $analito->cotio_item }}/{{ $analito->cotio_subitem }}/{{ $analito->instance_number }}?openModal={{ $analito->cotio_subitem }}{{ !empty($soloMisAsignaciones) ? '&solo_mis_asignaciones=1' : '' }}"
                           class="ucrud-btn ucrud-btn--sm bg-{{ $bagdeClass }} text-white border-0">Ver análisis</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($ordenesAgrupadas->isEmpty() && (!isset($analitosSugeridos) || $analitosSugeridos->isEmpty()))
        <div class="ucrud-panel">
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-clipboard-document-list style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No tienes {{ $tituloMisOrdenes }} asignadas</p>
                <p class="ucrud-empty__text">{{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'No hay órdenes de trabajo pendientes para tu usuario.' }}</p>
            </div>
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
@endsection
