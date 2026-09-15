@extends('layouts.app')

@section('title', 'Muestras')

@section('content')
@include('partials.operativo-styles')

@php
    $hayFiltros = request()->hasAny(['search', 'estado_muestra', 'matriz', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'sort', 'dir']);
    $datosVista = ($viewType ?? 'lista') === 'calendario'
        ? ($events ?? collect())
        : ($muestras ?? collect());
    $totalRegistros = ($viewType ?? 'lista') === 'calendario'
        ? $datosVista->count()
        : (method_exists($muestras ?? null, 'total') ? $muestras->total() : $datosVista->count());
@endphp

<div class="container py-4 ucrud ucrud-operativo" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Muestras
                @if(($viewType ?? 'lista') !== 'calendario')
                    <span class="ucrud-count">{{ number_format($totalRegistros, 0, ',', '.') }}</span>
                @endif
            </h1>
            <p class="ucrud-subtitle">Coordinación de muestreo y seguimiento de cotizaciones.</p>
        </div>

        <div class="ucrud-header__actions">
            <div class="ucrud-view-switch" role="group" aria-label="Tipo de vista">
                <a href="{{ route('muestras.index', array_merge(request()->except('page'), ['view' => 'lista'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'lista' ? 'active' : '' }}" title="Lista">
                    <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('muestras.index', array_merge(request()->except('page'), ['view' => 'calendario'])) }}"
                   class="ucrud-view-switch__btn {{ $viewType === 'calendario' ? 'active' : '' }}" title="Calendario">
                    <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
                </a>
                <a href="{{ route('muestras.index', array_merge(request()->except('page'), ['view' => 'documento'])) }}"
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

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form method="GET" action="{{ route('muestras.index') }}" class="ucrud-filters__grid">
            <input type="hidden" name="view" value="{{ $viewType }}">
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if(request('dir'))
                <input type="hidden" name="dir" value="{{ request('dir') }}">
            @endif

            <div class="ucrud-field">
                <label for="search">Buscar muestra</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                       placeholder="Número, empresa o establecimiento" value="{{ request('search') }}">
            </div>

            <div class="ucrud-field">
                <label for="estado_muestra">Estado muestra</label>
                <select class="ucrud-select" style="width: 100%;" id="estado_muestra" name="estado_muestra">
                    <option value="">Todos</option>
                    <option value="coordinado" @selected(request('estado_muestra') == 'coordinado')>Coordinado</option>
                    <option value="en_revision" @selected(request('estado_muestra') == 'en_revision')>En revisión</option>
                    <option value="muestreado" @selected(request('estado_muestra') == 'muestreado')>Muestreado</option>
                </select>
            </div>

            <div class="ucrud-field">
                <label for="matriz">Matriz</label>
                <select class="ucrud-select" style="width: 100%;" id="matriz" name="matriz">
                    <option value="">Todas</option>
                    @foreach($matrices as $matriz)
                        <option value="{{ $matriz->matriz_codigo }}" @selected(request('matriz') == $matriz->matriz_codigo)>
                            {{ $matriz->matriz_descripcion }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="ucrud-field">
                <label for="fecha_inicio_muestreo">Aprobación desde</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_inicio_muestreo"
                       name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
            </div>

            <div class="ucrud-field">
                <label for="fecha_fin_muestreo">Aprobación hasta</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_fin_muestreo"
                       name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
            </div>

            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route('muestras.index', ['view' => $viewType]) }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
            </div>
        </form>
    </div>

    @if($datosVista->isEmpty())
        <div class="ucrud-panel">
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-beaker style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay muestras disponibles</p>
                <p class="ucrud-empty__text">{{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'No hay cotizaciones con muestreo pendiente.' }}</p>
            </div>
        </div>
    @else
        @switch($viewType)
            @case('lista')
                @include('muestras.partials.lista')
                @break
            @case('calendario')
                @include('muestras.partials.calendario')
                @break
            @case('documento')
                @include('muestras.partials.documento')
                @break
        @endswitch
    @endif
</div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('prev-month')?.addEventListener('click', function() {
            navigateMonth(-1);
        });

        document.getElementById('next-month')?.addEventListener('click', function() {
            navigateMonth(1);
        });

        function navigateMonth(monthsToAdd) {
            const url = new URL(window.location.href);
            let currentMonth = new Date();

            if (url.searchParams.has('month')) {
                currentMonth = new Date(url.searchParams.get('month'));
            }

            currentMonth.setMonth(currentMonth.getMonth() + monthsToAdd);
            url.searchParams.set('month', currentMonth.toISOString().split('T')[0]);
            window.location.href = url.toString();
        }
    });
</script>
