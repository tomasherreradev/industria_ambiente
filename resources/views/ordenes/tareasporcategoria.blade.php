@extends('layouts.app')
{{-- @dd($instanciaActual) --}}
<head>
    <title>Cotización {{$cotizacion->coti_num}} | {{$categoria->cotio_descripcion}}</title>
</head>

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column gap-2 flex-md-row justify-content-between align-items-center mb-4">
        <a href="{{ url('/ordenes/'.$cotizacion->coti_num) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            Volver a la cotización
        </a>
        <div class="d-flex flex-column flex-md-row gap-2">
            {{-- <button type="button" class="btn btn-secondary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#asignarModal" disabled>
                Asignar elementos
            </button> --}}
            {{-- <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#asignarFrecuenciaModal">
                Ajustar Frecuencia
            </button> --}}
        </div>
    </div>

    @include('cotizaciones.info')

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="alert alert-warning mb-3">
                <strong>Módulo de OT - Asignación de analistas</strong>
                {{-- @if($instanciaActual->cotio_estado_analisis == 'coordinado analisis')
                    <button type="button" class="btn btn-danger" id="btnQuitarOT"
                            data-numcoti="{{ $instanciaActual->cotio_numcoti }}"
                            data-item="{{ $instanciaActual->cotio_item }}"
                            data-instance="{{ $instanciaActual->instance_number }}">
                        Quitar de OT
                    </button>
                @endif --}}
            </div>

            <form id="formQuitarDirectoAOT" method="POST" action="{{ route('muestras.quitar-directo-a-ot-from-coordinador', [
                'cotio_numcoti' => $instanciaActual->cotio_numcoti,
                'cotio_item' => $instanciaActual->cotio_item,
                'instance_number' => $instanciaActual->instance_number
            ]) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="isFromCoordinador" value="true">
            </form>
            
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="fw-bold mb-3">{{ $categoria->cotio_descripcion }} (#{{ $instanciaActual->instance_number ?? ''}} / {{ $categoria->cotio_cantidad ?? ''}})</h2>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary"
                        href="https://www.google.com/maps/search/?api=1&query={{ $cotizacion->coti_direccioncli }}, {{ $cotizacion->coti_localidad }}, {{ $cotizacion->coti_partido }}">
                        <x-heroicon-o-map class="me-1" style="width: 18px; height: 18px;" />
                        <span class="d-none d-md-inline">Ver en Maps</span>
                    </a>
                    @if($instanciaActual->latitud && $instanciaActual->longitud)
                        <a class="btn btn-outline-primary"
                            href="https://www.google.com/maps/search/?api=1&query={{ $instanciaActual->latitud }}, {{ $instanciaActual->longitud }}">
                            <x-heroicon-o-map-pin class="me-1" style="width: 18px; height: 18px;" />
                            <span class="d-none d-md-inline">Ver Georeferencia</span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <h4 class="text-muted mb-5">
                        Cotización: <strong>{{ $cotizacion->coti_num }}</strong>
                    </h4>
                        <p class="text-muted mb-1">
                            Estado: 
                            @php
                            // dd($instanciaActual);
                                $estado = strtolower($instanciaActual->cotio_estado_analisis);
                                $badgeClass = match ($estado) {
                                    'coordinado analisis' => 'warning',
                                    'en revision analisis' => 'info',
                                    'analizado' => 'success',
                                    'suspension' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeClass }}">{{ $instanciaActual->cotio_estado_analisis }}</span>
                                @if($instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                    <button type="button" class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#estadoModal" data-tipo="categoria">
                                        <x-heroicon-o-pencil style="width: 20px; height: 20px;" />
                                    </button>
                                @endif
                        </p>


                    {{-- Mostrar todos los responsables de las tareas --}}
                    @if(isset($todosResponsablesTareas) && $todosResponsablesTareas->count() > 0)
                        <p class="text-muted mb-1">
                            <strong>Asignada a:</strong> 
                            @foreach ($todosResponsablesTareas as $responsable)
                                <span class="badge bg-info d-inline-flex align-items-center me-2 mb-1">
                                    {{ $responsable->usu_descripcion }}
                                    @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                        <button type="button" 
                                                class="btn btn-sm btn-link text-danger p-0 ms-1" 
                                                style="font-size: 0.75rem; line-height: 1;"
                                                onclick="eliminarResponsableTodasTareas('{{ $responsable->usu_codigo }}')"
                                                title="Eliminar de todas las tareas">
                                            <x-heroicon-o-x-mark style="width: 12px; height: 12px;" />
                                        </button>
                                    @endif
                                </span>
                            @endforeach

                            {{-- @if($instanciaActual->cotio_estado_analisis == 'coordinado analisis')
                                <button type="button" class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#editarResponsables">
                                    <x-heroicon-o-pencil style="width: 20px; height: 20px;" />
                                </button>
                            @endif --}}
                        </p>
                    @endif

                    @if($instanciaActual->es_priori)
                        <p class="text-muted mb-1">
                            <strong>Es Prioridad:</strong> 
                            <span class="badge bg-primary">Sí</span>
                        </p>
                    @endif

                </div>
                <div class="col-md-6">
                    <p class="text-muted mb-1">
                        <strong>Frecuencia:</strong> 
                        @if ($instanciaActual->es_frecuente)
                            Frecuente
                        @else
                            Puntual
                        @endif
                    </p>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex align-items-center gap-3">
                        <p class="text-muted mb-0 fecha-wrapper" data-fecha-fin="{{ $instanciaActual->fecha_fin_ot ? $instanciaActual->fecha_fin_ot : '' }}">
                            <strong>Inicio:</strong> 
                            <span class="{{ $instanciaActual->fecha_inicio_ot ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                {{ $instanciaActual->fecha_inicio_ot ? $instanciaActual->fecha_inicio_ot : 'Faltante' }}
                            </span>
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Fin:</strong> 
                            <span class="fecha-fin {{ $instanciaActual->fecha_fin_ot ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                {{ $instanciaActual->fecha_fin_ot ? $instanciaActual->fecha_fin_ot : 'Faltante' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            @php
                // Parsear notas internas desde JSON
                $notasInternas = [];
                if (!empty($categoria->cotio_nota_contenido)) {
                    try {
                        $notasParsed = json_decode($categoria->cotio_nota_contenido, true);
                        if (is_array($notasParsed)) {
                            $notasInternas = collect($notasParsed)->filter(function($nota) {
                                return isset($nota['tipo']) && $nota['tipo'] === 'interna';
                            })->values()->toArray();
                        } else {
                            // Formato antiguo: nota simple
                            if (!empty($categoria->cotio_nota_tipo) && $categoria->cotio_nota_tipo === 'interna') {
                                $notasInternas = [['tipo' => 'interna', 'contenido' => $categoria->cotio_nota_contenido]];
                            }
                        }
                    } catch (\Exception $e) {
                        // No es JSON, es formato antiguo
                        if (!empty($categoria->cotio_nota_tipo) && $categoria->cotio_nota_tipo === 'interna') {
                            $notasInternas = [['tipo' => 'interna', 'contenido' => $categoria->cotio_nota_contenido]];
                        }
                    }
                }
            @endphp
            
            @if(!empty($notasInternas))
                <div class="alert alert-warning mb-3">
                    <div class="d-flex align-items-start">
                        <div class="me-2">
                            <x-heroicon-o-information-circle style="width: 20px; height: 20px;" class="text-warning" />
                        </div>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">Notas Internas:</strong>
                            @foreach($notasInternas as $nota)
                                <p class="mb-2">{{ $nota['contenido'] ?? '' }}</p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if($instanciaActual->cotio_identificacion)
                <div class="alert alert-info">
                    <strong>Identificador de muestra:</strong> {{ $instanciaActual->cotio_identificacion }}
                </div>
            @endif

            @if($instanciaActual->image)
            <!-- Botón para mostrar la imagen -->
            <div class="mt-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#imageModal">
                    <x-heroicon-o-photo style="width: 20px; height: 20px;" /> Imagen de la muestra
                </button>
            </div>
        
            <!-- Modal -->
            <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imageModalLabel">Imagen de la muestra</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="{{ Storage::url('images/' . $instanciaActual->image) }}" 
                                 alt="Imagen de la muestra" 
                                 class="img-fluid rounded">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif


            @if($instanciaActual->cotio_estado_analisis == 'finalizado' || $instanciaActual->cotio_estado_analisis == 'analizado' && $instanciaActual->enable_inform == false)
                <form action="{{ route('ordenes.enable-informe', [
                    'cotio_numcoti' => $instanciaActual->cotio_numcoti,
                    'cotio_item' => $instanciaActual->cotio_item,
                    'cotio_subitem' => $instanciaActual->cotio_subitem,
                    'instance' => $instance
                ]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="cotio_numcoti" value="{{ $instanciaActual->cotio_numcoti }}">
                    <input type="hidden" name="cotio_item" value="{{ $instanciaActual->cotio_item }}">
                    <input type="hidden" name="cotio_subitem" value="{{ $instanciaActual->cotio_subitem }}">
                    <input type="hidden" name="instance" value="{{ $instance }}">
                    <button class="btn btn-success mt-2">Pasar a Informe</button>
                </form>
            @endif

            @if($instanciaActual->enable_inform == true)
                <div class="mt-3 d-flex flex-wrap gap-2">
                    <!-- Botón para ver informe preliminar -->
                    <button type="button" 
                            class="btn btn-info" 
                            onclick="verInformePreliminar({{ $instanciaActual->id }})"
                            title="Ver informe preliminar">
                        <x-heroicon-o-document-text class="me-1" style="width: 18px; height: 18px;" />
                        Ver Informe Preliminar
                    </button>

                    @if(!$instanciaActual->aprobado_informe)
                        <!-- Botón para aprobar informe -->
                        <button type="button" 
                                class="btn btn-success" 
                                onclick="aprobarInforme({{ $instanciaActual->id }})"
                                title="Aprobar informe">
                            <x-heroicon-o-check-circle class="me-1" style="width: 18px; height: 18px;" />
                            Aprobar Informe
                        </button>
                    @else
                        <!-- Indicador de informe aprobado -->
                        <span class="badge bg-success fs-6 d-flex align-items-center">
                            <x-heroicon-o-check-circle class="me-1" style="width: 18px; height: 18px;" />
                            Informe Aprobado
                        </span>
                    @endif

                    <!-- Botón para deshabilitar informe -->
                    <form action="{{ route('ordenes.disable-informe', [
                        'cotio_numcoti' => $instanciaActual->cotio_numcoti,
                        'cotio_item' => $instanciaActual->cotio_item,
                        'cotio_subitem' => $instanciaActual->cotio_subitem,
                        'instance' => $instance
                    ]) }}" method="POST" class="d-inline" style="margin: 0;">
                    @csrf
                        <input type="hidden" name="cotio_numcoti" value="{{ $instanciaActual->cotio_numcoti }}">
                        <input type="hidden" name="cotio_item" value="{{ $instanciaActual->cotio_item }}">
                        <input type="hidden" name="cotio_subitem" value="{{ $instanciaActual->cotio_subitem }}">
                        <input type="hidden" name="instance" value="{{ $instance }}">
                        <button class="btn btn-danger" type="submit">
                            <x-heroicon-o-x-circle class="me-1" style="width: 18px; height: 18px;" />
                            Deshabilitar Informe
                        </button>
                    </form>
                </div>
            @endif

            @if($instanciaActual && $instanciaActual->herramientasLab && $instanciaActual->herramientasLab->count())
                <div class="card shadow-sm border-0 mt-5">
                    <div class="card-header bg-light d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <x-heroicon-o-wrench-screwdriver class="me-2" style="width: 1rem; height: 1rem;" />
                            <h6 class="card-title mb-0">Herramientas de Análisis</h6>
                        </div>
                        @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                            <button type="button" class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#herramientasModal" data-instancia-id="{{ $instanciaActual->id }}" data-descripcion="{{ $instanciaActual->cotio_descripcion }}">
                                <x-heroicon-o-pencil style="width: 20px; height: 20px;" />
                            </button>
                        @endif
                    </div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                            @foreach ($instanciaActual->herramientasLab as $herramienta)
                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 border-0">
                                    <div class="d-flex align-items-center">
                                        <x-heroicon-o-beaker class="text-muted me-2" style="width: 0.875rem; height: 0.875rem;" />
                                        <span>
                                            {{ $herramienta->equipamiento }}
                                            @if($herramienta->marca_modelo)
                                                <small class="text-muted">({{ $herramienta->marca_modelo }})</small>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        @if(isset($herramienta->pivot_observaciones) && $herramienta->pivot_observaciones)
                                            <span class="badge bg-light text-dark me-2" title="{{ $herramienta->pivot_observaciones }}">
                                                <x-heroicon-o-information-circle style="width: 0.875rem; height: 0.875rem;" />
                                            </span>
                                        @endif
                                        @if(isset($herramienta->cantidad) && $herramienta->cantidad > 1)
                                            <span class="badge bg-primary rounded-pill">{{ $herramienta->cantidad }}</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif


            @if($instanciaActual && $variablesMuestra->isNotEmpty())
            <div class="card shadow-sm my-5">
                <div class="card-header">
                    <h5 style="cursor: pointer; color: black; padding: 10px;" data-bs-toggle="collapse" data-bs-target="#variablesCollapse" aria-expanded="false" aria-controls="variablesCollapse">
                        Variables de Medición y Observaciones
                    </h5>
                </div>
            
                <div id="variablesCollapse" class="collapse show">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Variable</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($variablesMuestra as $variable)
                                        <tr>
                                            <td>{{ $variable->variable }}</td>
                                            <td>
                                                <input type="text" class="form-control variable-value" 
                                                       value="{{ $variable->valor }}" 
                                                       data-id="{{ $variable->id }}"
                                                       readonly>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        @if($instanciaActual->observaciones_medicion_coord_muestreo)
                            <div class="mt-4">
                                <label for="observaciones" class="form-label"><strong>Observaciones del Coordinador de Muestreo:</strong></label>
                                <textarea class="form-control" id="observaciones" rows="3" 
                                        readonly>{{ trim($instanciaActual->observaciones_medicion_coord_muestreo) }}</textarea>
                            </div>
                        @endif

                        @if($instanciaActual->observaciones_medicion_muestreador)
                        <div class="mt-4">
                                <label for="observaciones_muestreador" class="form-label"><strong>Observaciones del Muestreador:</strong></label>
                                <textarea class="form-control" id="observaciones_muestreador" rows="3" readonly
                                        style="background-color: #fff8e1; border-left: 4px solid #ffc107; padding-left: 12px;">{{ trim($instanciaActual->observaciones_medicion_muestreador) }}</textarea>
                            </div>
                        @endif
                    
                        @if($instanciaActual->cotio_estado != 'muestreado')
                            <div class="mt-3 text-center">
                                <button class="btn btn-primary btn-lg save-all-data" 
                                        data-instancia-id="{{ $instanciaActual->id }}">
                                    <i class="fas fa-save"></i> Guardar Variables y Observaciones
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
         @endif


        </div>
    </div>

    @if($tareas->count())
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Análisis de la muestra</h5>
                <div class="d-flex">
                    @php
                        $todosAnalizados = ($instanciaActual && $instanciaActual->cotio_estado_analisis === 'analizado')
                            && $tareas->every(function($t) {
                                return $t->instancia && $t->instancia->cotio_estado_analisis === 'analizado';
                            });
                    @endphp


                    @if($instanciaActual->active_ot)
                    <div class="d-flex justify-content-between align-items-center my-1 pb-2 gap-2">
                        <button 
                            type="button" 
                            class="btn btn-sm btn-outline-primary"
                            onclick="seleccionarTodosAnalisis({{ $instanciaActual->id }}, {{ $categoria->cotio_item }}, {{ $instanciaActual->instance_number }}, {{ $instanciaActual->cotio_numcoti }})"
                        >
                            <i class="fas fa-check-square me-1"></i> Seleccionar todos
                        </button>
                        
                        <!-- Menú de 3 puntitos (solo visible cuando hay análisis seleccionados) -->
                        <div class="dropdown" id="menu-acciones-{{ $instanciaActual->id }}" style="display: none;">
                            <button 
                                class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                type="button" 
                                id="dropdownMenuButton{{ $instanciaActual->id }}" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                            >
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton{{ $instanciaActual->id }}">
                                <li>
                                    <a 
                                        class="dropdown-item" 
                                        href="#" 
                                        onclick="finalizarAnalisisSeleccionados({{ $instanciaActual->id }}, {{ $categoria->cotio_item }}, {{ $instanciaActual->instance_number }}, {{ $instanciaActual->cotio_numcoti }}); return false;"
                                    >
                                        <i class="fas fa-check me-2"></i> Finalizar
                                    </a>
                                </li>
                                <li>
                                    <a 
                                        class="dropdown-item" 
                                        href="#" 
                                        onclick="gestionarResponsablesAnalisisSeleccionados({{ $instanciaActual->id }}, {{ $categoria->cotio_item }}, {{ $instanciaActual->instance_number }}, {{ $instanciaActual->cotio_numcoti }}); return false;"
                                    >
                                        <i class="fas fa-users me-2"></i> Gestionar responsables
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endif

                </div>
            </div>
            <div class="card-body">

                <div class="row row-cols-1 row-cols-md-2 g-3">
                    @foreach ($tareas as $tarea)
                        <div class="col">
                            <div class="card h-100 shadow-sm border-0">
                                <div class="card-body p-0">
                                    <!-- Card Header with Checkbox and Title -->
                                    <div class="d-flex justify-content-between align-items-center p-3" style="background-color: #A6C5E3; border-radius: 0.375rem 0.375rem 0 0;">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input tarea-checkbox tarea-checkbox-analisis" 
                                                type="checkbox" 
                                                name="tareas_seleccionadas[]" 
                                                value="{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                id="tarea_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                data-muestra-id="{{ $instanciaActual->id }}"
                                                data-instancia-id="{{ $tarea->instancia->id ?? null }}"
                                                data-item="{{ $tarea->cotio_item }}"
                                                data-subitem="{{ $tarea->cotio_subitem }}"
                                                data-instance="{{ $instanciaActual->instance_number }}"
                                                data-numcoti="{{ $tarea->cotio_numcoti }}"
                                                data-fecha-inicio="{{ $tarea->instancia && $tarea->instancia->fecha_inicio_ot ? $tarea->instancia->fecha_inicio_ot->format('Y-m-d\TH:i') : '' }}"
                                                data-fecha-fin="{{ $tarea->instancia && $tarea->instancia->fecha_fin_ot ? $tarea->instancia->fecha_fin_ot->format('Y-m-d\TH:i') : '' }}"
                                                @disabled($instanciaActual->cotio_estado_analisis === 'analizado')>
                                            <label class="form-check-label" for="tarea_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}">
                                                <h5 class="card-title mb-0 d-flex align-items-center">
                                                    <x-heroicon-o-clipboard-document-list class="me-2" style="width: 1.25rem; height: 1.25rem;" />
                                                    {{ $tarea->cotio_descripcion }}
                                                    @if($tarea->instancia->request_review)
                                                        <div 
                                                            class="bg-warning rounded-pill ms-2 d-flex align-items-center justify-content-center" 
                                                            data-bs-toggle="tooltip" 
                                                            onclick="requestReviewCancel({{ $tarea->instancia->id }})"
                                                            data-bs-placement="bottom"
                                                            title="Revisión solicitada"
                                                            style="width: 1.8rem; height: 1.8rem;">
                                                            <x-heroicon-o-exclamation-triangle style="width: 1rem; height: 1rem; color: black;" />
                                                        </div>
                                                    @endif
                                                </h5>
                                            </label>
                                        </div>
                                        @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                        <div class="d-flex justify-content-end gap-2">
                                            @if(!$tarea->instancia->request_review)
                                                <button type="button" class="btn btn-sm btn-outline-dark"
                                                        onclick="requestReview({{ $tarea->instancia->id }})"
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="bottom"
                                                        title="Solicitar revisión de resultados"
                                                        data-tipo="tarea"
                                                        data-item="{{ $tarea->cotio_item }}"
                                                        data-subitem="{{ $tarea->cotio_subitem }}"
                                                        >
                                                    <x-heroicon-o-arrow-path-rounded-square style="width: 1rem; height: 1rem;" />
                                                </button>
                                            @endif

                                            <button type="button" class="btn btn-sm btn-outline-dark"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#estadoModal"
                                                    data-tipo="tarea"
                                                    data-item="{{ $tarea->cotio_item }}"
                                                    data-subitem="{{ $tarea->cotio_subitem }}"
                                                    data-estado="{{ $tarea->instancia->cotio_estado_analisis ?? '' }}"
                                                    data-fecha-carga="{{ $tarea->instancia && $tarea->instancia->fecha_carga_ot ? $tarea->instancia->fecha_carga_ot->format('Y-m-d\TH:i') : '' }}"
                                                    data-observaciones-ot="{{ htmlspecialchars($tarea->instancia->observaciones_ot ?? '', ENT_QUOTES) }}">
                                                <x-heroicon-o-pencil-square style="width: 1rem; height: 1rem;" />
                                            </button>
                                        </div>  
                                        @endif
                                    </div>
                    
                                    <!-- Card Content -->
                                    <div class="p-3">
                                        <!-- Observación Section -->
                                        @if($tarea->instancia && $tarea->instancia->observaciones_ot)
                                            <div class="d-flex align-items-start mb-2">
                                                <x-heroicon-o-chat-bubble-bottom-center-text class="text-info me-2 mt-1" style="width: 1rem; height: 1rem;" />
                                                <div>
                                                    <span class="me-2"><strong>Observación del coordinador:</strong></span>
                                                    <span class="badge bg-info text-dark rounded-pill">{{ $tarea->instancia->observaciones_ot }}</span>
                                                </div>
                                            </div>
                                        @endif
                    
                                        <!-- Estado Section -->
                                        <div class="d-flex flex-row flex-column-sm align-items-center justify-content-between mb-3">
                                            <div>
                                                <x-heroicon-o-flag class="me-2" style="width: 1rem; height: 1rem;" />
                                                <span class="me-2"><strong>Estado:</strong></span>
                                                @php
                                                    $estado = strtolower($tarea->instancia->cotio_estado_analisis);
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
                                                <span class="badge bg-{{ $badgeClass }} rounded-pill me-2">{{ $tarea->instancia->cotio_estado_analisis }}</span>
                                            </div>
                    
                                            <div id="fecha_carga_ot_{{ $tarea->instancia->id }}">
                                                <x-heroicon-o-calendar class="me-2" style="width: 1rem; height: 1rem;" />
                                                <span class="me-2"><strong>Fecha de carga:</strong></span>
                                                <span class="badge bg-secondary rounded-pill me-2">{{ $tarea->instancia->fecha_carga_ot ?? 'Faltante' }}</span>
                                            </div>
                                        </div>
                    
                                        <!-- Asignado a Section -->
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <x-heroicon-o-user-circle class="me-2" style="width: 1rem; height: 1rem;" />
                                                <span class="me-2"><strong>Asignada a:</strong></span>
                                                @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-success"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#gestionarResponsablesModal"
                                                            data-cotio-numcoti="{{ $tarea->cotio_numcoti }}"
                                                            data-cotio-item="{{ $tarea->cotio_item }}"
                                                            data-cotio-subitem="{{ $tarea->cotio_subitem }}"
                                                            data-instance-number="{{ $tarea->instancia->instance_number }}"
                                                            data-instancia-id="{{ $tarea->instancia->id }}"
                                                            title="Gestionar responsables">
                                                        <x-heroicon-o-user-plus style="width: 0.875rem; height: 0.875rem;" />
                                                    </button>
                                                @endif
                                            </div>
                                            <div class="d-flex flex-wrap">
                                                @if ($tarea->instancia->responsablesAnalisis->count() > 0)
                                                    @foreach ($tarea->instancia->responsablesAnalisis as $responsable)
                                                        <div class="badge bg-primary rounded-pill d-flex align-items-center me-2 mb-1">
                                                            {{ $responsable->usu_descripcion }}
                                                            @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                                                <button type="button" 
                                                                        class="btn-close btn-close-white ms-2"
                                                                        style="font-size: 0.6em;"
                                                                        onclick="quitarResponsable('{{ $responsable->usu_codigo }}', '{{ $tarea->cotio_numcoti }}', '{{ $tarea->cotio_item }}', '{{ $tarea->cotio_subitem }}', '{{ $tarea->instancia->instance_number }}', '{{ $responsable->usu_descripcion }}')"
                                                                        title="Quitar responsable"
                                                                        aria-label="Quitar {{ $responsable->usu_descripcion }}"></button>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <span class="badge bg-secondary rounded-pill">Sin asignar</span>
                                                @endif
                                            </div>
                                        </div>
                    
                                        <!-- Fechas Section -->
                                        <div>
                                            <div class="d-flex flex-column flex-md-row justify-content-between mb-3 fecha-wrapper" data-fecha-fin="{{ $tarea->instancia && $tarea->instancia->fecha_fin_ot ? $tarea->instancia->fecha_fin_ot->format('Y-m-d\TH:i') : '' }}">
                                                <div class="d-flex align-items-center mb-2 mb-md-0">
                                                    <x-heroicon-o-calendar class="me-2" style="width: 1rem; height: 1rem;" />
                                                    <span class="me-2"><strong>Inicio:</strong></span>
                                                    <span class="{{ $tarea->instancia && $tarea->instancia->fecha_inicio_ot ? 'bg-light text-dark px-2 py-1 rounded' : 'text-muted' }}">
                                                        {{ $tarea->instancia && $tarea->instancia->fecha_inicio_ot ? $tarea->instancia->fecha_inicio_ot->format('d/m/Y H:i') : 'Faltante' }}
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <x-heroicon-o-clock class="me-2" style="width: 1rem; height: 1rem;" />
                                                    <span class="me-2"><strong>Fin:</strong></span>
                                                    <span class="fecha-fin {{ $tarea->instancia && $tarea->instancia->fecha_fin_ot ? 'bg-light text-dark px-2 py-1 rounded' : 'text-muted' }}">
                                                        {{ $tarea->instancia && $tarea->instancia->fecha_fin_ot ? $tarea->instancia->fecha_fin_ot->format('d/m/Y H:i') : 'Faltante' }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <footer>
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <div>
                                                    </div>
                        
                                                    <p style="text-align: center; color: #0d6efd; background-color: #f8f9fa; padding: 5px; border-radius: 5px;">
                                                        Resultado Final: <span style="font-weight: bold; color: #0d6efd;">{{ $tarea->instancia && $tarea->instancia->resultado_final ? $tarea->instancia->resultado_final : 'Faltante' }}</span>
                                                    </p>
                                                    
                                                    <div>
                                                        @if($tarea->instancia && $tarea->instancia->image_resultado_final)
                                                            <div class="text-center">
                                                                <a href="{{ Storage::url($tarea->instancia->image_resultado_final) }}" 
                                                                target="_blank" 
                                                                title="Click para ver en tamaño completo">
                                                                    <img src="{{ Storage::url($tarea->instancia->image_resultado_final) }}" 
                                                                        alt="Imagen del análisis" 
                                                                        class="img-thumbnail border border-primary"
                                                                        style="max-width: 80px; max-height: 60px; object-fit: cover; cursor: pointer;">
                                                                </a>
                                                                <br>
                                                                <small class="text-muted">Click para ampliar</small>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </footer>
                                        </div>
                    
                                        <!-- Resultados Section -->
                                        @php
                                            $accordionId = "resultadosAccordion_{$tarea->cotio_item}_{$tarea->cotio_subitem}";
                                            $headingId = "headingResultados_{$tarea->cotio_item}_{$tarea->cotio_subitem}";
                                            $collapseId = "collapseResultados_{$tarea->cotio_item}_{$tarea->cotio_subitem}";
                                        @endphp
                                        
                                        <div class="accordion mt-4" id="{{ $accordionId }}">
                                            <div class="accordion-item">
                                                <h2 class="accordion-header" id="{{ $headingId }}">
                                                    <button class="accordion-button collapsed"
                                                            type="button"
                                                            data-bs-toggle="collapse"
                                                            data-bs-target="#{{ $collapseId }}"
                                                            aria-expanded="false"
                                                            aria-controls="{{ $collapseId }}">
                                                        <x-heroicon-o-document-chart-bar class="text-primary me-2" style="width: 1.25rem; height: 1.25rem;" />
                                                        <strong>Resultados de Análisis</strong>
                                                    </button>
                                                </h2>
                                                <div id="{{ $collapseId }}"
                                                    class="accordion-collapse collapse"
                                                    aria-labelledby="{{ $headingId }}"
                                                    data-bs-parent="#{{ $accordionId }}">
                                                    <div class="accordion-body">
                                                        <form class="resultados-form" 
                                                            action="{{ route('tareas.updateResultado', ['cotio_numcoti' => $tarea->cotio_numcoti, 'cotio_item' => $tarea->cotio_item, 'cotio_subitem' => $tarea->cotio_subitem, 'instance' => $tarea->instancia->instance_number]) }}" 
                                                            method="POST"
                                                            data-cotio-numcoti="{{ $cotizacion->coti_num }}"
                                                            data-cotio-item="{{ $tarea->cotio_item }}"
                                                            data-cotio-subitem="{{ $tarea->cotio_subitem }}"
                                                            data-instance="{{ $tarea->instancia->instance_number }}">
                                                            @csrf
                                                            @method('PUT')
                                                            
                                                            @php
                                                                $resultados = [
                                                                    [
                                                                        'titulo' => 'Resultado Primario',
                                                                        'valor' => $tarea->instancia->resultado ?? '',
                                                                        'obs' => $tarea->instancia->observacion_resultado ?? '',
                                                                        'badge' => 'primary',
                                                                        'label' => 'R1',
                                                                        'field' => 'resultado',
                                                                        'fecha_carga' => $tarea->instancia->fecha_carga_resultado_1 ?? '',
                                                                        'obs_field' => 'observacion_resultado',
                                                                        'cotio_codigoum' => $tarea->instancia->cotio_codigoum ?? 'N/A'
                                                                    ],
                                                                    [
                                                                        'titulo' => 'Resultado Secundario',
                                                                        'valor' => $tarea->instancia->resultado_2 ?? '',
                                                                        'obs' => $tarea->instancia->observacion_resultado_2 ?? '',
                                                                        'badge' => 'info',
                                                                        'label' => 'R2',
                                                                        'field' => 'resultado_2',
                                                                        'fecha_carga' => $tarea->instancia->fecha_carga_resultado_2 ?? '',
                                                                        'obs_field' => 'observacion_resultado_2',
                                                                        'cotio_codigoum' => $tarea->instancia->cotio_codigoum ?? 'N/A'
                                                                    ],
                                                                    [
                                                                        'titulo' => 'Resultado Terciario',
                                                                        'valor' => $tarea->instancia->resultado_3 ?? '',
                                                                        'obs' => $tarea->instancia->observacion_resultado_3 ?? '',
                                                                        'badge' => 'warning',
                                                                        'label' => 'R3',
                                                                        'field' => 'resultado_3',
                                                                        'fecha_carga' => $tarea->instancia->fecha_carga_resultado_3 ?? '',
                                                                        'obs_field' => 'observacion_resultado_3',
                                                                        'cotio_codigoum' => $tarea->instancia->cotio_codigoum ?? 'N/A'
                                                                    ],
                                                                    [
                                                                        'titulo' => 'Resultado Final',
                                                                        'valor' => $tarea->instancia->resultado_final ?? '',
                                                                        'obs' => $tarea->instancia->observacion_resultado_final ?? '',
                                                                        'badge' => 'dark',
                                                                        'label' => 'Final',
                                                                        'field' => 'resultado_final',
                                                                        'fecha_carga' => $tarea->instancia->fecha_carga_ot ?? '',
                                                                        'obs_field' => 'observacion_resultado_final',
                                                                        'cotio_codigoum' => $tarea->instancia->cotio_codigoum ?? 'N/A'
                                                                    ]
                                                                ];
                                                            @endphp
                                                            

                                                            <div class="row g-3">
                                                                @foreach ($resultados as $r)
                                                                    <div class="col-12">
                                                                        <div class="card border-0 shadow-sm">
                                                                            <div class="card-header bg-{{ $r['badge'] }} bg-opacity-10 border-0 py-2">
                                                                                <div class="d-flex justify-content-between align-items-center">
                                                                                                                                                                         <div class="d-flex align-items-center">
                                                                                         <span class="badge bg-{{ $r['badge'] }} rounded-pill me-2">{{ $r['label'] }}</span>
                                                                                         <h6 class="mb-0 text-{{ $r['badge'] }} fw-semibold">{{ $r['titulo'] }}</h6>
                                                                                         @if($r['fecha_carga'])
                                                                                             <span class="badge bg-secondary rounded-pill ms-2" title="Fecha de carga">
                                                                                                 <small>
                                                                                                 Fecha de carga: {{ $r['fecha_carga'] }}
                                                                                                 </small>
                                                                                             </span>
                                                                                         @endif
                                                                                         @if($r['obs'])
                                                                                             <span class="badge bg-success rounded-pill ms-2" title="Tiene observaciones">
                                                                                                 <x-heroicon-o-chat-bubble-left-ellipsis style="width: 12px; height: 12px;" />
                                                                                             </span>
                                                                                         @endif
                                                                                     </div>
                                                                                    @if(isset($historialCambios[$tarea->instancia->id]) && $historialCambios[$tarea->instancia->id]->where('campo_modificado', $r['field'])->isNotEmpty())
                                                                                        <button class="btn btn-sm btn-outline-{{ $r['badge'] }} btn-historial-resultado" 
                                                                                                data-instancia-id="{{ $tarea->instancia->id }}"
                                                                                                data-campo="{{ $r['field'] }}"
                                                                                                data-bs-toggle="modal" 
                                                                                                data-bs-target="#historialResultadoModal"
                                                                                                title="Ver historial de cambios">
                                                                                            <x-heroicon-o-clock style="width: 16px; height: 16px;" />
                                                                                        </button>
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                            <div class="card-body p-3">
                                                                                <div class="row g-2">
                                                                                    <div class="col-md-6">
                                                                                        <label class="form-label text-muted small mb-1">
                                                                                            <x-heroicon-o-beaker class="me-1" style="width: 14px; height: 14px;" />
                                                                                            Resultado
                                                                                        </label>
                                                                                        <input 
                                                                                            class="form-control resultado-input" 
                                                                                            type="text"
                                                                                            id="{{ $r['field'] }}_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                                                            name="{{ $r['field'] }}"
                                                                                            value="{{ $r['valor'] }}"
                                                                                            placeholder="Ingrese el resultado..."
                                                                                            @if($instanciaActual->cotio_estado_analisis == 'analizado')
                                                                                                readonly
                                                                                            @endif
                                                                                        >
                                                                                    </div>
                                                                                    <div class="col-md-6">
                                                                                        <label class="form-label text-muted small mb-1">
                                                                                            <x-heroicon-o-chat-bubble-left-ellipsis class="me-1" style="width: 14px; height: 14px;" />
                                                                                            Observaciones
                                                                                        </label>
                                                                                        <textarea 
                                                                                            class="form-control observacion-input" 
                                                                                            name="{{ $r['obs_field'] }}"
                                                                                            id="{{ $r['obs_field'] }}_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                                                            rows="2"
                                                                                            placeholder="Observaciones del resultado..."
                                                                                            @if($instanciaActual->cotio_estado_analisis == 'analizado')
                                                                                                readonly
                                                                                            @endif
                                                                                        >{{ $r['obs'] }}</textarea>
                                                                                    </div>
                                                                                    @if($r['titulo'] == 'Resultado Final')
                                                                                        <div>
                                                                                            <input type="text" class="form-control" name="u_med_resultado" value="{{ $r['cotio_codigoum'] }}"
                                                                                            readonly>
                                                                                        </div>
                                                                                    @endif
                                                                                 </div>
                                                                             </div>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                    
                                                            <div class="mt-3 text-end">
                                                                @if($instanciaActual->cotio_estado_analisis != 'analizado' && $instanciaActual->active_ot == true && $instanciaActual->enable_inform == false)
                                                                    <button type="submit" 
                                                                            class="btn btn-success guardar-todos-resultados"
                                                                            data-form-id="{{ $accordionId }}">
                                                                        <x-heroicon-o-check-circle style="width: 1rem; height: 1rem;" />
                                                                        Guardar
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <x-heroicon-o-information-circle style="width: 20px; height: 20px;" /> No hay tareas asignadas a esta muestra.
        </div>
    @endif


<div class="modal fade" id="historialResultadoModal" tabindex="-1" aria-labelledby="historialResultadoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historialResultadoModalLabel">Historial de Cambios de Resultado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historialResultadoContent">
                    <p>Seleccione un resultado para ver su historial.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>



<div class="modal fade" id="estadoModal" tabindex="-1" aria-labelledby="estadoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="estadoModalLabel">Editar Análisis</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="estadoForm">
                    @csrf
                    <input type="hidden" name="cotio_numcoti" id="modal_cotio_numcoti" value="{{ $cotizacion->coti_num }}">
                    <input type="hidden" name="cotio_item" id="modal_cotio_item" value="{{ $categoria->cotio_item }}">
                    <input type="hidden" name="cotio_subitem" id="modal_cotio_subitem" value="0">
                    <input type="hidden" name="instance_number" id="modal_instance_number" value="{{ $instance }}">
                    
                    <div class="mb-3">
                        <label for="modal_estado" class="form-label">
                            <x-heroicon-o-flag class="me-1" style="width: 16px; height: 16px;" />
                            Estado
                        </label>
                        <select class="form-select" id="modal_estado" name="estado" required>
                            <option value="coordinado analisis" {{ ($instanciaActual->cotio_estado_analisis ?? 'coordinado analisis') == 'coordinado analisis' ? 'selected' : '' }}>coordinado analisis</option>
                            <option value="en revision analisis" {{ ($instanciaActual->cotio_estado_analisis ?? 'en revision analisis') == 'en revision analisis' ? 'selected' : '' }}>En revision analisis</option>
                            <option value="analizado" {{ ($instanciaActual->cotio_estado_analisis ?? 'analizado') == 'analizado' ? 'selected' : '' }}>analizado</option>
                            <option value="suspension" {{ ($instanciaActual->cotio_estado_analisis ?? 'suspension') == 'suspension' ? 'selected' : '' }}>Suspension</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_fecha_carga" class="form-label">
                            <x-heroicon-o-calendar class="me-1" style="width: 16px; height: 16px;" />
                            Fecha de Carga
                        </label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="modal_fecha_carga" 
                               name="fecha_carga_ot"
                               value="{{ $instanciaActual->fecha_carga_ot ? date('Y-m-d\TH:i', strtotime($instanciaActual->fecha_carga_ot)) : '' }}">
                        <small class="text-muted">Si no se especifica, se usará la fecha y hora actual</small>
                    </div>

                    <div class="mb-3">
                        <label for="modal_observaciones" class="form-label">
                            <x-heroicon-o-chat-bubble-left-ellipsis class="me-1" style="width: 16px; height: 16px;" />
                            Observaciones del Coordinador
                        </label>
                        <textarea class="form-control" 
                                  id="modal_observaciones" 
                                  name="observaciones_ot" 
                                  rows="3" 
                                  placeholder="Ingrese observaciones del coordinador...">{{ $instanciaActual->observaciones_ot ?? '' }}</textarea>
                    </div>
         
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmarEstado">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="fechaModal" tabindex="-1" aria-labelledby="fechaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fechaModalLabel">Ajustar fechas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="fechaForm">

                    <input type="hidden" name="cotio_numcoti" id="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                    <input type="hidden" name="cotio_item" id="cotio_item" value="{{ $categoria->cotio_item }}">
                    <input type="hidden" name="cotio_subitem" id="cotio_subitem" value="{{ $categoria->cotio_subitem }}">

                    <div class="mb-3">
                        <label for="fecha_inicio_gral" class="form-label">Fecha de inicio</label>
                        <input type="datetime-local" class="form-control" id="fecha_inicio_gral" name="fecha_inicio_gral" required>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_fin_gral" class="form-label">Fecha de finalización</label>
                        <input type="datetime-local" class="form-control" id="fecha_fin_gral" name="fecha_fin_gral" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarFechas()">Ajustar</button>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="asignarFrecuenciaModal" tabindex="-1" aria-labelledby="asignarFrecuenciaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="asignarFrecuenciaModalLabel">Ajustar Frecuencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="asignarFrecuenciaForm">
                    <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                    <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                    <input type="hidden" name="cotio_subitem" value="0">
                    

                    <div class="mb-3">
                        <label for="es_frecuente" class="form-label">¿Es frecuente?</label>
                        <select class="form-select" id="es_frecuente" name="es_frecuente">
                            <option value="1" {{ $categoria->es_frecuente ? 'selected' : '' }}>Sí</option>
                            <option value="0" {{ !$categoria->es_frecuente ? 'selected' : '' }}>No</option>
                        </select>
                    </div>


                    <div class="mb-3">
                        <label for="frecuencia_dias" class="form-label">Frecuencia en días</label>
                        <select class="form-select" id="frecuencia_dias" name="frecuencia_dias" required>
                            <option value="">Seleccione una opción</option>
                            <option value="diario" {{ $categoria->frecuencia_dias === 'diario' ? 'selected' : '' }}>Diario</option>
                            <option value="semanal" {{ $categoria->frecuencia_dias === 'semanal' ? 'selected' : '' }}>Semanal</option>
                            <option value="quincenal" {{ $categoria->frecuencia_dias === 'quincenal' ? 'selected' : '' }}>Quincenal</option>
                            <option value="mensual" {{ $categoria->frecuencia_dias === 'mensual' ? 'selected' : '' }}>Mensual</option>
                            <option value="trimestral" {{ $categoria->frecuencia_dias === 'trimestral' ? 'selected' : '' }}>Trimestral</option>
                            <option value="cuatr" {{ $categoria->frecuencia_dias === 'cuatr' ? 'selected' : '' }}>Cuatrimestral</option>
                            <option value="semestral" {{ $categoria->frecuencia_dias === 'semestral' ? 'selected' : '' }}>Semestral</option>
                            <option value="anual" {{ $categoria->frecuencia_dias === 'anual' ? 'selected' : '' }}>Anual</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="enviarFrecuencia()">Ajustar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="asignarModal" tabindex="-1" aria-labelledby="asignarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="asignarForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="asignarModalLabel">Asignar Responsable</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">


            <div class="mb-3">
                <label for="responsable_codigo" class="form-label">Responsable</label>
                <select class="form-select" id="responsable_codigo" name="responsable_codigo">
                    <option value="">-- Sin cambios --</option>
                    <option value="NULL">-- Quitar responsable --</option>
                    @foreach($usuarios as $usuario)
                        <option value="{{ $usuario->usu_codigo }}">
                            {{ $usuario->usu_descripcion }} ({{ $usuario->usu_codigo }})
                        </option>
                    @endforeach
                </select>
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
                <small class="text-muted">Seleccione múltiples herramientas con Ctrl+Click</small>
            </div>


            <div class="mb-3">
                <label for="fecha_inicio_ot" class="form-label">Fecha y Hora de Inicio</label>
                <input 
                    type="datetime-local" 
                    class="form-control" 
                    id="fecha_inicio_ot" 
                    name="fecha_inicio_ot"
                    value="{{ $categoria->fecha_inicio_ot ? date('Y-m-d\TH:i', strtotime($categoria->fecha_inicio_ot)) : '' }}"
                >
            </div>
            
            <div class="mb-3">
                <label for="fecha_fin_ot" class="form-label">Fecha y Hora de Fin</label>
                <input 
                    type="datetime-local" 
                    class="form-control" 
                    id="fecha_fin_ot" 
                    name="fecha_fin_ot"
                    value="{{ $categoria->fecha_fin_ot ? date('Y-m-d\TH:i', strtotime($categoria->fecha_fin_ot)) : '' }}"
                >
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Asignar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>


<style>
    .fecha-verde {
        background-color: #d4edda !important;
    }

    .fecha-roja {
        background-color: #f8d7da !important;
    }

    .btn[disabled] {
        cursor: not-allowed;
        opacity: 0.65;
    }
    
    /* Estilos para campos editables de resultados */
    .resultado-input:focus,
    .observacion-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    .resultado-input.border-warning,
    .observacion-input.border-warning {
        border-color: #ffc107 !important;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
    }
    
    .resultado-input.border-success,
    .observacion-input.border-success {
        border-color: #198754 !important;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
    }
    
    /* Estilos para las tarjetas de resultados */
    .card-header.bg-primary.bg-opacity-10 {
        background-color: rgba(13, 110, 253, 0.1) !important;
        border-bottom: 2px solid rgba(13, 110, 253, 0.2) !important;
    }
    
    .card-header.bg-info.bg-opacity-10 {
        background-color: rgba(13, 202, 240, 0.1) !important;
        border-bottom: 2px solid rgba(13, 202, 240, 0.2) !important;
    }
    
    .card-header.bg-warning.bg-opacity-10 {
        background-color: rgba(255, 193, 7, 0.1) !important;
        border-bottom: 2px solid rgba(255, 193, 7, 0.2) !important;
    }
    
    .card-header.bg-dark.bg-opacity-10 {
        background-color: rgba(33, 37, 41, 0.1) !important;
        border-bottom: 2px solid rgba(33, 37, 41, 0.2) !important;
    }
    
    /* Animaciones para las tarjetas */
    .card.border-0.shadow-sm {
        transition: all 0.3s ease;
        border-radius: 0.5rem !important;
    }
    
    .card.border-0.shadow-sm:hover {
        transform: translateY(-2px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Estilos para los campos de observación */
    .observacion-input {
        resize: vertical;
        min-height: 60px;
        font-size: 0.9rem;
    }
    
    .observacion-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
    }
    
    /* Mejoras para labels */
    .form-label.text-muted.small {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.75rem;
    }
    
    .guardar-resultado {
        transition: all 0.3s ease;
    }
    
    .guardar-resultado:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .guardar-resultado:disabled {
        transform: none;
        box-shadow: none;
    }
    
    /* Animación para el ícono de carga */
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
    
    /* Mejoras visuales para el accordion de resultados */
    .accordion-body {
        background-color: #f8f9fa;
    }
    
    .resultados-form .border.rounded {
        background-color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: box-shadow 0.3s ease;
    }
    
    .resultados-form .border.rounded:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .guardar-todos-resultados {
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .guardar-todos-resultados:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }
    
    .guardar-todos-resultados:disabled {
        transform: none;
        box-shadow: none;
    }
    
    /* Estilos para el modal del informe preliminar */
    .swal-wide {
        max-width: 95% !important;
    }
    
    .swal2-popup.swal-wide .swal2-html-container {
        max-height: 70vh;
        overflow-y: auto;
        text-align: left;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        background-color: #f8f9fa;
    }
    
    /* Estilos para los botones de informe */
    .btn-info:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .btn-success:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .badge.fs-6 {
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
</style>

<!-- Modal para Gestionar Responsables -->
<div class="modal fade" id="gestionarResponsablesModal" tabindex="-1" aria-labelledby="gestionarResponsablesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="gestionarResponsablesModalLabel">
                    <x-heroicon-o-users class="me-2" style="width: 1.25rem; height: 1.25rem;" />
                    Gestionar Responsables de Análisis
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Responsables Actuales -->
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">
                            <x-heroicon-o-user-group class="me-2" style="width: 1rem; height: 1rem;" />
                            Responsables Actuales
                        </h6>
                        <div id="responsablesActualesList" class="border rounded p-3" style="min-height: 200px;">
                            <!-- Se llenará dinámicamente -->
                        </div>
                    </div>

                    <!-- Agregar Nuevos Responsables -->
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">
                            <x-heroicon-o-user-plus class="me-2" style="width: 1rem; height: 1rem;" />
                            Agregar Responsables
                        </h6>
                        <form id="agregarResponsablesForm">
                            @csrf
                            @method('PUT')
                            <input type="hidden" id="gestionar_cotio_numcoti" name="cotio_numcoti">
                            <input type="hidden" id="gestionar_cotio_item" name="cotio_item">
                            <input type="hidden" id="gestionar_cotio_subitem" name="cotio_subitem">
                            <input type="hidden" id="gestionar_instance_number" name="instance_number">
                            
                            <div class="mb-3">
                                <label for="nuevos_responsables" class="form-label">Seleccionar responsables:</label>
                                <select class="form-select" id="nuevos_responsables" name="responsables_analisis[]" multiple>
                                    @foreach($usuariosAnalistas as $analista)
                                        <option value="{{ $analista->usu_codigo }}">{{ $analista->usu_descripcion }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    <x-heroicon-o-information-circle class="me-1" style="width: 0.875rem; height: 0.875rem;" />
                                    Seleccione uno o más responsables para agregar
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <x-heroicon-o-plus class="me-2" style="width: 1rem; height: 1rem;" />
                                Agregar Responsables
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <x-heroicon-o-x-mark class="me-2" style="width: 1rem; height: 1rem;" />
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Gestionar Responsables de Análisis Seleccionados -->
<div class="modal fade" id="gestionarResponsablesSeleccionadosModal" tabindex="-1" aria-labelledby="gestionarResponsablesSeleccionadosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="gestionarResponsablesSeleccionadosModalLabel">
                    <x-heroicon-o-users class="me-2" style="width: 1.25rem; height: 1.25rem;" />
                    Gestionar Responsables de Análisis Seleccionados
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="contadorAnalisisSeleccionados">0</span> análisis seleccionado(s)
                </div>
                
                <form id="formGestionarResponsablesSeleccionados">
                    @csrf
                    <input type="hidden" id="instancias_ids_seleccionadas" name="instancia_ids">
                    
                    <div class="mb-3">
                        <label for="responsables_seleccionados_multiple" class="form-label">
                            <strong>Seleccionar responsables:</strong>
                        </label>
                        <select class="form-select" id="responsables_seleccionados_multiple" name="responsables_analisis[]" multiple style="min-height: 200px;">
                            @foreach($usuariosAnalistas as $analista)
                                <option value="{{ $analista->usu_codigo }}">{{ $analista->usu_descripcion }} ({{ $analista->usu_codigo }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            <x-heroicon-o-information-circle class="me-1" style="width: 0.875rem; height: 0.875rem;" />
                            Seleccione uno o más responsables para asignar a todos los análisis seleccionados. Use Ctrl+Click para seleccionar múltiples.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-fill">
                            <i class="fas fa-save me-2"></i> Guardar Responsables
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const estadoModal = document.getElementById('estadoModal');
    const estadoButtons = document.querySelectorAll('[data-bs-target="#estadoModal"]');
    
    estadoModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const tipo = button.dataset.tipo;
        const card = button.closest('.card-body');
        
        // Establecer valores comunes
        document.getElementById('modal_cotio_numcoti').value = '{{ $cotizacion->coti_num }}';
        document.getElementById('modal_instance_number').value = '{{ $instance }}';
        
        if (tipo === 'categoria') {
            // Configuración para categoría
            document.getElementById('modal_cotio_item').value = '{{ $categoria->cotio_item }}';
            document.getElementById('modal_cotio_subitem').value = '0';
            
            // Obtener estado actual de la categoría
            const estadoActual = card.querySelector('.badge').textContent.trim().toLowerCase();
            document.getElementById('modal_estado').value = estadoActual;
            
            // Ocultar campos de fecha de carga y observaciones para categoría
            document.getElementById('modal_fecha_carga').closest('.mb-3').style.display = 'none';
            document.getElementById('modal_observaciones').closest('.mb-3').style.display = 'none';
            
            // Limpiar valores de los campos ocultos
            document.getElementById('modal_fecha_carga').value = '';
            document.getElementById('modal_observaciones').value = '';
        } else {
            // Configuración para tarea
            document.getElementById('modal_cotio_item').value = button.dataset.item;
            document.getElementById('modal_cotio_subitem').value = button.dataset.subitem;

            
            // Mostrar campos de fecha de carga y observaciones para tareas individuales
            document.getElementById('modal_fecha_carga').closest('.mb-3').style.display = 'block';
            document.getElementById('modal_observaciones').closest('.mb-3').style.display = 'block';
            
            // Usar los datos específicos de la tarea desde los data attributes
            document.getElementById('modal_estado').value = button.dataset.estado || '';
            document.getElementById('modal_fecha_carga').value = button.dataset.fechaCarga || '';
            document.getElementById('modal_observaciones').value = button.dataset.observacionesOt || '';
        }
    });

    document.getElementById('confirmarEstado').addEventListener('click', async function() {
        const form = document.getElementById('estadoForm');
        const formData = new FormData(form);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        try {
            const response = await fetch('{{ route("ordenes.actualizar-estado") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const contentType = response.headers.get('content-type');
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`Error ${response.status}: ${text || response.statusText}`);
            }
            
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Análisis Actualizado',
                    text: data.message,
                    confirmButtonColor: '#3085d6',
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al actualizar el análisis',
                    confirmButtonColor: '#3085d6',
                });
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Ocurrió un error al actualizar el estado. Verifique sus permisos.',
                confirmButtonColor: '#3085d6',
            });
        }
    });
});
</script>



<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#herramientas').select2({
        placeholder: "Seleccione herramientas",
        width: '100%',
        dropdownParent: $('#asignarModal')
    });



document.getElementById('asignarForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const herramientasSeleccionadas = $('#herramientas').select2('data').map(item => item.id);

        const tareasSeleccionadas = Array.from(document.querySelectorAll('.tarea-checkbox:checked'))
            .map(checkbox => {
                const [item, subitem] = checkbox.value.split('_');
                return { item, subitem };
            });

        const formData = {
            cotio_numcoti: "{{ $categoria->cotio_numcoti }}",
            cotio_item: "{{ $categoria->cotio_item }}",
            instance_number: "{{ $instance }}", // Asegúrate de pasar la instancia actual
            vehiculo_asignado: document.getElementById('vehiculo_asignado')?.value || null,
            responsable_codigo: document.getElementById('responsable_codigo')?.value || null,
            fecha_inicio_ot: document.getElementById('fecha_inicio_ot')?.value || null,
            fecha_fin_ot: document.getElementById('fecha_fin_ot')?.value || null,
            herramientas: herramientasSeleccionadas,
            tareas_seleccionadas: tareasSeleccionadas
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        try {
            const response = await fetch("{{ route('asignar.detalles-analisis') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(formData)
            });

            const contentType = response.headers.get('content-type');
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`Error ${response.status}: ${text || response.statusText}`);
            }
            
            if (response.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: data.message,
                    confirmButtonColor: '#3085d6',
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: "Error: " + (data.message || "Hubo un problema."),
                    confirmButtonColor: '#3085d6',
                });
                console.error(data);
            }
        } catch (err) {
            console.error(err);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || "Error al asignar detalles. Verifique sus permisos.",
                confirmButtonColor: '#3085d6',
            });
        }
    });
});


function removerResponsable(event, instanciaId, usuarioCodigo) {
        event.preventDefault();
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        const url = '/muestras/remover-responsable';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                instancia_id: instanciaId,
                usuario_codigo: usuarioCodigo
            })
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                });
            }
        })
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al remover responsable',
                    confirmButtonColor: '#3085d6'
                });
            }
        })
        .catch(error => {
            console.error('Error al remover responsable:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al remover responsable. Verifique sus permisos.',
                confirmButtonColor: '#3085d6'
            });
        });
    }



document.addEventListener('DOMContentLoaded', function() {
    const asignarBtn = document.querySelector('.btn[data-bs-target="#asignarModal"]');
    const checkboxes = document.querySelectorAll('.tarea-checkbox');
    const fechaInicioInput = document.getElementById('fecha_inicio_ot');
    const fechaFinInput = document.getElementById('fecha_fin_ot');
    
    function verificarCheckboxes() {
        const checkedBoxes = Array.from(checkboxes).filter(checkbox => checkbox.checked);
        const alMenosUnoMarcado = checkedBoxes.length > 0;
        
        if (alMenosUnoMarcado) {
            asignarBtn.disabled = false;
            asignarBtn.classList.remove('btn-secondary');
            asignarBtn.classList.add('btn-primary');
            
            if (checkedBoxes.length === 1) {
                const tareaSeleccionada = checkedBoxes[0];
                fechaInicioInput.value = tareaSeleccionada.dataset.fechaInicio || '';
                fechaFinInput.value = tareaSeleccionada.dataset.fechaFin || '';
            } else {
                fechaInicioInput.value = "{{ $categoria->fecha_inicio_ot ? date('Y-m-d\TH:i', strtotime($categoria->fecha_inicio_ot)) : '' }}";
                fechaFinInput.value = "{{ $categoria->fecha_fin_ot ? date('Y-m-d\TH:i', strtotime($categoria->fecha_fin_ot)) : '' }}";
            }
        } else {
            asignarBtn.disabled = true;
            asignarBtn.classList.remove('btn-primary');
            asignarBtn.classList.add('btn-secondary');
            
            fechaInicioInput.value = '';
            fechaFinInput.value = '';
        }
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', verificarCheckboxes);
    });
    
    verificarCheckboxes();
});

async function enviarFrecuencia() {
    const form = document.getElementById('asignarFrecuenciaForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    data.es_frecuente = data.es_frecuente === '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';

    try {
        const response = await fetch("{{ route('asignar.frecuencia') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        const contentType = response.headers.get('content-type');
        let result;
        if (contentType && contentType.includes('application/json')) {
            result = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
        }
        
        if (response.ok) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: result.message,
                confirmButtonColor: '#3085d6',
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "Error: " + (result.message || "Hubo un problema al asignar la frecuencia."),
                confirmButtonColor: '#3085d6',
            });
        }
    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || "Error al procesar la solicitud. Verifique sus permisos.",
            confirmButtonColor: '#3085d6',
        });
    }
}


async function enviarFechas() {
    const fechaInicio = document.getElementById('fecha_inicio_gral').value;
    const fechaFin = document.getElementById('fecha_fin_gral').value;
    const cotio_numcoti = document.getElementById('cotio_numcoti').value;
    const cotio_item = document.getElementById('cotio_item').value;
    const cotio_subitem = document.getElementById('cotio_subitem').value;

    const data = {
        fecha_inicio_ot: fechaInicio,
        fecha_fin_ot: fechaFin,
        cotio_numcoti: cotio_numcoti,
        cotio_item: cotio_item,
        cotio_subitem: cotio_subitem,
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';

    try {
        const response = await fetch("{{ route('asignar.fechas') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        const contentType = response.headers.get('content-type');
        let result;
        if (contentType && contentType.includes('application/json')) {
            result = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
        }

        if (response.ok) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: result.message,
                confirmButtonColor: '#3085d6',
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: "Error: " + (result.message || "Hubo un problema al asignar las fechas."),
                confirmButtonColor: '#3085d6',
            });
        }
    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || "Error al procesar la solicitud. Verifique sus permisos.",
            confirmButtonColor: '#3085d6',
        });
    }
}



document.addEventListener('DOMContentLoaded', () => {
    const fechaWrapper = document.querySelector('.fecha-wrapper');
    const fechaFin = fechaWrapper?.dataset?.fechaFin;

    if (fechaFin) {
        const fechaFinDate = new Date(fechaFin);
        const hoy = new Date();

        const diffTiempo = fechaFinDate.getTime() - hoy.getTime();
        const diffDias = Math.ceil(diffTiempo / (1000 * 60 * 60 * 24));

        const fechaFinSpan = document.querySelector('.fecha-fin');

        if (diffDias <= 3) {
            fechaFinSpan.classList.add('fecha-roja');
        } else {
            fechaFinSpan.classList.add('fecha-verde');
        }
    }
});


document.addEventListener('DOMContentLoaded', function () {
    const checkbox = document.getElementById('es_frecuente');
    const container = document.getElementById('frecuencia_container');

    container.style.display = checkbox.checked ? 'block' : 'none';

    checkbox.addEventListener('change', function () {
        container.style.display = this.checked ? 'block' : 'none';
    });
});

</script>


<script>
    document.getElementById('seleccionarTodas').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.tarea-checkbox');
        checkboxes.forEach(checkbox => {
            if(!checkbox.checked){
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
        });
    });

</script>


<script>
// Activar tooltips de resultados
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<script>
// Manejo de guardado de resultados editables
document.addEventListener('DOMContentLoaded', function() {
    // Función para calcular el promedio para un formulario específico
    function calcularPromedio(form) {
        console.log('Calculando promedios...');
        
        const resultadoInput = form.querySelector('input[name="resultado"]');
        const resultado2Input = form.querySelector('input[name="resultado_2"]');
        const resultado3Input = form.querySelector('input[name="resultado_3"]');
        const resultadoFinalInput = form.querySelector('input[name="resultado_final"]');

        // Respetar edición manual del resultado final
        if (form.dataset.forcedFinal === 'true') {
            console.log('Edición forzada activa: no se recalcula el resultado final');
            return;
        }
        
        // Función mejorada para extraer números con precisión
        function extraerNumero(valor) {
            if (!valor) return NaN;
            
            // Reemplazar comas por puntos para estandarizar
            const valorEstandarizado = valor.toString().replace(',', '.');
            
            // Extraer solo números, punto decimal y signo negativo
            const numeroString = valorEstandarizado
                .replace(/[^\d.-]/g, '')
                .replace(/(\..*)\./g, '$1'); // Eliminar puntos decimales adicionales
                
            const numero = parseFloat(numeroString);
            return isFinite(numero) ? numero : NaN;
        }
        
        // Función para determinar decimales necesarios
        function determinarDecimales(numeros) {
            let maxDecimales = 0;
            numeros.forEach(num => {
                const partes = num.toString().split('.');
                if (partes.length > 1) {
                    maxDecimales = Math.max(maxDecimales, partes[1].length);
                }
            });
            return Math.max(maxDecimales, 4); // Mínimo 4 decimales
        }
        
        // Recolectar valores válidos
        const valores = [];
        [resultadoInput, resultado2Input, resultado3Input].forEach(input => {
            if (input && input.value) {
                const valor = extraerNumero(input.value);
                if (!isNaN(valor)) {
                    valores.push(valor);
                }
            }
        });
        
        console.log('Valores recolectados:', valores);
        
        // Calcular promedio si hay valores válidos
        if (valores.length > 0 && resultadoFinalInput) {
            const suma = valores.reduce((a, b) => a + b, 0);
            const promedio = suma / valores.length;
            
            // Determinar decimales necesarios
            const decimales = determinarDecimales(valores);
            
            // Formatear el resultado
            let resultadoFormateado = promedio.toFixed(decimales);
            
            // Eliminar ceros innecesarios al final
            resultadoFormateado = resultadoFormateado.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.$/, '');
            
            resultadoFinalInput.value = resultadoFormateado;
            console.log('Promedio calculado:', resultadoFormateado);
        } else if (resultadoFinalInput) {
            resultadoFinalInput.value = '';
            console.log('Sin valores válidos para calcular promedio');
        }
    }
    
    // Función para inicializar los eventos de un formulario
    function inicializarFormulario(form) {
        console.log('Inicializando formulario:', form);
        
        const resultadoInput = form.querySelector('input[name="resultado"]');
        const resultado2Input = form.querySelector('input[name="resultado_2"]');
        const resultado3Input = form.querySelector('input[name="resultado_3"]');
        const resultadoFinalInput = form.querySelector('input[name="resultado_final"]');
        
        // Función para validar entrada numérica
        function validarNumerico(input) {
            if (input.value && isNaN(input.value)) {
                input.value = input.value.replace(/[^0-9.]/g, '');
                if (isNaN(input.value)) {
                    input.value = '';
                }
            }
        }
        
        // Marcar edición forzada cuando el usuario escribe en resultado_final
        if (resultadoFinalInput) {
            resultadoFinalInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    delete form.dataset.forcedFinal;
                } else {
                    form.dataset.forcedFinal = 'true';
                }
            });
        }
        
        // Agregar event listeners a los inputs relevantes
        [resultadoInput, resultado2Input, resultado3Input].forEach(input => {
            if (!input) return;
            
            input.addEventListener('input', function() {
                console.log('Input cambiado:', this.name, 'valor:', this.value);
                validarNumerico(this);
                calcularPromedio(form);
            });
            
            input.addEventListener('blur', function() {
                validarNumerico(this);
                calcularPromedio(form);
            });
        });
        
        // Calcular promedio inicial solo si el resultado final está vacío (no sobrescribir valores existentes)
        if (!resultadoFinalInput || resultadoFinalInput.value.trim() === '') {
            calcularPromedio(form);
        }
        
        // Manejar el envío del formulario
        form.addEventListener('submit', function(e) {
    e.preventDefault();
    console.log('Formulario enviado');
    
    // Recalcular solo si NO hay edición forzada
    if (form.dataset.forcedFinal !== 'true') {
        calcularPromedio(form);
    }
    
    // Obtener datos del formulario
    const cotioNumcoti = this.dataset.cotioNumcoti;
    const cotioItem = this.dataset.cotioItem;
    const cotioSubitem = this.dataset.cotioSubitem;
    const instance = this.dataset.instance;
    
    console.log('Datos del formulario:', {
        cotioNumcoti,
        cotioItem,
        cotioSubitem,
        instance
    });
    
    // Recopilar todos los datos del formulario
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('_method', 'PUT');
    
    // Agregar todos los campos de resultado y observación
    const inputs = this.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        if (input.name && input.value !== undefined) {
            formData.append(input.name, input.value);
            console.log('Agregando campo:', input.name, 'valor:', input.value);
        }
    });
    
    const submitBtn = this.querySelector('.guardar-todos-resultados');
    
    // Guardar el texto original del botón ANTES de cambiarlo
    const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
    
    // Mostrar loader
    if (submitBtn) {
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
        submitBtn.disabled = true;
    }
    
    // Construir la URL correcta
    const url = `{{ route('tareas.updateResultado', ['cotio_numcoti' => ':cotio_numcoti', 'cotio_item' => ':cotio_item', 'cotio_subitem' => ':cotio_subitem', 'instance' => ':instance']) }}`
        .replace(':cotio_numcoti', cotioNumcoti)
        .replace(':cotio_item', cotioItem)
        .replace(':cotio_subitem', cotioSubitem)
        .replace(':instance', instance);
    
    console.log('URL de envío:', url);
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';
    
    fetch(url, {
        method: 'POST', // Usar POST porque Laravel maneja PUT internamente
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Respuesta recibida:', response);
        const contentType = response.headers.get('content-type');
        if (!response.ok) {
            if (contentType && contentType.includes('application/json')) {
                return response.json().then(errorData => {
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                });
            } else {
                return response.text().then(text => {
                    throw new Error(`HTTP error! status: ${response.status}: ${text || response.statusText}`);
                });
            }
        }
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error(`Respuesta inesperada: ${text || response.statusText}`);
            });
        }
    })
    .then(data => {
        console.log('Datos de respuesta:', data);
        Swal.fire({
            icon: 'success',
            title: '¡Guardado!',
            text: 'Resultados guardados correctamente',
            timer: 1500,
            showConfirmButton: false
        });
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al guardar los resultados: ' + error.message
        });
    })
    .finally(() => {
        // Restaurar el botón solo si existe
        if (submitBtn) {
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        }
    });
});

    }
    
    // Inicializar todos los formularios de resultados
document.querySelectorAll('.resultados-form').forEach(form => {
    inicializarFormulario(form);
});

// Función para eliminar responsable de todas las tareas
// Función para eliminar responsable de todas las tareas
window.eliminarResponsableTodasTareas = function(usuCodigo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¿Estás seguro de que quieres eliminar este responsable de todas las tareas?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const cotioNumcoti = '{{ $cotizacion->coti_num }}';
            const cotioItem = '{{ $categoria->cotio_item }}';
            const instance = '{{ $instance }}';
            const instanciaId = '{{ $instanciaActual->id ?? "" }}';
            
            console.log('Datos para eliminar responsable:', {
                cotioNumcoti,
                cotioItem,
                instance,
                instanciaId,
                usuCodigo
            });
            
            // Crear el formulario de datos
            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('instancia_id', instanciaId);
            formData.append('user_codigo', usuCodigo);
            formData.append('todos', 'true');
            
            // Construir la URL
            const url = '{{ route("ordenes.remover-responsable", ["ordenId" => $cotizacion->coti_num]) }}';
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            console.log('Enviando petición a:', url);
            
            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Respuesta recibida:', response);
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                console.log('Datos de respuesta:', data);
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'Responsables actualizados correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al eliminar el responsable'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al eliminar el responsable: ' + error.message
                });
            });
        }
    });
};
});

document.getElementById('btnQuitarOT')?.addEventListener('click', function() {
    Swal.fire({
        title: '¿Está seguro?',
        text: "¿Quitar la muestra de la OT?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formQuitarDirectoAOT').submit();
        }
    });
});

window.requestReview = async function(instanciaId) {
    try {
        const result = await Swal.fire({
            title: '¿Estás seguro?',
            text: '¿Estás seguro de que quieres solicitar revisión de resultados?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Solicitar',
            cancelButtonText: 'Cancelar',
            input: 'textarea',
            inputLabel: 'Observaciones (Opcional)',
            inputPlaceholder: 'Escribe las observaciones para la revisión de resultados',
            inputAttributes: { maxlength: 255, 'aria-label': 'Observaciones' }
        });

        if (!result.isConfirmed) {
            return;
        }

        const observaciones = result.value || '';

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';

        const response = await fetch('/ordenes/' + instanciaId + '/request-review', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ observaciones })
        });

        const contentType = response.headers.get('content-type');
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
        }
        
        if (response.ok && data.success) {
            await Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'Revisión de resultados solicitada correctamente',
                timer: 1500,
                showConfirmButton: false
            });
            window.location.reload();
        } else {
            throw new Error(data.message || 'Hubo un problema al solicitar la revisión.');
        }
    } catch (error) {
        console.error(error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al solicitar revisión de resultados: ' + (error.message || 'Desconocido')
        });
    }
}

async function requestReviewCancel(instanciaId) {
    console.log('Cancelar revisión de resultados para instancia:', instanciaId);
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Estás seguro de que quieres cancelar la revisión de resultados?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Si, cancelar',
        cancelButtonText: 'No, no cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            const response = await fetch('/ordenes/' + instanciaId + '/request-review-cancel', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            const contentType = response.headers.get('content-type');
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`Error ${response.status}: ${text || response.statusText}`);
            }
            
            if (response.ok) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'Revisión de resultados cancelada correctamente',
                    timer: 1500,
                    showConfirmButton: false
                });
                window.location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al cancelar la revisión de resultados'
                });
            }
        }
    });
}

// Función para ver informe preliminar
async function verInformePreliminar(instanciaId) {
    try {
        // Mostrar loading
        Swal.fire({
            title: 'Cargando informe...',
            html: 'Por favor espera mientras se genera el informe preliminar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';

        const response = await fetch(`/ordenes/${instanciaId}/informe-preliminar`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const contentType = response.headers.get('content-type');
        if (response.ok) {
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                throw new Error(`Respuesta inesperada: ${text || response.statusText}`);
            }
            
            // Cerrar loading y mostrar informe en modal
            Swal.close();
            mostrarModalInforme(data.informe, instanciaId);
        } else {
            const text = await response.text();
            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al cargar el informe preliminar: ' + error.message
        });
    }
}

// Función para mostrar el modal del informe
function mostrarModalInforme(contenidoInforme, instanciaId) {
    Swal.fire({
        title: 'Informe Preliminar',
        html: `
            <div class="text-start" style="max-height: 70vh; overflow-y: auto;">
                ${contenidoInforme}
            </div>
        `,
        width: '90%',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-circle"></i> Aprobar Informe',
        confirmButtonColor: '#28a745',
        cancelButtonText: '<i class="fas fa-times"></i> Cerrar',
        cancelButtonColor: '#6c757d',
        showCloseButton: true,
        customClass: {
            popup: 'swal-wide',
            content: 'text-start'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            aprobarInforme(instanciaId);
        }
    });
}

// Función para aprobar informe
async function aprobarInforme(instanciaId) {
    try {
        const result = await Swal.fire({
            title: '¿Aprobar informe?',
            text: '¿Estás seguro de que quieres aprobar este informe? Esta acción marcará el informe como aprobado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check"></i> Sí, aprobar',
            cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        });

        if (!result.isConfirmed) {
            return;
        }

        // Mostrar loading
        Swal.fire({
            title: 'Aprobando informe...',
            html: 'Por favor espera...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';

        const response = await fetch(`/ordenes/${instanciaId}/aprobar-informe`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        const contentType = response.headers.get('content-type');
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
        }

        if (response.ok && data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Informe Aprobado!',
                text: 'El informe ha sido aprobado correctamente',
                timer: 2000,
                showConfirmButton: false
            });
            
            // Recargar la página para mostrar los cambios
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(data.message || 'Error al aprobar el informe');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al aprobar el informe: ' + error.message
        });
    }
}
</script>

{{-- MODAL DE EDICIÓN DE HERRAMIENTAS --}}
<div class="modal fade" id="herramientasModal" tabindex="-1" aria-labelledby="herramientasModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formEditarHerramientas" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-header">
          <h5 class="modal-title" id="herramientasModalLabel">Editar herramientas de la muestra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
          <div id="herramientasModalDescripcion" class="mb-2 text-primary small"></div>
          <div id="herramientasModalInputs">
            <div class="text-center text-muted">Cargando herramientas...</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
#herramientasModal .modal-body {
    max-height: 400px;
    overflow-y: auto;
}
#herramientasModalInputs .select2-container {
    width: 100% !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var herramientasModal = document.getElementById('herramientasModal');
    if (herramientasModal) {
        herramientasModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var instanciaId = button.getAttribute('data-instancia-id');
            var descripcion = button.getAttribute('data-descripcion');
            var modalDescripcion = document.getElementById('herramientasModalDescripcion');
            var modalInputs = document.getElementById('herramientasModalInputs');
            var form = document.getElementById('formEditarHerramientas');

            modalDescripcion.textContent = descripcion ? 'Muestra: ' + descripcion : '';
            form.action = '/instancias/' + instanciaId + '/herramientas';

            // Loader
            modalInputs.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Cargando herramientas...</div>';

            // Cargar herramientas actuales por AJAX
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch('/api/instancias/' + instanciaId + '/herramientas', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                        });
                    }
                })
                .then(data => {
                    let html = '';
                    html += `<label for='herramientasSelect' class='form-label'>Herramientas disponibles</label>`;
                    html += `<select id='herramientasSelect' name='herramientas[]' multiple='multiple' class='form-select'></select>`;
                    modalInputs.innerHTML = html;

                    // Inicializar Select2
                    const select = $('#herramientasSelect');
                    select.empty();
                    if (data && data.herramientas && data.herramientas.length > 0) {
                        data.herramientas.forEach(h => {
                            const option = new Option(h.nombre, h.id, h.asignada, h.asignada);
                            select.append(option);
                        });
                    }
                    select.select2({
                        dropdownParent: $('#herramientasModal'),
                        width: '100%',
                        placeholder: 'Seleccione herramientas',
                        allowClear: true
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalInputs.innerHTML = '<div class="text-danger">Error al cargar herramientas. Verifique sus permisos.</div>';
                });
        });

        // Enviar formulario por AJAX
        document.getElementById('formEditarHerramientas').addEventListener('submit', function(e) {
            e.preventDefault();
            var form = this;
            var formData = new FormData(form);
            var action = form.action;
            // Agregar los valores seleccionados manualmente (por select2)
            var herramientas = $('#herramientasSelect').val() || [];
            formData.delete('herramientas[]');
            herramientas.forEach(id => formData.append('herramientas[]', id));

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             form.querySelector('[name=_token]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch(action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Guardado!',
                        text: 'Herramientas actualizadas correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => { var modal = bootstrap.Modal.getInstance(herramientasModal); modal.hide(); location.reload(); }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Ocurrió un error al guardar.'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al guardar. Verifique sus permisos.'
                });
            });
        });
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('historialResultadoModal'));
    const historial = @json($historialCambios);

    document.querySelectorAll('.btn-historial-resultado').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault(); // Evita comportamiento predeterminado
            event.stopPropagation(); // Evita propagación del evento al formulario

            const instanciaId = this.dataset.instanciaId;
            const campo = this.dataset.campo;
            const cambios = historial[instanciaId] ? historial[instanciaId].filter(c => c.campo_modificado === campo) : [];

            let content = '';
            if (cambios.length === 0) {
                content = '<p>No hay historial de cambios para este resultado.</p>';
            } else {
                content = '<table class="table table-bordered table-striped">' +
                          '<thead><tr>' +
                          '<th>Fecha</th>' +
                          '<th>Usuario</th>' +
                          '<th>Acción</th>' +
                          '<th>Valor Anterior</th>' +
                          '<th>Valor Nuevo</th>' +
                          '</tr></thead><tbody>';

                cambios.forEach(cambio => {
                    content += `<tr>
                        <td>${new Date(cambio.fecha_cambio).toLocaleString()}</td>
                        <td>${cambio.usuario ? cambio.usuario.usu_descripcion : 'Desconocido'}</td>
                        <td>${cambio.accion.charAt(0).toUpperCase() + cambio.accion.slice(1)}</td>
                        <td>${cambio.valor_anterior || 'N/A'}</td>
                        <td>${cambio.valor_nuevo || 'N/A'}</td>
                    </tr>`;
                });

                content += '</tbody></table>';
            }

            document.getElementById('historialResultadoContent').innerHTML = content;
            document.getElementById('historialResultadoModalLabel').textContent = `Historial de Cambios - Resultado: ${campo}`;
            modal.show();
        });
    });
});
</script>

{{-- MODAL DE EDICIÓN DE RESPONSABLES --}}
<div class="modal fade" id="editarResponsables" tabindex="-1" aria-labelledby="editarResponsablesLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formEditarResponsables" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editarResponsablesLabel">Editar Responsables</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="responsables_analisis" class="form-label">Responsables de Análisis</label>
                        <select class="form-select select2-multiple" id="responsables_analisis" name="responsables_analisis[]" multiple>
                            @foreach($usuarios as $usuario)
                                <option value="{{ $usuario->usu_codigo }}">
                                    {{ $usuario->usu_descripcion }} ({{ $usuario->usu_codigo }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Los responsables actuales aparecen seleccionados y se mantienen. Seleccione responsables adicionales para agregar a la muestra y todos sus análisis.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Inicializar el modal de editar responsables
document.addEventListener('DOMContentLoaded', function() {
    const editarResponsablesModal = document.getElementById('editarResponsables');
    const form = document.getElementById('formEditarResponsables');
    const select = document.getElementById('responsables_analisis');
    
    // Inicializar Select2
    $(select).select2({
        dropdownParent: $('#editarResponsables'),
        multiple: true,
        placeholder: "Seleccione responsables",
        allowClear: true
    });
    
    // Cuando se abre el modal, cargar los responsables actuales
    editarResponsablesModal.addEventListener('show.bs.modal', function(event) {
        // Establecer la acción del formulario
        const cotioNumcoti = '{{ $cotizacion->coti_num }}';
        const cotioItem = '{{ $categoria->cotio_item }}';
        const instance = '{{ $instance }}';
        
        form.action = `/ordenes/${cotioNumcoti}/editar-responsables`;
        
        // Agregar campos ocultos
        let hiddenFields = form.querySelector('.hidden-fields');
        if (hiddenFields) {
            hiddenFields.remove();
        }
        
        hiddenFields = document.createElement('div');
        hiddenFields.className = 'hidden-fields';
        hiddenFields.innerHTML = `
            <input type="hidden" name="cotio_item" value="${cotioItem}">
            <input type="hidden" name="instance_number" value="${instance}">
        `;
        form.appendChild(hiddenFields);
        
        // Cargar responsables actuales
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        fetch(`/api/get-responsables-analisis`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                cotio_numcoti: cotioNumcoti,
                cotio_item: cotioItem,
                instance_number: instance
            })
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                });
            }
        })
        .then(data => {
            if (data.success) {
                // Guardar los responsables iniciales para poder comparar después
                window.responsablesIniciales = data.responsables || [];
                
                // Cargar todos los responsables actuales en el select
                $(select).val(data.responsables).trigger('change');
                console.log('Responsables actuales cargados:', data.responsables);
            }
        })
        .catch(error => {
            console.error('Error cargando responsables:', error);
        });
    });
    
    // Manejar el envío del formulario
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        // Obtener los valores seleccionados de Select2
        const responsablesSeleccionados = $(select).val() || [];
        
        // Obtener los responsables que estaban cargados inicialmente
        const responsablesIniciales = window.responsablesIniciales || [];
        
        // Filtrar solo los responsables nuevos (que no estaban inicialmente)
        const nuevosResponsables = responsablesSeleccionados.filter(responsable => 
            !responsablesIniciales.includes(responsable)
        );
        
        // Limpiar los valores existentes del FormData para responsables_analisis
        formData.delete('responsables_analisis[]');
        
        // Agregar solo los responsables nuevos
        if (nuevosResponsables.length > 0) {
            nuevosResponsables.forEach(responsable => {
                formData.append('responsables_analisis[]', responsable);
            });
        }
        
        console.log('Responsables iniciales:', responsablesIniciales);
        console.log('Responsables seleccionados:', responsablesSeleccionados);
        console.log('Nuevos responsables a enviar:', nuevosResponsables);
        
        // Si no hay nuevos responsables, mostrar mensaje y no enviar
        if (nuevosResponsables.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Sin cambios',
                text: 'No se han seleccionado nuevos responsables para agregar.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                });
            }
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'Responsables actualizados correctamente',
                    timer: 1500,
                    showConfirmButton: false
                });
                bootstrap.Modal.getInstance(editarResponsablesModal).hide();
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Ocurrió un error al guardar.'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al guardar. Verifique sus permisos.'
            });
        });
    });
});

// Función para quitar responsable
function quitarResponsable(responsableCodigo, cotioNumcoti, cotioItem, cotioSubitem, instanceNumber, nombreResponsable) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Deseas quitar a "${nombreResponsable}" como responsable de este análisis?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch(`/ordenes/${cotioNumcoti}/quitar-responsable`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    cotio_item: cotioItem,
                    cotio_subitem: cotioSubitem,
                    instance_number: instanceNumber,
                    responsable_codigo: responsableCodigo
                })
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Responsable quitado!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo quitar el responsable'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al quitar el responsable. Verifique sus permisos.'
                });
            });
        }
    });
}

// Manejar el modal de gestionar responsables
document.addEventListener('DOMContentLoaded', function() {
    const gestionarResponsablesModal = document.getElementById('gestionarResponsablesModal');
    const agregarResponsablesForm = document.getElementById('agregarResponsablesForm');
    
    // Configurar modal cuando se abre
    gestionarResponsablesModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const cotioNumcoti = button.getAttribute('data-cotio-numcoti');
        const cotioItem = button.getAttribute('data-cotio-item');
        const cotioSubitem = button.getAttribute('data-cotio-subitem');
        const instanceNumber = button.getAttribute('data-instance-number');
        const instanciaId = button.getAttribute('data-instancia-id');
        
        // Establecer valores en el formulario
        document.getElementById('gestionar_cotio_numcoti').value = cotioNumcoti;
        document.getElementById('gestionar_cotio_item').value = cotioItem;
        document.getElementById('gestionar_cotio_subitem').value = cotioSubitem;
        document.getElementById('gestionar_instance_number').value = instanceNumber;
        
        // Establecer la acción del formulario
        agregarResponsablesForm.action = `/ordenes/${cotioNumcoti}/editar-responsables`;
        
        // Cargar responsables actuales
        cargarResponsablesActuales(cotioNumcoti, cotioItem, cotioSubitem, instanceNumber);
    });
    
    // Manejar envío del formulario para agregar responsables
    agregarResponsablesForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(agregarResponsablesForm);
        const responsablesSeleccionados = Array.from(document.getElementById('nuevos_responsables').selectedOptions)
            .map(option => option.value);
        
        if (responsablesSeleccionados.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin selección',
                text: 'Por favor, selecciona al menos un responsable para agregar.'
            });
            return;
        }
        
        // Limpiar y agregar responsables seleccionados
        formData.delete('responsables_analisis[]');
        responsablesSeleccionados.forEach(responsable => {
            formData.append('responsables_analisis[]', responsable);
        });
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        fetch(agregarResponsablesForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                });
            }
        })
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Responsables agregados!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                bootstrap.Modal.getInstance(gestionarResponsablesModal).hide();
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudieron agregar los responsables'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al agregar responsables. Verifique sus permisos.'
            });
        });
    });
});

// Función para cargar responsables actuales en el modal
function cargarResponsablesActuales(cotioNumcoti, cotioItem, cotioSubitem, instanceNumber) {
    const responsablesActualesList = document.getElementById('responsablesActualesList');
    
    // Mostrar loading
    responsablesActualesList.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Cargando...</div>';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';
    
    fetch('/api/get-responsables-analisis', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            cotio_numcoti: cotioNumcoti,
            cotio_item: cotioItem,
            cotio_subitem: cotioSubitem,
            instance_number: instanceNumber
        })
    })
    .then(response => {
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return response.text().then(text => {
                throw new Error(`Error ${response.status}: ${text || response.statusText}`);
            });
        }
    })
    .then(data => {
        if (data.success && data.responsables) {
            if (data.responsables.length > 0) {
                // Obtener información completa de los responsables
                Promise.all(data.responsables.map(codigo => 
                    fetch(`/api/usuario/${codigo}`, {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            if (response.ok) {
                                const contentType = response.headers.get('content-type');
                                if (contentType && contentType.includes('application/json')) {
                                    return response.json();
                                }
                            }
                            return null;
                        })
                        .catch(() => null)
                ))
                .then(responsablesInfo => {
                    const responsablesHTML = data.responsables.map((codigo, index) => {
                        const info = responsablesInfo[index];
                        const nombre = info?.usu_descripcion || codigo;
                        
                                                                return `
                            <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                                <div>
                                    <strong>${nombre}</strong>
                                    <small class="text-muted d-block">${codigo}</small>
                                </div>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger"
                                        onclick="quitarResponsable('${codigo}', '${cotioNumcoti}', '${cotioItem}', '${cotioSubitem}', '${instanceNumber}', '${nombre}')"
                                        title="Quitar responsable">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `;
                    }).join('');
                    
                    responsablesActualesList.innerHTML = responsablesHTML;
                });
            } else {
                responsablesActualesList.innerHTML = '<div class="text-muted text-center">No hay responsables asignados</div>';
            }
        } else {
            responsablesActualesList.innerHTML = '<div class="text-danger text-center">Error al cargar responsables</div>';
        }
    })
    .catch(error => {
        console.error('Error cargando responsables:', error);
        responsablesActualesList.innerHTML = '<div class="text-danger text-center">Error al cargar responsables. Verifique sus permisos.</div>';
    });
}

// Función para seleccionar todos los análisis de una muestra
function seleccionarTodosAnalisis(muestraId, item, instance, numcoti) {
    const checkboxes = document.querySelectorAll(
        `.tarea-checkbox-analisis[data-muestra-id="${muestraId}"][data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled)`
    );
    
    if (checkboxes.length === 0) {
        Swal.fire({
            title: 'Sin análisis',
            text: 'No hay análisis disponibles para seleccionar',
            icon: 'info'
        });
        return;
    }
    
    const todosSeleccionados = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !todosSeleccionados;
    });
    
    actualizarMenuAcciones(muestraId);
}

// Función para actualizar la visibilidad del menú de acciones
function actualizarMenuAcciones(muestraId) {
    const checkboxes = document.querySelectorAll(
        `.tarea-checkbox-analisis[data-muestra-id="${muestraId}"]:not(:disabled)`
    );
    
    const seleccionados = Array.from(checkboxes).filter(cb => cb.checked);
    const menuAcciones = document.getElementById(`menu-acciones-${muestraId}`);
    
    if (menuAcciones) {
        menuAcciones.style.display = seleccionados.length > 0 ? 'block' : 'none';
    }
}

// Función para finalizar análisis seleccionados
function finalizarAnalisisSeleccionados(muestraId, item, instance, numcoti) {
    const checkboxes = document.querySelectorAll(
        `.tarea-checkbox-analisis[data-muestra-id="${muestraId}"][data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:checked:not(:disabled)`
    );
    
    if (checkboxes.length === 0) {
        Swal.fire({
            title: 'Sin selección',
            text: 'No hay análisis seleccionados para finalizar',
            icon: 'warning'
        });
        return;
    }

    const instanciaIds = Array.from(checkboxes)
        .map(cb => cb.dataset.instanciaId)
        .filter(id => id && id !== 'null' && id !== '');

    if (instanciaIds.length === 0) {
        Swal.fire({
            title: 'Error',
            text: 'No se encontraron instancias válidas para finalizar',
            icon: 'error'
        });
        return;
    }

    Swal.fire({
        title: '¿Estás seguro?',
        text: `¿Deseas finalizar ${instanciaIds.length} análisis seleccionado(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, finalizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Finalizando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch('/ordenes/finalizar-analisis-seleccionados', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    instancia_ids: instanciaIds
                })
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: data.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al finalizar los análisis',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al finalizar los análisis. Verifique sus permisos.',
                    icon: 'error'
                });
            });
        }
    });
}

// Función para gestionar responsables de análisis seleccionados
function gestionarResponsablesAnalisisSeleccionados(muestraId, item, instance, numcoti) {
    const checkboxes = document.querySelectorAll(
        `.tarea-checkbox-analisis[data-muestra-id="${muestraId}"][data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:checked:not(:disabled)`
    );

    if (checkboxes.length === 0) {
        Swal.fire({
            title: 'Sin selección',
            text: 'No hay análisis seleccionados para gestionar responsables',
            icon: 'warning'
        });
        return;
    }

    const instanciaIds = Array.from(checkboxes)
        .map(cb => cb.dataset.instanciaId)
        .filter(id => id && id !== 'null' && id !== '');

    if (instanciaIds.length === 0) {
        Swal.fire({
            title: 'Error',
            text: 'No se encontraron instancias válidas para gestionar responsables',
            icon: 'error'
        });
        return;
    }

    // Actualizar contador en el modal
    document.getElementById('contadorAnalisisSeleccionados').textContent = instanciaIds.length;
    
    // Guardar los IDs de instancias en el campo oculto
    document.getElementById('instancias_ids_seleccionadas').value = JSON.stringify(instanciaIds);
    
    // Limpiar selección previa del select
    document.getElementById('responsables_seleccionados_multiple').selectedIndex = -1;
    
    // Mostrar el modal
    const modal = new bootstrap.Modal(document.getElementById('gestionarResponsablesSeleccionadosModal'));
    modal.show();
}

// Manejar el envío del formulario de responsables seleccionados
document.addEventListener('DOMContentLoaded', function() {
    const formGestionarResponsables = document.getElementById('formGestionarResponsablesSeleccionados');
    
    if (formGestionarResponsables) {
        formGestionarResponsables.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const instanciaIdsJson = document.getElementById('instancias_ids_seleccionadas').value;
            const instanciaIds = JSON.parse(instanciaIdsJson);
            
            const responsablesSeleccionados = Array.from(document.getElementById('responsables_seleccionados_multiple').selectedOptions)
                .map(option => option.value);
            
            if (responsablesSeleccionados.length === 0) {
                Swal.fire({
                    title: 'Sin selección',
                    text: 'Por favor, seleccione al menos un responsable',
                    icon: 'warning'
                });
                return;
            }
            
            Swal.fire({
                title: 'Asignando responsables...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            fetch('/ordenes/asignar-responsables-analisis-seleccionados', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    instancia_ids: instanciaIds,
                    responsables_analisis: responsablesSeleccionados
                })
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: data.message,
                        icon: 'success'
                    }).then(() => {
                        // Cerrar el modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('gestionarResponsablesSeleccionadosModal'));
                        modal.hide();
                        // Recargar la página para mostrar los cambios
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Error al asignar los responsables',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al asignar los responsables. Verifique sus permisos.',
                    icon: 'error'
                });
            });
        });
    }
});

// Event listeners para checkboxes de análisis
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tarea-checkbox-analisis').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const muestraId = this.dataset.muestraId;
            actualizarMenuAcciones(muestraId);
        });
    });

    // Inicializar visibilidad del menú para cada muestra
    document.querySelectorAll('[id^="menu-acciones-"]').forEach(menu => {
        const muestraId = menu.id.replace('menu-acciones-', '');
        actualizarMenuAcciones(muestraId);
    });
});

</script>

@endsection
