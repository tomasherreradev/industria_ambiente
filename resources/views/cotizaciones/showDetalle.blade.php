@extends('layouts.app')

@section('content')
<div class="pdf-preview-container">
    @php
        // IMPORTANTE: $tareas ya viene del controlador con los datos correctos de la versión seleccionada
        // Si es una versión histórica, $tareas contiene los items de esa versión
        // Si es la versión actual, $tareas contiene los items actuales de la BD
        // Asegurar que $tareas sea una colección
        if (!isset($tareas) || is_null($tareas)) {
            // Fallback: si no viene del controlador, usar los de la cotización
        $tareas = $cotizacion->tareas ?? collect();
        }
        $tareasCollection = $tareas instanceof \Illuminate\Support\Collection ? $tareas : collect($tareas);
        $ensayos = $tareasCollection->where('cotio_subitem', 0);
        $componentes = $tareasCollection->where('cotio_subitem', '>', 0);

        // Agrupar ítems con sus componentes y métodos
        $itemsAgrupados = [];
        foreach ($ensayos as $ensayo) {
            $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);
            
            $cantidadMuestras = (float) ($ensayo->cotio_cantidad ?? 1);
            if ($cantidadMuestras <= 0) {
                $cantidadMuestras = 1;
            }

            $precioUnitario = $componentesDelEnsayo->sum(function ($componente) {
                $precio = (float) ($componente->cotio_precio ?? 0);
                $cantidad = (float) ($componente->cotio_cantidad ?? 1);
                if ($cantidad <= 0) {
                    $cantidad = 1;
                }
                return $precio * $cantidad;
            });

            $componentesConMetodos = [];
            foreach ($componentesDelEnsayo as $componente) {
                $metodoTexto = '';
                
                // Cargar relaciones si no están cargadas
                if (!$componente->relationLoaded('metodoAnalisis') && $componente->cotio_codigometodo_analisis) {
                    $componente->load('metodoAnalisis');
                }
                if (!$componente->relationLoaded('metodoMuestreo') && $componente->cotio_codigometodo) {
                    $componente->load('metodoMuestreo');
                }
                
                // Intentar obtener método de análisis primero
                if ($componente->cotio_codigometodo_analisis) {
                    if ($componente->metodoAnalisis) {
                        $metodoTexto = $componente->metodoAnalisis->nombre ?? '';
                    } else {
                        // Buscar directamente si la relación no está cargada
                        $metodoAnalisis = \App\Models\MetodoAnalisis::where('codigo', $componente->cotio_codigometodo_analisis)->first();
                        if ($metodoAnalisis) {
                            $metodoTexto = $metodoAnalisis->nombre ?? '';
                        }
                    }
                }
                
                // Si no hay método de análisis, intentar método de muestreo
                if (empty($metodoTexto) && $componente->cotio_codigometodo) {
                    // Intentar desde la relación metodoMuestreo
                    if ($componente->metodoMuestreo) {
                        $metodoTexto = $componente->metodoMuestreo->metodo_descripcion ?? $componente->metodoMuestreo->nombre ?? '';
                    }
                    
                    // Si aún no hay, buscar en la tabla metodo
                    if (empty($metodoTexto)) {
                        $metodo = \App\Models\Metodo::where('metodo_codigo', $componente->cotio_codigometodo)->first();
                        if ($metodo) {
                            $metodoTexto = $metodo->metodo_descripcion ?? '';
                        }
                    }
                }

                $componentesConMetodos[] = [
                    'descripcion' => $componente->cotio_descripcion ?? '',
                    'metodo' => $metodoTexto
                ];
            }

            // Parsear notas desde JSON
            $notas = [];
            if (!empty($ensayo->cotio_nota_contenido)) {
                try {
                    $notasParsed = json_decode($ensayo->cotio_nota_contenido, true);
                    if (is_array($notasParsed)) {
                        $notas = $notasParsed;
                    } else {
                        // Formato antiguo: nota simple
                        if (!empty($ensayo->cotio_nota_tipo)) {
                            $notas = [['tipo' => $ensayo->cotio_nota_tipo, 'contenido' => $ensayo->cotio_nota_contenido]];
                        }
                    }
                } catch (\Exception $e) {
                    // No es JSON, es formato antiguo
                    if (!empty($ensayo->cotio_nota_tipo)) {
                        $notas = [['tipo' => $ensayo->cotio_nota_tipo, 'contenido' => $ensayo->cotio_nota_contenido]];
                    }
                }
            }
            
            // Filtrar solo notas imprimibles
            $notasImprimibles = collect($notas)->filter(function($nota) {
                return isset($nota['tipo']) && $nota['tipo'] === 'imprimible';
            })->values()->toArray();

            $itemsAgrupados[] = [
                'item' => $ensayo->cotio_item,
                'descripcion' => $ensayo->cotio_descripcion ?? '',
                'cantidad' => $cantidadMuestras,
                'precio_unitario' => $precioUnitario,
                'importe' => $cantidadMuestras * $precioUnitario,
                'componentes' => $componentesConMetodos,
                'notas' => $notasImprimibles
            ];
        }

        $componentesSinCategoria = $componentes->filter(function ($componente) use ($ensayos) {
            return !$ensayos->contains('cotio_item', $componente->cotio_item);
        });

        $totalComponentesSinCategoria = $componentesSinCategoria->sum(function ($componente) {
            $precio = (float) ($componente->cotio_precio ?? 0);
            $cantidad = (float) ($componente->cotio_cantidad ?? 1);
            if ($cantidad <= 0) {
                $cantidad = 1;
            }
            return $precio * $cantidad;
        });

        $totalCalculado = collect($itemsAgrupados)->sum('importe') + (float) $totalComponentesSinCategoria;

        $formatCurrency = function ($value) {
            return number_format((float) $value, 2, ',', '.');
        };

        $formatDate = function ($date) {
            return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '—';
        };

        $cliente = $cotizacion->cliente ?? null;
        $descuentoGlobal = max((float) ($descuentoGlobalCliente ?? 0), 0);
        $descuentoSector = max((float) ($descuentoSectorCliente ?? 0), 0);
        $descuentoTotal = max((float) ($descuentoTotalCliente ?? ($descuentoGlobal + $descuentoSector)), 0);
        $descuentoGlobalMonto = $totalCalculado * ($descuentoGlobal / 100);
        $descuentoSectorMonto = $totalCalculado * ($descuentoSector / 100);
        $importeConDescuento = $totalCalculado - ($descuentoGlobalMonto + $descuentoSectorMonto);

        // Obtener empresa relacionada
        $empresaRelacionadaDetalle = null;
        if ($cotizacion->coti_cli_empresa) {
            $empresaRelacionadaDetalle = \App\Models\ClienteEmpresaRelacionada::find($cotizacion->coti_cli_empresa);
        }

        // Datos del cliente para mostrar
        $nombreCliente = $empresaRelacionadaDetalle ? $empresaRelacionadaDetalle->razon_social : ($cotizacion->coti_para ?? $cotizacion->coti_empresa ?? optional($cliente)->cli_razonsocial ?? '');
        $direccionCliente = $cotizacion->coti_direccioncli ?? optional($cliente)->cli_direccion ?? '';
        $localidadCliente = $cotizacion->coti_localidad ?? optional($cliente)->cli_localidad ?? '';
        $partidoCliente = $cotizacion->coti_partido ?? optional($cliente)->cli_partido ?? '';
        $cuitCliente = $empresaRelacionadaDetalle ? $empresaRelacionadaDetalle->cuit : ($cotizacion->coti_cuit ?? optional($cliente)->cli_cuit ?? '');
        $contactoCliente = $cotizacion->coti_contacto ?? '';
        $mailCliente = $cotizacion->coti_mail1 ?? optional($cliente)->cli_email ?? '';
        $telefonoCliente = $cotizacion->coti_telefono ?? optional($cliente)->cli_telefono ?? '';
        $codigoCliente = $cotizacion->coti_codigocli ?? optional($cliente)->cli_codigo ?? '';
    @endphp

    <!-- Botón de impresión flotante -->
    <div class="print-button-container">
        <div class="d-flex flex-column gap-2 align-items-end">
            <select id="selectorVersionDetalle" class="form-select form-select-sm" style="width: auto; min-width: 200px; background: white;">
                <option value="">Cargando versiones...</option>
            </select>
        <a href="{{ route('ventas.print', $cotizacion->coti_num) }}"
            class="btn-print-pdf"
            target="_blank"
            rel="noopener"
            title="Imprimir cotización">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" class="btn-icon">
                <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
                <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1v-4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v4H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
            </svg>
            <span class="btn-text">Imprimir PDF</span>
        </a>
        </div>
    </div>

    <!-- Documento PDF Preview -->
    <div class="pdf-document">
        <!-- Botón de impresión móvil (dentro del documento) -->
        <div class="print-button-mobile">
            <a href="{{ route('ventas.print', $cotizacion->coti_num) }}"
                class="btn-print-mobile"
                target="_blank"
                rel="noopener"
                title="Imprimir cotización">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
                    <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1v-4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v4H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                </svg>
                <span>Imprimir PDF</span>
            </a>
        </div>
        
        <!-- Encabezado -->
        <div class="pdf-header">
            <div class="header-left">
                <div class="logo-section">
                    @if(file_exists(public_path('assets/img/logo.png')))
                        <img src="{{ asset('assets/img/logo.png') }}" alt="Industria y Ambiente S.A." class="logo-img">
                    @else
                        <div class="logo-placeholder">
                            <div class="logo-box">
                                <span class="logo-text">IYA</span>
                            </div>
                        </div>
                    @endif
                    <div class="company-name">INDUSTRIA Y AMBIENTE S.A.</div>
                </div>
            </div>
            <div class="header-right">
                <div class="website-bar">www.industriayambiente.com.ar</div>
            </div>
        </div>

        <!-- Información de cotización y control -->
        <div class="quote-info-section">
            <div class="quote-info-left">
                <div class="quote-info-item">
                    <strong>Cotización:</strong> {{ str_pad($cotizacion->coti_num, 8, '0', STR_PAD_LEFT) }}
                </div>
                <div class="quote-info-item">
                    <strong>Fecha:</strong> {{ $formatDate($cotizacion->coti_fechaalta) }}
                </div>
                <div class="quote-info-item">
                    <strong>Página:</strong> 1 / 1
                </div>
            </div>
            <div class="quote-info-right">
                <table class="control-table">
                    <tr>
                        <td><strong>CODIGO</strong> R048</td>
                    </tr>
                    <tr>
                        <td><strong>VERSIÓN:</strong> {{ $cotizacion->coti_version ?? 1 }}</td>
                    </tr>
                    <tr>
                        <td><strong>FECHA EMISIÓN</strong> {{ $formatDate($cotizacion->coti_fechaalta) }}</td>
                    </tr>
                    <tr>
                        <td><strong>DR:</strong> PR10</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="client-section">
            <div class="client-left">
                <div class="client-label">Destinatario:</div>
                <div class="client-name">{{ $nombreCliente }}</div>
                @if($direccionCliente)
                    <div class="client-address">{{ $direccionCliente }}</div>
                @endif
                @if($localidadCliente || $partidoCliente)
                    <div class="client-location">
                        {{ trim($localidadCliente . ($partidoCliente ? ' - ' . $partidoCliente : '')) }}
                        @if($partidoCliente && !$localidadCliente)
                            {{ $partidoCliente }}
                        @endif
                    </div>
                @endif
                @if($contactoCliente)
                    <div class="client-contact">Atn. {{ $contactoCliente }}</div>
                @endif
                @if($mailCliente)
                    <div class="client-email">Mail: {{ $mailCliente }}</div>
                @endif
                @if($telefonoCliente)
                    <div class="client-phone">Tel.: {{ $telefonoCliente }}</div>
                @endif
            </div>
            <div class="client-right">
                @if($cuitCliente)
                    <div class="client-fiscal">C.U.I.T.: {{ $cuitCliente }}</div>
                @endif
                @if($codigoCliente)
                    <div class="client-code">Cliente: {{ $codigoCliente }}</div>
                @endif
            </div>
        </div>

        @if($cotizacion->coti_referencia_valor)
        <div class="reference-section">
            <strong>REF.:</strong> {{ $cotizacion->coti_referencia_valor }}
        </div>
        @endif

        <!-- Detalles adicionales del servicio -->
        @if($cotizacion->coti_cadena_custodia || $cotizacion->coti_muestreo)
        <div class="service-details-section">
            <div class="service-details-title">Características del Servicio:</div>
            <div class="service-details-list">
                @if($cotizacion->coti_cadena_custodia)
                    <div class="service-detail-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="service-icon">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="m10.97 4.97-.02.022a.75.75 0 1 0 1.04 1.04l.01-.01a.75.75 0 1 0-1.05-1.05m-2.95 2.95a.75.75 0 0 0-1.08.022L7.477 9.384 6.28 8.287a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.15-.106l2-2.5a.75.75 0 0 0-.01-.94Z"/>
                        </svg>
                        <span>Requiere Cadena de Custodia</span>
                    </div>
                @endif
                @if($cotizacion->coti_muestreo)
                    <div class="service-detail-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="service-icon">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="m10.97 4.97-.02.022a.75.75 0 1 0 1.04 1.04l.01-.01a.75.75 0 1 0-1.05-1.05m-2.95 2.95a.75.75 0 0 0-1.08.022L7.477 9.384 6.28 8.287a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.15-.106l2-2.5a.75.75 0 0 0-.01-.94Z"/>
                        </svg>
                        <span>Requiere Servicio de Muestreo</span>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Texto introductorio -->
        <div class="intro-text">
            De nuestra consideración: Tenemos el agrado de dirigirnos a Ud/s. a fin de someter a vuestra consideración el presente presupuesto:
        </div>

        <!-- Tabla de ítems -->
        <div class="items-section">
            <!-- Versión desktop/tablet: tabla -->
            <div class="items-table-desktop">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th class="col-cant">Cant.</th>
                            <th class="col-item">Item</th>
                            <th class="col-unitario">Unitario</th>
                            <th class="col-importe">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($itemsAgrupados as $item)
                            <tr class="item-row">
                                <td class="col-cant text-center">{{ number_format($item['cantidad'], 0, ',', '.') }}</td>
                                <td class="col-item">
                                    <div class="item-description">{{ $item['descripcion'] }}</div>
                                    @if(!empty($item['componentes']))
                                        @foreach($item['componentes'] as $componente)
                                            <div class="component-item">
                                                <span class="component-name">{{ $componente['descripcion'] }}</span>
                                                @if(!empty($componente['metodo']))
                                                    <span class="component-method">Método: {{ $componente['metodo'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                    @if(!empty($item['notas']))
                                        @foreach($item['notas'] as $nota)
                                            <div class="item-note">
                                                <span class="note-type-badge note-type-imprimible">
                                                    Nota Imprimible
                                                </span>
                                                <span class="note-content">{{ $nota['contenido'] ?? '' }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="col-unitario text-right">$ {{ $formatCurrency($item['precio_unitario']) }}</td>
                                <td class="col-importe text-right">$ {{ $formatCurrency($item['importe']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center">No hay ítems registrados para esta cotización.</td>
                            </tr>
                        @endforelse

                        @if($componentesSinCategoria->isNotEmpty())
                            <tr class="item-row">
                                <td class="col-cant text-center">—</td>
                                <td class="col-item">
                                    <div class="item-description">Componentes sin categoría asociada</div>
                                </td>
                                <td class="col-unitario text-right">—</td>
                                <td class="col-importe text-right">$ {{ $formatCurrency($totalComponentesSinCategoria) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            
            <!-- Versión móvil: tarjetas -->
            <div class="items-cards-mobile">
                @forelse($itemsAgrupados as $item)
                    <div class="item-card">
                        <div class="card-header-row">
                            <span class="card-cantidad">Cant: {{ number_format($item['cantidad'], 0, ',', '.') }}</span>
                            <span class="card-item-number">Item #{{ $item['item'] }}</span>
                        </div>
                        <div class="card-description">{{ $item['descripcion'] }}</div>
                        @if(!empty($item['componentes']))
                            <div class="card-components">
                                @foreach($item['componentes'] as $componente)
                                    <div class="card-component-item">
                                        <div class="card-component-name">{{ $componente['descripcion'] }}</div>
                                        @if(!empty($componente['metodo']))
                                            <div class="card-component-method">Método: {{ $componente['metodo'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if(!empty($item['notas']))
                            @foreach($item['notas'] as $nota)
                                <div class="card-note">
                                    <span class="note-type-badge note-type-imprimible">
                                        Nota Imprimible
                                    </span>
                                    <span class="note-content">{{ $nota['contenido'] ?? '' }}</span>
                                </div>
                            @endforeach
                        @endif
                        <div class="card-footer-row">
                            <div class="card-price">
                                <span class="card-label">Unitario:</span>
                                <span class="card-value">$ {{ $formatCurrency($item['precio_unitario']) }}</span>
                            </div>
                            <div class="card-total">
                                <span class="card-label">Importe:</span>
                                <span class="card-value">$ {{ $formatCurrency($item['importe']) }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="item-card">
                        <div class="card-description text-center">No hay ítems registrados para esta cotización.</div>
                    </div>
                @endforelse

                @if($componentesSinCategoria->isNotEmpty())
                    <div class="item-card">
                        <div class="card-description">Componentes sin categoría asociada</div>
                        <div class="card-footer-row">
                            <div class="card-total">
                                <span class="card-label">Importe:</span>
                                <span class="card-value">$ {{ $formatCurrency($totalComponentesSinCategoria) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Resumen económico -->
        <div class="summary-section">
            <table class="summary-table">
                <tr>
                    <td class="summary-label">Subtotal:</td>
                    <td class="summary-value">$ {{ $formatCurrency($totalCalculado) }}</td>
                </tr>
                @if($descuentoGlobal > 0)
                <tr>
                    <td class="summary-label">Descuento global ({{ number_format($descuentoGlobal, 2, ',', '.') }}%):</td>
                    <td class="summary-value text-danger">- $ {{ $formatCurrency($descuentoGlobalMonto) }}</td>
                </tr>
                @endif
                @if($descuentoSector > 0)
                <tr>
                    <td class="summary-label">Descuento sector ({{ number_format($descuentoSector, 2, ',', '.') }}%):</td>
                    <td class="summary-value text-danger">- $ {{ $formatCurrency($descuentoSectorMonto) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="summary-label"><strong>TOTAL:</strong></td>
                    <td class="summary-value"><strong>$ {{ $formatCurrency($importeConDescuento) }}</strong></td>
                </tr>
            </table>
        </div>

        <!-- Pie de página -->
        <div class="pdf-footer">
            <p>Documento confidencial. Su uso está limitado al cliente destinatario. Industria y Ambiente S.A.</p>
        </div>
    </div>
</div>

<!-- Comentario separado del documento PDF -->
@if(!empty($cotizacion->coti_notas))
<div class="comment-section">
    <div class="comment-header">
        <strong>Notas / Observaciones:</strong>
    </div>
    <div class="comment-content">
        <p>{{ $cotizacion->coti_notas }}</p>
    </div>
</div>
@endif

<style>
.pdf-preview-container {
    background-color: #f5f5f5;
    padding: 2rem;
    min-height: 100vh;
}

.print-button-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
}

.btn-print-pdf {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background-color: #0d6efd;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-print-pdf:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    color: white;
}

.btn-print-pdf .btn-icon {
    flex-shrink: 0;
}

.btn-print-pdf .btn-text {
    display: inline;
}

/* Botón de impresión móvil (dentro del documento) */
.print-button-mobile {
    display: none;
    margin-bottom: 20px;
    text-align: center;
}

.btn-print-mobile {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background-color: #0d6efd;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-weight: 500;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.btn-print-mobile:hover {
    background-color: #0b5ed7;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    color: white;
}

.btn-print-mobile svg {
    width: 18px;
    height: 18px;
}

.pdf-document {
    max-width: 210mm;
    margin: 0 auto;
    background: white;
    padding: 20mm 15mm;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    font-family: 'Arial', 'Helvetica', sans-serif;
    color: #000;
    line-height: 1.4;
}

/* Encabezado */
.pdf-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #0d6efd;
}

.header-left {
    flex: 1;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo-img {
    max-width: 120px;
    height: auto;
}

.logo-placeholder {
    width: 80px;
    height: 80px;
}

.logo-box {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
}

.logo-text {
    color: white;
    font-weight: bold;
    font-size: 24px;
}

.company-name {
    font-size: 14px;
    font-weight: bold;
    color: #000;
    text-transform: uppercase;
}

.header-right {
    flex: 1;
    text-align: right;
}

.website-bar {
    background-color: #0d6efd;
    color: white;
    padding: 8px 15px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

/* Certificaciones */
.certifications-row {
    display: flex;
    gap: 10px;
    margin: 10px 0 20px;
    flex-wrap: wrap;
}

.cert-logo {
    padding: 5px 10px;
    background-color: #f0f0f0;
    border: 1px solid #ddd;
    font-size: 10px;
    color: #666;
    border-radius: 3px;
}

/* Información de cotización */
.quote-info-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    font-size: 11px;
}

.quote-info-left {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.quote-info-item {
    color: #333;
}

.quote-info-right {
    text-align: right;
}

.control-table {
    border: 1px solid #000;
    border-collapse: collapse;
    font-size: 9px;
}

.control-table td {
    border: 1px solid #000;
    padding: 4px 8px;
    text-align: left;
}

/* Sección Cliente */
.client-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    font-size: 11px;
}

.client-left {
    flex: 1;
}

.client-label {
    font-weight: bold;
    margin-bottom: 5px;
}

.client-name {
    font-weight: bold;
    margin-bottom: 3px;
}

.client-address,
.client-location,
.client-contact,
.client-email,
.client-phone {
    margin-bottom: 2px;
    color: #333;
}

.client-right {
    text-align: right;
}

.client-fiscal,
.client-code {
    margin-bottom: 3px;
    font-size: 11px;
}

.reference-section {
    margin-bottom: 15px;
    font-size: 11px;
}

/* Sección de detalles del servicio */
.service-details-section {
    margin: 15px 0 20px 0;
    padding: 12px 15px;
    background-color: #f8f9fa;
    border-left: 4px solid #0d6efd;
    border-radius: 4px;
}

.service-details-title {
    font-weight: bold;
    font-size: 11px;
    color: #0d2b5f;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.service-details-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.service-detail-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 10px;
    color: #333;
}

.service-icon {
    color: #0d6efd;
    flex-shrink: 0;
}

/* Texto introductorio */
.intro-text {
    margin: 20px 0;
    font-size: 11px;
    font-style: italic;
}

/* Tabla de ítems */
.items-section {
    margin: 20px 0;
}

.items-table-desktop {
    display: block;
}

.items-cards-mobile {
    display: none;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
}

.items-table thead {
    background-color: #f8f9fa;
}

.items-table th {
    border: 1px solid #ddd;
    padding: 8px 5px;
    text-align: left;
    font-weight: bold;
    font-size: 10px;
}

.items-table td {
    border: 1px solid #ddd;
    padding: 8px 5px;
    vertical-align: top;
}

.item-row {
    background-color: #fff;
}

.item-row:nth-child(even) {
    background-color: #fafafa;
}

.col-cant {
    width: 8%;
    text-align: center;
}

.col-item {
    width: 52%;
}

.col-unitario {
    width: 20%;
    text-align: right;
}

.col-importe {
    width: 20%;
    text-align: right;
}

.item-description {
    font-weight: bold;
    margin-bottom: 5px;
    font-size: 10px;
}

.component-item {
    margin-top: 4px;
    padding-left: 10px;
    font-size: 9px;
    color: #555;
}

.component-name {
    display: block;
    margin-bottom: 2px;
}

    .component-method {
        display: block;
        font-size: 8px;
        color: #777;
        font-style: italic;
    }

/* Tarjetas móviles */
.items-cards-mobile {
    display: none;
}

.item-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.card-header-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.card-cantidad,
.card-item-number {
    font-weight: bold;
    font-size: 11px;
    color: #0d6efd;
}

.card-description {
    font-weight: bold;
    font-size: 12px;
    margin-bottom: 10px;
    color: #000;
}

.card-components {
    margin: 10px 0;
    padding-left: 10px;
}

.card-component-item {
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px dotted #eee;
}

.card-component-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.card-component-name {
    font-size: 11px;
    color: #555;
    margin-bottom: 3px;
}

.card-component-method {
    font-size: 9px;
    color: #777;
    font-style: italic;
}

.card-footer-row {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid #0d6efd;
}

.card-price,
.card-total {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.card-label {
    font-size: 9px;
    color: #666;
    margin-bottom: 3px;
}

.card-value {
    font-weight: bold;
    font-size: 13px;
    color: #000;
}

.card-total .card-value {
    color: #0d6efd;
    font-size: 14px;
}

/* Resumen económico */
.summary-section {
    margin-top: 30px;
    margin-left: auto;
    width: 50%;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.summary-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #ddd;
}

.summary-label {
    text-align: right;
    padding-right: 15px;
}

.summary-value {
    text-align: right;
    font-weight: 500;
}

.total-row {
    border-top: 2px solid #000;
    background-color: #f8f9fa;
}

.total-row td {
    padding-top: 10px;
    padding-bottom: 10px;
    font-size: 12px;
}

/* Notas */
.notes-section {
    margin-top: 25px;
    font-size: 10px;
    padding: 10px;
    background-color: #f8f9fa;
    border-left: 3px solid #0d6efd;
}

.notes-section p {
    margin: 5px 0 0 0;
}

/* Pie de página */
.pdf-footer {
    margin-top: 40px;
    text-align: center;
    font-size: 9px;
    color: #666;
    border-top: 1px solid #ddd;
    padding-top: 10px;
}

/* Utilidades */
.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.text-danger {
    color: #dc3545;
}

/* Responsive Styles */
@media (max-width: 1200px) {
    .pdf-document {
        max-width: 100%;
        padding: 15mm 10mm;
    }
    
    .summary-section {
        width: 60%;
    }
}

@media (max-width: 992px) {
    .pdf-preview-container {
        padding: 1rem;
    }
    
    .pdf-document {
        padding: 15mm 8mm;
    }
    
    .logo-section {
        flex-direction: column;
        gap: 10px;
    }
    
    .logo-img {
        max-width: 100px;
    }
    
    .logo-placeholder {
        width: 60px;
        height: 60px;
    }
    
    .logo-text {
        font-size: 20px;
    }
    
    .company-name {
        font-size: 12px;
    }
    
    .website-bar {
        font-size: 11px;
        padding: 6px 12px;
    }
    
    .summary-section {
        width: 70%;
    }
}

@media (max-width: 768px) {
    .pdf-preview-container {
        padding: 0.5rem;
    }
    
    .print-button-container {
        top: 10px;
        right: 10px;
        position: fixed;
        z-index: 1000;
    }
    
    .print-button-mobile {
        display: block;
    }
    
    .btn-print-pdf {
        padding: 10px 15px;
        font-size: 14px;
        min-width: 44px;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-print-pdf .btn-icon {
        width: 18px;
        height: 18px;
    }
    
    .btn-print-pdf .btn-text {
        display: inline;
    }
    
    .pdf-document {
        padding: 10mm 5mm;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    
    /* Encabezado responsive */
    .pdf-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .header-left,
    .header-right {
        width: 100%;
        text-align: left;
    }
    
    .logo-section {
        flex-direction: row;
        align-items: center;
    }
    
    .logo-img {
        max-width: 80px;
    }
    
    .logo-placeholder {
        width: 50px;
        height: 50px;
    }
    
    .logo-text {
        font-size: 18px;
    }
    
    .company-name {
        font-size: 11px;
    }
    
    .website-bar {
        display: block;
        text-align: center;
        font-size: 10px;
        padding: 6px 10px;
    }
    
    /* Información de cotización responsive */
    .quote-info-section {
        flex-direction: column;
        gap: 15px;
    }
    
    .quote-info-right {
        text-align: left;
    }
    
    .control-table {
        font-size: 8px;
    }
    
    /* Cliente responsive */
    .client-section {
        flex-direction: column;
        gap: 15px;
    }
    
    .client-right {
        text-align: left;
    }
    
    /* Tabla de ítems responsive */
    .items-section {
        margin: 15px 0;
    }
    
    .items-table-desktop {
        display: none;
    }
    
    .items-cards-mobile {
        display: block;
    }
    
    .items-table {
        min-width: 600px;
        font-size: 9px;
    }
    
    .items-table th,
    .items-table td {
        padding: 6px 4px;
    }
    
    .col-cant {
        width: 10%;
    }
    
    .col-item {
        width: 50%;
    }
    
    .col-unitario {
        width: 20%;
    }
    
    .col-importe {
        width: 20%;
    }
    
    .item-description {
        font-size: 9px;
    }
    
    .component-item {
        font-size: 8px;
        padding-left: 8px;
    }
    
    .component-method {
        font-size: 7px;
    }
    
    /* Resumen responsive */
    .summary-section {
        width: 100%;
        margin-left: 0;
    }
    
    .summary-table {
        font-size: 10px;
    }
    
    .summary-table td {
        padding: 5px 8px;
    }
    
    .total-row td {
        font-size: 11px;
    }
    
    /* Textos responsive */
    .intro-text {
        font-size: 10px;
        margin: 15px 0;
    }
    
    .reference-section {
        font-size: 10px;
    }
    
    .service-details-section {
        padding: 10px 12px;
        margin: 12px 0 15px 0;
    }
    
    .service-details-title {
        font-size: 10px;
    }
    
    .service-detail-item {
        font-size: 9px;
    }
    
    .service-icon {
        width: 14px;
        height: 14px;
    }
    
    .notes-section {
        font-size: 9px;
        padding: 8px;
    }
    
    .pdf-footer {
        font-size: 8px;
        margin-top: 30px;
    }
}

@media (max-width: 576px) {
    .pdf-preview-container {
        padding: 0.25rem;
    }
    
    .print-button-container {
        top: 10px;
        right: 10px;
        position: fixed;
        z-index: 1000;
    }
    
    .print-button-mobile {
        display: block;
    }
    
    .btn-print-pdf {
        padding: 12px 16px;
        font-size: 13px;
        gap: 6px;
        min-width: 48px;
        min-height: 48px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }
    
    .btn-print-pdf .btn-icon {
        width: 18px;
        height: 18px;
    }
    
    .btn-print-pdf .btn-text {
        display: inline;
        font-size: 12px;
    }
    
    .btn-print-mobile {
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
        font-size: 15px;
    }
    
    .btn-print-mobile svg {
        width: 20px;
        height: 20px;
    }
    
    .pdf-document {
        padding: 8mm 4mm;
    }
    
    .logo-img {
        max-width: 60px;
    }
    
    .logo-placeholder {
        width: 40px;
        height: 40px;
    }
    
    .logo-text {
        font-size: 16px;
    }
    
    .company-name {
        font-size: 10px;
    }
    
    .website-bar {
        font-size: 9px;
        padding: 5px 8px;
    }
    
    .quote-info-section {
        font-size: 10px;
    }
    
    .control-table {
        font-size: 7px;
    }
    
    .control-table td {
        padding: 3px 5px;
    }
    
    .client-section {
        font-size: 10px;
    }
    
    .items-table-desktop {
        display: none;
    }
    
    .items-cards-mobile {
        display: block;
    }
    
    .items-table {
        min-width: 500px;
        font-size: 8px;
    }
    
    .items-table th,
    .items-table td {
        padding: 4px 3px;
    }
    
    .col-cant {
        width: 12%;
    }
    
    .col-item {
        width: 48%;
    }
    
    .col-unitario {
        width: 20%;
    }
    
    .col-importe {
        width: 20%;
    }
    
    .item-description {
        font-size: 8px;
    }
    
    .component-item {
        font-size: 7px;
        padding-left: 6px;
    }
    
    .component-method {
        font-size: 6px;
    }
    
    .summary-table {
        font-size: 9px;
    }
    
    .summary-table td {
        padding: 4px 6px;
    }
    
    .total-row td {
        font-size: 10px;
    }
    
    .intro-text {
        font-size: 9px;
    }
    
    .notes-section {
        font-size: 8px;
    }
    
    .pdf-footer {
        font-size: 7px;
    }
}

/* Orientación landscape en tablets */
@media (max-width: 1024px) and (orientation: landscape) {
    .pdf-document {
        padding: 10mm 8mm;
    }
    
    .items-section {
        margin: 15px -8mm;
        padding: 0 8mm;
    }
}

@media print {
    .print-button-container {
        display: none;
    }
    
    .print-button-mobile {
        display: none;
    }
    
    .pdf-preview-container {
        padding: 0;
        background: white;
    }
    
    .pdf-document {
        box-shadow: none;
        padding: 15mm 10mm;
        max-width: 210mm;
    }
    
    .items-section {
        overflow: visible;
        margin: 20px 0;
        padding: 0;
    }
    
    .items-table-desktop {
        display: block;
    }
    
    .items-cards-mobile {
        display: none;
    }
    
    .items-table {
        min-width: auto;
    }
}

/* Estilos para el selector de versiones */
.print-button-container .form-select {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    border: 1px solid #ddd;
}

/* Estilos para el recuadro de comentario */
.comment-section {
    max-width: 210mm;
    margin: 20px auto;
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 8px;
    padding: 15px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.comment-header {
    font-size: 14px;
    font-weight: bold;
    color: #856404;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ffc107;
}

.comment-content {
    font-size: 13px;
    color: #856404;
    line-height: 1.6;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    max-width: 100%;
    overflow: hidden;
}

.comment-content p {
    margin: 0;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
}

@media print {
    .comment-section {
        display: none;
    }
}

/* Estilos para notas de ensayos */
.item-note {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed #ddd;
}

.note-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 8px;
    font-weight: bold;
    text-transform: uppercase;
    margin-right: 6px;
    vertical-align: middle;
}

.note-type-imprimible {
    background-color: #d1ecf1;
    color: #0c5460;
}

.note-type-interna {
    background-color: #fff3cd;
    color: #856404;
}

.note-type-fact {
    background-color: #f8d7da;
    color: #721c24;
}

.note-content {
    font-size: 9px;
    color: #555;
    font-style: italic;
    display: inline-block;
    vertical-align: middle;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

.card-note {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #eee;
}

.card-note .note-type-badge {
    font-size: 9px;
    padding: 3px 10px;
    margin-bottom: 5px;
    display: inline-block;
}

.card-note .note-content {
    font-size: 10px;
    display: block;
    margin-top: 5px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectorVersion = document.getElementById('selectorVersionDetalle');
    const cotiNum = {{ $cotizacion->coti_num }};
    
    if (!selectorVersion) return;
    
    // Obtener versión actual desde la URL si existe
    const urlParams = new URLSearchParams(window.location.search);
    const versionParam = urlParams.get('version');
    const versionActual = {{ $cotizacion->coti_version ?? 1 }};
    
    // Cargar versiones disponibles
    fetch(`/api/cotizaciones/${cotiNum}/versiones`)
        .then(response => response.json())
        .then(versiones => {
            selectorVersion.innerHTML = '';
            let versionSeleccionada = null;
            
            versiones.forEach(version => {
                const option = document.createElement('option');
                option.value = version.version;
                option.textContent = `Versión ${version.version} - ${version.fecha_version}`;
                
                // Determinar qué versión seleccionar
                const versionNum = String(version.version);
                const esVersionSolicitada = versionParam && versionNum === String(versionParam);
                const esVersionActual = !versionParam && version.es_actual;
                
                if (esVersionSolicitada || esVersionActual) {
                    option.selected = true;
                    versionSeleccionada = versionNum;
                    if (version.es_actual && !versionParam) {
                        option.textContent += ' (Actual)';
                    }
                }
                
                selectorVersion.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error cargando versiones:', error);
            selectorVersion.innerHTML = '<option value="">Error cargando versiones</option>';
        });
    
    // Manejar cambio de versión
    selectorVersion.addEventListener('change', function() {
        const version = this.value;
        if (!version) {
            console.warn('[showDetalle] No se proporcionó versión');
            return;
        }
        
        console.log('[showDetalle] Cambiando a versión:', version);
        console.log('[showDetalle] Versión actual:', versionActual);
        
        // Recargar la página con el parámetro de versión
        const url = new URL(window.location.href);
        url.searchParams.set('version', version);
        console.log('[showDetalle] Recargando página con URL:', url.toString());
        window.location.href = url.toString();
    });
});
</script>
@endsection
