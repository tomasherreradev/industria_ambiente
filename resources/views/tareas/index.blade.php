@extends('layouts.app')


<?php 
    // dd($tareasCombinadas);
?>

<head>
    <title>Mis muestras</title>
</head>

@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">Mis muestras</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
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

    <div class="collapse mb-4" id="collapseSearch">
        <div class="card shadow-sm">
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

    @if($tareasAgrupadas->isEmpty())
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
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchCollapse = document.getElementById('collapseSearch');
        const searchToggleBtn = document.getElementById('searchToggleBtn');
        
        const hasFilters = {!! json_encode(request()->hasAny(['search', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'estado'])) !!};
        
        if (hasFilters) {
            new bootstrap.Collapse(searchCollapse, { toggle: true });
            searchToggleBtn.setAttribute('aria-expanded', 'true');
            searchToggleBtn.classList.add('active');
        }
        
        searchCollapse.addEventListener('show.bs.collapse', function() {
            searchToggleBtn.classList.add('active');
        });
        
        searchCollapse.addEventListener('hide.bs.collapse', function() {
            searchToggleBtn.classList.remove('active');
        });
        
        document.querySelectorAll('[data-view-type]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const viewType = this.getAttribute('data-view-type');
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
    
<style>
    #searchToggleBtn.active {
        background-color: var(--bs-primary);
        color: white;
    }
    #searchToggleBtn.active:hover {
        background-color: var(--bs-primary-dark);
    }
    
    @media (max-width: 768px) {
        header {
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        .btn-group {
            margin-top: 0.5rem;
            width: 100%;
        }
        .btn-group .btn {
            flex: 1;
        }
        #searchToggleBtn {
            margin-right: 0 !important;
            width: 100%;
        }
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