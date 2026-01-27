@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Panel de Análisis</h1>
        <div class="text-muted">{{ now()->format('l, d F Y') }}</div>
    </div>

    {{-- Resumen General --}}
    <div class="row mb-4 g-4">
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'all']) }}" class="text-decoration-none">
                <div class="card bg-primary bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Pendientes por coordinar</h5>
                                <p class="card-text display-6 fw-bold">{{ $pendientesPorCoordinar }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-magnifying-glass style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Pendientes por coordinar</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado analisis']) }}" class="text-decoration-none">
                <div class="card bg-warning bg-gradient text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Pendientes de análisis</h5>
                                <p class="card-text display-6 fw-bold">{{ $pendientesDeAnalisis }}</p>
                            </div>
                            <div class="bg-dark bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-clock style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Pendientes de análisis</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'en revision analisis']) }}" class="text-decoration-none">
                <div class="card bg-info bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Pendientes de revisión</h5>
                                <p class="card-text display-6 fw-bold">{{ $pendientesDeRevision }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-arrow-path style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Pendientes de revisión</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'analizado']) }}" class="text-decoration-none">
                <div class="card bg-success bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Finalizados</h5>
                                <p class="card-text display-6 fw-bold">{{ $finalizados }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-check-circle style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Completados</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    {{-- Contenido principal --}}
    <div class="row g-4">
        {{-- Análisis asignados --}}
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Análisis Asignados</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <!-- Filtro de Estado -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-filter me-1"></i> Filtrar
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'all', 'metodo' => request()->get('metodo', '')]) }}">Todos</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado analisis', 'metodo' => request()->get('metodo', '')]) }}">Pendientes de análisis</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'en revision analisis', 'metodo' => request()->get('metodo', '')]) }}">Pendientes de revisión</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'analizado', 'metodo' => request()->get('metodo', '')]) }}">Finalizados</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'proximos', 'metodo' => request()->get('metodo', '')]) }}">Próximos 3 días</a></li>
                                </ul>
                            </div>
                            
                            <!-- Filtro de Método de Análisis -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="metodoFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-flask me-1"></i> 
                                    @if(request()->get('metodo'))
                                        Método: {{ $metodosDisponibles->firstWhere('metodo_codigo', request()->get('metodo'))->metodo_descripcion ?? 'Seleccionado' }}
                                    @else
                                        Método
                                    @endif
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="metodoFilterDropdown">
                                    <li><a class="dropdown-item metodo-filter-option" href="{{ request()->fullUrlWithQuery(['metodo' => '', 'estado' => request()->get('estado', 'all')]) }}">
                                        <i class="fas fa-times me-2 text-muted"></i> Todos los métodos
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    @foreach($metodosDisponibles as $metodo)
                                        <li>
                                            <a class="dropdown-item metodo-filter-option {{ request()->get('metodo') == $metodo->metodo_codigo ? 'active' : '' }}" 
                                               href="{{ request()->fullUrlWithQuery(['metodo' => $metodo->metodo_codigo, 'estado' => request()->get('estado', 'all')]) }}">
                                                {{ $metodo->metodo_descripcion ?? $metodo->metodo_codigo }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Botón para exportar -->
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalExportar">
                                <i class="fas fa-file-excel me-1"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Análisis asignados a mi o a mi equipo
                        @if(isset($esDiaUno) && $esDiaUno)
                            <span class="badge bg-info ms-2">Filtrado por mes actual (día 1)</span>
                        @endif
                    </p>
                </div>
                <div class="card-body p-0">
                    <div class="accordion p-2" id="analisisAccordion">
                        @forelse($analisisAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                // dd($primerAnalisis);   
                                $muestra = $primerAnalisis->muestra;
                                $accordionId = 'muestra-' . str_replace(['-', '.'], '', $grupo);

                                $estado = $muestra->cotio_estado_analisis ?? 'pendiente_coordinar';
                                $badgeColor = match($estado) {
                                    'coordinado analisis' => 'bg-warning',
                                    'en revision analisis' => 'bg-info',
                                    'analizado' => 'bg-success text-white',
                                    'suspension' => 'bg-danger text-white',
                                    'pendiente_coordinar' => 'bg-primary text-white',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header" id="heading{{ $accordionId }}">
                                    <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $accordionId }}" aria-expanded="false" aria-controls="collapse{{ $accordionId }}">
                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                            <div>
                                                <strong>Muestra: </strong>
                                                @if($muestra)
                                                        {{ $muestra->cotio_descripcion ?? 'N/A' }} (#{{ $muestra->otn ? $muestra->otn : $muestra->instance_number ?? 'N/A' }})
                                                        <span class="text-muted small">
                                                            <strong>Cotización:</strong> <a href="{{ route('cotizaciones.ver-detalle', $muestra->cotio_numcoti) }}" class="text-muted">{{ $muestra->cotio_numcoti ?? 'N/A' }}</a>
                                                        </span>
                                                @else
                                                    'N/A'
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                {{-- @if($muestra && $muestra->es_priori)
                                                    <span class="badge bg-warning text-dark" style="font-size: 0.7em;">
                                                        <i class="fas fa-star me-1"></i>PRIORIDAD
                                                    </span>
                                                @endif --}}
                                                <span class="small {{ $badgeColor }}" style="padding: 2px 5px; border-radius: 5px;">
                                                    {{ $estado === 'pendiente_coordinar' ? 'Pendiente por coordinar' : ucfirst($estado) }}
                                                </span>
                                                <span class="badge bg-primary rounded-pill">
                                                    @if($analisisGrupo->isEmpty() || ($analisisGrupo->count() == 1 && !$analisisGrupo->first()->id))
                                                        Sin análisis
                                                    @else
                                                        {{ count($analisisGrupo) }} análisis
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse{{ $accordionId }}" class="accordion-collapse collapse" aria-labelledby="heading{{ $accordionId }}" data-bs-parent="#analisisAccordion">
                                    <div class="accordion-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="ps-4">Cotización</th>
                                                        <th>Orden</th>
                                                        <th>Tipo Análisis</th>
                                                        <th>Fecha Límite</th>
                                                        <th>Responsables</th>
                                                        {{-- <th class="pe-4">Estado</th> --}}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @if($analisisGrupo->isEmpty() || ($analisisGrupo->count() == 1 && !$analisisGrupo->first()->id))
                                                        {{-- Muestra pendiente por coordinar sin análisis activos --}}
                                                        <tr>
                                                            <td class="ps-4 fw-bold">
                                                                @if($muestra && $muestra->cotizacion)
                                                                    <a href="/cotizaciones/{{ $muestra->cotizacion->coti_num }}" class="text-primary">#{{ $muestra->cotizacion->coti_num ?? 'N/A' }}</a>
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($muestra)
                                                                    <a href="{{ route('categoria.verOrden', [
                                                                        'cotizacion' => $muestra->cotio_numcoti,
                                                                        'item' => $muestra->cotio_item,
                                                                        'instance' => $muestra->instance_number
                                                                    ]) }}" class="text-primary">
                                                                        {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                                                    </a>
                                                                @else
                                                                    N/A
                                                                @endif
                                                            </td>   
                                                            <td colspan="3" class="text-center text-muted">
                                                                <em>Pendiente por coordinar - Sin análisis activos</em>
                                                            </td>
                                                        </tr>
                                                    @else
                                                        @foreach($analisisGrupo as $item)
                                                        <tr>
                                                            <td class="ps-4 fw-bold">
                                                                <a href="/cotizaciones/{{ $item->cotizacion->coti_num }}" class="text-primary">#{{ $item->cotizacion->coti_num ?? 'N/A' }}</a>
                                                            </td>
                                                            <td>
                                                                <a href="{{ route('categoria.verOrden', [
                                                                    'cotizacion' => $item->cotizacion->coti_num,
                                                                    'item' => $item->cotio_item,
                                                                    'instance' => $item->instance_number
                                                                ]) }}" class="text-primary">
                                                                @if($muestra)
                                                                    {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                                                @endif
                                                            </a>
                                                            </td>   
                                                            <td style="max-width: 150px;" title="{{ $item->cotio_descripcion }}">
                                                                {{ $item->cotio_descripcion ?? 'N/A' }} 
                                                            </td>
                                                            <td>
                                                                @if($item->fecha_fin_ot)
                                                                    <span class="d-block">{{ $item->fecha_fin_ot->format('d/m/Y') }}</span>
                                                                    <small class="text-muted">{{ $item->fecha_fin_ot->format('H:i') }}</small>
                                                                @else
                                                                    <span class="text-muted">Sin fecha</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($item->responsablesAnalisis && $item->responsablesAnalisis->count() > 0)
                                                                    <div class="avatar-group">
                                                                        @foreach($item->responsablesAnalisis as $responsable)
                                                                        <span class="avatar avatar-xs" data-bs-toggle="tooltip" title="{{ $responsable->usu_descripcion }}">
                                                                            {{ $responsable->usu_codigo }}
                                                                        </span>
                                                                        @endforeach
                                                                    </div>
                                                                @else
                                                                    <span class="text-muted">Sin asignar</span>
                                                                @endif
                                                            </td>
                                                            {{-- <td class="pe-4">
                                                                @php
                                                                    $estadoClase = '';
                                                                    if(str_contains($item->cotio_estado_analisis, 'coordinado analisis')) {
                                                                        $estadoClase = 'warning text-dark';
                                                                    } elseif(str_contains($item->cotio_estado_analisis, 'en revision analisis')) {
                                                                        $estadoClase = 'info text-dark';
                                                                    } elseif(str_contains($item->cotio_estado_analisis, 'analizado')) {
                                                                        $estadoClase = 'success';
                                                                    }
                                                                @endphp
                                                                <span class="badge rounded-pill bg-{{ $estadoClase }}">
                                                                    {{ Str::title(str_replace(['_', 'analisis'], [' ', ''], $item->cotio_estado_analisis)) }}
                                                                </span>
                                                            </td> --}}
                                                        </tr>
                                                        @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-flask fa-2x mb-2"></i>
                            <p class="mb-0">No hay análisis asignados actualmente</p>
                        </div>
                        @endforelse
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Mostrando {{ count($analisisAgrupados) }} muestras con sus análisis
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar con información complementaria --}}
        <div class="col-lg-4">
            {{-- Análisis próximos --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Próximos a Vencer</h5>
                    <p class="text-muted small mb-0">Próximos 3 días</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @forelse($analisisProximosAgrupados as $grupo => $analisisGrupo)
                            @php
                                $primerAnalisis = $analisisGrupo->first();
                                $muestra = $primerAnalisis->muestra;
                                $fechaMasProxima = $muestra ? $muestra->fecha_fin_ot : ($primerAnalisis->fecha_fin ?? null);
                            @endphp
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <strong>Muestra: </strong>
                                        @if($muestra)
                                            <a href="{{ route('categoria.verOrden', [
                                                'cotizacion' => $muestra->cotio_numcoti,
                                                'item' => $muestra->cotio_item,
                                                'instance' => $muestra->instance_number
                                            ]) }}" class="text-primary">
                                                {{ $muestra->cotio_descripcion ?? 'N/A' }}
                                            </a>
                                        @else
                                            'N/A'
                                        @endif
                                    </div>
                                    <small class="text-muted">{{ $fechaMasProxima ? $fechaMasProxima->format('d/m H:i') : 'Sin fecha' }}</small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-primary rounded-pill">{{ count($analisisGrupo) }} análisis</span>
                                    @php
                                        $estadoClase = '';
                                        if(str_contains($primerAnalisis->cotio_estado_analisis, 'coordinado analisis')) {
                                            $estadoClase = 'warning text-dark';
                                        } elseif(str_contains($primerAnalisis->cotio_estado_analisis, 'en revision analisis')) {
                                            $estadoClase = 'info text-dark';
                                        } elseif(str_contains($primerAnalisis->cotio_estado_analisis, 'analizado')) {
                                            $estadoClase = 'success';
                                        }
                                    @endphp
                                    <span class="badge bg-{{ $estadoClase }} small">
                                        {{ Str::title(str_replace(['_', 'analisis'], [' ', ''], $primerAnalisis->cotio_estado_analisis)) }}
                                    </span>
                                </div>
                            </div>
                        @empty
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <p class="mb-0 small">No hay análisis próximos a vencer</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Distribución por estado --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Estados de Análisis</h5>
                    <p class="text-muted small mb-0">Distribución por estado</p>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 200px;">
                        <canvas id="estadoAnalisisChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Pendientes por coordinar</span>
                                <span class="badge bg-primary text-white rounded-pill">{{ $pendientesPorCoordinar }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Pendientes de análisis</span>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $pendientesDeAnalisis }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Finalizados</span>
                                <span class="badge bg-success rounded-pill">{{ $finalizados }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Pendientes de revisión</span>
                                <span class="badge bg-info rounded-pill">{{ $pendientesDeRevision }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Anulaciones</span>
                                <span class="badge bg-danger rounded-pill">{{ $anulados }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Herramientas en uso --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Herramientas en Uso</h5>
                    <p class="text-muted small mb-0">Equipamiento asignado</p>
                </div>
                <div class="card-body">
                    @forelse($herramientasEnUso as $herramienta)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-light rounded p-2 me-3">
                            <x-heroicon-o-beaker style="width: 20px; height: 20px;"/>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ $herramienta->equipamiento }}</h6>
                            <small class="text-muted">Serial: {{ $herramienta->serial }}</small>
                        </div>
                        <span class="badge bg-light text-dark">
                            {{ $herramienta->cotio_instancias_count }} uso(s)
                        </span>
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p class="mb-0 small">No hay herramientas en uso actualmente</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal para Exportar --}}
<div class="modal fade" id="modalExportar" tabindex="-1" aria-labelledby="modalExportarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalExportarLabel">
                    <i class="fas fa-file-excel me-2"></i>Exportar Análisis a Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.analisis.exportar') }}" method="POST" id="formExportar">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">Seleccione el período para exportar los análisis:</p>
                    
                    <div class="mb-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" required>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>El archivo incluirá: N° Cotización, Muestra, Descripción, Estado, Fechas de Inicio y Fin OT, Responsables de Análisis y Cliente.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Exportar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Gráfico de estados de análisis
        const estadoAnalisisCtx = document.getElementById('estadoAnalisisChart').getContext('2d');
        new Chart(estadoAnalisisCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pendientes por coordinar', 'Pendientes de análisis', 'Finalizados', 'Pendientes de revisión'],
                datasets: [{
                    data: [@json($pendientesPorCoordinar), @json($pendientesDeAnalisis), @json($finalizados), @json($pendientesDeRevision)],
                    backgroundColor: [
                        '#007bff', // Azul para pendientes por coordinar
                        '#ffc107', // Amarillo para pendientes de análisis
                        '#28a745', // Verde para finalizados
                        '#11cbf0'  // Rojo para pendientes de revisión
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} (${Math.round(context.parsed)}%)`;
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });

        // Actualizar el texto del botón dropdown según el estado actual
        const currentEstado = '{{ $estadoFiltro }}';
        const filterDropdown = document.getElementById('filterDropdown');
        const filterOptions = document.querySelectorAll('.filter-option');
        
        filterOptions.forEach(option => {
            if (option.getAttribute('href').includes(`estado=${currentEstado}`)) {
                filterDropdown.innerHTML = `<i class="fas fa-filter me-1"></i> ${option.textContent}`;
            }
        });

        // Actualizar el texto del botón dropdown de método según el método actual
        const currentMetodo = '{{ $metodoFiltro }}';
        const metodoFilterDropdown = document.getElementById('metodoFilterDropdown');
        if (metodoFilterDropdown && currentMetodo) {
            const metodoSeleccionado = document.querySelector(`.metodo-filter-option[href*="metodo=${currentMetodo}"]`);
            if (metodoSeleccionado) {
                metodoFilterDropdown.innerHTML = `<i class="fas fa-flask me-1"></i> Método: ${metodoSeleccionado.textContent.trim()}`;
            }
        }

        // Validación del formulario de exportación
        const formExportar = document.getElementById('formExportar');
        if (formExportar) {
            formExportar.addEventListener('submit', function(e) {
                const fechaDesde = document.getElementById('fecha_desde').value;
                const fechaHasta = document.getElementById('fecha_hasta').value;
                
                if (!fechaDesde || !fechaHasta) {
                    e.preventDefault();
                    alert('Por favor, complete ambas fechas.');
                    return false;
                }
                
                if (new Date(fechaDesde) > new Date(fechaHasta)) {
                    e.preventDefault();
                    alert('La fecha desde debe ser anterior o igual a la fecha hasta.');
                    return false;
                }
            });
        }

        // Establecer valores por defecto en el modal (mes actual)
        const modalExportar = document.getElementById('modalExportar');
        if (modalExportar) {
            modalExportar.addEventListener('show.bs.modal', function() {
                const hoy = new Date();
                const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                const ultimoDiaMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                
                document.getElementById('fecha_desde').value = primerDiaMes.toISOString().split('T')[0];
                document.getElementById('fecha_hasta').value = ultimoDiaMes.toISOString().split('T')[0];
            });
        }
    });
</script>
@endsection