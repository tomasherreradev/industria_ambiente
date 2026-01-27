@extends('layouts.app')

<head>
    <title>Cotización {{ $cotizacion->coti_num }}</title>
    <style>
        body {
            background-color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            max-width: 1200px;
            padding: 1.5rem;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
        }
        .back-btn {
            display: inline-block;
            color: #555;
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .back-btn:hover {
            color: #007bff;
        }
        .sample-container {
            margin-bottom: 1rem;
        }
        .sample-header {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-color 0.2s;
        }
        .sample-header:hover {
            border-color: #007bff;
        }
        .sample-content {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 8px 8px;
            padding: 1rem;
            display: none;
        }
        .sample-content.open {
            display: block;
        }
        .checkbox {
            margin-right: 0.5rem;
        }
        .sample-label {
            font-weight: 500;
            color: #333;
        }
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        .analysis-card {
            border: 1px solid #e8ecef;
            border-radius: 6px;
            padding: 0.75rem;
            background: #fafafa;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
        }
        .facturar-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 20px;
            transition: all 0.2s;
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
        .facturar-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .alert {
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 0.9rem;
        }
        .checkbox-container {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
        }
        .analysis-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .analysis-checkbox:disabled + h6 {
            opacity: 0.7;
            text-decoration: line-through;
        }

        .resumen-card {
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        }

        .resumen-grid {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .resumen-item .label {
            display: block;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: #6c757d;
            margin-bottom: 0.35rem;
        }

        .resumen-item h5 {
            font-weight: 700;
        }
    </style>
</head>

@section('content')
<div class="container">
    <a href="{{ url('/facturacion') }}" class="back-btn">← Volver a Facturación</a>
    <h2 class="title">Cotización <span class="text-primary">{{ $cotizacion->coti_num }}</span></h2>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @include('cotizaciones.info')

    @php
        $formatCurrency = fn($value) => '$' . number_format(($value ?? 0), 2, ',', '.');
        $formatPercent = fn($value) => number_format(($value ?? 0), 2, ',', '.') . '%';
    @endphp

    @if(isset($resumenMontos))
        <div class="card resumen-card mb-4">
            <div class="card-body resumen-grid">
                <div class="resumen-item">
                    <span class="label">Importe bruto</span>
                    <h5 class="mb-0">{{ $formatCurrency($resumenMontos['total_bruto'] ?? 0) }}</h5>
                </div>
                <div class="resumen-item">
                    <span class="label">Descuento global</span>
                    <h5 class="mb-0">
                        {{ ($resumenMontos['descuento_global_porcentaje'] ?? 0) > 0 ? $formatPercent($resumenMontos['descuento_global_porcentaje']) : 'Sin descuento' }}
                    </h5>
                    @if(($resumenMontos['descuento_global_monto'] ?? 0) > 0)
                        <small class="text-muted">Ajuste: -{{ $formatCurrency($resumenMontos['descuento_global_monto']) }}</small>
                    @endif
                </div>
                <div class="resumen-item">
                    <span class="label">
                        Descuento sector
                        @if(!empty($resumenMontos['descuento_sector_etiqueta']))
                            ({{ $resumenMontos['descuento_sector_etiqueta'] }})
                        @endif
                    </span>
                    <h5 class="mb-0">
                        {{ ($resumenMontos['descuento_sector_porcentaje'] ?? 0) > 0 ? $formatPercent($resumenMontos['descuento_sector_porcentaje']) : 'Sin descuento' }}
                    </h5>
                    @if(($resumenMontos['descuento_sector_monto'] ?? 0) > 0)
                        <small class="text-muted">Ajuste: -{{ $formatCurrency($resumenMontos['descuento_sector_monto']) }}</small>
                    @endif
                </div>
                <div class="resumen-item">
                    <span class="label">Importe neto estimado</span>
                    <h5 class="mb-0 text-success">{{ $formatCurrency($resumenMontos['total_neto'] ?? 0) }}</h5>
                    <small class="text-muted">
                        Descuento total:
                        @if(($resumenMontos['descuento_total_porcentaje'] ?? 0) > 0)
                            {{ $formatPercent($resumenMontos['descuento_total_porcentaje']) }}
                            (-{{ $formatCurrency($resumenMontos['descuento_total_monto'] ?? 0) }})
                        @else
                            Sin descuento aplicado
                        @endif
                    </small>
                </div>
            </div>
        </div>
    @endif

    <div class="samples">
        @if($tareas->isEmpty())
            <div class="alert alert-warning">No hay muestras registradas.</div>
        @else
            @foreach($agrupadas as $item)
                @php
                    $instancia = $item['instancia'];
                    $categoria = $item['categoria'];
                    $tareas = $item['tareas'];
                    $responsables = $item['responsables'];
                @endphp
                @if($instancia->enable_inform)
                    @php
                        $facturada = $instancia->facturado;
                    @endphp
                    <div class="sample-container">
                        <div class="sample-header {{ $facturada ? '' : '' }}" onclick="toggleSample({{ $instancia->id }}, event)">
                            <div class="checkbox-container">
                                <input 
                                    type="checkbox" 
                                    class="checkbox sample-checkbox" 
                                    id="sample-{{ $instancia->id }}"
                                    data-instancia="{{ $instancia->id }}"
                                    onchange="toggleAllTasks(this, {{ $instancia->id }})"
                                    @if($facturada) disabled @endif
                                >
                                <label class="sample-label" for="sample-{{ $instancia->id }}">
                                    Muestra (#{{$instancia->instance_number }})
                                    @if($facturada)
                                        <x-heroicon-o-check-circle style="width: 18px; height: 18px; color: green;" />
                                        <span class="badge bg-success">Facturada</span>
                                    @else
                                        <x-heroicon-o-x-circle style="width: 18px; height: 18px; color: red;" />
                                        <span class="badge bg-danger">No Facturada</span>
                                    @endif
                                </label>
                            </div>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div id="sample-content-{{ $instancia->id }}" class="sample-content">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Descripción:</strong> {{ $categoria->cotio_descripcion }}</p>
                                    {{-- <p><strong>Estado:</strong> 
                                        <span class="badge bg-success">{{ $instancia->cotio_estado_analisis }}</span>
                                    </p> --}}
                                    <p><strong>Identificación:</strong> {{ $instancia->cotio_identificacion ?? 'N/A' }}</p>
                                    <p><strong>Fecha Muestreo:</strong> 
                                        {{ $instancia->fecha_muestreo ? \Carbon\Carbon::parse($instancia->fecha_muestreo)->format('d/m/Y H:i') : 'N/A' }}
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Responsables Muestreo:</strong> 
                                        @forelse($responsables as $resp)
                                            <span class="badge bg-info me-1">{{ $resp->usu_descripcion ?? $resp->usu_codigo }}</span>
                                        @empty
                                            <span class="text-muted">Sin asignar</span>
                                        @endforelse
                                    </p>
                                    @if($instancia->responsablesAnalisis && $instancia->responsablesAnalisis->count() > 0)
                                        <p><strong>Responsables Análisis:</strong> 
                                            @foreach($instancia->responsablesAnalisis as $analista)
                                                <span class="badge bg-primary me-1">{{ $analista->usu_descripcion }}</span>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                            </div>
                            
                            @php
                                // Parsear notas de facturación desde JSON
                                $notasFacturacion = [];
                                if (!empty($categoria->cotio_nota_contenido)) {
                                    try {
                                        $notasParsed = json_decode($categoria->cotio_nota_contenido, true);
                                        if (is_array($notasParsed)) {
                                            $notasFacturacion = collect($notasParsed)->filter(function($nota) {
                                                return isset($nota['tipo']) && $nota['tipo'] === 'fact';
                                            })->values()->toArray();
                                        } else {
                                            // Formato antiguo: nota simple
                                            if (!empty($categoria->cotio_nota_tipo) && $categoria->cotio_nota_tipo === 'fact') {
                                                $notasFacturacion = [['tipo' => 'fact', 'contenido' => $categoria->cotio_nota_contenido]];
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // No es JSON, es formato antiguo
                                        if (!empty($categoria->cotio_nota_tipo) && $categoria->cotio_nota_tipo === 'fact') {
                                            $notasFacturacion = [['tipo' => 'fact', 'contenido' => $categoria->cotio_nota_contenido]];
                                        }
                                    }
                                }
                            @endphp
                            
                            @if(!empty($notasFacturacion))
                                <div class="alert alert-danger mt-3">
                                    <strong>Notas de Facturación:</strong>
                                    @foreach($notasFacturacion as $nota)
                                        <div class="mt-2">
                                            <span class="badge bg-danger me-2">Nota Fact.</span>
                                            <span>{{ $nota['contenido'] ?? '' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if(isset($instancia->precio_bruto))
                                <div class="alert alert-secondary py-2 px-3 mt-2 mb-0">
                                    <small class="text-muted d-block">Importe muestra</small>
                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                        <span class="badge bg-light text-dark border">{{ $formatCurrency($instancia->precio_bruto) }} bruto</span>
                                        <span class="badge bg-success">{{ $formatCurrency($instancia->precio_neto) }} neto</span>
                                    </div>
                                </div>
                            @endif
                            @if($tareas && count($tareas) > 0)
                                <div class="mt-3">
                                    <h6>Análisis:</h6>
                                    <div class="analysis-grid">
                                        @foreach($tareas as $analisis)
                                            @if($analisis->instancia && $analisis->instancia->id)
                                                <div class="analysis-card">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <input 
                                                            type="checkbox" 
                                                            class="checkbox analysis-checkbox" 
                                                            id="analysis-{{ $analisis->instancia->id }}"
                                                            data-instancia="{{ $instancia->id }}"
                                                            data-analisis-id="{{ $analisis->instancia->id }}"
                                                            onchange="checkSampleStatus({{ $instancia->id }})"
                                                            @if($analisis->instancia->facturado) disabled @endif
                                                        >
                                                        <h6 class="mb-0 ms-2">{{ $analisis->instancia->cotio_descripcion ?? 'Sin descripción' }}</h6>
                                                        @if($analisis->instancia->facturado)
                                                            <x-heroicon-o-check-circle style="width: 18px; height: 18px; color: green;" />
                                                            <span class="badge bg-success">Facturada</span>
                                                        @else
                                                            <x-heroicon-o-x-circle style="width: 18px; height: 18px; color: red;" />
                                                            <span class="badge bg-danger">No Facturada</span>
                                                        @endif
                                                    </div>
                                                    @if($analisis->instancia->resultado_final)
                                                        <p><strong>Resultado:</strong> 
                                                            <span class="badge bg-success">{{ $analisis->instancia->resultado_final . ' ' . ($analisis->instancia->cotio_codigoum ?? '') }}</span>
                                                        </p>
                                                    @endif
                                                    @if(isset($analisis->instancia->precio_bruto))
                                                        <p class="mb-1">
                                                            <small class="text-muted d-block">Importe análisis bruto: {{ $formatCurrency($analisis->instancia->precio_bruto) }}</small>
                                                            <span class="badge bg-success">{{ $formatCurrency($analisis->instancia->precio_neto) }} neto</span>
                                                        </p>
                                                    @endif
                                                    @if($analisis->instancia->resultado || $analisis->instancia->resultado_2 || $analisis->instancia->resultado_3)
                                                        <small class="text-muted">
                                                            <strong>Resultados:</strong><br>
                                                            @if($analisis->instancia->resultado) R1: {{ $analisis->instancia->resultado }}<br> @endif
                                                            @if($analisis->instancia->resultado_2) R2: {{ $analisis->instancia->resultado_2 }}<br> @endif
                                                            @if($analisis->instancia->resultado_3) R3: {{ $analisis->instancia->resultado_3 }}<br> @endif
                                                        </small>
                                                    @endif
                                                    @if($analisis->instancia->observacion_resultado_final)
                                                        <p><small><strong>Obs.:</strong> {{ $analisis->instancia->observacion_resultado_final }}</small></p>
                                                    @endif
                                                    @if($analisis->instancia->observaciones_ot)
                                                        <p><small><strong>Obs. Coord.:</strong> {{ $analisis->instancia->observaciones_ot }}</small></p>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="alert alert-warning">Análisis sin instancia válida (ID: {{ $analisis->id ?? 'N/A' }})</div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-warning mt-3">No hay análisis registrados.</div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>

    <form id="facturarForm" action="{{ route('facturacion.facturar', ['cotizacion' => $cotizacion->coti_num]) }}" method="POST">
        @csrf
        <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->coti_num }}">
        <button id="btnFacturar" type="submit" class="btn btn-primary facturar-btn" disabled>
            <i class="fas fa-receipt me-1"></i>Facturar
        </button>
    </form>
</div>

<script>
    function toggleSample(instanciaId, event) {
        // Prevent accordion toggle when clicking the checkbox or its label
        if (event.target.classList.contains('sample-checkbox') || event.target.tagName === 'LABEL') {
            return;
        }
        const content = document.getElementById(`sample-content-${instanciaId}`);
        const icon = document.querySelector(`#sample-content-${instanciaId}`).parentElement.querySelector('.toggle-icon');
        content.classList.toggle('open');
        icon.textContent = content.classList.contains('open') ? '▼' : '▶';
    }

    function toggleAllTasks(checkbox, instanciaId) {
        const taskCheckboxes = document.querySelectorAll(`.analysis-checkbox[data-instancia="${instanciaId}"]:not(:disabled)`);
        taskCheckboxes.forEach(taskCheckbox => {
            // Solo marcar/desmarcar análisis que NO están facturados (no disabled)
            taskCheckbox.checked = checkbox.checked;
        });
        updateFacturarButton();
        updateHiddenInputs();
    }

    function checkSampleStatus(instanciaId) {
        const sampleCheckbox = document.getElementById(`sample-${instanciaId}`);
        // Solo considerar análisis NO facturados (no disabled)
        const taskCheckboxes = document.querySelectorAll(`.analysis-checkbox[data-instancia="${instanciaId}"]:not(:disabled)`);
        const anyChecked = Array.from(taskCheckboxes).some(cb => cb.checked);
        const allChecked = taskCheckboxes.length > 0 && Array.from(taskCheckboxes).every(cb => cb.checked);

        // La muestra solo se marca automáticamente si TODOS los análisis disponibles están seleccionados
        // Si hay análisis facturados, la muestra no se puede marcar automáticamente
        sampleCheckbox.checked = allChecked;

        updateFacturarButton();
        updateHiddenInputs();
    }

    function updateFacturarButton() {
        const btnFacturar = document.getElementById('btnFacturar');
        // Solo considerar checkboxes que no están deshabilitados
        const anyChecked = document.querySelectorAll('.sample-checkbox:checked:not(:disabled), .analysis-checkbox:checked:not(:disabled)').length > 0;
        btnFacturar.disabled = !anyChecked;
        btnFacturar.classList.toggle('btn-primary', anyChecked);
        btnFacturar.classList.toggle('btn-secondary', !anyChecked);
    }

    function updateHiddenInputs() {
        const form = document.getElementById('facturarForm');
        form.querySelectorAll('input[name="muestras[]"], input[name="analisis[]"]').forEach(input => input.remove());

        const selectedMuestras = new Set();
        const analisisDeMuestrasSeleccionadas = new Set();
        
        // Primero identificar qué muestras están completamente seleccionadas
        document.querySelectorAll('.sample-checkbox:checked:not(:disabled)').forEach(checkbox => {
            if (checkbox.dataset.instancia) {
                const instanciaId = checkbox.dataset.instancia;
                // Verificar si TODOS los análisis NO FACTURADOS de esta muestra están seleccionados
                const allAnalisisDisponibles = document.querySelectorAll(`.analysis-checkbox[data-instancia="${instanciaId}"]:not(:disabled)`);
                const selectedAnalisis = document.querySelectorAll(`.analysis-checkbox[data-instancia="${instanciaId}"]:checked:not(:disabled)`);
                
                // Solo agregar la muestra si:
                // 1. El checkbox de muestra está marcado Y
                // 2. TODOS los análisis DISPONIBLES (no facturados) están seleccionados (o no hay análisis disponibles)
                if (allAnalisisDisponibles.length === 0 || allAnalisisDisponibles.length === selectedAnalisis.length) {
                    selectedMuestras.add(instanciaId);
                    
                    // IMPORTANTE: Cuando se selecciona una muestra completa, también incluir TODOS sus análisis no facturados
                    // para que se marquen como facturados en el backend
                    allAnalisisDisponibles.forEach(analisisCheckbox => {
                        if (analisisCheckbox.dataset.analisisId) {
                            analisisDeMuestrasSeleccionadas.add(analisisCheckbox.dataset.analisisId);
                        }
                    });
                }
            }
        });

        // Recopilar análisis seleccionados SOLO de muestras que NO están completamente seleccionadas
        // Y que NO estén ya facturados
        document.querySelectorAll('.analysis-checkbox:checked:not(:disabled)').forEach(checkbox => {
            if (checkbox.dataset.analisisId && checkbox.dataset.instancia) {
                const instanciaId = checkbox.dataset.instancia;
                
                // Si la muestra está completamente seleccionada, NO enviar los análisis individuales
                // (ya se enviaron en el paso anterior)
                if (!selectedMuestras.has(instanciaId)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'analisis[]';
                    input.value = checkbox.dataset.analisisId;
                    form.appendChild(input);
                }
            }
        });

        // Agregar muestras solo si están explícitamente seleccionadas (muestra completa)
        selectedMuestras.forEach(instanciaId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'muestras[]';
            input.value = instanciaId;
            form.appendChild(input);
        });
        
        // IMPORTANTE: Agregar todos los análisis de las muestras seleccionadas para que se marquen como facturados
        analisisDeMuestrasSeleccionadas.forEach(analisisId => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'analisis[]';
            input.value = analisisId;
            form.appendChild(input);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.sample-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => toggleAllTasks(checkbox, checkbox.dataset.instancia));
        });
        document.querySelectorAll('.analysis-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => checkSampleStatus(checkbox.dataset.instancia));
        });
        
        // Verificar estado inicial de cada muestra
        document.querySelectorAll('.sample-checkbox').forEach(checkbox => {
            if (checkbox.dataset.instancia && !checkbox.disabled) {
                checkSampleStatus(checkbox.dataset.instancia);
            }
        });
        
        updateFacturarButton();
    });
</script>
@endsection