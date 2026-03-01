@extends('layouts.app')
<head>
    <title>Ver Cotización {{$cotizacion->coti_num}}</title>
</head>

@section('content')
<div class="container py-4">
    <a href="{{ url('/muestras') }}" class="btn btn-outline-secondary mb-4">← Volver a Muestras</a>
    <h2 class="mb-4">Muestras de Cotización <span class="text-primary">{{ $cotizacion->coti_num }}</span></h2>
    
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @include('cotizaciones.info')

    <div class="d-lg-block">
        <div class="row">
            <div>
                <div class="card shadow-sm mb-4">
                    <div 
                        class="card-header bg-primary text-white d-flex justify-content-between align-items-center" 
                        style="cursor: pointer;" 
                        onclick="toggleMuestras('muestras-pendientes-lg')"
                    >
                        <h5 class="mb-0">Muestras Pendientes</h5>
                        <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation();">
                            @php
                                $tiposParaSeleccionar = collect($agrupadas)
                                    ->map(fn($a) => $a['categoria']->cotio_descripcion ?? '')
                                    ->filter(fn($d) => $d !== '' && $d !== 'TRABAJO TECNICO EN CAMPO' && $d !== 'VIATICOS')
                                    ->unique()
                                    ->values()
                                    ->toArray();
                            @endphp
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="dropdownSeleccionarMuestras" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-check-double me-1"></i> Seleccionar muestras
                                </button>
                                <ul id="menu-seleccionar-muestras" class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownSeleccionarMuestras">
                                    <li><button type="button" class="dropdown-item" data-seleccion="todas"><i class="fas fa-list me-2"></i>Todas (disponibles)</button></li>
                                    @if(count($tiposParaSeleccionar) > 0)
                                        <li><hr class="dropdown-divider"></li>
                                        @foreach($tiposParaSeleccionar as $tipo)
                                            <li><button type="button" class="dropdown-item" data-seleccion="tipo" data-descripcion="{{ $tipo }}"><i class="fas fa-vial me-2"></i>{{ $tipo }}</button></li>
                                        @endforeach
                                    @endif
                                </ul>
                            </div>
                            <x-heroicon-o-chevron-up id="chevron-muestras-pendientes-lg" class="text-white" style="width: 20px; height: 20px;" />
                        </div>
                    </div>
                    <div id="muestras-pendientes-lg" class="card-body collapse-content">
                        @if($tareas->isEmpty())
                            <div class="alert alert-warning">
                                No hay muestras registradas para esta cotización.
                            </div>
                        @else
                            @php
                                $mostradoTecnicoCampo = false;
                                $mostradoViaticos = false;
                            @endphp
                            
                            @foreach($agrupadas as $item)
                                @php
                                    $categoria = $item['categoria'];
                                    $descripcion = $categoria->cotio_descripcion;
                                @endphp
                                
                                @if($descripcion === 'TRABAJO TECNICO EN CAMPO' && !$mostradoTecnicoCampo)
                                    <div class="mb-4">
                                        <div class="card shadow-sm mi-tarjeta h-100">
                                            <p class="card-header text-white d-flex justify-content-start align-items-center gap-2 flex-wrap bg-primary">
                                                <x-heroicon-o-information-circle class="text-white" style="width: 20px; height: 20px;"/>
                                                Visita a planta requerida
                                            </p>
                                        </div>
                                    </div>
                                    @php $mostradoTecnicoCampo = true; @endphp
                                @elseif($descripcion === 'VIATICOS' && !$mostradoViaticos)
                                    <div class="mb-4">
                                        <div class="card shadow-sm mi-tarjeta h-100">
                                            <p class="card-header text-white d-flex justify-content-start align-items-center gap-2 flex-wrap bg-primary">
                                                <x-heroicon-o-information-circle class="text-white" style="width: 20px; height: 20px;"/>
                                                Viaticos requeridos
                                            </p>
                                        </div>
                                    </div>
                                    @php $mostradoViaticos = true; @endphp
                                @endif
                            @endforeach
                    
                        @foreach($agrupadas as $item)
                            @php
                                $categoria = $item['categoria'];
                                $requiereMuestreo = $categoria->requiere_muestreo;
                                $instancia = $item['instancia'];
                                $tareasItem = $item['tareas'];
                                // dd($tareasItem);
                                $responsables = $item['responsables'] ?? collect(); // Asegurar que siempre haya una colección
                                $descripcion = $categoria->cotio_descripcion;
                                $isTecnicoCampo = $descripcion === 'TRABAJO TECNICO EN CAMPO';
                                $isViaticos = $descripcion === 'VIATICOS';
                        
                                // Determinar clase de fondo según estado
                                $headerClass = $categoria->enable_ot ? 'bg-warning' : 'bg-secondary';
                                if ($instancia->active_muestreo && $instancia->fecha_muestreo) {
                                    $headerClass = 'bg-success';
                                }
                        
                                if($instancia->cotio_estado == 'suspension') {
                                    $headerClass = 'bg-danger';
                                }
                            @endphp
                        
                            @if(!$isTecnicoCampo && !$isViaticos)
                            @php
                                $estado = $instancia->cotio_estado;
                                $badgeClass = match ($estado) {
                                    'pendiente' => 'warning',
                                    'coordinado muestreo' => 'warning',
                                    'coordinado analisis' => 'warning',
                                    'en proceso' => 'info',
                                    'en revision muestreo' => 'info',
                                    'en revision analisis' => 'info',
                                    'finalizado' => 'success',
                                    'muestreado' => 'success',
                                    'analizado' => 'success',
                                    'suspension' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                                <div class="mb-4">
                                    <div class="card shadow-sm mi-tarjeta h-100" @if($instancia->es_priori) style="border: 1px solid #FFC107;" @endif>
                                        <!-- Encabezado de la tarjeta -->
                                        <div class="card-header text-white d-flex justify-content-between align-items-center flex-wrap {{ $headerClass }} {{ $instancia->active_ot ? 'bg-success' : '' }}">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <!-- Checkbox principal -->
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input categoria-checkbox"
                                                    data-item="{{ $categoria->original_item }}"
                                                    data-instance="{{ $instancia->instance_number }}"
                                                    data-descripcion="{{ $descripcion }}"
                                                    onchange="toggleTareas(this)"
                                                    @if($instancia->active_muestreo) checked @endif
                                                    @if($instancia->enable_ot) disabled @endif
                                                />
                                            
                                                
                                                <!-- Información principal -->
                                                <div class="d-flex flex-column">
                                                    <a 
                                                        href="{{ route('muestras.ver', [
                                                            'cotizacion' => $cotizacion->coti_num, 
                                                            'item' => $categoria->original_item,
                                                            'instance' => $instancia->instance_number
                                                        ]) }}" 
                                                        class="text-decoration-none {{ $categoria->enable_ot ? 'text-dark' : 'text-white'}}"
                                                    >
                                                        <strong @if($instancia->es_priori) style="color: #FFC107;" @endif>
                                                            @if($instancia->es_priori)
                                                                <x-heroicon-o-star style="width: 18px; height: 18px;" />
                                                            @endif
                                                            {{ $descripcion }}
                                                        </strong> 
                                                        <span class="ms-2">(Muestra {{ $instancia->instance_number }} / {{ $categoria->cotio_cantidad ?? '-' }})</span>
                                                        @if($instancia->active_ot)
                                                            <span class="ms-2">(Esta muestra se encuentra en una Orden de Trabajo)</span>
                                                        @endif
                                                    </a>
                                                    
                                                    @if($instancia->active_muestreo && $instancia->fecha_muestreo)
                                                        <div class="d-flex flex-column mt-1">
                                                            <small class="text-light">
                                                                Coordinado el: {{ $instancia->fecha_muestreo->format('d/m/Y H:i') }}
                                                                @if($instancia->coordinador)
                                                                    por {{ $instancia->coordinador->usu_descripcion }}
                                                                @endif
                                                            </small>
                                                            <small class="text-light">
                                                                Fecha de inicio: {{ $instancia->fecha_inicio_muestreo ? $instancia->fecha_inicio_muestreo->format('d/m/Y H:i') : 'No definida' }}
                                                            </small>
                                                        </div>
                                                    @endif
                                                    
                                                    <!-- Mostrar responsables asignados -->
                                                    @if($responsables->isNotEmpty())
                                                        <div class="mt-1">
                                                            <small class="text-light">Muestreadores:</small>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($responsables as $responsable)
                                                                    <span class="badge bg-info">
                                                                        {{ $responsable->usu_descripcion }}
                                                                        @if($instancia->enable_ot == false)
                                                                            <button type="button" 
                                                                                class="btn-close btn-close-white btn-sm ms-1" 
                                                                                style="font-size: 0.5rem;"
                                                                                onclick="removerResponsable(event, {{ $instancia->id }}, '{{ $responsable->usu_codigo }}')"
                                                                                title="Remover muestreador">
                                                                            </button>
                                                                        @endif
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                        
                                            <!-- Acciones adicionales -->
                                            <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
                                                <div>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <p class="mb-0 badge bg-{{ $badgeClass }} ms-2">
                                                            {{ ucfirst($instancia->cotio_estado) }}
                                                        </p>
                                                        @if($instancia->cotio_estado == 'coordinado muestreo' && $instancia->enable_ot == false)
                                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#recoordinarModal"
                                                                    data-instancia="{{ $instancia->id }}"
                                                                    data-cotizacion="{{ $cotizacion->coti_num }}"
                                                                    data-item="{{ $instancia->cotio_item }}"
                                                                    data-descripcion="{{ $categoria->cotio_descripcion ?? $instancia->cotio_descripcion ?? '' }}"
                                                                    data-instance="{{ $instancia->instance_number }}">
                                                                Recoordinar
                                                            </button>
                                                        @endif
                                                        {{-- DEBUG: Rol usuario: "{{ Auth::user()->rol }}", Estado instancia: "{{ $instancia->cotio_estado }}" --}}
                                                        @php
                                                            $userRol = trim(Auth::user()->rol ?? '');
                                                            $instanciaEstado = trim($instancia->cotio_estado ?? '');
                                                            $mostrarSuspender = ($userRol == 'coordinador_muestreo' && $instanciaEstado == 'coordinado muestreo');
                                                        @endphp
                                                        @if($mostrarSuspender)
                                                            <button type="button" class="btn btn-sm btn-warning" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#suspenderModal{{ $instancia->id }}">
                                                                <i class="fas fa-pause me-1"></i> Suspender
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if(!$requiereMuestreo && !$instancia->enable_ot && (empty($instancia->cotio_estado) || $instancia->cotio_estado == 'pendiente'))
                                                @elseif(!$requiereMuestreo && $instancia->enable_ot)
                                                    <button 
                                                        type="button" 
                                                        onclick="quitarDirectoAOT({{ $instancia }})" 
                                                        class="btn btn-danger" 
                                                        data-bs-toggle="tooltip" 
                                                        title="Quitar esta muestra de la Orden de Trabajo"
                                                    >
                                                        Quitar de OT
                                                    </button>
                                                @elseif($requiereMuestreo && !$instancia->enable_ot)
                                                    <span class="ms-2">(Debe pasar por muestreo)</span>
                                                @endif
                                            
                                                {{-- @dd($instancia) --}}
                                                {{-- @if(!$instancia->active_ot) --}}
                                                    <a 
                                                        href="#"
                                                        class="text-decoration-none"
                                                        title="Generar CT para esta muestra"
                                                        data-url="{{ route('qr.universal', [
                                                        'cotio_numcoti' => $cotizacion->coti_num, 
                                                        'cotio_item' => $categoria->original_item,
                                                        'cotio_subitem' => 0,
                                                        'instance' => $instancia->instance_number
                                                    ]) }}"
                                                        data-coti="{{ $cotizacion->coti_num }}"
                                                        data-categoria="{{ $descripcion }}"
                                                        data-instance="{{ $instancia->instance_number }}"
                                                        data-fechaMuestreo="{{ $instancia->fecha_muestreo }}"
                                                        onclick="generateQr(this)"
                                                    >
                                                        <x-heroicon-o-qr-code class="text-white" style="width: 24px; height: 24px;"/>
                                                    </a>
                                                {{-- @endif --}}
                        
                                            </div>
                                        </div>
                        
                                        <!-- Cuerpo de la tarjeta -->
                                        <div class="card-body">
                                            @if(count($tareasItem) > 0)
                                                <ul class="list-group">
                                                    @foreach($tareasItem as $tarea)
                                                        <li class="list-group-item">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div class="d-flex align-items-center">
                                                                    <!-- Checkbox de análisis -->
                                                                    <input 
                                                                        type="checkbox"
                                                                        class="form-check-input tarea-checkbox me-2" 
                                                                        name="cotio_items[]"
                                                                        value="{{ $tarea->original_item }}-{{ $tarea->cotio_subitem }}-{{ $instancia->instance_number }}"
                                                                        data-item="{{ $tarea->original_item }}"
                                                                        data-subitem="{{ $tarea->cotio_subitem }}"
                                                                        data-instance="{{ $instancia->instance_number }}"
                                                                        onchange="actualizarEstadoTarea(this)"
                                                                        @if(filter_var($tarea->instancia->active_muestreo, FILTER_VALIDATE_BOOLEAN)) checked @endif
                                                                        @if($instancia->enable_ot) disabled @endif
                                                                    />
                                                                
                                                                
                                                                    
                                                                    <div class="d-flex gap-2 align-items-start justify-content-center">
                                                                        {{ $tarea->cotio_descripcion }}
                                                                        <div>
                                                                            @php
                                                                                $estado = $tarea->instancia->cotio_estado;
                                                                                $badgeClass = match ($estado) {
                                                                                    'pendiente' => 'warning',
                                                                                    'coordinado muestreo' => 'warning',
                                                                                    'coordinado analisis' => 'warning',
                                                                                    'en proceso' => 'info',
                                                                                    'en revision muestreo' => 'info',
                                                                                    'en revision analisis' => 'info',
                                                                                    'finalizado' => 'success',
                                                                                    'muestreado' => 'success',
                                                                                    'analizado' => 'success',
                                                                                    'suspension' => 'danger',
                                                                                    default => 'secondary'
                                                                                };
                                                                            @endphp
                                                                            <p class="mb-0 badge bg-{{ $badgeClass }} ms-2">
                                                                                {{ ucfirst($tarea->instancia->cotio_estado) }}
                                                                            </p>
                                                                        </div>
                                                                        @if($tarea->instancia->resultado)
                                                                            <span class="badge bg-primary ms-2">Resultado: {{ $tarea->instancia->resultado }}</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                
                                                                @if(filter_var($tarea->instancia->active_muestreo, FILTER_VALIDATE_BOOLEAN) && $tarea->instancia->fecha_muestreo)
                                                                    <small class="text-muted">
                                                                        {{ $tarea->instancia->fecha_muestreo->format('d/m/Y H:i') }}
                                                                        @if($tarea->instancia->coordinador)
                                                                            <br><small>por {{ $tarea->instancia->coordinador->usu_descripcion }}</small>
                                                                        @endif
                                                                    </small>
                                                                @endif
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="text-muted">No hay análisis en esta muestra</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @endif
                    </div>
            </div>
        </div>
    </div>
</div>

<!-- Botón flotante para aplicar cambios -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div class="d-flex gap-2">
        <button 
            id="btn-asignacion-masiva" 
            class="btn btn-success shadow" 
            onclick="mostrarModalAsignacion()"
            disabled
        >
           Pasar a muestreo
        </button>
    </div>
</div>






<div class="modal fade" id="asignacionMasivaModal" tabindex="-1" aria-labelledby="asignacionMasivaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="asignacionMasivaForm" action="{{ route('muestras.asignacion-masiva') }}" method="POST">
            @csrf
            <input type="hidden" name="cotio_numcoti" value="{{ $cotizacion->coti_num }}">
            <input type="hidden" id="items_seleccionados" name="items_seleccionados">
            <input type="hidden" id="parametros_seleccionados" name="parametros_seleccionados">
            <input type="hidden" name="pasar_a_muestreo" value="1">
        
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="asignacionMasivaModalLabel">Asignación de elementos</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="responsables_muestreo" class="form-label">Responsables de Muestreo</label>
                                <select class="form-select select2-multiple" id="responsables_muestreo" name="responsables_muestreo[]" multiple="multiple">
                                    @foreach($usuarios as $usuario)
                                        <option value="{{ $usuario->usu_codigo }}">
                                            {{ $usuario->usu_descripcion }} ({{ $usuario->usu_codigo }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Seleccione uno o más responsables</small>
                            </div>
        
                            <div class="mb-3">
                                <label for="herramientas" class="form-label">Herramientas/Equipos</label>
                                <select class="form-select select2-multiple" id="herramientas" name="herramientas[]" multiple="multiple">
                                    @foreach($inventario as $item)
                                        <option value="{{ $item->id }}">
                                            {{ $item->equipamiento }} ({{ $item->marca_modelo }}) - {{ $item->n_serie_lote }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Seleccione múltiples herramientas</small>
                            </div>
                        </div>
        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vehiculo" class="form-label">Vehículo</label>
                                <select class="form-select" id="vehiculo" name="vehiculo">
                                    <option value="">-- Sin cambios --</option>
                                    @foreach($vehiculos as $vehiculo)
                                        <option value="{{ $vehiculo->id }}">
                                            {{ $vehiculo->marca }} {{ $vehiculo->modelo }} {{ $vehiculo->patente }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_inicio_muestreo" class="form-label">Fecha y Hora de Inicio</label>
                                <input 
                                    type="datetime-local" 
                                    class="form-control" 
                                    id="fecha_inicio_muestreo" 
                                    name="fecha_inicio_muestreo"
                                    required
                                >
                            </div>
        
                            <div class="mb-3">
                                <label for="fecha_fin_muestreo" class="form-label">Fecha y Hora de Fin</label>
                                <input 
                                    type="datetime-local" 
                                    class="form-control" 
                                    id="fecha_fin_muestreo" 
                                    name="fecha_fin_muestreo"
                                    required
                                >
                            </div>
                        </div>
        
                        <!-- Campo de Frecuencia -->
                        <div class="col-md-6" id="frecuencia-container" style="display: none;">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="hidden" name="habilitar_frecuencia" value="0">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        id="habilitar_frecuencia" 
                                        name="habilitar_frecuencia"
                                        value="1"
                                    >
                                    <label class="form-check-label" for="habilitar_frecuencia">
                                        Habilitar coordinación frecuente
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3" id="frecuencia-opciones" style="display: none;">
                                <label for="frecuencia" class="form-label">Frecuencia</label>
                                <select class="form-select" id="frecuencia" name="frecuencia">
                                    <option value="">-- Seleccione frecuencia --</option>
                                    <option value="diario">Diario</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="quincenal">Quincenal</option>
                                    <option value="mensual">Mensual</option>
                                    <option value="trimestral">Trimestral</option>
                                    <option value="cuatrimestral">Cuatrimestral</option>
                                    <option value="semestral">Semestral</option>
                                    <option value="anual">Anual</option>
                                </select>
                            </div>
                        </div>
        
                        <!-- Variables requeridas -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Variables a Completar</label>
                                <div id="variables-container">
                                    <!-- Las variables se cargarán dinámicamente con JavaScript -->
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label" for="es_priori">
                                    <x-heroicon-o-star style="width: 20px; height: 20px; color: #ffc107;" />
                                    Marcar como Prioridad
                                </label>
                                <input type="checkbox" name="es_priori" id="es_priori" value="1" class="form-check-input" style="width: 20px; height: 20px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Aplicar Asignación</button>
                </div>
            </div>
        </form>        
    </div>
</div>


<!-- Modal de Recoordinación -->
<div class="modal fade" id="recoordinarModal" tabindex="-1" aria-labelledby="recoordinarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="recoordinarModalLabel">Recoordinar Muestra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="recoordinarForm">
                    @csrf
                    <input type="hidden" name="instancia_id" id="instancia_id">
                    <input type="hidden" name="cotio_numcoti" id="cotio_numcoti">
                    <input type="hidden" name="cotio_item" id="cotio_item">
                    <input type="hidden" name="instance_number" id="instance_number">

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="fecha_inicio_muestreo" class="form-label fw-semibold">Fecha Inicio Muestreo</label>
                            <input type="datetime-local" class="form-control rounded-3" id="fecha_inicio_muestreo" name="fecha_inicio_muestreo">
                        </div>
                        <div class="col-md-6">
                            <label for="fecha_fin_muestreo" class="form-label fw-semibold">Fecha Fin Muestreo</label>
                            <input type="datetime-local" class="form-control rounded-3" id="fecha_fin_muestreo" name="fecha_fin_muestreo">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="responsables_muestreo" class="form-label fw-semibold">Responsables de Muestreo</label>
                        <select class="form-select select2 rounded-3" id="responsables_muestreo" name="responsables_muestreo[]" multiple>
                            @foreach($usuarios as $responsable)
                                <option value="{{ $responsable->usu_codigo }}">{{ $responsable->usu_descripcion }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="vehiculo_asignado" class="form-label fw-semibold">Vehículo</label>
                        <select class="form-select rounded-3" id="vehiculo_asignado" name="vehiculo_asignado">
                            <option value="">Seleccione un vehículo</option>
                            @foreach($vehiculos as $vehiculo)
                                <option value="{{ $vehiculo->id }}">{{ $vehiculo->patente }} - {{ $vehiculo->modelo }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="herramientas" class="form-label fw-semibold">Herramientas de Muestreo</label>
                        <select class="form-select select2 rounded-3" id="herramientas" name="herramientas[]" multiple>
                            @foreach($inventario as $herramienta)
                                <option value="{{ $herramienta->id }}">
                                    {{ $herramienta->equipamiento }} ({{ $herramienta->marca_modelo }}) - {{ $herramienta->n_serie_lote }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Variables a Completar</label>
                        <div id="variables-container-recoordinacion" class="variables-main-container border rounded-3 p-3 bg-light">
                            <!-- Dynamic content -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="es_priori_recoordinar" class="form-label fw-semibold">
                            <x-heroicon-o-star style="width: 20px; height: 20px; color: #ffc107;" />
                            Marcar como Prioridad
                        </label>
                        <input type="checkbox" name="es_priori" id="es_priori_recoordinar" class="form-check-input" style="width: 20px; height: 20px;">
                    </div>

                    {{-- <div class="mb-4">
                        <label for="cotio_observaciones_suspension" class="form-label fw-semibold">Observaciones</label>
                        <textarea class="form-control rounded-3" id="cotio_observaciones_suspension" name="cotio_observaciones_suspension" rows="3"></textarea>
                    </div> --}}
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-3" id="guardarRecoordinacion">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>







<!-- Modal para suspender muestra -->
@foreach($agrupadas as $item)
    @php
        $instancia = $item['instancia'];
        $categoria = $item['categoria'];
    @endphp
    @if(trim($instancia->cotio_estado ?? '') == 'coordinado muestreo' && trim(Auth::user()->rol ?? '') == 'coordinador_muestreo')
    <div class="modal fade" id="suspenderModal{{ $instancia->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Suspender Muestra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de suspender esta muestra?</p>
                    <form id="suspenderForm{{ $instancia->id }}" method="POST" action="{{ route('asignar.suspension-muestra', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'instance_number' => $instancia->instance_number
                    ]) }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="observacion{{ $instancia->id }}" class="form-label">Observación de suspensión</label>
                            <input type="text" class="form-control" id="observacion{{ $instancia->id }}" 
                                   name="cotio_observaciones_suspension" 
                                   placeholder="Ingrese la razón de la suspensión" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" form="suspenderForm{{ $instancia->id }}">Suspender</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach

<script>
document.addEventListener('DOMContentLoaded', function() {
    @foreach($agrupadas as $item)
        @php
            $instancia = $item['instancia'];
        @endphp
        @if(!empty($instancia) && $instancia->cotio_estado == 'coordinado muestreo')
        const form{{ $instancia->id }} = document.getElementById('suspenderForm{{ $instancia->id }}');
        const textarea{{ $instancia->id }} = document.getElementById('observacion{{ $instancia->id }}');
        
        if (form{{ $instancia->id }}) {
            form{{ $instancia->id }}.addEventListener('submit', function(event) {
                if (!textarea{{ $instancia->id }}.value.trim()) {
                    event.preventDefault();
                    event.stopPropagation();
                    textarea{{ $instancia->id }}.classList.add('is-invalid');
                } else {
                    textarea{{ $instancia->id }}.classList.remove('is-invalid');
                }
                
                form{{ $instancia->id }}.classList.add('was-validated');
            });
            
            document.getElementById('suspenderModal{{ $instancia->id }}').addEventListener('hidden.bs.modal', function() {
                form{{ $instancia->id }}.classList.remove('was-validated');
                textarea{{ $instancia->id }}.classList.remove('is-invalid');
            });
        }
        @endif
    @endforeach
});
</script>

@endsection





<script>
    let cambiosPendientes = {};
    let hasChanges = false;

    function toggleMuestras(id) {
        const content = document.getElementById(id);
        if (content) {
            content.classList.toggle('show');
        }
        
        const chevronIcon = document.getElementById('chevron-' + id);
        if (chevronIcon) {
            chevronIcon.classList.toggle('rotate-180');
        }
    }

    function toggleTareas(checkbox) {
        const itemId = checkbox.dataset.item;
        const instance = checkbox.dataset.instance;
        
        cambiosPendientes[`${itemId}-0-${instance}`] = checkbox.checked;
        hasChanges = true;

        const checkboxesSeleccionados = document.querySelectorAll('.categoria-checkbox:checked, .tarea-checkbox:checked');
        const btnAsignacionMasiva = document.getElementById('btn-asignacion-masiva');
        
        if (checkboxesSeleccionados.length > 0) {
            btnAsignacionMasiva.disabled = false;
        } else {
            btnAsignacionMasiva.disabled = true;
        }
        
        actualizarEstadoBoton();
    }


    function actualizarEstadoTarea(checkbox) {
        const value = checkbox.value; 
        const isChecked = checkbox.checked;
        const wasChecked = checkbox.dataset.activo === '1';
        
        if (isChecked !== wasChecked) {
            cambiosPendientes[value] = isChecked;
            hasChanges = true;
            
            checkbox.closest('li').classList.toggle('checkbox-modified', isChecked !== wasChecked);
        } else {
            if (cambiosPendientes[value] !== undefined) {
                delete cambiosPendientes[value];
                checkbox.closest('li').classList.remove('checkbox-modified');
            }
        }
        
        actualizarEstadoBoton();
    }

    function actualizarEstadoBoton() {
        const hayCambios = Object.values(cambiosPendientes).some(value => value === true);
        const btn = document.getElementById('btn-aplicar-cambios');
        if (btn) btn.disabled = !hayCambios;
    }

    async function confirmarCambios() {
        if (!hasChanges) return;
        
        const categoriasActivadas = Object.entries(cambiosPendientes)
            .filter(([key, value]) => key.endsWith('-0') && value).length;
        const tareasActivadas = Object.entries(cambiosPendientes)
            .filter(([key, value]) => !key.endsWith('-0') && value).length;
        
        let mensaje = '';
        if (categoriasActivadas > 0 && tareasActivadas > 0) {
            mensaje = `Vas a habilitar ${categoriasActivadas} muestra(s) y ${tareasActivadas} análisis para muestreo. ¿Deseas continuar?`;
        } else if (categoriasActivadas > 0) {
            mensaje = `Vas a habilitar ${categoriasActivadas} muestra(s) para muestreo. ¿Deseas continuar?`;
        } else {
            mensaje = `Vas a habilitar ${tareasActivadas} análisis para muestreo. ¿Deseas continuar?`;
        }
        
        const { isConfirmed } = await Swal.fire({
            title: 'Confirmar cambios',
            html: mensaje,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar',
            cancelButtonText: 'Cancelar'
        });
        
        if (!isConfirmed) return;
        
        Swal.fire({
            title: 'Aplicando cambios...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        try {
            const response = await fetch('{{ route("tareas.pasar-muestreo") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    cambios: cambiosPendientes,
                    cotio_numcoti: '{{ $cotizacion->coti_num }}'
                })
            });
            
            const data = await response.json();
            
            if (response.ok) {
                Object.keys(cambiosPendientes).forEach(value => {
                    const [itemId, subitem, instance] = value.split('-');
                    if (subitem === '0') {
                        const categoriaCheckbox = document.querySelector(`.categoria-checkbox[data-item="${itemId}"][data-instance="${instance}"]`);
                        if (categoriaCheckbox) {
                            categoriaCheckbox.dataset.activo = cambiosPendientes[value] ? '1' : '0';
                        }
                    } else {
                        const checkbox = document.querySelector(`.tarea-checkbox[value="${value}"]`);
                        if (checkbox) {
                            checkbox.dataset.activo = cambiosPendientes[value] ? '1' : '0';
                            checkbox.closest('li').classList.remove('checkbox-modified');
                        }
                    }
                });
                
                cambiosPendientes = {};
                hasChanges = false;
                var btnAplicar = document.getElementById('btn-aplicar-cambios');
                if (btnAplicar) btnAplicar.disabled = true;
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Cambios aplicados!',
                    text: 'Los estados se han actualizado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Recargar la página para reflejar cambios
                setTimeout(() => window.location.reload(), 2000);
            } else {
                throw new Error(data.message || 'Error al aplicar cambios');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        }
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Seleccionar muestras] DOMContentLoaded ejecutado');
    // Obtener variables requeridas y si son obligatorias
    const variablesRequeridas = @json($variablesRequeridas);
    const mandatoryVariables = @json(\App\Models\VariableRequerida::where('obligatorio', true)->get()->groupBy('cotio_descripcion')->mapWithKeys(function ($variables, $tipoMuestra) {
        return [$tipoMuestra => $variables->pluck('id')->toArray()];
    }));

    // Configuración inicial de checkboxes
    document.querySelectorAll('.categoria-checkbox, .tarea-checkbox').forEach(checkbox => {
        if (checkbox.checked) {
            checkbox.setAttribute('data-persisted', 'true');
            checkbox.setAttribute('data-original-checked', 'true');
        }
    });

    function handleCheckboxState() {
        const btnAsignacionMasiva = document.getElementById('btn-asignacion-masiva');
        const hasManualSelections = document.querySelectorAll('.categoria-checkbox:checked:not([data-persisted]), .tarea-checkbox:checked:not([data-persisted])').length > 0;
        btnAsignacionMasiva.disabled = !hasManualSelections;
    }

    // Selección por tipo o todas: botón "Seleccionar muestras"
    var menuSeleccionar = document.getElementById('menu-seleccionar-muestras');
    if (menuSeleccionar) {
        console.log('[Seleccionar muestras] Menu encontrado, registrando click');
        menuSeleccionar.addEventListener('click', function(e) {
            console.log('[Seleccionar muestras] Click en menu, target:', e.target, 'currentTarget:', e.currentTarget);
            var link = e.target.closest('[data-seleccion]');
            if (!link) {
                console.log('[Seleccionar muestras] No es un botón con data-seleccion');
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var accion = link.dataset.seleccion;
            var descripcion = (link.dataset.descripcion || '').toString().trim();
            // Solo muestras disponibles: no deshabilitadas (no en OT) y no ya coordinadas (sin data-persisted)
            var checkboxes = document.querySelectorAll('.categoria-checkbox:not([disabled]):not([data-persisted])');
            var count = 0;
            console.log('[Seleccionar muestras] Accion:', accion, 'descripcion:', descripcion, 'checkboxes:', checkboxes.length);
            if (accion === 'todas') {
                var todasChecked = checkboxes.length > 0 && Array.from(checkboxes).every(function(cb) { return cb.checked; });
                checkboxes.forEach(function(cb) {
                    cb.checked = !todasChecked;
                    if (typeof toggleTareas === 'function') toggleTareas(cb);
                    if (!todasChecked) count++;
                    else count--;
                });
                console.log('[Seleccionar muestras] Todas:', todasChecked ? 'desmarcadas' : 'marcadas', count < 0 ? -count : count, 'de', checkboxes.length);
            } else if (accion === 'tipo' && descripcion) {
                var delTipo = Array.from(checkboxes).filter(function(cb) { return (cb.dataset.descripcion || '').trim() === descripcion; });
                var tipoChecked = delTipo.length > 0 && delTipo.every(function(cb) { return cb.checked; });
                delTipo.forEach(function(cb) {
                    cb.checked = !tipoChecked;
                    if (typeof toggleTareas === 'function') toggleTareas(cb);
                    if (!tipoChecked) count++;
                    else count--;
                });
                console.log('[Seleccionar muestras] Tipo "' + descripcion + '":', tipoChecked ? 'desmarcadas' : 'marcadas', count < 0 ? -count : count);
            } else {
                console.log('[Seleccionar muestras] Acción no manejada o descripcion vacía');
            }
            if (count !== 0 && typeof handleCheckboxState === 'function') handleCheckboxState();
            if (count !== 0 && typeof actualizarEstadoBoton === 'function') actualizarEstadoBoton();
        });
    } else {
        console.warn('[Seleccionar muestras] No se encontró #menu-seleccionar-muestras');
    }

    function validateFrecuenciaEligibility(items) {
        const muestras = items.filter(item => item.subitem === '0');
        if (muestras.length < 2) return false;

        const firstMuestra = muestras[0];
        return muestras.every(muestra => 
            muestra.item === firstMuestra.item && 
            muestra.descripcion === firstMuestra.descripcion &&
            muestra.subitem === '0'
        );
    }

    function mostrarModalAsignacion() {
        const checkboxes = document.querySelectorAll('.categoria-checkbox:checked:not([data-persisted]), .tarea-checkbox:checked:not([data-persisted])');
        
        const items = Array.from(checkboxes).map(checkbox => {
            return checkbox.classList.contains('categoria-checkbox') 
                ? { 
                    item: checkbox.dataset.item, 
                    subitem: '0', 
                    instance: checkbox.dataset.instance, 
                    isManual: true,
                    descripcion: checkbox.closest('.card-header').querySelector('strong').textContent.trim(),
                    instanciaId: checkbox.closest('.mi-tarjeta').querySelector('input[type="checkbox"]').dataset.instanciaId // Add instanciaId
                }
                : { 
                    item: checkbox.dataset.item, 
                    subitem: checkbox.dataset.subitem, 
                    instance: checkbox.dataset.instance, 
                    isManual: true,
                    descripcion: checkbox.closest('.list-group-item').querySelector('.d-flex.gap-2').childNodes[0].textContent.trim(),
                    instanciaId: checkbox.closest('.mi-tarjeta').querySelector('input[type="checkbox"]').dataset.instanciaId // Add instanciaId
                };
        });

        const manualInstances = new Set();
        checkboxes.forEach(checkbox => {
            const instanceKey = checkbox.classList.contains('categoria-checkbox') 
                ? `${checkbox.dataset.item}-0-${checkbox.dataset.instance}`
                : `${checkbox.dataset.item}-${checkbox.dataset.subitem}-${checkbox.dataset.instance}`;
            manualInstances.add(instanceKey);
        });

        document.querySelectorAll('.categoria-checkbox[data-persisted][data-original-checked="true"], .tarea-checkbox[data-persisted][data-original-checked="true"]').forEach(checkbox => {
            const instanceKey = checkbox.classList.contains('categoria-checkbox') 
                ? `${checkbox.dataset.item}-0-${checkbox.dataset.instance}`
                : `${checkbox.dataset.item}-${checkbox.dataset.subitem}-${checkbox.dataset.instance}`;
            
            let shouldInclude = false;
            manualInstances.forEach(manualKey => {
                const [manualItem, manualSubitem, manualInstance] = manualKey.split('-');
                const [currentItem, currentSubitem, currentInstance] = instanceKey.split('-');
                
                if (currentInstance === manualInstance && 
                    (currentSubitem === '0' && manualSubitem === '0' && currentItem === manualItem || 
                     currentItem === manualItem)) {
                    shouldInclude = true;
                }
            });

            if (shouldInclude) {
                const descripcion = checkbox.classList.contains('categoria-checkbox') 
                    ? checkbox.closest('.card-header').querySelector('strong').textContent.trim()
                    : checkbox.closest('.list-group-item').querySelector('.d-flex.gap-2').childNodes[0].textContent.trim();
                
                items.push({
                    item: checkbox.dataset.item,
                    subitem: checkbox.classList.contains('categoria-checkbox') ? '0' : checkbox.dataset.subitem,
                    instance: checkbox.dataset.instance,
                    isManual: false,
                    descripcion: descripcion,
                    instanciaId: checkbox.closest('.mi-tarjeta').querySelector('input[type="checkbox"]').dataset.instanciaId // Add instanciaId
                });
            }
        });

        if (items.length === 0) {
            Swal.fire({
                title: 'Selección requerida',
                text: 'Por favor seleccione al menos una muestra o análisis',
                icon: 'warning'
            });
            return;
        }

        // Restringir vehículos según tipos seleccionados: si algún tipo (cotio_descripcion) ya tiene vehículo fijado, solo mostrar ese(s) vehículo(s)
        const muestras = items.filter(i => i.subitem === '0');
        const descripcionesUnicas = [...new Set(muestras.map(m => (m.descripcion || '').toString().trim()))].filter(Boolean);
        const vehiculosPermitidosIds = new Set();
        descripcionesUnicas.forEach(desc => {
            const id = window.vehiculoFijadoPorTipo && window.vehiculoFijadoPorTipo[desc];
            if (id) vehiculosPermitidosIds.add(parseInt(id));
        });
        const selectVehiculo = document.getElementById('vehiculo');
        selectVehiculo.innerHTML = '<option value="">-- Sin cambios --</option>';
        let vehiculosToShow = window.todosVehiculos || [];
        if (vehiculosPermitidosIds.size > 0) {
            vehiculosToShow = vehiculosToShow.filter(v => vehiculosPermitidosIds.has(parseInt(v.id)));
        }
        vehiculosToShow.forEach(v => {
            const text = [v.marca, v.modelo, v.patente].filter(Boolean).join(' ') || v.patente + ' - ' + v.modelo;
            selectVehiculo.appendChild(new Option(text, v.id));
        });

        // Mostrar u ocultar el campo de frecuencia
        const frecuenciaContainer = document.getElementById('frecuencia-container');
        const frecuenciaOpciones = document.getElementById('frecuencia-opciones');
        const habilitarFrecuencia = document.getElementById('habilitar_frecuencia');
        if (validateFrecuenciaEligibility(items)) {
            frecuenciaContainer.style.display = 'block';
            habilitarFrecuencia.addEventListener('change', function() {
                frecuenciaOpciones.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    document.getElementById('frecuencia').value = '';
                }
            });
        } else {
            frecuenciaContainer.style.display = 'none';
            habilitarFrecuencia.checked = false;
            frecuenciaOpciones.style.display = 'none';
            document.getElementById('frecuencia').value = '';
        }

        // Cargar variables requeridas dinámicamente
        const variablesContainer = document.getElementById('variables-container');
        variablesContainer.innerHTML = '';

        const itemsPorTipo = items.reduce((acc, item) => {
            const tipo = item.descripcion;
            if (!acc[tipo]) {
                acc[tipo] = [];
            }
            acc[tipo].push(item);
            return acc;
        }, {});

        const mainContainer = document.createElement('div');
        mainContainer.className = 'variables-main-container';
        mainContainer.style.maxHeight = '400px';
        mainContainer.style.overflowY = 'auto';
        mainContainer.style.paddingRight = '10px';

        Object.entries(variablesRequeridas).forEach(([tipoMuestra, variables]) => {
            if (itemsPorTipo[tipoMuestra]) {
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'mb-4 variable-category';
                
                const categoryHeader = document.createElement('div');
                categoryHeader.className = 'd-flex align-items-center mb-2 category-header';
                categoryHeader.innerHTML = `
                    <h6 class="mb-0 flex-grow-1">
                        <i class="fas fa-flask me-2"></i>${tipoMuestra}
                    </h6>
                    <small class="text-muted">${itemsPorTipo[tipoMuestra].length} items</small>
                `;
                categoryDiv.appendChild(categoryHeader);

                const variablesGrid = document.createElement('div');
                variablesGrid.className = 'row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2';

                Object.entries(variables).forEach(([id, variable]) => {
                    const isMandatory = mandatoryVariables[tipoMuestra]?.includes(parseInt(id));
                    
                    const colDiv = document.createElement('div');
                    colDiv.className = 'col';
                    
                    const cardDiv = document.createElement('div');
                    cardDiv.className = `card h-100 variable-card ${isMandatory ? 'border-primary' : ''}`;
                    
                    const cardBody = document.createElement('div');
                    cardBody.className = 'card-body p-2';
                    
                    cardBody.innerHTML = `
                        <div class="form-check d-flex align-items-center">
                            <input type="checkbox" 
                                class="form-check-input variable-checkbox flex-shrink-0" 
                                data-tipo="${tipoMuestra}" 
                                data-variable="${variable}" 
                                value="${id}" 
                                ${isMandatory ? 'checked disabled' : 'checked'}
                                style="margin-top: 0;">
                            <label class="form-check-label ms-2 flex-grow-1 d-flex align-items-center">
                                <span class="variable-name">${variable}</span>
                                ${isMandatory ? '<span class="badge bg-primary ms-2">Obligatorio</span>' : ''}
                            </label>
                            ${isMandatory ? '<i class="fas fa-exclamation-circle text-primary ms-2" title="Variable obligatoria"></i>' : ''}
                        </div>
                    `;
                    
                    cardDiv.appendChild(cardBody);
                    colDiv.appendChild(cardDiv);
                    variablesGrid.appendChild(colDiv);
                });

                categoryDiv.appendChild(variablesGrid);
                mainContainer.appendChild(categoryDiv);
            }
        });

        variablesContainer.appendChild(mainContainer);

        // Actualizar el campo oculto con los items seleccionados
        document.getElementById('items_seleccionados').value = JSON.stringify(items);

        // Preseleccionar los responsables actuales en el modal
        const responsablesSelect = document.getElementById('responsables_muestreo');
        const selectedInstanciaIds = [...new Set(items.filter(item => item.subitem === '0').map(item => item.instanciaId))]; // Unique instancia IDs for samples

        fetch('/api/get-responsables-muestreo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ instancia_ids: selectedInstanciaIds })
        })
        .then(response => response.json())
        .then(data => {
            const responsables = data.responsables; // Array of user codes (e.g., ['LAB1', 'LAB2'])
            $(responsablesSelect).val(responsables).trigger('change'); // Preselect in Select2
        })
        .catch(error => {
            console.error('Error fetching responsables:', error);
            Swal.fire({
                title: 'Error',
                text: 'No se pudieron cargar los responsables actuales',
                icon: 'error'
            });
        });

        // Inicializar el campo de parámetros seleccionados
        updateParametrosSeleccionados();

        const modal = $('#asignacionMasivaModal');
        modal.find('select:not(#responsables_muestreo)').val('').trigger('change'); // Reset other selects
        modal.find('input[type="datetime-local"]').val('');
        modal.modal('show');
    }

    function updateParametrosSeleccionados() {
        const items = JSON.parse(document.getElementById('items_seleccionados').value || '[]');
        const parametrosSeleccionados = [];

        items.forEach(item => {
            const tipoMuestra = item.subitem === '0' 
                ? item.descripcion 
                : items.find(i => i.item === item.item && i.subitem === '0' && i.instance === item.instance)?.descripcion || item.descripcion;

            const variables = Array.from(document.querySelectorAll(`.variable-checkbox[data-tipo="${tipoMuestra}"]:checked`))
                .map(cb => parseInt(cb.value));

            if (variables.length > 0) {
                parametrosSeleccionados.push({
                    item: item.item,
                    subitem: item.subitem,
                    instance: item.instance,
                    variables: variables
                });
            }
        });

        document.getElementById('parametros_seleccionados').value = JSON.stringify(parametrosSeleccionados);
    }

    // Función para remover un responsable
    window.removerResponsable = function(event, instanciaId, userCodigo, tipo, todos = true) {
        event.preventDefault();
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas remover a ${userCodigo} como responsable?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, remover',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('/muestras/remover-responsable', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        instancia_id: instanciaId,
                        user_codigo: userCodigo,
                        todos: todos ? 'true' : 'false'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Éxito',
                            text: data.message,
                            icon: 'success'
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message,
                            icon: 'error'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Ocurrió un error al remover el responsable',
                        icon: 'error'
                    });
                });
            }
        });
    };

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('variable-checkbox')) {
            updateParametrosSeleccionados();
        }
    });

    document.querySelectorAll('.categoria-checkbox, .tarea-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                this.removeAttribute('data-persisted');
            } else if (this.hasAttribute('data-original-checked')) {
                this.checked = true;
            }
            handleCheckboxState();
        });
    });

    handleCheckboxState();

    $('#asignacionMasivaModal').on('show.bs.modal', function() {
        // Solo establecer valores por defecto si los campos están vacíos
        const fechaInicioInput = document.getElementById('fecha_inicio_muestreo');
        const fechaFinInput = document.getElementById('fecha_fin_muestreo');
        
        if (!fechaInicioInput.value && !fechaFinInput.value) {
            const now = new Date();
            now.setHours(8, 0, 0, 0);
            fechaInicioInput.value = formatDateTimeForInput(now);
            
            const endDate = new Date(now);
            endDate.setHours(18, 0, 0, 0);
            fechaFinInput.value = formatDateTimeForInput(endDate);
        }
    });

    function formatDateTimeForInput(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    // Función para ajustar la fecha de fin cuando se cambia la fecha de inicio
    document.getElementById('fecha_inicio_muestreo').addEventListener('change', function() {
        const startDateInput = this;
        const endDateInput = document.getElementById('fecha_fin_muestreo');
        
        if (startDateInput.value) {
            const startDate = new Date(startDateInput.value);
            
            // Ajustar la fecha de fin al mismo día con hora 18:00
            const endDate = new Date(startDate);
            endDate.setHours(18, 0, 0, 0);
            
            // Solo actualizar si la fecha de fin no está establecida o si es anterior a la nueva fecha de inicio
            if (!endDateInput.value) {
                endDateInput.value = formatDateTimeForInput(endDate);
            } else {
                const currentEndDate = new Date(endDateInput.value);
                // Si la fecha de fin actual es anterior o igual a la nueva fecha de inicio, ajustarla
                if (currentEndDate <= startDate) {
                    endDateInput.value = formatDateTimeForInput(endDate);
                }
            }
        }
    });

    // Función para ajustar la fecha de inicio cuando se cambia la fecha de fin
    document.getElementById('fecha_fin_muestreo').addEventListener('change', function() {
        const endDateInput = this;
        const startDateInput = document.getElementById('fecha_inicio_muestreo');
        
        if (endDateInput.value) {
            const endDate = new Date(endDateInput.value);
            
            // Ajustar la fecha de inicio al mismo día con hora 8:00
            const startDate = new Date(endDate);
            startDate.setHours(8, 0, 0, 0);
            
            // Solo actualizar si la fecha de inicio no está establecida o si es posterior a la nueva fecha de fin
            if (!startDateInput.value) {
                startDateInput.value = formatDateTimeForInput(startDate);
            } else {
                const currentStartDate = new Date(startDateInput.value);
                // Si la fecha de inicio actual es posterior a la nueva fecha de fin, ajustarla
                if (currentStartDate >= endDate) {
                    startDateInput.value = formatDateTimeForInput(startDate);
                }
            }
        }
    });

    document.getElementById('btn-asignacion-masiva').addEventListener('click', mostrarModalAsignacion);
    
    document.getElementById('asignacionMasivaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fechaInicio = document.getElementById('fecha_inicio_muestreo').value;
        const fechaFin = document.getElementById('fecha_fin_muestreo').value;
        
        if (!fechaInicio || !fechaFin) {
            Swal.fire({
                title: 'Fechas requeridas',
                text: 'Debe especificar fechas de inicio y fin',
                icon: 'warning'
            });
            return;
        }

        if (new Date(fechaFin) <= new Date(fechaInicio)) {
            Swal.fire({
                title: 'Fecha inválida',
                text: 'La fecha de fin debe ser posterior a la fecha de inicio',
                icon: 'error'
            });
            return;
        }

        // Validación: la fecha de fin debe ser posterior a la de inicio (permitir sábados y domingos)

        // let missingMandatory = false;
        // Object.entries(mandatoryVariables).forEach(([tipoMuestra, variableIds]) => {
        //     const selectedVariables = Array.from(document.querySelectorAll(`.variable-checkbox[data-tipo="${tipoMuestra}"]:checked`))
        //         .map(cb => parseInt(cb.value));
        //     variableIds.forEach(id => {
        //         if (!selectedVariables.includes(id)) {
        //             missingMandatory = true;
        //         }
        //     });
        // });

        // if (missingMandatory) {
        //     Swal.fire({
        //         title: 'Variables obligatorias faltantes',
        //         text: 'Debe seleccionar todas las variables obligatorias marcadas con *',
        //         icon: 'warning'
        //     });
        //     return;
        // }

        fetch(this.action, {
            method: 'POST',
            body: new FormData(this),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: data.message,
                    icon: 'success'
                }).then(() => window.location.reload());
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Ocurrió un error al procesar la solicitud',
                icon: 'error'
            });
        });
    });

    $(document).ready(function() {
        $('#herramientas').select2({
            placeholder: "Seleccione herramientas",
            width: '100%',
            dropdownParent: $('#asignacionMasivaModal')
        });

        $('#responsables_muestreo').select2({
            placeholder: "Seleccione responsables",
            width: '100%',
            dropdownParent: $('#asignacionMasivaModal')
        });
    });
});

//recoordinar muestra  
document.addEventListener('DOMContentLoaded', function() {

    @php
        $todosVehiculosParaJs = $vehiculos->map(function ($v) {
            return ['id' => $v->id, 'marca' => $v->marca, 'modelo' => $v->modelo, 'patente' => $v->patente];
        })->values()->toArray();
    @endphp
    window.vehiculoFijadoPorTipo = @json($vehiculoFijadoPorTipo ?? []);
    window.todosVehiculos = @json($todosVehiculosParaJs);

    // Inicializar selects múltiples
    $('#responsables_muestreo').select2({
        dropdownParent: $('#recoordinarModal'),
        multiple: true,
        placeholder: "Seleccione responsables",
        allowClear: true,
        templateSelection: function(data) {
            if ($('#responsables_muestreo').find('option:selected').filter(function() {
                return this.value === data.id;
            }).length) {
                return $('<span class="bg-primary-light p-1 rounded">' + data.text + '</span>');
            }
            return data.text;
        }
    });

    $('#herramientas').select2({
        dropdownParent: $('#recoordinarModal'),
        templateSelection: function(data) {
            // Resaltar los seleccionados con un fondo diferente
            if ($('#herramientas').find('option:selected').filter(function() {
                return this.value === data.id;
            }).length) {
                return $('<span class="bg-primary-light p-1 rounded">' + data.text + '</span>');
            }
            return data.text;
        }
    });

    // Manejar la apertura del modal
    $('#recoordinarModal').on('show.bs.modal', async function(event) {
        const button = $(event.relatedTarget);
        const instanciaId = button.data('instancia');
        const cotizacionNum = button.data('cotizacion');
        const item = button.data('item');
        const instanceNumber = button.data('instance');

        // Llenar campos ocultos
        $('#instancia_id').val(instanciaId);
        $('#cotio_numcoti').val(cotizacionNum);
        $('#cotio_item').val(item);
        $('#instance_number').val(instanceNumber);

        // Restringir vehículos según el tipo de muestra (cotio_descripcion): si este tipo ya tiene un vehículo fijado, solo mostrar ese
        const select = document.getElementById('vehiculo_asignado');
        const descripcion = (button.data('descripcion') || '').toString().trim();
        const fijadoId = descripcion && window.vehiculoFijadoPorTipo ? window.vehiculoFijadoPorTipo[descripcion] : null;
        select.innerHTML = '<option value="">Seleccione un vehículo</option>';
        const vehiculosParaTipo = fijadoId
            ? (window.todosVehiculos || []).filter(v => v.id == fijadoId)
            : (window.todosVehiculos || []);
        vehiculosParaTipo.forEach(v => {
            select.appendChild(new Option(v.patente + ' - ' + v.modelo, v.id));
        });

        // Cargar datos actuales via AJAX
        const response = await fetch(`/muestras/${instanciaId}/datos-recoordinacion`);
        const data = await response.json();
        console.log(data);
        
        fetch(`/muestras/${instanciaId}/datos-recoordinacion`)
            .then(response => response.json())
            .then(data => {
                // Llenar el formulario con los datos actuales
                $('#fecha_inicio_muestreo').val(data.fecha_inicio_muestreo);
                $('#fecha_fin_muestreo').val(data.fecha_fin_muestreo);
                $('#vehiculo_asignado').val(data.vehiculo_asignado).trigger('change');
                $('#cotio_observaciones_suspension').val(data.cotio_observaciones_suspension);
                
                // Establecer el checkbox de prioridad
                $('#es_priori_recoordinar').prop('checked', data.es_priori == 1);

                // Seleccionar responsables
                if (data.responsables && data.responsables.length > 0) {
                    $('#responsables_muestreo').val(data.responsables.map(r => r.usu_codigo)).trigger('change');
                    
                    // Agregar clase a los elementos seleccionados
                    setTimeout(() => {
                        $('.select2-selection__choice').addClass('bg-primary-light');
                    }, 100);
                }

                // Seleccionar herramientas
                $('#herramientas').val(data.herramientas.map(h => h.id)).trigger('change');

                // Cargar variables requeridas
                const variablesContainer = document.getElementById('variables-container-recoordinacion');
                variablesContainer.innerHTML = '';

                Object.entries(data.variables_requeridas).forEach(([tipoMuestra, variables]) => {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.className = 'mb-4 variable-category';
                    
                    const categoryHeader = document.createElement('div');
                    categoryHeader.className = 'd-flex align-items-center mb-2 category-header';
                    categoryHeader.innerHTML = `
                        <h6 class="mb-0 flex-grow-1">
                            <i class="fas fa-flask me-2"></i>${tipoMuestra}
                        </h6>
                    `;
                    categoryDiv.appendChild(categoryHeader);

                    const variablesGrid = document.createElement('div');
                    variablesGrid.className = 'row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2';

                    Object.entries(variables).forEach(([id, variable]) => {
                        const isMandatory = variable.obligatorio;
                        const isSelected = data.variables_seleccionadas?.includes(parseInt(id));
                        
                        const colDiv = document.createElement('div');
                        colDiv.className = 'col';
                        
                        const cardDiv = document.createElement('div');
                        cardDiv.className = `card h-100 variable-card ${isMandatory ? 'border-primary' : ''}`;
                        
                        const cardBody = document.createElement('div');
                        cardBody.className = 'card-body p-2';
                        
                        cardBody.innerHTML = `
                            <div class="form-check d-flex align-items-center">
                                <input type="checkbox" 
                                    class="form-check-input variable-checkbox flex-shrink-0" 
                                    data-tipo="${tipoMuestra}" 
                                    data-variable="${variable.nombre}" 
                                    value="${id}" 
                                    ${isMandatory ? 'checked disabled' : ''}
                                    ${isSelected ? 'checked' : ''}
                                    style="margin-top: 0;">
                                <label class="form-check-label ms-2 flex-grow-1 d-flex align-items-center">
                                    <span class="variable-name">${variable.nombre}</span>
                                    ${isMandatory ? '<span class="badge bg-primary ms-2">Obligatorio</span>' : ''}
                                </label>
                                ${isMandatory ? '<i class="fas fa-exclamation-circle text-primary ms-2" title="Variable obligatoria"></i>' : ''}
                            </div>
                        `;
                        
                        cardDiv.appendChild(cardBody);
                        colDiv.appendChild(cardDiv);
                        variablesGrid.appendChild(colDiv);
                    });

                    categoryDiv.appendChild(variablesGrid);
                    variablesContainer.appendChild(categoryDiv);
                });
            });
    });

    // Ajustar fechas automáticamente en el modal de recoordinación
    $(document).on('change', '#recoordinarModal #fecha_inicio_muestreo', function() {
        const startDateInput = this;
        const endDateInput = document.getElementById('fecha_fin_muestreo');
        
        if (startDateInput.value) {
            const startDate = new Date(startDateInput.value);
            
            // Ajustar la fecha de fin al mismo día con hora 18:00
            const endDate = new Date(startDate);
            endDate.setHours(18, 0, 0, 0);
            
            // Solo actualizar si la fecha de fin no está establecida o si es anterior a la nueva fecha de inicio
            if (!endDateInput.value) {
                endDateInput.value = formatDateTimeForInput(endDate);
            } else {
                const currentEndDate = new Date(endDateInput.value);
                // Si la fecha de fin actual es anterior o igual a la nueva fecha de inicio, ajustarla
                if (currentEndDate <= startDate) {
                    endDateInput.value = formatDateTimeForInput(endDate);
                }
            }
        }
    });

    $(document).on('change', '#recoordinarModal #fecha_fin_muestreo', function() {
        const endDateInput = this;
        const startDateInput = document.getElementById('fecha_inicio_muestreo');
        
        if (endDateInput.value) {
            const endDate = new Date(endDateInput.value);
            
            // Ajustar la fecha de inicio al mismo día con hora 8:00
            const startDate = new Date(endDate);
            startDate.setHours(8, 0, 0, 0);
            
            // Solo actualizar si la fecha de inicio no está establecida o si es posterior a la nueva fecha de fin
            if (!startDateInput.value) {
                startDateInput.value = formatDateTimeForInput(startDate);
            } else {
                const currentStartDate = new Date(startDateInput.value);
                // Si la fecha de inicio actual es posterior a la nueva fecha de fin, ajustarla
                if (currentStartDate >= endDate) {
                    startDateInput.value = formatDateTimeForInput(startDate);
                }
            }
        }
    });

    // Manejar el envío del formulario
    $('#guardarRecoordinacion').click(function() {
        const formData = new FormData($('#recoordinarForm')[0]);
        const jsonData = {};

        // Procesar el FormData
        for (let [key, value] of formData.entries()) {
            if (key === 'responsables_muestreo[]' || key === 'herramientas[]') {
                const cleanKey = key.replace('[]', '');
                if (!jsonData[cleanKey]) {
                    jsonData[cleanKey] = [];
                }
                jsonData[cleanKey].push(value);
            } else {
                jsonData[key] = value;
            }
        }

        // Agregar variables seleccionadas
        const variablesSeleccionadas = [];
        document.querySelectorAll('.variable-checkbox:checked').forEach(checkbox => {
            variablesSeleccionadas.push(parseInt(checkbox.value));
        });
        jsonData.variables_seleccionadas = variablesSeleccionadas;
        jsonData.es_priori = $('#es_priori_recoordinar').is(':checked') ? 1 : 0;

        // Asegurarse de que los arrays no estén vacíos
        if (jsonData.responsables_muestreo && jsonData.responsables_muestreo.length === 0) {
            delete jsonData.responsables_muestreo;
        }
        if (jsonData.herramientas && jsonData.herramientas.length === 0) {
            delete jsonData.herramientas;
        }

        fetch('/muestras/recoordinar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                $('#recoordinarModal').modal('hide');
                Swal.fire({
                    title: 'Éxito',
                    text: data.message,
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Ocurrió un error al guardar los cambios',
                icon: 'error'
            });
        });
    });
});



const pasarDirectoAOT = (instancia) => {
    const cotio_numcoti = instancia.cotio_numcoti;
    const cotio_item = instancia.cotio_item;
    const instance_number = instancia.instance_number;

    fetch(`/muestras/pasar-directo-a-ot/${cotio_numcoti}/${cotio_item}/${instance_number}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            cotio_numcoti,
            cotio_item,
            instance_number
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Éxito',
                text: data.message,
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message,
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'Ocurrió un error al guardar los cambios',
            icon: 'error'
        });
    });
}

const quitarDirectoAOT = (instancia) => {
    const cotio_numcoti = instancia.cotio_numcoti;
    const cotio_item = instancia.cotio_item;
    const instance_number = instancia.instance_number;
    const isFromCoordinador = false;

    fetch(`/muestras/quitar-directo-a-ot/${cotio_numcoti}/${cotio_item}/${instance_number}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Éxito',
                text: data.message,
                icon: 'success'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: data.message,
                icon: 'error'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error',
            text: 'Ocurrió un error al quitar de OT',
            icon: 'error'
        });
    });
};



</script>



<style>
        .mi-tarjeta {
            cursor: pointer;
        }

        .mi-tarjeta:hover {
            transition: all 0.3s ease;
            cursor: pointer;
            transform: scale(1.02);
        }

        .collapse-content {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: max-height 0.5s ease, opacity 0.5s ease;
        }

        .collapse-content.show {
            max-height: 100%;
            opacity: 1;
        }

        .rotate-180 {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
        }


        .checkbox-modified {
            position: relative;
        }
        .checkbox-modified::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 123, 255, 0.1);
            border-radius: 0.25rem;
        }


        .variables-main-container {
            scrollbar-width: thin;
            scrollbar-color: #ddd #f8f9fa;
        }

        .variables-main-container::-webkit-scrollbar {
            width: 8px;
        }

        .variables-main-container::-webkit-scrollbar-track {
            background: #f8f9fa;
        }

        .variables-main-container::-webkit-scrollbar-thumb {
            background-color: #ddd;
            border-radius: 4px;
        }

        .variable-category {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            border-left: 4px solid #0d6efd;
        }

        .category-header {
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .variable-card {
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
        }

        .variable-card:hover {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transform: translateY(-2px);
        }

        .variable-card.border-primary {
            border-left: 3px solid #0d6efd;
        }

        .variable-name {
            font-weight: 500;
            color: #212529;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .form-check-input:disabled {
            opacity: 0.7;
        }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>




<script>
    window.generateQr = function(element) {
        const url = element.dataset.url;
        const coti = element.dataset.coti;
        const categoria = element.dataset.categoria;
        const instance = element.dataset.instance;
        const fechaMuestreo = element.dataset.fechamuestreo;  
        const existing = document.getElementById('dynamicQrModal');
        if (existing) existing.remove();

        // console.log(fechaMuestreo);

        const modal = document.createElement('div');
        modal.id = 'dynamicQrModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">CT ${coti} - Categoría: ${categoria} - Fecha: ${fechaMuestreo}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div style="display: flex; justify-content: center; align-items: center; min-height: 250px;">
                            <div id="qrContainer" style="margin: 0 auto;"></div>
                        </div>
                    </div>
                    <div style="width: 100%; max-width: 60%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                        <p></p>
                    </div>

                    <div style="width: 100%; max-width: 60%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                        <p></p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button onclick="printQr('${url}', '${coti}', '${categoria}', '${instance}', '${fechaMuestreo}')" class="btn btn-primary">
                            Imprimir CT 
                        </button>
                        <a href="${url}" class="btn btn-primary">
                            Ver Formulario
                        </a>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Limpiar contenedor si ya existe un QR
        const container = document.getElementById('qrContainer');
        container.innerHTML = '';
        
        new QRCode(container, {
            text: url,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    window.printQr = function(url, coti, categoria, instance, fechaMuestreo) {
        const win = window.open('', '_blank');
        win.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Imprimir CT - Cotización ${coti}</title>
                <style>
                    body {
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                    }
                    .print-container {
                        text-align: center;
                        padding: 20px;
                    }
                    .qr-wrapper {
                        margin: 20px auto;
                        padding: 10px;
                        border: 1px dashed #ccc;
                        display: inline-block;
                    }
                    h1 {
                        font-size: 24px;
                        margin-bottom: 20px;
                        color: #333;
                    }
                    .info {
                        margin-top: 20px;
                        font-size: 14px;
                        color: #666;
                    }
                    @media print {
                        body {
                            height: auto;
                        }
                        .no-print {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <h1>CT ${coti}</h1>
                    <p><strong>Muestreo:</strong> ${categoria}</p>
                    <p><strong>Muestra:</strong> ${instance}</p>
                    <p>
                        <strong>Fecha y hora:</strong>
                        <span style="display:inline-block; min-width: 140px; border-bottom: 1px solid #000; margin-left: 4px;">&nbsp;</span>
                    </p>
                    <div class="qr-wrapper">
                        <div id="qr"></div>
                        <div style="width: 100%; max-width: 90%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                            <p></p>
                        </div>
                        <div style="width: 100%; max-width: 90%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                            <p></p>
                        </div>
                    </div>
                    
                    <p class="info">Escanee este código CT para ver los detalles</p>
                    <p class="info no-print">Esta ventana se cerrará automáticamente después de imprimir</p>
                </div>
                
                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
                <script>
                    new QRCode(document.getElementById("qr"), {
                        text: "${url}",
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => window.close(), 100);
                    }, 500);
                <\/script>
            </body>
            </html>
        `);
        win.document.close();
    }
</script>


