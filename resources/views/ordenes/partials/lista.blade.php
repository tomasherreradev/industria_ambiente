
<div class="d-none d-lg-block">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="50"></th>
                            <th width="120">Cotización</th>
                            <th>Cliente</th>
                            <th width="140" class="text-center">Progreso</th>
                            <th width="120" class="text-center">Fecha</th>
                            <th width="150">Matriz</th>
                            <th width="150" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ordenes as $numCoti => $instanciaData)
                        @php
                            // Usar cotizacion directamente del array (funciona con o sin instancias)
                            $coti = $instanciaData['cotizacion'];
                            $instancias = collect($instanciaData['instancias'] ?? []);
                            $muestras = $instancias->where('cotio_subitem', '=', 0)->where('enable_ot', '=', 1);
                            $tieneInstancias = $instancias->isNotEmpty();
                            
                            // Calcular estados para la barra de progreso
                            $analizadas = $instancias->where('cotio_estado_analisis', 'analizado')->where('cotio_subitem', '=', 0)->count();
                            $enProceso = $instancias->where('cotio_estado_analisis', 'en revision analisis')->where('cotio_subitem', '=', 0)->count();
                            $coordinadas = $instancias->where('cotio_estado_analisis', 'coordinado analisis')->where('cotio_subitem', '=', 0)->count();
                            $total = $muestras->count();
                            
                            $porcentajes = [
                                'analizadas' => $total > 0 ? ($analizadas / $total) * 100 : 0,
                                'en_proceso' => $total > 0 ? ($enProceso / $total) * 100 : 0,
                                'coordinadas' => $total > 0 ? ($coordinadas / $total) * 100 : 0,
                                'total' => $total > 0 ? (($analizadas + $enProceso + $coordinadas) / $total) * 100 : 0
                            ];

                            // Determinar estado predominante para mostrar
                            $estadoPredominante = $instanciaData['estado_predominante'] ?? 'pendiente_coordinar';
                            $badgeColorEstado = match($estadoPredominante) {
                                'coordinado analisis' => 'bg-warning text-dark',
                                'en revision analisis' => 'bg-info text-white',
                                'analizado' => 'bg-success text-white',
                                'suspension' => 'bg-danger text-white',
                                'pendiente_coordinar' => 'bg-secondary text-white',
                                default => 'bg-secondary text-white',
                            };
                            $estadoTexto = match($estadoPredominante) {
                                'coordinado analisis' => 'Coordinada',
                                'en revision analisis' => 'En Revisión',
                                'analizado' => 'Finalizada',
                                'suspension' => 'Suspendida',
                                'pendiente_coordinar' => 'Pendiente',
                                default => 'Sin Estado',
                            };
                        @endphp
                        <tr class="orden-row @if($instanciaData['has_suspension']) table-danger @endif @if($instanciaData['has_priority']) table-warning @endif" 
                        style="@if($instanciaData['has_suspension']) border-left: 4px solid #dc3545; @endif @if($instanciaData['has_priority']) border-left: 4px solid #ffc107; @endif cursor: pointer;"
                        data-order="{{ $numCoti }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse-{{ $numCoti }}"
                        aria-expanded="false"
                        aria-controls="collapse-{{ $numCoti }}">
                            <td class="text-center">
                                <button class="btn btn-sm btn-link p-0 toggle-icon" type="button">
                                    <x-heroicon-o-chevron-right class="chevron-icon" style="width: 18px; height: 18px; transition: transform 0.2s;" />
                                </button>
                            </td>
                            <td>
                                <div class="fw-bold">#{{ $coti->coti_num }}
                                    @if($instanciaData['has_suspension'])
                                        <span class="badge bg-danger ms-2">Suspendida</span>
                                    @elseif($instanciaData['has_priority'])
                                        <span class="badge bg-warning text-dark">
                                            <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                            Prioritaria
                                        </span>
                                    @endif
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted">{{ $coti->coti_descripcion ?? 'N/A' }}</small>
                                    {{-- <span class="badge {{ $badgeColorEstado }}" style="font-size: 0.7em;">{{ $estadoTexto }}</span> --}}
                                </div>
                            </td>
                                <td>
                                    <div>{{ $coti->coti_empresa ?? 'N/A' }}</div>
                                    @if($coti->coti_establecimiento)
                                        <small class="text-muted">{{ $coti->coti_establecimiento }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($total > 0)
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 20px;">
                                                <!-- Segmento de analizadas (verde) -->
                                                <div class="progress-bar bg-success" 
                                                     role="progressbar" 
                                                     style="width: {{ $porcentajes['analizadas'] }}%" 
                                                     data-bs-toggle="tooltip" 
                                                     data-bs-placement="bottom"
                                                     title="Analizadas: {{ round($porcentajes['analizadas']) }}%">
                                                </div>
                                                
                                                <!-- Segmento en proceso (azul) -->
                                                <div class="progress-bar bg-info" 
                                                     role="progressbar" 
                                                     style="width: {{ $porcentajes['en_proceso'] }}%" 
                                                     data-bs-toggle="tooltip" 
                                                     data-bs-placement="bottom"
                                                     title="En proceso: {{ round($porcentajes['en_proceso']) }}%">
                                                </div>
                                                
                                                <!-- Segmento coordinadas (amarillo) -->
                                                <div class="progress-bar bg-warning" 
                                                     role="progressbar" 
                                                     style="width: {{ $porcentajes['coordinadas'] }}%" 
                                                     data-bs-toggle="tooltip" 
                                                     data-bs-placement="bottom"
                                                     title="Coordinadas: {{ round($porcentajes['coordinadas']) }}%">
                                                </div>
                                            </div>
                                            <small class="text-nowrap">
                                                {{ $analizadas + $enProceso + $coordinadas }}/{{ $total }}
                                            </small>
                                        </div>
                                        @if($porcentajes['total'] > 0 && $porcentajes['total'] < 100)
                                            <small class="d-block mt-1 @if($instanciaData['has_suspension']) text-danger fw-bold @else text-muted @endif">
                                                {{ round($porcentajes['total']) }}%
                                                @if($instanciaData['has_suspension'])
                                                    <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                                @endif
                                            </small>
                                        @endif
                                    @else
                                        @if(!$tieneInstancias)
                                            <span class="badge bg-info text-white">
                                                <x-heroicon-o-plus-circle style="width: 12px; height: 12px;" class="me-1" />
                                                Nuevo
                                            </span>
                                        @else
                                            <span class="badge bg-light text-dark">Sin OTs activas</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div>{{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : '-' }}</div>
                                    @if($coti->coti_fechafin)
                                        <small class="text-muted">Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td>{{ $coti->matriz->matriz_descripcion ?? 'N/A' }}</td>
                                <td class="text-center" onclick="event.stopPropagation();">
                                    <div class="btn-group" role="group">
                                        <a href="{{ url('/ordenes/' . $numCoti) }}" 
                                           class="btn btn-sm btn-outline-primary" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="bottom"
                                           title="Gestionar orden">
                                           <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <!-- Fila colapsable con las órdenes de trabajo -->
                            <tr class="collapse-row">
                                <td colspan="7" class="p-0 border-0">
                                    <div class="collapse" id="collapse-{{ $numCoti }}" style="will-change: height;">
                                        <div class="bg-light p-3 border-top" style="min-height: 0;">
                                            @if($muestras->count() > 0)
                                                <h6 class="mb-3 text-muted">
                                                    <x-heroicon-o-clipboard-document-list style="width: 16px; height: 16px;" class="me-1" />
                                                    Órdenes de trabajo ({{ $muestras->count() }})
                                                </h6>
                                                <div class="table-responsive" style="max-height: none;">
                                                    <table class="table table-sm table-bordered mb-0 bg-white" style="width: 100%; table-layout: auto;">
                                                        <thead class="table-secondary">
                                                            <tr>
                                                                <th style="width: 100px; min-width: 100px;">OT #</th>
                                                                <th style="min-width: 200px;">Descripción</th>
                                                                <th style="width: 120px; min-width: 120px;" class="text-center">Estado</th>
                                                                <th style="width: 130px; min-width: 130px;" class="text-center">Fecha Muestreo</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($muestras as $muestra)
                                                                @php
                                                                    $estadoMuestra = $muestra->cotio_estado_analisis ?? 'pendiente';
                                                                    $badgeMuestra = match($estadoMuestra) {
                                                                        'coordinado analisis' => 'bg-warning text-dark',
                                                                        'en revision analisis' => 'bg-info text-white',
                                                                        'analizado' => 'bg-success text-white',
                                                                        'suspension' => 'bg-danger text-white',
                                                                        default => 'bg-secondary text-white',
                                                                    };
                                                                    $textoMuestra = match($estadoMuestra) {
                                                                        'coordinado analisis' => 'Coordinado',
                                                                        'en revision analisis' => 'En Revisión',
                                                                        'analizado' => 'Analizado',
                                                                        'suspension' => 'Suspendido',
                                                                        default => 'Pendiente',
                                                                    };
                                                                @endphp
                                                                <tr class="@if($muestra->cotio_estado == 'suspension') table-danger @elseif($muestra->es_priori) table-warning @endif">
                                                                    <td class="fw-bold" style="padding: 10px !important;">
                                                                        {{ $muestra->otn ?? 'N/A' }}
                                                                    </td>
                                                                    <td style="padding: 10px !important;">
                                                                        {{ $muestra->cotio_descripcion ?? 'Sin descripción' }}
                                                                        @if($muestra->cotio_identificacion)
                                                                            <br><small class="text-muted">ID: {{ $muestra->cotio_identificacion }}</small>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-center" style="padding: 10px !important;">
                                                                        <span class="badge {{ $badgeMuestra }}">{{ $textoMuestra }}</span>
                                                                    </td>
                                                                    <td class="text-center" style="padding: 10px !important;">
                                                                        {{ $muestra->fecha_muestreo ? $muestra->fecha_muestreo->format('d/m/Y') : '-' }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="text-center py-4">
                                                    <x-heroicon-o-document-plus style="width: 48px; height: 48px;" class="text-muted mb-3" />
                                                    <h6 class="text-muted mb-2">Sin órdenes de trabajo</h6>
                                                    <p class="text-muted small mb-3">Esta cotización aún no tiene órdenes de trabajo creadas.</p>
                                                    <a href="{{ url('/ordenes/' . $numCoti) }}" class="btn btn-sm btn-primary">
                                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                                        Crear órdenes de trabajo
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Vista móvil -->
<div class="d-block d-lg-none">
    <div class="row g-3">
        @foreach($ordenes as $numCoti => $instanciaData)
            @php
                // Usar cotizacion directamente del array (funciona con o sin instancias)
                $coti = $instanciaData['cotizacion'];
                $instanciasMobile = collect($instanciaData['instancias'] ?? []);
                $muestrasMobile = $instanciasMobile->where('cotio_subitem', '=', 0)->where('enable_ot', '=', 1);
                $tieneInstanciasMobile = $instanciasMobile->isNotEmpty();
                
                // Calcular estados para la barra de progreso
                $analizadas = $muestrasMobile->where('cotio_estado_analisis', 'analizado')->count();
                $enProceso = $muestrasMobile->where('cotio_estado_analisis', 'en revision analisis')->count();
                $coordinadas = $muestrasMobile->where('cotio_estado_analisis', 'coordinado analisis')->count();
                $total = $muestrasMobile->count();
                
                $porcentajes = [
                    'analizadas' => $total > 0 ? ($analizadas / $total) * 100 : 0,
                    'en_proceso' => $total > 0 ? ($enProceso / $total) * 100 : 0,
                    'coordinadas' => $total > 0 ? ($coordinadas / $total) * 100 : 0,
                    'total' => $total > 0 ? (($analizadas + $enProceso + $coordinadas) / $total) * 100 : 0
                ];

                // Estado predominante para vista móvil
                $estadoPredominante = $instanciaData['estado_predominante'] ?? 'pendiente_coordinar';
                $badgeColorEstado = match($estadoPredominante) {
                    'coordinado analisis' => 'bg-warning text-dark',
                    'en revision analisis' => 'bg-info text-white',
                    'analizado' => 'bg-success text-white',
                    'suspension' => 'bg-danger text-white',
                    'pendiente_coordinar' => 'bg-secondary text-white',
                    default => 'bg-secondary text-white',
                };
                $estadoTexto = match($estadoPredominante) {
                    'coordinado analisis' => 'Coordinada',
                    'en revision analisis' => 'En Revisión',
                    'analizado' => 'Finalizada',
                    'suspension' => 'Suspendida',
                    'pendiente_coordinar' => 'Pendiente',
                    default => 'Sin Estado',
                };
            @endphp
            <div class="col-12">
                <div class="card shadow-sm border-start border-4 
                @if($instanciaData['has_suspension']) border-danger
                @elseif($instanciaData['has_priority']) border-warning
                @elseif($coti->coti_estado == 'A') border-success
                @elseif($coti->coti_estado == 'E') border-warning
                @elseif($coti->coti_estado == 'S') border-danger
                @else border-secondary @endif">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">#{{ $numCoti }}</h5>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    @if($instanciaData['has_suspension'])
                                        <span class="badge bg-danger">Suspendida</span>
                                    @elseif($instanciaData['has_priority'])
                                        <span class="badge bg-warning text-dark">
                                            <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                            Prioritaria
                                        </span>
                                    @endif
                                    <span class="badge {{ $badgeColorEstado }}">{{ $estadoTexto }}</span>
                                    <span class="badge 
                                        @if($coti->coti_estado == 'A') bg-success
                                        @elseif($coti->coti_estado == 'E') bg-warning
                                        @elseif($coti->coti_estado == 'S') bg-danger
                                        @else bg-secondary @endif">
                                        {{ trim($coti->coti_estado) }}
                                    </span>
                                </div>
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
                                <span class="small">Progreso de análisis</span>
                                <span class="small fw-bold" @if($instanciaData['has_suspension']) text-danger @endif>{{ round($porcentajes['total']) }}%</span>
                                @if($instanciaData['has_suspension'])
                                    <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                @endif
                            </div>
                            <div class="progress" style="height: 8px;">
                                @if($instanciaData['has_suspension']) 
                                    <div class="progress-bar bg-danger" 
                                         style="width: {{ $porcentajes['total'] }}%">
                                    </div>
                                @endif
                                <!-- Segmento de analizadas (verde) -->
                                <div class="progress-bar bg-success" 
                                     style="width: {{ $porcentajes['analizadas'] }}%">
                                </div>
                                
                                <!-- Segmento en proceso (azul) -->
                                <div class="progress-bar bg-info" 
                                     style="width: {{ $porcentajes['en_proceso'] }}%">
                                </div>
                                
                                <!-- Segmento coordinadas (amarillo) -->
                                <div class="progress-bar bg-warning" 
                                     style="width: {{ $porcentajes['coordinadas'] }}%">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                @if($total > 0)
                                    <small class="text-muted">{{ $total }} órdenes</small>
                                    <small class="text-muted">{{ $analizadas + $enProceso + $coordinadas }} completados</small>
                                @elseif(!$tieneInstanciasMobile)
                                    <small class="text-info">
                                        <x-heroicon-o-plus-circle style="width: 12px; height: 12px;" class="me-1" />
                                        Nueva cotización
                                    </small>
                                @else
                                    <small class="text-muted">Sin OTs activas</small>
                                @endif
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
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-sm btn-outline-secondary" 
                                        type="button"
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse-mobile-{{ $numCoti }}"
                                        aria-expanded="false">
                                    <x-heroicon-o-chevron-down class="chevron-icon-mobile" style="width: 15px; height: 15px; transition: transform 0.2s;" />
                                </button>
                                <a href="{{ url('/ordenes/' . $numCoti) }}" class="btn btn-sm btn-outline-primary">
                                    <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                </a>
                            </div>
                        </div>
                        
                        <!-- Sección colapsable con las órdenes de trabajo (móvil) -->
                        <div class="collapse mt-3" id="collapse-mobile-{{ $numCoti }}">
                            <hr class="my-2">
                            @if($muestrasMobile->count() > 0)
                                <h6 class="text-muted mb-2">
                                    <x-heroicon-o-clipboard-document-list style="width: 14px; height: 14px;" class="me-1" />
                                    Órdenes de trabajo ({{ $muestrasMobile->count() }})
                                </h6>
                                <div class="list-group list-group-flush">
                                    @foreach($muestrasMobile as $muestra)
                                        @php
                                            $estadoMuestra = $muestra->cotio_estado_analisis ?? 'pendiente';
                                            $badgeMuestra = match($estadoMuestra) {
                                                'coordinado analisis' => 'bg-warning text-dark',
                                                'en revision analisis' => 'bg-info text-white',
                                                'analizado' => 'bg-success text-white',
                                                'suspension' => 'bg-danger text-white',
                                                default => 'bg-secondary text-white',
                                            };
                                            $textoMuestra = match($estadoMuestra) {
                                                'coordinado analisis' => 'Coordinado',
                                                'en revision analisis' => 'En Revisión',
                                                'analizado' => 'Analizado',
                                                'suspension' => 'Suspendido',
                                                default => 'Pendiente',
                                            };
                                        @endphp
                                        <div class="list-group-item px-2 py-2 @if($muestra->cotio_estado == 'suspension') list-group-item-danger @elseif($muestra->es_priori) list-group-item-warning @endif">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong class="small">OT: {{ $muestra->otn ?? 'N/A' }}</strong>
                                                    @if($muestra->es_priori)
                                                        <x-heroicon-o-star style="width: 12px; height: 12px;" class="text-warning ms-1" />
                                                    @endif
                                                </div>
                                                <span class="badge {{ $badgeMuestra }}" style="font-size: 0.7em;">{{ $textoMuestra }}</span>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                {{ Str::limit($muestra->cotio_descripcion ?? 'Sin descripción', 40) }}
                                            </div>
                                            @if($muestra->fecha_muestreo)
                                                <div class="small text-muted">
                                                    <i class="far fa-calendar me-1"></i>{{ $muestra->fecha_muestreo->format('d/m/Y') }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-3">
                                    <x-heroicon-o-document-plus style="width: 32px; height: 32px;" class="text-muted mb-2" />
                                    <p class="text-muted small mb-2">Sin órdenes de trabajo</p>
                                    <a href="{{ url('/ordenes/' . $numCoti) }}" class="btn btn-sm btn-primary">
                                        <x-heroicon-o-plus style="width: 14px; height: 14px;" class="me-1" />
                                        Crear OTs
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $pagination->links() }}
</div>

<style>
    .progress {
        background-color: #f0f3f5;
    }
    .progress-bar + .progress-bar {
        border-left: 1px solid rgba(255,255,255,0.3);
    }
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }
    .table-hover tbody tr.orden-row:hover {
        background-color: #e9ecef;
    }
    .card {
        transition: transform 0.2s;
    }
    .card:hover {
        transform: translateY(-2px);
    }

    .table-warning {
        background-color: #fff3cd;
    }
    .table-warning:hover {
        background-color: #ffeeba !important;
    }

    /* Estilos para filas colapsables */
    .orden-row {
        cursor: pointer;
    }
    .orden-row:not(.collapsed) .chevron-icon,
    .orden-row[aria-expanded="true"] .chevron-icon {
        transform: rotate(90deg);
    }
    .collapse-row {
        background-color: transparent !important;
    }
    .collapse-row:hover {
        background-color: transparent !important;
    }
    .collapse-row td {
        padding: 0 !important;
    }
    
    /* Animación para el icono en móvil */
    [data-bs-toggle="collapse"][aria-expanded="true"] .chevron-icon-mobile {
        transform: rotate(180deg);
    }
    
    /* Estilos para la tabla interna de órdenes */
    .collapse .table-sm th,
    .collapse .table-sm td {
        padding: 0.4rem 0.5rem;
        font-size: 0.85rem;
    }
    
    /* Collapse sin animación para evitar el efecto visual de fuente que crece */
    .collapse-row .collapse {
        display: none;
        transition: none !important;
    }
    .collapse-row .collapse.show {
        display: block;
    }
    .collapse-row .collapse.collapsing {
        display: none;
        height: auto !important;
        transition: none !important;
    }
    .collapse-row .collapse .bg-light {
        overflow: hidden;
    }
    .collapse-row .collapse .table-responsive {
        overflow-x: auto;
        overflow-y: hidden;
        width: 100%;
    }
    .collapse-row .collapse .table {
        margin-bottom: 0;
        width: 100%;
    }
    
    /* Lista de órdenes en móvil */
    .list-group-item {
        border-radius: 0.375rem;
        margin-bottom: 0.25rem;
    }
</style>

<script>
    // Inicializar tooltips para los segmentos de progreso
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('.progress-bar[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                container: 'body',
                placement: 'top'
            });
        });

        // Manejar el toggle manual sin animación para evitar el efecto de fuente
        document.querySelectorAll('.orden-row').forEach(function(row) {
            var collapseId = row.getAttribute('data-bs-target');
            var collapseEl = document.querySelector(collapseId);
            
            if (collapseEl) {
                // Remover el data-bs-toggle para manejar manualmente
                row.removeAttribute('data-bs-toggle');
                
                row.addEventListener('click', function(e) {
                    // Evitar que se propague si se hace clic en el botón de acciones
                    if (e.target.closest('[onclick="event.stopPropagation();"]') || 
                        e.target.closest('a.btn')) {
                        return;
                    }
                    
                    var isExpanded = row.getAttribute('aria-expanded') === 'true';
                    var icon = row.querySelector('.chevron-icon');
                    
                    if (isExpanded) {
                        // Cerrar
                        collapseEl.classList.remove('show');
                        row.setAttribute('aria-expanded', 'false');
                        if (icon) icon.style.transform = 'rotate(0deg)';
                    } else {
                        // Abrir
                        collapseEl.classList.add('show');
                        row.setAttribute('aria-expanded', 'true');
                        if (icon) icon.style.transform = 'rotate(90deg)';
                    }
                });
            }
        });

        // Manejar la rotación del icono chevron en la vista móvil
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(btn) {
            var collapseId = btn.getAttribute('data-bs-target');
            if (collapseId && collapseId.includes('mobile')) {
                var collapseEl = document.querySelector(collapseId);
                
                if (collapseEl) {
                    collapseEl.addEventListener('show.bs.collapse', function() {
                        btn.setAttribute('aria-expanded', 'true');
                    });
                    
                    collapseEl.addEventListener('hide.bs.collapse', function() {
                        btn.setAttribute('aria-expanded', 'false');
                    });
                }
            }
        });
    });
</script>