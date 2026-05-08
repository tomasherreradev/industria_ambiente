@extends('layouts.app')


<?php 
    // dd($ordenesCombinadas);
?>

<head>
    <title>Mis {{ Auth::user()->rol == 'laboratorio' ? 'análisis' : 'muestras' }}</title>
</head>

@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">Mis {{ Auth::user()->rol == 'laboratorio' ? 'análisis' : 'muestras' }}</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
            </button>
            
            <a href="{{ route('mis-ordenes', ['view' => 'lista']) }}" 
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', ['view' => 'calendario']) }}" 
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('mis-ordenes', ['view' => 'documento']) }}" 
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>

            @if($viewType === 'lista' && Auth::user()->rol == 'laboratorio')
                <a
                    href="{{ route('mis-ordenes', array_merge(request()->query(), ['view' => 'lista', 'print' => 1])) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn btn-sm btn-outline-dark ms-2"
                    title="Imprimir listado">
                    <x-heroicon-o-printer style="width: 18px; height: 18px;" class="me-1" />
                    <span class="d-none d-sm-inline">Imprimir</span>
                </a>
            @endif
        </div>
    </header>

    <div class="collapse mb-4" id="collapseSearch">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('mis-ordenes') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    
                    <div class="col-md-6">
                        <label for="search" class="form-label">Buscar por cotización</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Número, empresa o establecimiento" 
                               value="{{ request('search') }}">
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
                    
                    <div class="col-md-3" id="descripcionContainer" style="display: none;">
                        <label for="cotio_descripcion_analisis" class="form-label">Descripción análisis</label>
                        <input type="text" class="form-control" id="cotio_descripcion_analisis" name="cotio_descripcion_analisis" oninput="filtrarAnalitos()"
                               placeholder="Ej: CONDUCTIVIDAD ELECTRICA" value="{{ request('cotio_descripcion_analisis') }}">
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

    {{-- SUGERENCIAS DE ANALITOS POR ESTADO --}}
    @if(request('estado') && isset($analitosSugeridos) && $analitosSugeridos->count())
    @php
        $bagdeClass = match (request('estado')) {
            'coordinado analisis' => 'warning',
            'en revision analisis' => 'info',
            'analizado' => 'success',
            'suspension' => 'danger',
        };
    @endphp
    <div class="card mb-3 shadow-sm border-{{ $bagdeClass }}" id="analitosSugeridosContainer">
        <div class="card-body py-2">
            <div class="mb-2 fw-bold text-dark">
                Análisis con estado "{{ ucfirst(request('estado')) }}":
            </div>
            <ul class="list-group list-group-flush" id="listaAnalitos">
                @foreach($analitosSugeridos as $analito)
                    <li class="list-group-item d-flex justify-content-between align-items-center table-{{ $bagdeClass }} analito-item" 
                        data-descripcion="{{ strtolower($analito->cotio_descripcion ?? '') }}">
                        <span>
                            {{ $analito->cotio_descripcion ?? 'Sin descripción' }}
                            <span class="text-muted small">(Cotización N° {{ $analito->cotio_numcoti }})</span>
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

    @if($ordenesAgrupadas->isEmpty())
        <div class="alert alert-warning">
            No tienes muestras asignadas.
        </div>
    @else
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchCollapse = document.getElementById('collapseSearch');
        const searchToggleBtn = document.getElementById('searchToggleBtn');
        
        const hasFilters = @json(request()->hasAny(['search', 'fecha_inicio_ot', 'fecha_fin_ot']));
        
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
                
                @if(request()->hasAny(['search', 'fecha_inicio_ot', 'fecha_fin_ot']))
                    url.searchParams.set('search', @json(request('search')));
                    url.searchParams.set('fecha_inicio_ot', @json(request('fecha_inicio_ot')));
                    url.searchParams.set('fecha_fin_ot', @json(request('fecha_fin_ot')));
                @endif
                
                window.location.href = url.toString();
            });
        });
    });
</script>

<script>
    function filtrarAnalitos() {
        const filtroDescripcion = document.getElementById('cotio_descripcion_analisis').value.toLowerCase();
        const items = document.querySelectorAll('.analito-item');
        
        items.forEach(item => {
            const descripcion = item.getAttribute('data-descripcion');
            if (descripcion.includes(filtroDescripcion) || filtroDescripcion === '') {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Si hay un valor en el filtro de descripción al cargar la página, aplica el filtro
        if (document.getElementById('cotio_descripcion_analisis').value) {
            filtrarAnalitos();
        }
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const estadoSelect = document.getElementById('estado');
        const descripcionContainer = document.getElementById('descripcionContainer');
        
        // Mostrar/ocultar al cargar la página según el estado seleccionado
        if (estadoSelect.value) {
            descripcionContainer.style.display = 'block';
        }
        
        // Manejar cambios en el select de estado
        estadoSelect.addEventListener('change', function() {
            if (this.value) {
                descripcionContainer.style.display = 'block';
                // Opcional: enfocar el campo de descripción
                document.getElementById('cotio_descripcion_analisis').focus();
            } else {
                descripcionContainer.style.display = 'none';
                // Limpiar el campo al seleccionar "Todos"
                document.getElementById('cotio_descripcion_analisis').value = '';
            }
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