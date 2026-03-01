<?php 
// Ordenar los grupos por estado y fecha con lógica de jerarquía
$gruposVencidas = [];
$gruposPrioritarios = [];
$gruposCoordinados = [];
$gruposEnRevision = [];
$gruposFinalizados = [];

foreach ($tareasAgrupadas as $key => $grupo) {
    // Obtener estados de todas las instancias del grupo
    $estadosMuestras = $grupo['instancias']->map(function($instancia) {
        return strtolower($instancia['instancia_muestra']->cotio_estado ?? 'pendiente');
    })->toArray();
    
    // Verificar si al menos una muestra es prioritaria
    $esPrioritario = $grupo['instancias']->contains(function($instancia) {
        return (bool) $instancia['instancia_muestra']->es_priori;
    });
    
    $fechaMuestreo = $grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo ?? null;
    
    // Obtener fecha de fin (fecha_fin_muestreo o fecha_fin_ot) para detectar vencidas
    $fechasFin = $grupo['instancias']->map(function($i) {
        $m = $i['instancia_muestra'];
        return $m->fecha_fin_muestreo ?? $m->fecha_fin_ot ?? null;
    })->filter();
    $fechaFin = $fechasFin->isEmpty() ? null : $fechasFin->sortBy(fn($d) => $d->getTimestamp())->first();
    
    // "Ahora" y fecha fin en la zona horaria de la aplicación (ej. Argentina) para comparar correctamente
    $now = \Carbon\Carbon::now(config('app.timezone'));
    $fechaFinLocal = $fechaFin ? \Carbon\Carbon::parse($fechaFin->format('Y-m-d H:i:s'), config('app.timezone')) : null;
    
    // Determinar estado del grupo basado en jerarquía
    $estadoGrupo = 'pendiente';
    
    // Si hay al menos una muestra en "coordinado muestreo", el grupo se mantiene en ese estado
    if (in_array('coordinado muestreo', $estadosMuestras)) {
        $estadoGrupo = 'coordinado muestreo';
    }
    // Si TODAS están en "en revision muestreo", el grupo pasa a ese estado
    elseif (count(array_unique($estadosMuestras)) === 1 && $estadosMuestras[0] === 'en revision muestreo') {
        $estadoGrupo = 'en revision muestreo';
    }
    // Si TODAS están en "muestreado", el grupo pasa a ese estado
    elseif (count(array_unique($estadosMuestras)) === 1 && $estadosMuestras[0] === 'muestreado') {
        $estadoGrupo = 'muestreado';
    }
    // Si hay mezcla de "en revision" y "muestreado", se mantiene en revisión
    elseif (in_array('en revision muestreo', $estadosMuestras)) {
        $estadoGrupo = 'en revision muestreo';
    }
    // Si hay suspensión, tiene prioridad
    elseif (in_array('suspension', $estadosMuestras)) {
        $estadoGrupo = 'suspension';
    }
    
    // Vencida solo si: tiene fecha fin pasada Y aún NO está en revisión ni muestreada (ej. sigue "coordinado muestreo")
    $esVencida = $fechaFinLocal && $fechaFinLocal->lt($now) && !in_array($estadoGrupo, ['en revision muestreo', 'muestreado']);
    
    $grupoConFecha = [
        'grupo' => $grupo,
        'key' => $key,
        'fecha' => $fechaMuestreo ? \Carbon\Carbon::parse($fechaMuestreo) : null,
        'fecha_fin' => $fechaFin,
        'estado_grupo' => $estadoGrupo,
        'es_prioritario' => $esPrioritario,
        'es_vencida' => $esVencida
    ];
    
    // Clasificar grupos: finalizados, vencidas (fecha fin < hoy y NO muestreado), prioritarias, coordinadas, en revisión
    if ($estadoGrupo === 'muestreado') {
        $gruposFinalizados[] = $grupoConFecha;
    } elseif ($esVencida) {
        $gruposVencidas[] = $grupoConFecha;
    } elseif ($esPrioritario) {
        $gruposPrioritarios[] = $grupoConFecha;
    } elseif ($estadoGrupo === 'coordinado muestreo') {
        $gruposCoordinados[] = $grupoConFecha;
    } elseif ($estadoGrupo === 'en revision muestreo') {
        $gruposEnRevision[] = $grupoConFecha;
    }
}

// Función para ordenar por proximidad a la fecha/hora actual (más cercanas primero)
$fechaActual = \Carbon\Carbon::now(config('app.timezone'));

// Vencidas: ordenar por más vencidas primero (fecha_fin más antigua primero)
usort($gruposVencidas, function($a, $b) {
    $fa = $a['fecha_fin'] ?? null;
    $fb = $b['fecha_fin'] ?? null;
    if ($fa && $fb) return $fa->getTimestamp() <=> $fb->getTimestamp();
    if (!$fa && !$fb) return 0;
    if (!$fa) return 1;
    return -1;
});

usort($gruposPrioritarios, function($a, $b) use ($fechaActual) {
    if ($a['fecha'] && $b['fecha']) {
        // Calcular diferencia absoluta con la fecha actual (en segundos)
        $diffA = abs($a['fecha']->diffInSeconds($fechaActual));
        $diffB = abs($b['fecha']->diffInSeconds($fechaActual));
        // Ordenar por menor diferencia primero (más cercano a la actual)
        return $diffA <=> $diffB;
    }
    // Si una fecha es null, ponerla al final
    if (!$a['fecha'] && !$b['fecha']) return 0;
    if (!$a['fecha']) return 1;
    if (!$b['fecha']) return -1;
    return 0;
});

usort($gruposCoordinados, function($a, $b) use ($fechaActual) {
    if ($a['fecha'] && $b['fecha']) {
        // Calcular diferencia absoluta con la fecha actual (en segundos)
        $diffA = abs($a['fecha']->diffInSeconds($fechaActual));
        $diffB = abs($b['fecha']->diffInSeconds($fechaActual));
        // Ordenar por menor diferencia primero (más cercano a la actual)
        return $diffA <=> $diffB;
    }
    // Si una fecha es null, ponerla al final
    if (!$a['fecha'] && !$b['fecha']) return 0;
    if (!$a['fecha']) return 1;
    if (!$b['fecha']) return -1;
    return 0;
});

usort($gruposEnRevision, function($a, $b) use ($fechaActual) {
    if ($a['fecha'] && $b['fecha']) {
        // Calcular diferencia absoluta con la fecha actual (en segundos)
        $diffA = abs($a['fecha']->diffInSeconds($fechaActual));
        $diffB = abs($b['fecha']->diffInSeconds($fechaActual));
        // Ordenar por menor diferencia primero (más cercano a la actual)
        return $diffA <=> $diffB;
    }
    // Si una fecha es null, ponerla al final
    if (!$a['fecha'] && !$b['fecha']) return 0;
    if (!$a['fecha']) return 1;
    if (!$b['fecha']) return -1;
    return 0;
});

usort($gruposFinalizados, function($a, $b) use ($fechaActual) {
    if ($a['fecha'] && $b['fecha']) {
        // Calcular diferencia absoluta con la fecha actual (en segundos)
        $diffA = abs($a['fecha']->diffInSeconds($fechaActual));
        $diffB = abs($b['fecha']->diffInSeconds($fechaActual));
        // Ordenar por menor diferencia primero (más cercano a la actual)
        return $diffA <=> $diffB;
    }
    // Si una fecha es null, ponerla al final
    if (!$a['fecha'] && !$b['fecha']) return 0;
    if (!$a['fecha']) return 1;
    if (!$b['fecha']) return -1;
    return 0;
});
?>

@if(count($tareasAgrupadas) > 0)
    <!-- Control para mostrar/ocultar muestras finalizadas -->
    @if(count($gruposFinalizados) > 0)
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                <span id="contadorFinalizadas">{{ count($gruposFinalizados) }} muestra(s) finalizada(s) oculta(s)</span>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleFinalizadas" style="cursor: pointer;">
                <label class="form-check-label" for="toggleFinalizadas" style="cursor: pointer;">
                    <i class="fas fa-eye me-1"></i> Mostrar muestras finalizadas
                </label>
            </div>
        </div>
    @endif

    <!-- Control para mostrar/ocultar muestras vencidas -->
    @if(count($gruposVencidas) > 0)
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                <i class="fas fa-info-circle me-1"></i>
                <span id="contadorVencidas">{{ count($gruposVencidas) }} muestra(s) vencida(s) oculta(s)</span>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleVencidas" style="cursor: pointer;">
                <label class="form-check-label" for="toggleVencidas" style="cursor: pointer;">
                    <i class="fas fa-eye me-1"></i> Mostrar muestras vencidas
                </label>
            </div>
        </div>
    @endif

    <!-- Sección de Muestras Vencidas -->
    @if(count($gruposVencidas) > 0)
        <div class="mb-4 muestras-vencidas-section">
            <h3 class="text-danger mb-3">
                <x-heroicon-o-exclamation-triangle class="me-2" style="width: 24px; height: 24px; color: #dc3545;" />
                Muestras Vencidas ({{ count($gruposVencidas) }})
            </h3>
            @foreach($gruposVencidas as $grupoData)
                @php
                    $grupo = $grupoData['grupo'];
                    $key = $grupoData['key'];
                    $isHermana = $grupo['is_hermana'];
                    if ($isHermana) {
                        [$numCoti, $itemId, $subitemId] = explode('_', $key);
                    } else {
                        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                    }
                    $cotizacion = $cotizaciones->get($numCoti);
                    
                    $estadoGrupo = $grupoData['estado_grupo'];
                    $badgeClassMuestra = match ($estadoGrupo) {
                        'coordinado muestreo' => 'warning',
                        'en revision muestreo' => 'info',
                        'muestreado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="card mb-4 shadow-sm table-danger" style="border-left: 4px solid #dc3545 !important;">
                    <div class="card-header">
                        <!-- Encabezado -->
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-link text-decoration-none p-0 me-2" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#tabla-vencida-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" 
                                        aria-expanded="false" 
                                        aria-controls="tabla-vencida-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}"
                                        onclick="toggleChevron('chevron-vencida-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}')">
                                    <x-heroicon-o-chevron-up id="chevron-vencida-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="text-dark chevron-icon" style="width: 20px; height: 20px;" />
                                </button>
                                <div>
                                    <h4 class="mb-0 text-primary">
                                        {{ $cotizacion->coti_empresa ?? 'NA' }} - {{ $grupo['instancias'][0]['instancia_muestra']->cotio_descripcion ?? 'N/A' }}
                                        @if($isHermana)
                                            ({{ $grupo['instancias']->count() }} Muestras)
                                        @endif
                                    </h4>
                                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                        <span class="badge bg-danger text-white">
                                            <x-heroicon-o-exclamation-triangle class="me-1" style="width: 12px; height: 12px;" />
                                            Vencida
                                        </span>
                                        <span class="badge bg-{{ $badgeClassMuestra }} text-dark">
                                            {{ ucfirst($estadoGrupo) }}
                                        </span>
                                        @if($grupoData['fecha_fin'])
                                            <span class="badge bg-dark">Venció: {{ $grupoData['fecha_fin']->format('d/m/Y H:i') }}</span>
                                        @endif
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_priori)
                                            <span class="badge bg-warning text-dark">
                                                <x-heroicon-o-star class="me-1" style="width: 12px; height: 12px;" />
                                                Prioridad
                                            </span>
                                        @endif
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_frecuente && $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias > 0)
                                            <span class="badge bg-light text-dark border">
                                                <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                Cada {{ $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias }} días
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a class="btn btn-outline-danger btn-sm"
                                   href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli ?? '' }}, {{ $cotizacion->coti_localidad ?? '' }}, {{ $cotizacion->coti_partido ?? '' }}">
                                    <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        </div>

                        <!-- Información de la cotización -->
                        @if($cotizacion)
                            <div class="mt-3 small text-dark">
                                <div class="row g-2">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-calendar class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Fecha y hora: </strong> {{ $grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo ? \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i:s') : 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-map-pin class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Dirección: </strong> {{ $cotizacion->coti_direccioncli ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-user-circle class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Cotización N°: </strong> {{ $cotizacion->coti_num ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div id="tabla-vencida-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="collapse">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="w-60">Descripción</th>
                                            <th class="w-40">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupo['instancias'] as $instancia)
                                            @php
                                                $muestra = $instancia['muestra'];
                                                $instanciaMuestra = $instancia['instancia_muestra'];
                                                $analisis = $instancia['analisis'];
                                                $vehiculoAsignado = $instanciaMuestra->vehiculo ?? null;
                                                $esFrecuente = $instanciaMuestra->es_frecuente ?? false;
                                                $frecuenciaDias = $instanciaMuestra->frecuencia_dias ?? 0;
                                                $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                                                $badgeClassMuestra = match ($estadoMuestra) {
                                                    'coordinado muestreo' => 'table-warning',
                                                    'en revision muestreo' => 'table-info',
                                                    'muestreado' => 'table-success',
                                                    'suspension' => 'table-danger text-white',
                                                    default => 'table-secondary'
                                                };
                                            @endphp

                                            <!-- Fila de la muestra -->
                                            <tr class="fw-bold {{ $badgeClassMuestra }}">
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span>MUESTRA: {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }} @if($isHermana)(#{{ $instanciaMuestra->instance_number }})@endif
                                                                @if($instanciaMuestra->es_priori)
                                                                    <x-heroicon-o-star class="ms-1" style="width: 14px; height: 14px; color: #ffc107;" />
                                                                @endif
                                                            </span>
                                                            <small class="text-muted d-block mt-1">ID: #{{ $instanciaMuestra->instance_number }}</small>
                                                            @if($esFrecuente && $frecuenciaDias > 0)
                                                                <span class="badge bg-light text-dark border mt-1">
                                                                    <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                                    Cada {{ $frecuenciaDias }} días
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A'])}}" 
                                                           class="btn btn-sm btn-dark">
                                                            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                                            Ver
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassMuestra) }}">
                                                        {{ ucfirst($estadoMuestra) }}
                                                    </span>
                                                </td>
                                            </tr>

                                            @if($vehiculoAsignado)
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-uppercase small">
                                                        <x-heroicon-o-truck class="me-1" style="width: 16px; height: 16px;" />
                                                        <strong>Vehículo asignado:</strong> {{ $vehiculoAsignado->marca }} {{ $vehiculoAsignado->modelo }} ({{ $vehiculoAsignado->patente }})
                                                    </td>
                                                </tr>
                                            @endif

                                            <!-- Filas de análisis -->
                                            @foreach($analisis as $tarea)
                                                @php
                                                    $estado = strtolower($tarea->cotio_estado ?? 'pendiente');
                                                    $badgeClassAnalisis = match ($estado) {
                                                        'coordinado muestreo' => 'table-warning',
                                                        'en revision muestreo' => 'table-info',
                                                        'muestreado' => 'table-success',
                                                        'suspension' => 'table-danger text-white',
                                                        default => 'table-secondary'
                                                    };
                                                @endphp
                                                <tr class="{{ $badgeClassAnalisis }}">
                                                    <td class="small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span>ANÁLISIS: {{ $tarea->cotio_descripcion }}</span>
                                                                <small class="text-muted d-block mt-1">ID: {{ $tarea->id }}</small>
                                                                @if($tarea->resultado)
                                                                    <span class="badge bg-dark mt-1">RESULTADO: {{ $tarea->resultado }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassAnalisis) }}">
                                                            {{ ucfirst($estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Sección de Muestras Prioritarias -->
    @if(count($gruposPrioritarios) > 0)
        <div class="mb-4">
            <h3 class="text-warning mb-3">
                <x-heroicon-o-star class="me-2" style="width: 24px; height: 24px; color: #ffc107;" />
                Muestras Prioritarias ({{ count($gruposPrioritarios) }})
            </h3>
            @foreach($gruposPrioritarios as $grupoData)
                @php
                    $grupo = $grupoData['grupo'];
                    $key = $grupoData['key'];
                    $isHermana = $grupo['is_hermana'];
                    if ($isHermana) {
                        [$numCoti, $itemId, $subitemId] = explode('_', $key);
                    } else {
                        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                    }
                    $cotizacion = $cotizaciones->get($numCoti);
                    
                    $estadoGrupo = $grupoData['estado_grupo'];
                    $badgeClassMuestra = match ($estadoGrupo) {
                        'coordinado muestreo' => 'warning',
                        'en revision muestreo' => 'info',
                        'muestreado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="card mb-4 shadow-sm table-{{ $badgeClassMuestra }}" style="border-left: 4px solid #ffc107 !important;">
                    <div class="card-header">
                        <!-- Encabezado -->
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-link text-decoration-none p-0 me-2" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#tabla-priority-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" 
                                        aria-expanded="false" 
                                        aria-controls="tabla-priority-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}"
                                        onclick="toggleChevron('chevron-priority-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}')">
                                    <x-heroicon-o-chevron-up id="chevron-priority-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="text-dark chevron-icon" style="width: 20px; height: 20px;" />
                                </button>
                                <div>
                                    <h4 class="mb-0 text-primary">
                                        {{ $cotizacion->coti_empresa ?? 'NA' }} - {{ $grupo['instancias'][0]['instancia_muestra']->cotio_descripcion ?? 'N/A' }}
                                        @if($isHermana)
                                            ({{ $grupo['instancias']->count() }} Muestras)
                                        @endif
                                    </h4>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-{{ $badgeClassMuestra }} text-dark">
                                            {{ ucfirst($estadoGrupo) }}
                                        </span>
                                        <span class="badge bg-warning text-dark">
                                            <x-heroicon-o-star class="me-1" style="width: 12px; height: 12px;" />
                                            Prioridad
                                        </span>
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_frecuente && $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias > 0)
                                            <span class="badge bg-light text-dark border">
                                                <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                Cada {{ $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias }} días
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a class="btn btn-outline-dark btn-sm"
                                   href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli ?? '' }}, {{ $cotizacion->coti_localidad ?? '' }}, {{ $cotizacion->coti_partido ?? '' }}">
                                    <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        </div>

                        <!-- Información de la cotización -->
                        @if($cotizacion)
                            <div class="mt-3 small text-dark">
                                <div class="row g-2">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-calendar class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Fecha y hora inicio: </strong> {{ \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i:s') ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-map-pin class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Dirección: </strong> {{ $cotizacion->coti_direccioncli ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-user-circle class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Cotización N°: </strong> {{ $cotizacion->coti_num ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div id="tabla-priority-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="collapse">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="w-60">Descripción</th>
                                            <th class="w-40">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupo['instancias'] as $instancia)
                                            @php
                                                $muestra = $instancia['muestra'];
                                                $instanciaMuestra = $instancia['instancia_muestra'];
                                                $analisis = $instancia['analisis'];
                                                $vehiculoAsignado = $instanciaMuestra->vehiculo ?? null;
                                                $esFrecuente = $instanciaMuestra->es_frecuente ?? false;
                                                $frecuenciaDias = $instanciaMuestra->frecuencia_dias ?? 0;
                                                $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                                                $badgeClassMuestra = match ($estadoMuestra) {
                                                    'coordinado muestreo' => 'table-warning',
                                                    'en revision muestreo' => 'table-info',
                                                    'muestreado' => 'table-success',
                                                    'suspension' => 'table-danger text-white',
                                                    default => 'table-secondary'
                                                };
                                            @endphp

                                            <!-- Fila de la muestra -->
                                            <tr class="fw-bold {{ $badgeClassMuestra }}">
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span>MUESTRA: {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }} @if($isHermana)(#{{ $instanciaMuestra->instance_number }})@endif
                                                                @if($instanciaMuestra->es_priori)
                                                                    <x-heroicon-o-star class="ms-1" style="width: 14px; height: 14px; color: #ffc107;" />
                                                                @endif
                                                            </span>
                                                            <small class="text-muted d-block mt-1">ID: #{{ $instanciaMuestra->instance_number }}</small>
                                                            @if($esFrecuente && $frecuenciaDias > 0)
                                                                <span class="badge bg-light text-dark border mt-1">
                                                                    <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                                    Cada {{ $frecuenciaDias }} días
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A'])}}" 
                                                           class="btn btn-sm btn-dark">
                                                            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                                            Ver
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassMuestra) }}">
                                                        {{ ucfirst($estadoMuestra) }}
                                                    </span>
                                                </td>
                                            </tr>

                                            @if($vehiculoAsignado)
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-uppercase small">
                                                        <x-heroicon-o-truck class="me-1" style="width: 16px; height: 16px;" />
                                                        <strong>Vehículo asignado:</strong> {{ $vehiculoAsignado->marca }} {{ $vehiculoAsignado->modelo }} ({{ $vehiculoAsignado->patente }})
                                                    </td>
                                                </tr>
                                            @endif

                                            <!-- Filas de análisis -->
                                            @foreach($analisis as $tarea)
                                                @php
                                                    $estado = strtolower($tarea->cotio_estado ?? 'pendiente');
                                                    $badgeClassAnalisis = match ($estado) {
                                                        'coordinado muestreo' => 'table-warning',
                                                        'en revision muestreo' => 'table-info',
                                                        'muestreado' => 'table-success',
                                                        'suspension' => 'table-danger text-white',
                                                        default => 'table-secondary'
                                                    };
                                                @endphp
                                                <tr class="{{ $badgeClassAnalisis }}">
                                                    <td class="small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span>ANÁLISIS: {{ $tarea->cotio_descripcion }}</span>
                                                                <small class="text-muted d-block mt-1">ID: {{ $tarea->id }}</small>
                                                                @if($tarea->resultado)
                                                                    <span class="badge bg-dark mt-1">RESULTADO: {{ $tarea->resultado }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassAnalisis) }}">
                                                            {{ ucfirst($estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Sección de Tareas Coordinadas -->
    @if(count($gruposCoordinados) > 0)
        <div class="mb-4">
            <h3 class="text-primary mb-3">
                <x-heroicon-o-clipboard-document-check class="me-2" style="width: 24px; height: 24px;" />
                Muestras Pendientes ({{ count($gruposCoordinados) }})
            </h3>
            @foreach($gruposCoordinados as $grupoData)
                @php
                    $grupo = $grupoData['grupo'];
                    $key = $grupoData['key'];
                    $isHermana = $grupo['is_hermana'];
                    if ($isHermana) {
                        [$numCoti, $itemId, $subitemId] = explode('_', $key);
                    } else {
                        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                    }
                    $cotizacion = $cotizaciones->get($numCoti);
                    
                    $estadoMuestra = strtolower($grupo['instancias'][0]['instancia_muestra']->cotio_estado ?? 'pendiente');
                    $badgeClassMuestra = match ($estadoMuestra) {
                        'coordinado muestreo' => 'warning',
                        'en revision muestreo' => 'info',
                        'muestreado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="card mb-4 shadow-sm">
                    <div class="card-header table-{{ $badgeClassMuestra }}">
                        <!-- Encabezado -->
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-link text-decoration-none p-0 me-2" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" 
                                        aria-expanded="false" 
                                        aria-controls="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}"
                                        onclick="toggleChevron('chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}')">
                                    <x-heroicon-o-chevron-up id="chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="text-primary chevron-icon" style="width: 20px; height: 20px;" />
                                </button>
                                <div>
                                    <h4 class="mb-0 text-primary">
                                        {{ $cotizacion->coti_empresa ?? 'NA' }} - {{ $grupo['instancias'][0]['instancia_muestra']->cotio_descripcion ?? 'N/A' }}
                                        @if($isHermana)
                                            ({{ $grupo['instancias']->count() }} Muestras)
                                        @endif
                                    </h4>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-{{ $badgeClassMuestra }} text-dark">
                                            {{ ucfirst($estadoMuestra) }}
                                        </span>
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_frecuente && $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias > 0)
                                            <span class="badge bg-light text-dark border">
                                                <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                Cada {{ $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias }} días
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli ?? '' }}, {{ $cotizacion->coti_localidad ?? '' }}, {{ $cotizacion->coti_partido ?? '' }}">
                                    <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        </div>

                        <!-- Información de la cotización -->
                        @if($cotizacion)
                            <div class="mt-3 small">
                                <div class="row g-2">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-calendar class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Fecha y hora: </strong> {{ \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i:s') ?? 'N/A' }}    
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-map-pin class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Dirección: </strong> {{ $cotizacion->coti_direccioncli ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-user-circle class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Cotización N°: </strong> {{ $cotizacion->coti_num ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div id="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="collapse">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="w-60">Descripción</th>
                                            <th class="w-40">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupo['instancias'] as $instancia)
                                            @php
                                                $muestra = $instancia['muestra'];
                                                $instanciaMuestra = $instancia['instancia_muestra'];
                                                $analisis = $instancia['analisis'];
                                                $vehiculoAsignado = $instanciaMuestra->vehiculo ?? null;
                                                $esFrecuente = $instanciaMuestra->es_frecuente ?? false;
                                                $frecuenciaDias = $instanciaMuestra->frecuencia_dias ?? 0;
                                                $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                                                $badgeClassMuestra = match ($estadoMuestra) {
                                                    'coordinado muestreo' => 'table-warning',
                                                    'en revision muestreo' => 'table-info',
                                                    'muestreado' => 'table-success',
                                                    'suspension' => 'table-danger text-white',
                                                    default => 'table-secondary'
                                                };
                                            @endphp

                                            <!-- Fila de la muestra -->
                                            <tr class="fw-bold {{ $badgeClassMuestra }}">
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span>MUESTRA: {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }} @if($isHermana)(#{{ $instanciaMuestra->instance_number }})@endif</span>
                                                            <small class="text-muted d-block mt-1">ID: #{{ $instanciaMuestra->instance_number }}</small>
                                                            @if($esFrecuente && $frecuenciaDias > 0)
                                                                <span class="badge bg-light text-dark border mt-1">
                                                                    <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                                    Cada {{ $frecuenciaDias }} días
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A'])}}" 
                                                           class="btn btn-sm btn-dark">
                                                            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                                            Ver
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassMuestra) }}">
                                                        {{ ucfirst($estadoMuestra) }}
                                                    </span>
                                                </td>
                                            </tr>

                                            @if($vehiculoAsignado)
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-uppercase small">
                                                        <x-heroicon-o-truck class="me-1" style="width: 16px; height: 16px;" />
                                                        <strong>Vehículo asignado:</strong> {{ $vehiculoAsignado->marca }} {{ $vehiculoAsignado->modelo }} ({{ $vehiculoAsignado->patente }})
                                                    </td>
                                                </tr>
                                            @endif

                                            <!-- Filas de análisis -->
                                            @foreach($analisis as $tarea)
                                                @php
                                                    $estado = strtolower($tarea->cotio_estado ?? 'pendiente');
                                                    $badgeClassAnalisis = match ($estado) {
                                                        'coordinado muestreo' => 'table-warning',
                                                        'en revision muestreo' => 'table-info',
                                                        'muestreado' => 'table-success',
                                                        'suspension' => 'table-danger text-white',
                                                        default => 'table-secondary'
                                                    };
                                                @endphp
                                                <tr class="{{ $badgeClassAnalisis }}">
                                                    <td class="small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span>ANÁLISIS: {{ $tarea->cotio_descripcion }}</span>
                                                                <small class="text-muted d-block mt-1">ID: {{ $tarea->id }}</small>
                                                                @if($tarea->resultado)
                                                                    <span class="badge bg-dark mt-1">RESULTADO: {{ $tarea->resultado }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassAnalisis) }}">
                                                            {{ ucfirst($estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Sección de Tareas en Revisión -->
    @if(count($gruposEnRevision) > 0)
        <div class="mb-4">
            <h3 class="text-primary mb-3">
                <x-heroicon-o-magnifying-glass class="me-2" style="width: 24px; height: 24px;" />
                Muestras en Revisión ({{ count($gruposEnRevision) }})
            </h3>
            @foreach($gruposEnRevision as $grupoData)
                @php
                    $grupo = $grupoData['grupo'];
                    $key = $grupoData['key'];
                    $isHermana = $grupo['is_hermana'];
                    if ($isHermana) {
                        [$numCoti, $itemId, $subitemId] = explode('_', $key);
                    } else {
                        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                    }
                    $cotizacion = $cotizaciones->get($numCoti);
                    
                    $estadoMuestra = strtolower($grupo['instancias'][0]['instancia_muestra']->cotio_estado ?? 'pendiente');
                    $badgeClassMuestra = match ($estadoMuestra) {
                        'coordinado muestreo' => 'warning',
                        'en revision muestreo' => 'info',
                        'muestreado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="card mb-4 shadow-sm">
                    <div class="card-header table-{{ $badgeClassMuestra }}">
                        <!-- Encabezado -->
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-link text-decoration-none p-0 me-2" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" 
                                        aria-expanded="false" 
                                        aria-controls="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}"
                                        onclick="toggleChevron('chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}')">
                                    <x-heroicon-o-chevron-up id="chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="text-primary chevron-icon" style="width: 20px; height: 20px;" />
                                </button>
                                <div>
                                    <h4 class="mb-0 text-primary">
                                        {{ $cotizacion->coti_empresa ?? 'NA' }} - {{ $grupo['instancias'][0]['instancia_muestra']->cotio_descripcion ?? 'N/A' }}
                                        @if($isHermana)
                                            ({{ $grupo['instancias']->count() }} Muestras)
                                        @endif
                                    </h4>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-{{ $badgeClassMuestra }} text-dark">
                                            {{ ucfirst($estadoMuestra) }}
                                        </span>
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_frecuente && $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias > 0)
                                            <span class="badge bg-light text-dark border">
                                                <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                Cada {{ $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias }} días
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli ?? '' }}, {{ $cotizacion->coti_localidad ?? '' }}, {{ $cotizacion->coti_partido ?? '' }}">
                                    <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        </div>

                        <!-- Información de la cotización -->
                        @if($cotizacion)
                            <div class="mt-3 small">
                                <div class="row g-2">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-calendar class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Fecha y hora: </strong> {{ \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i:s') ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-map-pin class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Dirección: </strong> {{ $cotizacion->coti_direccioncli ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-user-circle class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Cotización N°: </strong> {{ $cotizacion->coti_num ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div id="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="collapse">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="w-60">Descripción</th>
                                            <th class="w-40">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupo['instancias'] as $instancia)
                                            @php
                                                $muestra = $instancia['muestra'];
                                                $instanciaMuestra = $instancia['instancia_muestra'];
                                                $analisis = $instancia['analisis'];
                                                $vehiculoAsignado = $instanciaMuestra->vehiculo ?? null;
                                                $esFrecuente = $instanciaMuestra->es_frecuente ?? false;
                                                $frecuenciaDias = $instanciaMuestra->frecuencia_dias ?? 0;
                                                $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                                                $badgeClassMuestra = match ($estadoMuestra) {
                                                    'coordinado muestreo' => 'table-warning',
                                                    'en revision muestreo' => 'table-info',
                                                    'muestreado' => 'table-success',
                                                    'suspension' => 'table-danger text-white',
                                                    default => 'table-secondary'
                                                };
                                            @endphp

                                            <!-- Fila de la muestra -->
                                            <tr class="fw-bold {{ $badgeClassMuestra }}">
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span>MUESTRA: {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }} @if($isHermana)(#{{ $instanciaMuestra->instance_number }})@endif</span>
                                                            <small class="text-muted d-block mt-1">ID: #{{ $instanciaMuestra->instance_number }}</small>
                                                            @if($esFrecuente && $frecuenciaDias > 0)
                                                                <span class="badge bg-light text-dark border mt-1">
                                                                    <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                                    Cada {{ $frecuenciaDias }} días
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A'])}}" 
                                                           class="btn btn-sm btn-dark">
                                                            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                                            Ver
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassMuestra) }}">
                                                        {{ ucfirst($estadoMuestra) }}
                                                    </span>
                                                </td>
                                            </tr>

                                            @if($vehiculoAsignado)
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-uppercase small">
                                                        <x-heroicon-o-truck class="me-1" style="width: 16px; height: 16px;" />
                                                        <strong>Vehículo asignado:</strong> {{ $vehiculoAsignado->marca }} {{ $vehiculoAsignado->modelo }} ({{ $vehiculoAsignado->patente }})
                                                    </td>
                                                </tr>
                                            @endif

                                            <!-- Filas de análisis -->
                                            @foreach($analisis as $tarea)
                                                @php
                                                    $estado = strtolower($tarea->cotio_estado ?? 'pendiente');
                                                    $badgeClassAnalisis = match ($estado) {
                                                        'coordinado muestreo' => 'table-warning',
                                                        'en revision muestreo' => 'table-info',
                                                        'muestreado' => 'table-success',
                                                        'suspension' => 'table-danger text-white',
                                                        default => 'table-secondary'
                                                    };
                                                @endphp
                                                <tr class="{{ $badgeClassAnalisis }}">
                                                    <td class="small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span>ANÁLISIS: {{ $tarea->cotio_descripcion }}</span>
                                                                <small class="text-muted d-block mt-1">ID: {{ $tarea->id }}</small>
                                                                @if($tarea->resultado)
                                                                    <span class="badge bg-dark mt-1">RESULTADO: {{ $tarea->resultado }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassAnalisis) }}">
                                                            {{ ucfirst($estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Sección de Tareas Finalizadas -->
    @if(count($gruposFinalizados) > 0)
        <div class="mb-4 muestras-finalizadas-section" style="display: none;">
            <h3 class="text-primary mb-3">
                <x-heroicon-o-check-circle class="me-2" style="width: 24px; height: 24px;" />
                Muestras Finalizadas ({{ count($gruposFinalizados) }})
            </h3>
            @foreach($gruposFinalizados as $grupoData)
                @php
                    $grupo = $grupoData['grupo'];
                    $key = $grupoData['key'];
                    $isHermana = $grupo['is_hermana'];
                    if ($isHermana) {
                        [$numCoti, $itemId, $subitemId] = explode('_', $key);
                    } else {
                        [$numCoti, $instanceNumber, $itemId] = explode('_', $key);
                    }
                    $cotizacion = $cotizaciones->get($numCoti);
                    
                    $estadoMuestra = strtolower($grupo['instancias'][0]['instancia_muestra']->cotio_estado ?? 'pendiente');
                    $badgeClassMuestra = match ($estadoMuestra) {
                        'coordinado muestreo' => 'warning',
                        'en revision muestreo' => 'info',
                        'muestreado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary'
                    };
                @endphp

                <div class="card mb-4 shadow-sm">
                    <div class="card-header table-{{ $badgeClassMuestra }}">
                        <!-- Encabezado -->
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-link text-decoration-none p-0 me-2" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" 
                                        aria-expanded="false" 
                                        aria-controls="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}"
                                        onclick="toggleChevron('chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}')">
                                    <x-heroicon-o-chevron-up id="chevron-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="text-primary chevron-icon" style="width: 20px; height: 20px;" />
                                </button>
                                <div>
                                    <h4 class="mb-0 text-primary">
                                        {{ $cotizacion->coti_empresa ?? 'NA' }} - {{ $grupo['instancias'][0]['instancia_muestra']->cotio_descripcion ?? 'N/A' }}
                                        @if($isHermana)
                                            ({{ $grupo['instancias']->count() }} Muestras)
                                        @endif
                                    </h4>
                                    <div class="d-flex align-items-center gap-2 mt-1">
                                        <span class="badge bg-{{ $badgeClassMuestra }} text-dark">
                                            {{ ucfirst($estadoMuestra) }}
                                        </span>
                                        @if($grupo['instancias'][0]['instancia_muestra']->es_frecuente && $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias > 0)
                                            <span class="badge bg-light text-dark border">
                                                <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                Cada {{ $grupo['instancias'][0]['instancia_muestra']->frecuencia_dias }} días
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-2 mt-md-0">
                                <a class="btn btn-outline-primary btn-sm"
                                   href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli ?? '' }}, {{ $cotizacion->coti_localidad ?? '' }}, {{ $cotizacion->coti_partido ?? '' }}">
                                    <x-heroicon-o-map class="me-1" style="width: 16px; height: 16px;" />
                                    <span>Maps</span>
                                </a>
                            </div>
                        </div>

                        <!-- Información de la cotización -->
                        @if($cotizacion)
                            <div class="mt-3 small">
                                <div class="row g-2">
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-calendar class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Fecha y hora: </strong> {{ \Carbon\Carbon::parse($grupo['instancias'][0]['instancia_muestra']->fecha_inicio_muestreo)->format('d/m/Y H:i:s') ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-map-pin class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Dirección: </strong> {{ $cotizacion->coti_direccioncli ?? 'N/A' }}
                                    </div>
                                    <div class="col-md-4 d-flex align-items-center">
                                        <x-heroicon-o-user-circle class="me-2 text-muted" style="width: 14px; height: 14px;" />
                                        <strong>Cotización N°: </strong> {{ $cotizacion->coti_num ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div id="tabla-{{ $numCoti }}-{{ $itemId }}-{{ $isHermana ? $subitemId : $instanceNumber }}" class="collapse">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th class="w-60">Descripción</th>
                                            <th class="w-40">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($grupo['instancias'] as $instancia)
                                            @php
                                                $muestra = $instancia['muestra'];
                                                $instanciaMuestra = $instancia['instancia_muestra'];
                                                $analisis = $instancia['analisis'];
                                                $vehiculoAsignado = $instanciaMuestra->vehiculo ?? null;
                                                $esFrecuente = $instanciaMuestra->es_frecuente ?? false;
                                                $frecuenciaDias = $instanciaMuestra->frecuencia_dias ?? 0;
                                                $estadoMuestra = strtolower($instanciaMuestra->cotio_estado ?? 'pendiente');
                                                $badgeClassMuestra = match ($estadoMuestra) {
                                                    'coordinado muestreo' => 'table-warning',
                                                    'en revision muestreo' => 'table-info',
                                                    'muestreado' => 'table-success',
                                                    'suspension' => 'table-danger text-white',
                                                    default => 'table-secondary'
                                                };
                                            @endphp

                                            <!-- Fila de la muestra -->
                                            <tr class="fw-bold {{ $badgeClassMuestra }}">
                                                <td>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span>MUESTRA: {{ $instanciaMuestra->cotio_descripcion ?? 'N/A' }} @if($isHermana)(#{{ $instanciaMuestra->instance_number }})@endif</span>
                                                            <small class="text-muted d-block mt-1">ID: #{{ $instanciaMuestra->instance_number }}</small>
                                                            @if($esFrecuente && $frecuenciaDias > 0)
                                                                <span class="badge bg-light text-dark border mt-1">
                                                                    <x-heroicon-o-arrow-path class="me-1" style="width: 14px; height: 14px;" />
                                                                    Cada {{ $frecuenciaDias }} días
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <a href="{{ Auth::user()->rol == 'laboratorio' ? route('ordenes.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A']) : route('tareas.all.show', [$instanciaMuestra->cotio_numcoti ?? 'N/A', $instanciaMuestra->cotio_item ?? 'N/A', $instanciaMuestra->cotio_subitem ?? 'N/A', $instanciaMuestra->instance_number ?? 'N/A'])}}" 
                                                           class="btn btn-sm btn-dark">
                                                            <x-heroicon-o-eye class="me-1" style="width: 16px; height: 16px;" />
                                                            Ver
                                                        </a>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassMuestra) }}">
                                                        {{ ucfirst($estadoMuestra) }}
                                                    </span>
                                                </td>
                                            </tr>

                                            @if($vehiculoAsignado)
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-uppercase small">
                                                        <x-heroicon-o-truck class="me-1" style="width: 16px; height: 16px;" />
                                                        <strong>Vehículo asignado:</strong> {{ $vehiculoAsignado->marca }} {{ $vehiculoAsignado->modelo }} ({{ $vehiculoAsignado->patente }})
                                                    </td>
                                                </tr>
                                            @endif

                                            <!-- Filas de análisis -->
                                            @foreach($analisis as $tarea)
                                                @php
                                                    $estado = strtolower($tarea->cotio_estado ?? 'pendiente');
                                                    $badgeClassAnalisis = match ($estado) {
                                                        'coordinado muestreo' => 'table-warning',
                                                        'en revision muestreo' => 'table-info',
                                                        'muestreado' => 'table-success',
                                                        'suspension' => 'table-danger text-white',
                                                        default => 'table-secondary'
                                                    };
                                                @endphp
                                                <tr class="{{ $badgeClassAnalisis }}">
                                                    <td class="small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span>ANÁLISIS: {{ $tarea->cotio_descripcion }}</span>
                                                                <small class="text-muted d-block mt-1">ID: {{ $tarea->id }}</small>
                                                                @if($tarea->resultado)
                                                                    <span class="badge bg-dark mt-1">RESULTADO: {{ $tarea->resultado }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge text-dark {{ str_replace('table-', 'bg-', $badgeClassAnalisis) }}">
                                                            {{ ucfirst($estado) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@else
    <div class="alert alert-info">
        No hay tareas para mostrar.
    </div>
@endif

<div class="d-flex justify-content-center mt-4">
    @if($tareasPaginadas instanceof \Illuminate\Pagination\LengthAwarePaginator)
        {{ $tareasPaginadas->links() }}
    @endif
</div>

<style>
    .chevron-icon {
        transition: transform 0.3s ease;
    }
    .chevron-icon.rotated {
        transform: rotate(180deg);
    }
    .table td {
        vertical-align: middle;
    }
    .badge {
        font-size: 0.85em;
        font-weight: 500;
        padding: 0.35em 0.65em;
    }
    .table-light {
        background-color: rgba(248, 249, 250, 0.8) !important;
    }
    .table-warning {
        background-color: rgba(255, 243, 205, 0.8) !important;
    }
    .table-info {
        background-color: rgba(209, 236, 241, 0.8) !important;
    }
    .table-success {
        background-color: rgba(212, 237, 218, 0.8) !important;
    }
    .card.table-danger .card-header {
        background-color: rgba(248, 81, 73, 0.18) !important;
    }
    .bg-warning {
        background-color: #ffc107 !important;
    }
    .bg-info {
        background-color: #0dcaf0 !important;
    }
    .bg-success {
        background-color: #198754 !important;
    }
    .card-header {
        padding: 1rem 1.25rem;
    }
</style>

<script>
    function toggleChevron(iconId) {
        const icon = document.getElementById(iconId);
        if (icon) {
            icon.classList.toggle('rotated');
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.collapse.show').forEach(collapseElement => {
            const targetId = collapseElement.id;
            const iconId = `chevron-${targetId.replace('tabla-', '')}`;
            const icon = document.getElementById(iconId);
            if (icon) {
                icon.classList.add('rotated');
            }
        });

        // Toggle para mostrar/ocultar muestras finalizadas
        const toggleFinalizadas = document.getElementById('toggleFinalizadas');
        const muestrasFinalizadasSection = document.querySelector('.muestras-finalizadas-section');
        const contadorFinalizadas = document.getElementById('contadorFinalizadas');
        
        if (toggleFinalizadas && muestrasFinalizadasSection) {
            // Verificar si hay preferencia guardada en localStorage
            const mostrarFinalizadas = localStorage.getItem('mostrarMuestrasFinalizadas') === 'true';
            toggleFinalizadas.checked = mostrarFinalizadas;
            
            if (mostrarFinalizadas) {
                muestrasFinalizadasSection.style.display = 'block';
                if (contadorFinalizadas) {
                    contadorFinalizadas.style.display = 'none';
                }
            } else {
                if (contadorFinalizadas) {
                    contadorFinalizadas.style.display = 'inline';
                }
            }
            
            toggleFinalizadas.addEventListener('change', function() {
                if (this.checked) {
                    muestrasFinalizadasSection.style.display = 'block';
                    if (contadorFinalizadas) {
                        contadorFinalizadas.style.display = 'none';
                    }
                    localStorage.setItem('mostrarMuestrasFinalizadas', 'true');
                } else {
                    muestrasFinalizadasSection.style.display = 'none';
                    if (contadorFinalizadas) {
                        contadorFinalizadas.style.display = 'inline';
                    }
                    localStorage.setItem('mostrarMuestrasFinalizadas', 'false');
                }
            });
        }

        // Toggle para mostrar/ocultar muestras vencidas (visible por defecto)
        const toggleVencidas = document.getElementById('toggleVencidas');
        const muestrasVencidasSection = document.querySelector('.muestras-vencidas-section');
        const contadorVencidas = document.getElementById('contadorVencidas');
        
        if (toggleVencidas && muestrasVencidasSection) {
            // Por defecto se muestran; solo ocultar si hay 'false' en localStorage
            const mostrarVencidas = localStorage.getItem('mostrarMuestrasVencidas') !== 'false';
            toggleVencidas.checked = mostrarVencidas;
            
            if (mostrarVencidas) {
                muestrasVencidasSection.style.display = 'block';
                if (contadorVencidas) {
                    contadorVencidas.style.display = 'none';
                }
            } else {
                muestrasVencidasSection.style.display = 'none';
                if (contadorVencidas) {
                    contadorVencidas.style.display = 'inline';
                }
            }
            
            toggleVencidas.addEventListener('change', function() {
                if (this.checked) {
                    muestrasVencidasSection.style.display = 'block';
                    if (contadorVencidas) {
                        contadorVencidas.style.display = 'none';
                    }
                    localStorage.setItem('mostrarMuestrasVencidas', 'true');
                } else {
                    muestrasVencidasSection.style.display = 'none';
                    if (contadorVencidas) {
                        contadorVencidas.style.display = 'inline';
                    }
                    localStorage.setItem('mostrarMuestrasVencidas', 'false');
                }
            });
        }
    });
</script>