<div class="op-documento">
@once
    @if($muestras->contains(fn($c) => !empty($c->coti_cuotas)))
        <p class="small text-muted mb-2 d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-info text-white"><x-heroicon-o-check style="width: 10px; height: 10px;" /></span>
            <span><strong>Cuotas</strong> = cotización con <strong>cuotas</strong> (coordinar a lo largo del plan).</span>
        </p>
    @endif
@endonce
@foreach($muestras as $coti)
@php
    $estado = trim($coti->coti_estado);
    $badgeClass = match ($estado) {
        'A' => 'bg-success',
        'E' => 'bg-warning text-dark',
        'S' => 'bg-danger',
        default => 'bg-secondary'
    };
    $estadoText = match ($estado) {
        'A' => 'Aprobado',
        'E' => 'En espera',
        'S' => 'Rechazado',
        default => $estado
    };
    
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

<div class="card mb-2 op-documento-card documento-card
    @if($coti->has_suspension) border-start border-danger border-3
    @elseif($coti->has_priority) border-start border-warning border-3
    @elseif(! empty($coti->coti_cuotas)) border-start border-info border-3
    @else border-start border-3
    @endif">
    
    <div class="card-body py-2 px-3">
        <!-- Línea principal: Título, Info rápida y Acciones -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <!-- Título y badges -->
            <div class="d-flex align-items-center flex-wrap gap-1">
                <button class="btn btn-link text-decoration-none p-0 d-flex align-items-center" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#tabla-{{ $coti->coti_num }}" 
                        onclick="toggleChevron('chevron-{{ $coti->coti_num }}')">
                    <span class="fw-bold text-primary me-1">#{{ $coti->coti_num }}</span>
                    <x-heroicon-o-chevron-down id="chevron-{{ $coti->coti_num }}" class="text-primary chevron-icon" style="width: 14px; height: 14px;" />
                </button>
                <span class="badge {{ $badgeClass }} badge-sm">{{ $estadoText }}</span>
                @if($coti->has_suspension)
                    <span class="badge bg-danger badge-sm">Suspendida</span>
                @elseif($coti->has_priority)
                    <span class="badge bg-warning text-dark badge-sm">
                        <x-heroicon-o-star style="width: 10px; height: 10px;" /> Prioritaria
                    </span>
                @endif
                @include('muestras.partials.cuotas-cotizacion-badge', ['coti' => $coti])
                <span class="text-muted small d-none d-md-inline">|</span>
                <span class="small text-truncate" style="max-width: 200px;">{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</span>
                <span class="small text-muted d-none d-lg-inline">- {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</span>
            </div>
            
            <!-- Progreso compacto -->
            <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-center" style="max-width: 250px; min-width: 150px;">
                @if($coti->total_instancias > 0)
                    <div class="progress flex-grow-1" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: {{ $coti->porcentaje_progreso['muestreadas'] }}%" title="Muestreadas"></div>
                        <div class="progress-bar bg-info" style="width: {{ $coti->porcentaje_progreso['en_revision'] }}%" title="En revisión"></div>
                        <div class="progress-bar bg-warning" style="width: {{ $coti->porcentaje_progreso['coordinadas'] }}%" title="Coordinadas"></div>
                    </div>
                    <small class="text-nowrap text-muted">{{ $coti->instancias_completadas }}/{{ $coti->total_instancias }}</small>
                @else
                    <small class="text-muted">Sin muestras</small>
                @endif
            </div>
            
            <!-- Botones de acción -->
            <div class="btn-group btn-group-sm">
                @if(userHasRole('coordinador_muestreo') || Auth::user()->usu_nivel >= 900)
                    <a href="{{ url('/show/'.$coti->coti_num) }}" class="btn btn-outline-primary btn-xs" title="Gestionar">
                        <x-heroicon-o-pencil style="width: 14px; height: 14px;" />
                    </a>
                @endif
                <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}" class="btn btn-outline-secondary btn-xs" title="Detalles">
                    <x-heroicon-o-document-magnifying-glass style="width: 14px; height: 14px;" />
                </a>
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($coti)) }}" 
                   target="_blank" class="btn btn-outline-info btn-xs" title="Mapa">
                    <x-heroicon-o-map style="width: 14px; height: 14px;" />
                </a>
            </div>
        </div>
        
        <!-- Segunda línea: Info adicional compacta (solo desktop) -->
        <div class="d-none d-md-flex flex-wrap gap-3 mt-1 small text-muted">
            @if($coti->coti_establecimiento)
                <span><x-heroicon-o-building-office style="width: 12px; height: 12px;" /> {{ Str::limit($coti->coti_establecimiento, 25) }}</span>
            @endif
            <span><x-heroicon-o-calendar style="width: 12px; height: 12px;" /> {{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : 'Pendiente' }}</span>
            @if($coti->coti_fechafin)
                <span class="{{ \Carbon\Carbon::parse($coti->coti_fechafin)->isPast() ? 'text-danger' : '' }}">
                    <x-heroicon-o-clock style="width: 12px; height: 12px;" /> Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}
                </span>
            @endif
            <span><x-heroicon-o-user style="width: 12px; height: 12px;" /> {{ $coti->responsable->usu_descripcion ?? 'Sin asignar' }}</span>
        </div>
    </div>

    <!-- Contenido colapsable compacto -->
    <div id="tabla-{{ $coti->coti_num }}" class="collapse">
        <div class="card-body py-2 px-3 border-top bg-light">
            <div class="row g-2 small">
                <!-- Info en móvil (solo visible en collapse) -->
                <div class="col-12 d-md-none">
                    <div class="d-flex flex-wrap gap-2 text-muted mb-2">
                        <span><strong>Establecimiento:</strong> {{ $coti->coti_establecimiento ?? 'N/A' }}</span>
                        <span><strong>Matriz:</strong> {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</span>
                        <span><strong>Responsable:</strong> {{ $coti->responsable->usu_descripcion ?? 'Sin asignar' }}</span>
                    </div>
                </div>
                
                <div class="col-12 col-md-6">
                    <strong class="text-muted">Dirección:</strong> 
                    {{ \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($coti) ?: '—' }}
                </div>
                @if($coti->coti_observaciones)
                    <div class="col-12 col-md-6">
                        <strong class="text-muted">Obs:</strong> {{ Str::limit($coti->coti_observaciones, 80) }}
                    </div>
                @endif
            </div>
            
            <!-- Muestras/Tareas compactas -->
            @if($coti->tareas && $coti->tareas->where('cotio_subitem', 0)->isNotEmpty())
                <div class="mt-2">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($coti->tareas->where('cotio_subitem', 0)->reject(function ($tarea) {
                            return in_array(trim($tarea->cotio_descripcion), [
                                'TRABAJO TECNICO EN CAMPO',
                                'TRABAJOS EN CAMPO NOCTURNO - VIATICOS',
                                'VIATICOS'
                            ]);
                        })->take(8) as $tarea)
                            @php
                                $instanciasCount = $tarea->instancias->count();
                                $instanciasCompletadas = $tarea->instancias->whereIn('cotio_estado', ['muestreado', 'completado'])->count();
                                $estadoTarea = strtolower($tarea->instancias->first()?->cotio_estado ?? 'pendiente');
                                $badgeTarea = match($estadoTarea) {
                                    'muestreado', 'completado' => 'bg-success',
                                    'coordinado muestreo' => 'bg-warning text-dark',
                                    'en revision muestreo' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $badgeTarea }}" title="{{ $tarea->cotio_descripcion }} ({{ $instanciasCompletadas }}/{{ $instanciasCount }})">
                                {{ $tarea->cotio_cantidad }}: {{ Str::limit($tarea->cotio_descripcion, 15) }}
                            </span>
                        @endforeach
                        @if($coti->tareas->where('cotio_subitem', 0)->count() > 8)
                            <span class="badge bg-light text-dark">+{{ $coti->tareas->where('cotio_subitem', 0)->count() - 8 }} más</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endforeach
</div>

<div class="ucrud-panel ucrud-pagination mt-3">
    {{ $muestras->links() }}
</div>

@push('scripts')
<script>
    function toggleChevron(iconId) {
        const icon = document.getElementById(iconId);
        if (icon) icon.classList.toggle('rotated');
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.collapse.show').forEach(el => {
            const iconId = `chevron-${el.id.replace('tabla-', '')}`;
            document.getElementById(iconId)?.classList.add('rotated');
        });
        [].slice.call(document.querySelectorAll('[title]')).forEach(el => {
            new bootstrap.Tooltip(el, { container: 'body', placement: 'bottom' });
        });
    });
</script>
@endpush
