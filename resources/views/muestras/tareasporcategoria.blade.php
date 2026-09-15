@extends('layouts.app')
<head>
    <title>Cotización {{$cotizacion->coti_num}} | {{$categoria->cotio_descripcion}} - Muestra {{$instance}}</title>
</head>

{{-- @dd($instanciaActual) --}}


@section('content')
@php
    $normalizeStr = function($str) {
        if (empty($str)) return '';
        $str = mb_strtolower($str, 'UTF-8');
        // Quitar paréntesis y su contenido
        $str = preg_replace('/\s*\(.*\)\s*/u', '', $str);
        // Quitar acentos
        $search  = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'u'];
        $str = str_replace($search, $replace, $str);
        // Quitar cualquier caracter que no sea letra o número para máxima compatibilidad
        $str = preg_replace('/[^a-z0-9]/u', '', $str);
        return trim($str);
    };

    $medicionesMapa = collect();
    if ($instanciaActual && $instanciaActual->valoresVariables) {
        $medicionesMapa = $instanciaActual->valoresVariables->mapWithKeys(function ($v) use ($normalizeStr) {
            return [$normalizeStr($v->variable) => $v->valor];
        });
    }

    $analisisNormSet = collect($tareas ?? [])->map(function ($a) use ($normalizeStr) {
        return $normalizeStr($a->cotio_descripcion);
    })->filter()->unique()->values()->toArray();

    $cotizacion->loadMissing('matriz');
    $esEnsayoMediciones = \App\Support\CotizacionCanalEnsayo::resolverCanalEnsayo(
        $categoria,
        optional($cotizacion->matriz)->matriz_descripcion
    ) === 'mediciones';
    $esVistaMediciones = ($canalParaFiltrar ?? '') === 'mediciones' || !empty($esGestionMediciones);
    $bloquearCambioEstadoMuestreo = $instanciaActual
        && \App\Support\CotizacionCanalEnsayo::instanciaMedicionesEnDocumentacion($instanciaActual);
    $canalDetalleMuestra = $canalParaFiltrar
        ?: \App\Support\CotizacionCanalEnsayo::resolverCanalEnsayo(
            $categoria,
            optional($cotizacion->matriz)->matriz_descripcion
        );
    $mostrarBotonDeshabilitarOt = ! \App\Support\CotizacionCanalEnsayo::esCanalFacturacionDirecta($canalDetalleMuestra);
    $esTrabajoTecnicoCampo = \App\Support\TrabajoTecnicoCampo::esDescripcion(
        $categoria->cotio_descripcion ?? ($instanciaActual->cotio_descripcion ?? null)
    );
    $listoFacturacionTrabajoTecnico = $esTrabajoTecnicoCampo
        && $instanciaActual
        && \App\Support\TrabajoTecnicoCampo::estadoEsMuestreado($instanciaActual->cotio_estado ?? null);
    if ($esTrabajoTecnicoCampo) {
        $mostrarBotonDeshabilitarOt = false;
    }
@endphp
<div class="container py-4">
    <div class="d-flex flex-column gap-2 flex-md-row justify-content-between align-items-center mb-4">
        <a href="{{ $esVistaMediciones ? route('mediciones.show', $cotizacion->coti_num) : route('muestras.show', ['coti_num' => $cotizacion->coti_num, 'canal' => $canalParaFiltrar ?? null]) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            Volver a la cotización
        </a>
        <div class="d-flex flex-column flex-md-row gap-2">
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="instanceDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Muestra {{$instance}} de {{$categoria->cotio_cantidad}}
                </button>
                <ul class="dropdown-menu" aria-labelledby="instanceDropdown">
                    @for($i = 1; $i <= $categoria->cotio_cantidad; $i++)
                        <li>
                            <a class="dropdown-item {{$i == $instance ? 'active' : ''}}" 
                               href="{{ $esVistaMediciones ? route('mediciones.ver', [
                                   'cotizacion' => $cotizacion->coti_num,
                                   'item' => $categoria->cotio_item,
                                   'instance' => $i,
                               ]) : route('muestras.ver', [
                                   'cotizacion' => $cotizacion->coti_num,
                                   'item' => $categoria->cotio_item,
                                   'instance' => $i,
                                   'canal' => $canalParaFiltrar ?? null
                               ]) }}">
                                Muestra {{$i}}
                                @if(optional($instanciasMuestra[$i] ?? null)->fecha_muestreo)
                                    <small class="text-muted">(Muestreada)</small>
                                @endif
                            </a>
                        </li>
                    @endfor
                </ul>
            </div>
        </div>
    </div>

    @include('cotizaciones.info')

    
    @if(!$instanciaActual)
            <div class="alert alert-info mb-3">
                <strong>Muestra {{ $instance }} de {{ $categoria->cotio_cantidad }}</strong>:
                esta instancia aún no fue creada o no está habilitada para muestreo.
                @if($instance > 1)
                    <span class="d-block mt-2 small">Podés abrir otra muestra del menú superior o coordinarla desde el detalle de la cotización.</span>
                @endif
            </div>
        @else
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                    <strong>Muestra {{$instance}} de {{$categoria->cotio_cantidad}}</strong>
                    @if($instanciaActual->fecha_muestreo)
                        - Coordinada el {{ $instanciaActual->fecha_muestreo->format('d/m/Y H:i') }}
                        @if($instanciaActual->coordinador)
                            por {{ $instanciaActual->coordinador->usu_descripcion }}
                        @endif
                    @endif
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="fw-bold">{{ $categoria->cotio_descripcion }} ({{ $instanciaActual->instance_number ?? ''}} / {{ $categoria->cotio_cantidad ?? ''}})</h2>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-primary"
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode(\App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($cotizacion)) }}">
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
                                $estado = strtolower($instanciaActual->cotio_estado ?? $categoria->cotio_estado);
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
                                    'completado' => 'success',
                                    'suspension' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $badgeClass }}">{{ ucfirst($instanciaActual->cotio_estado ?? $categoria->cotio_estado) }}</span>
                            @if($instanciaActual->enable_ot == false && ! $bloquearCambioEstadoMuestreo)
                                <button type="button" class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#estadoModal" data-tipo="categoria">
                                    <x-heroicon-o-pencil style="width: 20px; height: 20px;" />
                                </button>
                            @endif
                        </p>
                        <p class="text-muted mb-1">
                            <strong>Asignada a:</strong> 
                            @if ($instanciaActual->responsablesMuestreo->count() > 0)
                                @foreach ($instanciaActual->responsablesMuestreo as $responsable)
                                    <span class="badge bg-info d-inline-flex align-items-center me-2 mb-1">
                                        {{ $responsable->usu_descripcion }}

                                    @if($instanciaActual->enable_ot == false)
                                        <button type="button" 
                                            class="btn btn-sm btn-link text-danger p-0 ms-1" 
                                            style="font-size: 0.75rem; line-height: 1;"
                                            onclick="quitarResponsableMuestreo('{{ $responsable->usu_codigo }}')"
                                            title="Quitar responsable de muestreo">
                                            <x-heroicon-o-x-mark style="width: 12px; height: 12px;" />
                                        </button>
                                    @endif
                            
                                </span>
                                @endforeach
                            @else
                                <span class="badge bg-secondary">Sin asignar</span>
                            @endif
                            
                            @if($instanciaActual->enable_ot == false && $instanciaActual->cotio_estado != 'muestreado')
                                <button type="button" 
                                        class="btn btn-sm btn-outline-primary ms-2"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#gestionarResponsablesMuestreoModal"
                                        data-instancia-id="{{ $instanciaActual->id }}"
                                        title="Gestionar responsables de muestreo">
                                    <x-heroicon-o-user-plus style="width: 0.875rem; height: 0.875rem;" />
                                    <span class="d-none d-md-inline ms-1">Gestionar</span>
                                </button>
                            @endif
                            @if(Auth::user()->rol == 'coordinador_muestreo' && ! $bloquearCambioEstadoMuestreo)
                                @if($instanciaActual->cotio_estado != 'suspension')
                                    <button type="button" class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" data-bs-target="#suspenderModal">
                                        <i class="fas fa-pause me-1"></i> Suspender
                                    </button>
                                @endif

                                @if(in_array($instanciaActual->cotio_estado, ['coordinado muestreo', 'suspension']) && $instanciaActual->enable_ot == false)
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-primary ms-2"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#recoordinarModal"
                                            data-instancia="{{ $instanciaActual->id }}"
                                            data-cotizacion="{{ $cotizacion->coti_num }}"
                                            data-item="{{ $instanciaActual->cotio_item }}"
                                            data-instance="{{ $instanciaActual->instance_number }}">
                                        Recoordinar
                                    </button>
                                @endif
                            @endif
                        </p>

                        @if($instanciaActual->enable_ot == true)
                            <p class="text-muted mb-1">
                                <strong>Es Prioridad:</strong> 
                                <span class="badge bg-primary">{{ $instanciaActual->es_priori ? 'Sí' : 'No' }}</span>
                            </p>
                        @endif


                        
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">
                            <strong>Vehículo:</strong> 
                            @if ($instanciaActual->vehiculo_asignado)
                                {{ $instanciaActual->vehiculo->marca ?? 'N/A' }} {{ $instanciaActual->vehiculo->modelo ?? 'N/A' }} ({{ $instanciaActual->vehiculo->patente ?? 'N/A' }})
                            @else
                                Sin asignar
                            @endif
                        </p>
                        <p class="text-muted mb-1">
                            <strong>Frecuencia:</strong> 
                            @if ($instanciaActual->es_frecuente)
                                Frecuencia asignada
                            @else
                                Puntual
                            @endif
                        </p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-12">
                        <p class="text-muted mb-1 fecha-wrapper" data-fecha-fin="{{ $instanciaActual->fecha_fin_muestreo ?? $categoria->fecha_fin ?? '' }}">
                            <strong>Inicio:</strong> 
                            <span class="{{ $instanciaActual->fecha_inicio_muestreo ?? $categoria->fecha_inicio ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                {{ $instanciaActual->fecha_inicio_muestreo ?? $categoria->fecha_inicio ?? 'Faltante' }}
                            </span>
                            &nbsp;&nbsp;|&nbsp;&nbsp;
                            <strong>Fin:</strong> 
                            <span class="fecha-fin {{ $instanciaActual->fecha_fin_muestreo ?? $categoria->fecha_fin ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                {{ $instanciaActual->fecha_fin_muestreo ?? $categoria->fecha_fin ?? 'Faltante' }}
                            </span>
                        </p>
                    </div>
                </div>

                {{-- Nota interna del ensayo --}}
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


                {{-- herramientas asignadas --}}
                @if(isset($herramientasMuestra) && $herramientasMuestra->count())
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0 p-2">Herramientas de Muestreo</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group">
                                @foreach ($herramientasMuestra as $herramienta)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        {{ $herramienta->equipamiento }}
                                        @if($herramienta->marca_modelo)
                                            <small class="text-muted">({{ $herramienta->marca_modelo }})</small>
                                        @endif
                                        @if($herramienta->pivot_observaciones)
                                            <small class="text-muted">{{ $herramienta->pivot_observaciones }}</small>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                {{-- Datos de la muestra: identificación, coordenadas, precinto, cadena de custodia, foto --}}
                @if($instanciaActual)
                @php
                    $esCoordOAdminMuestreo = Auth::user()->hasRole('coordinador_muestreo') || (int) (Auth::user()->usu_nivel ?? 0) >= 900;
                    $puedeEditarDatosMuestraCoord = $esCoordOAdminMuestreo && $instanciaActual->cotio_estado !== 'muestreado';
                    $reqCadenaCustodiaMuestraCategoria = (bool) ($categoria->req_cadena_custodia ?? false);
                @endphp
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="card-title mb-0 p-2">Datos de la Muestra</h5>
                        @if($puedeEditarDatosMuestraCoord)
                            <button type="button" class="btn btn-sm btn-primary me-2 mb-2" data-bs-toggle="modal" data-bs-target="#modalCoordDatosMuestra">
                                <i class="fas fa-edit me-1"></i> Editar identificación y datos de campo
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-7">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4 text-muted">Identificación</dt>
                                    <dd class="col-sm-8">{{ $instanciaActual->cotio_identificacion ?? '—' }}</dd>

                                    @if($instanciaActual->latitud && $instanciaActual->longitud)
                                    <dt class="col-sm-4 text-muted">Coordenadas</dt>
                                    <dd class="col-sm-8">
                                        {{ number_format($instanciaActual->latitud, 6) }}, {{ number_format($instanciaActual->longitud, 6) }}
                                        <a class="btn btn-sm btn-outline-primary ms-2" href="https://www.google.com/maps/search/?api=1&query={{ $instanciaActual->latitud }},{{ $instanciaActual->longitud }}" target="_blank" rel="noopener">
                                            <x-heroicon-o-map-pin style="width: 14px; height: 14px;" /> Ver en mapa
                                        </a>
                                    </dd>
                                    @endif

                                    <dt class="col-sm-4 text-muted">Precinto</dt>
                                    <dd class="col-sm-8">{{ $instanciaActual->nro_precinto ?? '—' }}</dd>

                                    <dt class="col-sm-4 text-muted">Cadena de custodia</dt>
                                    <dd class="col-sm-8">{{ $instanciaActual->nro_cadena ?? '—' }}</dd>

                                    @if($instanciaActual->fecha_identificacion)
                                    <dt class="col-sm-4 text-muted">Fecha identificación</dt>
                                    <dd class="col-sm-8">{{ \Carbon\Carbon::parse($instanciaActual->fecha_identificacion)->format('d/m/Y H:i') }}</dd>
                                    @endif

                                    @if(filled($instanciaActual->observaciones_muestreo_coord))
                                    <dt class="col-sm-4 text-muted">Obs. coordinador</dt>
                                    <dd class="col-sm-8" style="white-space: pre-wrap;">{{ trim($instanciaActual->observaciones_muestreo_coord) }}</dd>
                                    @endif

                                    @if(filled($instanciaActual->observaciones_muestreo_muestreador))
                                    <dt class="col-sm-4 text-muted">Obs. muestreador</dt>
                                    <dd class="col-sm-8" style="white-space: pre-wrap;">{{ trim($instanciaActual->observaciones_muestreo_muestreador) }}</dd>
                                    @endif
                                </dl>
                            </div>
                            @if($instanciaActual->image)
                            <div class="col-md-6 col-lg-5 text-center mt-3 mt-md-0">
                                <p class="text-muted small mb-2">Foto de la muestra</p>
                                <a href="{{ Storage::url('images/' . $instanciaActual->image) }}" target="_blank" rel="noopener" class="d-inline-block">
                                    <img src="{{ Storage::url('images/' . $instanciaActual->image) }}" alt="Foto de la muestra" class="img-fluid rounded shadow-sm" style="max-height: 220px; object-fit: contain;">
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                @if($instanciaActual && $variablesMuestra->isNotEmpty())
                <div class="card shadow-sm my-5">
                    <div class="card-header bg-secondary text-white">
                        <h5 style="cursor: pointer; color: black; padding: 10px;" data-bs-toggle="collapse" data-bs-target="#variablesCollapse" aria-expanded="false" aria-controls="variablesCollapse">
                            Variables de Muestra y Observaciones
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
                                            <th style="width: 100px; text-align: center; white-space: nowrap;">Historial de Cambios</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($variablesMuestra as $variable)
                                            @php
                                                $isUsed = in_array($normalizeStr($variable->variable), $analisisNormSet);
                                            @endphp
                                            <tr @if($isUsed) class="table-info" title="Este valor se utiliza en los resultados de análisis" @endif>
                                                <td @if($isUsed) class="fw-bold" @endif>
                                                    {{ $variable->variable }}
                                                    @if($isUsed)
                                                        <i class="fas fa-check-circle text-info ms-1" style="font-size: 0.8rem;"></i>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control variable-value @if($isUsed) fw-bold text-info @endif" 
                                                           value="{{ $variable->valor }}" 
                                                           data-id="{{ $variable->id }}"
                                                           @if($instanciaActual->cotio_estado == 'muestreado') readonly @endif>
                                                </td>
                                                <td style="display: flex; justify-content: center; align-items: center;">
                                                    @if(isset($historialCambios[$variable->id]))
                                                        <button class="btn btn-sm btn-link btn-historial" 
                                                                data-variable-id="{{ $variable->id }}" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#historialModal">
                                                            <x-heroicon-o-clock style="width: 20px; height: 20px;" />
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <label for="observaciones" class="form-label"><strong>Tu observación:</strong></label>
                                <textarea class="form-control" id="observaciones" rows="3" 
                                          @if($instanciaActual->cotio_estado == 'muestreado') readonly @endif>{{ trim($instanciaActual->observaciones_medicion_coord_muestreo) }}</textarea>
                            </div>

                            <div class="mt-4">
                                <label for="observaciones_muestreador" class="form-label"><strong>Observaciones del Muestreador:</strong></label>
                                <textarea class="form-control" id="observaciones_muestreador" rows="3" readonly
                                          style="background-color: #fff8e1; border-left: 4px solid #ffc107; padding-left: 12px;">
                                    {{ trim($instanciaActual->observaciones_medicion_muestreador) }}
                                </textarea>
                            </div>
                            
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

                @if($instanciaActual)
                <div class="card shadow-sm my-4">
                    <div class="card-header bg-light py-3 px-3">
                        <h5 class="mb-0 ps-1">Archivos adjuntos</h5>
                        <small class="text-muted ps-1">PDF o imágenes de la cotización y de la revisión de muestreo</small>
                    </div>
                    <div class="card-body p-3">
                        @include('muestras.partials.adjuntos-revision-coord')
                    </div>
                </div>
                @endif

                @if($instanciaActual->cotio_estado == 'suspension')
                    <div class="alert alert-danger">
                        <strong>Motivos de suspensión:</strong> {{ $instanciaActual->cotio_observaciones_suspension ?? 'N/A' }}
                    </div>
                @endif

                {{-- añadir boton para 'habilit en otro analisis' solo si la instancia actual y los analisis tienen un estado 'finalizado' --}}
                @if($instanciaActual->cotio_estado == 'finalizado' || ($instanciaActual->cotio_estado == 'muestreado' && $instanciaActual->enable_ot == false))
                    @if($listoFacturacionTrabajoTecnico)
                        <div class="alert alert-success mt-2 mb-0">
                            <i class="fas fa-file-invoice-dollar me-1"></i>
                            <strong>Trabajo técnico en campo finalizado.</strong>
                            Este ítem está disponible para facturar en el módulo de <strong>Facturación</strong>
                            @if($instanciaActual->enable_inform && $instanciaActual->aprobado_informe)
                                <span class="badge bg-light text-success ms-1">Habilitado</span>
                            @else
                                <span class="badge bg-warning text-dark ms-1">Pendiente de habilitación</span>
                            @endif
                        </div>
                    @elseif($esEnsayoMediciones)
                        @if(($instanciaActual->enable_modulo_mediciones ?? false) && empty($esGestionMediciones))
                            <div class="alert alert-info mt-2 mb-0">
                                Esta muestra ya está en el módulo de documentación.
                                @if((int) (Auth::user()->usu_nivel ?? 0) >= 900 || userHasRole('coordinador_mediciones'))
                                    <a href="{{ route('mediciones.ver', ['cotizacion' => $categoria->cotio_numcoti, 'item' => $categoria->cotio_item, 'instance' => $instance]) }}" class="alert-link">Ir a gestionar informe</a>
                                @endif
                            </div>
                        @elseif(! ($instanciaActual->enable_modulo_mediciones ?? false))
                        <form action="{{ route('muestras.pasar-a-mediciones') }}" method="POST" class="mt-2 js-swal-confirm-form"
                              data-swal-title="Pasar a documentación"
                              data-swal-text="¿Enviar esta muestra al módulo de documentación?">
                            @csrf
                            <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                            <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                            <input type="hidden" name="instance_number" value="{{ $instance }}">
                            <button type="submit" class="btn btn-primary mt-2">
                                <i class="fas fa-folder-open me-1"></i> Pasar a documentación
                            </button>
                        </form>
                        @endif
                    @else
                        <form action="{{ route('categorias.enable-ot', [
                            'cotio_numcoti' => $categoria->cotio_numcoti,
                            'cotio_item' => $categoria->cotio_item,
                            'cotio_subitem' => $categoria->cotio_subitem,
                            'instance' => $instance
                        ]) }}" method="POST" class="js-swal-confirm-form"
                              data-swal-title="Pasar a Laboratorio"
                              data-swal-text="¿Enviar esta muestra a laboratorio?">
                            @csrf
                            <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                            <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                            <input type="hidden" name="cotio_subitem" value="{{ $categoria->cotio_subitem }}">
                            <input type="hidden" name="instance" value="{{ $instance }}">
                            <div class="p-2">
                                <label for="es_priori" class="form-label">Es Prioridad?</label>
                                <input type="checkbox" name="es_priori" id="es_priori" value="1" {{ $instanciaActual->es_priori ? 'checked' : '' }}>
                            </div>
                            <button class="btn btn-success mt-2">Pasar a Laboratorio</button>
                        </form>
                    @endif
                @endif

                @if(!empty($esGestionMediciones) && $instanciaActual)
                @php
                    $informePdfMediciones = trim((string) ($instanciaActual->archivo_informe ?? ''));
                    $tieneInformePdfMediciones = $informePdfMediciones !== '';
                @endphp
                <div class="card border-primary mt-3">
                    <div class="card-header bg-primary text-white">
                        <strong>Informe de medición</strong>
                    </div>
                    <div class="card-body">
                        @if($instanciaActual->aprobado_informe && ! $tieneInformePdfMediciones)
                            <div class="alert alert-warning mb-3">
                                La muestra figura como aprobada pero no hay PDF cargado. Subí el informe para continuar.
                            </div>
                        @endif

                        @if($tieneInformePdfMediciones)
                            <p class="mb-2">
                                <span class="badge bg-success">Informe cargado</span>
                                @if($instanciaActual->aprobado_informe)
                                    <span class="badge bg-primary ms-1">Aprobado</span>
                                @endif
                            </p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a href="{{ route('mediciones.informe.ver', ['cotizacion' => $categoria->cotio_numcoti, 'item' => $categoria->cotio_item, 'instance' => $instance]) }}"
                                   class="btn btn-outline-primary btn-sm" target="_blank">
                                    Ver informe actual
                                </a>
                                @if(! $instanciaActual->aprobado_informe)
                                <form action="{{ route('mediciones.informe.eliminar') }}" method="POST" class="d-inline js-swal-confirm-form"
                                      data-swal-title="Eliminar informe"
                                      data-swal-text="¿Eliminar el informe cargado? Esta acción no se puede deshacer."
                                      data-swal-icon="warning"
                                      data-swal-confirm-color="#d33">
                                    @csrf
                                    <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                                    <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                                    <input type="hidden" name="instance_number" value="{{ $instance }}">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar informe</button>
                                </form>
                                @endif
                            </div>
                        @else
                            <p class="text-muted mb-3">Todavía no se subió un informe para esta muestra.</p>
                        @endif

                        @if(! $instanciaActual->aprobado_informe || ! $tieneInformePdfMediciones)
                        <form action="{{ route('mediciones.informe.subir') }}" method="POST" enctype="multipart/form-data" class="mb-3 js-form-subir-informe-mediciones">
                            @csrf
                            <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                            <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                            <input type="hidden" name="instance_number" value="{{ $instance }}">
                            <div class="mb-2">
                                <label class="form-label">Subir informe (PDF)</label>
                                <input type="file" name="informe_pdf" class="form-control" accept="application/pdf" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Subir informe</button>
                        </form>

                        @if($tieneInformePdfMediciones)
                        <form action="{{ route('mediciones.informe.aprobar') }}" method="POST" class="js-swal-confirm-form"
                              data-swal-title="Aprobar informe"
                              data-swal-text="¿Aprobar el informe y enviar la muestra a Informes?"
                              data-swal-icon="question">
                            @csrf
                            <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                            <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                            <input type="hidden" name="instance_number" value="{{ $instance }}">
                            <button type="submit" class="btn btn-success btn-sm">Aprobar informe</button>
                        </form>
                        @endif
                        @endif
                    </div>
                </div>
                @endif

                @if(!empty($esFacturacionDirecta) && $instanciaActual)
                @php
                    $informePdfFd = trim((string) ($instanciaActual->archivo_informe ?? ''));
                    $tieneInformePdfFd = $informePdfFd !== '';
                @endphp
                <div class="card border-success mt-3">
                    <div class="card-header bg-success text-white">
                        <strong>Informe de consultoría</strong>
                    </div>
                    <div class="card-body">
                        @if($tieneInformePdfFd)
                            <p class="mb-2">
                                <span class="badge bg-success">Informe cargado</span>
                                @if($instanciaActual->enable_inform || $instanciaActual->aprobado_informe)
                                    <span class="badge bg-primary ms-1">En informes</span>
                                @endif
                                @if(($instanciaActual->cotio_estado ?? '') === 'completado')
                                    <span class="badge bg-secondary ms-1">Completado</span>
                                @endif
                            </p>
                            <a href="{{ asset('storage/' . $informePdfFd) }}"
                               class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                                Ver informe actual
                            </a>
                        @else
                            <p class="text-muted mb-0">
                                Todavía no hay informe cargado para esta muestra.
                                @if(($instanciaActual->cotio_estado ?? '') !== 'completado')
                                    Podés subirlo desde el detalle de la cotización con <strong>Pasar a informes</strong>.
                                @endif
                            </p>
                        @endif
                    </div>
                </div>
                @endif

                @if($mostrarBotonDeshabilitarOt && $instanciaActual->enable_ot == true && Auth::user()->rol !== 'coordinador_muestreo')
                    <form action="{{ route('categorias.disable-ot', [
                        'cotio_numcoti' => $categoria->cotio_numcoti,
                        'cotio_item' => $categoria->cotio_item,
                        'cotio_subitem' => $categoria->cotio_subitem,
                        'instance' => $instance
                    ]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="cotio_numcoti" value="{{ $categoria->cotio_numcoti }}">
                        <input type="hidden" name="cotio_item" value="{{ $categoria->cotio_item }}">
                        <input type="hidden" name="cotio_subitem" value="{{ $categoria->cotio_subitem }}">
                        <input type="hidden" name="instance" value="{{ $instance }}">
                        <button class="btn btn-danger mt-2">Deshabilitar OT</button>
                    </form>
                @endif



            </div>
        </div>



        @if($tareas->count())
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 p-2">Análisis agregados a la muestra</h5>
                    <div class="d-flex gap-2">
                        <form class="p-2 mb-0" action="{{ route('muestras.finalizar-todas', [
                            'cotio_numcoti' => $categoria->cotio_numcoti,
                            'cotio_item' => $categoria->cotio_item,
                            'cotio_subitem' => $categoria->cotio_subitem,
                            'instance_number' => $instanciaActual->instance_number
                        ]) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <x-heroicon-o-check style="width: 20px; height: 20px;"/>
                                Finalizar todas
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @foreach ($tareas as $tarea)
                            <div class="col">
                                <div class="card h-100">
                                    <div class="card-body" style="background-color: #A6C5E3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input tarea-checkbox" 
                                                    type="checkbox" 
                                                    name="tareas_seleccionadas[]" 
                                                    value="{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                    id="tarea_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                                                    data-fecha-inicio="{{ $tarea->fecha_inicio_muestreo ? date('Y-m-d\TH:i', strtotime($tarea->fecha_inicio_muestreo)) : '' }}"
                                                    data-fecha-fin="{{ $tarea->fecha_fin_muestreo ? date('Y-m-d\TH:i', strtotime($tarea->fecha_fin_muestreo)) : '' }}">
                                            <label class="form-check-label" for="tarea_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}">
                                                <h5 class="card-title">{{ $tarea->cotio_descripcion }}</h5>
                                            </label>
                                        </div>

                                        @if($tarea->instancia && $tarea->instancia->resultado)
                                            <p class="mb-2">
                                                Resultado: 
                                                <span class="badge bg-success">{{ $tarea->instancia->resultado }}</span>
                                            </p>
                                        @endif

                                        <p class="text-muted mb-3">
                                            <strong>Asignada a:</strong> 
                                            @if ($tarea->instancia->responsablesMuestreo->count() > 0)
                                                @foreach ($tarea->instancia->responsablesMuestreo as $responsable)
                                                    <span class="badge bg-primary">{{ $responsable->usu_descripcion }}</span>
                                                @endforeach
                                            @else
                                                <span class="badge bg-secondary">Sin asignar</span>
                                            @endif
                                        </p>

                                        @if ($tarea->instancia->vehiculo_asignado)
                                            <div class="mb-2 d-flex align-items-center justify-content-between">
                                                <p class="mb-1">Vehículo: 
                                                    <span class="badge bg-primary">{{ $tarea->instancia->vehiculo->marca ?? 'N/A' }} {{ $tarea->instancia->vehiculo->modelo ?? 'N/A' }} ({{ $tarea->instancia->vehiculo->patente ?? 'N/A' }})</span>
                                                </p>
                                                <form 
                                                    action="{{ route('tareas.desasignar-vehiculo', [
                                                        'cotizacion' => $tarea->instancia->cotio_numcoti,
                                                        'item' => $tarea->instancia->cotio_item,
                                                        'subitem' => $tarea->instancia->cotio_subitem,
                                                        'vehiculo_id' => $tarea->instancia->vehiculo_asignado
                                                    ]) }}" 
                                                    method="POST"
                                                    class="d-inline"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <x-heroicon-o-trash style="width: 20px; height: 20px;" />
                                                    </button>
                                                </form>
                                            </div>
                                        @endif

                                        @if(isset($tarea->instancia->herramientas) && $tarea->instancia->herramientas->count())
                                            <div class="card shadow-sm mb-3">
                                                <div class="card-header bg-light">
                                                    <h6 class="card-title mb-0 p-2">Herramientas para {{ $tarea->cotio_descripcion }}</h6>
                                                </div>
                                                <div class="card-body">
                                                    <ul class="list-group">
                                                        @foreach ($tarea->instancia->herramientas as $herramienta)
                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                {{ $herramienta->equipamiento }}
                                                                @if($herramienta->marca_modelo)
                                                                    <small class="text-muted">({{ $herramienta->marca_modelo }})</small>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        @endif

                                        <p class="text-muted mb-2 fecha-wrapper" data-fecha-fin="{{ $tarea->instancia->fecha_fin_muestreo ? $tarea->instancia->fecha_fin_muestreo : '' }}">
                                            <strong>Inicio:</strong> 
                                            <span class="{{ $tarea->instancia->fecha_inicio_muestreo ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                                {{ $tarea->instancia->fecha_inicio_muestreo ? date('d/m/Y H:i', strtotime($tarea->instancia->fecha_inicio_muestreo)) : 'Faltante' }}
                                            </span>
                                            &nbsp;&nbsp;|&nbsp;&nbsp;
                                            <strong>Fin:</strong> 
                                            <span class="fecha-fin {{ $tarea->instancia->fecha_fin_muestreo ? 'bg-light text-dark px-2 py-1 rounded' : '' }}">
                                                {{ $tarea->instancia->fecha_fin ? date('d/m/Y H:i', strtotime($tarea->instancia->fecha_fin)) : 'Faltante' }}
                                            </span>
                                        </p>

                                    
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-info">
                <x-heroicon-o-information-circle style="width: 20px; height: 20px;" />No hay análisis agregados al muestreo.
            </div>
        @endif
    @endif

    <div class="modal fade" id="estadoModal" tabindex="-1" aria-labelledby="estadoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="estadoModalLabel">Ajustar estado de tarea</h5>
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
                            <label for="modal_estado" class="form-label">Estado</label>
                            <select class="form-select" id="modal_estado" name="estado" required>
                                <option value="coordinado muestreo" {{ ($instanciaActual->cotio_estado ?? 'pendiente') == 'coordinado muestreo' ? 'selected' : '' }}>coordinado muestreo</option>
                                <option value="en revision muestreo" {{ ($instanciaActual->cotio_estado ?? 'pendiente') == 'en revision muestreo' ? 'selected' : '' }}>En revision Muestreo</option>
                                <option value="muestreado" {{ ($instanciaActual->cotio_estado ?? 'pendiente') == 'muestreado' ? 'selected' : '' }}>muestreado</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmarEstado">Ajustar</button>
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

    <div class="modal fade" id="herramientasModal" tabindex="-1" aria-labelledby="herramientasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="herramientasModalLabel">Seleccionar Herramientas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                                @foreach($inventario as $item)
                                    <label class="list-group-item d-flex align-items-center">
                                        <input class="form-check-input me-3" type="checkbox" value="{{ $item->id }}" 
                                               data-equipamiento="{{ $item->equipamiento }}"
                                               data-marca="{{ $item->marca_modelo }}"
                                               data-serie="{{ $item->n_serie_lote }}">
                                        <div>
                                            <div class="fw-bold">{{ $item->equipamiento }}</div>
                                            <small class="text-muted">{{ $item->marca_modelo }} - {{ $item->n_serie_lote }}</small>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarSeleccionHerramientas()">Guardar Selección</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="historialModal" tabindex="-1" aria-labelledby="historialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="historialModalLabel">Historial de Cambios</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="historialContent">
                        <p>Seleccione una variable para ver su historial.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para gestionar responsables de muestreo -->
    <div class="modal fade" id="gestionarResponsablesMuestreoModal" tabindex="-1" aria-labelledby="gestionarResponsablesMuestreoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gestionarResponsablesMuestreoModalLabel">Gestionar Responsables de Muestreo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Responsables actuales -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Responsables Actuales</h6>
                        <div id="responsablesActualesMuestreo">
                            <div class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Agregar nuevos responsables -->
                    <div class="mb-3">
                        <h6 class="fw-bold mb-3">Agregar Responsables</h6>
                        <form id="agregarResponsablesMuestreoForm">
                            <div class="mb-3">
                                <label for="nuevosResponsablesMuestreo" class="form-label fw-semibold">
                                    <i class="fas fa-users me-2"></i>Seleccionar Muestreadores
                                </label>
                                <select id="nuevosResponsablesMuestreo" name="nuevos_responsables[]" class="form-select select-muestreadores" multiple>
                                    @foreach($usuariosMuestreo as $usuario)
                                        @if($usuario->rol === 'muestreador')
                                            <option value="{{ trim($usuario->usu_codigo) }}" data-role="muestreador">
                                                {{ $usuario->usu_descripcion }} ({{ trim($usuario->usu_codigo) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <small class="form-text text-muted mt-1">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Selecciona uno o más muestreadores para agregar a esta instancia
                                </small>
                            </div>
                            <input type="hidden" id="instanciaIdMuestreo" name="instancia_id" value="">
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="agregarResponsablesMuestreo()" id="btnAgregarResponsables">
                        <i class="fas fa-user-plus me-1"></i>Agregar Seleccionados
                    </button>
                </div>
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
        } else {
            // Configuración para tarea
            document.getElementById('modal_cotio_item').value = button.dataset.item;
            document.getElementById('modal_cotio_subitem').value = button.dataset.subitem;

            
            // Obtener estado actual de la tarea
            const estadoActual = card.querySelector('.badge').textContent.trim().toLowerCase();
            document.getElementById('modal_estado').value = estadoActual;
        }
    });

    document.getElementById('confirmarEstado').addEventListener('click', async function() {
        const form = document.getElementById('estadoForm');
        const formData = new FormData(form);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        try {
            const response = await fetch('{{ route("tareas.actualizar-estado") }}', {
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
                    text: data.message || 'Error al actualizar el estado',
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
    // Inicializar Select2 para herramientas
    $('#herramientas').select2({
        placeholder: "Seleccione herramientas",
        width: '100%',
        dropdownParent: $('#herramientasModal')
    });

    // Configurar fechas al cargar el formulario
    function setupInitialDates() {
        const fechaInicioInput = document.getElementById('fecha_inicio_muestreo');
        const fechaFinInput = document.getElementById('fecha_fin_muestreo');
        
        // Si no hay valores establecidos, configurar defaults
        if (!fechaInicioInput.value) {
            const now = new Date();
            let defaultStartDate = new Date();
            
            // Si hoy es fin de semana, establecer para el próximo lunes
            if (now.getDay() === 0) defaultStartDate.setDate(now.getDate() + 1);
            else if (now.getDay() === 6) defaultStartDate.setDate(now.getDate() + 2);
            
            // Establecer hora a las 8:00 AM
            defaultStartDate.setHours(8, 0, 0, 0);
            fechaInicioInput.value = formatDateTimeForInput(defaultStartDate);
            
            // Establecer fecha fin (mismo día a las 6:00 PM)
            if (!fechaFinInput.value) {
                let defaultEndDate = new Date(defaultStartDate);
                defaultEndDate.setHours(18, 0, 0, 0);
                fechaFinInput.value = formatDateTimeForInput(defaultEndDate);
            }
        }
    }

    // Función para formatear fecha al formato que espera el input datetime-local
    function formatDateTimeForInput(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
    }

    // Validar fecha inicio al cambiar
    document.getElementById('fecha_inicio_muestreo').addEventListener('change', function() {
        const startDateInput = this;
        const endDateInput = document.getElementById('fecha_fin_muestreo');
        const startDate = new Date(startDateInput.value);
        
        if (startDate.getDay() === 0 || startDate.getDay() === 6) {
            Swal.fire({
                title: 'Día no válido',
                text: 'No se pueden seleccionar sábados o domingos como fecha de inicio',
                icon: 'error'
            });
            startDateInput.value = '';
            return;
        }
        
        if (endDateInput.value) {
            const endDate = new Date(endDateInput.value);
            if (endDate <= startDate) {
                const newEndDate = new Date(startDate);
                newEndDate.setHours(18, 0, 0, 0);
                endDateInput.value = formatDateTimeForInput(newEndDate);
            }
        } else {
            const defaultEndDate = new Date(startDate);
            defaultEndDate.setHours(18, 0, 0, 0);
            endDateInput.value = formatDateTimeForInput(defaultEndDate);
        }
    });

    // Validar fecha fin al cambiar
    document.getElementById('fecha_fin_muestreo').addEventListener('change', function() {
        const endDateInput = this;
        const startDateInput = document.getElementById('fecha_inicio_muestreo');
        
        if (!startDateInput.value) {
            Swal.fire({
                title: 'Fecha requerida',
                text: 'Primero debe seleccionar una fecha de inicio',
                icon: 'warning'
            });
            endDateInput.value = '';
            return;
        }
        
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        if (endDate.getDay() === 0 || endDate.getDay() === 6) {
            Swal.fire({
                title: 'Día no válido',
                text: 'No se pueden seleccionar sábados o domingos como fecha de fin',
                icon: 'error'
            });
            endDateInput.value = '';
            return;
        }
        
        if (endDate <= startDate) {
            Swal.fire({
                title: 'Fecha inválida',
                text: 'La fecha de fin debe ser posterior a la fecha de inicio',
                icon: 'error'
            });
            const defaultEndDate = new Date(startDate);
            defaultEndDate.setHours(18, 0, 0, 0);
            endDateInput.value = formatDateTimeForInput(defaultEndDate);
            return;
        }
        
        if (endDate.getDate() !== startDate.getDate() || 
            endDate.getMonth() !== startDate.getMonth() || 
            endDate.getFullYear() !== startDate.getFullYear()) {
            Swal.fire({
                title: 'Fecha inválida',
                text: 'La fecha de fin debe ser el mismo día que la fecha de inicio',
                icon: 'error'
            });
            const defaultEndDate = new Date(startDate);
            defaultEndDate.setHours(18, 0, 0, 0);
            endDateInput.value = formatDateTimeForInput(defaultEndDate);
        }
    });

    // Manejar envío del formulario
    document.getElementById('asignarForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validación de fechas antes de enviar
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

        // Obtener herramientas seleccionadas como array de IDs
        const herramientasSeleccionadas = $('#herramientas').val() || [];
        
        // Obtener tareas seleccionadas como array
        const tareasSeleccionadas = Array.from(document.querySelectorAll('.tarea-checkbox:checked'))
            .map(checkbox => checkbox.value);

        // Crear objeto FormData
        const formData = new FormData();
        formData.append('cotio_numcoti', document.querySelector('input[name="cotio_numcoti"]').value);
        formData.append('cotio_item', document.querySelector('input[name="cotio_item"]').value);
        formData.append('instance', document.querySelector('input[name="instance"]').value);
        formData.append('vehiculo_asignado', document.getElementById('vehiculo_asignado').value || '');
        formData.append('responsable_codigo', document.getElementById('responsable_codigo').value || '');
        formData.append('fecha_inicio_muestreo', fechaInicio);
        formData.append('fecha_fin_muestreo', fechaFin);

        // Agregar herramientas como array
        herramientasSeleccionadas.forEach(herramienta => {
            formData.append('herramientas[]', herramienta);
        });

        // Agregar tareas seleccionadas como array
        tareasSeleccionadas.forEach(tarea => {
            formData.append('tareas_seleccionadas[]', tarea);
        });

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value ||
                         '{{ csrf_token() }}';
        
        try {
            const response = await fetch(e.target.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
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
                throw new Error(data.message || "Hubo un problema al procesar la solicitud");
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: err.message || 'Ocurrió un error al procesar la solicitud. Verifique sus permisos.',
                confirmButtonColor: '#3085d6',
            });
            console.error(err);
        }
    });

    // Configurar fechas iniciales al cargar
    setupInitialDates();
});


async function enviarFrecuencia() {
    const form = document.getElementById('asignarFrecuenciaForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    data.es_frecuente = data.es_frecuente === '1';

    // Obtener las tareas seleccionadas
    const tareasSeleccionadas = Array.from(document.querySelectorAll('.tarea-checkbox:checked'))
        .map(checkbox => {
            const [item, subitem] = checkbox.value.split('_');
            return { item, subitem };
        });

    data.tareas_seleccionadas = tareasSeleccionadas;

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
    $(document).ready(function() {
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar Select2
        $('.select2-multiple').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        const identificacionFormLegacy = document.getElementById('identificacionForm');
        if (identificacionFormLegacy) {
            identificacionFormLegacy.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
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
                        text: data.message || 'Error al guardar los cambios',
                        confirmButtonColor: '#3085d6',
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud. Verifique sus permisos.',
                    confirmButtonColor: '#3085d6',
                });
            }
            });
        }

        const coordIdentForm = document.getElementById('coordIdentificacionMuestraForm');
        if (coordIdentForm) {
            const fotoInput = document.getElementById('coord_foto_muestra');
            const hiddenB64 = document.getElementById('coord_image_base64');
            if (fotoInput && hiddenB64) {
                fotoInput.addEventListener('change', function () {
                    const file = this.files && this.files[0];
                    if (!file) {
                        hiddenB64.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function () {
                        hiddenB64.value = reader.result || '';
                    };
                    reader.readAsDataURL(file);
                });
            }

            coordIdentForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                    document.querySelector('input[name="_token"]')?.value ||
                    '{{ csrf_token() }}';
                try {
                    const formData = new FormData(coordIdentForm);
                    const response = await fetch(coordIdentForm.action, {
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
                    if (response.ok && data.success !== false) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: data.message || 'Datos guardados',
                            confirmButtonColor: '#3085d6',
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'No se pudieron guardar los cambios',
                            confirmButtonColor: '#3085d6',
                        });
                    }
                } catch (error) {
                    console.error(error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Ocurrió un error al guardar.',
                        confirmButtonColor: '#3085d6',
                    });
                }
            });
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const medicionesForm = document.getElementById('medicionesForm');
        if (!medicionesForm) {
            return;
        }
        medicionesForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
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
                        text: data.message || 'Error al guardar las mediciones',
                        confirmButtonColor: '#3085d6',
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al procesar la solicitud. Verifique sus permisos.',
                    confirmButtonColor: '#3085d6',
                });
            }
        });
    });
</script>

<script>
    function toggleFormularios() {
        const content = document.getElementById('formularios-content');
        const chevron = document.getElementById('chevron-formularios');
        const header = content.previousElementSibling;
        
        if (content.style.display === 'none' || getComputedStyle(content).display === 'none') {
            content.style.display = 'block';
            let height = content.scrollHeight + 'px';
            content.style.height = '0';
            requestAnimationFrame(() => {
                content.style.transition = 'height 0.3s ease';
                content.style.height = height;
            });
            
            chevron.setAttribute('transform', 'rotate(180)');
            header.setAttribute('aria-expanded', 'true');
            
            content.addEventListener('transitionend', function handler() {
                content.style.height = 'auto';
                content.removeEventListener('transitionend', handler);
            });
        } else {
            content.style.height = content.scrollHeight + 'px';
            requestAnimationFrame(() => {
                content.style.transition = 'height 0.3s ease';
                content.style.height = '0';
            });
            
            chevron.setAttribute('transform', 'rotate(0)');
            header.setAttribute('aria-expanded', 'false');
            
            content.addEventListener('transitionend', function handler() {
                content.style.display = 'none';
                content.removeEventListener('transitionend', handler);
            });
        }
    }

    // Inicializar el estado del formulario
    document.addEventListener('DOMContentLoaded', function() {
        const content = document.getElementById('formularios-content');
        content.style.display = 'none';
        content.style.height = '0';
        content.style.overflow = 'hidden';
    });
</script>

<script>
    function guardarSeleccionHerramientas() {
        const checkboxes = document.querySelectorAll('#herramientasModal .form-check-input:checked');
        const select = document.getElementById('herramientas');
        const contenedor = document.getElementById('herramientas-seleccionadas');
        
        select.innerHTML = '';
        contenedor.innerHTML = '';
        
        if (checkboxes.length === 0) {
            contenedor.innerHTML = '<small class="text-muted">Ninguna herramienta seleccionada</small>';
        } else {
            const badges = [];
            checkboxes.forEach(checkbox => {
                const option = document.createElement('option');
                option.value = checkbox.value;
                option.selected = true;
                option.text = checkbox.dataset.equipamiento;
                select.appendChild(option);
                
                badges.push(`
                    <span class="badge bg-primary me-2 mb-2">
                        ${checkbox.dataset.equipamiento}
                        <button type="button" class="btn-close btn-close-white ms-2" 
                                style="font-size: 0.5rem;"
                                onclick="quitarHerramienta(${checkbox.value})"></button>
                    </span>
                `);
            });
            contenedor.innerHTML = badges.join('');
        }
        
        bootstrap.Modal.getInstance(document.getElementById('herramientasModal')).hide();
    }

    function quitarHerramienta(id) {
        const checkbox = document.querySelector(`#herramientasModal .form-check-input[value="${id}"]`);
        if (checkbox) {
            checkbox.checked = false;
        }
        guardarSeleccionHerramientas();
    }
</script>

<script>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Guardar todas las variables y observaciones
        document.querySelectorAll('.save-all-data').forEach(button => {
            button.addEventListener('click', function() {
                const instanciaId = this.dataset.instanciaId;
                const button = this;
                
                // Recopilar todas las variables (incluir todas, incluso las vacías)
                const variables = [];
                document.querySelectorAll('.variable-value').forEach(input => {
                    variables.push({
                        id: input.dataset.id,
                        valor: input.value.trim()
                    });
                });
                
                // Obtener las observaciones
                const observaciones = document.getElementById('observaciones').value.trim();
                
                // Validar que haya al menos una variable con valor o una observación
                const hasVariablesWithValue = variables.some(v => v.valor !== '');
                if (!hasVariablesWithValue && observaciones === '') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Advertencia',
                        text: 'Debes ingresar al menos un valor de variable o una observación antes de guardar.',
                        confirmButtonColor: '#3085d6',
                    });
                    return;
                }
                
                // Mostrar indicador de carga
                button.disabled = true;
                const originalHtml = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                
                // Obtener token CSRF
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                 document.querySelector('input[name="_token"]')?.value ||
                                 '{{ csrf_token() }}';
                
                // Configurar el cuerpo de la petición
                const body = JSON.stringify({
                    instancia_id: instanciaId,
                    variables: variables,
                    observaciones: observaciones
                });
                
                console.log('Enviando datos:', body); // Para debugging
                
                // Hacer la petición con fetch
                fetch('{{ route("muestras.updateAllData") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body,
                    credentials: 'same-origin'
                })
                .then(response => {
                    const contentType = response.headers.get('content-type');
                    if (!response.ok) {
                        if (contentType && contentType.includes('application/json')) {
                            return response.json().then(errorData => {
                                throw new Error(errorData.message || 'Error en la respuesta del servidor');
                            });
                        } else {
                            return response.text().then(text => {
                                throw new Error(`Error ${response.status}: ${text || response.statusText}`);
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
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message || 'Variables y observaciones actualizadas correctamente',
                        confirmButtonColor: '#3085d6',
                    });
                    button.innerHTML = '<i class="fas fa-check"></i> Guardado';
                    setTimeout(() => {
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    }, 2000);
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message,
                        confirmButtonColor: '#3085d6',
                    });
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                    console.error('Error:', error);
                });
            });
        });
        
        // Opcional: permitir guardar con Enter en cualquier campo
        document.querySelectorAll('.variable-value, #observaciones').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if(e.which === 13) { // Tecla Enter
                    e.preventDefault();
                    document.querySelector('.save-all-data').click();
                }
            });
        });
        
        // Adjuntos de revisión (coordinador de muestreo)
        const btnSubirAdjuntos = document.getElementById('btnSubirAdjuntosInstancia');
        if (btnSubirAdjuntos) {
            btnSubirAdjuntos.addEventListener('click', function () {
                const input = document.getElementById('adjuntos_instancia_input');
                const instanciaId = this.dataset.instanciaId;
                const lista = document.getElementById('listaAdjuntosInstancia');

                if (!input || !input.files || input.files.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin archivos',
                        text: 'Seleccioná al menos un archivo PDF o imagen.',
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('instancia_id', instanciaId);
                Array.from(input.files).forEach(function (file) {
                    formData.append('archivos[]', file);
                });

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value
                    || '{{ csrf_token() }}';

                btnSubirAdjuntos.disabled = true;
                const textoOriginal = btnSubirAdjuntos.innerHTML;
                btnSubirAdjuntos.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Subiendo...';

                fetch('{{ route("muestras.adjuntos.subir") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                    credentials: 'same-origin',
                })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al subir archivos');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    input.value = '';
                    const vacio = lista.querySelector('.adjuntos-vacio');
                    if (vacio) {
                        vacio.remove();
                    }

                    (data.adjuntos || []).forEach(function (adj) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center adjunto-instancia-item';
                        li.dataset.adjuntoId = adj.id;
                        const iconoSvg = adj.es_imagen
                            ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-info flex-shrink-0" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" /></svg>'
                            : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-danger flex-shrink-0" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>';
                        li.innerHTML = ''
                            + '<div class="d-flex align-items-center gap-2 text-truncate me-2">'
                            + iconoSvg
                            + '<a href="' + adj.url + '" target="_blank" rel="noopener" class="text-truncate">' + adj.name + '</a>'
                            + '</div>'
                            + '<button type="button" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center justify-content-center btn-eliminar-adjunto-instancia" style="width:2rem;height:2rem;padding:0;" data-adjunto-id="' + adj.id + '" title="Eliminar" aria-label="Eliminar adjunto">'
                            + '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>'
                            + '</button>';
                        lista.appendChild(li);
                    });

                    Swal.fire({
                        icon: 'success',
                        title: 'Listo',
                        text: data.message || 'Archivos subidos correctamente.',
                        timer: 2000,
                        showConfirmButton: false,
                    });
                })
                .catch(function (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudieron subir los archivos.',
                    });
                })
                .finally(function () {
                    btnSubirAdjuntos.disabled = false;
                    btnSubirAdjuntos.innerHTML = textoOriginal;
                });
            });
        }

        document.getElementById('listaAdjuntosInstancia')?.addEventListener('click', function (event) {
            const btnVentas = event.target.closest('.btn-eliminar-adjunto-ventas');
            const btnInstancia = event.target.closest('.btn-eliminar-adjunto-instancia');
            const btn = btnVentas || btnInstancia;
            if (!btn) {
                return;
            }

            const adjuntoId = btn.dataset.adjuntoId;
            const item = btn.closest('.adjunto-ventas-item, .adjunto-instancia-item');
            const urlEliminar = btnVentas
                ? '{{ url("/muestras/adjuntos-ventas") }}/' + adjuntoId
                : '{{ url("/muestras/adjuntos") }}/' + adjuntoId;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || '{{ csrf_token() }}';

            Swal.fire({
                icon: 'question',
                title: 'Eliminar archivo',
                text: '¿Querés eliminar este adjunto?',
                showCancelButton: true,
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                fetch(urlEliminar, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                })
                .then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok) {
                            throw new Error(data.message || 'Error al eliminar');
                        }
                        return data;
                    });
                })
                .then(function () {
                    item.remove();
                    const lista = document.getElementById('listaAdjuntosInstancia');
                    if (lista && lista.querySelectorAll('.adjunto-instancia-item, .adjunto-ventas-item').length === 0) {
                        lista.innerHTML = '<li class="list-group-item text-muted adjuntos-vacio">No hay archivos adjuntos.</li>';
                    }
                })
                .catch(function (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudo eliminar el archivo.',
                    });
                });
            });
        });

        // Función para eliminar responsable de todas las tareas
        window.eliminarResponsableTodasTareas = function(usuCodigo) {
            if (!confirm('¿Estás seguro de que quieres eliminar este responsable de todas las tareas?')) {
                return;
            }
            
            // Obtener los datos necesarios de la página
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
            formData.append('todos', 'true'); // Enviar como string 'true' o 'false'
            
            // Construir la URL
            const url = '{{ route("muestras.remover-responsable") }}';
            
            console.log('Enviando petición a:', url);
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                             document.querySelector('input[name="_token"]')?.value ||
                             '{{ csrf_token() }}';
            
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
                        title: 'Éxito',
                        text: data.message,
                        confirmButtonColor: '#3085d6',
                    });
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al eliminar el responsable',
                        confirmButtonColor: '#3085d6',
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al eliminar el responsable. Verifique sus permisos.',
                    confirmButtonColor: '#3085d6',
                });
            });
        };
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('historialModal'));
        const historial = @json($historialCambios);

        document.querySelectorAll('.btn-historial').forEach(button => {
            button.addEventListener('click', function() {
                const variableId = this.dataset.variableId;
                const cambios = historial[variableId] || [];

                let content = '';
                if (cambios.length === 0) {
                    content = '<p>No hay historial de cambios para esta variable.</p>';
                } else {
                    content = '<table class="table table-bordered table-striped">' +
                              '<thead><tr>' +
                              '<th>Fecha</th>' +
                              '<th>Usuario</th>' +
                              '<th>Acción</th>' +
                              '<th>Campo</th>' +
                              '<th>Valor Anterior</th>' +
                              '<th>Valor Nuevo</th>' +
                              '</tr></thead><tbody>';

                    cambios.forEach(cambio => {
                        content += `<tr>
                            <td>${new Date(cambio.fecha_cambio).toLocaleString()}</td>
                            <td>${cambio.usuario ? cambio.usuario.usu_descripcion : 'Desconocido'}</td>
                            <td>${cambio.accion.charAt(0).toUpperCase() + cambio.accion.slice(1)}</td>
                            <td>${cambio.campo_modificado}</td>
                            <td>${cambio.valor_anterior || 'N/A'}</td>
                            <td>${cambio.valor_nuevo || 'N/A'}</td>
                        </tr>`;
                    });

                    content += '</tbody></table>';
                }

                document.getElementById('historialContent').innerHTML = content;
                document.getElementById('historialModalLabel').textContent = `Historial de Cambios - Variable ID: ${variableId}`;
                modal.show();
            });
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('observaciones');
    const textarea2 = document.getElementById('observaciones_muestreador');
    if (textarea) {
        textarea.value = textarea.value.trim();
    }
    if (textarea2) {
        textarea2.value = textarea2.value.trim();
    }
});

</script>

<script>
// Funciones para gestionar responsables de muestreo
document.addEventListener('DOMContentLoaded', function() {
    // Event listener para cuando se abre el modal
    const modal = document.getElementById('gestionarResponsablesMuestreoModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const instanciaId = button.getAttribute('data-instancia-id');
            
            // Establecer el ID de instancia en el formulario
            document.getElementById('instanciaIdMuestreo').value = instanciaId;
            
            // Cargar responsables actuales
            cargarResponsablesActualesMuestreo(instanciaId);
            
            // Destruir Select2 existente si existe
            if ($('#nuevosResponsablesMuestreo').hasClass('select2-hidden-accessible')) {
                $('#nuevosResponsablesMuestreo').select2('destroy');
            }
            
            // Inicializar Select2 con configuración mejorada
            $('#nuevosResponsablesMuestreo').select2({
                placeholder: "🔍 Buscar y seleccionar muestreadores...",
                width: '100%',
                dropdownParent: $('#gestionarResponsablesMuestreoModal'),
                theme: 'bootstrap-5',
                allowClear: true,
                closeOnSelect: false,
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    
                    // Crear elemento personalizado para cada opción
                    var $result = $(
                        '<div class="d-flex align-items-center">' +
                            '<div class="me-2">' +
                                '<i class="fas fa-user-hard-hat text-primary"></i>' +
                            '</div>' +
                            '<div>' +
                                '<div class="fw-semibold">' + data.text.split(' (')[0] + '</div>' +
                                '<small class="text-muted">Código: ' + data.text.match(/\(([^)]+)\)/)?.[1] + '</small>' +
                            '</div>' +
                        '</div>'
                    );
                    return $result;
                },
                templateSelection: function(data) {
                    if (!data.id) return data.text;
                    
                    // Formato para elementos seleccionados
                    return '👤 ' + data.text.split(' (')[0];
                }
            });
            
            // Limpiar selección
            $('#nuevosResponsablesMuestreo').val(null).trigger('change');
        });
    }
});

function cargarResponsablesActualesMuestreo(instanciaId) {
    const container = document.getElementById('responsablesActualesMuestreo');
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';
    
    fetch(`/api/get-responsables-muestreo`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            instancia_id: instanciaId
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
            if (data.responsables.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No hay responsables asignados</div>';
            } else {
                const responsablesHtml = data.responsables.map(responsable => `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                        <span>
                            <strong>${responsable.usu_descripcion}</strong>
                            <small class="text-muted">(${responsable.usu_codigo.trim()})</small>
                        </span>
                        <button type="button" 
                                class="btn btn-sm btn-outline-danger"
                                onclick="quitarResponsableMuestreoModal('${responsable.usu_codigo.trim()}')"
                                title="Quitar responsable">
                            ×
                        </button>
                    </div>
                `).join('');
                
                container.innerHTML = responsablesHtml;
            }
        } else {
            container.innerHTML = '<div class="alert alert-danger">Error al cargar responsables</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<div class="alert alert-danger">Error al cargar responsables. Verifique sus permisos.</div>';
    });
}

function agregarResponsablesMuestreo() {
    const instanciaId = document.getElementById('instanciaIdMuestreo').value;
    const nuevosResponsables = $('#nuevosResponsablesMuestreo').val();
    const btn = document.getElementById('btnAgregarResponsables');
    
    if (!nuevosResponsables || nuevosResponsables.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Advertencia',
            text: 'Debes seleccionar al menos un muestreador',
            confirmButtonColor: '#3085d6'
        });
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                     document.querySelector('input[name="_token"]')?.value ||
                     '{{ csrf_token() }}';
    
    // Mostrar indicador de carga
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Agregando...';
    
    fetch(`/muestras/editar-responsables`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'X-HTTP-Method-Override': 'PUT'
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            instancia_id: instanciaId,
            responsables: nuevosResponsables
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
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus me-1"></i>Agregar Seleccionados';
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
                confirmButtonColor: '#3085d6',
                timer: 2000
            }).then(() => {
                // Recargar responsables actuales
                cargarResponsablesActualesMuestreo(instanciaId);
                // Limpiar selección
                $('#nuevosResponsablesMuestreo').val(null).trigger('change');
                // Recargar página para mostrar cambios
                setTimeout(() => window.location.reload(), 1000);
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al agregar responsables',
                confirmButtonColor: '#3085d6'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Restaurar botón
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus me-1"></i>Agregar Seleccionados';
        
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Error al agregar responsables. Verifique sus permisos.',
            confirmButtonColor: '#3085d6'
        });
    });
}

function quitarResponsableMuestreo(responsableCodigo) {
    const instanciaId = document.getElementById('instanciaIdMuestreo').value || '{{ $instanciaActual->id ?? "" }}';
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: '¿Quieres quitar este responsable del muestreo?',
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
            
            fetch(`/muestras/quitar-responsable-muestreo`, {
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
                    responsable_codigo: responsableCodigo
                })
            })
            .then(response => {
                // Verificar si la respuesta es JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // Si no es JSON, puede ser un error 403/500
                    return response.text().then(text => {
                        throw new Error(`Error ${response.status}: ${text || response.statusText}`);
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Éxito',
                        text: data.message,
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        // Si estamos en el modal, recargar responsables actuales
                        if (document.getElementById('gestionarResponsablesMuestreoModal').classList.contains('show')) {
                            cargarResponsablesActualesMuestreo(instanciaId);
                        }
                        // Recargar página para mostrar cambios
                        setTimeout(() => window.location.reload(), 1000);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al quitar responsable',
                        confirmButtonColor: '#3085d6'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al quitar responsable. Verifique sus permisos.',
                    confirmButtonColor: '#3085d6'
                });
            });
        }
    });
}

function quitarResponsableMuestreoModal(responsableCodigo) {
    quitarResponsableMuestreo(responsableCodigo);
}
</script>

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

    .card-header {
        transition: background-color 0.3s ease;
    }
    
    .card-header:hover {
        background-color: #f8f9fa !important;
    }
    
    .card-header i, .card-header svg {
        transition: transform 0.3s ease;
    }
    
    .card-header[aria-expanded="true"] i,
    .card-header[aria-expanded="true"] svg {
        transform: rotate(180deg);
    }
    
    .select2-container {
        width: 100% !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
    }
    
    /* Estilos para el select de muestreadores */
    .select-muestreadores {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .select-muestreadores:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    /* Estilos para Select2 del modal de muestreadores */
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: 100px !important;
        border: 2px solid #e9ecef !important;
        border-radius: 8px !important;
        padding: 8px !important;
    }
    
    .select2-container--bootstrap-5.select2-container--focus .select2-selection--multiple {
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
    }
    
    /* Estilo para las opciones seleccionadas */
    .select2-container--bootstrap-5 .select2-selection__choice {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: white !important;
        border-radius: 6px !important;
        padding: 4px 8px !important;
        margin: 2px !important;
        font-weight: 500 !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection__choice__remove {
        color: white !important;
        margin-right: 5px !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection__choice__remove:hover {
        color: #ffdddd !important;
    }
    
    /* Estilo para el dropdown */
    .select2-container--bootstrap-5 .select2-dropdown {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .select2-container--bootstrap-5 .select2-results__option {
        padding: 10px 15px;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .select2-container--bootstrap-5 .select2-results__option:hover {
        background-color: #f8f9fa !important;
        color: #0d6efd !important;
    }
    
    .select2-container--bootstrap-5 .select2-results__option--highlighted {
        background-color: #0d6efd !important;
        color: white !important;
    }
    
    .select2-container--bootstrap-5 .select2-results__option[data-role="muestreador"]::before {
        content: "👤 ";
        margin-right: 5px;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    .nav-tabs .nav-link {
        color: #6c757d;
        border: none;
        padding: 0.75rem 1.25rem;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.1);
    }

    .nav-tabs .nav-link.active {
        color: #0d6efd;
        background-color: transparent;
        border-bottom: 2px solid #0d6efd;
    }

    .card-header {
        padding: 0;
        background-color: transparent !important;
    }

    .tab-content {
        padding: 1.5rem 0;
    }

    .list-group-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    .form-check-input:checked + div {
        color: #0d6efd;
    }
    
    .badge {
        font-size: 0.9em;
        padding: 0.5em 0.8em;
    }
    
    .btn-close-white {
        opacity: 0.8;
    }
    
    .btn-close-white:hover {
        opacity: 1;
    }
</style>

@if(isset($instanciaActual) && $instanciaActual)
    @php
        $esCoordOAdminMuestreoModal = Auth::user()->hasRole('coordinador_muestreo') || (int) (Auth::user()->usu_nivel ?? 0) >= 900;
        $puedeModalCoord = $esCoordOAdminMuestreoModal && $instanciaActual->cotio_estado !== 'muestreado';
        $reqCadenaModal = (bool) ($categoria->req_cadena_custodia ?? false);
    @endphp
    @if($puedeModalCoord)
    <div class="modal fade" id="modalCoordDatosMuestra" tabindex="-1" aria-labelledby="modalCoordDatosMuestraLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCoordDatosMuestraLabel">Editar datos de la muestra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="coordIdentificacionMuestraForm" method="POST" action="{{ route('asignar.identificacion-muestra') }}">
                        @csrf
                        <input type="hidden" name="cotio_numcoti" value="{{ $instanciaActual->cotio_numcoti }}">
                        <input type="hidden" name="cotio_item" value="{{ $instanciaActual->cotio_item }}">
                        <input type="hidden" name="instance_number" value="{{ $instanciaActual->instance_number }}">
                        <input type="hidden" name="accion" value="borrador">
                        <input type="hidden" name="image_base64" id="coord_image_base64" value="">

                        <div class="mb-3">
                            <label for="coord_cotio_identificacion" class="form-label">Identificación <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="coord_cotio_identificacion" name="cotio_identificacion"
                                   value="{{ old('cotio_identificacion', $instanciaActual->cotio_identificacion ?? '') }}"
                                   maxlength="255" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label for="coord_latitud" class="form-label">Latitud</label>
                                <input type="number" step="any" class="form-control" id="coord_latitud" name="latitud"
                                       value="{{ old('latitud', $instanciaActual->latitud) }}" placeholder="-33.441953">
                            </div>
                            <div class="col-md-6">
                                <label for="coord_longitud" class="form-label">Longitud</label>
                                <input type="number" step="any" class="form-control" id="coord_longitud" name="longitud"
                                       value="{{ old('longitud', $instanciaActual->longitud) }}" placeholder="-70.638523">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="coord_nro_precinto" class="form-label">N° precinto</label>
                            <input type="text" class="form-control" id="coord_nro_precinto" name="nro_precinto" maxlength="100"
                                   value="{{ old('nro_precinto', $instanciaActual->nro_precinto ?? '') }}">
                        </div>

                        @if($reqCadenaModal)
                            <div class="mb-3">
                                <label for="coord_nro_cadena" class="form-label">N° cadena de custodia</label>
                                <input type="text" class="form-control" id="coord_nro_cadena" name="nro_cadena" maxlength="100"
                                       value="{{ old('nro_cadena', $instanciaActual->nro_cadena ?? '') }}">
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="coord_fecha_identificacion" class="form-label">Fecha y hora de identificación</label>
                            <input type="datetime-local" class="form-control" id="coord_fecha_identificacion" name="fecha_identificacion"
                                   value="{{ old('fecha_identificacion', $instanciaActual->fecha_identificacion ? $instanciaActual->fecha_identificacion->format('Y-m-d\TH:i') : '') }}">
                            <small class="text-muted">Opcional. Si la deja vacía, se conserva la fecha actual registrada.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Foto de la muestra</label>
                            @if($instanciaActual->image)
                                <div class="mb-2">
                                    <img src="{{ Storage::url('images/' . $instanciaActual->image) }}" alt="Foto actual" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="coord_remove_image" name="remove_image" value="1">
                                    <label class="form-check-label" for="coord_remove_image">Eliminar foto actual</label>
                                </div>
                            @endif
                            <input type="file" class="form-control" id="coord_foto_muestra" accept="image/*">
                            <small class="text-muted">Si selecciona un archivo, reemplaza la imagen actual (salvo que marque eliminar).</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
@endif

<!-- Modal de Recoordinación -->
@if($instanciaActual)
<div class="modal fade" id="recoordinarModal" tabindex="-1" aria-labelledby="recoordinarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="recoordinarModalLabel">Recoordinar Muestra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <label for="herramientas_recoordinar" class="form-label fw-semibold">Herramientas de Muestreo</label>
                        <select class="form-select select2 rounded-3" id="herramientas_recoordinar" name="herramientas[]" multiple>
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

                    <div class="mb-4 d-none" id="motivoSuspensionGroup">
                        <label for="motivoSuspension" class="form-label fw-semibold">Motivo de suspensión (muestreador)</label>
                        <textarea class="form-control rounded-3" id="motivoSuspension" rows="3" readonly></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-3" id="guardarRecoordinacion">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Modal para suspender muestra -->
@if($instanciaActual && $instanciaActual->cotio_estado != 'suspension')
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
                    'cotio_numcoti' => $instanciaActual->cotio_numcoti,
                    'cotio_item' => $instanciaActual->cotio_item,
                    'instance_number' => $instanciaActual->instance_number
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('suspenderForm');
    const textarea = document.getElementById('observacion');
    
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
        
        const modal = document.getElementById('suspenderModal');
        if (modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                form.classList.remove('was-validated');
                textarea.classList.remove('is-invalid');
            });
        }
    }

    // Recoordinación desde verMuestra
    const recoordinarModal = document.getElementById('recoordinarModal');
    if (recoordinarModal) {
        recoordinarModal.addEventListener('show.bs.modal', async function (event) {
            const button = event.relatedTarget;
            const instanciaId = button.getAttribute('data-instancia');
            const cotizacionNum = button.getAttribute('data-cotizacion');
            const item = button.getAttribute('data-item');
            const instanceNumber = button.getAttribute('data-instance');

            document.getElementById('instancia_id').value = instanciaId;
            document.getElementById('cotio_numcoti').value = cotizacionNum;
            document.getElementById('cotio_item').value = item;
            document.getElementById('instance_number').value = instanceNumber;

            try {
                const response = await fetch(`/muestras/${instanciaId}/datos-recoordinacion`);
                const data = await response.json();

                document.getElementById('fecha_inicio_muestreo').value = data.fecha_inicio_muestreo || '';
                document.getElementById('fecha_fin_muestreo').value = data.fecha_fin_muestreo || '';

                // Responsables (scoped al modal de recoordinación)
                if (Array.isArray(data.responsables)) {
                    const responsablesSelect = $('#recoordinarModal #responsables_muestreo');
                    responsablesSelect.val(data.responsables.map(r => r.usu_codigo)).trigger('change');
                }

                // Vehículo (scoped al modal de recoordinación)
                $('#recoordinarModal #vehiculo_asignado').val(data.vehiculo_asignado || '').trigger('change');

                // Herramientas (scoped al modal de recoordinación)
                if (Array.isArray(data.herramientas)) {
                    $('#recoordinarModal #herramientas_recoordinar').val(data.herramientas.map(h => h.id)).trigger('change');
                }

                // Prioridad
                $('#es_priori_recoordinar').prop('checked', data.es_priori == 1);

                // Motivo de suspensión
                const motivoGroup = document.getElementById('motivoSuspensionGroup');
                const motivoTextarea = document.getElementById('motivoSuspension');
                if (motivoGroup && motivoTextarea) {
                    if (data.cotio_observaciones_suspension) {
                        motivoTextarea.value = data.cotio_observaciones_suspension;
                        motivoGroup.classList.remove('d-none');
                    } else {
                        motivoTextarea.value = '';
                        motivoGroup.classList.add('d-none');
                    }
                }

                // Variables requeridas
                const variablesContainer = document.getElementById('variables-container-recoordinacion');
                if (variablesContainer) {
                    variablesContainer.innerHTML = '';
                    Object.entries(data.variables_requeridas || {}).forEach(([tipoMuestra, variables]) => {
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

                        Object.values(variables).forEach(variable => {
                            const col = document.createElement('div');
                            col.className = 'col';
                            const formCheck = document.createElement('div');
                            formCheck.className = 'form-check form-check-inline';
                            const checkbox = document.createElement('input');
                            checkbox.className = 'form-check-input';
                            checkbox.type = 'checkbox';
                            checkbox.name = 'variables_seleccionadas[]';
                            checkbox.value = variable.id;
                            checkbox.id = `var-${tipoMuestra}-${variable.id}`;
                            if (Array.isArray(data.variables_seleccionadas) && data.variables_seleccionadas.includes(variable.id)) {
                                checkbox.checked = true;
                            }

                            const label = document.createElement('label');
                            label.className = 'form-check-label';
                            label.setAttribute('for', checkbox.id);
                            label.textContent = variable.nombre;
                            if (variable.obligatorio) {
                                const badge = document.createElement('span');
                                badge.className = 'badge bg-danger ms-1';
                                badge.textContent = 'Obligatorio';
                                label.appendChild(badge);
                            }

                            formCheck.appendChild(checkbox);
                            formCheck.appendChild(label);
                            col.appendChild(formCheck);
                            variablesGrid.appendChild(col);
                        });

                        categoryDiv.appendChild(variablesGrid);
                        variablesContainer.appendChild(categoryDiv);
                    });
                }
            } catch (e) {
                console.error('Error cargando datos de recoordinación', e);
            }
        });

        // Enviar recoordinación
        const guardarBtn = document.getElementById('guardarRecoordinacion');
        if (guardarBtn) {
            guardarBtn.addEventListener('click', async function () {
                const form = document.getElementById('recoordinarForm');
                const formData = new FormData(form);
                const jsonData = Object.fromEntries(formData.entries());

                jsonData.responsables_muestreo = formData.getAll('responsables_muestreo[]');
                jsonData.herramientas = formData.getAll('herramientas[]');
                jsonData.variables_seleccionadas = formData.getAll('variables_seleccionadas[]');
                jsonData.es_priori = document.getElementById('es_priori_recoordinar').checked ? 1 : 0;

                try {
                    const response = await fetch('/muestras/recoordinar', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(jsonData)
                    });

                    const result = await response.json();
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message || 'Error al recoordinar la muestra');
                    }
                } catch (e) {
                    console.error('Error enviando recoordinación', e);
                    alert('Error al recoordinar la muestra');
                }
            });
        }
    }
});

document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: @json(session('success')),
            confirmButtonColor: '#3085d6'
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: @json(session('error')),
            confirmButtonColor: '#d33'
        });
    @endif

    document.querySelectorAll('.js-swal-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const title = form.dataset.swalTitle || '¿Confirmar?';
            const text = form.dataset.swalText || '';
            const icon = form.dataset.swalIcon || 'question';
            const confirmColor = form.dataset.swalConfirmColor || '#3085d6';

            Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: confirmColor,
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('.js-form-subir-informe-mediciones').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const input = form.querySelector('input[type="file"][name="informe_pdf"]');
            if (input && (!input.files || !input.files.length)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Archivo requerido',
                    text: 'Seleccioná un informe en PDF antes de subirlo.',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
    });
});
</script>

@endsection
