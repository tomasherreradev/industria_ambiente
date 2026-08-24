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
        .facturar-btn, .facturar-btn-group {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 20px;
            transition: all 0.2s;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .facturar-btn-blocked {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 20px;
            width: 100%;
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
    <p class="text-muted mb-3">Divisa: {{ $cotizacion->divisa_codigo ?? 'PES' }}</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @isset($contactosEnvioFactura, $estadoEnvioFactura)
        @php
            $emailsCoincidentes = collect($estadoEnvioFactura['emails_coincidentes'] ?? []);
            $contactosCotiEnvio = collect($estadoEnvioFactura['contactos_coti'] ?? []);
            $requiereSeleccionEmail = ! empty($estadoEnvioFactura['requiere_seleccion']);
            $tieneEnvioConfigurado = ! empty($estadoEnvioFactura['tiene_envio_configurado']);
        @endphp
        <div class="card border-primary mb-4 shadow-sm" id="card-emails-envio-factura">
            <div class="card-header bg-primary text-white py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="fw-semibold mb-0">Email de envío de factura</span>
                <small class="opacity-75">Se enviará la factura PDF al confirmar</small>
            </div>
            <div class="card-body py-3">
                @if ($tieneEnvioConfigurado)
                    <div class="alert alert-success py-2 mb-3 small">
                        La cotización ya tiene contacto(s) de tipo «Envío de factura». Se usarán al facturar.
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach ($contactosCotiEnvio as $contactoCoti)
                            @php
                                $emailNorm = \App\Support\CotizacionContactosFacturacion::normalizarEmail($contactoCoti['correo'] ?? '');
                                $coincideCliente = $emailsCoincidentes->contains($emailNorm);
                            @endphp
                            <li class="@if(! $loop->last) mb-2 pb-2 border-bottom @endif">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="hidden" name="emails_envio_factura[]" value="{{ $contactoCoti['correo'] }}" form="facturarForm">
                                    @if ($coincideCliente)
                                        <span class="badge bg-success">En cliente</span>
                                    @else
                                        <span class="badge bg-secondary">Solo en cotización</span>
                                    @endif
                                    @if (trim((string) ($contactoCoti['nombre'] ?? '')) !== '')
                                        <span class="fw-semibold">{{ $contactoCoti['nombre'] }}</span>
                                        <span class="text-muted">·</span>
                                    @endif
                                    <span>{{ $contactoCoti['correo'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @elseif ($contactosEnvioFactura->isNotEmpty())
                    <div class="alert alert-warning py-2 mb-3 small">
                        La cotización no tiene email de envío de factura. Seleccione uno o más contactos del cliente antes de facturar.
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach ($contactosEnvioFactura as $contacto)
                            @php $email = trim((string) $contacto->email); @endphp
                            <li class="@if(! $loop->last) mb-2 pb-2 border-bottom @endif">
                                <label class="d-flex flex-wrap align-items-center gap-2 mb-0 cursor-pointer">
                                    <input type="checkbox"
                                           class="form-check-input email-envio-factura-check mt-0"
                                           name="emails_envio_factura[]"
                                           value="{{ $email }}"
                                           form="facturarForm">
                                    @if (trim((string) ($contacto->nombre ?? '')) !== '')
                                        <span class="fw-semibold">{{ trim($contacto->nombre) }}</span>
                                        <span class="text-muted">·</span>
                                    @endif
                                    <span>{{ $email }}</span>
                                    @if (trim((string) ($contacto->telefono ?? '')) !== '')
                                        <small class="text-muted">{{ trim($contacto->telefono) }}</small>
                                    @endif
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0 small">
                        No hay contactos de tipo «Envío de factura» en el cliente ni en la cotización. La factura se generará sin envío por email.
                    </p>
                @endif
            </div>
        </div>
    @endisset

    @isset($refsFacturacion)
        <div class="card border-secondary mb-4 shadow-sm">
            <div class="card-header bg-light py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <span class="fw-semibold mb-0">Referencias para facturación</span>
                    <small class="text-muted ms-2">Mismos datos que se imprimen en la factura</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarRefs">
                    <i class="fas fa-edit me-1"></i>Editar Referencias
                </button>
            </div>
            <div class="card-body py-3">
                @if (empty($refsFacturacion['puede_facturar']) && !empty($refsFacturacion['mensaje_bloqueo']))
                    <div class="alert alert-warning py-2 mb-3">{{ $refsFacturacion['mensaje_bloqueo'] }}</div>
                @endif
                <div class="row g-3 small">
                    <div class="col-md-4">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.06em;">Remito (1.ª línea)</span>
                        <span class="fw-semibold">{{ ($refsFacturacion['remito'] ?? '') !== '' ? $refsFacturacion['remito'] : '—' }}</span>
                    </div>
                    <div class="col-md-5">
                        <span class="text-muted d-block text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.06em;">Orden de compra (O.C.)</span>
                        <span class="fw-semibold">{{ ($refsFacturacion['oc'] ?? '') !== '' ? $refsFacturacion['oc'] : '—' }}</span>
                        @if (!empty($refsFacturacion['oc_obligatorio']))
                            <span class="badge bg-dark ms-1">Obligatoria para facturar</span>
                        @endif
                    </div>
                </div>
                @php $filasRefs = $refsFacturacion['filas'] ?? []; @endphp
                @if (count($filasRefs) > 0)
                    <hr class="my-3">
                    <span class="text-muted d-block mb-2 small">Referencias adicionales (hasta 4)</span>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:110px;">Tipo</th>
                                    <th>Valor</th>
                                    <th style="width:140px;">Oblig. facturar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filasRefs as $fila)
                                    <tr>
                                        <td class="fw-medium">{{ $fila['tipo'] ?? '' }}</td>
                                        <td>{{ ($fila['valor'] ?? '') !== '' ? $fila['valor'] : '—' }}</td>
                                        <td>
                                            @if (!empty($fila['obligatorio_factura']))
                                                <span class="badge bg-warning text-dark">Sí</span>
                                            @else
                                                <span class="text-muted">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endisset

    @include('cotizaciones.info')

    @php
        $divisaCodigo = $cotizacion->divisa_codigo ?? 'PES';
        $formatCurrency = fn($value) => $divisaCodigo . ' ' . number_format(($value ?? 0), 2, ',', '.');
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

    @if(isset($cuotasInfo))
        @php
            $mesesEs = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];
            $fechaBase = $cuotasInfo['fecha_inicio'] instanceof \Carbon\Carbon
                ? $cuotasInfo['fecha_inicio']
                : \Carbon\Carbon::parse($cuotasInfo['fecha_inicio']);
        @endphp
        <div class="card mb-4 border-info shadow-sm">
            <div class="card-header bg-info text-white py-2">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Facturación por Cuotas</h5>
            </div>
            <div class="card-body">
                @php
                    $tieneInteres = ($cuotasInfo['interes'] ?? 0) > 0;
                @endphp
                <div class="row g-3 mb-3 small">
                    <div class="col-auto">
                        <span class="text-muted">Cuotas:</span>
                        <strong class="ms-1">{{ $cuotasInfo['total'] }}</strong>
                        <span class="text-muted ms-2">({{ $cuotasInfo['facturadas'] }} facturadas)</span>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted">Monto base:</span>
                        <strong class="ms-1">{{ $formatCurrency($cuotasInfo['monto_base']) }}</strong>
                    </div>
                    @if($tieneInteres)
                    <div class="col-auto">
                        <span class="text-muted">Interés:</span>
                        <strong class="ms-1 text-warning">{{ number_format($cuotasInfo['interes'], 2, ',', '.') }}%</strong>
                    </div>
                    <div class="col-auto">
                        <span class="text-muted">Total con interés:</span>
                        <strong class="ms-1 text-success">{{ $formatCurrency($cuotasInfo['monto_con_interes']) }}</strong>
                    </div>
                    @endif
                    <div class="col-auto">
                        <span class="text-muted">Valor por cuota:</span>
                        <strong class="ms-1">{{ $formatCurrency($cuotasInfo['monto_indiv']) }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Sel.</th>
                                <th>Cuota</th>
                                <th>Período</th>
                                <th>Importe</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $hoyInicio = \Carbon\Carbon::now()->startOfMonth(); @endphp
                            @for($i = 1; $i <= $cuotasInfo['total']; $i++)
                                @php
                                    $yaFacturada  = in_array($i, $cuotasInfo['billed_list'] ?? []);
                                    $fechaCuota   = $fechaBase->copy()->addMonths($i - 1);
                                    $nombreMes    = $mesesEs[(int) $fechaCuota->format('n')];
                                    $anio         = $fechaCuota->format('Y');
                                    // Futura = el mes de la cuota todavía no llegó
                                    $esFutura     = $fechaCuota->startOfMonth()->gt($hoyInicio);
                                    $deshabilitada = $yaFacturada || $esFutura;
                                @endphp
                                <tr class="{{ $yaFacturada ? 'table-light text-muted' : ($esFutura ? 'table-light' : '') }}">
                                    <td>
                                        <input 
                                            type="checkbox" 
                                            class="checkbox cuota-checkbox" 
                                            name="cuotas[]" 
                                            value="{{ $i }}"
                                            @if($deshabilitada) disabled @endif
                                            onchange="handleCuotaChange(this)"
                                        >
                                    </td>
                                    <td>
                                        <span class="fw-semibold">Cuota {{ $i }} de {{ $cuotasInfo['total'] }}</span>
                                        @if($cuotasInfo['descripcion'])
                                            <small class="text-muted d-block">{{ $cuotasInfo['descripcion'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary fs-6">{{ $nombreMes }} {{ $anio }}</span>
                                    </td>
                                    <td class="fw-bold">{{ $formatCurrency($cuotasInfo['monto_indiv']) }}</td>
                                    <td>
                                        @if($yaFacturada)
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Facturada</span>
                                        @elseif($esFutura)
                                            <span class="badge bg-light text-secondary border" title="Solo se puede facturar el mes actual o meses anteriores">
                                                <i class="fas fa-lock me-1"></i>No disponible aún
                                            </span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

        @if($cotizacion->coti_cuotas)
            <div class="alert alert-info border-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Atención:</strong> Esta cotización está configurada para <strong>facturación por cuotas</strong>. 
                Utilice la sección superior para seleccionar las cuotas a facturar. Las muestras y análisis individuales están deshabilitados.
            </div>
        @endif

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
                                    @if($facturada || $cotizacion->coti_cuotas) disabled @endif
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
                                                            @if($analisis->instancia->facturado || $cotizacion->coti_cuotas) disabled @endif
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
        
        <div class="mb-3 container">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label for="observaciones" class="form-label fw-bold mb-0">Notas en la Factura:</label>
                <button type="button" id="btnGuardarNotas" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-save me-1"></i>Guardar Notas
                </button>
            </div>
            <textarea name="observaciones" id="observaciones" class="form-control" rows="3" placeholder="Escribe aquí las notas que aparecerán en la factura...">{{ $cotizacion->coti_notas_facturacion }}</textarea>
        </div>

        @if (!empty($refsFacturacion['puede_facturar']))
            <button id="btnFacturar" type="submit" class="btn btn-primary facturar-btn" disabled>
                <i class="fas fa-receipt me-1"></i>Facturar
            </button>
        @else
            <div class="d-flex flex-column align-items-end facturar-btn-group">
                <button type="button" class="btn btn-outline-danger mb-2" data-bs-toggle="modal" data-bs-target="#modalEditarRefs">
                    <i class="fas fa-plus me-1"></i>Cargar O.C
                </button>
                <button id="btnFacturar" type="button" class="btn btn-secondary facturar-btn-blocked" onclick="alert('{{ $refsFacturacion['mensaje_bloqueo'] }}')">
                    <i class="fas fa-receipt me-1"></i>Facturar (Bloqueado)
                </button>
            </div>
        @endif
    </form>
    
    <!-- Modal Editar Referencias -->
    <div class="modal fade" id="modalEditarRefs" tabindex="-1" aria-labelledby="modalEditarRefsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarRefsLabel">Editar Referencias de Facturación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarRefs">
                        <div class="mb-3">
                            <label for="modal_coti_oc_referencia" class="form-label">Orden de Compra (O.C.)</label>
                            <input type="text" class="form-control" id="modal_coti_oc_referencia" name="coti_oc_referencia" value="{{ $refsFacturacion['oc'] ?? '' }}">
                            @if (!empty($refsFacturacion['oc_obligatorio']))
                                <div class="form-text text-danger">Esta referencia es obligatoria para facturar.</div>
                            @endif
                        </div>

                        <hr class="my-4">
                        <div id="modalRefsContainer">
                            @foreach ($filasRefs as $idx => $fila)
                                <div class="row g-2 mb-2 ref-row">
                                    <div class="col-md-4">
                                        <label class="small text-muted">Tipo</label>
                                        <input type="text" class="form-control form-control-sm ref-tipo" value="{{ $fila['tipo'] ?? '' }}" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small text-muted">Valor</label>
                                        <input type="text" class="form-control form-control-sm ref-valor" value="{{ $fila['valor'] ?? '' }}">
                                        @if (!empty($fila['obligatorio_factura']))
                                            <div class="form-text text-danger mt-0" style="font-size: 0.7rem;">Obligatoria</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <input type="hidden" name="coti_refs_facturacion_json" id="modal_coti_refs_facturacion_json">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarRefs">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btnGuardarNotas').addEventListener('click', function() {
            const observations = document.getElementById('observaciones').value;
            const btn = this;
            const originalHtml = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';

            fetch("{{ route('facturacion.guardar-notas', ['cotizacion' => $cotizacion->coti_num]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ observaciones: observations })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-success');
                    btn.innerHTML = '<i class="fas fa-check me-1"></i>Guardado';
                    setTimeout(() => {
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-success');
                        btn.innerHTML = originalHtml;
                        btn.disabled = false;
                    }, 2000);
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });

        document.getElementById('btnGuardarRefs').addEventListener('click', function() {
            const oc = document.getElementById('modal_coti_oc_referencia').value;
            const rows = [];
            document.querySelectorAll('#modalRefsContainer .ref-row').forEach(row => {
                rows.push({
                    tipo: row.querySelector('.ref-tipo').value,
                    valor: row.querySelector('.ref-valor').value,
                    obligatorio_factura: row.innerHTML.includes('Obligatoria')
                });
            });

            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';

            fetch("{{ route('facturacion.update-referencias', ['cotizacion' => $cotizacion->coti_num]) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    coti_oc_referencia: oc,
                    coti_refs_facturacion_json: JSON.stringify(rows)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar con el servidor');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        });
    </script>
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

    function handleCuotaChange(checkbox) {
        if (checkbox.checked) {
            // Deseleccionar todas las muestras y análisis
            document.querySelectorAll('.sample-checkbox, .analysis-checkbox').forEach(cb => {
                cb.checked = false;
            });
        }
        updateFacturarButton();
        updateHiddenInputs();
    }

    function toggleAllTasks(checkbox, instanciaId) {
        if (checkbox.checked) {
            // Deseleccionar todas las cuotas
            document.querySelectorAll('.cuota-checkbox').forEach(cb => {
                cb.checked = false;
            });
        }
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
        
        if (sampleCheckbox.checked) {
             // Deseleccionar todas las cuotas
             document.querySelectorAll('.cuota-checkbox').forEach(cb => {
                cb.checked = false;
            });
        }

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

    function emailEnvioFacturaValido() {
        const card = document.getElementById('card-emails-envio-factura');
        if (!card) return true;
        const checks = card.querySelectorAll('.email-envio-factura-check');
        if (checks.length === 0) return true;
        return Array.from(checks).some(cb => cb.checked);
    }

    function updateFacturarButton() {
        const btnFacturar = document.getElementById('btnFacturar');
        if (!btnFacturar || btnFacturar.type === 'button') return; // Bloqueado por backend

        const anyChecked = document.querySelectorAll('.sample-checkbox:checked:not(:disabled), .analysis-checkbox:checked:not(:disabled), .cuota-checkbox:checked:not(:disabled)').length > 0;
        const emailOk = emailEnvioFacturaValido();
        btnFacturar.disabled = !anyChecked || !emailOk;
        btnFacturar.classList.toggle('btn-primary', anyChecked && emailOk);
        btnFacturar.classList.toggle('btn-secondary', !anyChecked || !emailOk);
    }

    function updateHiddenInputs() {
        const form = document.getElementById('facturarForm');
        form.querySelectorAll('input[name="muestras[]"], input[name="analisis[]"], input[name="cuotas[]"]').forEach(input => input.remove());

        // Recopilar cuotas seleccionadas
        document.querySelectorAll('.cuota-checkbox:checked:not(:disabled)').forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cuotas[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });

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
        document.querySelectorAll('.cuota-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => handleCuotaChange(checkbox));
        });
        document.querySelectorAll('.email-envio-factura-check').forEach(checkbox => {
            checkbox.addEventListener('change', () => updateFacturarButton());
        });

        const facturarForm = document.getElementById('facturarForm');
        if (facturarForm) {
            facturarForm.addEventListener('submit', function(e) {
                if (!emailEnvioFacturaValido()) {
                    e.preventDefault();
                    alert('Seleccione al menos un email de envío de factura antes de continuar.');
                }
            });
        }
        
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