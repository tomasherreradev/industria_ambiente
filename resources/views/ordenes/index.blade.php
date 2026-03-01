@extends('layouts.app')
<head>
    <title>Ordenes de trabajo</title>
</head>


@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">Ordenes de trabajo</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
            </button>
            
            <a href="{{ route('ordenes.index', ['view' => 'lista']) }}" 
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('ordenes.index', ['view' => 'calendario']) }}" 
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('ordenes.index', ['view' => 'documento']) }}" 
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>
    </header>

    <div class="collapse mb-4" id="collapseSearch">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('ordenes.index') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Buscar cotización / OT</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Nº cotización, empresa, establecimiento, Nº OT" 
                               value="{{ request('search') }}">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option class="bg-primary text-white" value="pendiente por coordinar" {{ request('estado') == 'pendiente por coordinar' ? 'selected' : '' }}>Pendiente por coordinar</option>
                            <option class="bg-warning text-dark" value="coordinado analisis" {{ request('estado') == 'coordinado analisis' ? 'selected' : '' }}>Coordinado analisis</option>
                            <option class="bg-info text-dark" value="en revision analisis" {{ request('estado') == 'en revision analisis' ? 'selected' : '' }}>En revision analisis</option>
                            <option class="bg-success text-white" value="analizado" {{ request('estado') == 'analizado' ? 'selected' : '' }}>Analizado</option>
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
                        <label for="fecha_inicio_ot" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_ot" 
                               name="fecha_inicio_ot" value="{{ request('fecha_inicio_ot') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_fin_ot" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_ot" 
                               name="fecha_fin_ot" value="{{ request('fecha_fin_ot') }}">
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route('ordenes.index', ['view' => $viewType]) }}" class="btn btn-outline-secondary">
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
        $datosVista = $viewType === 'calendario' ? ($tareasCalendario ?? collect()) : ($ordenes ?? collect());
    @endphp

    @if($datosVista->isEmpty())
        <div class="alert alert-warning">
            No hay ordenes disponibles.
        </div>
    @else
        @switch($viewType)
            @case('lista')
                @include('ordenes.partials.lista')
                @break
            @case('calendario')
                @include('ordenes.partials.calendario')
                @break
            @case('documento')
                @include('ordenes.partials.documento')
                @break
        @endswitch
    @endif

    
</div>
@endsection



<script>
    document.addEventListener('DOMContentLoaded', function() {
        const viewAsDropdown = document.getElementById('viewAsUserDropdown');
        if (viewAsDropdown) {
            viewAsDropdown.addEventListener('shown.bs.dropdown', function() {
                document.querySelector('[name="user_to_view"]').focus();
            });
        }
        
        document.getElementById('prev-month')?.addEventListener('click', function() {
            navigateMonth(-1);
        });
        
        document.getElementById('next-month')?.addEventListener('click', function() {
            navigateMonth(1);
        });
        
        function navigateMonth(monthsToAdd) {
            const url = new URL(window.location.href);
            const currentMonth = new Date();
            
            if (url.searchParams.has('month')) {
                currentMonth = new Date(url.searchParams.get('month'));
            }
            
            currentMonth.setMonth(currentMonth.getMonth() + monthsToAdd);
            url.searchParams.set('month', currentMonth.toISOString().split('T')[0]);
            window.location.href = url.toString();
        }
    });

</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchCollapse = document.getElementById('collapseSearch');
        const searchToggleBtn = document.getElementById('searchToggleBtn');
        
        const hasFilters = {{ request()->hasAny(['search', 'estado', 'fecha_inicio_ot', 'fecha_fin_ot']) }};
        
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
                
                @if(request()->hasAny(['search', 'estado', 'fecha_inicio_ot', 'fecha_fin_ot']))
                    url.searchParams.set('search', @json(request('search')));
                    url.searchParams.set('estado', @json(request('estado')));
                    url.searchParams.set('fecha_inicio_ot', @json(request('fecha_inicio_ot')));
                    url.searchParams.set('fecha_fin_ot', @json(request('fecha_fin_ot')));
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

    .dropdown-menu form {
        min-width: 250px;
    }

    .view-as-alert {
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .task-item {
        transition: transform 0.2s;
    }

    .task-item:hover {
        transform: translateX(2px);
    }
</style>