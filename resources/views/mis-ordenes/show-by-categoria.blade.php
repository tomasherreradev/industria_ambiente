@extends('layouts.app')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

{{-- @dd($instancia); --}}

@section('content')
<div class="container py-4">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
            Detalle de Muestra
            @if($instanceNumber)
                <span class="fs-5 text-muted">(Muestra #{{ $instancia->instance_number }})</span>
            @endif
        </h1>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                {{ $instancia->cotio_descripcion ?? 'N/A' }}
                @php
                    $estadoMuestra = strtolower($instancia->cotio_estado_analisis ?? 'pendiente');
                    $badgeClassMuestra = match ($estadoMuestra) {
                        'coordinado muestreo' => 'warning',
                        'pendiente' => 'warning',
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
                <span class="badge bg-{{ $badgeClassMuestra }} ms-2">
                    {{ ucfirst($instancia->cotio_estado_analisis ?? 'pendiente') }}
                </span>
            </h5>

            @if(Auth::user()->rol != 'laboratorio')

                <div>
                    <div class="btn-group" role="group">
                        @if($instancia->cotio_estado_analisis != 'suspension')
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#suspenderModal">
                            <i class="fas fa-pause me-1"></i> Suspender
                        </button>
                        @else
                        <button type="button" class="btn btn-sm btn-secondary" disabled>
                            <i class="fas fa-pause me-1"></i> Ya suspendida
                        </button>
                        @endif
                    </div>
    
                    <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editMuestraModal">
                        Editar Muestra
                    </button>
                </div>
            @endif

        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p><strong>Cotización:</strong> {{ $instancia->cotio_numcoti ?? 'N/A' }}</p>
                </div>
                <div class="col-md-4">
                    <p><strong>Fecha Inicio:</strong> 
                        {{ $instancia->fecha_inicio_ot ? \Carbon\Carbon::parse($instancia->fecha_inicio_ot)->format('d/m/Y') : 'N/A' }}
                    </p>
                    <p><strong>Fecha Fin:</strong> 
                        {{ $instancia->fecha_fin_ot ? \Carbon\Carbon::parse($instancia->fecha_fin_ot)->format('d/m/Y') : 'N/A' }}
                    </p>
                </div>

                <div class="col-md-4">
                    <p><strong>Identificación:</strong> {{ $instancia->cotio_identificacion ?? 'N/A' }}</p>
                </div>


                <div class="col-md-4">
                    @if($instancia->image)
                        <img src="{{ Storage::url('images/' . $instancia->image) }}" alt="Imagen de la muestra" class="img-fluid w-50 rounded">
                    @else
                        <p>No hay imagen disponible</p>
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

            @if($instancia->herramientasLab->count() > 0)
            <div class="mt-3 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6>Herramientas asignadas</h6>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editHerramientasModal">
                        <i class="fas fa-edit"></i> Editar Herramientas
                    </button>
                </div>
                <div class="row">
                    @foreach($instancia->herramientasLab as $herramienta)
                        <div class="col-md-4 mb-2">
                            <div class="card border">
                                <div class="card-body p-2">
                                    <h6 class="mb-1">{{ $herramienta->equipamiento }}</h6>
                                    <p class="small mb-1 text-muted">{{ $herramienta->marca_modelo }}</p>
                                    {{-- <p class="small mb-1"><strong>Cantidad:</strong> {{ $herramienta->pivot->cantidad }}</p> --}}
                                    @if($herramienta->pivot->observaciones)
                                        <p class="small mb-1"><strong>Observaciones:</strong> {{ $herramienta->pivot->observaciones }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="alert alert-info mt-3">
                No hay herramientas asignadas. 
                <button type="button" class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#editHerramientasModal">
                    <i class="fas fa-plus"></i> Agregar Herramientas
                </button>
            </div>
        @endif
        
        <!-- Modal para editar herramientas -->
        <div class="modal fade" id="editHerramientasModal" tabindex="-1" aria-labelledby="editHerramientasModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="formEditarHerramientas" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title" id="editHerramientasModalLabel">Editar Herramientas Asignadas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                            <div class="mb-3">
                                <label class="form-label">Herramientas Disponibles</label>
                                <select id="herramientasSelect" class="form-select select2-herramientas" name="herramientas[]" multiple="multiple" style="width: 100%;"></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar y Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        </div>
    </div>



    <div class="card shadow-sm my-5">
        <div class="card-header bg-secondary text-white">
            <h5 style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#medicionesCollapse" aria-expanded="false" aria-controls="medicionesCollapse">
                Mediciones de Campo
            </h5>
        </div>
      
        <div id="medicionesCollapse" class="collapse">
            <div class="card-body">
                <div class="mt-3">
                    @if($instancia->valoresVariables->isEmpty())
                        <div class="alert alert-info">
                            No se registraron mediciones para esta muestra.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Variable</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($instancia->valoresVariables as $valorVariable)
                                        <tr>
                                            <td>{{ $valorVariable->variable }}</td>
                                            <td>{{ $valorVariable->valor }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>  
        </div>
    </div>



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
                <div class="accordion p-2" id="analisisAccordion">
                    @foreach($analisis as $item)
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header" id="heading{{ $item->cotio_subitem }}">
                                <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse{{ $item->cotio_subitem }}" aria-expanded="false" 
                                    aria-controls="collapse{{ $item->cotio_subitem }}">
                                    <div class="d-flex justify-content-between w-100 pe-3">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2">#{{ $item->cotio_subitem }}</span>
                                            {{ $item->cotio_descripcion }}
                                            @if($item->request_review)
                                                <div class="bg-warning rounded-pill ms-2 d-flex align-items-center justify-content-center" style="width: 1.8rem; height: 1.8rem;"
                                                data-bs-toggle="tooltip" 
                                                data-bs-placement="bottom"
                                                title="Revisión de Resultados Pendiente. Se necesita recalcular los resultados para confirmar los valores.">
                                                    <x-heroicon-o-exclamation-triangle style="width: 1rem; height: 1rem; color: black;" />
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            @php
                                                $estado = strtolower($item->cotio_estado_analisis ?? 'pendiente');
                                                $badgeClassAnalisis = match ($estado) {
                                                    'coordinado muestreo' => 'warning',
                                                    'pendiente' => 'warning',
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
                                            <span class="badge bg-{{ $badgeClassAnalisis }} me-2">
                                                {{ ucfirst($estado) }}
                                            </span>
                                            <span class="text-muted small">Resultado Final: {{ $item->resultado_final ?? 'N/A' }} </span>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse{{ $item->cotio_subitem }}" class="accordion-collapse collapse" 
                                aria-labelledby="heading{{ $item->cotio_subitem }}" data-bs-parent="#analisisAccordion">
                                <div class="accordion-body pt-3">
                             
                                    @if($instancia->cotio_estado_analisis != 'analizado' && ($item->cotio_estado_analisis == 'coordinado analisis' || $item->cotio_estado_analisis == 'en revision analisis'))
                                        <div class="d-flex justify-content-end mb-3">
                                            <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" 
                                                data-bs-target="#editAnalisisModal{{ $item->cotio_subitem }}">
                                                Agregar Resultado
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

   

   <!-- Modales para editar análisis -->
    @foreach($analisis as $item)
    <div class="modal fade" id="editAnalisisModal{{ $item->cotio_subitem }}" tabindex="-1" aria-hidden="true" data-subitem="{{ $item->cotio_subitem }}">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Análisis #{{ $item->cotio_descripcion }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>



                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('tareas.onlyUpdateResultado', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'cotio_subitem' => $item->cotio_subitem,
                        'instance' => $instanceNumber
                    ]) }}"
                    data-update-url="{{ route('tareas.updateResultado', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'cotio_subitem' => $item->cotio_subitem,
                        'instance' => $instanceNumber
                    ]) }}"
                    data-only-url="{{ route('tareas.onlyUpdateResultado', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'cotio_subitem' => $item->cotio_subitem,
                        'instance' => $instanceNumber
                    ]) }}">
                        @csrf
                        @method('PUT')
                        

                        <div class="d-flex justify-content-between flex-md-row flex-column">
                            <div class="mb-3">
                                <div>
                                    <label for="resultado" class="form-label">Resultado</label>
                                    <input type="text" class="form-control" name="resultado" rows="4" value="{{ $item->resultado }}"
                                        placeholder="Ingrese los resultados del análisis"></input>
                                </div>

                                <div>
                                    <input type="text" class="form-control" name="observacion_resultado" rows="4" value="{{ $item->observacion_resultado }}"
                                        placeholder="Observación"></input>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div>
                                    <label for="resultado_2" class="form-label">Resultado 2</label>
                                    <input type="text" class="form-control" name="resultado_2" rows="4" value="{{ $item->resultado_2 }}"
                                        placeholder="Ingrese los resultados del análisis"></input>
                                </div>

                                <div>
                                    <input type="text" class="form-control" name="observacion_resultado_2" rows="4" value="{{ $item->observacion_resultado_2 }}"
                                        placeholder="Observación"></input>
                                </div>
                            </div>


                            <div class="mb-3">
                                <div>
                                    <label for="resultado_3" class="form-label">Resultado 3</label>
                                    <input type="text" class="form-control" name="resultado_3" rows="4" value="{{ $item->resultado_3 }}"
                                        placeholder="Ingrese los resultados del análisis"></input>
                                </div>

                                <div>
                                    <input type="text" class="form-control" name="observacion_resultado_3" rows="4" value="{{ $item->observacion_resultado_3 }}"
                                        placeholder="Observación"></input>
                                </div>
                            </div>
                        </div>
          
                        
                        <div class="mb-3">
                            <label for="resultado_final" class="form-label">Resultado Final</label>
                            <textarea class="form-control" name="resultado_final" rows="4" value="{{ $item->resultado_final }}"
                                placeholder="Ingrese los resultados del análisis">{{ $item->resultado_final ?? '' }}</textarea>
                        </div>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <label for="u_med_resultado" class="form-label">Unidad de medición</label>
                            <input type="text" class="form-control" name="u_med_resultado" value="{{ $item->cotio_codigoum }}"
                                placeholder="No disponible" readonly>
                        </div>
                    
                        {{-- linea separadora --}}
                        <div style="margin: 1.5rem 0;">
                            <hr style="border: 1px solid #52ABDA;">
                        </div>

                        <div style="margin: 2rem 0;">
                            <div>
                                <label for="image_resultado_final" class="form-label">Imagen Resultado (Opcional)</label>
                                <input type="file" class="form-control" name="image_resultado_final" accept="image/*">
                                    @if ($item->image_resultado_final)
                                        <div class="mt-2">
                                            <p>Imagen actual:</p>
                                            <img src="{{ asset('storage/' . $item->image_resultado_final) }}" alt="Resultado Imagen" style="max-width: 100px;">
                                            <p><a class="btn btn-primary mt-1" href="{{ asset('storage/' . $item->image_resultado_final) }}" target="_blank">Ver imagen</a></p>
                                        </div>
                                    @endif
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="observacion_resultado_final" class="form-label">Observación</label>
                            <textarea class="form-control" name="observacion_resultado_final" rows="4"
                                placeholder="Observación">{{ $item->observacion_resultado_final ?? '' }}</textarea>
                        </div>

                        <div class="mb-3 p-3 bg-warning bg-opacity-25 rounded border border-warning">
                            <label for="observaciones_ot" class="form-label fw-bold text-dark">
                                Observaciones del Coordinador
                            </label>
                            <textarea 
                                class="form-control" 
                                name="observaciones_ot" 
                                id="observaciones_ot" 
                                rows="3"
                                placeholder="Observaciones del coordinador sobre este análisis..." 
                                readonly
                            >{{ $item->observaciones_ot ?? '' }}</textarea>
                        </div>
                        

                        <div class="mb-3">
                            <x-heroicon-o-calendar style="width: 1rem; height: 1rem;" />
                            <small class="form-text text-muted">El sistema asignará la fecha actual automáticamente</small>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary me-2 btn-only-save">Guardar</button>
                            <button type="button" class="btn btn-primary btn-save-send">Guardar y Enviar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
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

    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        padding: 0.375rem 0.75rem;
    }
    #editHerramientasModal .modal-body {
        max-height: 400px;
        overflow-y: auto;
    }
    #editHerramientasModal .select2-container {
        width: 100% !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Manejar botones Guardar y Guardar y Enviar en modales de análisis
        document.querySelectorAll('form[action*="resultado"]').forEach(form => {
            const btnOnly = form.querySelector('.btn-only-save');
            const btnSend = form.querySelector('.btn-save-send');
            if (!btnOnly || !btnSend) return;

            const onlyUrl = form.dataset.onlyUrl;
            const updateUrl = form.dataset.updateUrl;

            function submitAjax(url) {
                const formData = new FormData(form);
                formData.set('_method', 'PUT');

                return fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    }
                }).then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(data.message || 'Error en la solicitud');
                    return data;
                });
            }

            btnOnly.addEventListener('click', async (e) => {
                e.preventDefault();
                try {
                    const result = await submitAjax(onlyUrl);
                    Swal.fire({ icon: 'success', title: 'Guardado', text: result.message || 'Guardado correctamente', timer: 1200, showConfirmButton: false });
                    setTimeout(() => window.location.reload(), 1200);
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo guardar' });
                }
            });

            btnSend.addEventListener('click', async (e) => {
                e.preventDefault();
                const { isConfirmed } = await Swal.fire({
                    title: 'Confirmar envío',
                    text: '¿Deseas guardar y enviar este análisis para revisión?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, enviar',
                    cancelButtonText: 'Cancelar'
                });
                if (!isConfirmed) return;
                try {
                    const result = await submitAjax(updateUrl);
                    Swal.fire({ icon: 'success', title: 'Enviado', text: result.message || 'Guardado y enviado correctamente', timer: 1200, showConfirmButton: false });
                    window.location.reload();
                } catch (err) {
                    Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'No se pudo enviar' });
                }
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function () {
        const herramientasModal = document.getElementById('editHerramientasModal');
        const instanciaId = {{ $instancia->id }};
        const modalInputs = document.getElementById('herramientasSelect');
        const form = document.getElementById('formEditarHerramientas');

        if (herramientasModal && form && modalInputs) {
            herramientasModal.addEventListener('show.bs.modal', function () {
                console.log(`[Modal] Abriendo modal para instancia ID: ${instanciaId}`);
                
                form.action = `/instancias/${instanciaId}/herramientas`;
                console.log(`[Modal] Action del formulario configurada a: ${form.action}`);

                // Limpiar y mostrar loader
                modalInputs.innerHTML = '';
                const loadingOption = document.createElement('option');
                loadingOption.textContent = 'Cargando...';
                modalInputs.appendChild(loadingOption);

                console.log(`[Modal] Solicitando herramientas desde API: /api/instancias/${instanciaId}/herramientas`);

                fetch(`/instancias/${instanciaId}/herramientas`)
                    .then(response => response.json())
                    .then(data => {
                        console.log(`[API] Herramientas recibidas:`, data);

                        modalInputs.innerHTML = '';

                        if (data?.herramientas?.length > 0) {
                            data.herramientas.forEach(h => {
                                const option = document.createElement('option');
                                option.value = h.id;
                                option.textContent = h.nombre;
                                option.selected = h.asignada;
                                option.dataset.cantidad = h.cantidad || 1;
                                option.dataset.observaciones = h.observaciones || '';
                                modalInputs.appendChild(option);
                            });
                            console.log(`[Modal] ${data.herramientas.length} herramientas agregadas al select`);
                        } else {
                            console.warn('[Modal] No se encontraron herramientas para esta instancia');
                        }

                        if (window.$ && window.$.fn.select2) {
                            console.log('[Select2] Inicializando select2...');
                            window.$(modalInputs).select2({
                                dropdownParent: window.$('#editHerramientasModal'),
                                width: '100%',
                                placeholder: 'Seleccione herramientas',
                                allowClear: true
                            }).trigger('change');
                        }
                    })
                    .catch(err => {
                        console.error('[API] Error al cargar herramientas:', err);
                        modalInputs.innerHTML = '';
                        const errorOption = document.createElement('option');
                        errorOption.textContent = 'Error al cargar';
                        modalInputs.appendChild(errorOption);
                    });
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(form);
                console.log('[Formulario] Enviando formulario con acción:', form.action);

                let herramientas = [];
                if (window.$ && window.$.fn.select2) {
                    herramientas = window.$(modalInputs).val() || [];
                } else {
                    herramientas = Array.from(modalInputs.selectedOptions).map(opt => opt.value);
                }

                console.log('[Formulario] Herramientas seleccionadas:', herramientas);

                formData.delete('herramientas[]');
                herramientas.forEach(id => formData.append('herramientas[]', id));

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('[name=_token]').value,
                        'Accept': 'application/json',
                    },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('[Respuesta] Datos recibidos al guardar:', data);

                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Guardado!',
                                text: 'Herramientas actualizadas correctamente',
                                timer: 1500,
                                showConfirmButton: false
                            });
                            setTimeout(() => {
                                const modal = bootstrap.Modal.getInstance(herramientasModal);
                                modal.hide();
                                location.reload();
                            }, 1500);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Ocurrió un error al guardar.'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('[Formulario] Error al guardar herramientas:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al guardar.'
                        });
                    });
            });
        } else {
            console.warn('[Inicialización] Elementos del DOM no encontrados correctamente.');
        }
    });
</script>

    

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('suspensionForm');
        const textarea = document.getElementById('cotio_observaciones_suspension');
        
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
        
        document.getElementById('suspenderModal').addEventListener('hidden.bs.modal', function() {
            form.classList.remove('was-validated');
            textarea.classList.remove('is-invalid');
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const imageInput = document.getElementById('image');
        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const previewContainer = document.createElement('div');
                        previewContainer.className = 'mt-2';
                        previewContainer.innerHTML = `
                            <img src="${event.target.result}" alt="Vista previa" class="img-thumbnail" style="max-height: 150px;">
                        `;
                        
                        const existingPreview = imageInput.nextElementSibling.querySelector('.mt-2');
                        if (existingPreview) {
                            existingPreview.replaceWith(previewContainer);
                        } else {
                            imageInput.insertAdjacentElement('afterend', previewContainer);
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }


    const form = document.querySelector('#editMuestraModal form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Mostrar en consola para depuración
                console.log('Formulario enviado');
                console.log(new FormData(form));
                
                // Enviar el formulario manualmente
                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    }
                })
                .then(response => {
                    console.log('Respuesta recibida', response);
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.json();
                    }
                })
                .then(data => {
                    if (data) {
                        console.log('Datos recibidos', data);
                        // Recargar la página si es exitoso
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }
    });
</script>



<script>
    $(document).ready(function() {
        // Inicializar select2
        $('.select2-herramientas').select2({
            placeholder: "Seleccione herramientas",
            allowClear: true
        });
    

    
        // Disparar evento change al abrir el modal para mostrar las herramientas ya seleccionadas
$('#editHerramientasModal').on('shown.bs.modal', function() {
    $('.select2-herramientas').trigger('change');
});

// Evento para guardar con Fetch
document.getElementById('guardarHerramientasBtn').addEventListener('click', function() {
    const instanciaId = {{ $instancia->id }};
    const selectElement = document.querySelector('.select2-herramientas');
    const selectedOptions = Array.from(selectElement.selectedOptions);
    
    // Preparar los datos para enviar
    const herramientasData = {
        herramientas: selectedOptions.map(option => option.value),
        cantidades: {},
        observaciones: {}
    };
    
    // Obtener cantidades y observaciones de los inputs generados
    selectedOptions.forEach(option => {
        const herramientaId = option.value;
        herramientasData.cantidades[herramientaId] = document.getElementById(`cantidad-${herramientaId}`).value;
        herramientasData.observaciones[herramientaId] = document.getElementById(`observaciones-${herramientaId}`).value;
    });
    
    // Configurar el token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Realizar la petición Fetch
    fetch(`/instancias/${instanciaId}/herramientas`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(herramientasData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Mostrar mensaje de éxito y cerrar el modal
            alert(data.message);
            $('#editHerramientasModal').modal('hide');
            
            // Opcional: Recargar la página o actualizar la UI según sea necesario
            // location.reload();
        } else {
            throw new Error(data.message || 'Error al guardar los cambios');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ocurrió un error al guardar los cambios: ' + error.message);
    });
});
    });
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to calculate the average for a specific modal
    function calculateAverage(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        const resultadoInput = modal.querySelector('input[name="resultado"]');
        const resultado2Input = modal.querySelector('input[name="resultado_2"]');
        const resultado3Input = modal.querySelector('input[name="resultado_3"]');
        const resultadoFinalTextarea = modal.querySelector('textarea[name="resultado_final"]');

        // Improved function to extract numbers with small decimal values
        function extractNumber(value) {
            if (!value) return NaN;
            
            // Convert commas to points for decimal separation if needed
            const standardizedValue = value.toString().replace(',', '.');
            
            // Remove any non-numeric characters except decimal point and minus sign
            const numericString = standardizedValue
                .replace(/[^\d.-]/g, '')
                .replace(/(\..*)\./g, '$1'); // Remove extra decimal points
            
            return parseFloat(numericString);
        }

        // Function to determine the appropriate number of decimal places
        function getDecimalPlaces(numbers) {
            // Find the number with most decimal places
            const decimalCounts = numbers.map(num => {
                const parts = num.toString().split('.');
                return parts.length > 1 ? parts[1].length : 0;
            });
            return Math.max(...decimalCounts, 4); // Use at least 4 decimal places
        }

        // Function to compute and update the average
        function updateAverage() {
            try {
                // Get values and convert to numbers
                const val1 = extractNumber(resultadoInput.value);
                const val2 = extractNumber(resultado2Input.value);
                const val3 = extractNumber(resultado3Input.value);

                // Filter valid numbers (not NaN)
                const validValues = [val1, val2, val3].filter(val => !isNaN(val));
                const count = validValues.length;

                // Calculate average if we have valid values
                let average = '';
                if (count > 0) {
                    const sum = validValues.reduce((acc, val) => acc + val, 0);
                    const avg = sum / count;
                    
                    // Determine appropriate decimal places
                    const decimalPlaces = getDecimalPlaces(validValues);
                    
                    // Format the average with the required precision
                    average = avg.toFixed(decimalPlaces);
                    
                    // Remove trailing zeros after decimal if needed
                    average = average.replace(/(\.\d*?[1-9])0+$/, '$1').replace(/\.$/, '');
                }

                // Update resultado_final textarea
                resultadoFinalTextarea.value = average;
            } catch (error) {
                console.error('Error calculating average:', error);
                resultadoFinalTextarea.value = 'Error en cálculo';
            }
        }

        // Add event listeners with debounce
        const debounceTime = 300;
        let debounceTimer;
        
        function debouncedUpdate() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(updateAverage, debounceTime);
        }

        [resultadoInput, resultado2Input, resultado3Input].forEach(input => {
            input.addEventListener('input', debouncedUpdate);
            input.addEventListener('paste', debouncedUpdate);
            input.addEventListener('change', updateAverage);
        });

        // Initial calculation
        updateAverage();
    }

    // Initialize for each modal
    @foreach($analisis as $item)
        calculateAverage('editAnalisisModal{{ $item->cotio_subitem }}');
    @endforeach
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Verificar si hay un parámetro openModal en la URL
        const urlParams = new URLSearchParams(window.location.search);
        const subitemToOpen = urlParams.get('openModal');
        
        if (subitemToOpen) {
            // Esperar un breve momento para asegurar que los modales están cargados
            setTimeout(() => {
                const modal = new bootstrap.Modal(document.getElementById(`editAnalisisModal${subitemToOpen}`));
                modal.show();
                
                // Opcional: Expandir el acordeón correspondiente
                const accordionItem = document.getElementById(`heading${subitemToOpen}`);
                if (accordionItem) {
                    const collapse = new bootstrap.Collapse(document.getElementById(`collapse${subitemToOpen}`), {
                        toggle: true
                    });
                }
            }, 300);
        }
    });
</script>

<script>
    // Manejar el cierre del modal para actualizar la URL
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function () {
            // Eliminar el parámetro openModal de la URL sin recargar
            if (window.history.replaceState && window.location.search.includes('openModal')) {
                const newUrl = window.location.pathname + window.location.search.replace(/(&|\?)openModal=[^&]*/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    });
    
    // Opcional: Scroll al análisis cuando se abre el modal
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function () {
            const subitem = this.getAttribute('data-subitem');
            const accordionHeader = document.getElementById(`heading${subitem}`);
            if (accordionHeader) {
                accordionHeader.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
</script>


@endsection












