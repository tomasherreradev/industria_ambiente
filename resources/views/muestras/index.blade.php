@extends('layouts.app')

@section('content')
<div class="container py-3 py-md-4">
    <header class="d-flex flex-md-row justify-content-between align-items-center align-items-md-center">
        <h1 class="mb-md-4">Muestras</h1>

        <div class="d-flex gap-2 align-items-center mb-2">
            <button class="btn btn-sm btn-outline-primary me-2" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#collapseSearch" aria-expanded="false" aria-controls="collapseSearch"
                    id="searchToggleBtn">
                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" class="me-1"/>
                <span class="d-none d-sm-inline">Buscar</span>
            </button>
            
            <a href="{{ route('muestras.index', ['view' => 'lista']) }}" 
               class="btn btn-sm {{ $viewType === 'lista' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-list-bullet style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('muestras.index', ['view' => 'calendario']) }}" 
               class="btn btn-sm {{ $viewType === 'calendario' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" />
            </a>
            <a href="{{ route('muestras.index', ['view' => 'documento']) }}" 
               class="btn btn-sm {{ $viewType === 'documento' ? 'btn-primary' : 'btn-outline-secondary' }}">
               <x-heroicon-o-document style="width: 20px; height: 20px;" />
            </a>
        </div>
    </header>

    <div class="collapse mb-4" id="collapseSearch">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" action="{{ route('muestras.index') }}" class="row g-3">
                    <input type="hidden" name="view" value="{{ $viewType }}">
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if(request('dir'))
                        <input type="hidden" name="dir" value="{{ request('dir') }}">
                    @endif
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Buscar muestra</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Número, empresa o establecimiento" 
                               value="{{ request('search') }}">
                    </div>
                    
                    <div class="col-md-2">
                        <label for="estado_muestra" class="form-label">Estado muestra</label>
                        <select class="form-select" id="estado_muestra" name="estado_muestra">
                            <option value="">Todos</option>
                            <option value="coordinado" {{ request('estado_muestra') == 'coordinado' ? 'selected' : '' }}>Coordinado</option>
                            <option value="en_revision" {{ request('estado_muestra') == 'en_revision' ? 'selected' : '' }}>En revision</option>
                            <option value="muestreado" {{ request('estado_muestra') == 'muestreado' ? 'selected' : '' }}>Muestreado</option>
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
                        <label for="fecha_inicio_muestreo" class="form-label">Aprobación desde</label>
                        <input type="date" class="form-control" id="fecha_inicio_muestreo" 
                               name="fecha_inicio_muestreo" value="{{ request('fecha_inicio_muestreo') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="fecha_fin_muestreo" class="form-label">Aprobación hasta</label>
                        <input type="date" class="form-control" id="fecha_fin_muestreo" 
                               name="fecha_fin_muestreo" value="{{ request('fecha_fin_muestreo') }}">
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                                Buscar
                            </button>
                            <a href="{{ route('muestras.index', ['view' => $viewType]) }}" class="btn btn-outline-secondary">
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

    @if(($muestras ?? collect())->isEmpty())
        <div class="alert alert-warning">
            No hay muestras disponibles.
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
                
                @if(request()->hasAny(['search', 'estado_muestra', 'matriz', 'fecha_inicio_muestreo', 'fecha_fin_muestreo', 'sort', 'dir']))
                    url.searchParams.set('search', @json(request('search')));
                    url.searchParams.set('estado_muestra', @json(request('estado_muestra')));
                    url.searchParams.set('matriz', @json(request('matriz')));
                    url.searchParams.set('fecha_inicio_muestreo', @json(request('fecha_inicio_muestreo')));
                    url.searchParams.set('fecha_fin_muestreo', @json(request('fecha_fin_muestreo')));
                    url.searchParams.set('sort', @json(request('sort')));
                    url.searchParams.set('dir', @json(request('dir')));
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