@extends('layouts.app')
<head>
    <title>Informes de Muestras</title>
</head>

@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">Informes de Muestras</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
            </button>

            @if(userCanEditInformeProtocoloPdf())
                <a href="{{ route('informes.notas.index') }}" class="btn btn-sm btn-outline-secondary me-2" title="Notas reutilizables para informes">
                    <x-heroicon-o-document-text style="width: 16px; height: 16px;" class="me-1"/>
                    <span class="d-none d-sm-inline">Notas</span>
                </a>
            @endif
            
            @php
                $informesViewQuery = array_merge(
                    request()->except(['view', 'page']),
                    ['view' => 'lista']
                );
            @endphp
            <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'lista'])) }}" 
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'calendario'])) }}" 
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('informes.index', array_merge($informesViewQuery, ['view' => 'documento'])) }}" 
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>
    </header>

    <div class="collapse mb-4" id="collapseSearch">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('informes.index') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Buscar cotización</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Número, empresa o establecimiento" 
                               value="{{ request('search') }}">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="tipo_informe" class="form-label">Tipo de Informe</label>
                        <select class="form-select" id="tipo_informe" name="tipo_informe">
                            <option value="">Todos</option>
                            <option value="final" {{ request('tipo_informe') == 'final' ? 'selected' : '' }}>Final</option>
                            <option value="parcial" {{ request('tipo_informe') == 'parcial' ? 'selected' : '' }}>Parcial</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="estado_firma" class="form-label">Firma</label>
                        <select class="form-select" id="estado_firma" name="estado_firma">
                            <option value="">Todos</option>
                            <option value="firmados" {{ request('estado_firma') === 'firmados' ? 'selected' : '' }}>Firmados</option>
                            <option value="pendiente" {{ request('estado_firma') === 'pendiente' ? 'selected' : '' }}>Pendientes de firma</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="matriz" class="form-label">Matriz</label>
                        <select class="form-select" id="matriz" name="matriz">
                            <option value="">Todas</option>
                            @foreach($matrices as $matriz)
                                <option value="{{ $matriz->matriz_codigo }}" 
                                    {{ request('matriz') == $matriz->matriz_codigo ? 'selected' : '' }}>
                                    {{ $matriz->matriz_descripcion }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label for="fecha_inicio" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_inicio" 
                               name="fecha_inicio" value="{{ request('fecha_inicio') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_fin" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_fin" 
                               name="fecha_fin" value="{{ request('fecha_fin') }}">
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route('informes.index', ['view' => $viewType]) }}" class="btn btn-outline-secondary">
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
        $datosVista = $viewType === 'calendario' ? ($events ?? collect()) : ($informesPorCotizacion ?? collect());
    @endphp

    @if($datosVista->isEmpty())
        <div class="alert alert-warning">
            No hay informes disponibles.
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
    document.addEventListener('DOMContentLoaded', function() {
        function logInformeFirmaRequest(payload) {
            try {
                console.log('[informes-firma] request', JSON.stringify(payload, null, 2));
            } catch (e) {
                console.log('[informes-firma] request', payload);
            }
        }

        // Loguear (en consola) lo que se envía al iniciar la firma del informe (request al backend).
        // Nota: la "API de firma digital" se llama desde el backend; desde el navegador solo vemos
        // esta request GET que dispara el proceso.
        document.querySelectorAll('a[href*="/informes/"][href$="/firmar"]').forEach(link => {
            link.addEventListener('click', function() {
                const href = this.getAttribute('href');
                if (!href) return;

                try {
                    const url = new URL(href, window.location.origin);
                    // /informes/{cotio_numcoti}/{cotio_item}/{instance_number}/firmar
                    const match = url.pathname.match(/\/informes\/(\d+)\/(\d+)\/(\d+)\/firmar$/);
                    const cotio_numcoti = match ? Number(match[1]) : null;
                    const cotio_item = match ? Number(match[2]) : null;
                    const instance_number = match ? Number(match[3]) : null;

                    logInformeFirmaRequest({
                        action: 'informes.firmar',
                        method: 'GET',
                        url: url.toString(),
                        params: {
                            cotio_numcoti,
                            cotio_item,
                            instance_number,
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

        const searchCollapse = document.getElementById('collapseSearch');
        const searchToggleBtn = document.getElementById('searchToggleBtn');
        
        const hasFilters = {{ request()->hasAny(['search', 'tipo_informe', 'fecha_inicio', 'fecha_fin']) }};
        
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

    .badge-informe-final {
        background-color: #28a745;
    }
    
    .badge-informe-parcial {
        background-color: #ffc107;
        color: #212529;
    }
</style>
@endpush