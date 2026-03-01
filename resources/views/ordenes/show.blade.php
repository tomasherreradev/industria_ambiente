@extends('layouts.app')

<head>
    <title>Ver Orden {{$cotizacion->coti_num}}</title>
</head>

@section('content')
<div class="container py-4">
    <a href="{{ url('/ordenes') }}" class="btn btn-outline-secondary mb-4">← Volver a Ordenes</a>
    <h2 class="mb-4">Análisis de cotización <span class="text-primary">{{ $cotizacion->coti_num }}</span></h2>
    
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
                        <h5 class="mb-0">Análisis Pendientes</h5>
                        <x-heroicon-o-chevron-up id="chevron-muestras-pendientes-lg" class="text-white" style="width: 20px; height: 20px;" />
                    </div>
                    <div id="muestras-pendientes-lg" class="card-body collapse-content">
                        @php
                            $hasTareas = false;
                            foreach ($agrupadas as $grupo) {
                                foreach ($grupo['instancias'] as $instancia) {
                                    if ($instancia['analisis']->isNotEmpty()) {
                                        $hasTareas = true;
                                        break 2;
                                    }
                                }
                            }
                        @endphp

                        @if(!$hasTareas)
                            <div class="alert alert-warning">
                                No hay muestras registradas para esta cotización.
                            </div>
                        @else
                            @php
                                $mostradoTecnicoCampo = false;
                            @endphp
                            
                            @foreach($agrupadas as $grupo)
                                @if($grupo['categoria']->cotio_descripcion === 'TRABAJO TECNICO EN CAMPO' && !$mostradoTecnicoCampo)
                                    <div class="mb-4">
                                        <div class="card shadow-sm mi-tarjeta h-100">
                                            <p class="card-header text-white d-flex justify-content-between align-items-center flex-wrap bg-primary mb-0">
                                                Visita a planta requerida
                                            </p>
                                        </div>
                                    </div>
                                    @php $mostradoTecnicoCampo = true; @endphp
                                @endif
                            @endforeach

                            @foreach($agrupadas as $grupo)
                            @php
                                $categoria = $grupo['categoria'];
                                $instancias = $grupo['instancias'];
                                $headerClass = $categoria->active_ot ? 'bg-primary' : 'bg-secondary';
                            @endphp
                    
                            @foreach($instancias as $instancia)
                                @php
                                    $muestra = $instancia['muestra'];
                                    $responsables = $muestra->responsablesAnalisis ?? collect();
                                    
                                    // Verificar si es una instancia virtual (no persistida)
                                    $esInstanciaVirtual = !is_numeric($muestra->id);
                                    
                                    // ID para el checkbox (numcoti_item_subitem_instance para virtuales)
                                    $muestraId = $esInstanciaVirtual 
                                        ? "{$muestra->cotio_numcoti}_{$muestra->cotio_item}_0_{$muestra->instance_number}"
                                        : $muestra->id;
                                    
                                    // Define header and badge classes based on analysis status
                                    $headerClass = 'bg-secondary';
                                    $badgeClass = 'bg-secondary';
                                    if ($muestra->cotio_estado_analisis === 'coordinado analisis') {
                                        $headerClass = 'bg-warning';
                                        $badgeClass = 'bg-warning border border-white';
                                    } elseif ($muestra->cotio_estado_analisis === 'suspension') {
                                        $headerClass = 'bg-danger';
                                        $badgeClass = 'bg-danger border border-white';
                                    } elseif ($muestra->cotio_estado_analisis === 'analizado') {
                                        $headerClass = 'bg-success';
                                        $badgeClass = 'bg-success border border-white';
                                    } elseif ($muestra->cotio_estado_analisis === 'en revision analisis') {
                                        $headerClass = 'bg-success';
                                        $badgeClass = 'bg-info border border-white';
                                    }
                                @endphp
                                <div class="mb-4">
                                    <div class="card shadow-sm h-100" @if($muestra->es_priori) style="border: 2px solid #ffd700;" @endif>
                                        <div class="card-header {{ $headerClass }} text-white d-flex align-items-center justify-content-between flex-wrap p-3">
                                            <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                <!-- Checkbox for Sample -->
                                                @php
                                                    $muestraActiva = $muestra->active_ot;
                                                    $muestraTieneResultado = $muestra->resultado !== null;
                                                @endphp
                                                <div class="d-flex flex-column gap-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input instancia-checkbox"
                                                        data-id="{{ $muestraId }}"
                                                        data-item="{{ $categoria->cotio_item }}"
                                                        data-subitem="0"
                                                        data-instance="{{ $muestra->instance_number }}"
                                                        data-numcoti="{{ $muestra->cotio_numcoti }}"
                                                        data-descripcion="{{ $categoria->cotio_descripcion }}"
                                                        data-virtual="{{ $esInstanciaVirtual ? 'true' : 'false' }}"
                                                        @checked($muestra->active_ot)
                                                        data-user-toggled="false"
                                                        onchange="toggleInstancia(this)"
                                                        @disabled($muestraActiva || $muestraTieneResultado)
                                                        aria-label="Activar/Desactivar muestra {{ $categoria->cotio_descripcion }} (Instancia {{ $muestra->instance_number }})"
                                                    />
                                                    <!-- Checkbox auxiliar para seleccionar todos los restantes -->
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input checkbox-auxiliar"
                                                        data-id="{{ $muestraId }}"
                                                        data-item="{{ $categoria->cotio_item }}"
                                                        data-instance="{{ $muestra->instance_number }}"
                                                        data-numcoti="{{ $muestra->cotio_numcoti }}"
                                                        onchange="seleccionarTodosRestantes(this)"
                                                        style="display: none; transform: scale(0.8);"
                                                        title="Seleccionar todos los análisis restantes"
                                                        aria-label="Seleccionar todos los análisis restantes de {{ $categoria->cotio_descripcion }}"
                                                    />
                                                </div>
                                                <!-- Title and Link -->
                                                <div class="flex-grow-1">
                                                    <a 
                                                        href="{{ route('categoria.verOrden', [
                                                            'cotizacion' => $cotizacion->coti_num, 
                                                            'item' => $muestra->cotio_item,
                                                            'instance' => $muestra->instance_number
                                                        ]) }}" 
                                                        class="text-decoration-none text-white"
                                                    >
                                                        <div class="d-flex align-items-center gap-2">
                                                            <h6 class="mb-1 fw-bold">
                                                                {{ $categoria->cotio_descripcion }} (#{{ $muestra->otn ? $muestra->otn : $muestra->instance_number ?? 'N/A' }})
                                                                <small class="fw-normal">(Instancia {{ $muestra->instance_number }} / {{ $categoria->cotio_cantidad ?? '-' }})</small>
                                                            </h6>
                                                            @if($esInstanciaVirtual)
                                                                <div class="ms-2">
                                                                    <span class="badge bg-info text-white">
                                                                        <x-heroicon-o-plus-circle style="width: 12px; height: 12px;" class="me-1" />
                                                                        Nueva
                                                                    </span>
                                                                </div>
                                                            @elseif($muestra->active_ot)
                                                                <div class="ms-2">
                                                                    <span class="badge {{ $badgeClass }} text-white">
                                                                        {{ str_replace('_', ' ', ucwords($muestra->cotio_estado_analisis)) }}
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </a>
                                                        @if($muestra->active_ot && $muestra->coordinador)
                                                            <small class="text-light d-block">
                                                                Coordinado por {{ $muestra->coordinador->usu_descripcion }}
                                                            </small>
                                                        @endif
                                                    </a>
                                                </div>
                                            </div>
                    
                                            <!-- Re-coordination Button -->
                                            @if($muestra->cotio_estado_analisis === 'suspension' || $muestra->cotio_estado_analisis === 'coordinado analisis')
                                                    <div style="margin-right: 10px; margin-top: -10px;">
                                                        <button 
                                                        type="button" 
                                                        class="btn btn-sm btn-danger text-white"
                                                        onclick="confirmarRecoordinacion({{ $muestra->id }}, '{{ $cotizacion->coti_num }}')"
                                                        title="Recoordinar muestra"
                                                        aria-label="Recoordinar muestra {{ $categoria->cotio_descripcion }} (Instancia {{ $muestra->instance_number }})"
                                                    >
                                                        <i class="fas fa-sync-alt me-1"></i> Anular
                                                    </button>
                                                </div>
                                            @endif
                                            <!-- Analysts Section -->
                                            @if($responsables->isNotEmpty())
                                                <div>
                                                    <small class="text-light d-block mb-1">Analistas:</small>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($responsables as $responsable)
                                                            <span class="badge bg-light text-dark d-flex align-items-center gap-1">
                                                                {{ $responsable->usu_descripcion }}
                                                                <button 
                                                                    type="button" 
                                                                    class="btn-close btn-close-dark"
                                                                    style="font-size: 0.4rem;"
                                                                    onclick="removerResponsable(event, {{ $muestra->cotio_numcoti }}, {{ $muestra->id }}, '{{ $responsable->usu_codigo }}', 'analisis', true)"
                                                                    title="Remover analista"
                                                                    aria-label="Remover {{ $responsable->usu_descripcion }}"
                                                                ></button>
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            @if($instancia['muestra']->es_priori)
                                                <div class="mb-2 d-flex align-items-center justify-content-between">
                                                    <span data-bs-toggle="tooltip" 
                                                          data-bs-placement="bottom" 
                                                          data-bs-html="true"
                                                          data-bs-title="<i class='fas fa-star text-warning me-1'></i><strong>Muestra Prioritaria</strong><br><small>Esta muestra requiere atención especial</small>">
                                                        <x-heroicon-o-star style="width: 20px; height: 20px; color: #ffd700; cursor: pointer;" />
                                                    </span>
                                                </div>
                                            @endif

                                            <!-- QR Code Icon -->
                                            <div class="d-flex align-items-center gap-2">
                                                <a 
                                                    href="#"
                                                    class="text-decoration-none text-white"
                                                    title="Generar CT para esta muestra"
                                                    data-url="{{ route('qr.universal', [
                                                        'cotio_numcoti' => $cotizacion->coti_num, 
                                                        'cotio_item' => $categoria->cotio_item, 
                                                        'cotio_subitem' => 0,
                                                        'instance' => $muestra->instance_number
                                                    ]) }}"
                                                    data-coti="{{ $cotizacion->coti_num }}"
                                                    data-categoria="{{ $categoria->cotio_descripcion }}"
                                                    data-instance="{{ $muestra->instance_number }}"
                                                    data-fechaanalisis="{{ $muestra->fecha_ot ?? $muestra->fecha_inicio_ot }}"
                                                    onclick="generateQr(this)"
                                                >
                                                    <x-heroicon-o-qr-code class="text-white" style="width: 24px; height: 24px;"/>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            @if(isset($metodosUnicos) && $metodosUnicos->isNotEmpty())
                                                <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
                                                    <label for="select-metodo-{{ $instancia['muestra']->id }}" class="form-label mb-0 small">Seleccionar por método:</label>
                                                    <select 
                                                        id="select-metodo-{{ $instancia['muestra']->id }}" 
                                                        class="form-select form-select-sm" 
                                                        style="max-width: 300px;"
                                                        onchange="seleccionarPorMetodo(this, '{{ $instancia['muestra']->id }}')"
                                                    >
                                                        <option value="">-- Seleccionar método --</option>
                                                        @foreach($metodosUnicos as $metodo)
                                                            <option value="{{ trim($metodo->metodo_codigo) }}">
                                                                {{ $metodo->metodo_descripcion }} ({{ trim($metodo->metodo_codigo) }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-sm btn-outline-secondary"
                                                        onclick="deseleccionarTodos('{{ $instancia['muestra']->id }}')"
                                                    >
                                                        <i class="fas fa-times me-1"></i> Deseleccionar todos
                                                    </button>
                                                </div>
                                            @endif
                                            @foreach($instancia['analisis'] as $tarea)
                                                <div class="mb-2 p-2 border rounded @if($tarea->modulo_origen == 'muestreo') bg-light @endif">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            @if($tarea->modulo_origen == 'muestreo')
                                                                <input type="checkbox" class="form-check-input" disabled checked>
                                                                <span class="text-muted">
                                                                    {{ $tarea->cotio_descripcion }}
                                                                </span>
                                                            @else
                                                                @php
                                                                    $instanciaActiva = $tarea->instancia && $tarea->instancia->active_ot;
                                                                    $tieneResultado = optional($tarea->instancia)->resultado !== null;
                                                                    $metodoAnalisis = $tarea->instancia ? $tarea->instancia->getMetodoAnalisisConTrim() : null;
                                                                    $metodoCodigo = $tarea->instancia && $tarea->instancia->cotio_codigometodo_analisis ? trim($tarea->instancia->cotio_codigometodo_analisis) : '';
                                                                    
                                                                    // Generar ID: usar ID real si es numérico, sino generar ID virtual
                                                                    $tareaInstanciaId = $tarea->instancia && is_numeric($tarea->instancia->id) && $tarea->instancia->exists
                                                                        ? $tarea->instancia->id
                                                                        : ((int)$tarea->cotio_numcoti . '_' . (int)$tarea->cotio_item . '_' . (int)$tarea->cotio_subitem . '_' . (int)$instancia['muestra']->instance_number);
                                                                @endphp
                                                                <input
                                                                    type="checkbox"
                                                                    class="form-check-input tarea-checkbox"
                                                                    name="cotio_items[]"
                                                                    value="{{ $tarea->cotio_numcoti }}-{{ $tarea->cotio_item }}-{{ $tarea->cotio_subitem }}-{{ $instancia['muestra']->instance_number }}"
                                                                    data-id="{{ $tareaInstanciaId }}"
                                                                    data-item="{{ $tarea->cotio_item }}"
                                                                    data-subitem="{{ $tarea->cotio_subitem }}"
                                                                    data-instance="{{ $instancia['muestra']->instance_number }}"
                                                                    data-numcoti="{{ $tarea->cotio_numcoti }}"
                                                                    data-descripcion="{{ $tarea->cotio_descripcion }}"
                                                                    data-metodo="{{ $metodoCodigo }}"
                                                                    @checked($tarea->instancia->active_ot ?? false)
                                                                    data-user-toggled="false"
                                                                    @disabled($instanciaActiva)
                                                                />
                                                                {{ $tarea->cotio_descripcion }} {{ $metodoAnalisis ? ' - ' . $metodoAnalisis->metodo_descripcion : '' }}
                                                            @endif
                                                        </div>
                                                        <div>
                                                            @if($tarea->instancia ?? false)
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
                                                                <span class="badge bg-{{ $badgeClass }}">
                                                                    {{ ucfirst($tarea->instancia->cotio_estado_analisis) }}
                                                                </span>
                                                                @if($tarea->instancia->resultado)
                                                                    <span class="badge bg-info badge-resultado ms-1">
                                                                        Res: {{ $tarea->instancia->resultado }}
                                                                    </span>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<div class="modal fade" id="modalAsignacionMasiva" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignación masiva</h5>
                <div class="form-check form-switch ms-3" style="display: none;">
                    <input 
                        class="form-check-input" 
                        type="checkbox" 
                        id="aplicar_a_gemelas" 
                        name="aplicar_a_gemelas"
                        value="1"
                    >
                    <label class="form-check-label" for="aplicar_a_gemelas">
                        Aplicar a muestras gemelas
                        <i class="fas fa-info-circle text-info ms-1" 
                           data-bs-toggle="tooltip" 
                           title="Si está activado, la asignación se aplicará también a todas las muestras gemelas (mismo item/subitem pero diferente número de instancia)"></i>
                    </label>
                </div>
            </div>
            <div class="modal-body">
                <form id="formAsignacionMasiva">
                    <div class="mb-3">
                        <label for="responsables_analisis" class="form-label">Responsables de Análisis</label>
                        <select class="form-select select2-multiple" id="responsables_analisis" name="responsables_analisis[]" multiple="multiple">
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
                        <small class="text-muted">Seleccione múltiples herramientas con Ctrl+Click</small>
                    </div>

                    <div class="mb-3">
                        <label for="fecha_inicio_ot" class="form-label">Fecha y Hora de Inicio</label>
                        <input 
                            type="datetime-local" 
                            class="form-control" 
                            id="fecha_inicio_ot" 
                            name="fecha_inicio_ot"
                        >
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_fin_ot" class="form-label">Fecha y Hora de Fin</label>
                        <input 
                            type="datetime-local" 
                            class="form-control" 
                            id="fecha_fin_ot" 
                            name="fecha_fin_ot"
                        >
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarAsignacionMasiva()">Confirmar</button>
            </div>
        </div>
    </div>
</div>






<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <button 
        id="btn-asignacion-masiva" 
        class="btn btn-success shadow" 
        onclick="mostrarModalAsignacionMasiva()"
        disabled
    >
        <i class="fas fa-check-circle me-2"></i>Pasar a Análisis
    </button>
</div>

@endsection

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
    .disabled-checkbox {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .disabled-checkbox:checked {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .checkbox-auxiliar {
        border: 2px solid #28a745 !important;
        background-color: rgba(40, 167, 69, 0.1) !important;
    }

    .checkbox-auxiliar:hover {
        background-color: rgba(40, 167, 69, 0.2) !important;
        transform: scale(0.85) !important;
    }

    .checkbox-auxiliar:checked {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
    }
</style>

<script>
    let cambiosPendientes = {};
    let hasChanges = false;

    function verificarAnalisisSeleccionados() {
        const checkboxesAnalisis = document.querySelectorAll('.tarea-checkbox:checked:not(:disabled)');
        const btnAsignacionMasiva = document.getElementById('btn-asignacion-masiva');
        btnAsignacionMasiva.disabled = checkboxesAnalisis.length === 0;
    }

    /**
     * Seleccionar todos los análisis que usen un método específico
     */
    function seleccionarPorMetodo(selectElement, muestraId) {
        const metodoCodigo = selectElement.value;
        
        if (!metodoCodigo) {
            return;
        }

        // Buscar todos los checkboxes de análisis en esta muestra que tengan el método seleccionado
        const cardBody = selectElement.closest('.card-body');
        if (!cardBody) return;

        const checkboxes = cardBody.querySelectorAll('.tarea-checkbox:not(:disabled)');
        let seleccionados = 0;

        checkboxes.forEach(checkbox => {
            const checkboxMetodo = checkbox.getAttribute('data-metodo');
            if (checkboxMetodo && trim(checkboxMetodo) === trim(metodoCodigo)) {
                if (!checkbox.checked) {
                    checkbox.checked = true;
                    checkbox.setAttribute('data-user-toggled', 'true');
                    // Llamar a toggleTarea para actualizar las selecciones
                    toggleTarea(checkbox);
                    seleccionados++;
                }
            }
        });

        // Resetear el select
        selectElement.value = '';

        // Mostrar mensaje
        if (seleccionados > 0) {
            Swal.fire({
                icon: 'success',
                title: 'Análisis seleccionados',
                text: `Se seleccionaron ${seleccionados} análisis con el método elegido`,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Sin resultados',
                text: 'No se encontraron análisis con ese método en esta muestra o ya estaban seleccionados',
                timer: 2000,
                showConfirmButton: false
            });
        }

        // Actualizar el botón de asignación masiva
        actualizarBotonAsignacionMasiva();
    }

    /**
     * Deseleccionar todos los análisis de una muestra
     */
    function deseleccionarTodos(muestraId) {
        // Buscar el card-body correspondiente
        const selectElement = document.getElementById('select-metodo-' + muestraId);
        if (!selectElement) return;

        const cardBody = selectElement.closest('.card-body');
        if (!cardBody) return;

        const checkboxes = cardBody.querySelectorAll('.tarea-checkbox:not(:disabled)');
        let deseleccionados = 0;

        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                checkbox.checked = false;
                checkbox.setAttribute('data-user-toggled', 'false');
                // Llamar a toggleTarea para actualizar las selecciones
                toggleTarea(checkbox);
                deseleccionados++;
            }
        });

        // Actualizar el botón de asignación masiva
        actualizarBotonAsignacionMasiva();

        if (deseleccionados > 0) {
            Swal.fire({
                icon: 'success',
                title: 'Análisis deseleccionados',
                text: `Se deseleccionaron ${deseleccionados} análisis`,
                timer: 1500,
                showConfirmButton: false
            });
        }
    }

    /**
     * Función auxiliar para hacer trim (compatible con espacios)
     */
    function trim(str) {
        return str ? str.toString().trim() : '';
    }

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

    window.removerResponsable = function(event, ordenId, instanciaId, userCodigo, tipo, todos = true) {
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
            fetch(`/ordenes/${ordenId}/remover-responsable`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    instancia_id: parseInt(instanciaId),
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

    function toggleTareas(checkbox) {
        const item = checkbox.dataset.item;
        const instance = checkbox.dataset.instance;
        const isChecked = checkbox.checked;
        
        const muestraValue = `${item}-0-${instance}`;
        cambiosPendientes[muestraValue] = isChecked;
        hasChanges = true;
        
        document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"]`).forEach(tareaCheckbox => {
            const hasResult = tareaCheckbox.closest('.border.rounded').querySelector('.badge-resultado') !== null;
            
            if (!hasResult) {
                tareaCheckbox.checked = isChecked;
                actualizarEstadoTarea(tareaCheckbox, true);
            }
        });
        
        document.getElementById('btn-aplicar-cambios').disabled = !hasChanges;
        verificarAnalisisSeleccionados();
    }


    function actualizarEstadoTarea(checkbox, isMassUpdate = false) {
        if (checkbox.disabled) return;

        const value = checkbox.value;
        const isChecked = checkbox.checked;
        
        if (cambiosPendientes[value] !== isChecked) {
            cambiosPendientes[value] = isChecked;
            hasChanges = Object.values(cambiosPendientes).some(v => v !== undefined);
            
            const taskContainer = checkbox.closest('.border.rounded');
            if (taskContainer) {
                taskContainer.classList.toggle('border-primary', isChecked);
                taskContainer.classList.toggle('bg-light', isChecked);
            }
        }
        
        document.getElementById('btn-aplicar-cambios').disabled = !hasChanges;
        verificarAnalisisSeleccionados(); // Verificar selección después de cambios
        
        if (!isMassUpdate) {
            updateInstanciaCheckbox(checkbox);
        }
    }


    function updateInstanciaCheckbox(checkbox) {
        const itemId = checkbox.dataset.item;
        const instanceId = checkbox.dataset.instance;
        const instanciaCheckbox = document.querySelector(`.instancia-checkbox[data-item="${itemId}"][data-instance="${instanceId}"]`);
        
        if (instanciaCheckbox) {
            // Contar solo análisis sin resultado
            const allTareasSinResultado = document.querySelectorAll(`
                .tarea-checkbox[data-item="${itemId}"][data-instance="${instanceId}"]:not([disabled])
            `);
            
            const checkedTareasSinResultado = document.querySelectorAll(`
                .tarea-checkbox[data-item="${itemId}"][data-instance="${instanceId}"]:not([disabled]):checked
            `);
            
            // Verificar estado de la muestra también
            const muestraValue = `${itemId}-0-${instanceId}`;
            const muestraChecked = cambiosPendientes[muestraValue] !== undefined ? 
                                cambiosPendientes[muestraValue] : 
                                instanciaCheckbox.checked;
            
            instanciaCheckbox.checked = checkedTareasSinResultado.length === allTareasSinResultado.length && muestraChecked;
            instanciaCheckbox.indeterminate = (checkedTareasSinResultado.length > 0 && checkedTareasSinResultado.length < allTareasSinResultado.length) || 
                                            (checkedTareasSinResultado.length === allTareasSinResultado.length && !muestraChecked);
        }
    }

    function confirmarCambios() {
        if (!hasChanges) return;

        const cambiosFormateados = Object.entries(cambiosPendientes)
            .filter(([_, value]) => value !== undefined)
            .map(([key, value]) => {
                const [numcoti, item, subitem, instance] = key.split('-');
                return {
                    numcoti: numcoti,
                    item: parseInt(item),
                    subitem: parseInt(subitem),
                    instance: parseInt(instance),
                    activo: value
                };
            });

        Swal.fire({
            title: '¿Confirmar paso a análisis?',
            html: `Se procesarán <b>${cambiosFormateados.length}</b> elementos seleccionados`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarCambios(cambiosFormateados);
            }
        });
    }

    function enviarCambios(cambios) {
        const url = '{{ route("tareas.pasar-analisis") }}';
        const data = {
            cotizacion_id: '{{ $cotizacion->coti_num }}',
            cambios: cambios,
            _token: '{{ csrf_token() }}'
        };

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: '¡Éxito!',
                    html: data.message,
                    icon: 'success'
                }).then(() => {
                    window.location.reload();
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
            Swal.fire({
                title: 'Error',
                text: 'Error al comunicarse con el servidor',
                icon: 'error'
            });
            console.error('Error:', error);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    document.querySelectorAll('.tarea-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            actualizarEstadoTarea(this);
        });
    });
    
    document.querySelectorAll('.instancia-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleTareas(this);
        });
    });
    
        verificarAnalisisSeleccionados();
    });




    let seleccionesInstancias = [];
    let seleccionesTareas = [];


    function initializeSelections() {
        document.querySelectorAll('.instancia-checkbox').forEach(checkbox => {
            if (checkbox.checked) {
                const id = checkbox.dataset.id;
                if (!seleccionesInstancias.includes(id)) {
                    seleccionesInstancias.push(id);
                }
            }
        });

        document.querySelectorAll('.tarea-checkbox:not(:disabled)').forEach(checkbox => {
            if (checkbox.checked) {
                const id = checkbox.dataset.id;
                if (!seleccionesTareas.includes(id)) {
                    seleccionesTareas.push(id);
                }
            }
        });

        actualizarBotonAsignacionMasiva();
    }

    function toggleInstancia(checkbox) {
        const id = checkbox.dataset.id;
        const item = checkbox.dataset.item;
        const subitem = checkbox.dataset.subitem;
        const instance = checkbox.dataset.instance;
        const numcoti = checkbox.dataset.numcoti;
        const descripcion = checkbox.dataset.descripcion;
        const isChecked = checkbox.checked;
        
        console.log('toggleInstancia llamado:', { id, item, instance, numcoti, isChecked });

        // Update seleccionesInstancias
        if (isChecked) {
            if (!seleccionesInstancias.includes(id)) {
                seleccionesInstancias.push(id);
                console.log('Agregado a seleccionesInstancias:', id);
            }
        } else {
            seleccionesInstancias = seleccionesInstancias.filter(i => i !== id);
            console.log('Removido de seleccionesInstancias:', id);
        }

        checkbox.dataset.userToggled = true;

        // Buscar y marcar todos los checkboxes de análisis de esta muestra
        const tareasCheckboxes = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled)`);
        console.log('Tareas encontradas para esta muestra:', tareasCheckboxes.length);
        
        tareasCheckboxes.forEach(tareaCheckbox => {
            console.log('Procesando tarea:', { id: tareaCheckbox.dataset.id, subitem: tareaCheckbox.dataset.subitem });
            tareaCheckbox.checked = isChecked;
            toggleTarea(tareaCheckbox);
        });

        // Actualizar el checkbox auxiliar después de cambiar la instancia
        actualizarCheckboxAuxiliar(item, instance, numcoti);
        actualizarBotonAsignacionMasiva();
    }

    function toggleTarea(checkbox) {
        const id = checkbox.dataset.id;
        const isChecked = checkbox.checked;
        
        console.log('toggleTarea llamado:', { id, isChecked, userToggled: checkbox.dataset.userToggled });

        if (checkbox.dataset.userToggled !== undefined) {
            if (isChecked) {
                if (!seleccionesTareas.includes(id)) {
                    seleccionesTareas.push(id);
                    console.log('Agregado a seleccionesTareas:', id, 'Total:', seleccionesTareas);
                }
            } else {
                seleccionesTareas = seleccionesTareas.filter(i => i !== id);
                console.log('Removido de seleccionesTareas:', id, 'Total:', seleccionesTareas);
            }
        }

        checkbox.dataset.userToggled = true;
        updateInstanciaCheckbox(checkbox);

        actualizarBotonAsignacionMasiva();
    }

    function updateInstanciaCheckbox(checkbox) {
        const item = checkbox.dataset.item;
        const instance = checkbox.dataset.instance;
        const numcoti = checkbox.dataset.numcoti;
        const instanciaCheckbox = document.querySelector(`.instancia-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]`);

        if (instanciaCheckbox && !instanciaCheckbox.disabled) {
            // Para el estado del checkbox principal, solo considerar tareas habilitadas
            const allTareasHabilitadas = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled)`);
            const checkedTareasHabilitadas = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled):checked`);

            instanciaCheckbox.checked = checkedTareasHabilitadas.length === allTareasHabilitadas.length && allTareasHabilitadas.length > 0;
            instanciaCheckbox.indeterminate = checkedTareasHabilitadas.length > 0 && checkedTareasHabilitadas.length < allTareasHabilitadas.length;
        }
        
        // Siempre actualizar el checkbox auxiliar (independiente del estado del principal)
        actualizarCheckboxAuxiliar(item, instance, numcoti);
    }

    function actualizarBotonAsignacionMasiva() {
        const btnAsignacionMasiva = document.getElementById('btn-asignacion-masiva');
        btnAsignacionMasiva.disabled = seleccionesInstancias.length === 0 && seleccionesTareas.length === 0;
    }

    function actualizarCheckboxAuxiliar(item, instance, numcoti) {
        const checkboxAuxiliar = document.querySelector(`.checkbox-auxiliar[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]`);
        const instanciaCheckbox = document.querySelector(`.instancia-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]`);
        
        if (!checkboxAuxiliar || !instanciaCheckbox) return;

        // Solo mostrar el checkbox auxiliar si la categoría ya está activa (active_ot = true)
        const categoriaEstaActiva = instanciaCheckbox.checked && instanciaCheckbox.disabled;
        
        if (!categoriaEstaActiva) {
            checkboxAuxiliar.style.display = 'none';
            checkboxAuxiliar.checked = false;
            return;
        }

        // Considerar TODAS las tareas (habilitadas y deshabilitadas) para determinar selección parcial
        const allTareas = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]`);
        const checkedTareas = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:checked`);
        
        // Solo considerar tareas no marcadas y habilitadas para el auxiliar
        const tareasRestantesSeleccionables = document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled):not(:checked)`);
        
        // Mostrar checkbox auxiliar si:
        // 1. La categoría está activa (ya verificado arriba)
        // 2. Hay al menos una tarea marcada (checkedTareas.length > 0)
        // 3. No están todas marcadas (checkedTareas.length < allTareas.length)  
        // 4. Hay tareas restantes que se pueden seleccionar (tareasRestantesSeleccionables.length > 0)
        const haySeleccionParcial = checkedTareas.length > 0 && 
                                   checkedTareas.length < allTareas.length && 
                                   tareasRestantesSeleccionables.length > 0;
        
        if (haySeleccionParcial) {
            checkboxAuxiliar.style.display = 'block';
            checkboxAuxiliar.checked = false; // Siempre empieza desmarcado
            // Actualizar el tooltip para mostrar cuántas tareas se pueden seleccionar
            checkboxAuxiliar.title = `Seleccionar ${tareasRestantesSeleccionables.length} análisis restantes`;
        } else {
            checkboxAuxiliar.style.display = 'none';
            checkboxAuxiliar.checked = false;
        }
    }

    function seleccionarTodosRestantes(checkbox) {
        const item = checkbox.dataset.item;
        const instance = checkbox.dataset.instance;
        const numcoti = checkbox.dataset.numcoti;
        const isChecked = checkbox.checked;
        
        // Seleccionar todas las tareas no marcadas
        document.querySelectorAll(`.tarea-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]:not(:disabled):not(:checked)`).forEach(tareaCheckbox => {
            if (isChecked) {
                tareaCheckbox.checked = true;
                toggleTarea(tareaCheckbox);
            }
        });
        
        // Si se seleccionan todos los restantes, actualizar el checkbox principal
        if (isChecked) {
            const instanciaCheckbox = document.querySelector(`.instancia-checkbox[data-item="${item}"][data-instance="${instance}"][data-numcoti="${numcoti}"]`);
            if (instanciaCheckbox && !instanciaCheckbox.disabled) {
                instanciaCheckbox.checked = true;
                instanciaCheckbox.indeterminate = false;
                
                // Actualizar la selección de la instancia
                const id = instanciaCheckbox.dataset.id;
                if (!seleccionesInstancias.includes(id)) {
                    seleccionesInstancias.push(id);
                }
            }
        }
        
        // Ocultar el checkbox auxiliar después de usarlo
        checkbox.style.display = 'none';
        checkbox.checked = false;
        
        actualizarBotonAsignacionMasiva();
    }

    function mostrarModalAsignacionMasiva() {
        if (seleccionesInstancias.length === 0 && seleccionesTareas.length === 0) {
            Swal.fire({
                title: 'Error',
                text: 'Debe seleccionar al menos una instancia o análisis para asignación masiva',
                icon: 'error'
            });
            return;
        }

        // Solo establecer valores por defecto si los campos están vacíos
        const fechaInicioInput = document.getElementById('fecha_inicio_ot');
        const fechaFinInput = document.getElementById('fecha_fin_ot');
        
        if (!fechaInicioInput.value && !fechaFinInput.value) {
            const now = new Date();
            now.setHours(8, 0, 0, 0);
            fechaInicioInput.value = formatDateTimeForInput(now);
            
            const endDate = new Date(now);
            endDate.setHours(18, 0, 0, 0);
            fechaFinInput.value = formatDateTimeForInput(endDate);
        }

        const modal = new bootstrap.Modal(document.getElementById('modalAsignacionMasiva'));
        modal.show();
    }

    function formatDateTimeForInput(date) {
        return date.toISOString().slice(0, 16);
    }

    function validarFechas() {
        const fechaInicio = new Date(document.getElementById('fecha_inicio_ot').value);
        const fechaFin = new Date(document.getElementById('fecha_fin_ot').value);

        if (fechaInicio && fechaFin && fechaFin <= fechaInicio) {
            Swal.fire({
                title: 'Error',
                text: 'La fecha de fin debe ser posterior a la fecha de inicio',
                icon: 'error'
            });
            return false;
        }

        // Permitir sábados y domingos - eliminada la validación que los bloqueaba

        return true;
    }

    function confirmarAsignacionMasiva() {
        if (!validarFechas()) return;

        const form = document.getElementById('formAsignacionMasiva');
        const responsables = Array.from(form['responsables_analisis[]'].selectedOptions).map(opt => opt.value);

        // DEBUG: Log de selecciones
        console.log('=== DEBUG ASIGNACION MASIVA ===');
        console.log('seleccionesInstancias:', seleccionesInstancias);
        console.log('seleccionesTareas:', seleccionesTareas);

        const data = {
            instancia_selecciones: seleccionesInstancias,
            tarea_selecciones: seleccionesTareas,
            responsables_analisis: responsables,
            herramientas_lab: Array.from(form.herramientas.selectedOptions).map(opt => opt.value),
            fecha_inicio_ot: form.fecha_inicio_ot.value,
            fecha_fin_ot: form.fecha_fin_ot.value,
            aplicar_a_gemelas: document.getElementById('aplicar_a_gemelas').checked
        };
        
        console.log('Data a enviar:', data);

        const mensaje = document.getElementById('aplicar_a_gemelas').checked 
            ? `Esta acción afectará a ${seleccionesInstancias.length} instancias y ${seleccionesTareas.length} análisis, incluyendo sus instancias gemelas`
            : `Esta acción afectará a ${seleccionesInstancias.length} instancias y ${seleccionesTareas.length} análisis`;

        Swal.fire({
            title: '¿Confirmar asignación masiva?',
            text: mensaje,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarAsignacionMasiva(data);
            }
        });
    }

    function enviarAsignacionMasiva(data) {
        const url = '{{ route("ordenes.asignacionMasiva", $cotizacion->coti_num) }}';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                ...data,
                _token: '{{ csrf_token() }}'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: '¡Éxito!',
                    text: data.message,
                    icon: 'success'
                }).then(() => {
                    window.location.href = '{{ route("ordenes.index") }}';
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
                text: 'Ocurrió un error al procesar la solicitud',
                icon: 'error'
            });
        });
    }

    // Initialization
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize Select2 for laboratory tools
        $('#herramientas').select2({
            placeholder: "Seleccione herramientas de laboratorio",
            width: '100%',
            dropdownParent: $('#modalAsignacionMasiva')
        });

        $('#responsables_analisis').select2({
            placeholder: "Seleccione responsables",
            width: '100%',
            dropdownParent: $('#modalAsignacionMasiva')
        });

        // Event listeners for checkboxes
        document.querySelectorAll('.tarea-checkbox:not(:disabled)').forEach(checkbox => {
            checkbox.addEventListener('change', () => toggleTarea(checkbox));
        });

        document.querySelectorAll('.instancia-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => toggleInstancia(checkbox));
        });

        // Ajustar fechas automáticamente cuando se selecciona una
        document.getElementById('fecha_inicio_ot').addEventListener('change', function() {
            const startDateInput = this;
            const endDateInput = document.getElementById('fecha_fin_ot');
            
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
            validarFechas();
        });

        document.getElementById('fecha_fin_ot').addEventListener('change', function() {
            const endDateInput = this;
            const startDateInput = document.getElementById('fecha_inicio_ot');
            
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
            validarFechas();
        });

        // Inicializar estado de checkboxes auxiliares para todas las instancias
        document.querySelectorAll('.checkbox-auxiliar').forEach(checkboxAuxiliar => {
            const item = checkboxAuxiliar.dataset.item;
            const instance = checkboxAuxiliar.dataset.instance;
            const numcoti = checkboxAuxiliar.dataset.numcoti;
            actualizarCheckboxAuxiliar(item, instance, numcoti);
        });
    });

    function confirmarRecoordinacion(instanciaId, cotizacionId) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción deshará todas las asignaciones de esta muestra y sus análisis. ¿Deseas continuar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, recoordinar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                deshacerAsignaciones(instanciaId, cotizacionId);
            }
        });
    }

    function deshacerAsignaciones(instanciaId, cotizacionId) {
        fetch(`/ordenes/${cotizacionId}/deshacer-asignaciones`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                instancia_id: instanciaId,
                cotizacion_id: cotizacionId
            })
        })
        .then(response => response.json())
        .then(data => {
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
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Ocurrió un error al deshacer las asignaciones',
                icon: 'error'
            });
        });
    }
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
    window.generateQr = function(element) {
        const url = element.dataset.url;
        const coti = element.dataset.coti;
        const categoria = element.dataset.categoria;
        const instance = element.dataset.instance;
        const fechaAnalisis = element.dataset.fechaanalisis;  
        const existing = document.getElementById('dynamicQrModal');
        if (existing) existing.remove();

        console.log(fechaAnalisis);

        const modal = document.createElement('div');
        modal.id = 'dynamicQrModal';
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">CT ${coti} - Categoría: ${categoria} - Fecha: ${fechaAnalisis || 'No asignada'}</h5>
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
                        <button onclick="printQr('${url}', '${coti}', '${categoria}', '${instance}', '${fechaAnalisis}')" class="btn btn-primary">
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

    window.printQr = function(url, coti, categoria, instance, fechaAnalisis) {
        const win = window.open('', '_blank');
        win.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Imprimir CT - Orden ${coti}</title>
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
                    <p><strong>Análisis:</strong> ${categoria}</p>
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