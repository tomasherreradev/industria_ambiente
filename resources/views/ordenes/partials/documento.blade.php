@foreach($ordenes as $numCoti => $data)
    @php
        // Usar cotizacion directamente del array (funciona con o sin instancias)
        $coti = $data['cotizacion'];
        $instancias = collect($data['instancias'] ?? []);
        $muestras = $instancias->where('cotio_subitem', '=', 0)->where('enable_ot', '=', 1);
        $tieneInstancias = $instancias->isNotEmpty();
        
        // Calcular progreso
        $analizadas = $muestras->where('cotio_estado_analisis', 'analizado')->count();
        $enProceso = $muestras->where('cotio_estado_analisis', 'en revision analisis')->count();
        $coordinadas = $muestras->where('cotio_estado_analisis', 'coordinado analisis')->count();
        $total = $muestras->count();
        
        $porcentajes = [
            'analizadas' => $total > 0 ? ($analizadas / $total) * 100 : 0,
            'en_proceso' => $total > 0 ? ($enProceso / $total) * 100 : 0,
            'coordinadas' => $total > 0 ? ($coordinadas / $total) * 100 : 0,
        ];
        
        $hasSuspension = $data['has_suspension'] ?? false;
        $hasPriority = $data['has_priority'] ?? false;
    @endphp
    
    @if($coti)
    <div class="card mb-2 shadow-sm documento-card
        @if($hasSuspension) border-start border-danger border-3
        @elseif($hasPriority) border-start border-warning border-3
        @else border-start border-3
        @endif">
        
        <div class="card-body py-2 px-3">
            <!-- Línea principal: Título, Info rápida y Acciones -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <!-- Título y badges -->
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <button class="btn btn-link text-decoration-none p-0 d-flex align-items-center" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#tabla-{{ $numCoti }}" 
                            onclick="toggleChevron('chevron-{{ $numCoti }}')">
                        <span class="fw-bold text-primary me-1">#{{ $coti->coti_num }}</span>
                        <x-heroicon-o-chevron-down id="chevron-{{ $numCoti }}" class="text-primary chevron-icon" style="width: 14px; height: 14px;" />
                    </button>
                    @if($hasSuspension)
                        <span class="badge bg-danger badge-sm">Suspendida</span>
                    @elseif($hasPriority)
                        <span class="badge bg-warning text-dark badge-sm">
                            <x-heroicon-o-star style="width: 10px; height: 10px;" /> Prioritaria
                        </span>
                    @endif
                    <span class="text-muted small d-none d-md-inline">|</span>
                    <span class="small text-truncate" style="max-width: 200px;">{{ $coti->coti_empresa }}</span>
                    <span class="small text-muted d-none d-lg-inline">- {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</span>
                </div>
                
                <!-- Progreso compacto -->
                <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-center" style="max-width: 250px; min-width: 150px;">
                    @if($total > 0)
                        <div class="progress flex-grow-1" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $porcentajes['analizadas'] }}%" title="Analizadas: {{ $analizadas }}"></div>
                            <div class="progress-bar bg-info" style="width: {{ $porcentajes['en_proceso'] }}%" title="En revisión: {{ $enProceso }}"></div>
                            <div class="progress-bar bg-warning" style="width: {{ $porcentajes['coordinadas'] }}%" title="Coordinadas: {{ $coordinadas }}"></div>
                        </div>
                        <small class="text-nowrap text-muted">{{ $analizadas + $enProceso + $coordinadas }}/{{ $total }}</small>
                    @elseif(!$tieneInstancias)
                        <span class="badge bg-info text-white">
                            <x-heroicon-o-plus-circle style="width: 12px; height: 12px;" class="me-1" />
                            Nueva
                        </span>
                    @else
                        <small class="text-muted">Sin OTs activas</small>
                    @endif
                </div>
                
                <!-- Botones de acción -->
                <div class="btn-group btn-group-sm">
                    <a href="{{ url('/ordenes/' . $numCoti) }}" class="btn btn-outline-primary btn-xs" title="Gestionar orden">
                        <x-heroicon-o-pencil style="width: 14px; height: 14px;" />
                    </a>
                    <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}" class="btn btn-outline-secondary btn-xs" title="Ver cotización">
                        <x-heroicon-o-document-magnifying-glass style="width: 14px; height: 14px;" />
                    </a>
                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($coti->coti_direccioncli.', '.$coti->coti_localidad.', '.$coti->coti_partido) }}" 
                       target="_blank" class="btn btn-outline-info btn-xs" title="Ver en mapa">
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
            </div>
        </div>

        <!-- Contenido colapsable compacto -->
        <div id="tabla-{{ $numCoti }}" class="collapse">
            <div class="card-body py-2 px-3 border-top bg-light">
                <div class="row g-2 small">
                    <!-- Info en móvil (solo visible en collapse) -->
                    <div class="col-12 d-md-none">
                        <div class="d-flex flex-wrap gap-2 text-muted mb-2">
                            <span><strong>Establecimiento:</strong> {{ $coti->coti_establecimiento ?? 'N/A' }}</span>
                            <span><strong>Matriz:</strong> {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</span>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <strong class="text-muted">Dirección:</strong> 
                        {{ $coti->coti_direccioncli }}, {{ $coti->coti_localidad }}
                    </div>
                    @if($coti->coti_observaciones)
                        <div class="col-12 col-md-6">
                            <strong class="text-muted">Obs:</strong> {{ Str::limit($coti->coti_observaciones, 80) }}
                        </div>
                    @endif
                </div>
                
                <!-- Órdenes de trabajo compactas -->
                @if($muestras->isNotEmpty())
                    <div class="mt-2">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <strong class="small text-muted">
                                <x-heroicon-o-clipboard-document-list style="width: 14px; height: 14px;" />
                                Órdenes de trabajo ({{ $muestras->count() }})
                            </strong>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($muestras->take(10) as $muestra)
                                @php
                                    $estadoMuestra = strtolower($muestra->cotio_estado_analisis ?? 'pendiente');
                                    $badgeMuestra = match($estadoMuestra) {
                                        'analizado' => 'bg-success',
                                        'en revision analisis' => 'bg-info',
                                        'coordinado analisis' => 'bg-warning text-dark',
                                        'suspension' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    $textoEstado = match($estadoMuestra) {
                                        'analizado' => 'Analizado',
                                        'en revision analisis' => 'En revisión',
                                        'coordinado analisis' => 'Coordinado',
                                        'suspension' => 'Suspendido',
                                        default => 'Pendiente'
                                    };
                                @endphp
                                <a href="{{ route('categoria.verOrden', [
                                        'cotizacion' => $numCoti,
                                        'item' => $muestra->cotio_item,
                                        'instance' => $muestra->instance_number
                                    ]) }}" 
                                   class="badge {{ $badgeMuestra }} text-decoration-none ot-badge" 
                                   title="{{ $muestra->cotio_descripcion }} - {{ $textoEstado }}&#10;OT: {{ $muestra->otn ?? 'N/A' }}&#10;Fecha: {{ $muestra->fecha_muestreo ? $muestra->fecha_muestreo->format('d/m/Y') : 'Sin fecha' }}">
                                    @if($muestra->es_priori)
                                        <x-heroicon-o-star style="width: 10px; height: 10px;" />
                                    @endif
                                    {{ $muestra->otn ? Str::limit($muestra->otn, 10) : 'OT' }}: {{ Str::limit($muestra->cotio_descripcion, 20) }}
                                </a>
                            @endforeach
                            @if($muestras->count() > 10)
                                <span class="badge bg-light text-dark">+{{ $muestras->count() - 10 }} más</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <span class="small text-muted">
                            <x-heroicon-o-information-circle style="width: 14px; height: 14px;" class="me-1" />
                            Sin órdenes de trabajo creadas
                        </span>
                        <a href="{{ url('/ordenes/' . $numCoti) }}" class="btn btn-xs btn-outline-primary">
                            <x-heroicon-o-plus style="width: 12px; height: 12px;" class="me-1" />
                            Crear OTs
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
@endforeach

<div class="d-flex justify-content-center mt-3">
    {{ $pagination->links() }}
</div>

@push('styles')
<style>
    .documento-card {
        transition: transform 0.15s, box-shadow 0.15s;
    }
    .documento-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.12) !important;
    }
    .chevron-icon {
        transition: transform 0.2s ease;
    }
    .chevron-icon.rotated {
        transform: rotate(180deg);
    }
    .badge-sm {
        font-size: 0.7rem;
        padding: 0.2em 0.5em;
    }
    .btn-xs {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    .progress {
        background-color: #e9ecef;
        border-radius: 4px;
    }
    .progress-bar + .progress-bar {
        border-left: 1px solid rgba(255,255,255,0.3);
    }
    .border-danger.border-3 {
        background-color: rgba(220, 53, 69, 0.02);
    }
    .border-warning.border-3 {
        background-color: rgba(255, 193, 7, 0.02);
    }
    .ot-badge {
        cursor: pointer;
        transition: transform 0.1s, opacity 0.1s;
    }
    .ot-badge:hover {
        transform: scale(1.05);
        opacity: 0.9;
    }
</style>
@endpush

@push('scripts')
<script>
    function toggleChevron(iconId) {
        const icon = document.getElementById(iconId);
        if (icon) icon.classList.toggle('rotated');
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar chevrons de collapses abiertos
        document.querySelectorAll('.collapse.show').forEach(el => {
            const iconId = `chevron-${el.id.replace('tabla-', '')}`;
            document.getElementById(iconId)?.classList.add('rotated');
        });
        // Inicializar tooltips
        [].slice.call(document.querySelectorAll('[title]')).forEach(el => {
            new bootstrap.Tooltip(el, { container: 'body', placement: 'top' });
        });
    });
</script>
@endpush
