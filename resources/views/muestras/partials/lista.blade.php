<div class="d-none d-lg-block">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th width="100">Muestra</th>
                    <th>Cliente</th>
                    <th width="140" class="text-center">Muestras</th>
                    <th width="120" class="text-center">Fecha</th>
                    <th width="150" class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($muestras as $coti)
                    <tr class="@if($coti->has_suspension) table-danger @endif @if($coti->has_priority) table-warning @endif" 
                        style="@if($coti->has_suspension) border-left: 4px solid #dc3545; @elseif($coti->has_priority) border-left: 4px solid #ffc107; @endif">
                        <td>
                            <div class="fw-bold">#{{ $coti->coti_num }}
                                @if($coti->has_suspension)
                                    <span class="badge bg-danger ms-2">Suspendida</span>
                                @elseif($coti->has_priority)
                                    <span class="badge bg-warning text-dark">
                                        <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                        Prioritaria
                                    </span>
                                @endif
                            </div>
                            <small class="text-muted">{{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</small>
                            <small class="text-muted">- {{ $coti->coti_descripcion ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <div>{{ $coti->coti_empresa }}</div>
                            @if($coti->coti_establecimiento)
                                <small class="text-muted">{{ $coti->coti_establecimiento }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($coti->total_instancias > 0)
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 20px;">
                                        <!-- Segmento de muestreadas (verde) -->
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['muestreadas'] }}%" 
                                             title="Muestreadas: {{ round($coti->porcentaje_progreso['muestreadas']) }}%">
                                        </div>
                                        
                                        <!-- Segmento en revisión (azul) -->
                                        <div class="progress-bar bg-info" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['en_revision'] }}%" 
                                             title="En revisión: {{ round($coti->porcentaje_progreso['en_revision']) }}%">
                                        </div>
                                        
                                        <!-- Segmento coordinadas (amarillo) -->
                                        <div class="progress-bar bg-warning" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['coordinadas'] }}%" 
                                             title="Coordinadas: {{ round($coti->porcentaje_progreso['coordinadas']) }}%">
                                        </div>
                                    </div>
                                    <small class="text-nowrap">
                                        {{ $coti->instancias_completadas }}/{{ $coti->total_instancias }}
                                    </small>
                                </div>
                                @if($coti->porcentaje_progreso['total'] > 0 && $coti->porcentaje_progreso['total'] < 100)
                                    <small class="d-block mt-1 @if($coti->has_suspension) text-danger fw-bold @else text-muted @endif">
                                        @if($coti->has_priority)
                                            <x-heroicon-o-star style="width: 14px; height: 14px;" class="text-warning me-1" />
                                        @endif
                                        {{ round($coti->porcentaje_progreso['total']) }}%
                                        @if($coti->has_suspension)
                                            <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                        @endif
                                    </small>
                                @endif
                            @else
                                <span class="badge bg-light text-dark">Sin muestras</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div>{{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : '-' }}</div>
                            @if($coti->coti_fechafin)
                                <small class="text-muted">Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="{{ url('/show/'.$coti->coti_num) }}" 
                                   class="btn btn-sm btn-outline-primary" 
                                   data-bs-toggle="tooltip" 
                                   title="Gestionar muestras"
                                   data-bs-placement="bottom">
                                   <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                </a>
                                <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}" 
                                   class="btn btn-sm btn-outline-secondary" 
                                   data-bs-toggle="tooltip" 
                                   title="Ver detalles"
                                   data-bs-placement="bottom">
                                    <x-heroicon-o-document-magnifying-glass style="width: 15px; height: 15px;" />
                                </a>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($coti->coti_direccioncli.', '.$coti->coti_localidad.', '.$coti->coti_partido) }}" 
                                   class="btn btn-sm btn-outline-info" 
                                   data-bs-toggle="tooltip" 
                                   title="Ver en mapa"
                                   data-bs-placement="bottom">  
                                   <x-heroicon-o-map style="width: 15px; height: 15px;" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Vista móvil -->
<div class="d-block d-lg-none">
    <div class="row g-3">
        @foreach($muestras as $coti)
            @php
                // Calcular el número de muestras originales (matrices)
                $totalMuestras = $coti->tareas->where('cotio_subitem', 0)
                    ->reject(function ($tarea) {
                        $descripcion = trim($tarea->cotio_descripcion);
                        return in_array($descripcion, [
                            'TRABAJO TECNICO EN CAMPO',
                            'TRABAJOS EN CAMPO NOCTURNO - VIATICOS',
                            'VIATICOS'
                        ]);
                    })->count();
            @endphp
            <div class="col-12">
                <div class="card shadow-sm border-start border-4 
                    @if($coti->has_suspension) border-danger
                    @elseif($coti->has_priority) border-warning
                    @elseif($coti->coti_estado == 'A') border-success
                    @elseif($coti->coti_estado == 'E') border-warning
                    @elseif($coti->coti_estado == 'S') border-danger
                    @else border-secondary @endif">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">#{{ $coti->coti_num }}
                                    @if($coti->has_suspension)
                                        <span class="badge bg-danger ms-2">Suspendida</span>
                                    @elseif($coti->has_priority)
                                        <span class="badge bg-warning text-dark">
                                            <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                            Prioritaria
                                        </span>
                                    @endif
                                </h5>
                                <span class="badge 
                                    @if($coti->coti_estado == 'A') bg-success
                                    @elseif($coti->coti_estado == 'E') bg-warning
                                    @elseif($coti->coti_estado == 'S') bg-danger
                                    @else bg-secondary @endif">
                                    {{ trim($coti->coti_estado) }}
                                </span>
                            </div>
                            <small class="text-muted">
                                {{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : 'Pendiente' }}
                            </small>
                        </div>
                        
                        <h6 class="card-subtitle mb-2 text-muted">{{ $coti->coti_empresa }}</h6>
                        
                        @if($coti->coti_establecimiento)
                            <p class="small mb-1"><i class="fas fa-building me-1"></i> {{ $coti->coti_establecimiento }}</p>
                        @endif
                        
                        <div class="my-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Progreso de muestreo</span>
                                <span class="small fw-bold @if($coti->has_suspension) text-danger @endif">
                                    @if($coti->has_priority)
                                        <x-heroicon-o-star style="width: 14px; height: 14px;" class="text-warning me-1" />
                                    @endif
                                    {{ round($coti->porcentaje_progreso['total']) }}%
                                    @if($coti->has_suspension)
                                        <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar 
                                    @if($coti->has_suspension) bg-danger
                                    @elseif($coti->porcentaje_progreso['total'] == 100) bg-success
                                    @elseif($coti->porcentaje_progreso['total'] > 0) bg-info
                                    @else bg-light @endif" 
                                    style="width: {{ $coti->porcentaje_progreso['total'] }}%">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">{{ $totalMuestras }} matrices</small>
                                <small class="text-muted">{{ $coti->instancias_completadas }}/{{ $coti->total_instancias }} muestras</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-muted me-2">
                                    <i class="fas fa-flask me-1"></i> {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}
                                </span>
                                @if($coti->coti_fechafin)
                                    <span class="small text-muted">
                                        <i class="far fa-clock me-1"></i> Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                            <div class="btn-group btn-group-sm" style="width: 100%; max-width: 200px;">
                                <a href="{{ url('/show/'.$coti->coti_num) }}" class="btn btn-sm btn-outline-primary">
                                    <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                </a>
                                <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}" class="btn btn-sm btn-outline-secondary">
                                    <x-heroicon-o-document-magnifying-glass style="width: 15px; height: 15px;" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="d-flex justify-content-center mt-4">
    {{ $muestras->links() }}
</div>

@push('styles')
<style>
    .progress {
        background-color: #f0f3f5;
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
    }
    .badge {
        font-weight: 500;
        letter-spacing: 0.5px;
    }
    .text-danger {
        color: #dc3545 !important;
    }
    .progress-bar {
        position: relative;
        overflow: visible;
    }
    
    .progress-bar + .progress-bar {
        border-left: 1px solid rgba(255,255,255,0.3);
    }
    
    .progress-legend {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 0.5rem;
        font-size: 0.75rem;
    }
    
    .progress-legend-item {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .progress-legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
    }
    
    /* Estilo para filas prioritarias */
    tr[style*="border-left: 4px solid #ffc107"] {
        background-color: rgba(255, 193, 7, 0.05);
    }
    
    /* Estilo para cards prioritarias en móvil */
    .border-warning {
        background-color: rgba(255, 193, 7, 0.05);
    }
</style>
@endpush

@push('scripts')
<script>
    // Inicializar tooltips
    document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips para los segmentos de progreso
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('.progress-bar[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                container: 'body',
                placement: 'top'
            });
        });
    });
</script>
@endpush