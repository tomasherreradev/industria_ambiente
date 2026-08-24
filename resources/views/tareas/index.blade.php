@extends('layouts.app')


<?php 
    // dd($tareasCombinadas);
?>

<head>
    <title>Mis muestras</title>
</head>

@section('content')
<link rel="stylesheet" href="{{ asset('css/tareas-muestreo-mobile.css') }}?v={{ filemtime(public_path('css/tareas-muestreo-mobile.css')) }}">
<div class="container py-3 py-md-4 tareas-muestreo-page">
    {{-- Header desktop --}}
    <header class="d-none d-md-flex flex-md-row justify-content-between align-items-center align-items-md-center tareas-muestreo-header">
        <h1 class="mb-md-4">Mis muestras</h1>

        <div class="d-flex gap-2 align-items-center mb-2 tareas-muestreo-toolbar">
            <button class="btn btn-sm btn-outline-primary me-2 btn-search-toggle {{ request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']) ? 'active' : '' }}" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="{{ request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']) ? 'true' : 'false' }}" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span>Buscar</span>
            </button>
            
            <a href="{{ route('mis-tareas', ['view' => 'lista']) }}" 
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-tareas', ['view' => 'calendario']) }}" 
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-tareas', ['view' => 'documento']) }}" 
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>
    </header>

    {{-- Header móvil --}}
    <div class="tareas-mobile-header d-md-none">
        <h1>Mis muestras</h1>

        <form method="GET" action="{{ route('mis-tareas') }}" class="tareas-mobile-search-wrap">
            <input type="hidden" name="view" value="{{ $viewType }}">
            <div class="input-group">
                <span class="input-group-text bg-white">
                    <x-heroicon-o-magnifying-glass style="width: 18px; height: 18px;" />
                </span>
                <input type="search"
                       class="form-control"
                       name="search"
                       placeholder="Buscar muestra, cliente o ID..."
                       value="{{ request('search') }}">
            </div>
        </form>

        <div class="tareas-mobile-toolbar">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseSearchMobile" aria-expanded="{{ request()->hasAny(['fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']) ? 'true' : 'false' }}"
                    aria-label="Filtros avanzados">
                <x-heroicon-o-funnel style="width: 20px; height: 20px;" />
            </button>
            <a href="{{ route('mis-tareas', ['view' => 'lista']) }}"
               class="btn btn-outline-secondary {{ $viewType === 'lista' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-tareas', ['view' => 'calendario']) }}"
               class="btn btn-outline-secondary {{ $viewType === 'calendario' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-tareas', ['view' => 'documento']) }}"
               class="btn btn-outline-secondary {{ $viewType === 'documento' ? 'btn-view-active' : '' }}">
                <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>

        <div class="collapse tareas-mobile-search-advanced {{ request()->hasAny(['fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']) ? 'show' : '' }}" id="collapseSearchMobile">
            <form method="GET" action="{{ route('mis-tareas') }}" class="card card-body border mt-2 p-3">
                <input type="hidden" name="view" value="{{ $viewType }}">
                @if(request('search'))
                    <input type="hidden" name="search" value="{{ request('search') }}">
                @endif
                <div class="row g-2">
                    <div class="col-6">
                        <label for="fecha_inicio_muestreo_mobile" class="form-label">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_inicio_muestreo_mobile"
                               name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
                    </div>
                    <div class="col-6">
                        <label for="fecha_fin_muestreo_mobile" class="form-label">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_fin_muestreo_mobile"
                               name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
                    </div>
                    <div class="col-12">
                        <label for="estado_mobile" class="form-label">Estado</label>
                        <select class="form-select form-select-sm" id="estado_mobile" name="estado">
                            <option value="">Todos</option>
                            <option value="coordinado muestreo" @selected(request('estado') === 'coordinado muestreo')>Coordinados</option>
                            <option value="en revision muestreo" @selected(request('estado') === 'en revision muestreo')>En revisión</option>
                            <option value="muestreado" @selected(request('estado') === 'muestreado')>Muestreados</option>
                            <option value="suspension" @selected(request('estado') === 'suspension')>Suspensión</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Aplicar</button>
                        <a href="{{ route('mis-tareas', ['view' => $viewType]) }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="collapse mb-4 d-none d-md-block {{ request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']) ? 'show' : '' }}" id="collapseSearch">
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
                <form method="GET" action="{{ route('mis-tareas') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    
                    <div class="col-md-6">
                        <label for="search" class="form-label">Buscar cotización</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Número, empresa o establecimiento" 
                               value="{{ request('search') }}">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_inicio_muestreo" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_muestreo" 
                               name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="fecha_fin_muestreo" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_muestreo" 
                               name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
                    </div>

                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="coordinado muestreo">Coordinados</option>
                            <option value="en revision muestreo">En revisión</option>
                            <option value="muestreado">Muestreados</option>
                            <option value="suspension">Suspensión</option>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route('mis-tareas', ['view' => $viewType]) }}" class="btn btn-outline-secondary">
                                Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


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

    @php
        $mostrarVista = match ($viewType) {
            'calendario' => isset($events) && $events->isNotEmpty(),
            default => !$tareasAgrupadas->isEmpty(),
        };
    @endphp

    @if(!$mostrarVista)
        <div class="alert alert-warning">
            No tienes muestras asignadas.
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

                @if(request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado']))
                    url.searchParams.set('search', @json(request('search')));
                    url.searchParams.set('fecha_inicio_muestreo', @json(request('fecha_inicio_muestreo')));
                    url.searchParams.set('fecha_fin_muestreo', @json(request('fecha_fin_muestreo')));
                    url.searchParams.set('estado', @json(request('estado')));
                @endif

                window.location.href = url.toString();
            });
        });
    });
</script>
@endpush