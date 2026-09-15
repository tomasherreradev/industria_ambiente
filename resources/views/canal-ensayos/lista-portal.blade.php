@extends('layouts.app')

@section('title', $portalTitulo ?? 'Portal')

@section('content')
@include('partials.operativo-styles')

@php
    $hayFiltros = request()->hasAny(['search', 'estado', 'matriz', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'sort', 'dir']);
    $totalRegistros = method_exists($muestras ?? null, 'total') ? $muestras->total() : ($muestras ?? collect())->count();
    $subtitulos = [
        'consultoria' => 'Ensayos y trabajos de consultoría.',
        'mediciones' => 'Mediciones con informes y seguimiento.',
        'asp' => 'Ensayos del área ASP.',
        'clarke_fire' => 'Ensayos Clarke Fire.',
    ];
    $subtitulo = $subtitulos[$portalCanal ?? ''] ?? 'Listado de cotizaciones del canal.';
@endphp

<div class="container py-4 ucrud ucrud-operativo" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                {{ $portalTitulo }}
                <span class="ucrud-count">{{ number_format($totalRegistros, 0, ',', '.') }}</span>
            </h1>
            <p class="ucrud-subtitle">{{ $subtitulo }}</p>
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
        <form method="GET" action="{{ route($portalRouteName) }}" class="ucrud-filters__grid">
            <input type="hidden" name="view" value="lista">
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
            @endif
            @if(request('dir'))
                <input type="hidden" name="dir" value="{{ request('dir') }}">
            @endif

            <div class="ucrud-field">
                <label for="search">Buscar</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                       placeholder="Número, empresa o establecimiento" value="{{ request('search') }}">
            </div>

            <div class="ucrud-field">
                <label for="estado">Estado</label>
                <select class="ucrud-select" style="width: 100%;" id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="A" @selected(request('estado') == 'A')>Aprobado</option>
                    <option value="E" @selected(request('estado') == 'E')>En espera</option>
                    <option value="S" @selected(request('estado') == 'S')>Rechazado</option>
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
                <label for="fecha_inicio_muestreo">Alta desde</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_inicio_muestreo"
                       name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
            </div>

            <div class="ucrud-field">
                <label for="fecha_fin_muestreo">Alta hasta</label>
                <input type="date" class="ucrud-input" style="padding-left: .9rem;" id="fecha_fin_muestreo"
                       name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
            </div>

            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route($portalRouteName) }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>
            </div>
        </form>
    </div>

    @if(($muestras ?? collect())->isEmpty())
        <div class="ucrud-panel">
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-document-magnifying-glass style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">Sin resultados</p>
                <p class="ucrud-empty__text">No hay cotizaciones con ensayos de esta área según los filtros.</p>
            </div>
        </div>
    @else
        @include('muestras.partials.lista')
    @endif
</div>
@endsection
