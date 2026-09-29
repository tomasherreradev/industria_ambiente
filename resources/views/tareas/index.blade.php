@extends('layouts.app')

@section('title', 'Mis muestras')

@section('content')
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/tareas-muestreo-mobile.css') }}?v={{ filemtime(public_path('css/tareas-muestreo-mobile.css')) }}">

@php
    $hayFiltros = request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']);
    $datosVista = $viewType === 'calendario'
        ? ($events ?? collect())
        : ($tareasAgrupadas ?? collect());
    $totalRegistros = $viewType === 'calendario'
        ? $datosVista->count()
        : $datosVista->count();
@endphp

<div class="container py-4 ucrud ucrud-operativo tareas-muestreo-page" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Mis muestras
                @if($viewType !== 'calendario')
                    <span class="ucrud-count">{{ number_format($totalRegistros, 0, ',', '.') }}</span>
                @endif
            </h1>
            <p class="ucrud-subtitle">Tareas de muestreo asignadas a tu usuario.</p>
        </div>

        <div class="ucrud-header__actions">
            <div class="ucrud-view-switch" role="group" aria-label="Tipo de vista">
                <a href="{{ route('mis-tareas', array_merge(request()->except('page'), ['view' => 'lista'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'lista' ? 'active' : '' }}" title="Lista">
                    <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('mis-tareas', array_merge(request()->except('page'), ['view' => 'calendario'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'calendario' ? 'active' : '' }}" title="Calendario">
                    <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('mis-tareas', array_merge(request()->except('page'), ['view' => 'documento'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'documento' ? 'active' : '' }}" title="Documento">
                    <x-heroicon-o-document style="width: 20px; height: 20px;" />
                </a>
            </div>
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

    <div class="ucrud-filters ucrud-filters--collapsible">
        <button type="button"
                class="ucrud-filters__mobile-toggle d-md-none"
                data-bs-toggle="collapse"
                data-bs-target="#misTareasFiltersCollapse"
                aria-expanded="{{ $hayFiltros ? 'true' : 'false' }}"
                aria-controls="misTareasFiltersCollapse">
            <span class="ucrud-filters__mobile-toggle-label">
                <x-heroicon-o-funnel style="width: 18px; height: 18px;" aria-hidden="true" />
                Filtros
                @if($hayFiltros)
                    <span class="ucrud-filters__active-badge" title="Hay filtros aplicados">Activos</span>
                @endif
            </span>
            <x-heroicon-o-chevron-down class="ucrud-filters__mobile-chevron" style="width: 20px; height: 20px;" aria-hidden="true" />
        </button>
        <p class="ucrud-filters__title d-none d-md-block">Filtros</p>
        <div id="misTareasFiltersCollapse"
             class="collapse ucrud-filters__collapsible-panel {{ $hayFiltros ? 'show' : '' }}">
            <form method="GET" action="{{ route('mis-tareas') }}" class="ucrud-filters__grid ucrud-filters__collapsible-inner">
                <input type="hidden" name="view" value="{{ $viewType }}">

                <div class="ucrud-field">
                    <label for="search">Buscar</label>
                    <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                           placeholder="Número, empresa o establecimiento" value="{{ request('search') }}">
                </div>

                <div class="ucrud-field">
                    <label for="fecha_inicio_muestreo">Desde</label>
                    <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_inicio_muestreo"
                           name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
                </div>

                <div class="ucrud-field">
                    <label for="fecha_fin_muestreo">Hasta</label>
                    <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_fin_muestreo"
                           name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
                </div>

                <div class="ucrud-field">
                    <label for="estado">Estado</label>
                    <select class="ucrud-select" style="width: 100%;" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="coordinado muestreo" @selected(request('estado') === 'coordinado muestreo')>Coordinados</option>
                        <option value="en revision muestreo" @selected(request('estado') === 'en revision muestreo')>En revisión</option>
                        <option value="muestreado" @selected(request('estado') === 'muestreado')>Muestreados</option>
                        <option value="suspension" @selected(request('estado') === 'suspension')>Suspensión</option>
                    </select>
                </div>

                <div class="ucrud-filters__actions">
                    @if($hayFiltros)
                        <a href="{{ route('mis-tareas', ['view' => $viewType]) }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                    @endif
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    @if($datosVista->isEmpty())
        <div class="ucrud-panel">
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-beaker style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No tienes muestras asignadas</p>
                <p class="ucrud-empty__text">{{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'No hay tareas de muestreo pendientes para tu usuario.' }}</p>
            </div>
        </div>
    @else
        @switch($viewType)
            @case('lista')
                @include('tareas.partials.lista')
                @break
            @case('calendario')
                @include('tareas.partials.calendario')
                @break
            @case('documento')
                @include('tareas.partials.documento')
                @break
        @endswitch
    @endif
</div>
@endsection
