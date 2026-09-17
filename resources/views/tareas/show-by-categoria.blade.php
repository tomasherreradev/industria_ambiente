@extends('layouts.app')

@section('content')
@php
    $reqCadenaCustodiaMuestra = (bool) (optional($instancia->muestra)->req_cadena_custodia ?? false);
    $esMuestreadoEstado = strtolower(trim((string) ($instancia->cotio_estado ?? ''))) === 'muestreado';
    $fechaFinDetalle = $instancia->fecha_identificacion
        ? \Carbon\Carbon::parse($instancia->fecha_identificacion)->format('d/m/Y H:i')
        : ($instancia->fecha_fin_muestreo
            ? \Carbon\Carbon::parse($instancia->fecha_fin_muestreo)->format('d/m/Y H:i')
            : 'N/A');
@endphp
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/tareas-muestreo-mobile.css') }}?v={{ filemtime(public_path('css/tareas-muestreo-mobile.css')) }}">
<div class="container py-4 ucrud ucrud-operativo ucrud-detalle tarea-detalle-page" data-ucrud-root>
    @include('partials.ucrud-form-header', [
        'title' => 'Detalle de muestra',
        'subtitle' => $instancia->cotio_descripcion ?? null,
        'backUrl' => $canalParaFiltrar ? route('muestras.show', ['coti_num' => $instancia->cotio_numcoti, 'canal' => $canalParaFiltrar]) : route('mis-tareas'),
    ])

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success mb-3" role="status">
            <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Detalles de la muestra -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center tarea-detalle-hero">
            <div class="tarea-detalle-hero-main">
                <h5 class="mb-0 tarea-detalle-hero-title">
                    {{ $instancia->cotio_descripcion }}
                </h5>
                @php
                    $estado = strtolower($instancia->cotio_estado);
                    $badgeClass = match ($estado) {
                        'pendiente', 'coordinado muestreo', 'coordinado analisis' => 'warning',
                        'en proceso', 'en revision muestreo', 'en revision analisis' => 'info',
                        'finalizado', 'muestreado', 'analizado' => 'success',
                        'suspension' => 'danger',
                        default => 'secondary',
                    };
                @endphp
                <span class="badge bg-{{ $badgeClass }} mt-2 d-inline-block">
                    {{ ucfirst($instancia->cotio_estado) }}
                </span>
            </div>
            @if(Auth::user()->rol != 'laboratorio')
                <div class="d-flex flex-wrap gap-2 botones-muestra tarea-detalle-acciones">
                    @if(!$esMuestreadoEstado)
                        @if($instancia->cotio_estado != 'suspension')
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#suspenderModal">
                                <i class="fas fa-pause me-1"></i> Suspender
                            </button>
                        @else
                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-pause me-1"></i> Ya suspendida
                            </button>
                        @endif
                    @endif
                    @if($instancia->cotio_estado == 'coordinado muestreo' || $instancia->cotio_estado == 'en revision muestreo')
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editMuestraModal">
                            @if($instancia->cotio_identificacion)
                                Editar identificación
                            @else
                                Agregar identificación
                            @endif
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <div class="card-body">
            <div class="row gy-3 tarea-detalle-datos">
                <div class="col-md-4">
                    <div class="tarea-detalle-dato">
                        <span class="tarea-detalle-dato-label">Cotización</span>
                        <span class="tarea-detalle-dato-value">{{ $instancia->cotio_numcoti }}</span>
                    </div>
                    <div class="tarea-detalle-dato mt-2">
                        <span class="tarea-detalle-dato-label">Identificación</span>
                        <span class="tarea-detalle-dato-value">{{ $instancia->cotio_identificacion ?? 'N/A' }}</span>
                    </div>
                    <div class="tarea-detalle-dato mt-2">
                        <span class="tarea-detalle-dato-label">N° Precinto</span>
                        <span class="tarea-detalle-dato-value">{{ $instancia->nro_precinto ?? 'N/A' }}</span>
                    </div>
                    @if($reqCadenaCustodiaMuestra)
                        <div class="tarea-detalle-dato mt-2">
                            <span class="tarea-detalle-dato-label">N° Cadena</span>
                            <span class="tarea-detalle-dato-value">{{ $instancia->nro_cadena ?? 'N/A' }}</span>
                        </div>
                    @elseif(filled($instancia->nro_cadena))
                        <div class="tarea-detalle-dato mt-2">
                            <span class="tarea-detalle-dato-label">N° Cadena</span>
                            <span class="tarea-detalle-dato-value">{{ $instancia->nro_cadena }} <span class="text-muted small">(no exigido)</span></span>
                        </div>
                    @else
                        <div class="tarea-detalle-dato mt-2">
                            <span class="tarea-detalle-dato-label">N° Cadena</span>
                            <span class="tarea-detalle-dato-value text-muted">No requerido</span>
                        </div>
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="tarea-detalle-dato">
                        <span class="tarea-detalle-dato-label">Fecha inicio</span>
                        <span class="tarea-detalle-dato-value">
                            {{ $instancia->fecha_inicio_muestreo ? \Carbon\Carbon::parse($instancia->fecha_inicio_muestreo)->format('d/m/Y H:i') : 'N/A' }}
                        </span>
                    </div>
                    <div class="tarea-detalle-dato mt-2">
                        <span class="tarea-detalle-dato-label">Fecha fin</span>
                        <span class="tarea-detalle-dato-value">{{ $fechaFinDetalle }}</span>
                    </div>
                </div>
                @if(Auth::user()->rol != 'laboratorio')
                    <div class="col-md-4">
                        <div class="tarea-detalle-dato">
                            <span class="tarea-detalle-dato-label">Vehículo</span>
                            <span class="tarea-detalle-dato-value">
                                @if($instancia->vehiculo)
                                    {{ $instancia->vehiculo->marca }} {{ $instancia->vehiculo->modelo }} ({{ $instancia->vehiculo->patente }})
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                    </div>
                @endif
                <div class="col-md-4">
                    @if($instancia->image)
                        <img src="{{ Storage::url('images/' . $instancia->image) }}" alt="Imagen de la muestra" class="img-fluid w-50 rounded">
                    @else
                        <p class="text-muted mb-0">No hay imagen disponible</p>
                    @endif
                </div>
            </div>
            
            @php
                // Parsear notas internas desde la categoría (ensayo)
                $notasInternas = [];
                
                // Obtener la categoría (ensayo) - intentar primero con la relación, luego directamente
                $categoria = $instancia->muestra ?? null;
                if (!$categoria) {
                    // Si la relación no está cargada, obtenerla directamente
                    $categoria = \App\Models\Cotio::where('cotio_numcoti', $instancia->cotio_numcoti)
                        ->where('cotio_item', $instancia->cotio_item)
                        ->where('cotio_subitem', 0)
                        ->first();
                }
                
                if ($categoria && !empty($categoria->cotio_nota_contenido)) {
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
                <div class="alert alert-warning mt-3">
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


            @if(filled($instancia->observaciones_muestreo_coord))
                <div class="mt-3 pt-3 border-top">
                    <div class="tarea-detalle-dato">
                        <span class="tarea-detalle-dato-label">Observaciones del coordinador</span>
                        <p class="mb-0 mt-1" style="white-space: pre-wrap;">{{ trim($instancia->observaciones_muestreo_coord) }}</p>
                    </div>
                </div>
            @endif

            <!-- Herramientas asignadas -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3 tarea-detalle-herramientas-header">
                    <h6 class="mb-0">Herramientas Asignadas</h6>
                    @if($instancia->cotio_estado != 'muestreado')
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editHerramientasModal">
                            <i class="fas fa-edit me-1"></i> Editar Herramientas
                        </button>
                    @endif
                </div>
                
                @if(isset($herramientasMuestra) && $herramientasMuestra->count() > 0)
                    <div class="row gy-2 tarea-detalle-herramientas-grid" id="herramientasContainer">
                        @foreach($herramientasMuestra as $herramienta)
                            <div class="col-md-4">
                                <div class="card border h-100">
                                    <div class="card-body p-2">
                                        <h6 class="mb-1">{{ $herramienta->equipamiento }}</h6>
                                        <p class="small mb-0 text-muted">{{ $herramienta->marca_modelo }}</p>
                                        @if($herramienta->cantidad > 1)
                                            <span class="badge bg-secondary">Cantidad: {{ $herramienta->cantidad }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-info" id="noHerramientasMsg">
                        <i class="fas fa-info-circle me-1"></i>
                        No hay herramientas asignadas a esta muestra.
                    </div>
                @endif
            </div>

            @if($instancia->latitud && $instancia->longitud)
                <div class="mt-3">
                    <a href="https://www.google.com/maps?q={{ $instancia->latitud }},{{ $instancia->longitud }}&z=15&t=m" 
                        target="_blank"
                        class="btn btn-sm btn-outline-primary">
                         <x-heroicon-o-map style="width: 14px; height: 14px;" class="me-1"/>
                         Ver en Google Maps
                     </a>
                </div>
            @endif
        </div>
    </div>




    <div class="card shadow-sm my-5">
        <div class="card-header bg-secondary text-white">
            <h5 style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#medicionesCollapse" aria-expanded="false" aria-controls="medicionesCollapse">
                Mediciones de campo
            </h5>
        </div>
        
        <div id="medicionesCollapse" class="collapse show">
            <form class="card-body" action="{{ route('tareas.updateMediciones', $instancia->id) }}" 
                  method="POST" id="medicionesForm">
                @csrf
                @method('PUT')
                <div class="mt-3">
                    @if($instancia->valoresVariables->isEmpty())
                        <div class="alert alert-info">
                            No hay variables asignadas para este análisis.
                        </div>
                    @else
                        @foreach($instancia->valoresVariables as $valorVariable)
                            <div class="mb-3">
                                <label for="variable_{{ $valorVariable->id }}" class="form-label">
                                    {{ $valorVariable->variable }}
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       name="valores[{{ $valorVariable->id }}][valor]" 
                                       id="variable_{{ $valorVariable->id }}" 
                                       value="{{ $valorVariable->valor ?? '' }}" 
                                       placeholder="Ingrese el valor para {{ $valorVariable->variable }}"
                                       @if($instancia->cotio_estado == 'muestreado') readonly @endif>
                                <input type="hidden" 
                                       name="valores[{{ $valorVariable->id }}][variable_id]" 
                                       value="{{ $valorVariable->id }}">
                            </div>
                        @endforeach
    
                        <div style="background-color: #DECB72; padding: 10px; border-radius: 5px; margin-top: 50px; margin-bottom: 30px;">
                            <label for="observaciones_medicion_coord_muestreo" class="form-label">
                                Observaciones del coordinador:
                            </label>
                            <textarea class="form-control" id="observaciones_muestreador" rows="3" readonly
                                style="background-color: #fff8e1; border-left: 4px solid #ffc107; padding-left: 12px;">
                                {{ trim($instancia->observaciones_medicion_coord_muestreo) ?? '' }}
                            </textarea>
                        </div>
    
                        <div class="mb-3">
                            <label for="observaciones_medicion_muestreador" class="form-label">
                                Observaciones del muestreador:
                            </label>
                            <textarea class="form-control" 
                                name="observaciones_medicion_muestreador" 
                                id="observaciones_medicion_muestreador" 
                                rows="3"
                                placeholder="Ingrese las observaciones del muestreador"
                                @if($instancia->cotio_estado == 'muestreado') readonly @endif
                            >{{ trim($instancia->observaciones_medicion_muestreador ?? '') }}</textarea>
                        </div>
        
                        @if($instancia->cotio_estado != 'muestreado')
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" id="submitButton">
                                    Guardar
                                </button>
                            </div>
                        @endif
                    @endif
                </div>
            </form>  
        </div>
    </div>

    @if($instancia->cotio_estado != 'muestreado')
        <div class="d-flex justify-content-end mb-4 d-none d-md-flex">
            <button type="button" class="btn btn-success" id="guardarIdentificacionYMediciones">
                Guardar identificación y mediciones
            </button>
        </div>

        <div class="tarea-detalle-sticky-actions d-md-none">
            @if($instancia->cotio_estado == 'coordinado muestreo' || $instancia->cotio_estado == 'en revision muestreo')
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editMuestraModal">
                    {{ $instancia->cotio_identificacion ? 'Editar ID' : 'Identificación' }}
                </button>
            @endif
            <button type="button" class="btn btn-success" id="guardarIdentificacionYMedicionesMobile">
                Guardar todo
            </button>
        </div>
    @endif





    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Análisis Asociados</h5>
        </div>
        
        @if($analisis->isEmpty())
            <div class="card-body text-center py-5">
                <div class="empty-state">
                    <h3 class="text-muted">No hay análisis registrados</h3>
                    <p class="text-muted">Esta muestra no tiene análisis asociados.</p>
                </div>
            </div>
        @else
            <div class="card-body p-0">
                <div class="p-2" id="analisisAccordion">
                    @foreach($analisis as $item)
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header" id="heading{{ $item->cotio_subitem }}">
                                <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse{{ $item->cotio_subitem }}" aria-expanded="false" 
                                    aria-controls="collapse{{ $item->cotio_subitem }}">
                                    <div class="d-flex justify-content-between w-100 pe-3">
                                        <div>
                                            <span class="badge bg-primary me-2">#{{ $item->cotio_subitem }}</span>
                                            {{ $item->cotio_descripcion }}
                                        </div>
                                    </div>
                                </button>
                            </h2>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="modal fade" id="editMuestraModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Muestra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form method="POST" action="{{ route('asignar.identificacion-muestra') }}" id="muestraForm" enctype="multipart/form-data" data-req-cadena-custodia="{{ $reqCadenaCustodiaMuestra ? '1' : '0' }}">
                        @csrf
                        <input type="hidden" name="cotio_numcoti" value="{{ $instancia->cotio_numcoti }}">
                        <input type="hidden" name="cotio_item" value="{{ $instancia->cotio_item }}">
                        <input type="hidden" name="instance_number" value="{{ $instanceNumber }}">
                        
                        <div class="mb-3">
                            <label for="cotio_identificacion" class="form-label">Identificación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cotio_identificacion" 
                                   name="cotio_identificacion" value="{{ $instancia->cotio_identificacion ?? '' }}"
                                   placeholder="Ingrese la identificación de la muestra" required>
                        </div>
                    
                        <div class="mb-3">
                            <label class="form-label">Imagen de la Muestra</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-primary" id="captureBtn">
                                    <i class="fas fa-camera me-1"></i> Tomar Foto
                                </button>
                                <button type="button" class="btn btn-secondary" id="selectBtn">
                                    <i class="fas fa-image me-1"></i> Seleccionar de Galería
                                </button>
                            </div>
                            <input type="hidden" name="image_base64" id="image_base64">
                            <input type="file" class="d-none" id="imageInput" accept="image/*" capture="environment">
                            <input type="file" class="d-none" id="galleryInput" accept="image/*">
                            
                            <div id="imagePreview" class="mt-2">
                                @if($instancia->image)
                                    <img src="{{ asset('storage/images/'.$instancia->image) }}" width="100" class="img-thumbnail">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">Eliminar imagen actual</label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    
                        <div class="mb-3">
                            <label class="form-label">Georeferencia (coordenadas) <span class="text-danger">*</span></label>
                            <div id="map" style="height: 300px; width: 100%;"></div>
                            <input type="hidden" id="latitud" name="latitud" value="{{ $instancia->latitud ?? '' }}">
                            <input type="hidden" id="longitud" name="longitud" value="{{ $instancia->longitud ?? '' }}">
                            <div class="input-group mt-2">
                                <span class="input-group-text">Latitud</span>
                                <input type="number" step="any" class="form-control" id="latitude-display" value="{{ $instancia->latitud ?? '' }}" required placeholder="Ej: -33.441953">
                                <span class="input-group-text">Longitud</span>
                                <input type="number" step="any" class="form-control" id="longitude-display" value="{{ $instancia->longitud ?? '' }}" required placeholder="Ej: -70.638523">
                            </div>
                            <small class="text-muted">Haz clic en el mapa para seleccionar la ubicación o edita los valores de latitud y longitud manualmente.</small>
                        </div>
                    
                        <div class="mb-3">
                            <label for="nro_precinto" class="form-label">N° Precinto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nro_precinto" 
                                   name="nro_precinto" value="{{ $instancia->nro_precinto ?? '' }}"
                                   placeholder="Ingrese el número de precinto" required>
                        </div>
                    
                        @if($reqCadenaCustodiaMuestra)
                            <div class="mb-3">
                                <label for="nro_cadena" class="form-label">N° Cadena <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nro_cadena"
                                       name="nro_cadena" value="{{ $instancia->nro_cadena ?? '' }}"
                                       placeholder="Ingrese el número de cadena" required>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="observaciones_muestreo_muestreador" class="form-label">Observaciones del muestreador</label>
                            <textarea class="form-control" id="observaciones_muestreo_muestreador" name="observaciones_muestreo_muestreador" rows="3"
                                      placeholder="Observaciones al identificar la muestra (visibles para el coordinador)">{{ trim($instancia->observaciones_muestreo_muestreador ?? '') }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-outline-secondary me-2" name="accion" value="borrador" formnovalidate>
                                <i class="fas fa-file-alt me-1"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if(!$esMuestreadoEstado)
    <!-- Modal para suspender muestra -->
    <div class="modal fade" id="suspenderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Suspender Muestra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de suspender esta muestra?</p>
                    <form id="suspenderForm" method="POST" action="{{ route('asignar.suspension-muestra', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'instance_number' => $instanceNumber
                    ]) }}">
                        @csrf
                        
                        <div class="mb-3">
                            <label for="observacion" class="form-label">Observación de suspensión</label>
                            <input type="text" class="form-control" id="observacion" 
                                   name="cotio_observaciones_suspension" 
                                   placeholder="Ingrese la razón de la suspensión" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" form="suspenderForm">Suspender</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Modal para editar herramientas -->
    <div class="modal fade" id="editHerramientasModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Herramientas de Muestreo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="herramientasForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Seleccionar Herramientas:</label>
                            <div class="row">
                                @if(isset($todasHerramientas))
                                    @foreach($todasHerramientas as $herramienta)
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       value="{{ $herramienta->id }}" 
                                                       id="herramienta_{{ $herramienta->id }}"
                                                       name="herramientas[]"
                                                       @if(isset($herramientasMuestra) && $herramientasMuestra->pluck('id')->contains($herramienta->id)) checked @endif>
                                                <label class="form-check-label" for="herramienta_{{ $herramienta->id }}">
                                                    <strong>{{ $herramienta->equipamiento }}</strong>
                                                    @if($herramienta->marca_modelo)
                                                        <br><small class="text-muted">{{ $herramienta->marca_modelo }}</small>
                                                    @endif
                                                    @if($herramienta->n_serie_lote)
                                                        <br><small class="text-muted">S/N: {{ $herramienta->n_serie_lote }}</small>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Selecciona las herramientas que necesitas para esta muestra. Los cambios se guardarán automáticamente.
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarHerramientas()">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>


</div>

<style>
    .empty-state {
        max-width: 500px;
        margin: 0 auto;
        padding: 2rem;
    }
    .empty-state i {
        opacity: 0.6;
    }

    @media (min-width: 768px) {
        .tarea-detalle-dato {
            background: transparent;
            border: none;
            padding: 0;
        }
        .tarea-detalle-dato-label {
            font-size: inherit;
            text-transform: none;
            letter-spacing: normal;
            color: inherit;
            font-weight: 700;
        }
        .tarea-detalle-dato-label::after {
            content: ':';
        }
        .tarea-detalle-dato-value {
            display: inline;
        }
        .tarea-detalle-dato + .tarea-detalle-dato {
            margin-top: 0.5rem !important;
        }
    }
</style>

<script>
async function enviarResultado(event, cotio_numcoti, cotio_item, cotio_subitem) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch(`/tareas/${cotio_numcoti}/${cotio_item}/${cotio_subitem}/resultado`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const responseData = await response.json();

        if (response.ok) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: responseData.message,
                confirmButtonColor: '#3085d6'
            }).then(() => {
                if (window.limpiarResiduosSweetAlert2) {
                    window.limpiarResiduosSweetAlert2();
                }
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: responseData.message || 'Hubo un error al procesar la solicitud',
                confirmButtonColor: '#3085d6'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al procesar la solicitud',
            confirmButtonColor: '#3085d6'
        });
    }
}
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('suspenderModal');
        if (!modal) {
            return;
        }
        const form = document.getElementById('suspensionForm') || document.getElementById('suspenderForm');
        const textarea = document.getElementById('cotio_observaciones_suspension') || document.getElementById('observacion');
        if (form && textarea) {
            form.addEventListener('submit', function(event) {
                if (!textarea.value.trim()) {
                    event.preventDefault();
                    event.stopPropagation();
                    textarea.classList.add('is-invalid');
                } else {
                    textarea.classList.remove('is-invalid');
                }

                form.classList.add('was-validated');
            });
        }

        modal.addEventListener('hidden.bs.modal', function() {
            if (form && textarea) {
                form.classList.remove('was-validated');
                textarea.classList.remove('is-invalid');
            }
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const captureBtn = document.getElementById('captureBtn');
        const selectBtn = document.getElementById('selectBtn');
        const imageInput = document.getElementById('imageInput');
        const galleryInput = document.getElementById('galleryInput');
        const imageBase64 = document.getElementById('image_base64');
        const imagePreview = document.getElementById('imagePreview');

        if (!captureBtn || !selectBtn || !imageInput || !galleryInput || !imageBase64 || !imagePreview) {
            return;
        }

        // Función para procesar la imagen
        function processImage(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = new Image();
                    img.onload = function() {
                        // Crear un canvas para redimensionar
                        const canvas = document.createElement('canvas');
                        const MAX_WIDTH = 800;
                        const MAX_HEIGHT = 800;
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        // Convertir a base64 con calidad reducida
                        const base64 = canvas.toDataURL('image/jpeg', 0.7);
                        resolve(base64);
                    };
                    img.onerror = reject;
                    img.src = e.target.result;
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        // Función para mostrar la vista previa
        function showPreview(base64) {
            imagePreview.innerHTML = `
                <img src="${base64}" class="img-thumbnail" style="max-height: 200px;">
            `;
        }

        // Manejar captura de foto
        captureBtn.addEventListener('click', () => {
            imageInput.click();
        });

        // Manejar selección de galería
        selectBtn.addEventListener('click', () => {
            galleryInput.click();
        });

        // Procesar imagen de la cámara
        imageInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                try {
                    const base64 = await processImage(file);
                    imageBase64.value = base64;
                    showPreview(base64);
                } catch (error) {
                    console.error('Error al procesar la imagen:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo procesar la imagen'
                    });
                }
            }
        });

        // Procesar imagen de la galería
        galleryInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (file) {
                try {
                    const base64 = await processImage(file);
                    imageBase64.value = base64;
                    showPreview(base64);
                } catch (error) {
                    console.error('Error al procesar la imagen:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo procesar la imagen'
                    });
                }
            }
        });
    });
</script>



<script>
    const form = document.querySelector('#editMuestraModal form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Sincronizar coordenadas de los inputs de solo lectura al hidden
                const latDisplay = document.getElementById('latitude-display');
                const lngDisplay = document.getElementById('longitude-display');
                const latHidden = document.getElementById('latitud');
                const lngHidden = document.getElementById('longitud');
                if (latDisplay && lngDisplay && latHidden && lngHidden) {
                    latHidden.value = latDisplay.value.trim();
                    lngHidden.value = lngDisplay.value.trim();
                }

                const esGuardarYEnviar = e.submitter && e.submitter.value === 'guardar';
                const reqCadenaCustodia = form.getAttribute('data-req-cadena-custodia') === '1';

                // Validar campos obligatorios solo si presionó "Guardar y Enviar"
                if (esGuardarYEnviar) {
                    const identificacion = (document.getElementById('cotio_identificacion') || {}).value || '';
                    const precinto = (document.getElementById('nro_precinto') || {}).value || '';
                    const cadenaEl = document.getElementById('nro_cadena');
                    const cadena = cadenaEl ? (cadenaEl.value || '') : '';
                    const lat = (document.getElementById('latitud') || {}).value || '';
                    const lng = (document.getElementById('longitud') || {}).value || '';

                    const faltantes = [];
                    if (!identificacion.trim()) faltantes.push('Identificación de muestra');
                    if (!precinto.trim()) faltantes.push('N° Precinto');
                    if (reqCadenaCustodia && !cadena.trim()) faltantes.push('N° Cadena');
                    if (!lat.trim() || !lng.trim()) faltantes.push('Coordenadas (latitud y longitud)');

                    if (faltantes.length > 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Campos obligatorios',
                            html: 'Debe completar: <strong>' + faltantes.join(', ') + '</strong>',
                            confirmButtonText: 'Entendido'
                        });
                        return;
                    }
                }
                
                // Mostrar loader mientras se procesa
                Swal.fire({
                    title: 'Procesando...',
                    html: 'Guardando los datos de la muestra',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(form);
                if (e.submitter && e.submitter.name) {
                    formData.append(e.submitter.name, e.submitter.value);
                }

                // Enviar el formulario manualmente
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    }
                })
                .then(response => {
                    if (response.redirected) {
                        return { redirected: true, url: response.url };
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.redirected) {
                        Swal.close();
                        if (window.limpiarResiduosSweetAlert2) {
                            window.limpiarResiduosSweetAlert2();
                        }
                        window.location.href = data.url;
                        return;
                    }

                    Swal.close();
                    if (window.limpiarResiduosSweetAlert2) {
                        window.limpiarResiduosSweetAlert2();
                    }

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.message || 'Datos guardados correctamente',
                            confirmButtonText: 'Aceptar',
                            didClose: function () {
                                if (window.limpiarResiduosSweetAlert2) {
                                    window.limpiarResiduosSweetAlert2();
                                }
                            },
                        }).then(() => {
                            const modalEl = document.getElementById('editMuestraModal');
                            if (modalEl) {
                                const inst = bootstrap.Modal.getInstance(modalEl);
                                if (inst) {
                                    inst.hide();
                                }
                            }
                            if (window.limpiarResiduosSweetAlert2) {
                                window.limpiarResiduosSweetAlert2();
                            }
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Ocurrió un error al guardar',
                            confirmButtonText: 'Entendido'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.close();
                    if (window.limpiarResiduosSweetAlert2) {
                        window.limpiarResiduosSweetAlert2();
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error en la conexión con el servidor',
                        confirmButtonText: 'Entendido'
                    });
                });
            });
    }

document.addEventListener('DOMContentLoaded', function() {
    let initialLat = -33.45694;
    let initialLng = -70.64827;
    let zoomLevel = 13;
    
    // Si hay coordenadas existentes, usarlas
    @if($instancia->latitud && $instancia->longitud)
        initialLat = parseFloat({{ $instancia->latitud }});
        initialLng = parseFloat({{ $instancia->longitud }});
        zoomLevel = 15;
    @endif
    
    // Inicializar el mapa
    const map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: initialLat, lng: initialLng },
        zoom: zoomLevel,
        streetViewControl: false,
        mapTypeControlOptions: {
            mapTypeIds: ['roadmap', 'satellite']
        }
    });
    
    let marker;
    
    // Función para actualizar los campos de entrada
    function updateInputs(latLng) {
        const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
        const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
        
        document.getElementById('latitud').value = lat;
        document.getElementById('longitud').value = lng;
        document.getElementById('latitude-display').value = lat.toFixed(6);
        document.getElementById('longitude-display').value = lng.toFixed(6);
    }
    
    // Función para actualizar la posición del marcador
    function updateMarkerPosition(lat, lng) {
        const newPos = { lat: parseFloat(lat), lng: parseFloat(lng) };
        if (isNaN(newPos.lat) || isNaN(newPos.lng)) {
            Swal.fire({
                icon: 'error',
                title: 'Coordenadas inválidas',
                text: 'Por favor, ingresa valores válidos para latitud y longitud.'
            });
            return false;
        }
        if (marker) {
            marker.setPosition(newPos);
        } else {
            marker = new google.maps.Marker({
                position: newPos,
                map: map,
                draggable: true,
                icon: {
                    url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            // Manejador de arrastre del marcador
            google.maps.event.addListener(marker, 'dragend', function() {
                updateInputs(marker.getPosition());
            });
        }
        map.setCenter(newPos);
        return true;
    }
    
    // Si hay coordenadas iniciales, colocar marcador
    @if($instancia->latitud && $instancia->longitud)
        marker = new google.maps.Marker({
            position: { lat: initialLat, lng: initialLng },
            map: map,
            draggable: true,
            icon: {
                url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
                scaledSize: new google.maps.Size(32, 32)
            }
        });
        updateInputs({ lat: initialLat, lng: initialLng });
        
        // Manejador de arrastre del marcador
        google.maps.event.addListener(marker, 'dragend', function() {
            updateInputs(marker.getPosition());
        });
    @endif
    
    // Manejador de clics en el mapa
    map.addListener('click', function(e) {
        if (marker) {
            marker.setPosition(e.latLng);
        } else {
            marker = new google.maps.Marker({
                position: e.latLng,
                map: map,
                draggable: true,
                icon: {
                    url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
                    scaledSize: new google.maps.Size(32, 32)
                }
            });
            
            // Manejador de arrastre del marcador
            google.maps.event.addListener(marker, 'dragend', function() {
                updateInputs(marker.getPosition());
            });
        }
        updateInputs(e.latLng);
    });
    
    // Manejador de cambios en los inputs de latitud y longitud
    document.getElementById('latitude-display').addEventListener('change', function() {
        const lat = this.value;
        const lng = document.getElementById('longitude-display').value;
        if (updateMarkerPosition(lat, lng)) {
            updateInputs({ lat: parseFloat(lat), lng: parseFloat(lng) });
        }
    });
    
    document.getElementById('longitude-display').addEventListener('change', function() {
        const lat = document.getElementById('latitude-display').value;
        const lng = this.value;
        if (updateMarkerPosition(lat, lng)) {
            updateInputs({ lat: parseFloat(lat), lng: parseFloat(lng) });
        }
    });
    
    // Geolocalización con SweetAlert
    if (navigator.geolocation) {
        const locateBtn = document.createElement('button');
        locateBtn.textContent = 'Usar mi ubicación actual';
        locateBtn.className = 'btn btn-sm btn-info mb-2';
        locateBtn.type = 'button';
        locateBtn.onclick = function() {
            Swal.fire({
                title: 'Obteniendo ubicación...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    Swal.close();
                    const pos = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    if (marker) {
                        marker.setPosition(pos);
                    } else {
                        marker = new google.maps.Marker({
                            position: pos,
                            map: map,
                            draggable: true,
                            icon: {
                                url: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
                                scaledSize: new google.maps.Size(32, 32)
                            }
                        });
                        
                        // Manejador de arrastre del marcador
                        google.maps.event.addListener(marker, 'dragend', function() {
                            updateInputs(marker.getPosition());
                        });
                    }
                    map.setCenter(pos);
                    map.setZoom(15);
                    updateInputs(pos);
                }, 
                function(error) {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de geolocalización',
                        text: getGeoLocationError(error),
                        confirmButtonText: 'Entendido'
                    });
                }
            );
        };
        document.querySelector('#map').parentNode.insertBefore(locateBtn, document.querySelector('#map'));
    }
    
    // Función para mensajes de error de geolocalización
    function getGeoLocationError(error) {
        switch(error.code) {
            case error.PERMISSION_DENIED:
                return "Se denegó el permiso para obtener la ubicación.";
            case error.POSITION_UNAVAILABLE:
                return "La información de ubicación no está disponible.";
            case error.TIMEOUT:
                return "La solicitud de ubicación tardó demasiado tiempo.";
            case error.UNKNOWN_ERROR:
                return "Ocurrió un error desconocido.";
            default:
                return "Error al obtener la ubicación.";
        }
    }

    // Manejar el envío del formulario (validación y envío ya están en el primer listener de #editMuestraModal form)
    // No requerimos imagen obligatoria; coordenadas, identificación, precinto y cadena se validan en el otro handler.
});

document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('observaciones_muestreador');
    const textarea2 = document.getElementById('observaciones_medicion_muestreador');
    if (textarea) {
        textarea.value = textarea.value.trim();
    }
    if (textarea2) {
        textarea2.value = textarea2.value.trim();
    }
});

</script>

<script>
// Función para guardar herramientas
function guardarHerramientas() {
    const form = document.getElementById('herramientasForm');
    const formData = new FormData(form);
    
    // Obtener herramientas seleccionadas
    const herramientasSeleccionadas = [];
    const checkboxes = form.querySelectorAll('input[name="herramientas[]"]:checked');
    checkboxes.forEach(checkbox => {
        herramientasSeleccionadas.push(parseInt(checkbox.value));
    });
    
    // Mostrar loading
    Swal.fire({
        title: 'Guardando herramientas...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Enviar petición
    fetch(`/tareas/{{ $instancia->cotio_numcoti }}/{{ $instancia->cotio_item }}/{{ $instancia->cotio_subitem }}/{{ $instanceNumber }}/herramientas`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            herramientas: herramientasSeleccionadas
        })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
                confirmButtonText: 'Aceptar',
                didClose: function () {
                    if (window.limpiarResiduosSweetAlert2) {
                        window.limpiarResiduosSweetAlert2();
                    }
                },
            }).then(() => {
                const modalEl = document.getElementById('editHerramientasModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    }
                }
                if (window.limpiarResiduosSweetAlert2) {
                    window.limpiarResiduosSweetAlert2();
                }
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Ocurrió un error al guardar las herramientas',
                confirmButtonText: 'Entendido'
            });
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor',
            confirmButtonText: 'Entendido'
        });
    });
}

// Función para actualizar la vista de herramientas sin recargar la página (opcional)
function actualizarVistaHerramientas(herramientas) {
    const container = document.getElementById('herramientasContainer');
    const noHerramientasMsg = document.getElementById('noHerramientasMsg');
    
    if (herramientas.length === 0) {
        if (container) container.style.display = 'none';
        if (noHerramientasMsg) noHerramientasMsg.style.display = 'block';
    } else {
        if (noHerramientasMsg) noHerramientasMsg.style.display = 'none';
        if (container) {
            container.innerHTML = '';
            herramientas.forEach(herramienta => {
                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.innerHTML = `
                    <div class="card border h-100">
                        <div class="card-body p-2">
                            <h6 class="mb-1">${herramienta.equipamiento}</h6>
                            <p class="small mb-0 text-muted">${herramienta.marca_modelo || ''}</p>
                            ${herramienta.cantidad > 1 ? `<span class="badge bg-secondary">Cantidad: ${herramienta.cantidad}</span>` : ''}
                        </div>
                    </div>
                `;
                container.appendChild(col);
            });
            container.style.display = 'flex';
        }
    }
}

// Event listener para botones de seleccionar/deseleccionar todo (opcional)
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editHerramientasModal');
    if (modal) {
        // Agregar botones de seleccionar todo / deseleccionar todo
        const modalBody = modal.querySelector('.modal-body');
        const buttonsDiv = document.createElement('div');
        buttonsDiv.className = 'mb-3 d-flex gap-2';
        buttonsDiv.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-success" onclick="seleccionarTodas()">
                <i class="fas fa-check-square me-1"></i> Seleccionar Todas
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning" onclick="deseleccionarTodas()">
                <i class="fas fa-square me-1"></i> Deseleccionar Todas
            </button>
        `;
        modalBody.insertBefore(buttonsDiv, modalBody.querySelector('form'));
    }
});

function seleccionarTodas() {
    const checkboxes = document.querySelectorAll('#herramientasForm input[name="herramientas[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deseleccionarTodas() {
    const checkboxes = document.querySelectorAll('#herramientasForm input[name="herramientas[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}
</script>

<script>
    function confirmarMedicionesCampoSiCorresponde() {
        return new Promise((resolve) => {
            const inputs = document.querySelectorAll('input[name^="valores["][type="text"]');
            const totalInputs = inputs.length;
            let filledCount = 0;
            let emptyInputs = 0;

            inputs.forEach(input => {
                if (input.value.trim() === '') {
                    emptyInputs++;
                } else {
                    filledCount++;
                }
            });

            if (totalInputs > 0 && filledCount === 0) {
                Swal.fire({
                    title: 'Mediciones de campo obligatorias',
                    text: 'Debe ingresar al menos un valor en las variables de medición de campo.',
                    icon: 'warning',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'Entendido'
                }).then(() => resolve(false));
                return;
            }

            if (emptyInputs > 0) {
                Swal.fire({
                    title: 'Advertencia',
                    text: 'Al menos una variable está vacía, ¿Deseas continuar?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar',
                    cancelButtonText: 'No, cancelar',
                    buttonsStyling: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33'
                }).then((result) => resolve(result.isConfirmed));
                return;
            }

            resolve(true);
        });
    }

    function sincronizarCoordenadasMuestraForm() {
        const latDisplay = document.getElementById('latitude-display');
        const lngDisplay = document.getElementById('longitude-display');
        const latHidden = document.getElementById('latitud');
        const lngHidden = document.getElementById('longitud');
        if (latDisplay && lngDisplay && latHidden && lngHidden) {
            latHidden.value = latDisplay.value.trim();
            lngHidden.value = lngDisplay.value.trim();
        }
    }

    const medicionesForm = document.getElementById('medicionesForm');
    if (medicionesForm) {
        medicionesForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const continuar = await confirmarMedicionesCampoSiCorresponde();
            if (continuar) {
                medicionesForm.submit();
            }
        });
    }
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botonGlobal = document.getElementById('guardarIdentificacionYMediciones');
    const botonGlobalMobile = document.getElementById('guardarIdentificacionYMedicionesMobile');

    async function guardarIdentificacionYMedicionesHandler() {
        const muestraForm = document.getElementById('muestraForm');
        const medicionesFormEl = document.getElementById('medicionesForm');

        if (!muestraForm || !medicionesFormEl) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se encontraron los formularios de identificación o mediciones.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const identInput = document.getElementById('cotio_identificacion');
        if (identInput && !identInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo obligatorio',
                text: 'Debe completar la identificación de la muestra antes de guardar.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            }).then(() => identInput.focus());
            return;
        }

        const reqCadenaCustodia = muestraForm.getAttribute('data-req-cadena-custodia') === '1';
        const cadenaInput = document.getElementById('nro_cadena');
        if (reqCadenaCustodia && cadenaInput && !cadenaInput.value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Campo obligatorio',
                text: 'Debe completar el N° Cadena de custodia antes de guardar.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            }).then(() => cadenaInput.focus());
            return;
        }

        const medicionesOk = await confirmarMedicionesCampoSiCorresponde();
        if (!medicionesOk) {
            return;
        }

        Swal.fire({
            title: 'Procesando...',
            html: 'Guardando identificación y mediciones',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            sincronizarCoordenadasMuestraForm();

            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const fdIdent = new FormData(muestraForm);
            fdIdent.append('accion', 'guardar');

            const respIdent = await fetch(muestraForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: fdIdent
            });

            let dataIdent;
            try {
                dataIdent = await respIdent.json();
            } catch (parseError) {
                dataIdent = { success: respIdent.ok };
            }

            if (!respIdent.ok || dataIdent.success === false) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: dataIdent.message || 'Error al guardar la identificación de la muestra.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            const fdMed = new FormData(medicionesFormEl);
            fdMed.append('avanzar_estado', '1');
            const respMed = await fetch(medicionesFormEl.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'text/html,application/json'
                },
                body: fdMed
            });

            if (!respMed.ok) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error al guardar las mediciones de campo.',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6'
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: 'Identificación y mediciones guardadas correctamente',
                confirmButtonText: 'Aceptar',
                confirmButtonColor: '#3085d6',
                didClose: function () {
                    if (window.limpiarResiduosSweetAlert2) {
                        window.limpiarResiduosSweetAlert2();
                    }
                },
            }).then(() => {
                if (window.limpiarResiduosSweetAlert2) {
                    window.limpiarResiduosSweetAlert2();
                }
                window.location.reload();
            });
        } catch (e) {
            console.error(e);
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al guardar identificación y mediciones.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6'
            });
        }
    }

    if (botonGlobal) {
        botonGlobal.addEventListener('click', guardarIdentificacionYMedicionesHandler);
    }
    if (botonGlobalMobile) {
        botonGlobalMobile.addEventListener('click', guardarIdentificacionYMedicionesHandler);
    }
});
</script>

@endsection











