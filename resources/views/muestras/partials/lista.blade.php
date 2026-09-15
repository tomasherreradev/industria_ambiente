<div class="d-none d-lg-block">
    @if($muestras->contains(fn($c) => !empty($c->coti_cuotas)))
        <p class="op-legend">
            <span class="badge bg-info text-white"><x-heroicon-o-check class="d-inline" style="width: 11px; height: 11px;" /></span>
            <span><strong>Cuotas</strong> = cotización con cuotas (seguir coordinando según plazos del plan).</span>
        </p>
    @endif
    <div class="ucrud-panel">
        <div class="ucrud-tablewrap">
            <table class="ucrud-table ucrud-table--sticky-actions">
                <thead>
                <tr>
                    @include('partials.th-ordenable', ['columna' => 'muestra', 'etiqueta' => 'Muestra', 'width' => '100'])
                    @include('partials.th-ordenable', ['columna' => 'cliente', 'etiqueta' => 'Cliente'])
                    @include('partials.th-ordenable', ['columna' => 'progreso', 'etiqueta' => 'Muestras', 'width' => '140', 'centrado' => true])
                    @include('partials.th-ordenable', ['columna' => 'fecha', 'etiqueta' => 'Fecha de aprobación', 'width' => '140', 'centrado' => true])
                    <th style="width: 150px; text-align: center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($muestras as $coti)
                    <tr class="@if($coti->has_suspension) table-danger @endif @if($coti->has_priority) table-warning @endif @if(! $coti->has_suspension && ! $coti->has_priority && ! empty($coti->coti_cuotas)) table-info @endif" 
                        style="@if($coti->has_suspension) border-left: 4px solid #dc3545; @elseif($coti->has_priority) border-left: 4px solid #ffc107; @elseif(! empty($coti->coti_cuotas)) border-left: 4px solid #0dcaf0; @endif">
                        <td>
                            <div class="fw-bold">#{{ $coti->coti_num }}
                                @if($coti->has_suspension)
                                    <span class="badge bg-danger ms-2">Suspendida</span>
                                @elseif($coti->has_priority)
                                    <span class="badge bg-warning text-dark">
                                        <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                        Prioritaria
                                    </span>
                                @endif
                                @include('muestras.partials.cuotas-cotizacion-badge', ['coti' => $coti])
                            </div>
                            <small class="text-muted">{{ $coti->matriz_descripcion_calculada }}</small>
                            <small class="text-muted">- {{ $coti->coti_descripcion ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <div>{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</div>
                            @if($coti->coti_establecimiento)
                                <small class="text-muted">{{ $coti->coti_establecimiento }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($coti->total_instancias > 0)
                                <div class="op-progress">
                                    <div class="op-progress__bar">
                                        @if(($portalTipo ?? '') === 'mediciones')
                                            <div class="progress-bar bg-success"
                                                 role="progressbar"
                                                 style="width: {{ $coti->porcentaje_progreso['con_informe'] ?? 0 }}%"
                                                 title="Informe subido: {{ round($coti->porcentaje_progreso['con_informe'] ?? 0) }}%">
                                            </div>
                                        @else
                                        <!-- Segmento de muestreadas (verde) -->
                                        <div class="progress-bar bg-success" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['muestreadas'] }}%" 
                                             title="Muestreadas: {{ round($coti->porcentaje_progreso['muestreadas']) }}%">
                                        </div>
                                        
                                        <!-- Segmento en revisión (azul) -->
                                        <div class="progress-bar bg-info" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['en_revision'] }}%" 
                                             title="En revisión: {{ round($coti->porcentaje_progreso['en_revision']) }}%">
                                        </div>
                                        
                                        <!-- Segmento coordinadas (amarillo) -->
                                        <div class="progress-bar bg-warning" 
                                             role="progressbar" 
                                             style="width: {{ $coti->porcentaje_progreso['coordinadas'] }}%" 
                                             title="Coordinadas: {{ round($coti->porcentaje_progreso['coordinadas']) }}%">
                                        </div>
                                        @endif
                                    </div>
                                    <span class="op-progress__count">
                                        @if(($portalTipo ?? '') === 'mediciones')
                                            {{ $coti->instancias_completadas }}/{{ $coti->total_instancias }} informes
                                        @else
                                            {{ $coti->instancias_completadas }}/{{ $coti->total_instancias }}
                                        @endif
                                    </span>
                                </div>
                                @if(($portalTipo ?? '') === 'mediciones')
                                    <small class="d-block mt-1 text-muted">
                                        {{ round($coti->porcentaje_progreso['con_informe'] ?? 0) }}% con informe
                                    </small>
                                @elseif($coti->porcentaje_progreso['total'] > 0 && $coti->porcentaje_progreso['total'] < 100)
                                    <small class="d-block mt-1 @if($coti->has_suspension) text-danger fw-bold @else text-muted @endif">
                                        @if($coti->has_priority)
                                            <x-heroicon-o-star style="width: 14px; height: 14px;" class="text-warning me-1" />
                                        @endif
                                        {{ round($coti->porcentaje_progreso['total']) }}%
                                        @if($coti->has_suspension)
                                            <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                        @endif
                                    </small>
                                @endif
                            @else
                                <span class="badge bg-light text-dark">Sin muestras</span>
                            @endif
                        </td>

                        <td class="text-center">
                            <div>{{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : '-' }}</div>
                            @if($coti->coti_fechafin)
                                <small class="text-muted">Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}</small>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="ucrud-actions">
                                @if(userPuedeGestionarMuestrasCotizacion())
                                    @php
                                        $urlGestion = ($portalCanal ?? '') === 'mediciones'
                                            ? route('mediciones.show', $coti->coti_num)
                                            : url('/show/'.$coti->coti_num) . (isset($portalCanal) ? '?canal='.$portalCanal : '');
                                    @endphp
                                    <a href="{{ $urlGestion }}"
                                        class="ucrud-iconbtn"
                                        data-bs-toggle="tooltip"
                                        title="Gestionar muestras"
                                        data-bs-placement="bottom">
                                        <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                    </a>
                                @endif
                                <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}"
                                   class="ucrud-iconbtn"
                                   data-bs-toggle="tooltip"
                                   title="Ver detalles"
                                   data-bs-placement="bottom">
                                    <x-heroicon-o-document-magnifying-glass style="width: 15px; height: 15px;" />
                                </a>

                                @php
                                    $direccionCompleta = \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($coti);
                                    $mapsQueryDestinatario = \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioMapsQuery($coti);
                                @endphp

                                <button type="button"
                                        class="ucrud-iconbtn"
                                        data-bs-toggle="tooltip"
                                        title="Copiar dirección"
                                        data-bs-placement="bottom"
                                        onclick="copiarDireccionAlPortapapeles(this)"
                                        data-direccion="{{ e($direccionCompleta) }}">
                                    <x-heroicon-o-document-duplicate style="width: 15px; height: 15px;" />
                                </button>
                                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapsQueryDestinatario) }}"
                                   class="ucrud-iconbtn"
                                   data-bs-toggle="tooltip"
                                   title="Ver en mapa"
                                   target="_blank"
                                   data-bs-placement="bottom">
                                   <x-heroicon-o-map style="width: 15px; height: 15px;" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Vista móvil -->
<div class="d-block d-lg-none">
    <p class="small text-muted mb-3">
        <span class="badge bg-info text-white me-1"><x-heroicon-o-check style="width: 10px; height: 10px;" /></span>
        <strong>Cuotas</strong> = cuotas en la cotización.
    </p>
    <div class="row g-3">
        @foreach($muestras as $coti)
            @php
                // Calcular el número de muestras originales (matrices)
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
            <div class="col-12">
                <div class="op-mobile-card border-start border-4 
                    @if($coti->has_suspension) border-danger
                    @elseif($coti->has_priority) border-warning
                    @elseif(! empty($coti->coti_cuotas)) border-info
                    @elseif($coti->coti_estado == 'A') border-success
                    @elseif($coti->coti_estado == 'E') border-warning
                    @elseif($coti->coti_estado == 'S') border-danger
                    @else border-secondary @endif">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">#{{ $coti->coti_num }}
                                    @if($coti->has_suspension)
                                        <span class="badge bg-danger ms-2">Suspendida</span>
                                    @elseif($coti->has_priority)
                                        <span class="badge bg-warning text-dark">
                                            <x-heroicon-o-star style="width: 12px; height: 12px;" class="me-1" />
                                            Prioritaria
                                        </span>
                                    @endif
                                    @include('muestras.partials.cuotas-cotizacion-badge', ['coti' => $coti])
                                </h5>
                                <span class="badge 
                                    @if($coti->coti_estado == 'A') bg-success
                                    @elseif($coti->coti_estado == 'E') bg-warning
                                    @elseif($coti->coti_estado == 'S') bg-danger
                                    @else bg-secondary @endif">
                                    {{ trim($coti->coti_estado) }}
                                </span>
                            </div>
                            <small class="text-muted">
                                {{ $coti->coti_fechaaprobado ? \Carbon\Carbon::parse($coti->coti_fechaaprobado)->format('d/m/Y') : 'Pendiente' }}
                            </small>
                        </div>
                        
                        <h6 class="card-subtitle mb-2 text-muted">{{ \App\Support\CotizacionClienteEtiqueta::paraLista($coti) }}</h6>
                        
                        @if($coti->coti_establecimiento)
                            <p class="small mb-1"><i class="fas fa-building me-1"></i> {{ $coti->coti_establecimiento }}</p>
                        @endif
                        
                        <div class="my-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small">Progreso de muestreo</span>
                                <span class="small fw-bold @if($coti->has_suspension) text-danger @endif">
                                    @if($coti->has_priority)
                                        <x-heroicon-o-star style="width: 14px; height: 14px;" class="text-warning me-1" />
                                    @endif
                                    {{ round($coti->porcentaje_progreso['total']) }}%
                                    @if($coti->has_suspension)
                                        <x-heroicon-o-exclamation-triangle style="width: 16px; height: 16px;" class="ms-1" />
                                    @endif
                                </span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar 
                                    @if($coti->has_suspension) bg-danger
                                    @elseif($coti->porcentaje_progreso['total'] == 100) bg-success
                                    @elseif($coti->porcentaje_progreso['total'] > 0) bg-info
                                    @else bg-light @endif" 
                                    style="width: {{ $coti->porcentaje_progreso['total'] }}%">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">{{ $totalMuestras }} matrices</small>
                                <small class="text-muted">{{ $coti->instancias_completadas }}/{{ $coti->total_instancias }} muestras</small>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-muted me-2">
                                    <i class="fas fa-flask me-1"></i> {{ $coti->matriz_descripcion_calculada }}
                                </span>
                                @if($coti->coti_fechafin)
                                    <span class="small text-muted">
                                        <i class="far fa-clock me-1"></i> Vence: {{ \Carbon\Carbon::parse($coti->coti_fechafin)->format('d/m/Y') }}
                                    </span>
                                @endif
                            </div>
                            <div class="btn-group btn-group-sm" style="width: 100%; max-width: 200px;">
                                @if(userPuedeGestionarMuestrasCotizacion())
                                    @php
                                        $urlGestionMobile = ($portalCanal ?? '') === 'mediciones'
                                            ? route('mediciones.show', $coti->coti_num)
                                            : url('/show/'.$coti->coti_num) . (isset($portalCanal) ? '?canal='.$portalCanal : '');
                                    @endphp
                                    <a href="{{ $urlGestionMobile }}" class="btn btn-sm btn-outline-primary">
                                        <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                    </a>
                                @endif
                                <a href="{{ url('/cotizaciones/'.$coti->coti_num) }}" class="btn btn-sm btn-outline-secondary">
                                    <x-heroicon-o-document-magnifying-glass style="width: 15px; height: 15px;" />
                                    </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="ucrud-panel ucrud-pagination mt-3">
    {{ $muestras->links() }}
</div>

<script>
    // Copia al portapapeles usando Clipboard API (si está disponible) y fallback.
    function copiarDireccionAlPortapapeles(boton) {
        if (!boton) return;

        const direccion = boton.getAttribute('data-direccion') || '';
        if (!direccion) {
            console.warn('No hay dirección para copiar.');
            return;
        }

        const fallbackCopy = () => {
            const textarea = document.createElement('textarea');
            textarea.value = direccion;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                console.log('Dirección copiada (fallback).');
            } catch (err) {
                console.error('Error copiando al portapapeles:', err);
            } finally {
                document.body.removeChild(textarea);
            }
        };

        // Clipboard API requiere contexto seguro (HTTPS) en algunos navegadores.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(direccion)
                .then(() => console.log('Dirección copiada.'))
                .catch((err) => {
                    console.error('Error con Clipboard API, usando fallback:', err);
                    fallbackCopy();
                });
        } else {
            fallbackCopy();
        }

        // Feedback mínimo (no interfiere con tooltips existentes).
        const originalTitle = boton.getAttribute('title') || '';
        boton.setAttribute('title', 'Copiado');
        setTimeout(() => boton.setAttribute('title', originalTitle), 1200);
    }

    // Inicializar tooltips
    document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips para los segmentos de progreso
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('.progress-bar[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                container: 'body',
                placement: 'top'
            });
        });
    });
</script>