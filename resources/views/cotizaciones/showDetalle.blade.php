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

            $cotiReqCadenaRel = (bool) ($cotizacion->coti_req_cadena_custodia_relacionada ?? false);
            
            // Detectar si estamos usando la nueva lógica de agrupadores (basado en si algún componente tiene el flag)
            $esNuevaLogicaPrecio = $tareasCollection->contains(function($t) {
                return (bool)($t->de_agrupador ?? false);
            });

            // Agrupar ítems con sus componentes y métodos
            $itemsAgrupados = [];
            foreach ($ensayos as $ensayo) {
                $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);

                $cantidadMuestras = (float) ($ensayo->cotio_cantidad ?? 1);
                if ($cantidadMuestras <= 0) {
                    $cantidadMuestras = 1;
                }

                $sumaComponentesUnitaria = $componentesDelEnsayo->sum(function ($componente) {
                    if ($componente->de_agrupador) {
                        return 0;
                    }
                    $precio = (float) ($componente->cotio_precio ?? 0);
                    $cantidad = (float) ($componente->cotio_cantidad ?? 1);
                    if ($cantidad <= 0) {
                        $cantidad = 1;
                    }
                    return $precio * $cantidad;
                });

                // Columna ensayo: solo precio adicional u.m. (como en ventas/edit); importe = adicional × cant. muestras.
                $cotioPrecioRaw = $ensayo->cotio_precio ?? null;
                $precioUnitarioTotal = \App\Support\CotizacionPrecioEnsayo::precioUnitarioLineaEnsayo((float) $sumaComponentesUnitaria, $cotioPrecioRaw, $esNuevaLogicaPrecio);
                $importeEnsayoFila = $cantidadMuestras * $precioUnitarioTotal;

                $componentesConMetodos = [];
                foreach ($componentesDelEnsayo as $componente) {
                    $metodoTexto = '';

                    // Cargar solo el método de ANÁLISIS
                    if (!$componente->relationLoaded('metodoAnalisis') && $componente->cotio_codigometodo_analisis) {
                        $componente->load('metodoAnalisis');
                    }

                    // Resolver nombre del método de análisis únicamente
                    if ($componente->cotio_codigometodo_analisis) {
                        $_codigoMetodo = trim($componente->cotio_codigometodo_analisis);
                        if ($componente->metodoAnalisis) {
                            $metodoTexto = trim($componente->metodoAnalisis->nombre ?? '');
                        }
                        if (empty($metodoTexto)) {
                            $ma = \App\Models\MetodoAnalisis::where('codigo', $_codigoMetodo)->first();
                            $metodoTexto = $ma ? trim($ma->nombre ?? '') : '';
                        }
                        // Fallback: buscar en la tabla legado metodo (donde viven los códigos de CotioItems.metodo)
                        if (empty($metodoTexto)) {
                            $ml = \App\Models\Metodo::where('metodo_codigo', $componente->cotio_codigometodo_analisis)->first();
                            $metodoTexto = $ml ? trim($ml->metodo_descripcion ?? '') : $_codigoMetodo;
                        }
                    }

                    $componentesConMetodos[] = [
                        'descripcion' => $componente->cotio_descripcion ?? '',
                        'metodo' => $metodoTexto,
                        'de_agrupador' => (bool) ($componente->de_agrupador ?? false),
                        'req_cadena_custodia' => (bool) ($componente->req_cadena_custodia ?? false),
                        'req_prot_mapba' => (bool) ($componente->req_prot_mapba ?? false),
                    ];
                }

                $notasImprimibles = $ensayo->notasImprimiblesList();
                $ensDesc = trim($ensayo->cotio_descripcion ?? '');
                foreach ($componentesDelEnsayo as $componente) {
                    $compDesc = trim($componente->cotio_descripcion ?? '');
                    foreach ($componente->notasImprimiblesList() as $cn) {
                        $txtComp = trim((string) ($cn['contenido'] ?? ''));
                        if ($txtComp === '') {
                            continue;
                        }
                        $notasImprimibles[] = [
                            'tipo' => $cn['tipo'] ?? 'imprimible',
                            'contenido' => $txtComp,
                            'descripcion_contexto' => ($compDesc !== '')
                                ? ($ensDesc !== '' ? $ensDesc . ' — ' . $compDesc : $compDesc)
                                : $ensDesc,
                        ];
                    }
                }

                $itemsAgrupados[] = [
                    'item' => $ensayo->cotio_item,
                    'descripcion' => $ensayo->cotio_descripcion ?? '',
                    'cantidad' => $cantidadMuestras,
                    'precio_unitario' => $precioUnitarioTotal,
                    'importe' => $importeEnsayoFila,
                    'componentes' => $componentesConMetodos,
                    'notas' => $notasImprimibles,
                    'req_cadena_custodia' => (bool) ($ensayo->req_cadena_custodia ?? false),
                    'req_cadena_custodia_rel' => $cotiReqCadenaRel,
                    'req_prot_mapba' => (bool) ($ensayo->req_prot_mapba ?? false),
                    'lleva_muestreo' => (bool) ($ensayo->lleva_muestreo ?? true),
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

            $totalCalculado = collect($itemsAgrupados)->sum('importe') + $totalComponentesSinCategoria;

            $subtotalAdicionalEnsayos = (float) collect($itemsAgrupados)->sum(function($item) use ($componentes, $ensayos) {
                 // Para el resumen, seguimos queriendo separar el "adicional" de los componentes si es posible.
                 // Pero como el usuario quiere ver el total en la fila, tal vez el resumen deba cambiar.
                 // Re-calculamos el extra para el resumen:
                 return $item['importe']; 
            });
            
            // Ajuste del resumen económico para que sea consistente
            $totalImporteComponentesEnGrupos = collect($itemsAgrupados)->sum(function($ia) use ($componentes) {
                $componentesDelEnsayo = $componentes->where('cotio_item', $ia['item']);
                $sumaComponentesUnitaria = $componentesDelEnsayo->sum(function ($componente) {
                    if ($componente->de_agrupador) {
                        return 0;
                    }
                    $precio = (float) ($componente->cotio_precio ?? 0);
                    $cantidad = (float) ($componente->cotio_cantidad ?? 1);
                    return $precio * ($cantidad <= 0 ? 1 : $cantidad);
                });
                return $ia['cantidad'] * $sumaComponentesUnitaria;
            });

            $subtotalComponentesBajoEnsayos = $totalImporteComponentesEnGrupos;
            $subtotalAdicionalEnsayos = $totalCalculado - $subtotalComponentesBajoEnsayos - $totalComponentesSinCategoria;


            $formatCurrency = function ($value) {
                return number_format((float) $value, 2, ',', '.');
            };

            $formatDate = function ($date) {
                return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '—';
            };

            $cliente = $cotizacion->cliente ?? null;
            $cliNombreDetalle = trim((string) (optional($cliente)->cli_razonsocial ?? ''));
            if ($cliNombreDetalle === '') {
                $cliNombreDetalle = trim((string) (optional($cliente)->cli_fantasia ?? ''));
            }
            if ($cliNombreDetalle === '') {
                $cliNombreDetalle = trim((string) ($cotizacion->coti_empresa ?? ''));
            }
            $divisaCodigo = $cotizacion->divisa_codigo ?? 'PES';
            $descuentoGlobal = max((float) ($descuentoGlobalCliente ?? 0), 0);
            $descuentoSector = max((float) ($descuentoSectorCliente ?? 0), 0);
            $descuentoTotal = max((float) ($descuentoTotalCliente ?? ($descuentoGlobal + $descuentoSector)), 0);
            $descuentoGlobalMonto = $totalCalculado * ($descuentoGlobal / 100);
            $descuentoSectorMonto = $totalCalculado * ($descuentoSector / 100);

            // Aumento global aplicado a la cotización (igual lógica que en la edición)
            $aumentoGlobal = max((float) ($cotizacion->coti_aumentoglobal ?? 0), 0);
            $aumentoGlobalMonto = $totalCalculado * ($aumentoGlobal / 100);

            // Total final = Subtotal + Aumento - Descuentos
            $importeConAjustes = $totalCalculado + $aumentoGlobalMonto - ($descuentoGlobalMonto + $descuentoSectorMonto);

            // Obtener empresa relacionada
            $empresaRelacionadaDetalle = null;
            $idEmpresaRelDet = $cotizacion->coti_empresa_rel ?? $cotizacion->coti_cli_empresa;
            if ($idEmpresaRelDet) {
                $empresaRelacionadaDetalle = \App\Models\ClienteEmpresaRelacionada::find($idEmpresaRelDet);
            }

            // Obtener sucursal si está seleccionada (mismo tratamiento que empresa relacionada, pero se aclara "sucursal")
            $sucursalDetalle = null;
            if (trim((string) ($cotizacion->coti_codigosuc ?? '')) !== '') {
                $sucursalDetalle = \App\Models\Clientes::where('cli_codigo', trim($cotizacion->coti_codigosuc))->first();
            }

            // Datos para el bloque Destinatario (a quien va dirigido el presupuesto).
            // Prioridad: 1) Sucursal (coti_codigosuc), 2) Empresa relacionada (coti_cli_empresa), 3) coti_para / cliente.
            if ($sucursalDetalle) {
                $nombreBaseSucursal = trim($sucursalDetalle->cli_razonsocial ?? $sucursalDetalle->cli_fantasia ?? 'Sucursal');
                $nombreCliente = $nombreBaseSucursal . ' (sucursal)';
                $direccionCliente = trim($sucursalDetalle->cli_direccion ?? '');
                $localidadCliente = trim($sucursalDetalle->cli_localidad ?? '');
                $partidoCliente = trim($sucursalDetalle->cli_partido ?? '');
                $cuitCliente = trim($sucursalDetalle->cli_cuit ?? '');
                $contactoCliente = trim($sucursalDetalle->cli_contacto ?? $cotizacion->coti_contacto ?? '');
            } elseif ($empresaRelacionadaDetalle) {
                $relNombreDetalle = trim((string) ($empresaRelacionadaDetalle->razon_social ?? ''));
                $nombreCliente = ($cliNombreDetalle !== '' && $relNombreDetalle !== '')
                    ? $cliNombreDetalle . ' - ' . $relNombreDetalle
                    : ($relNombreDetalle !== '' ? $relNombreDetalle : $cliNombreDetalle);
                $direccionCliente = trim($empresaRelacionadaDetalle->direcciones ?? '');
                $localidadCliente = trim($empresaRelacionadaDetalle->localidad ?? '');
                $partidoCliente = trim($empresaRelacionadaDetalle->partido ?? '');
                $cuitCliente = trim($empresaRelacionadaDetalle->cuit ?? '');
                $contactoCliente = trim($empresaRelacionadaDetalle->contacto ?? $cotizacion->coti_contacto ?? '');
            } else {
                $paraTxtDet = trim((string) ($cotizacion->coti_para ?? ''));
                if ($paraTxtDet !== '') {
                    $esConsultorDet = (bool) (optional($cliente)->es_consultor ?? false);
                    $empRelMarcadaDet = (bool) ($cotizacion->coti_para_empresa_rel ?? false);
                    $tieneIdEmpRelDet = !empty($cotizacion->coti_empresa_rel) || !empty($cotizacion->coti_cli_empresa);
                    $paraDistintoDet = $cliNombreDetalle !== '' && strcasecmp($paraTxtDet, $cliNombreDetalle) !== 0;
                    $usarCompositeDet = $esConsultorDet && $cliNombreDetalle !== '' && $paraTxtDet !== ''
                        && ($empRelMarcadaDet || $tieneIdEmpRelDet || $paraDistintoDet);
                    $nombreCliente = $usarCompositeDet ? $cliNombreDetalle . ' - ' . $paraTxtDet : $paraTxtDet;
                } else {
                    $nombreCliente = trim((string) ($cotizacion->coti_empresa ?? ''));
                    if ($nombreCliente === '') {
                        $nombreCliente = $cliNombreDetalle;
                    }
                }
                $direccionCliente = $cotizacion->coti_direccioncli ?? optional($cliente)->cli_direccion ?? '';
                $localidadCliente = $cotizacion->coti_localidad ?? optional($cliente)->cli_localidad ?? '';
                $partidoCliente = $cotizacion->coti_partido ?? optional($cliente)->cli_partido ?? '';
                $cuitCliente = $cotizacion->coti_cuit ?? optional($cliente)->cli_cuit ?? '';
                $contactoCliente = $cotizacion->coti_contacto ?? '';
            }
            $mailCliente = $cotizacion->coti_mail1 ?? optional($cliente)->cli_email ?? '';
            $telefonoCliente = $cotizacion->coti_telefono ?? optional($cliente)->cli_telefono ?? '';
            $codigoCliente = $cotizacion->coti_codigocli ?? optional($cliente)->cli_codigo ?? '';

            // Datos de facturación (solapa Empresa): siempre los guardados en coti_empresa, coti_direccioncli, etc.
            $facturacionRazonSocial = trim($cotizacion->coti_empresa ?? '');
            $facturacionDireccion = trim($cotizacion->coti_direccioncli ?? '');
            $facturacionLocalidad = trim($cotizacion->coti_localidad ?? '');
            $facturacionPartido = trim($cotizacion->coti_partido ?? '');
            $facturacionCuit = trim($cotizacion->coti_cuit ?? '');
            $facturacionCodigoPostal = trim($cotizacion->coti_codigopostal ?? '');
            $tieneDatosFacturacion = $facturacionRazonSocial !== '' || $facturacionDireccion !== '' || $facturacionCuit !== '';
            $facturacionLocalidadLine = trim($facturacionLocalidad);
            if (trim($facturacionPartido) !== '') {
                $facturacionLocalidadLine = $facturacionLocalidadLine !== ''
                    ? $facturacionLocalidadLine . ' - ' . trim($facturacionPartido)
                    : trim($facturacionPartido);
            }

            // Contactos de la cotización (1 a 4), solo los que tengan al menos nombre, correo o teléfono
            $contactosCoti = [];
            $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto ?? ''), 'correo' => trim($cotizacion->coti_mail1 ?? ''), 'telefono' => trim($cotizacion->coti_telefono ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo1 ?? '')];
            $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto2 ?? ''), 'correo' => trim($cotizacion->coti_mail2 ?? ''), 'telefono' => trim($cotizacion->coti_telefono2 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo2 ?? '')];
            $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto3 ?? ''), 'correo' => trim($cotizacion->coti_mail3 ?? ''), 'telefono' => trim($cotizacion->coti_telefono3 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo3 ?? '')];
            $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto4 ?? ''), 'correo' => trim($cotizacion->coti_mail4 ?? ''), 'telefono' => trim($cotizacion->coti_telefono4 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo4 ?? '')];
            $contactosCoti = array_filter($contactosCoti, function ($c) {
                return ($c['nombre'] ?? '') !== '' || ($c['correo'] ?? '') !== '' || ($c['telefono'] ?? '') !== '';
            });
            // Ordenar por tipo (alfabético; sin tipo al final)
            usort($contactosCoti, function ($a, $b) {
                $ta = $a['tipo'] ?? '';
                $tb = $b['tipo'] ?? '';
                if ($ta === '' && $tb === '')
                    return 0;
                if ($ta === '')
                    return 1;
                if ($tb === '')
                    return -1;
                return strcasecmp($ta, $tb);
            });
            $contactosOrdenados = array_values($contactosCoti);

            // Notas imprimibles de ítems (bloque final “Notas y condiciones”)
            $todasNotasImprimiblesItems = [];
            $ordinalEnsayoNotas = 0;
            foreach ($itemsAgrupados as $ia) {
                $ordinalEnsayoNotas++;
                if (!empty($ia['notas'])) {
                    foreach ($ia['notas'] as $n) {
                        if (!is_array($n)) {
                            continue;
                        }
                        $textoNota = trim((string) ($n['contenido'] ?? ''));
                        if ($textoNota === '') {
                            continue;
                        }
                        $descEtiqueta = trim((string) ($n['descripcion_contexto'] ?? ($ia['descripcion'] ?? '')));
                        $todasNotasImprimiblesItems[] = [
                            'item' => $ia['item'],
                            'item_ordinal' => $ordinalEnsayoNotas,
                            'descripcion' => $descEtiqueta,
                            'contenido' => $textoNota,
                        ];
                    }
                }
            }

            $creadorCodigo = trim((string) ($cotizacion->coti_creador ?? ''));
            $nombreCreadorCoti = '';
            if ($creadorCodigo !== '') {
                $nombreCreadorCoti = trim((string) (\App\Models\User::where('usu_codigo', $creadorCodigo)->value('usu_descripcion') ?? ''));
            }

            //$fechaCotizacion = $cotizacion->coti_fechaalta ?? $cotizacion->coti_fechaaltatecnica ?? null;
            $fechaPresupuesto = '22/12/2023';
            $fechaEmision = $cotizacion->coti_fechaalta ?? $cotizacion->coti_fechaaltatecnica ?? null;
            $correoDestinatarioTabla = $mailCliente !== '' ? $mailCliente : ($contactosOrdenados[0]['correo'] ?? '');
            $contactoNombreTabla = $contactoCliente !== '' ? $contactoCliente : ($contactosOrdenados[0]['nombre'] ?? '');
            $telefonoDestinatarioTabla = $telefonoCliente !== '' ? $telefonoCliente : ($contactosOrdenados[0]['telefono'] ?? '');
            if ($telefonoDestinatarioTabla === '' && count($contactosOrdenados) > 1) {
                $telefonos = array_filter(array_column($contactosOrdenados, 'telefono'));
                $telefonoDestinatarioTabla = implode(' / ', $telefonos);
            }
            $localidadDestinatarioTabla = trim($localidadCliente);
            if (trim($partidoCliente) !== '') {
                $localidadDestinatarioTabla = $localidadDestinatarioTabla !== ''
                    ? $localidadDestinatarioTabla . ' - ' . trim($partidoCliente)
                    : trim($partidoCliente);
            }
        @endphp

        <!-- Botón de impresión flotante -->
        <div class="print-button-container">
            <div class="d-flex flex-column gap-2 align-items-end">
                <select id="selectorVersionDetalle" class="form-select form-select-sm"
                    style="width: auto; min-width: 200px; background: white;">
                    <option value="">Cargando versiones...</option>
                </select>
                <a href="{{ route('ventas.print', $cotizacion->coti_num) }}" class="btn-print-pdf" target="_blank"
                    rel="noopener" title="Imprimir cotización">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"
                        class="btn-icon">
                        <path
                            d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z" />
                        <path
                            d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1v-4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v4H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z" />
                    </svg>
                    <span class="btn-text">Imprimir PDF</span>
                </a>
            </div>
        </div>

        <!-- Documento PDF Preview -->
        <div class="pdf-document pdf-like">
            <div class="pdf-like-header">
                <img src="{{ asset('assets/img/header_pf.png') }}" alt="Header" class="pdf-like-header-img">
            </div>
            <div class="pdf-like-footer">
                <img src="{{ asset('assets/img/footer_pdf.png') }}" alt="Footer" class="pdf-like-footer-img">
            </div>
            <div class="pdf-like-body">
                <!-- Botón de impresión móvil (dentro del documento) -->
                <div class="print-button-mobile">
                    <a href="{{ route('ventas.print', $cotizacion->coti_num) }}" class="btn-print-mobile" target="_blank"
                        rel="noopener" title="Imprimir cotización">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                            viewBox="0 0 16 16">
                            <path
                                d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z" />
                            <path
                                d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-1v-4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v4H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z" />
                        </svg>
                        <span>Imprimir PDF</span>
                    </a>
                </div>

                {{-- Header se reemplaza por imagen (header_pf.png) --}}

                <div class="quote-meta-bar">
                    <div class="quote-meta-left">
                        <div class="quote-meta-line"><strong>Cotización:</strong> #{{ $cotizacion->coti_num }}</div>
                        <div class="quote-meta-line"><strong>Fecha:</strong> {{ $fechaEmision }}</div>
                        @php
                            $codCliPreview = trim((string) ($codigoCliente ?? ''));
                            $nomCliPreview = trim((string) ($nombreCliente ?? ''));
                        @endphp
                        @if($codCliPreview !== '' || $nomCliPreview !== '')
                            <div class="quote-meta-line compact-client-ref">
                                {{ $codCliPreview }}{{ $codCliPreview !== '' && $nomCliPreview !== '' ? ' - ' : '' }}{{ $nomCliPreview }}
                            </div>
                        @endif
                    </div>
                    <div class="quote-meta-right">
                        <table class="doc-control-table" role="presentation">
                            <tr>
                                <td rowspan="2" class="doc-ctrl-cell doc-ctrl-code">CÓDIGO
                                    {{ config('cotizacion_documento.codigo') }}</td>
                                <td class="doc-ctrl-cell doc-ctrl-mid-top">VERSIÓN: 2</td>
                                <td rowspan="2" class="doc-ctrl-cell doc-ctrl-dp">DP:
                                    {{ config('cotizacion_documento.dp') }}</td>
                            </tr>
                            <tr>
                                <td class="doc-ctrl-cell doc-ctrl-mid-bot">FECHA DE EMISIÓN {{ $fechaPresupuesto }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="quote-meta-separator"></div>

                <h2 class="section-title-pdf">Sr.(es).</h2>
                <table class="destinatario-grid" role="presentation">
                    <tr>
                        <td class="dg-label">Razón Social</td>
                        <td class="dg-value">{{ trim($nombreCliente) !== '' ? $nombreCliente : '—' }}</td>
                        <td class="dg-label">CUIT</td>
                        <td class="dg-value">{{ trim($cuitCliente) !== '' ? $cuitCliente : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="dg-label">Dirección</td>
                        <td class="dg-value">{{ trim($direccionCliente) !== '' ? $direccionCliente : '—' }}</td>
                        <td class="dg-label">Localidad</td>
                        <td class="dg-value">{{ $localidadDestinatarioTabla !== '' ? $localidadDestinatarioTabla : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="dg-label">Sucursal / Establecimiento</td>
                        <td class="dg-value">
                            {{ trim((string) ($cotizacion->coti_establecimiento ?? '')) !== '' ? trim($cotizacion->coti_establecimiento) : '—' }}
                        </td>
                        <td class="dg-label">Correo</td>
                        <td class="dg-value">{{ trim($correoDestinatarioTabla) !== '' ? $correoDestinatarioTabla : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="dg-label">Contacto</td>
                        <td class="dg-value">{{ trim($contactoNombreTabla) !== '' ? $contactoNombreTabla : '—' }}</td>
                        <td class="dg-label">Teléfono</td>
                        <td class="dg-value">
                            {{ trim($telefonoDestinatarioTabla) !== '' ? $telefonoDestinatarioTabla : '—' }}</td>
                    </tr>
                </table>

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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        viewBox="0 0 16 16" class="service-icon">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                                        <path
                                            d="m10.97 4.97-.02.022a.75.75 0 1 0 1.04 1.04l.01-.01a.75.75 0 1 0-1.05-1.05m-2.95 2.95a.75.75 0 0 0-1.08.022L7.477 9.384 6.28 8.287a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.15-.106l2-2.5a.75.75 0 0 0-.01-.94Z" />
                                    </svg>
                                    <span>Requiere Cadena de Custodia</span>
                                </div>
                            @endif
                            @if($cotizacion->coti_muestreo)
                                <div class="service-detail-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        viewBox="0 0 16 16" class="service-icon">
                                        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16" />
                                        <path
                                            d="m10.97 4.97-.02.022a.75.75 0 1 0 1.04 1.04l.01-.01a.75.75 0 1 0-1.05-1.05m-2.95 2.95a.75.75 0 0 0-1.08.022L7.477 9.384 6.28 8.287a.75.75 0 0 0-1.06 1.06l1.5 1.5a.75.75 0 0 0 1.15-.106l2-2.5a.75.75 0 0 0-.01-.94Z" />
                                    </svg>
                                    <span>Requiere Servicio de Muestreo</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Texto introductorio -->
                <div class="intro-text">
                    De nuestra consideración: Tenemos el agrado de dirigirnos a Ud/s. a fin de someter a vuestra
                    consideración el presente presupuesto:
                </div>

                <!-- Tabla de ítems -->
                <div class="items-section">
                    <h2 class="section-title-pdf">Detalle de ensayos y componentes</h2>
                    <!-- Versión desktop/tablet: tabla -->
                    <div class="items-table-desktop">
                        <table class="items-table items-table-pdflike">
                            <thead>
                                <tr>
                                    <th class="col-item-num">Item</th>
                                    <th class="col-item-desc">Descripción</th>
                                    <th class="col-item-cant th-num">Cant.</th>
                                    <th class="col-item-pu th-num">Precio Unit.</th>
                                    <th class="col-item-imp th-num">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($itemsAgrupados as $item)
                                    <tr class="item-row">
                                        <td class="col-item-num text-center">#{{ $loop->iteration }}</td>
                                        <td class="col-item-desc">
                                            <div class="item-description">
                                                {{ $item['descripcion'] }}
                                                @if(!empty($item['req_cadena_custodia']))
                                                    <span class="badge bg-info text-dark ms-2">Cadena</span>
                                                    @if(!empty($item['req_cadena_custodia_rel']))
                                                        <span class="badge bg-primary ms-1">Cadena rel.</span>
                                                    @endif
                                                @endif
                                            </div>
                                            @if(!empty($item['componentes']))
                                                @foreach($item['componentes'] as $componente)
                                                    <div class="component-item component-bullet">
                                                        <span>• {{ $componente['descripcion'] }}</span>
                                                        @if(!empty($componente['de_agrupador']))
                                                            <span style="font-size: 0.85em; color: #0d6efd; font-weight: bold; margin-left: 4px;">(incluido)</span>
                                                        @endif
                                                        @if(!empty($componente['metodo']))
                                                            <span class="component-method-bracket">[{{ $componente['metodo'] }}]</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="col-item-cant text-right">{{ number_format($item['cantidad'], 2, ',', '.') }}
                                        </td>
                                        <td class="col-item-pu text-right">$ {{ $formatCurrency($item['precio_unitario']) }}
                                        </td>
                                        <td class="col-item-imp text-right"><strong>$
                                                {{ $formatCurrency($item['importe']) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No hay ítems registrados para esta cotización.</td>
                                    </tr>
                                @endforelse

                                @if($componentesSinCategoria->isNotEmpty())
                                    <tr class="item-row">
                                        <td class="col-item-num text-center">—</td>
                                        <td class="col-item-desc">
                                            <div class="item-description">Componentes sin categoría asociada</div>
                                        </td>
                                        <td class="col-item-cant text-right">—</td>
                                        <td class="col-item-pu text-right">—</td>
                                        <td class="col-item-imp text-right"><strong>$
                                                {{ $formatCurrency($totalComponentesSinCategoria) }}</strong></td>
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
                                    <span class="card-cantidad">Cant.:
                                        {{ number_format($item['cantidad'], 2, ',', '.') }}</span>
                                    <span class="card-item-number">Item #{{ $loop->iteration }}</span>
                                </div>
                                <div class="card-description">
                                    {{ $item['descripcion'] }}
                                    @if(!empty($item['req_cadena_custodia']))
                                        <span class="badge bg-info text-dark ms-2">Cadena</span>
                                        @if(!empty($item['req_cadena_custodia_rel']))
                                            <span class="badge bg-primary ms-1">Cadena rel.</span>
                                        @endif
                                    @endif
                                </div>
                                @if(!empty($item['componentes']))
                                    <div class="card-components">
                                        @foreach($item['componentes'] as $componente)
                                            <div class="card-component-item">
                                                <div class="card-component-name">• {{ $componente['descripcion'] }}</div>
                                                @if(!empty($componente['metodo']))
                                                    <div class="card-component-method">{{ $componente['metodo'] }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
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
                <h2 class="section-title-pdf">Resumen económico</h2>
                <div class="summary-section">
                    <table class="summary-table">
                        @if($totalComponentesSinCategoria > 0)
                            <tr>
                                <td class="summary-label">Componentes sin categoría asociada:</td>
                                <td class="summary-value">$ {{ $formatCurrency($totalComponentesSinCategoria) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="summary-label">Subtotal:</td>
                            <td class="summary-value">$ {{ $formatCurrency($totalCalculado) }}</td>
                        </tr>
                        @if($aumentoGlobal > 0)
                            <tr>
                                <td class="summary-label">Aumento global ({{ number_format($aumentoGlobal, 2, ',', '.') }}%):
                                </td>
                                <td class="summary-value">$ {{ $formatCurrency($aumentoGlobalMonto) }}</td>
                            </tr>
                        @endif
                        @if($cotizacion->coti_mostrar_descuento ?? true)
                            <tr>
                                <td class="summary-label">
                                    Descuento global
                                    @if($descuentoGlobal > 0)
                                        ({{ number_format($descuentoGlobal, 2, ',', '.') }}%)
                                    @endif
                                    :
                                </td>
                                <td class="summary-value text-danger">- $ {{ $formatCurrency($descuentoGlobalMonto) }}</td>
                            </tr>
                        @endif
                        @if($descuentoSector > 0)
                            <tr>
                                <td class="summary-label">Descuento sector
                                    ({{ number_format($descuentoSector, 2, ',', '.') }}%):</td>
                                <td class="summary-value text-danger">- $ {{ $formatCurrency($descuentoSectorMonto) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="summary-label">Condición de pago:</td>
                            <td class="summary-value">{{ $condicionPagoDescripcion }}</td>
                        </tr>
                        <tr>
                            <td class="summary-label">Divisa:</td>
                            <td class="summary-value">{{ $divisaCodigo }}</td>
                        </tr>
                        <tr class="total-row">
                            <td class="summary-label"><strong>TOTAL:</strong></td>
                            <td class="summary-value"><strong>$ {{ $formatCurrency($importeConAjustes) }}</strong></td>
                        </tr>
                    </table>
                </div>

                @if($tieneDatosFacturacion)
                    <h2 class="section-title-pdf">Datos de facturación</h2>
                    <table class="destinatario-grid facturacion-grid" role="presentation">
                        <tr>
                            <td class="dg-label">Razón Social</td>
                            <td class="dg-value">{{ $facturacionRazonSocial !== '' ? $facturacionRazonSocial : '—' }}</td>
                            <td class="dg-label">CUIT</td>
                            <td class="dg-value">{{ $facturacionCuit !== '' ? $facturacionCuit : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="dg-label">Dirección</td>
                            <td class="dg-value">{{ $facturacionDireccion !== '' ? $facturacionDireccion : '—' }}</td>
                            <td class="dg-label">Localidad</td>
                            <td class="dg-value">{{ $facturacionLocalidadLine !== '' ? $facturacionLocalidadLine : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="dg-label">Código Postal</td>
                            <td class="dg-value">{{ $facturacionCodigoPostal !== '' ? $facturacionCodigoPostal : '—' }}</td>
                            <td class="dg-label"></td>
                            <td class="dg-value"></td>
                        </tr>
                    </table>
                @endif

                <h2 class="section-title-pdf">Notas y condiciones</h2>
                <div class="legal-conditions-block">
                    <ol class="legal-conditions-list">
                        <li>Los precios se expresan en
                            {{ strtoupper($divisaCodigo) === 'PES' || strtoupper($divisaCodigo) === 'ARS' ? 'Pesos' : $divisaCodigo }}
                            y no incluyen I.V.A. (Salvo aclaración específica en la cotización)</li>
                        <li>Industria y Ambiente S.A., apegado a la Ley Nacional 24.766, declara mantener la
                            confidencialidad de los resultados totales o parciales obtenidos en los análisis realizados.
                        </li>
                        <li>La Empresa contratante deberá garantizar la seguridad tanto física del personal de Industria y
                            Ambiente S.A. como de la seguridad patrimonial del equipamiento a utilizar en los trabajos
                            contratados. En el caso de sufrir pérdidas o roturas en los equipos utilizados, la empresa
                            deberá retribuir al Laboratorio el monto de los bienes dañados o extraídos. En caso de requerir,
                            el personal del Laboratorio podrá exigir la presencia de personal de vigilancia para realizar
                            las tareas.</li>
                        <li>Los timbrados, tasas, aportes y sellados que surjan de los trabajos contratados correrán por
                            cuenta de vuestra Empresa. Salvo que los mismos estén expresamente aclarados en el presente
                            presupuesto.</li>
                        <li>La empresa deberá brindar los medios para acceder a los puntos de análisis y/o mediciones en
                            forma segura. El personal de Industria y Ambiente S.A. se reserva el derecho de NO realizar las
                            tareas pactadas en caso del NO cumplimiento de las normas mínimas de Higiene y Seguridad en el
                            trabajo.</li>
                        <li>La empresa deberá cumplir con todas las características constructivas de las Instalaciones a
                            medir y/o analizar según lo especificado en la Legislación vigente. En caso de no poseerlas, se
                            evaluará puntualmente la situación y se acordará con la empresa contratante los pasos a seguir.
                        </li>
                        <li>En el caso de concurrir a planta con la previa coordinación y no poder realizar el trabajo por
                            única culpa de la empresa contratante, la misma deberá abonar el monto correspondiente a un
                            viático adicional al establecido en el presupuesto. El valor viático, corresponde a media
                            Jornada de 2 técnicos. (En caso del interior del país o en el 3er cordón de la provincia de Bs.
                            As., se cotizara oportunamente)</li>
                        <li>Para la extracción de las muestras ver procedimientos y normas de TOMA DE MUESTRA de nuestra
                            página web: <a href="https://www.industriayambiente.com.ar" target="_blank"
                                rel="noopener">www.industriayambiente.com.ar</a>.</li>
                        <li>En caso de aceptación del presente presupuesto, favor de enviar una Orden de Compra mencionando
                            el Nro. de Cotización del presente presupuesto.</li>
                    </ol>
                    @if(count($todasNotasImprimiblesItems) > 0)
                        <div class="legal-item-notes-wrap">
                            @foreach($todasNotasImprimiblesItems as $idx => $nin)
                                <p class="legal-nota-imprimible-line"><strong>Nota{{ $idx + 1 }}</strong> -
                                    {{ trim((string) ($nin['descripcion'] ?? '')) !== '' ? $nin['descripcion'] : ('Ítem #' . ($nin['item_ordinal'] ?? $nin['item'])) }}:
                                    {{ $nin['contenido'] ?? '' }}</p>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty(trim((string) ($cotizacion->coti_notas ?? ''))))
                        <div class="legal-coti-notas-wrap">
                            <p class="legal-item-notes-title"><strong>Observaciones de la cotización:</strong></p>
                            <p class="legal-coti-notas-text">{{ $cotizacion->coti_notas }}</p>
                        </div>
                    @endif
                </div>

                <div class="closing-saludo-row">
                    <span class="closing-saludo-left">Sin otro particular saluda a ud. atte.</span>
                    <span class="closing-saludo-right">{{ $nombreCreadorCoti !== '' ? $nombreCreadorCoti : '—' }}</span>
                </div>

                {{-- Footer se reemplaza por imagen (footer_pdf.png) --}}
            </div> {{-- /pdf-like-body --}}
        </div> {{-- /pdf-document pdf-like --}}
    </div> {{-- /pdf-preview-container --}}

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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-print-pdf:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .btn-print-mobile:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            font-family: 'Arial', 'Helvetica', sans-serif;
            color: #000;
            line-height: 1.4;
        }

        /* Emular PDF con header/footer fijos */
        .pdf-like {
            position: relative;
            padding: 0;
            /* el padding lo maneja el body interno */
        }

        .pdf-like-header,
        .pdf-like-footer {
            position: absolute;
            left: 0;
            right: 0;
            z-index: 2;
        }

        .pdf-like-header {
            top: 0;
        }

        .pdf-like-footer {
            bottom: 0;
            padding: 12mm 0 0 0;
            /* padding extra para que se vea como la imagen */
        }

        .pdf-like-header-img,
        .pdf-like-footer-img {
            display: block;
            width: 100%;
            height: auto;
        }

        .pdf-like-body {
            position: relative;
            z-index: 1;
            /* espacio para header/footer (+ margen extra respecto al encabezado gráfico) */
            padding: 44mm 15mm 42mm 15mm;
        }

        .quote-meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 10px;
            color: #333;
        }

        .quote-meta-left,
        .quote-meta-right {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .quote-meta-right {
            text-align: right;
            flex-shrink: 0;
        }

        .doc-control-table {
            border-collapse: collapse;
            font-size: 7px;
            text-transform: uppercase;
            color: #111;
            margin-left: auto;
        }

        .doc-control-table .doc-ctrl-cell {
            border: 1px solid #000;
            padding: 3px 6px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.25;
            font-weight: normal;
        }

        .doc-control-table .doc-ctrl-code {
            min-width: 78px;
        }

        .doc-control-table .doc-ctrl-mid-top,
        .doc-control-table .doc-ctrl-mid-bot {
            min-width: 110px;
            font-size: 6.5px;
        }

        .doc-control-table .doc-ctrl-dp {
            min-width: 62px;
        }

        .quote-meta-line strong {
            color: #0d2b5f;
        }

        .quote-meta-separator {
            height: 2px;
            background: #1a56a8;
            margin: 0 0 18px 0;
        }

        .compact-client-ref {
            color: #555;
            font-size: 9px;
        }

        .section-title-pdf {
            font-size: 11px;
            font-weight: bold;
            color: #1a56a8;
            margin: 16px 0 8px 0;
            text-transform: none;
        }

        .destinatario-grid,
        .facturacion-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 4px;
            table-layout: fixed;
        }

        .destinatario-grid td,
        .facturacion-grid td {
            border: 1px solid #d8d8d8;
            padding: 6px 8px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .destinatario-grid .dg-label,
        .facturacion-grid .dg-label {
            width: 22%;
            background: #f0f2f5;
            font-weight: bold;
            color: #444;
        }

        .destinatario-grid .dg-value,
        .facturacion-grid .dg-value {
            width: 28%;
            color: #222;
        }

        .items-table-pdflike {
            border: none;
            table-layout: fixed;
        }

        .items-table-pdflike thead th {
            background: #e8eefc;
            color: #0d2b5f;
            border: 1px solid #d0d7eb;
            font-weight: bold;
            font-size: 9px;
        }

        .items-table-pdflike td {
            border: 1px solid #ddd;
        }

        .items-table-pdflike .col-item-num {
            width: 7%;
        }

        .items-table-pdflike .col-item-desc {
            width: 46%;
        }

        .items-table-pdflike .col-item-cant {
            width: 10%;
        }

        .items-table-pdflike .col-item-pu {
            width: 18%;
        }

        .items-table-pdflike .col-item-imp {
            width: 19%;
        }

        .items-table-pdflike th.th-num {
            text-align: right;
        }

        .component-bullet {
            font-size: 9px;
            color: #444;
        }

        .component-method-bracket {
            font-size: 8px;
            color: #666;
            margin-left: 4px;
        }

        .legal-conditions-block {
            font-size: 9px;
            line-height: 1.45;
            color: #333;
            margin-bottom: 20px;
        }

        .legal-conditions-list {
            margin: 0;
            padding-left: 18px;
        }

        .legal-conditions-list li {
            margin-bottom: 8px;
        }

        .legal-item-notes-wrap,
        .legal-coti-notas-wrap {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #dee2e6;
        }

        .legal-item-notes-title {
            margin: 0 0 8px 0;
            font-size: 9px;
        }

        .legal-item-notes-list {
            margin: 0;
            padding-left: 18px;
        }

        .legal-item-notes-list li {
            margin-bottom: 10px;
        }

        .legal-note-item-ref {
            font-weight: 600;
            color: #0d2b5f;
        }

        .legal-note-body {
            margin-top: 4px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .legal-nota-imprimible-line {
            margin: 0 0 10px 0;
            font-size: 9px;
            line-height: 1.45;
            color: #333;
            word-break: break-word;
        }

        .legal-nota-imprimible-line:last-child {
            margin-bottom: 0;
        }

        .legal-coti-notas-text {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .closing-saludo-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            margin: 28px 0 8px 0;
            font-size: 10px;
            color: #222;
        }

        .closing-saludo-left {
            flex: 1;
        }

        .closing-saludo-right {
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        /* Encabezado */
        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            padding-bottom: 2rem;
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

        .client-contacts-list {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #eee;
        }

        .client-contacts-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
            color: #333;
        }

        .client-contact-item {
            font-size: 10px;
            margin-bottom: 4px;
            color: #333;
        }

        .client-contact-item .contact-tipo {
            font-weight: 600;
            color: #0d6efd;
            margin-right: 6px;
        }

        .client-contact-item .contact-nombre {
            font-weight: 500;
        }

        .client-contact-item .contact-datos {
            display: block;
            font-size: 9px;
            color: #555;
            margin-top: 1px;
            margin-left: 0;
        }

        .client-right {
            text-align: right;
        }

        .client-fiscal,
        .client-code {
            margin-bottom: 3px;
            font-size: 11px;
        }

        .client-code-ref {
            font-size: 11px;
            color: #555;
        }

        .facturacion-section {
            margin-top: 12px;
            padding: 12px 15px;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
            border-radius: 6px;
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

        .item-flags {
            margin-top: 4px;
        }

        .component-flags,
        .card-component-flags {
            display: block;
            margin-top: 2px;
        }

        .flag-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
            margin-right: 4px;
            margin-top: 2px;
            border: 1px solid transparent;
        }

        .flag-cadena {
            background-color: #e0f3ff;
            color: #0c5460;
            border-color: #9bd3f5;
        }

        .flag-mapba {
            background-color: #ffe8cc;
            color: #7f3e00;
            border-color: #ffc78a;
        }

        .flag-muestreo {
            background-color: #d1e7dd;
            color: #0f5132;
            border-color: #198754;
        }

        .flag-lab-directo {
            background-color: #e9ecef;
            color: #495057;
            border-color: #6c757d;
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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

        .card-component-bracket {
            font-size: 8px;
            color: #666;
            font-style: normal;
            font-weight: normal;
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
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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

            .items-table-pdflike .col-item-num {
                width: 12%;
            }

            .items-table-pdflike .col-item-desc {
                width: 40%;
            }

            .items-table-pdflike .col-item-cant {
                width: 12%;
            }

            .items-table-pdflike .col-item-pu {
                width: 18%;
            }

            .items-table-pdflike .col-item-imp {
                width: 18%;
            }

            .quote-meta-bar {
                flex-direction: column;
            }

            .quote-meta-right {
                text-align: left;
            }

            .quote-meta-right .doc-control-table {
                margin-left: 0;
                width: 100%;
                max-width: 320px;
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
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
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

            .items-table-pdflike .col-item-num {
                width: 12%;
            }

            .items-table-pdflike .col-item-desc {
                width: 38%;
            }

            .items-table-pdflike .col-item-cant {
                width: 12%;
            }

            .items-table-pdflike .col-item-pu {
                width: 19%;
            }

            .items-table-pdflike .col-item-imp {
                width: 19%;
            }

            .item-description {
                font-size: 8px;
            }

            .component-item {
                font-size: 7px;
                padding-left: 6px;
            }

            .component-method-bracket {
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
        document.addEventListener('DOMContentLoaded', function () {
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
            selectorVersion.addEventListener('change', function () {
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