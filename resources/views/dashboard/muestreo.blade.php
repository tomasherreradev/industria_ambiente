@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Panel de Muestreo</h1>
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
                                <h5 class="card-title text-uppercase small">Total Muestras</h5>
                                <p class="card-text display-6 fw-bold">{{ $totalMuestras }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-beaker style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Asignadas a mi equipo</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado muestreo']) }}" class="text-decoration-none">
                <div class="card bg-warning bg-gradient text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Pendientes</h5>
                                <p class="card-text display-6 fw-bold">{{ $pendientes }}</p>
                            </div>
                            <div class="bg-dark bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-clock style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Por procesar</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'en revision muestreo']) }}" class="text-decoration-none">
                <div class="card bg-info bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">En Proceso</h5>
                                <p class="card-text display-6 fw-bold">{{ $enProceso }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-arrow-path style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">En revisión</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="{{ request()->fullUrlWithQuery(['estado' => 'muestreado']) }}" class="text-decoration-none">
                <div class="card bg-success bg-gradient text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title text-uppercase small">Finalizadas</h5>
                                <p class="card-text display-6 fw-bold">{{ $finalizadas }}</p>
                            </div>
                            <div class="bg-white bg-opacity-25 p-3 rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                                <x-heroicon-o-check-circle style="width: 20px; height: 20px;"/>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="small">Completadas</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- Contenido principal --}}
    <div class="row g-4">
        {{-- Muestras asignadas --}}
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Muestras Asignadas</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <!-- Filtro de Estado -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterEstadoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-filter me-1"></i> Estado
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterEstadoDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'all', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Todos</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'coordinado muestreo', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Pendientes</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'en revision muestreo', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">En proceso</a></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'muestreado', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Finalizados</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['estado' => 'proximos', 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Próximos 3 días</a></li>
                                </ul>
                            </div>
                            
                            <!-- Filtro de Muestreador -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterMuestreadorDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-1"></i> Muestreador
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterMuestreadorDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['muestreador' => 'all', 'estado' => request('estado', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">Todos</a></li>
                                    @foreach($muestreadores as $muestreador)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['muestreador' => $muestreador->usu_codigo, 'estado' => request('estado', 'all'), 'vehiculo' => request('vehiculo', 'all'), 'zona' => request('zona', 'all')]) }}">{{ $muestreador->usu_descripcion }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Filtro de Vehículo -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterVehiculoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-car me-1"></i> Vehículo
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterVehiculoDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['vehiculo' => 'all', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'zona' => request('zona', 'all')]) }}">Todos</a></li>
                                    @foreach($vehiculosDisponibles as $vehiculo)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['vehiculo' => $vehiculo->id, 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'zona' => request('zona', 'all')]) }}">{{ $vehiculo->patente }} - {{ $vehiculo->marca }} {{ $vehiculo->modelo }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Filtro de Zona -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterZonaDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-map-marker-alt me-1"></i> Zona
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterZonaDropdown">
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['zona' => 'all', 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all')]) }}">Todas</a></li>
                                    @foreach($zonasDisponibles as $zona)
                                    <li><a class="dropdown-item filter-option" href="{{ request()->fullUrlWithQuery(['zona' => $zona->zon_codigo, 'estado' => request('estado', 'all'), 'muestreador' => request('muestreador', 'all'), 'vehiculo' => request('vehiculo', 'all')]) }}">{{ $zona->zon_descripcion }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                            
                            <!-- Botón para limpiar filtros -->
                            @if(request('estado') != 'all' || request('muestreador') != 'all' || request('vehiculo') != 'all' || request('zona') != 'all')
                            <a href="{{ route('dashboard.muestreo') }}" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-times me-1"></i> Limpiar
                            </a>
                            @endif
                            
                            <!-- Botón para exportar -->
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalExportar">
                                <i class="fas fa-file-excel me-1"></i> Exportar
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Muestras asignadas a mi o a mi equipo
                        @if(isset($esDiaUno) && $esDiaUno)
                            <span class="badge bg-info ms-2">Filtrado por mes actual (día 1)</span>
                        @endif
                    </p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Cotización</th>
                                    <th>Descripción</th>
                                    <th>Fecha Muestreo</th>
                                    <th>Responsables</th>
                                    <th class="pe-4 d-flex flex-column align-items-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($muestras as $muestra)
                                <tr>
                                    <td class="ps-4 fw-bold">
                                        <a href="/show/{{ $muestra->cotizacion->coti_num }}" class="text-primary">
                                            {{ $muestra->cotizacion->coti_num ?? 'N/A' }}
                                        </a>
                                    </td>
                                    <td style="max-width: 200px;" title="{{ $muestra->cotio_descripcion }}">
                                        <a href="{{ route('muestras.ver', [
                                            'cotizacion' => $muestra->cotizacion->coti_num,
                                            'item' => $muestra->cotio_item,
                                            'instance' => $muestra->instance_number
                                        ]) }}" class="text-primary">
                                            {{ $muestra->cotio_descripcion }}
                                        </a>
                                    </td>
                                    <td>
                                        @if($muestra->fecha_muestreo)
                                            <span class="d-block">{{ $muestra->fecha_muestreo->format('d/m/Y') }}</span>
                                            <small class="text-muted">{{ $muestra->fecha_muestreo->format('H:i') }}</small>
                                        @else
                                            <span class="text-muted">Sin fecha</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($muestra->responsablesMuestreo->count() > 0)
                                            <div class="avatar-group">
                                                @foreach($muestra->responsablesMuestreo as $responsable)
                                                <span class="avatar avatar-xs" data-bs-toggle="tooltip" title="{{ $responsable->usu_descripcion }}">
                                                    {{ substr($responsable->usu_descripcion, 0, 1) }}{{ substr(strstr($responsable->usu_descripcion, ' '), 1, 1) }}
                                                </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted">Sin asignar</span>
                                        @endif
                                    </td>
                                    <td class="pe-4">
                                        @php
                                            $badgeColor = match($muestra->cotio_estado) {
                                                'coordinado muestreo' => 'warning text-dark',
                                                'en revision muestreo' => 'info text-dark',
                                                'suspension' => 'danger text-white',
                                                'muestreado' => 'success',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-1">
                                            <span class="badge rounded-pill bg-{{ $badgeColor }} text-capitalize">
                                                {{ str_replace('_', ' ', $muestra->cotio_estado) }}
                                            </span>
                                            
                                            @if($muestra->enable_ot)
                                                <small class="badge bg-info text-white px-2 py-1 rounded-pill">
                                                    En OT
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                        <p class="mb-0">No hay muestras asignadas actualmente</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Mostrando {{ $muestras->firstItem() ?? 0 }} a {{ $muestras->lastItem() ?? 0 }} de {{ $muestras->total() }} muestras
                            </div>
                            <div>
                                {{ $muestras->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar con información complementaria --}}
        <div class="col-lg-4">
            {{-- Muestras próximas --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Estados de Muestras</h5>
                    <p class="text-muted small mb-0">Distribución por estado</p>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height: 200px;">
                        <canvas id="estadoMuestrasChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Pendientes</span>
                                <span class="badge bg-warning text-dark rounded-pill">{{ $pendientes }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>En Proceso</span>
                                <span class="badge bg-info text-dark rounded-pill">{{ $enProceso }}</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span>Finalizadas</span>
                                <span class="badge bg-success rounded-pill">{{ $finalizadas }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Muestras próximas --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Muestras Próximas</h5>
                    <p class="text-muted small mb-0">Próximos 3 días</p>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @forelse($muestrasProximas as $muestra)
                        <div class="list-group-item border-0 px-0 py-2">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-bold">#{{ $muestra->cotizacion->coti_num ?? 'N/A' }}</span>
                                <small class="text-muted">{{ $muestra->fecha_muestreo ? $muestra->fecha_muestreo->format('d/m H:i') : 'Sin fecha' }}</small>
                            </div>
                            <p class="mb-1 small text-truncate">{{ $muestra->cotio_descripcion }}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-{{ $muestra->cotio_estado == 'coordinado muestreo' ? 'warning text-dark' : ($muestra->cotio_estado == 'en revision muestreo' ? 'info text-dark' : 'success') }} small">
                                    {{ Str::title(str_replace('_', ' ', $muestra->cotio_estado)) }}
                                </span>
                                @if($muestra->vehiculo)
                                <span class="badge bg-light text-dark small">
                                    <i class="fas fa-car me-1"></i> {{ $muestra->vehiculo->patente }}
                                </span>
                                @endif
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <p class="mb-0 small">No hay muestras programadas para los próximos 3 días</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Vehículos asignados --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Vehículos Asignados</h5>
                    <p class="text-muted small mb-0">En uso por tu equipo</p>
                </div>
                <div class="card-body">
                    @forelse($vehiculosAsignados as $vehiculo)
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-light rounded p-2 me-3">
                            <i class="fas fa-car text-primary fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</h6>
                            <small class="text-muted">Patente: {{ $vehiculo->patente }}</small>
                        </div>
                        {{-- <span class="badge bg-info text-dark">
                            {{ $vehiculo->cotioInstancias->where('cotio_estado', '!=', 'finalizado')->count() }} muestras
                        </span> --}}
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-car-side fa-2x mb-2"></i>
                        <p class="mb-0 small">No hay vehículos asignados actualmente</p>
                    </div>
                    @endforelse
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
                            <i class="fas fa-tools text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">{{ $herramienta->nombre }}</h6>
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
                    <i class="fas fa-file-excel me-2"></i>Exportar Muestras a Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard.muestreo.exportar') }}" method="POST" id="formExportar">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">Seleccione el período para exportar las muestras:</p>
                    
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
                        <small>El archivo incluirá: N° Cotización, Nombre de la Muestra, Descripción, Responsables, Estado, Fechas de Inicio y Fin, Vehículo, Zona y Cliente.</small>
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
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    
        // Actualizar el texto de los botones dropdown según los filtros actuales
        const currentEstado = '{{ $estadoFiltro }}';
        const currentMuestreador = '{{ $muestreadorFiltro }}';
        const currentVehiculo = '{{ $vehiculoFiltro }}';
        const currentZona = '{{ $zonaFiltro }}';
        
        // Actualizar botón de Estado
        const filterEstadoDropdown = document.getElementById('filterEstadoDropdown');
        if (filterEstadoDropdown) {
            const estadoOptions = filterEstadoDropdown.nextElementSibling.querySelectorAll('.filter-option');
            estadoOptions.forEach(option => {
                if (option.getAttribute('href').includes(`estado=${currentEstado}`)) {
                    filterEstadoDropdown.innerHTML = `<i class="fas fa-filter me-1"></i> Estado: ${option.textContent}`;
                }
            });
        }
        
        // Actualizar botón de Muestreador
        const filterMuestreadorDropdown = document.getElementById('filterMuestreadorDropdown');
        if (filterMuestreadorDropdown && currentMuestreador !== 'all') {
            const muestreadorOptions = filterMuestreadorDropdown.nextElementSibling.querySelectorAll('.filter-option');
            muestreadorOptions.forEach(option => {
                if (option.getAttribute('href').includes(`muestreador=${currentMuestreador}`)) {
                    filterMuestreadorDropdown.innerHTML = `<i class="fas fa-user me-1"></i> ${option.textContent}`;
                }
            });
        }
        
        // Actualizar botón de Vehículo
        const filterVehiculoDropdown = document.getElementById('filterVehiculoDropdown');
        if (filterVehiculoDropdown && currentVehiculo !== 'all') {
            const vehiculoOptions = filterVehiculoDropdown.nextElementSibling.querySelectorAll('.filter-option');
            vehiculoOptions.forEach(option => {
                if (option.getAttribute('href').includes(`vehiculo=${currentVehiculo}`)) {
                    filterVehiculoDropdown.innerHTML = `<i class="fas fa-car me-1"></i> ${option.textContent.split(' - ')[0]}`;
                }
            });
        }
        
        // Actualizar botón de Zona
        const filterZonaDropdown = document.getElementById('filterZonaDropdown');
        if (filterZonaDropdown && currentZona !== 'all') {
            const zonaOptions = filterZonaDropdown.nextElementSibling.querySelectorAll('.filter-option');
            zonaOptions.forEach(option => {
                if (option.getAttribute('href').includes(`zona=${currentZona}`)) {
                    filterZonaDropdown.innerHTML = `<i class="fas fa-map-marker-alt me-1"></i> ${option.textContent}`;
                }
            });
        }

        // Verificar si el canvas existe
        const canvas = document.getElementById('estadoMuestrasChart');
        if (!canvas) {
            console.error('No se encontró el elemento canvas para el gráfico');
            return;
        }

        // Verificar los datos
        const datosGrafico = {
            pendientes: {{ $pendientes }},
            enProceso: {{ $enProceso }},
            finalizadas: {{ $finalizadas }}
        };
        // console.log('Datos del gráfico:', datosGrafico);

        // Gráfico de estados
        try {
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pendientes', 'En Proceso', 'Finalizadas'],
                    datasets: [{
                        data: [
                            datosGrafico.pendientes,
                            datosGrafico.enProceso,
                            datosGrafico.finalizadas
                        ],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(13, 202, 240, 0.7)',
                            'rgba(25, 135, 84, 0.7)'
                        ],
                        borderColor: [
                            'rgba(255, 193, 7, 1)',
                            'rgba(13, 202, 240, 1)',
                            'rgba(25, 135, 84, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        } catch (error) {
            console.error('Error al crear el gráfico:', error);
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