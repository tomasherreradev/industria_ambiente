@extends('layouts.app')

@section('content')
@include('partials.operativo-styles')
<link rel="stylesheet" href="{{ asset('css/facturacion-facturar.css') }}?v={{ filemtime(public_path('css/facturacion-facturar.css')) }}">

@php
    $subtituloFacturar = collect([
        $datosCliente['razon_social'] ?? null,
        'Divisa ' . ($cotizacion->divisa_codigo ?? 'PES'),
    ])->filter()->implode(' · ');
@endphp

<div class="container py-4 ucrud ucrud-operativo ucrud-detalle facturacion-facturar-page" data-ucrud-root>
    @include('partials.ucrud-form-header', [
        'title' => 'Facturar cotización #' . $cotizacion->coti_num,
        'subtitle' => $subtituloFacturar,
        'backUrl' => route('facturacion.index'),
        'backLabel' => 'Volver a Facturación',
    ])

    @if (session('success'))
        <div class="ucrud-alert ucrud-alert--success mb-3" role="status">
            <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
            <x-heroicon-o-exclamation-circle style="width: 18px; height: 18px;" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @isset($datosCliente)
        <div class="ucrud-panel mb-4">
            <div class="fact-panel-head">
                <h2 class="ucrud-panel-title">Datos del cliente</h2>
                @if(!empty($datosCliente['cliente_edit_url']))
                    <a href="{{ $datosCliente['cliente_edit_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                        <x-heroicon-o-arrow-up-right style="width: 14px; height: 14px;" class="me-1" />
                        Ficha del cliente
                    </a>
                @endif
            </div>
            <div class="fact-panel-body fact-dato-grid">
                <div class="fact-dato">
                    <span class="fact-dato-label">Razón social (facturación)</span>
                    <span class="fact-dato-value">{{ $datosCliente['razon_social'] !== '' ? $datosCliente['razon_social'] : '—' }}</span>
                </div>
                <div class="fact-dato">
                    <span class="fact-dato-label">CUIT</span>
                    <span class="fact-dato-value">{{ $datosCliente['cuit'] !== '' ? $datosCliente['cuit'] : '—' }}</span>
                </div>
                <div class="fact-dato">
                    <span class="fact-dato-label">Cliente / titular</span>
                    <span class="fact-dato-value">{{ $datosCliente['cliente_linea'] !== '' ? $datosCliente['cliente_linea'] : '—' }}</span>
                </div>
                <div class="fact-dato">
                    <span class="fact-dato-label">Sucursal / establecimiento</span>
                    <span class="fact-dato-value">{{ $datosCliente['sucursal'] !== '' ? $datosCliente['sucursal'] : '—' }}</span>
                </div>
                @if($datosCliente['es_consultor'] && ($datosCliente['empresa_relacionada'] !== '' || $datosCliente['para'] !== ''))
                    <div class="fact-dato">
                        <span class="fact-dato-label">Consultora / destinatario</span>
                        <span class="fact-dato-value">
                            @if($datosCliente['empresa_relacionada'] !== '')
                                {{ $datosCliente['empresa_relacionada'] }}
                            @elseif($datosCliente['para'] !== '')
                                {{ $datosCliente['para'] }}
                            @else
                                —
                            @endif
                        </span>
                    </div>
                @elseif($datosCliente['para'] !== '')
                    <div class="fact-dato">
                        <span class="fact-dato-label">Para</span>
                        <span class="fact-dato-value">{{ $datosCliente['para'] }}</span>
                    </div>
                @endif
                <div class="fact-dato">
                    <span class="fact-dato-label">Dirección</span>
                    <span class="fact-dato-value">{{ $datosCliente['direccion'] !== '' ? $datosCliente['direccion'] : '—' }}</span>
                </div>
                <div class="fact-dato">
                    <span class="fact-dato-label">Localidad</span>
                    <span class="fact-dato-value">{{ $datosCliente['localidad'] !== '' ? $datosCliente['localidad'] : '—' }}</span>
                </div>
                <div class="fact-dato">
                    <span class="fact-dato-label">Cód. cliente</span>
                    <span class="fact-dato-value">{{ $datosCliente['codigo_cliente'] !== '' ? $datosCliente['codigo_cliente'] : '—' }}</span>
                </div>
            </div>
        </div>
    @endisset

    @isset($contactosEnvioFactura, $estadoEnvioFactura)
        @php
            $emailsCoincidentes = collect($estadoEnvioFactura['emails_coincidentes'] ?? []);
            $contactosCotiEnvio = collect($estadoEnvioFactura['contactos_coti'] ?? []);
            $requiereSeleccionEmail = ! empty($estadoEnvioFactura['requiere_seleccion']);
            $tieneEnvioConfigurado = ! empty($estadoEnvioFactura['tiene_envio_configurado']);
            $permiteSeleccionEnvio = $tieneEnvioConfigurado || $requiereSeleccionEmail || $contactosEnvioFactura->isNotEmpty();
            $emailsPreseleccionados = $contactosCotiEnvio
                ->pluck('correo')
                ->map(fn ($e) => \App\Support\CotizacionContactosFacturacion::normalizarEmail($e))
                ->filter()
                ->values()
                ->all();
        @endphp
        <div class="ucrud-panel mb-4" id="card-emails-envio-factura"
             data-cli-codigo="{{ $cliCodigo ?? '' }}"
             data-permite-seleccion="{{ $permiteSeleccionEnvio ? '1' : '0' }}"
             data-store-url="{{ !empty($cliCodigo) ? route('facturacion.contactos-envio.store', ['codigo' => $cliCodigo]) : '' }}">
            <div class="fact-panel-head">
                <div>
                    <h2 class="ucrud-panel-title">Email de envío de factura</h2>
                    <small class="text-muted">Se guardan en el cliente · tipo «Envío de factura»</small>
                </div>
                @if(!empty($cliCodigo))
                    <button type="button" class="btn btn-sm btn-primary" id="btnAgregarContactoEnvio">
                        <x-heroicon-o-plus style="width: 14px; height: 14px;" class="me-1" />
                        Agregar email
                    </button>
                @endif
            </div>
            <div class="fact-panel-body">
                @if ($tieneEnvioConfigurado)
                    @php
                        $contactosCotiSoloCotizacion = $contactosCotiEnvio->filter(function ($contactoCoti) use ($emailsCoincidentes) {
                            $emailNorm = \App\Support\CotizacionContactosFacturacion::normalizarEmail($contactoCoti['correo'] ?? '');

                            return ! $emailsCoincidentes->contains($emailNorm);
                        });
                    @endphp
                    <div class="alert alert-success small mb-0">
                        Marque a quién enviar la factura. La selección actual viene de la cotización; puede cambiarla y se guardará al facturar.
                    </div>
                    @if ($contactosCotiSoloCotizacion->isNotEmpty())
                        <ul class="list-unstyled fact-contacto-list mb-3 mt-3">
                            @foreach ($contactosCotiSoloCotizacion as $contactoCoti)
                                <li class="contacto-envio-item @if(! $loop->last) border-bottom @endif">
                                    <div class="contacto-envio-row">
                                        <input type="checkbox"
                                               class="form-check-input email-envio-factura-check mt-0"
                                               name="emails_envio_factura[]"
                                               value="{{ $contactoCoti['correo'] }}"
                                               form="facturarForm"
                                               checked>
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="badge bg-secondary me-1">Solo en cotización</span>
                                            @if (trim((string) ($contactoCoti['nombre'] ?? '')) !== '')
                                                <span class="fw-semibold">{{ $contactoCoti['nombre'] }}</span>
                                                <span class="text-muted">·</span>
                                            @endif
                                            <span>{{ $contactoCoti['correo'] }}</span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <hr class="my-3">
                    @endif
                    <p class="small text-muted mb-2">Contactos del cliente:</p>
                @elseif ($requiereSeleccionEmail)
                    <div class="alert alert-warning small mb-0">
                        Seleccione uno o más emails del cliente antes de facturar, o agregue uno nuevo.
                    </div>
                @else
                    <p class="text-muted mb-0 small">
                        Agregue emails de envío de factura al cliente. Si no hay ninguno, la factura se generará sin envío por email.
                    </p>
                @endif

                <ul class="list-unstyled fact-contacto-list mb-0" id="lista-contactos-envio-cliente">
                    @forelse ($contactosEnvioFactura as $contacto)
                        @include('facturacion.partials.contacto-envio-row', [
                            'contacto' => $contacto,
                            'permiteSeleccionEnvio' => $permiteSeleccionEnvio,
                            'requiereSeleccionEmail' => $requiereSeleccionEmail,
                            'emailsPreseleccionados' => $emailsPreseleccionados,
                        ])
                    @empty
                        <li class="text-muted small" id="sin-contactos-envio-msg">No hay contactos de envío de factura en el cliente.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="modal fade" id="modalContactoEnvio" tabindex="-1" aria-labelledby="modalContactoEnvioLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalContactoEnvioLabel">Agregar email de envío</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div id="contactoEnvioAlert" class="alert d-none" role="alert"></div>
                        <form id="formContactoEnvio">
                            @csrf
                            <input type="hidden" id="contacto_envio_id" value="">
                            <div class="mb-3">
                                <label for="contacto_envio_nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="contacto_envio_nombre" required maxlength="120">
                            </div>
                            <div class="mb-3">
                                <label for="contacto_envio_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="contacto_envio_email" required maxlength="120">
                            </div>
                            <div class="mb-0">
                                <label for="contacto_envio_telefono" class="form-label">Teléfono <span class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control" id="contacto_envio_telefono" maxlength="30">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="btnGuardarContactoEnvio">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    @endisset

    @isset($refsFacturacion)
        <div class="ucrud-panel mb-4">
            <div class="fact-panel-head">
                <div>
                    <h2 class="ucrud-panel-title">Referencias para facturación</h2>
                    <small class="text-muted">Mismos datos que se imprimen en la factura</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarRefs">
                    <x-heroicon-o-pencil-square style="width: 14px; height: 14px;" class="me-1" />
                    Editar referencias
                </button>
            </div>
            <div class="fact-panel-body">
                @if (empty($refsFacturacion['puede_facturar']) && !empty($refsFacturacion['mensaje_bloqueo']))
                    <div class="alert alert-warning mb-0">{{ $refsFacturacion['mensaje_bloqueo'] }}</div>
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
        <div class="ucrud-panel mb-4">
            <div class="fact-panel-head">
                <h2 class="ucrud-panel-title">Resumen económico</h2>
            </div>
            <div class="fact-panel-body fact-resumen-grid">
                <div class="fact-resumen-item">
                    <span class="label">Importe bruto</span>
                    <h5 class="mb-0">{{ $formatCurrency($resumenMontos['total_bruto'] ?? 0) }}</h5>
                </div>
                <div class="fact-resumen-item">
                    <span class="label">Descuento global</span>
                    <h5 class="mb-0">
                        {{ ($resumenMontos['descuento_global_porcentaje'] ?? 0) > 0 ? $formatPercent($resumenMontos['descuento_global_porcentaje']) : 'Sin descuento' }}
                    </h5>
                    @if(($resumenMontos['descuento_global_monto'] ?? 0) > 0)
                        <small class="text-muted">Ajuste: -{{ $formatCurrency($resumenMontos['descuento_global_monto']) }}</small>
                    @endif
                </div>
                <div class="fact-resumen-item">
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
                <div class="fact-resumen-item">
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
            $fechaFin = ! empty($cuotasInfo['fecha_fin'])
                ? ($cuotasInfo['fecha_fin'] instanceof \Carbon\Carbon
                    ? $cuotasInfo['fecha_fin']
                    : \Carbon\Carbon::parse($cuotasInfo['fecha_fin']))
                : null;
        @endphp
        <div class="ucrud-panel mb-4">
            <div class="fact-panel-head">
                <h2 class="ucrud-panel-title">
                    <x-heroicon-o-calendar-days style="width: 20px; height: 20px;" class="me-1" />
                    Facturación por cuotas / abono
                </h2>
            </div>
            <div class="fact-panel-body">
                @php
                    $tieneInteres = ($cuotasInfo['interes'] ?? 0) > 0;
                @endphp
                @if(! empty($cuotasInfo['usa_inicio_custom']) && ! empty($cuotasInfo['fecha_aprobacion']))
                    <div class="alert alert-light border mb-3 py-2 small">
                        <i class="fas fa-info-circle me-1 text-info"></i>
                        Cotización aprobada el <strong>{{ \Carbon\Carbon::parse($cuotasInfo['fecha_aprobacion'])->format('d/m/Y') }}</strong>.
                        Las cuotas se facturan desde
                        <strong>{{ $fechaBase->locale('es')->translatedFormat('F Y') }}</strong>
                        (inicio de ejecución).
                    </div>
                @endif
                <div class="row g-3 mb-3 small">
                    <div class="col-auto">
                        <span class="text-muted">Inicio ejecución:</span>
                        <strong class="ms-1">{{ $fechaBase->format('m/Y') }}</strong>
                    </div>
                    @if($fechaFin)
                    <div class="col-auto">
                        <span class="text-muted">Fin ejecución:</span>
                        <strong class="ms-1">{{ $fechaFin->format('m/Y') }}</strong>
                    </div>
                    @endif
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
                                    $esFutura     = $fechaCuota->startOfMonth()->gt($hoyInicio);
                                    $fueraPeriodo = $fechaFin && $fechaCuota->startOfMonth()->gt($fechaFin->copy()->startOfMonth());
                                    $deshabilitada = $yaFacturada || $esFutura || $fueraPeriodo;
                                @endphp
                                <tr class="{{ $yaFacturada ? 'table-light text-muted' : (($esFutura || $fueraPeriodo) ? 'table-light' : '') }}">
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
                                        @elseif($fueraPeriodo)
                                            <span class="badge bg-light text-secondary border" title="Fuera del período de ejecución definido">
                                                <i class="fas fa-ban me-1"></i>Fuera de período
                                            </span>
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

    <form id="facturarForm" action="{{ route('facturacion.facturar', ['cotizacion' => $cotizacion->coti_num]) }}" method="POST">
        @csrf
        <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->coti_num }}">

        <div class="ucrud-panel mb-4">
            <div class="fact-panel-head">
                <h2 class="ucrud-panel-title">Notas en la factura</h2>
                <button type="button" id="btnGuardarNotas" class="btn btn-sm btn-outline-success">
                    <x-heroicon-o-document-check style="width: 14px; height: 14px;" class="me-1" />
                    Guardar notas
                </button>
            </div>
            <div class="fact-panel-body">
                <textarea name="observaciones" id="observaciones" class="form-control" rows="3" placeholder="Notas que aparecerán en la factura...">{{ $cotizacion->coti_notas_facturacion }}</textarea>
            </div>
        </div>

        <div class="fact-footer-bar">
            <div class="fact-footer-actions">
                @if (empty($refsFacturacion['puede_facturar']))
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEditarRefs">
                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                        Cargar O.C.
                    </button>
                    <span id="btnFacturarWrap" class="d-inline-block fact-btn-facturar-wrap" tabindex="0"
                          data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover focus"
                          title="{{ $refsFacturacion['mensaje_bloqueo'] ?? 'No se puede facturar: complete las referencias obligatorias.' }}">
                        <button id="btnFacturar" type="button" class="btn btn-secondary" style="pointer-events: none;" aria-disabled="true">
                            <x-heroicon-o-currency-dollar style="width: 16px; height: 16px;" class="me-1" />
                            Facturar (bloqueado)
                        </button>
                    </span>
                @else
                    <span id="btnFacturarWrap" class="d-inline-block fact-btn-facturar-wrap" tabindex="0"
                          data-bs-toggle="tooltip" data-bs-placement="top" data-bs-trigger="hover focus"
                          title="Seleccione al menos una cuota, muestra o análisis para facturar.">
                        <button id="btnFacturar" type="submit" class="btn btn-secondary btn-lg" disabled style="pointer-events: none;">
                            <x-heroicon-o-currency-dollar style="width: 18px; height: 18px;" class="me-1" />
                            Facturar
                        </button>
                    </span>
                @endif
            </div>
        </div>
    </form>
</div>
    
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

    function getFacturarBloqueoMensaje(anyChecked, emailOk) {
        const esCuotas = document.querySelector('.cuota-checkbox') !== null;
        if (!anyChecked && !emailOk) {
            return esCuotas
                ? 'Seleccione al menos una cuota disponible y un email de envío de factura.'
                : 'Seleccione al menos una muestra o análisis y un email de envío de factura.';
        }
        if (!anyChecked) {
            return esCuotas
                ? 'Seleccione al menos una cuota disponible para facturar.'
                : 'Seleccione al menos una muestra o análisis para facturar.';
        }
        if (!emailOk) {
            return 'Seleccione al menos un email de envío de factura.';
        }
        return '';
    }

    function setFacturarTooltip(mensaje) {
        const wrap = document.getElementById('btnFacturarWrap');
        if (!wrap || typeof bootstrap === 'undefined') return;

        const tip = bootstrap.Tooltip.getOrCreateInstance(wrap, {
            placement: 'top',
            trigger: 'hover focus',
        });

        if (mensaje) {
            wrap.setAttribute('data-bs-title', mensaje);
            wrap.setAttribute('title', mensaje);
            tip.setContent({ '.tooltip-inner': mensaje });
            wrap.classList.add('fact-btn-facturar-wrap--bloqueado');
        } else {
            wrap.removeAttribute('data-bs-title');
            wrap.removeAttribute('title');
            tip.hide();
            wrap.classList.remove('fact-btn-facturar-wrap--bloqueado');
        }
    }

    function updateFacturarButton() {
        const btnFacturar = document.getElementById('btnFacturar');
        if (!btnFacturar || btnFacturar.type === 'button') return; // Bloqueado por backend

        const anyChecked = document.querySelectorAll('.sample-checkbox:checked:not(:disabled), .analysis-checkbox:checked:not(:disabled), .cuota-checkbox:checked:not(:disabled)').length > 0;
        const emailOk = emailEnvioFacturaValido();
        const bloqueado = !anyChecked || !emailOk;

        btnFacturar.disabled = bloqueado;
        btnFacturar.style.pointerEvents = bloqueado ? 'none' : '';
        btnFacturar.classList.toggle('btn-primary', !bloqueado);
        btnFacturar.classList.toggle('btn-secondary', bloqueado);
        btnFacturar.classList.toggle('btn-lg', true);

        setFacturarTooltip(bloqueado ? getFacturarBloqueoMensaje(anyChecked, emailOk) : '');
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
        
        if (typeof bootstrap !== 'undefined') {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                bootstrap.Tooltip.getOrCreateInstance(el, { placement: 'top', trigger: 'hover focus' });
            });
        }

        updateFacturarButton();
        initContactosEnvioFactura();
    });

    function initContactosEnvioFactura() {
        const card = document.getElementById('card-emails-envio-factura');
        if (!card) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const permiteSeleccion = card.dataset.permiteSeleccion === '1';
        const emailsPreseleccionados = @json($emailsPreseleccionados ?? []);
        const storeUrl = card.dataset.storeUrl || '';
        const modalEl = document.getElementById('modalContactoEnvio');
        const modal = modalEl && window.bootstrap ? new bootstrap.Modal(modalEl) : null;

        const btnAgregar = document.getElementById('btnAgregarContactoEnvio');
        const btnGuardar = document.getElementById('btnGuardarContactoEnvio');
        const alertBox = document.getElementById('contactoEnvioAlert');
        const inputId = document.getElementById('contacto_envio_id');
        const inputNombre = document.getElementById('contacto_envio_nombre');
        const inputEmail = document.getElementById('contacto_envio_email');
        const inputTelefono = document.getElementById('contacto_envio_telefono');
        const modalTitle = document.getElementById('modalContactoEnvioLabel');

        function contactoUpdateUrl(id) {
            return `/facturacion/contactos-envio/${id}`;
        }

        function mostrarAlerta(msg, tipo) {
            if (!alertBox) return;
            alertBox.textContent = msg;
            alertBox.className = `alert alert-${tipo}`;
            alertBox.classList.remove('d-none');
        }

        function limpiarAlerta() {
            if (!alertBox) return;
            alertBox.classList.add('d-none');
            alertBox.textContent = '';
        }

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        const iconPencilSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>';
        const iconTrashSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>';

        function renderContactoRow(contacto, isLast) {
            const nombre = escHtml(contacto.nombre || '');
            const email = escHtml(contacto.email || '');
            const telefono = escHtml(contacto.telefono || '');
            const emailNorm = String(contacto.email || '').trim().toLowerCase();
            const checked = emailsPreseleccionados.includes(emailNorm) ? ' checked' : '';
            const checkHtml = permiteSeleccion
                ? `<input type="checkbox" class="form-check-input email-envio-factura-check mt-0" name="emails_envio_factura[]" value="${email}" form="facturarForm"${checked}>`
                : '';
            const borderClass = isLast ? '' : ' border-bottom';
            return `
                <li class="contacto-envio-item${borderClass}"
                    data-contacto-id="${contacto.id}"
                    data-nombre="${nombre}"
                    data-email="${email}"
                    data-telefono="${telefono}">
                    <div class="contacto-envio-row">
                        ${checkHtml}
                        <div class="flex-grow-1 min-w-0">
                            ${nombre ? `<span class="fw-semibold">${nombre}</span><span class="text-muted"> · </span>` : ''}
                            <span>${email}</span>
                            ${telefono ? `<small class="text-muted ms-1">${telefono}</small>` : ''}
                        </div>
                        <div class="contacto-envio-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-contacto-envio" title="Editar" aria-label="Editar">
                                ${iconPencilSvg}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-contacto-envio" title="Eliminar" aria-label="Eliminar">
                                ${iconTrashSvg}
                            </button>
                        </div>
                    </div>
                </li>`;
        }

        function renderListaContactos(contactos) {
            const lista = document.getElementById('lista-contactos-envio-cliente');
            if (!lista) return;

            if (!contactos || contactos.length === 0) {
                lista.innerHTML = '<li class="text-muted small" id="sin-contactos-envio-msg">No hay contactos de envío de factura en el cliente.</li>';
            } else {
                lista.innerHTML = contactos.map((c, i) => renderContactoRow(c, i === contactos.length - 1)).join('');
            }

            lista.querySelectorAll('.email-envio-factura-check').forEach(cb => {
                cb.addEventListener('change', () => updateFacturarButton());
            });
            bindContactoRowActions();
            updateFacturarButton();
        }

        function abrirModal(modo, contacto) {
            limpiarAlerta();
            inputId.value = contacto?.id || '';
            inputNombre.value = contacto?.nombre || '';
            inputEmail.value = contacto?.email || '';
            inputTelefono.value = contacto?.telefono || '';
            modalTitle.textContent = modo === 'edit' ? 'Editar email de envío' : 'Agregar email de envío';
            modal?.show();
        }

        async function guardarContacto() {
            limpiarAlerta();
            const id = inputId.value.trim();
            const payload = {
                nombre: inputNombre.value.trim(),
                email: inputEmail.value.trim(),
                telefono: inputTelefono.value.trim() || null,
            };

            if (!payload.nombre || !payload.email) {
                mostrarAlerta('Complete nombre y email.', 'warning');
                return;
            }

            const url = id ? contactoUpdateUrl(id) : storeUrl;
            const method = id ? 'PUT' : 'POST';

            if (!url) {
                mostrarAlerta('No se pudo determinar la URL de guardado.', 'danger');
                return;
            }

            btnGuardar.disabled = true;
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Error al guardar el contacto.');
                }
                renderListaContactos(data.contactos || []);
                modal?.hide();
            } catch (err) {
                mostrarAlerta(err.message || 'Error al guardar.', 'danger');
            } finally {
                btnGuardar.disabled = false;
            }
        }

        async function eliminarContacto(id) {
            if (!confirm('¿Eliminar este email de envío de factura del cliente?')) return;

            try {
                const res = await fetch(contactoUpdateUrl(id), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Error al eliminar.');
                }
                renderListaContactos(data.contactos || []);
            } catch (err) {
                alert(err.message || 'Error al eliminar el contacto.');
            }
        }

        function bindContactoRowActions() {
            document.querySelectorAll('.btn-editar-contacto-envio').forEach(btn => {
                btn.addEventListener('click', function () {
                    const li = this.closest('.contacto-envio-item');
                    if (!li) return;
                    abrirModal('edit', {
                        id: li.dataset.contactoId,
                        nombre: li.dataset.nombre || '',
                        email: li.dataset.email || '',
                        telefono: li.dataset.telefono || '',
                    });
                });
            });

            document.querySelectorAll('.btn-eliminar-contacto-envio').forEach(btn => {
                btn.addEventListener('click', function () {
                    const li = this.closest('.contacto-envio-item');
                    if (!li?.dataset.contactoId) return;
                    eliminarContacto(li.dataset.contactoId);
                });
            });
        }

        btnAgregar?.addEventListener('click', () => abrirModal('create', null));
        btnGuardar?.addEventListener('click', guardarContacto);
        bindContactoRowActions();
    }
</script>
@endsection