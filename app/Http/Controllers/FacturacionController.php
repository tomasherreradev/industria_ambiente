<?php

namespace App\Http\Controllers;

use App\Models\Coti;
use App\Models\Cotio;
use App\Models\CotioInstancia;
use App\Models\CotioInstanciaAnalisis;
use App\Models\CotioInstanciaMuestra;
use App\Models\CotioInstanciaMuestraAnalisis;
use App\Models\CotioInstanciaMuestraMuestra;
use App\Models\CotioInstanciaMuestraMuestraAnalisis;
use App\Models\Matriz;
use App\Models\Factura;
use App\Models\Clientes;
use App\Models\ClienteContacto;
use App\Models\Divis;
use App\Support\CotizacionContactosFacturacion;
use App\Support\CotizacionClienteEtiqueta;
use App\Support\CotizacionPrecioEnsayo;
use App\Support\CotizacionReferenciasFacturacion;
use App\Support\CotizacionResumenEconomico;
use App\Services\Afip\AfipDirectWsfeClient;
use App\Mail\FacturaEnviadaMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Afip;
use Barryvdh\DomPDF\Facade\Pdf;


class FacturacionController extends Controller
{
    /**
     * CUIT de testing del Afip SDK: en homologación permite usar solo AFIPSDK_ACCESS_TOKEN sin certificado/clave.
     *
     * @see https://docs.afipsdk.com/
     */
    private const AFIP_CUIT_SDK_SOLO_TOKEN = '20409378472';

public function index(Request $request)
{
    \App\Support\TrabajoTecnicoCampo::sincronizarInstanciasMuestreadasPendientes();

    $query = Factura::with('cotizacion')
                ->orderBy('created_at', 'desc');

    // Filtrar por estado si se especifica
    if ($request->has('estado') && $request->estado != '') {
        $query->where('estado', $request->estado);
    }

    // Filtrar por cotización si se especifica
    if ($request->has('cotizacion') && $request->cotizacion != '') {
        $query->where('cotizacion_id', $request->cotizacion);
    }

    // Filtrar por fecha si se especifica
    if ($request->has('fecha_desde') && $request->fecha_desde != '') {
        $query->whereDate('fecha_emision', '>=', $request->fecha_desde);
    }

    if ($request->has('fecha_hasta') && $request->fecha_hasta != '') {
        $query->whereDate('fecha_emision', '<=', $request->fecha_hasta);
    }

    $facturas = $query->paginate(20);

    // Obtener estadísticas base
    $totalFacturas = Factura::count();
    $montoTotalFacturas = Factura::sum('monto_total');
    $facturasPendientes = CotioInstancia::where('facturado', false)->where('enable_inform', true)->where('cotio_subitem', 0)->count();
    $facturasFacturadas = CotioInstancia::where('facturado', true)->where('enable_inform', true)->where('cotio_subitem', 0)->count();
    
    // Calcular monto de pendientes usando el mismo método que en facturar
    $montoPendientes = $this->calcularMontoPendientes();
    
    // Determinar monto a mostrar según el filtro tipo
    $tipoFiltro = $request->get('tipo_filtro', 'total'); // 'total', 'pendientes'
    $montoMostrar = $montoTotalFacturas; // Por defecto mostrar total de facturas
    
    if ($tipoFiltro == 'pendientes') {
        $montoMostrar = $montoPendientes;
    }

    // Obtener estadísticas
    $estadisticas = [
        'total_facturas' => $totalFacturas,
        'monto_total' => $montoMostrar,
        'monto_total_facturas' => $montoTotalFacturas,
        'monto_pendientes' => $montoPendientes,
        'facturas_pendientes' => $facturasPendientes,
        'facturas_facturadas' => $facturasFacturadas,
    ];


    $baseQuery = CotioInstancia::with([
        'cotizacion.matriz',
        'cotizacion.cliente',
        'cotizacion.sucursal',
        'tareas' => function($query) {
            $query->where('enable_inform', true)
                    ->orderBy('cotio_subitem');
        },
        'cotizacion.instancias'
    ])
    ->where('enable_inform', true)
    ->where('facturado', false)
    ->where('cotio_subitem', 0);

    // Vista de lista o documento
    $pagination = $baseQuery
        ->orderBy('cotio_numcoti', $request->get('orden_cotizacion', 'desc'))
        ->orderBy('cotio_item', 'asc')
        ->orderBy('instance_number', 'asc')
        ->paginate(20);

    CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
        $pagination->getCollection()->map->cotizacion->unique(fn ($c) => $c->coti_num)->filter()->values()
    );

    // Agrupar por cotización
    $informesPorCotizacion = $pagination->groupBy('cotio_numcoti')->map(function ($group) {
        $cotizacion = $group->first()->cotizacion;
        
        return [
            'cotizacion' => $cotizacion,
            'muestras' => $group->map(function($muestra) {
                return $muestra;
            })
        ];
    });



    return view('facturacion.index', compact('facturas', 'estadisticas', 'request', 'informesPorCotizacion'));
}


public function facturar($coti_num)
{
    \App\Support\TrabajoTecnicoCampo::sincronizarInstanciasMuestreadasPendientes((int) $coti_num);

    $cotizacion = Coti::with(['cliente.contactos'])->findOrFail($coti_num);
    $contactosEnvioFactura = $this->contactosEnvioFacturaParaCotizacion($cotizacion);
    $estadoEnvioFactura = CotizacionContactosFacturacion::estadoEnvioFactura($cotizacion, $contactosEnvioFactura);

    // Cargar tareas con relaciones necesarias
    $tareas = $cotizacion->tareas()
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();

    // Obtener todas las instancias (muestras y análisis)
    $todasInstancias = CotioInstancia::where('cotio_numcoti', $coti_num)
                        ->with(['responsablesMuestreo', 'responsablesAnalisis'])
                        ->get()
                        ->groupBy(['cotio_item', 'cotio_subitem', 'instance_number']);
    
    // Filtrar solo las muestras (cotio_subitem = 0) que tienen enable_inform = true
    $muestrasParaFacturar = CotioInstancia::where('cotio_numcoti', $coti_num)
                        ->where('enable_inform', true)
                        ->where('aprobado_informe', true)
                        ->where('cotio_subitem', 0)
                        ->with(['responsablesMuestreo', 'responsablesAnalisis'])
                        ->get()
                        ->groupBy(['cotio_item', 'cotio_subitem', 'instance_number']);

    $usuarios = [];
    $agrupadas = [];

    $resumenFinanciero = $this->construirResumenFinanciero($cotizacion, $tareas);
    $muestrasTarifario = $resumenFinanciero['muestras'];
    $analisisTarifario = $resumenFinanciero['analisis'];
    $descuentoFactor = $resumenFinanciero['descuento_factor'];
    $aumentoFactor = $resumenFinanciero['aumento_factor'] ?? 0.0;
    $resumenMontos = [
        'total_bruto' => $resumenFinanciero['totales']['bruto'],
        'total_neto' => $resumenFinanciero['totales']['neto'],
        'descuento_porcentaje' => $resumenFinanciero['totales']['descuento_porcentaje'],
        'descuento_monto' => $resumenFinanciero['totales']['descuento_monto'],
        'descuento_total_porcentaje' => $resumenFinanciero['totales']['descuento_porcentaje'],
        'descuento_total_monto' => $resumenFinanciero['totales']['descuento_monto'],
        'descuento_global_porcentaje' => $resumenFinanciero['totales']['descuento_global_porcentaje'],
        'descuento_global_monto' => $resumenFinanciero['totales']['descuento_global_monto'],
        'descuento_sector_porcentaje' => $resumenFinanciero['totales']['descuento_sector_porcentaje'],
        'descuento_sector_monto' => $resumenFinanciero['totales']['descuento_sector_monto'],
        'descuento_sector_etiqueta' => $resumenFinanciero['totales']['descuento_sector_etiqueta'],
    ];

    foreach ($muestrasParaFacturar as $item => $subitems) {
        foreach ($subitems as $subitem => $instances) {
            if ($subitem == 0) {
                foreach ($instances as $instanceNumber => $instanciaCollection) {
                    $instancia = $instanciaCollection->first();
                    $tarea = $tareas->where('cotio_item', $item)->where('cotio_subitem', 0)->first();
                    
                    if ($instancia && $tarea) {
                        $precioBrutoMuestra = $muestrasTarifario[$item]['precio_unitario'] ?? 0.0;
                        
                        // IMPORTANTE: Restar el precio de los análisis ya facturados de esta muestra
                        // No filtrar por enable_inform porque los análisis facturados pueden tener enable_inform = false
                        $analisisYaFacturados = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
                            ->where('cotio_item', $item)
                            ->where('instance_number', $instanceNumber)
                            ->where('cotio_subitem', '>', 0)
                            ->where('facturado', true)
                            ->get();
                        
                        $cantLineaEnsayo = (float) ($muestrasTarifario[$item]['cantidad'] ?? 1);
                        $precioAnalisisYaFacturados = $this->sumaPreciosAnalisisFacturadosRepartida(
                            $analisisYaFacturados,
                            $analisisTarifario,
                            $tareas,
                            $cantLineaEnsayo
                        );

                        Log::info('Análisis ya facturados (importe prorrateado por cantidad línea ensayo) para ajuste de precio de muestra', [
                            'cotio_item' => $item,
                            'instance_number' => $instanceNumber,
                            'cantidad_linea_ensayo' => $cantLineaEnsayo,
                            'precio_analisis_ya_facturados' => $precioAnalisisYaFacturados,
                            'cantidad_analisis_facturados' => $analisisYaFacturados->count(),
                        ]);
                        
                        // El precio de la muestra debe ser el total menos los análisis ya facturados
                        $precioBrutoMuestraAjustado = max(0.0, $precioBrutoMuestra - $precioAnalisisYaFacturados);
                        $precioNetoMuestra = $this->aplicarAjustes($precioBrutoMuestraAjustado, $aumentoFactor, $descuentoFactor);

                        Log::info('Cálculo de precio de muestra ajustado', [
                            'muestra_id' => $instancia->id,
                            'cotio_item' => $item,
                            'instance_number' => $instanceNumber,
                            'precio_bruto_original' => $precioBrutoMuestra,
                            'precio_analisis_ya_facturados' => $precioAnalisisYaFacturados,
                            'cantidad_analisis_facturados' => $analisisYaFacturados->count(),
                            'precio_bruto_ajustado' => $precioBrutoMuestraAjustado,
                            'precio_neto_ajustado' => $precioNetoMuestra,
                            'descuento_factor' => $descuentoFactor
                        ]);

                        $instancia->precio_bruto = $precioBrutoMuestraAjustado;
                        $instancia->precio_neto = $precioNetoMuestra;

                        $analisisMuestra = $this->getAnalisisForMuestra($tareas, $item, $instanceNumber, $todasInstancias);

                        foreach ($analisisMuestra as $analisis) {
                            if (!isset($analisis->instancia) || !$analisis->instancia) {
                                continue;
                            }

                            $precioBrutoAnalisis = $analisisTarifario[$analisis->instancia->cotio_item][$analisis->instancia->cotio_subitem]['precio'] ?? 0.0;
                            $precioNetoAnalisis = $this->aplicarAjustes($precioBrutoAnalisis, $aumentoFactor, $descuentoFactor);

                            $analisis->instancia->precio_bruto = $precioBrutoAnalisis;
                            $analisis->instancia->precio_neto = $precioNetoAnalisis;
                        }

                        $agrupadas[] = [
                            'categoria' => (object) array_merge($tarea->toArray(), [
                                'instance_number' => $instancia->instance_number,
                                'original_item' => $tarea->cotio_item,
                                'display_item' => '#' . $tarea->cotio_item,
                            ]),
                            'instancia' => $instancia,
                            'tareas' => $analisisMuestra,
                            'responsables' => $instancia->responsablesMuestreo ?? collect(),
                        ];
                    }
                }
            }
        }
    }

    $refsFacturacionBloqueo = CotizacionReferenciasFacturacion::mensajeSiNoPuedeFacturar($cotizacion);
    $refsFacturacion = [
        'remito' => CotizacionReferenciasFacturacion::textoPrimerRemitoParaFactura($cotizacion),
        'oc' => trim((string) ($cotizacion->coti_oc_referencia ?? '')),
        'oc_obligatorio' => (bool) ($cotizacion->coti_oc_requerido_factura ?? false),
        'filas' => CotizacionReferenciasFacturacion::rowsFromModel($cotizacion),
        'puede_facturar' => $refsFacturacionBloqueo === null,
        'mensaje_bloqueo' => $refsFacturacionBloqueo,
    ];

    $cuotasInfo = null;
    if ($cotizacion->coti_cuotas) {
        $facturas = Factura::where('cotizacion_id', $coti_num)->get();
        $cuotasFacturadas = [];
        
        foreach ($facturas as $f) {
            $itemsData = $f->items;
            if (is_string($itemsData)) {
                $itemsData = json_decode($itemsData, true);
            }
            
            $listaItems = $itemsData['items'] ?? [];
            foreach ($listaItems as $item) {
                if (($item['tipo'] ?? '') === 'cuota') {
                    // Intentar extraer el número de cuota de la descripción o buscar un campo específico
                    // Por ahora, usaremos la descripción: "Cuota X de Y"
                    if (preg_match('/Cuota (\d+) de/', $item['descripcion'], $matches)) {
                        $cuotasFacturadas[] = (int) $matches[1];
                    }
                }
            }
        }
        
        // Determinar fecha de inicio de cuotas: aprobación o fecha de alta como fallback
        $fechaInicioCuotas = $cotizacion->coti_fechaaprobado
            ?? $cotizacion->coti_fechaalta
            ?? now();

        $descuentoData = $this->obtenerDatosDescuento($cotizacion);
        $resumenEconomico = CotizacionResumenEconomico::calcular(
            $tareas,
            (float) ($cotizacion->coti_aumentoglobal ?? 0),
            $descuentoData['porcentaje_global'],
            $descuentoData['porcentaje_sector']
        );
        $cuotaCant    = max(1, (int) $cotizacion->coti_cuota_cant);
        $cuotaInteres = (float) ($cotizacion->coti_cuota_interes ?? 0);
        $montosCuotas = CotizacionResumenEconomico::calcularCuotas(
            $resumenEconomico['total_final'],
            $cuotaCant,
            $cuotaInteres
        );

        $cuotasInfo = [
            'total'             => $cuotaCant,
            'billed_list'       => $cuotasFacturadas,
            'facturadas'        => count($cuotasFacturadas),
            'monto_base'        => $montosCuotas['monto_total'],
            'interes'           => $cuotaInteres,
            'monto_con_interes' => $montosCuotas['monto_con_interes'],
            'monto_indiv'       => $montosCuotas['monto_individual'],
            'descripcion'       => $cotizacion->coti_cuota_desc,
            'proxima'           => empty($cuotasFacturadas) ? 1 : max($cuotasFacturadas) + 1,
            'fecha_inicio'      => $fechaInicioCuotas,
        ];
    }

    return view('facturacion.show', compact(
        'cotizacion',
        'tareas',
        'usuarios',
        'agrupadas',
        'resumenMontos',
        'refsFacturacion',
        'cuotasInfo',
        'contactosEnvioFactura',
        'estadoEnvioFactura'
    ));
}

/**
 * Valida y resuelve emails de envío. Retorna redirect si falta selección obligatoria.
 *
 * @return \Illuminate\Http\RedirectResponse|array{emails: array<int, string>, contactos_cliente: \Illuminate\Support\Collection, estado: array}
 */
private function validarYResolverEmailsEnvioFactura(Request $request, Coti $cotizacion)
{
    $contactosCliente = $this->contactosEnvioFacturaParaCotizacion($cotizacion);
    $estado = CotizacionContactosFacturacion::estadoEnvioFactura($cotizacion, $contactosCliente);
    $emails = CotizacionContactosFacturacion::resolverEmailsDestino(
        $cotizacion,
        $contactosCliente,
        $request->input('emails_envio_factura')
    );

    if ($emails === [] && $estado['requiere_seleccion'] && $contactosCliente->isNotEmpty()) {
        return redirect()->back()->with('error', 'Seleccione al menos un email de envío de factura antes de facturar.');
    }

    return [
        'emails' => $emails,
        'contactos_cliente' => $contactosCliente,
        'estado' => $estado,
    ];
}

private function procesarEnvioFacturaPorCorreo(Coti $cotizacion, Factura $factura, array $emails, $contactosCliente, array $estado): void
{
    if ($emails === []) {
        return;
    }

    if ($estado['requiere_seleccion']) {
        CotizacionContactosFacturacion::persistirContactosEnvioFacturaEnCoti(
            $cotizacion,
            $contactosCliente,
            $emails
        );
    }

    $this->enviarFacturaPorCorreo($factura, $emails);
}

/**
 * @param  array<int, string>  $emails
 */
private function enviarFacturaPorCorreo(Factura $factura, array $emails): void
{
    try {
        $rutaPdf = null;
        try {
            $rutaPdf = $this->resolverRutaPdfFactura($factura);
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar PDF para envío por email', [
                'factura_id' => $factura->id,
                'error' => $e->getMessage(),
            ]);
        }

        Mail::to($emails)->send(new FacturaEnviadaMail($factura, $rutaPdf));

        Log::info('Factura enviada por email', [
            'factura_id' => $factura->id,
            'numero_factura' => $factura->numero_factura,
            'destinatarios' => $emails,
            'pdf_adjunto' => $rutaPdf !== null,
        ]);
    } catch (\Throwable $e) {
        Log::error('Error al enviar factura por email', [
            'factura_id' => $factura->id,
            'destinatarios' => $emails,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * Contactos del cliente con tipo «Envío de factura» (tabla cliente_contactos).
 *
 * @return \Illuminate\Support\Collection<int, ClienteContacto>
 */
private function contactosEnvioFacturaParaCotizacion(Coti $cotizacion): \Illuminate\Support\Collection
{
    $cliCodigo = trim((string) ($cotizacion->coti_codigocli ?? ''));
    if ($cliCodigo === '') {
        return collect();
    }

    return ClienteContacto::query()
        ->whereRaw('LTRIM(RTRIM(cli_codigo)) = ?', [$cliCodigo])
        ->envioFactura()
        ->whereNotNull('email')
        ->whereRaw("LTRIM(RTRIM(email)) <> ''")
        ->orderBy('nombre')
        ->get();
}





protected function getOrCreateInstancia($numcoti, $item, $subitem, $instance, $instanciasExistentes)
{
    if (
        isset($instanciasExistentes[$item]) &&
        isset($instanciasExistentes[$item][$subitem]) &&
        isset($instanciasExistentes[$item][$subitem][$instance])
    ) {
        return $instanciasExistentes[$item][$subitem][$instance]->first();
    }

    // Obtener datos de Cotio para copiar métodos
    $cotio = Cotio::where('cotio_numcoti', $numcoti)
        ->where('cotio_item', $item)
        ->where('cotio_subitem', $subitem)
        ->first();

    $instanciaData = [
        'cotio_numcoti' => $numcoti,
        'cotio_item' => $item,
        'cotio_subitem' => $subitem,
        'instance_number' => $instance,
        'responsable_muestreo' => null,
        'fecha_muestreo' => null,
        'enable_inform' => true,
    ];

    // Copiar ambos métodos desde Cotio si están disponibles
    if ($cotio) {
        if ($cotio->cotio_codigometodo) {
            $instanciaData['cotio_codigometodo'] = $cotio->cotio_codigometodo;
        }
        if ($cotio->cotio_codigometodo_analisis) {
            $instanciaData['cotio_codigometodo_analisis'] = $cotio->cotio_codigometodo_analisis;
        }
    }

    return new CotioInstancia($instanciaData);
}

protected function getAnalisisForMuestra($tareas, $item, $instance, $todasInstancias)
{
    $analisis = [];

    foreach ($tareas as $tarea) {
        if ($tarea->cotio_item == $item && $tarea->cotio_subitem != 0) {
            // Buscar la instancia del análisis en todas las instancias
            if (isset($todasInstancias[$item]) && 
                isset($todasInstancias[$item][$tarea->cotio_subitem]) && 
                isset($todasInstancias[$item][$tarea->cotio_subitem][$instance])) {
                
                $instanciaAnalisis = $todasInstancias[$item][$tarea->cotio_subitem][$instance]->first();

                $tareaClonada = clone $tarea;
                $tareaClonada->instancia = $instanciaAnalisis;
                $tareaClonada->original_item = $tarea->cotio_item;
                
                // Agregar los datos de resultado directamente a la tarea clonada
                $tareaClonada->resultado = $instanciaAnalisis->resultado;
                $tareaClonada->resultado_2 = $instanciaAnalisis->resultado_2;
                $tareaClonada->resultado_3 = $instanciaAnalisis->resultado_3;
                $tareaClonada->resultado_final = $instanciaAnalisis->resultado_final;
                $tareaClonada->observacion_resultado = $instanciaAnalisis->observacion_resultado;
                $tareaClonada->observacion_resultado_2 = $instanciaAnalisis->observacion_resultado_2;
                $tareaClonada->observacion_resultado_3 = $instanciaAnalisis->observacion_resultado_3;
                $tareaClonada->observacion_resultado_final = $instanciaAnalisis->observacion_resultado_final;
                $tareaClonada->observaciones_ot = $instanciaAnalisis->observaciones_ot;

                $analisis[] = $tareaClonada;
            }
        }
    }

    return $analisis;
}
    




public function generarFacturaArca(Request $request, $coti_num)
{
    Log::info('Datos recibidos:', $request->all());
    try {
        $request->validate([
            'cotizacion_id' => 'required|numeric',
            'muestras' => 'array|nullable',
            'analisis' => 'array|nullable',
            'cuotas' => 'array|nullable',
            'muestras.*' => 'numeric|exists:cotio_instancias,id',
            'analisis.*' => 'numeric|exists:cotio_instancias,id',
            'cuotas.*' => 'numeric',
            'emails_envio_factura' => 'array|nullable',
            'emails_envio_factura.*' => 'email',
        ], [
            'analisis.*.numeric' => 'El ID del análisis :index debe ser un número.',
            'analisis.*.exists' => 'El ID del análisis :index no existe en la base de datos.',
            'muestras.*.numeric' => 'El ID de la muestra :index debe ser un número.',
            'muestras.*.exists' => 'El ID de la muestra :index no existe en la base de datos.',
            'cuotas.*.numeric' => 'El número de cuota :index debe ser un número.',
        ]);

        $cotizacion = Coti::with(['cliente'])->findOrFail($coti_num);

        $emailsResueltos = $this->validarYResolverEmailsEnvioFactura($request, $cotizacion);
        if ($emailsResueltos instanceof \Illuminate\Http\RedirectResponse) {
            return $emailsResueltos;
        }

        $bloqueoRefs = CotizacionReferenciasFacturacion::mensajeSiNoPuedeFacturar($cotizacion);
        if ($bloqueoRefs) {
            return redirect()->back()->with('error', $bloqueoRefs);
        }

        $muestrasSeleccionadas = $request->input('muestras', []);
        $analisisSeleccionados = $request->input('analisis', []);
        $cuotasSeleccionadas = $request->input('cuotas', []);
        $observaciones = $request->input('observaciones');

        // Si se seleccionaron cuotas, priorizar esa lógica
        if (!empty($cuotasSeleccionadas)) {
            return $this->generarFacturaCuotas($request, $cotizacion, $cuotasSeleccionadas, $observaciones, $emailsResueltos);
        }

        // Cargar instancias de muestras (solo las que NO están facturadas)
        $muestras = CotioInstancia::whereIn('id', $muestrasSeleccionadas)
                    ->where('facturado', false) // Solo muestras no facturadas
                    ->with(['cotizacion', 'responsablesAnalisis'])
                    ->get();

        // Cargar instancias de análisis (solo los que NO están facturados)
        $analisis = CotioInstancia::whereIn('id', $analisisSeleccionados)
                    ->where('facturado', false) // Solo análisis no facturados
                    ->with(['cotizacion'])
                    ->get();

        // Verificar que los datos existan
        if ($muestras->isEmpty() && $analisis->isEmpty()) {
            return redirect()->back()->with('error', 'No se seleccionaron muestras ni análisis válidos.');
        }

        $tareasParaResumen = $cotizacion->tareas()
            ->orderBy('cotio_item')
            ->orderBy('cotio_subitem')
            ->get();
        $resumenFinanciero = $this->construirResumenFinanciero($cotizacion, $tareasParaResumen);
        $muestrasTarifario = $resumenFinanciero['muestras'];
        $analisisTarifario = $resumenFinanciero['analisis'];
        $descuentoFactor = $resumenFinanciero['descuento_factor'];
        $aumentoFactor = $resumenFinanciero['aumento_factor'] ?? 0.0;
        $totalesFinancieros = $resumenFinanciero['totales'];
        $descuentoPorcentaje = $totalesFinancieros['descuento_porcentaje'];
        $descuentoGlobalPorcentaje = $totalesFinancieros['descuento_global_porcentaje'];
        $descuentoSectorPorcentaje = $totalesFinancieros['descuento_sector_porcentaje'];
        $descuentoGlobalMontoTotal = $totalesFinancieros['descuento_global_monto'];
        $descuentoSectorMontoTotal = $totalesFinancieros['descuento_sector_monto'];
        $descuentoSectorEtiqueta = $totalesFinancieros['descuento_sector_etiqueta'];
        $aumentoPorcentaje = $totalesFinancieros['aumento_porcentaje'] ?? 0.0;

        Log::info('Aplicando descuentos durante facturación (prioridad: cotización, luego cliente)', [
            'cotizacion' => $cotizacion->coti_num,
            'cliente' => optional($cotizacion->cliente)->cli_descripcion ?? CotizacionClienteEtiqueta::datosFacturacionFiscales($cotizacion)['razon_social'],
            'descuento_total_porcentaje' => $descuentoPorcentaje,
            'descuento_global_porcentaje' => $descuentoGlobalPorcentaje,
            'descuento_sector_porcentaje' => $descuentoSectorPorcentaje,
            'sector' => $descuentoSectorEtiqueta,
            'descuento_global_cotizacion' => $cotizacion->coti_descuentoglobal ?? null,
            'descuento_global_cliente' => optional($cotizacion->cliente)->cli_descuentoglobal ?? null,
        ]);

        // Generar items para la factura
        $items = [];
        $montoTotal = 0;
        $montoTotalBruto = 0;
        $selectedMuestrasIndex = $muestras->keyBy(function ($muestra) {
            return $muestra->cotio_item . '|' . $muestra->instance_number;
        });

        // Crear un índice de análisis seleccionados por muestra para verificar si se factura muestra completa
        $analisisSeleccionadosPorMuestra = [];
        foreach ($analisis as $analisis_item) {
            $key = $analisis_item->cotio_item . '|' . $analisis_item->instance_number;
            if (!isset($analisisSeleccionadosPorMuestra[$key])) {
                $analisisSeleccionadosPorMuestra[$key] = [];
            }
            $analisisSeleccionadosPorMuestra[$key][] = $analisis_item;
        }

        // Agregar muestras como items SOLO si están explícitamente seleccionadas
        // (no si solo se seleccionaron algunos análisis de esa muestra)
        foreach ($muestras as $muestra) {
            $key = $muestra->cotio_item . '|' . $muestra->instance_number;
            
            // Si hay análisis seleccionados de esta muestra, verificar si TODOS los NO FACTURADOS están seleccionados
            if (isset($analisisSeleccionadosPorMuestra[$key])) {
                // Obtener todos los análisis NO FACTURADOS de esta muestra
                $todosAnalisisMuestraDisponibles = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
                    ->where('cotio_item', $muestra->cotio_item)
                    ->where('instance_number', $muestra->instance_number)
                    ->where('cotio_subitem', '>', 0)
                    ->where('enable_inform', true)
                    ->where('facturado', false) // Solo contar los NO facturados
                    ->count();
                
                $analisisSeleccionadosCount = count($analisisSeleccionadosPorMuestra[$key]);
                
                // Si no todos los análisis DISPONIBLES están seleccionados, NO facturar la muestra
                if ($todosAnalisisMuestraDisponibles > 0 && $analisisSeleccionadosCount < $todosAnalisisMuestraDisponibles) {
                    continue; // Saltar esta muestra, solo se facturarán los análisis individuales
                }
            }
            
            // Obtener el precio UNITARIO de la muestra (no el total de todas las muestras)
            $precioBase = $muestrasTarifario[$muestra->cotio_item]['precio_unitario'] ?? 0.0;
            
            // Verificar que no se esté usando el subtotal en lugar del precio unitario
            if (isset($muestrasTarifario[$muestra->cotio_item]['subtotal'])) {
                $subtotal = $muestrasTarifario[$muestra->cotio_item]['subtotal'];
                $cantidad = $muestrasTarifario[$muestra->cotio_item]['cantidad'] ?? 1;
                // Si el precio base parece ser el subtotal, calcular el unitario
                if ($precioBase > 0 && $cantidad > 1 && abs($precioBase - $subtotal) < 0.01) {
                    $precioBase = $subtotal / $cantidad;
                }
            }
            
            // IMPORTANTE: Si hay análisis ya facturados de esta muestra, restar sus precios del total
            // No filtrar por enable_inform porque los análisis facturados pueden tener enable_inform = false
            $analisisYaFacturados = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $muestra->cotio_item)
                ->where('instance_number', $muestra->instance_number)
                ->where('cotio_subitem', '>', 0)
                ->where('facturado', true)
                ->get();
            
            $cantLineaEnsayo = (float) ($muestrasTarifario[$muestra->cotio_item]['cantidad'] ?? 1);
            $precioAnalisisYaFacturados = $this->sumaPreciosAnalisisFacturadosRepartida(
                $analisisYaFacturados,
                $analisisTarifario,
                $tareasParaResumen,
                $cantLineaEnsayo
            );
            
            // El precio de la muestra debe ser el total menos los análisis ya facturados
            $precioBaseAjustado = max(0.0, $precioBase - $precioAnalisisYaFacturados);
            
            Log::info('Ajuste de precio de muestra por análisis ya facturados', [
                'muestra_id' => $muestra->id,
                'precio_base_original' => $precioBase,
                'precio_analisis_ya_facturados' => $precioAnalisisYaFacturados,
                'precio_base_ajustado' => $precioBaseAjustado,
                'cantidad_analisis_ya_facturados' => $analisisYaFacturados->count()
            ]);
            
            $precio = $this->aplicarAjustes($precioBaseAjustado, $aumentoFactor, $descuentoFactor);

            Log::info('Facturando muestra individual', [
                'cotio_item' => $muestra->cotio_item,
                'instance_number' => $muestra->instance_number,
                'precio_base_unitario' => $precioBase,
                'precio_con_descuento' => $precio,
                'descuento_factor' => $descuentoFactor,
                'tarifario_info' => $muestrasTarifario[$muestra->cotio_item] ?? null,
            ]);

            $items[] = [
                'tipo' => 'muestra',
                'descripcion' => "Muestra - {$muestra->cotio_descripcion}",
                'identificacion' => $muestra->cotio_identificacion ?? 'N/A',
                'cantidad' => 1,
                'precio_unitario' => $precio,
                'subtotal' => $precio,
                'instancia_id' => $muestra->id,
                'precio_unitario_bruto' => $precioBaseAjustado,
                'subtotal_bruto' => $precioBaseAjustado,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_global_porcentaje' => $descuentoGlobalPorcentaje,
                'descuento_sector_porcentaje' => $descuentoSectorPorcentaje,
                'descuento_monto_item' => round($precioBaseAjustado - $precio, 2),
                'precio_original' => $precioBase,
                'precio_analisis_ya_facturados' => $precioAnalisisYaFacturados,
            ];
            $montoTotalBruto += $precioBaseAjustado;
            $montoTotal += $precio;
        }

        // Agregar análisis como items (solo los que NO están incluidos en una muestra facturada completa)
        foreach ($analisis as $analisis_item) {
            $key = $analisis_item->cotio_item . '|' . $analisis_item->instance_number;
            
            // Si la muestra está en el índice de muestras seleccionadas, NO facturar los análisis individuales
            // porque la muestra completa ya incluye todos sus análisis
            if ($selectedMuestrasIndex->has($key)) {
                // Si la muestra está seleccionada, significa que se facturó completa
                // Por lo tanto, NO facturar los análisis individuales
                Log::info('Saltando análisis individual - muestra completa ya facturada', [
                    'analisis_id' => $analisis_item->id,
                    'muestra_key' => $key,
                ]);
                continue;
            }

            $precioBase = $analisisTarifario[$analisis_item->cotio_item][$analisis_item->cotio_subitem]['precio'] ?? 0.0;
            $precio = $this->aplicarAjustes($precioBase, $aumentoFactor, $descuentoFactor);

            Log::info('Facturando análisis individual', [
                'analisis_id' => $analisis_item->id,
                'precio_base' => $precioBase,
                'precio_con_descuento' => $precio,
            ]);

            $items[] = [
                'tipo' => 'analisis',
                'descripcion' => "Análisis - {$analisis_item->cotio_descripcion}",
                'resultado' => $analisis_item->resultado_final ?? 'N/A',
                'cantidad' => 1,
                'precio_unitario' => $precio,
                'subtotal' => $precio,
                'instancia_id' => $analisis_item->id,
                'precio_unitario_bruto' => $precioBase,
                'subtotal_bruto' => $precioBase,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_global_porcentaje' => $descuentoGlobalPorcentaje,
                'descuento_sector_porcentaje' => $descuentoSectorPorcentaje,
                'descuento_monto_item' => round($precioBase - $precio, 2),
            ];
            $montoTotalBruto += $precioBase;
            $montoTotal += $precio;
        }

        // Calcular descuentos basados en el total de lo SELECCIONADO, no de toda la cotización
        $descuentoMontoTotalSeleccionado = round($montoTotalBruto * ($descuentoPorcentaje / 100), 2);
        $descuentoGlobalMontoSeleccionado = round($montoTotalBruto * ($descuentoGlobalPorcentaje / 100), 2);
        $descuentoSectorMontoSeleccionado = round($montoTotalBruto * ($descuentoSectorPorcentaje / 100), 2);
        $aumentoMontoTotalSeleccionado = round($montoTotalBruto * ($aumentoPorcentaje / 100), 2);
        
        $resumenDescuento = [
            'total_bruto' => round($montoTotalBruto, 2),
            'total_neto' => round($montoTotal, 2),
            'descuento_porcentaje' => $descuentoPorcentaje,
            'descuento_monto' => $descuentoMontoTotalSeleccionado,
            'descuento_total_porcentaje' => $descuentoPorcentaje,
            'descuento_total_monto' => $descuentoMontoTotalSeleccionado,
            'descuento_global_porcentaje' => $descuentoGlobalPorcentaje,
            'descuento_sector_porcentaje' => $descuentoSectorPorcentaje,
            'descuento_global_monto' => $descuentoGlobalMontoSeleccionado,
            'descuento_sector_monto' => $descuentoSectorMontoSeleccionado,
            'descuento_sector_etiqueta' => $descuentoSectorEtiqueta,
            'aumento_porcentaje' => $aumentoPorcentaje,
            'aumento_monto' => $aumentoMontoTotalSeleccionado,
        ];

        // Titular fiscal: ficha del cliente (cli_*), no sucursal ni empresa relacionada
        $fiscal = CotizacionClienteEtiqueta::datosFacturacionFiscales($cotizacion);
        $clienteData = [
            'razon_social' => $fiscal['razon_social'] !== '' ? $fiscal['razon_social'] : env('EMPRESA_RAZON_SOCIAL', 'Cliente Prueba'),
            'cuit' => $fiscal['cuit'] !== '' ? $fiscal['cuit'] : config('afip.cuit', '20111111112'),
            'domicilio' => $fiscal['domicilio'] !== '' ? $fiscal['domicilio'] : env('EMPRESA_DOMICILIO', 'Domicilio Prueba'),
            'localidad' => $fiscal['localidad'] !== '' ? $fiscal['localidad'] : 'CABA',
            'provincia' => $fiscal['provincia'] !== '' ? $fiscal['provincia'] : 'Buenos Aires',
            'email' => $fiscal['email'] !== '' ? $fiscal['email'] : 'pruebas@afip.com',
        ];

        // Generar factura con ARCA/AFIP
        Log::info('Generando factura con precios reales en entorno de prueba', [
            'cotizacion_id' => $coti_num,
            'monto_total' => $montoTotal,
            'cantidad_items' => count($items),
            'total_bruto' => $resumenDescuento['total_bruto'],
            'descuento_aplicado' => $resumenDescuento['descuento_monto']
        ]);

        $resultadoFactura = $this->integrarConArca($clienteData, $items, $montoTotal, $cotizacion);

        if (! ($resultadoFactura['success'] ?? false)) {
            $err = (string) ($resultadoFactura['error'] ?? 'No se pudo generar la factura en AFIP.');
            if (! empty($resultadoFactura['detalle'])) {
                $err .= ' ' . (string) $resultadoFactura['detalle'];
            }

            return redirect()->back()->with('error', $err);
        }

        try {
            // Obtener la primera muestra para la descripción e instancia
            $muestraPrincipal = $muestras->first();
            
            $factura = $this->guardarFacturacion([
                'cotizacion_id' => $coti_num,
                'cotio_descripcion' => $muestraPrincipal ? $muestraPrincipal->cotio_descripcion : 'Muestra no especificada',
                'instance_number' => $muestraPrincipal ? $muestraPrincipal->instance_number : 0,
                'numero_factura' => $resultadoFactura['numero_factura'],
                'cae' => $resultadoFactura['cae'],
                'fecha_vencimiento_cae' => $resultadoFactura['fecha_vencimiento'],
                'monto_total' => $montoTotal,
                'items' => [
                    'items' => $items,
                    'resumen' => $resumenDescuento,
                ],
                'estado' => 'aprobada',
                'muestras_ids' => $muestrasSeleccionadas,
                'analisis_ids' => $analisisSeleccionados,
                'observaciones' => $observaciones
            ]);

            $this->procesarEnvioFacturaPorCorreo(
                $cotizacion,
                $factura,
                $emailsResueltos['emails'],
                $emailsResueltos['contactos_cliente'],
                $emailsResueltos['estado']
            );
    
            $mensajeExito = 'Factura generada exitosamente: ' . $resultadoFactura['numero_factura'];
            if ($emailsResueltos['emails'] !== []) {
                $mensajeExito .= '. Enviada a: ' . implode(', ', $emailsResueltos['emails']);
            }

            return redirect()->back()->with('success', $mensajeExito);
        } catch (\Exception $e) {
            Log::error('Error al guardar factura después de generarla en AFIP: ' . $e->getMessage());
            return redirect()->back()->with('success', 'Factura generada en AFIP: ' . $resultadoFactura['numero_factura'] . ' (Error al guardar en BD: ' . $e->getMessage() . ')');
        }
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Errores de validación: ' . json_encode($e->errors()));
        return redirect()->back()->with('error', 'Errores en los datos enviados: ' . implode(', ', $e->errors()['analisis.0'] ?? $e->errors()));
    } catch (\Exception $e) {
        Log::error('Error al generar factura ARCA: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error interno al generar la factura: ' . $e->getMessage());
    }
}

protected function generarFacturaCuotas(Request $request, $cotizacion, $cuotasSeleccionadas, $observaciones = null, ?array $emailsResueltos = null)
{
    // Verificar si alguna de las cuotas ya fue facturada (evitar duplicados)
    $facturas = Factura::where('cotizacion_id', $cotizacion->coti_num)->get();
    $cuotasFacturadas = [];
    foreach ($facturas as $f) {
        $itemsData = $f->items;
        if (is_string($itemsData)) {
            $itemsData = json_decode($itemsData, true);
        }
        $listaItems = $itemsData['items'] ?? [];
        foreach ($listaItems as $item) {
            if (($item['tipo'] ?? '') === 'cuota') {
                if (preg_match('/Cuota (\d+) de/', $item['descripcion'], $matches)) {
                    $cuotasFacturadas[] = (int) $matches[1];
                }
            }
        }
    }

    foreach ($cuotasSeleccionadas as $numCuota) {
        if (in_array((int) $numCuota, $cuotasFacturadas)) {
            return redirect()->back()->with('error', "La cuota {$numCuota} ya ha sido facturada.");
        }
    }

    $items = [];
    $montoTotal = 0;
    $montoTotalBruto = 0;

    $tareasCuotas = $cotizacion->tareas()
        ->orderBy('cotio_item')
        ->orderBy('cotio_subitem')
        ->get();
    $descuentoDataCuotas = $this->obtenerDatosDescuento($cotizacion);
    $resumenCuotas = CotizacionResumenEconomico::calcular(
        $tareasCuotas,
        (float) ($cotizacion->coti_aumentoglobal ?? 0),
        $descuentoDataCuotas['porcentaje_global'],
        $descuentoDataCuotas['porcentaje_sector']
    );
    $totalCuotas      = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
    $montosCuotaFactura = CotizacionResumenEconomico::calcularCuotas(
        $resumenCuotas['total_final'],
        $totalCuotas,
        (float) ($cotizacion->coti_cuota_interes ?? 0)
    );
    $montoCuota       = $montosCuotaFactura['monto_individual'];
    $descripcionCuota = $cotizacion->coti_cuota_desc ?? 'Cuota';

    $mesesEs = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    $fechaBase = \Carbon\Carbon::parse(
        $cotizacion->coti_fechaaprobado ?? $cotizacion->coti_fechaalta ?? now()
    );

    // Validar que ninguna cuota seleccionada sea de un período futuro
    $hoyInicio = Carbon::now()->startOfMonth();
    foreach ($cuotasSeleccionadas as $numCuota) {
        $fechaCuota = $fechaBase->copy()->addMonths((int) $numCuota - 1)->startOfMonth();
        if ($fechaCuota->gt($hoyInicio)) {
            $nombreMes = $mesesEs[(int) $fechaCuota->format('n')];
            $anio      = $fechaCuota->format('Y');
            return redirect()->back()->with(
                'error',
                "La cuota {$numCuota} ({$nombreMes} {$anio}) corresponde a un período futuro y no puede facturarse todavía."
            );
        }
    }

    foreach ($cuotasSeleccionadas as $numCuota) {
        $fechaCuota = $fechaBase->copy()->addMonths((int) $numCuota - 1);
        $nombreMes = $mesesEs[(int) $fechaCuota->format('n')];
        $anio = $fechaCuota->format('Y');
        $items[] = [
            'tipo' => 'cuota',
            'descripcion' => "Cuota {$numCuota} de {$totalCuotas} - {$nombreMes} {$anio}" . ($descripcionCuota ? " ({$descripcionCuota})" : ''),
            'cantidad' => 1,
            'precio_unitario' => $montoCuota,
            'subtotal' => $montoCuota,
            'precio_unitario_bruto' => $montoCuota,
            'subtotal_bruto' => $montoCuota,
            'descuento_porcentaje' => 0,
            'descuento_monto_item' => 0,
        ];
        $montoTotal += $montoCuota;
        $montoTotalBruto += $montoCuota;
    }

    $resumenDescuento = [
        'total_bruto' => $montoTotalBruto,
        'total_neto' => $montoTotal,
        'descuento_porcentaje' => 0,
        'descuento_monto' => 0,
        'aumento_porcentaje' => 0,
        'aumento_monto' => 0,
    ];

    $fiscal = CotizacionClienteEtiqueta::datosFacturacionFiscales($cotizacion);
    $clienteData = [
        'razon_social' => $fiscal['razon_social'] !== '' ? $fiscal['razon_social'] : env('EMPRESA_RAZON_SOCIAL', 'Cliente Prueba'),
        'cuit' => $fiscal['cuit'] !== '' ? $fiscal['cuit'] : config('afip.cuit', '20111111112'),
        'domicilio' => $fiscal['domicilio'] !== '' ? $fiscal['domicilio'] : env('EMPRESA_DOMICILIO', 'Domicilio Prueba'),
        'localidad' => $fiscal['localidad'] !== '' ? $fiscal['localidad'] : 'CABA',
        'provincia' => $fiscal['provincia'] !== '' ? $fiscal['provincia'] : 'Buenos Aires',
        'email' => $fiscal['email'] !== '' ? $fiscal['email'] : 'pruebas@afip.com',
    ];

    $resultadoFactura = $this->integrarConArca($clienteData, $items, $montoTotal, $cotizacion);

    if (! ($resultadoFactura['success'] ?? false)) {
        $err = (string) ($resultadoFactura['error'] ?? 'No se pudo generar la factura en AFIP.');
        if (! empty($resultadoFactura['detalle'])) {
            $err .= ' ' . (string) $resultadoFactura['detalle'];
        }
        return redirect()->back()->with('error', $err);
    }

    try {
        $factura = $this->guardarFacturacion([
            'cotizacion_id' => $cotizacion->coti_num,
            'cotio_descripcion' => "Facturación de cuotas: " . implode(', ', $cuotasSeleccionadas),
            'instance_number' => 0,
            'numero_factura' => $resultadoFactura['numero_factura'],
            'cae' => $resultadoFactura['cae'],
            'fecha_vencimiento_cae' => $resultadoFactura['fecha_vencimiento'],
            'monto_total' => $montoTotal,
            'items' => [
                'items' => $items,
                'resumen' => $resumenDescuento,
            ],
            'estado' => 'aprobada',
            'cuotas_nums' => $cuotasSeleccionadas,
            'observaciones' => $observaciones
        ]);

        if ($emailsResueltos !== null) {
            $this->procesarEnvioFacturaPorCorreo(
                $cotizacion,
                $factura,
                $emailsResueltos['emails'],
                $emailsResueltos['contactos_cliente'],
                $emailsResueltos['estado']
            );
        }

        $mensajeExito = 'Factura de cuotas generada exitosamente: ' . $resultadoFactura['numero_factura'];
        if ($emailsResueltos !== null && ($emailsResueltos['emails'] ?? []) !== []) {
            $mensajeExito .= '. Enviada a: ' . implode(', ', $emailsResueltos['emails']);
        }

        return redirect()->back()->with('success', $mensajeExito);
    } catch (\Exception $e) {
        Log::error('Error al guardar factura de cuotas: ' . $e->getMessage());
        return redirect()->back()->with('success', 'Factura de cuotas generada en AFIP: ' . $resultadoFactura['numero_factura'] . ' (Error al guardar en BD: ' . $e->getMessage() . ')');
    }
}

protected function guardarFacturacion(array $data)
{
    try {
        Log::info('Intentando guardar factura con datos:', $data);

        // Obtener la cotización para extraer datos del cliente (fiscal = ficha cli, no sucursal/emp. rel.)
        $cotizacion = Coti::with('cliente')->find($data['cotizacion_id']);
        $fiscalGuardar = $cotizacion
            ? CotizacionClienteEtiqueta::datosFacturacionFiscales($cotizacion)
            : ['razon_social' => '', 'cuit' => '', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'email' => ''];
        
        // Procesar fecha de vencimiento CAE
        $fechaVencimiento = null;
        if (isset($data['fecha_vencimiento_cae'])) {
            $fechaStr = $data['fecha_vencimiento_cae'];
            Log::info('Procesando fecha CAE:', ['fecha_raw' => $fechaStr, 'length' => strlen($fechaStr)]);
            
            try {
                if (strlen($fechaStr) === 8 && is_numeric($fechaStr)) {
                    $fechaVencimiento = Carbon::createFromFormat('Ymd', $fechaStr)->format('Y-m-d');
                } else {
                    $fechaVencimiento = Carbon::parse($fechaStr)->format('Y-m-d');
                }
                Log::info('Fecha CAE procesada exitosamente:', ['fecha_procesada' => $fechaVencimiento]);
            } catch (\Exception $dateException) {
                Log::error('Error al procesar fecha CAE:', [
                    'fecha_original' => $fechaStr,
                    'error' => $dateException->getMessage()
                ]);
                $fechaVencimiento = Carbon::now()->addDays(10)->format('Y-m-d');
            }
        }

        // Procesar items
        $items = $data['items'];
        if (is_array($items)) {
            $items = json_encode($items, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($items)) {
            json_decode($items);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Items no es JSON válido, convirtiendo a JSON:', ['items_original' => $items]);
                $items = json_encode(['descripcion' => $items], JSON_UNESCAPED_UNICODE);
            }
        }

        // Preparar datos para crear la factura (incluyendo los nuevos campos)
        $facturaData = [
            'cotizacion_id' => (int) $data['cotizacion_id'],
            'cotio_descripcion' => $data['cotio_descripcion'] ?? 'Muestra no especificada',
            'instance_number' => $data['instance_number'] ?? 0,
            'cliente_razon_social' => ($fiscalGuardar['razon_social'] ?? '') !== '' ? $fiscalGuardar['razon_social'] : 'Cliente no especificado',
            'cliente_cuit' => ($fiscalGuardar['cuit'] ?? '') !== '' ? $fiscalGuardar['cuit'] : '00-00000000-0',
            'numero_factura' => (string) $data['numero_factura'],
            'cae' => (string) $data['cae'],
            'fecha_emision' => now(),
            'fecha_vencimiento_cae' => $fechaVencimiento,
            'monto_total' => (float) $data['monto_total'],
            'items' => $items,
            'estado' => (string) $data['estado'],
            'observaciones' => $data['observaciones'] ?? null
        ];

        Log::info('Datos preparados para crear factura:', $facturaData);

        // Crear la factura en la base de datos
        $factura = Factura::create($facturaData);

        // Marcar SOLO los análisis seleccionados como facturados
        $analisisIds = $data['analisis_ids'] ?? [];
        if (!empty($analisisIds)) {
            CotioInstancia::whereIn('id', $analisisIds)
                ->update(['facturado' => true]);

            Log::info('Análisis marcados como facturados:', [
                'factura_id' => $factura->id,
                'analisis_ids' => $analisisIds
            ]);
        }

        // Marcar muestras como facturadas SOLO si están explícitamente seleccionadas
        // (no si solo se seleccionaron algunos análisis)
        $muestrasIds = $data['muestras_ids'] ?? [];
        if (!empty($muestrasIds)) {
            // Obtener las muestras para conocer sus datos
            $muestrasFacturadas = CotioInstancia::whereIn('id', $muestrasIds)
                ->where('cotio_subitem', 0) // Solo muestras, no análisis
                ->get();

            // Marcar las muestras como facturadas
            CotioInstancia::whereIn('id', $muestrasIds)
                ->where('cotio_subitem', 0) // Solo muestras, no análisis
                ->update(['facturado' => true]);

            Log::info('Muestras marcadas como facturadas:', [
                'factura_id' => $factura->id,
                'muestras_ids' => $muestrasIds
            ]);

            // IMPORTANTE: Cuando se factura una muestra completa, también marcar TODOS sus análisis como facturados
            foreach ($muestrasFacturadas as $muestra) {
                $analisisDeMuestra = CotioInstancia::where('cotio_numcoti', $muestra->cotio_numcoti)
                    ->where('cotio_item', $muestra->cotio_item)
                    ->where('instance_number', $muestra->instance_number)
                    ->where('cotio_subitem', '>', 0) // Solo análisis, no la muestra
                    ->where('enable_inform', true)
                    ->update(['facturado' => true]);

                Log::info('Análisis de muestra marcados como facturados (muestra completa facturada):', [
                    'muestra_id' => $muestra->id,
                    'cotio_item' => $muestra->cotio_item,
                    'instance_number' => $muestra->instance_number,
                    'analisis_marcados' => $analisisDeMuestra
                ]);
            }
        }

        // Verificar muestras que tienen análisis facturados y marcar muestra como facturada
        // si TODOS sus análisis están facturados
        if (!empty($analisisIds)) {
            $analisisFacturados = CotioInstancia::whereIn('id', $analisisIds)->get();
            
            // Agrupar por muestra (cotio_item + instance_number)
            $muestrasConAnalisis = [];
            foreach ($analisisFacturados as $analisis) {
                $key = $analisis->cotio_item . '|' . $analisis->instance_number;
                if (!isset($muestrasConAnalisis[$key])) {
                    $muestrasConAnalisis[$key] = [
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number,
                        'cotio_numcoti' => $analisis->cotio_numcoti
                    ];
                }
            }

            // Para cada muestra, verificar si todos sus análisis están facturados
            foreach ($muestrasConAnalisis as $muestraInfo) {
                $totalAnalisis = CotioInstancia::where('cotio_numcoti', $muestraInfo['cotio_numcoti'])
                    ->where('cotio_item', $muestraInfo['cotio_item'])
                    ->where('instance_number', $muestraInfo['instance_number'])
                    ->where('cotio_subitem', '>', 0)
                    ->where('enable_inform', true)
                    ->count();

                $analisisFacturadosCount = CotioInstancia::where('cotio_numcoti', $muestraInfo['cotio_numcoti'])
                    ->where('cotio_item', $muestraInfo['cotio_item'])
                    ->where('instance_number', $muestraInfo['instance_number'])
                    ->where('cotio_subitem', '>', 0)
                    ->where('enable_inform', true)
                    ->where('facturado', true)
                    ->count();

                // Si todos los análisis están facturados, marcar la muestra también
                if ($totalAnalisis > 0 && $analisisFacturadosCount >= $totalAnalisis) {
                    CotioInstancia::where('cotio_numcoti', $muestraInfo['cotio_numcoti'])
                        ->where('cotio_item', $muestraInfo['cotio_item'])
                        ->where('instance_number', $muestraInfo['instance_number'])
                        ->where('cotio_subitem', 0)
                        ->update(['facturado' => true]);

                    Log::info('Muestra marcada como facturada (todos sus análisis están facturados):', [
                        'cotio_item' => $muestraInfo['cotio_item'],
                        'instance_number' => $muestraInfo['instance_number']
                    ]);
                }
            }
        }

        if (empty($analisisIds) && empty($muestrasIds)) {
            Log::warning('No se encontraron IDs de muestras o análisis para marcar como facturados', [
                'factura_id' => $factura->id
            ]);
        }

        Log::info('Factura guardada exitosamente en BD:', [
            'id' => $factura->id,
            'numero_factura' => $factura->numero_factura,
            'cae' => $factura->cae,
            'monto_total' => $factura->monto_total,
            'fecha_vencimiento_cae' => $fechaVencimiento,
            'cliente_razon_social' => $factura->cliente_razon_social,
            'cliente_cuit' => $factura->cliente_cuit
        ]);

        return $factura;

    } catch (\Exception $e) {
        Log::error('Error al guardar factura en BD: ' . $e->getMessage(), [
            'data' => $data,
            'error' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        throw $e;
    }
}

private function obtenerDatosDescuento(?Coti $cotizacion): array
{
    $cliente = $cotizacion?->cliente;
    $sectorCodigoOriginal = $cotizacion?->coti_sector;
    $sectorCodigo = $this->normalizarCodigoSector($sectorCodigoOriginal);

    // Solo usar descuentos de la cotización (global)
    $descuentoGlobal = 0.0;
    if ($cotizacion && $cotizacion->coti_descuentoglobal !== null) {
        $descuentoGlobal = (float) $cotizacion->coti_descuentoglobal;
    }

    // Descuento sector: cotización; si no hay, el del cliente (igual que showDetalle)
    $descuentoSector = 0.0;
    if ($cotizacion && $sectorCodigo) {
        $descuentoSector = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
    }
    if ($descuentoSector == 0.0 && $cliente && $sectorCodigo) {
        $descuentoSector = $this->obtenerDescuentoSector($cliente, $sectorCodigo);
    }

    $descuentoGlobal = max(0.0, min($descuentoGlobal, 100.0));
    $descuentoSector = max(0.0, min($descuentoSector, 100.0));

    $descuentoTotal = max(0.0, min($descuentoGlobal + $descuentoSector, 100.0));

    return [
        'porcentaje_total' => $descuentoTotal,
        'porcentaje_global' => $descuentoGlobal,
        'porcentaje_sector' => $descuentoSector,
        'factor_total' => $descuentoTotal / 100,
        'factor_global' => $descuentoGlobal / 100,
        'factor_sector' => $descuentoSector / 100,
        'sector_codigo' => $sectorCodigo,
        'sector_etiqueta' => $this->obtenerEtiquetaSector($sectorCodigo),
    ];
}

private function aplicarDescuento(float $monto, float $factor): float
{
    $monto = (float) $monto;

    if ($monto <= 0 || $factor <= 0) {
        return round($monto, 2);
    }

    $resultado = round($monto * (1 - $factor), 2);

    return $resultado < 0 ? 0.0 : $resultado;
}

/**
 * Aplica en un solo paso el aumento global y los descuentos totales.
 * Fórmula: monto * (1 + aumentoFactor - descuentoFactor)
 */
private function aplicarAjustes(float $monto, float $aumentoFactor, float $descuentoFactor): float
{
    $monto = (float) $monto;

    if ($monto <= 0) {
        return round($monto, 2);
    }

    $aumentoFactor = (float) $aumentoFactor;
    $descuentoFactor = (float) $descuentoFactor;

    $ajusteFactor = $aumentoFactor - $descuentoFactor;

    $resultado = round($monto * (1 + $ajusteFactor), 2);

    return $resultado < 0 ? 0.0 : $resultado;
}

private function normalizarCodigoSector(?string $sector): ?string
{
    if (is_null($sector)) {
        return null;
    }

    $valor = strtoupper(trim($sector));
    if ($valor === '') {
        return null;
    }

    $map = [
        'LABORATORIO' => 'LAB',
        'HIGIENE Y SEGURIDAD' => 'HYS',
        'MICROBIOLOGIA' => 'MIC',
        'CROMATOGRAFIA' => 'CRO',
        'LAB' => 'LAB',
        'HYS' => 'HYS',
        'MIC' => 'MIC',
        'CRO' => 'CRO',
    ];

    if (isset($map[$valor])) {
        return $map[$valor];
    }

    $abreviado = substr($valor, 0, 3);
    return $map[$abreviado] ?? null;
}

private function obtenerDescuentosSectorCliente(?Clientes $cliente): array
{
    if (!$cliente) {
        return [
            'LAB' => 0.0,
            'HYS' => 0.0,
            'MIC' => 0.0,
            'CRO' => 0.0,
        ];
    }

    return [
        'LAB' => (float) ($cliente->cli_sector_laboratorio_pct ?? 0.0),
        'HYS' => (float) ($cliente->cli_sector_higiene_pct ?? 0.0),
        'MIC' => (float) ($cliente->cli_sector_microbiologia_pct ?? 0.0),
        'CRO' => (float) ($cliente->cli_sector_cromatografia_pct ?? 0.0),
    ];
}

private function obtenerDescuentoSector(?Clientes $cliente, ?string $sectorCodigo): float
{
    if (!$cliente || !$sectorCodigo) {
        return 0.0;
    }

    $descuentos = $this->obtenerDescuentosSectorCliente($cliente);
    return (float) ($descuentos[$sectorCodigo] ?? 0.0);
}

private function obtenerDescuentosSectorCotizacion(?Coti $cotizacion): array
{
    if (!$cotizacion) {
        return [
            'LAB' => 0.0,
            'HYS' => 0.0,
            'MIC' => 0.0,
            'CRO' => 0.0,
        ];
    }

    return [
        'LAB' => (float) ($cotizacion->coti_sector_laboratorio_pct ?? 0.0),
        'HYS' => (float) ($cotizacion->coti_sector_higiene_pct ?? 0.0),
        'MIC' => (float) ($cotizacion->coti_sector_microbiologia_pct ?? 0.0),
        'CRO' => (float) ($cotizacion->coti_sector_cromatografia_pct ?? 0.0),
    ];
}

private function obtenerDescuentoSectorCotizacion(?Coti $cotizacion, ?string $sectorCodigo): float
{
    if (!$cotizacion || !$sectorCodigo) {
        return 0.0;
    }

    $descuentos = $this->obtenerDescuentosSectorCotizacion($cotizacion);
    return (float) ($descuentos[$sectorCodigo] ?? 0.0);
}

private function obtenerEtiquetaSector(?string $sectorCodigo): ?string
{
    if (!$sectorCodigo) {
        return null;
    }

    $registro = Divis::whereRaw('TRIM(divis_codigo) = ?', [$sectorCodigo])->first();

    if ($registro) {
        return trim($registro->divis_descripcion ?? '') ?: trim($registro->divis_codigo ?? '');
    }

    return $sectorCodigo;
}

private function normalizarItemsFactura($items): array
{
    if (is_string($items)) {
        $decoded = json_decode($items, true) ?? [];
    } elseif (is_array($items)) {
        $decoded = $items;
    } else {
        $decoded = [];
    }

    $lista = $decoded;
    $resumen = null;

    if (isset($decoded['items']) && is_array($decoded['items'])) {
        $lista = $decoded['items'];
        $resumen = $decoded['resumen'] ?? null;
    }

    $lista = collect($lista)
        ->filter(fn($item) => is_array($item) && (!empty($item['subtotal']) || !empty($item['precio_unitario'])))
        ->values()
        ->all();

    return [
        'items' => $lista,
        'resumen' => is_array($resumen) ? $resumen : null,
    ];
}

private function calcularImporteDesdeTarea($tarea): float
{
    $precio = (float) ($tarea->cotio_precio ?? 0);
    $cantidad = (float) ($tarea->cotio_cantidad ?? 1);

    if ($cantidad <= 0) {
        $cantidad = 1;
    }

    return $precio * $cantidad;
}

/**
 * Suma importes tarifario de análisis ya facturados para una instancia de muestra.
 * Los importes en cotio son por línea de cotización; si la línea ensayo tiene cantidad > 1,
 * se prorratean (misma base que CotizacionPrecioEnsayo::importeEnsayoMasAnalitos).
 *
 * @param  \Illuminate\Support\Collection|\Traversable|array|null  $analisisYaFacturados
 * @param  \Illuminate\Support\Collection|null  $tareas
 */
private function sumaPreciosAnalisisFacturadosRepartida($analisisYaFacturados, array $analisisTarifario, $tareas, float $cantidadLineaEnsayo): float
{
    if ($analisisYaFacturados === null) {
        return 0.0;
    }
    if (is_array($analisisYaFacturados)) {
        $analisisYaFacturados = collect($analisisYaFacturados);
    }
    if ($analisisYaFacturados instanceof \Illuminate\Support\Collection && $analisisYaFacturados->isEmpty()) {
        return 0.0;
    }
    if (! is_iterable($analisisYaFacturados)) {
        return 0.0;
    }

    $cant = $cantidadLineaEnsayo > 0 ? $cantidadLineaEnsayo : 1.0;
    $suma = 0.0;
    $tareasColl = $tareas instanceof \Illuminate\Support\Collection ? $tareas : null;

    foreach ($analisisYaFacturados as $analisisFacturado) {
        $itemK = (int) ($analisisFacturado->cotio_item ?? 0);
        $subK = (int) ($analisisFacturado->cotio_subitem ?? 0);
        $precioAnalisis = (float) ($analisisTarifario[$itemK][$subK]['precio'] ?? 0.0);
        if ($precioAnalisis == 0.0 && $tareasColl) {
            $tareaAnalisis = $tareasColl->where('cotio_item', $itemK)->where('cotio_subitem', $subK)->first();
            if ($tareaAnalisis) {
                $precioAnalisis = $this->calcularImporteDesdeTarea($tareaAnalisis);
            }
        }
        $suma += $precioAnalisis / $cant;
    }

    return $suma;
}

private function construirResumenFinanciero(Coti $cotizacion, $tareas = null): array
{
    $tareasCollection = $tareas instanceof \Illuminate\Support\Collection ? $tareas : collect($tareas ?? $cotizacion->tareas);

    $ensayos = $tareasCollection->where('cotio_subitem', 0);
    $componentes = $tareasCollection->where('cotio_subitem', '>', 0);

    $muestrasInfo = [];
    $analisisInfo = [];

    foreach ($ensayos as $ensayo) {
        $cantidad = (float) ($ensayo->cotio_cantidad ?? 1);
        if ($cantidad <= 0) {
            $cantidad = 1;
        }

        $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);

        $precioSoloComponentes = $componentesDelEnsayo->sum(function ($componente) {
            if ($componente->de_agrupador) {
                return 0;
            }
            return $this->calcularImporteDesdeTarea($componente);
        });

        $esNuevaLogica = $tareasCollection->contains(function($t) {
            return (bool)($t->de_agrupador ?? false);
        });

        $nullableCotio = null;
        if ($ensayo->cotio_precio !== null && $ensayo->cotio_precio !== '') {
            $nullableCotio = (float) $ensayo->cotio_precio;
        }
        $precioAdicionalUnitario = CotizacionPrecioEnsayo::resolverPrecioExtraEnsayoDesdeCotioRow(
            $nullableCotio,
            (float) $precioSoloComponentes,
            $esNuevaLogica
        );
        $subtotalMuestra = CotizacionPrecioEnsayo::importeEnsayoMasAnalitos(
            $cantidad,
            (float) $precioSoloComponentes,
            $ensayo->cotio_precio ?? null,
            $esNuevaLogica
        );
        $precioUnitarioEfectivo = $cantidad > 0 ? ($subtotalMuestra / $cantidad) : $subtotalMuestra;

        $muestrasInfo[$ensayo->cotio_item] = [
            'descripcion' => $ensayo->cotio_descripcion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitarioEfectivo,
            'subtotal' => $subtotalMuestra,
            'precio_adicional_unitario' => $precioAdicionalUnitario,
            'subtotal_analitos' => (float) $precioSoloComponentes,
            'subtotal_adicional' => $precioAdicionalUnitario * $cantidad,
        ];

        foreach ($componentesDelEnsayo as $componente) {
            $precioComp = $this->calcularImporteDesdeTarea($componente);
            if ($componente->de_agrupador) {
                $precioComp = 0.0;
            }
            $analisisInfo[$componente->cotio_item][$componente->cotio_subitem] = [
                'descripcion' => $componente->cotio_descripcion,
                'precio' => $precioComp,
                'de_agrupador' => $componente->de_agrupador,
            ];
        }
    }

    $componentesExtras = $componentes->filter(function ($componente) use ($ensayos) {
        return !$ensayos->contains('cotio_item', $componente->cotio_item);
    });

    $componentesExtrasDetalle = $componentesExtras->map(function ($componente) {
        return [
            'item' => $componente->cotio_item,
            'descripcion' => $componente->cotio_descripcion,
            'precio' => $this->calcularImporteDesdeTarea($componente),
        ];
    })->values();

    $totalMuestras = array_reduce($muestrasInfo, function ($carry, $item) {
        return $carry + ($item['subtotal'] ?? 0);
    }, 0.0);

    $totalComponentesExtras = $componentesExtrasDetalle->sum('precio');

    $descuentoData = $this->obtenerDatosDescuento($cotizacion);
    $aumentoPorcentaje = max(0.0, min((float) ($cotizacion->coti_aumentoglobal ?? 0.0), 100.0));
    $aumentoFactor = $aumentoPorcentaje / 100;

    $resumenEconomico = CotizacionResumenEconomico::calcular(
        $tareasCollection,
        $aumentoPorcentaje,
        $descuentoData['porcentaje_global'],
        $descuentoData['porcentaje_sector']
    );

    $totalBruto = $resumenEconomico['subtotal'];
    $descuentoMontoGlobal = $resumenEconomico['descuento_global_monto'];
    $descuentoMontoSector = $resumenEconomico['descuento_sector_monto'];
    $descuentoMontoTotal = $resumenEconomico['descuento_total_monto'];
    $aumentoMontoTotal = $resumenEconomico['aumento_monto'];
    $totalNeto = $resumenEconomico['total_final'];

    return [
        'muestras' => $muestrasInfo,
        'analisis' => $analisisInfo,
        'componentes_extra' => $componentesExtrasDetalle,
        'totales' => [
            'bruto' => round($totalBruto, 2),
            'descuento_monto' => $descuentoMontoTotal,
            'descuento_porcentaje' => $descuentoData['porcentaje_total'],
            'descuento_global_porcentaje' => $descuentoData['porcentaje_global'],
            'descuento_sector_porcentaje' => $descuentoData['porcentaje_sector'],
            'descuento_global_monto' => $descuentoMontoGlobal,
            'descuento_sector_monto' => $descuentoMontoSector,
            'descuento_sector_etiqueta' => $descuentoData['sector_etiqueta'],
            'aumento_porcentaje' => $aumentoPorcentaje,
            'aumento_monto' => $aumentoMontoTotal,
            'neto' => $totalNeto,
        ],
        'descuento_factor' => $descuentoData['factor_total'],
        'aumento_factor' => $aumentoFactor,
        'descuento_detalle' => $descuentoData,
    ];
}

/**
 * Solo dígitos para CUIT / DocNro AFIP.
 */
private function afipSoloDigitos(?string $valor): string
{
    return preg_replace('/\D/', '', (string) $valor);
}

/**
 * CUIT del emisor (laboratorio) para el SDK Afip: 11 dígitos. Se envía en el TA (app.afipsdk.com).
 *
 * @return array{cuit_int: int, cuit_log: string}
 */
private function afipResolverCuitEmisorParaSdk(): array
{
    $digits = $this->afipSoloDigitos((string) config('afip.cuit', '20409378472'));
    if (strlen($digits) !== 11) {
        throw new \InvalidArgumentException('AFIP_CUIT debe tener exactamente 11 dígitos (CUIT emisor registrado en Afip SDK).');
    }

    return [
        'cuit_int' => (int) $digits,
        'cuit_log' => $digits,
    ];
}

/**
 * Dígito verificador de CUIT (11 dígitos) sobre la base de 10 dígitos iniciales.
 */
private function afipCuitCalcularDigitoVerificador(string $base10): int
{
    if (strlen($base10) !== 10 || ! ctype_digit($base10)) {
        throw new \InvalidArgumentException('afipCuitCalcularDigitoVerificador: se esperaban 10 dígitos numéricos.');
    }

    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += ((int) $base10[$i]) * $pesos[$i];
    }
    $mod = $suma % 11;
    $dv = 11 - $mod;
    if ($dv === 11) {
        return 0;
    }
    if ($dv === 10) {
        return 9;
    }

    return $dv;
}

/**
 * Lleva un CUIT del cliente (ficha/cotización) a 11 dígitos con DV correcto, si es posible.
 * Cubre: 6–8 dígitos (DNI/cuerpo), 9 (tipo+cuerpo sin DV o 20 + 8 sin prefijo fijo en tipo),
 * 10 (sin DV), 11 (corrige DV si estaba mal), y >11 (carga duplicada: primeros 11).
 */
private function afipNormalizarCuitReceptor11(?string $cuitRaw): ?string
{
    $d = $this->afipSoloDigitos((string) $cuitRaw);
    if ($d === '') {
        return null;
    }
    if (strlen($d) > 11) {
        $d = substr($d, 0, 11);
    }

    if (strlen($d) === 11) {
        $b10 = substr($d, 0, 10);
        $esperado = $this->afipCuitCalcularDigitoVerificador($b10);
        if ((int) $d[10] === $esperado) {
            return $d;
        }

        return $b10 . (string) $esperado;
    }
    if (strlen($d) === 10) {
        $b10 = $d;

        return $b10 . (string) $this->afipCuitCalcularDigitoVerificador($b10);
    }
    if (strlen($d) === 8) {
        $b10 = '20' . $d;

        return $b10 . (string) $this->afipCuitCalcularDigitoVerificador($b10);
    }
    if (strlen($d) === 7) {
        $b10 = '20' . str_pad($d, 8, '0', STR_PAD_LEFT);

        return $b10 . (string) $this->afipCuitCalcularDigitoVerificador($b10);
    }
    if (strlen($d) === 9) {
        $tipo = substr($d, 0, 2);
        $tiposCuit = ['20', '23', '24', '27', '30', '33', '34'];
        if (in_array($tipo, $tiposCuit, true)) {
            $cuerpo = str_pad(substr($d, 2), 8, '0', STR_PAD_LEFT);
            if (strlen($cuerpo) !== 8) {
                return null;
            }
            $b10 = $tipo . $cuerpo;
        } else {
            $b10 = '20' . str_pad(substr($d, 0, 8), 8, '0', STR_PAD_LEFT);
        }

        return $b10 . (string) $this->afipCuitCalcularDigitoVerificador($b10);
    }
    if (strlen($d) >= 1 && strlen($d) <= 6) {
        $b10 = '20' . str_pad($d, 8, '0', STR_PAD_LEFT);
        if (strlen($b10) === 10) {
            return $b10 . (string) $this->afipCuitCalcularDigitoVerificador($b10);
        }
    }

    return null;
}

/**
 * DocNro receptor (DocTipo 80) a partir de CUIT de 11 dígitos. Nunca usar 0 en FECAE.
 */
private function afipDocNroReceptorDesdeCuit11(?string $cuit11): int
{
    if ($cuit11 === null || strlen($cuit11) !== 11 || ! ctype_digit($cuit11)) {
        return 0;
    }

    return (int) $cuit11;
}

/**
 * Ruta absoluta a un archivo de certificado/clave configurado en .env.
 */
private function afipResolverRutaArchivo(string $ruta): string
{
    $ruta = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta));
    if ($ruta === '') {
        return '';
    }
    if (preg_match('#^[A-Za-z]:[/\\\\]#', $ruta) || str_starts_with($ruta, DIRECTORY_SEPARATOR)) {
        return $ruta;
    }

    return base_path($ruta);
}

/**
 * Lee PEM de certificado y clave, u omite ambos en modo desarrollo con el CUIT de testing del SDK.
 *
 * @return array{cert: string|null, key: string|null, modo_sin_certificado?: true}|array{success: false, error: string, detalle: string}
 */
private function afipCargarCertYKeyDesdeEnv(): array
{
    // Usar config(), no env(): con config:cache, env() devuelve null fuera de config/ y falla el modo solo-token.
    $certRel = config('afip.cert_path');
    $keyRel = config('afip.key_path');
    $certVacio = ! is_string($certRel) || trim($certRel) === '';
    $keyVacio = ! is_string($keyRel) || trim($keyRel) === '';

    $production = (bool) config('afip.production', false);
    $cuitEmisor = $this->afipSoloDigitos((string) config('afip.cuit', ''));

    if (! $production
        && $cuitEmisor === self::AFIP_CUIT_SDK_SOLO_TOKEN
        && $certVacio
        && $keyVacio) {
        return [
            'cert' => null,
            'key' => null,
            'modo_sin_certificado' => true,
        ];
    }

    if ($certVacio || $keyVacio) {
        return [
            'success' => false,
            'error' => 'Faltan AFIP_CERT_PATH y/o AFIP_KEY_PATH en .env.',
            'detalle' => 'Con un CUIT propio el certificado es obligatorio incluso en homologación. '
                .'Para probar solo con token, usá AFIP_CUIT='.self::AFIP_CUIT_SDK_SOLO_TOKEN.', AFIP_PRODUCTION=false '
                .'y no definas rutas de certificado ni clave.',
        ];
    }

    $certPath = $this->afipResolverRutaArchivo($certRel);
    $keyPath = $this->afipResolverRutaArchivo($keyRel);

    if ($certPath === '' || ! is_file($certPath) || ! is_readable($certPath)) {
        return [
            'success' => false,
            'error' => 'Certificado AFIP no encontrado o ilegible.',
            'detalle' => $certPath !== '' ? $certPath : $certRel,
        ];
    }
    if ($keyPath === '' || ! is_file($keyPath) || ! is_readable($keyPath)) {
        return [
            'success' => false,
            'error' => 'Clave privada AFIP no encontrada o ilegible.',
            'detalle' => $keyPath !== '' ? $keyPath : $keyRel,
        ];
    }

    $cert = @file_get_contents($certPath);
    $key = @file_get_contents($keyPath);
    if ($cert === false || trim($cert) === '') {
        return [
            'success' => false,
            'error' => 'No se pudo leer el certificado PEM.',
            'detalle' => $certPath,
        ];
    }
    if ($key === false || trim($key) === '') {
        return [
            'success' => false,
            'error' => 'No se pudo leer la clave privada PEM.',
            'detalle' => $keyPath,
        ];
    }

    return ['cert' => $cert, 'key' => $key];
}

private function integrarConArca($clienteData, $items, $montoTotal, $cotizacion)
{
    try {
        // CONFIGURACIÓN: Entorno de PRUEBA de AFIP con PRECIOS REALES de la BD
        Log::info('Integrando con AFIP en modo PRUEBA usando precios reales de BD', [
            'monto_total' => $montoTotal,
            'entorno' => 'homologacion',
            'precios' => 'reales_bd'
        ]);

        $tokenSdk = config('afip.access_token');
        if (! is_string($tokenSdk) || trim($tokenSdk) === '') {
            return [
                'success' => false,
                'error' => 'Falta configurar AFIPSDK_ACCESS_TOKEN en .env.',
                'detalle' => 'Sin token, el SDK no puede obtener el Ticket de Acceso (wsfe).',
            ];
        }

        $emisor = $this->afipResolverCuitEmisorParaSdk();

        $pem = $this->afipCargarCertYKeyDesdeEnv();
        if (isset($pem['success']) && $pem['success'] === false) {
            return [
                'success' => false,
                'error' => $pem['error'],
                'detalle' => $pem['detalle'] ?? null,
            ];
        }

        $productionAfip = (bool) config('afip.production', false);

        $opcionesAfip = [
            'CUIT' => $emisor['cuit_log'],
            'production' => $productionAfip,
            'access_token' => $tokenSdk,
            'debug' => true,
        ];
        if (empty($pem['modo_sin_certificado'])) {
            $opcionesAfip['cert'] = $pem['cert'];
            $opcionesAfip['key'] = $pem['key'];
        }

        $afip = new Afip($opcionesAfip);

        Log::info('AFIP SDK emisor (auth TA)', [
            'cuit_emisor' => $emisor['cuit_log'],
            'production' => $productionAfip,
            'sin_certificado_sdk' => ! empty($pem['modo_sin_certificado']),
        ]);

        $neto = round($montoTotal / 1.21, 2);
        $iva = round($montoTotal - $neto, 2);

        // AFIP: para comprobantes clase 'A' (CbteTipo=1) exige DocTipo=80 (CUIT).
        // Se normaliza desde la ficha (6–11 dígitos, DV, tipos 20/27/30, etc.) en
        // {@see afipNormalizarCuitReceptor11()}.
        $cuitRaw = (string) ($clienteData['cuit'] ?? '');
        $cuitDigits = $this->afipSoloDigitos($cuitRaw);
        $cuit11 = $this->afipNormalizarCuitReceptor11($cuitRaw);

        $docTipo = 80; // Clase A => CUIT
        $docNro = $this->afipDocNroReceptorDesdeCuit11($cuit11);
        if ($docNro <= 0) {
            $hint = $cuitDigits === ''
                ? ' El CUIT en la ficha no tiene dígitos; revisá el campo o los guiones (solo se envían números).'
                : '';

            return [
                'success' => false,
                'error' => 'CUIT del receptor fiscal inválido para factura clase A (DocTipo 80).',
                'detalle' => 'Normalice el CUIT del cliente a 11 dígitos o complete la ficha fiscal (cli_cuit / coti_cuit).'
                    . $hint
                    . (strlen($cuitDigits) > 0 ? ' Dígitos detectados: ' . strlen($cuitDigits) . '.' : ''),
            ];
        }

        Log::info('AFIP DocNro/DocTipo (receptor) para FECAESolicitar', [
            'cuit_raw' => $cuitRaw,
            'cuitDigits' => $cuitDigits,
            'cuit11' => $cuit11,
            'docTipo' => $docTipo,
            'docNro' => $docNro,
        ]);

        // Determinar divisa para AFIP
        $divisaCodigo = $cotizacion->divisa_codigo ?? 'PES';
        // Mapear código interno a código AFIP
        switch ($divisaCodigo) {
            case 'USD':
                $monId = 'DOL';
                break;
            default:
                $monId = 'PES';
                break;
        }
        // Por ahora cotización fija 1; se puede parametrizar luego
        $monCotiz = 1;

        $ptoVta = max(1, min(9999, (int) config('afip.punto_venta', 1)));
        $cbteTipo = max(1, (int) config('afip.cbte_tipo', 1));

        $facturaData = [
            'CbteTipo' => $cbteTipo,
            'PtoVta' => $ptoVta,
            'Concepto' => 1,
            'DocTipo' => (int) $docTipo,
            'DocNro' => (int) $docNro,
            'CondicionIVAReceptorId' => 1, // Consumidor Final
            'CbteFch' => date('Ymd'),
            'ImpTotal' => number_format((float) $montoTotal, 2, '.', ''),
            'ImpTotConc' => 0,
            'ImpNeto' => number_format((float) $neto, 2, '.', ''),
            'ImpIVA' => number_format((float) $iva, 2, '.', ''),
            'MonId' => $monId,
            'MonCotiz' => (int) $monCotiz,
            'Iva' => [
                [
                    'Id' => 5,
                    'BaseImp' => round($neto, 2),
                    'Importe' => round($iva, 2),
                ]
            ],
        ];

        $facturaData['Detalles'] = [];
        foreach ($items as $item) {
            $unitPrice = round($item['precio_unitario'] / 1.21, 2);
            $importe = round($item['subtotal'] / 1.21, 2);
            $qty = (int) ($item['cantidad'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }

            $facturaData['Detalles'][] = [
                'Qty' => $qty,
                'ProDs' => substr((string) ($item['descripcion'] ?? ''), 0, 250),
                'ProUMed' => 7,
                'ProPrecioUnit' => number_format((float) $unitPrice, 2, '.', ''),
                'ProImporteItem' => number_format((float) $importe, 2, '.', ''),
                'ProBonif' => 0,
            ];
        }

        if ((bool) config('afip.direct_wsfe', false)) {
            if (! empty($pem['modo_sin_certificado'])) {
                return [
                    'success' => false,
                    'error' => 'AFIP_DIRECT_WSFE requiere certificado y clave PEM.',
                    'detalle' => 'El modo directo usa WSAA local (firma con tu .key). Con solo token (CUIT 20409378472 sin PEM) tenés que usar el proxy del SDK. Si app.afipsdk.com falla, generá PEM (php artisan afip:create-cert-dev), configurá AFIP_CERT_PATH/AFIP_KEY_PATH, tu CUIT y AFIP_DIRECT_WSFE=true.',
                ];
            }
            if (! extension_loaded('soap')) {
                return [
                    'success' => false,
                    'error' => 'AFIP_DIRECT_WSFE requiere la extensión PHP soap.',
                    'detalle' => 'Habilitá extension=soap en php.ini del PHP que usa php artisan serve.',
                ];
            }

            return $this->integrarConArcaWsfeDirecto($facturaData, $emisor, $pem, $productionAfip);
        }

        $wsfe = $afip->ElectronicBilling;
        
        // Paso 1: Obtener el último comprobante autorizado para conocer el número y la fecha
        try {
            $ultimoNumeroRaw = $wsfe->GetLastVoucher((int) $facturaData['PtoVta'], (int) $facturaData['CbteTipo']);
            $ultimoNumero = is_numeric($ultimoNumeroRaw) ? (int) $ultimoNumeroRaw : 0;
            if ($ultimoNumero < 0) {
                $ultimoNumero = 0;
            }
            $proximoNumero = $ultimoNumero + 1;
            
            Log::info('Último comprobante autorizado', [
                'PtoVta' => $facturaData['PtoVta'],
                'CbteTipo' => $facturaData['CbteTipo'],
                'ultimo_numero' => $ultimoNumero,
                'proximo_numero' => $proximoNumero
            ]);
            
            // Paso 2: Obtener información del último comprobante para conocer su fecha
            $fechaUltimoComprobante = null;
            if ($ultimoNumero > 0) {
                try {
                    $infoUltimoComprobante = $wsfe->GetVoucherInfo($ultimoNumero, $facturaData['PtoVta'], $facturaData['CbteTipo']);
                    if ($infoUltimoComprobante && isset($infoUltimoComprobante->CbteFch)) {
                        $fechaUltimoComprobante = $infoUltimoComprobante->CbteFch;
                        Log::info('Fecha del último comprobante obtenida', [
                            'fecha_ultimo' => $fechaUltimoComprobante,
                            'fecha_actual_intentada' => $facturaData['CbteFch']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo obtener información del último comprobante: ' . $e->getMessage());
                }
            }
            
            // Paso 3: Ajustar la fecha si es necesario
            // La fecha debe ser igual o posterior a la del último comprobante
            $fechaActual = $facturaData['CbteFch'];
            if ($fechaUltimoComprobante && $fechaUltimoComprobante > $fechaActual) {
                // Si la fecha del último comprobante es posterior, usar esa fecha o una posterior
                $facturaData['CbteFch'] = $fechaUltimoComprobante;
                Log::warning('Fecha ajustada para cumplir con requisitos de AFIP', [
                    'fecha_anterior' => $fechaActual,
                    'fecha_ajustada' => $facturaData['CbteFch'],
                    'fecha_ultimo_comprobante' => $fechaUltimoComprobante
                ]);
            }
            
            // Paso 4: Establecer el número del comprobante explícitamente
            $facturaData['CbteDesde'] = (int) $proximoNumero;
            $facturaData['CbteHasta'] = (int) $proximoNumero;
            
            Log::info('Datos del comprobante a generar', [
                'PtoVta' => $facturaData['PtoVta'],
                'CbteTipo' => $facturaData['CbteTipo'],
                'CbteDesde' => $facturaData['CbteDesde'],
                'CbteHasta' => $facturaData['CbteHasta'],
                'CbteFch' => $facturaData['CbteFch'],
                'ImpTotal' => $facturaData['ImpTotal']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener último comprobante autorizado', [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $msg = $e->getMessage();
            $detalle = 'No se pudo consultar el último comprobante autorizado en AFIP.';
            if (str_contains($msg, 'GetServiceTA')
                || str_contains($msg, 'NumberFormatException')
                || str_contains($msg, 'BigInteger')
                || str_contains($msg, 'afip/auth')) {
                $detalle = 'Fallo al obtener el Ticket de Acceso (Afip SDK → app.afipsdk.com). Suele deberse a AFIPSDK_ACCESS_TOKEN, a AFIP_CUIT (11 dígitos, mismo CUIT que el certificado en el panel del SDK) o al entorno de homologación; no indica que DocNro del receptor esté vacío en esta llamada. '
                    .'Si el error persiste, probá AFIP_DIRECT_WSFE=true con AFIP_CERT_PATH y AFIP_KEY_PATH (WSAA+WSFE directo a ARCA, requiere extension soap).';
            }
            
            // Si no se puede obtener el último comprobante, intentar con CreateNextVoucher como fallback
            Log::info('Intentando con CreateNextVoucher como fallback');
            try {
                $result = $wsfe->CreateNextVoucher($facturaData);
                
                if (isset($result['CAE'], $result['voucher_number'], $result['CAEFchVto'])) {
                    return [
                        'success' => true,
                        'numero_factura' => sprintf('%04d-%08d', $facturaData['PtoVta'], $result['voucher_number']),
                        'cae' => $result['CAE'],
                        'fecha_vencimiento' => $result['CAEFchVto'],
                    ];
                }
            } catch (\Exception $e2) {
                Log::error('Error también con CreateNextVoucher: ' . $e2->getMessage());
            }
            
            return [
                'success' => false,
                'error' => 'Error al obtener último comprobante autorizado: ' . $msg,
                'detalle' => $detalle,
            ];
        }
        
        // Paso 5: Crear el comprobante con los parámetros correctos
        try {
            $result = $wsfe->CreateVoucher($facturaData);
            
            if (isset($result['CAE'])) {
                return [
                    'success' => true,
                    'numero_factura' => sprintf('%04d-%08d', $facturaData['PtoVta'], $proximoNumero),
                    'cae' => $result['CAE'],
                    'fecha_vencimiento' => $result['CAEFchVto'] ?? null,
                ];
            }
            
            Log::error('Error al generar factura en AFIP: Respuesta incompleta', [
                'resultado' => $result
            ]);
            
            return [
                'success' => false,
                'error' => isset($result['Errors'])
                    ? 'Error AFIP: ' . json_encode($result['Errors'], JSON_UNESCAPED_UNICODE)
                    : 'Error al generar factura: Respuesta incompleta.',
            ];
            
        } catch (\Exception $e) {
            Log::error('Excepción al llamar CreateVoucher', [
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'codigo_error' => $e->getCode(),
                'proximo_numero' => $proximoNumero,
                'fecha_usada' => $facturaData['CbteFch']
            ]);
            
            return [
                'success' => false,
                'error' => 'Error al generar factura: ' . $e->getMessage(),
                'detalle' => 'Verificar que el punto de venta y tipo de comprobante sean correctos, y que la fecha sea válida.'
            ];
        }

    } catch (\Exception $e) {
        Log::error('Error en integración ARCA: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error en integración ARCA: ' . $e->getMessage(),
        ];
    }
}

/**
 * WSAA + WSFEv1 contra ARCA sin app.afipsdk.com (AFIP_DIRECT_WSFE=true).
 *
 * @param  array{cert: string, key: string}  $pem
 * @param  array{cuit_int: int, cuit_log: string}  $emisor
 */
private function integrarConArcaWsfeDirecto(array $facturaData, array $emisor, array $pem, bool $productionAfip): array
{
    Log::info('AFIP WSFE directo (WSAA + SoapClient, sin proxy Afip SDK)', [
        'cuit_emisor' => $emisor['cuit_log'],
        'production' => $productionAfip,
    ]);

    try {
        $direct = AfipDirectWsfeClient::fromConfig($emisor['cuit_log'], $pem['cert'], $pem['key']);

        $ultimoNumeroRaw = $direct->getLastVoucher((int) $facturaData['PtoVta'], (int) $facturaData['CbteTipo']);
        $ultimoNumero = is_numeric($ultimoNumeroRaw) ? (int) $ultimoNumeroRaw : 0;
        if ($ultimoNumero < 0) {
            $ultimoNumero = 0;
        }
        $proximoNumero = $ultimoNumero + 1;

        Log::info('Último comprobante autorizado (WSFE directo)', [
            'PtoVta' => $facturaData['PtoVta'],
            'CbteTipo' => $facturaData['CbteTipo'],
            'ultimo_numero' => $ultimoNumero,
            'proximo_numero' => $proximoNumero,
        ]);

        $fechaUltimoComprobante = null;
        if ($ultimoNumero > 0) {
            try {
                $infoUltimoComprobante = $direct->getVoucherInfo($ultimoNumero, (int) $facturaData['PtoVta'], (int) $facturaData['CbteTipo']);
                if ($infoUltimoComprobante && isset($infoUltimoComprobante->CbteFch)) {
                    $fechaUltimoComprobante = $infoUltimoComprobante->CbteFch;
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener información del último comprobante (WSFE directo): '.$e->getMessage());
            }
        }

        $fechaActual = $facturaData['CbteFch'];
        if ($fechaUltimoComprobante && $fechaUltimoComprobante > $fechaActual) {
            $facturaData['CbteFch'] = $fechaUltimoComprobante;
        }

        $facturaData['CbteDesde'] = (int) $proximoNumero;
        $facturaData['CbteHasta'] = (int) $proximoNumero;

        $result = $direct->createVoucher($facturaData);

        if (isset($result['CAE'])) {
            return [
                'success' => true,
                'numero_factura' => sprintf('%04d-%08d', $facturaData['PtoVta'], $proximoNumero),
                'cae' => $result['CAE'],
                'fecha_vencimiento' => $result['CAEFchVto'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => 'Error al generar factura: respuesta incompleta (WSFE directo).',
        ];
    } catch (\Throwable $e) {
        Log::error('Error en WSFE directo (WSAA/ARCA)', [
            'mensaje' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'error' => 'Error en facturación AFIP (WSFE directo): '.$e->getMessage(),
            'detalle' => 'Comprobá PEM, CUIT, AFIP_PRODUCTION, wsfe autorizado en homologación y extension=soap.',
        ];
    }
}

/**
 * Ver detalle de una factura específica
 */
public function verFactura($id)
{
    $factura = Factura::with(['cotizacion.cliente'])->findOrFail($id);
    
    $normalizado = $this->normalizarItemsFactura($factura->items);

    return view('facturacion.detalle', [
        'factura' => $factura,
        'items' => $normalizado['items'],
        'resumenItems' => $normalizado['resumen'],
    ]);
}

public function descargar(Factura $factura)
{
    try {
        Log::info('Descargando factura', [
            'factura_id' => $factura->id,
            'numero_factura' => $factura->numero_factura,
            'cae' => $factura->cae
        ]);

        $filePath = $this->resolverRutaPdfFactura($factura);

        return response()->download($filePath, basename($filePath));

    } catch (\Exception $e) {
        Log::error('Error al generar/descargar factura: ' . $e->getMessage(), [
            'factura_id' => $factura->id,
            'numero_factura' => $factura->numero_factura,
            'error_trace' => $e->getTraceAsString()
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'error' => 'No se pudo generar el PDF: ' . $e->getMessage()
            ], 500);
        }

        return redirect()->back()->with('error', 'Error al generar la factura: ' . $e->getMessage());
    }
}

protected function resolverRutaPdfFactura(Factura $factura): string
{
        // Verificar si ya tenemos un PDF guardado localmente
        $fileName = 'Factura_' . str_replace(['-', '/', '\\'], '_', $factura->numero_factura) . '.pdf';
        $filePath = storage_path('app/facturas/' . $fileName);

        if (file_exists($filePath)) {
            Log::info('PDF encontrado en cache', ['file' => $fileName]);
            return $filePath;
        }

        // Crear directorio si no existe
        $directory = storage_path('app/facturas');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Cargar el archivo bill.html
        $htmlPath = base_path('resources/views/facturacion/bill.html');
        if (!file_exists($htmlPath)) {
            throw new \Exception('Archivo bill.html no encontrado en ' . $htmlPath);
        }
        $html = file_get_contents($htmlPath);

        $factura->loadMissing(['cotizacion.cliente']);
        $cot = $factura->cotizacion;
        $fiscalBill = $cot ? CotizacionClienteEtiqueta::datosFacturacionFiscales($cot) : [
            'razon_social' => '', 'cuit' => '', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'email' => '',
        ];

        // Preparar los datos para reemplazar los placeholders
        $normalizado = $this->normalizarItemsFactura($factura->items);
        $items = $normalizado['items'];
        $total = (float) ($factura->monto_total ?? 0);
        $neto = round($total / 1.21, 2);
        $iva = round($total - $neto, 2);

        // Tabla de ítems (4 columnas, estilo factura impresa)
        $itemsTable = '';
        if ($items && is_array($items)) {
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $descripcion = htmlspecialchars($item['descripcion'] ?? 'N/A');
                $identificacion = !empty($item['identificacion'])
                    ? '<br><small style="color:#444;">ID: ' . htmlspecialchars($item['identificacion']) . '</small>'
                    : '';
                $resultado = !empty($item['resultado'])
                    ? '<br><small style="color:#444;">' . htmlspecialchars($item['resultado']) . '</small>'
                    : '';

                $cantidad = (float) ($item['cantidad'] ?? 1);
                $precioUnitario = (float) ($item['precio_unitario'] ?? 0);
                $subtotalItem = (float) ($item['subtotal'] ?? ($precioUnitario * $cantidad));

                $itemsTable .= '<tr>'
                    . '<td class="c-cant">' . htmlspecialchars(number_format($cantidad, 2, ',', '.')) . '</td>'
                    . '<td class="c-desc">' . $descripcion . $identificacion . $resultado . '</td>'
                    . '<td class="c-pu">' . htmlspecialchars(number_format($precioUnitario, 2, ',', '.')) . '</td>'
                    . '<td class="c-pt">' . htmlspecialchars(number_format($subtotalItem, 2, ',', '.')) . '</td>'
                    . '</tr>';
            }
        }
        if ($itemsTable === '') {
            $itemsTable = '<tr><td colspan="4" style="text-align:center;padding:0.12in;">No hay ítems registrados</td></tr>';
        }

        $locPart = trim(implode(' ', array_filter([
            trim((string) ($fiscalBill['localidad'] ?? '')),
            trim((string) ($fiscalBill['provincia'] ?? '')),
        ])));
        $refRemito = $cot ? CotizacionReferenciasFacturacion::textoPrimerRemitoParaFactura($cot) : '';
        $remitoRef = $refRemito !== '' ? htmlspecialchars($refRemito, ENT_QUOTES, 'UTF-8') : '—';
        $refsExtraHtml = $cot ? CotizacionReferenciasFacturacion::htmlReferenciasExtraParaFactura($cot) : '';
        $lineaRefsCotizacion = $refsExtraHtml !== '' ? '<br>' . $refsExtraHtml : '';

        $condicionesVenta = '—';
        if ($cot && trim((string) ($cot->coti_cond_pago ?? '')) !== '') {
            $condicionesVenta = htmlspecialchars(trim((string) $cot->coti_cond_pago));
        }

        $fechaVtoCae = $factura->fecha_vencimiento_cae
            ? Carbon::parse($factura->fecha_vencimiento_cae)->format('d/m/Y')
            : 'N/A';
        $fechaEmisionFmt = $factura->fecha_emision ? $factura->fecha_emision->format('d/m/Y') : 'N/A';

        $fechaVtoPagoFmt = trim((string) env('FACTURA_FECHA_VTO_PAGO', ''));
        if ($fechaVtoPagoFmt !== '') {
            $fechaVtoPagoFmt = htmlspecialchars($fechaVtoPagoFmt, ENT_QUOTES, 'UTF-8');
        } else {
            $diasVto = env('FACTURA_VTO_PAGO_DIAS');
            if ($diasVto !== null && $diasVto !== '' && is_numeric($diasVto) && $factura->fecha_emision) {
                $fechaVtoPagoFmt = Carbon::parse($factura->fecha_emision)->addDays((int) $diasVto)->format('d/m/Y');
            } else {
                $fechaVtoPagoFmt = $fechaVtoCae;
            }
        }

        $leyendaCambioRaw = trim((string) env('FACTURA_LEYENDA_TIPO_CAMBIO', ''));
        $leyendaTipoCambioBlock = $leyendaCambioRaw !== ''
            ? '<p class="leyenda-cambio">' . htmlspecialchars($leyendaCambioRaw, ENT_QUOTES, 'UTF-8') . '</p>'
            : '';

        $tipoLetra = strtoupper(substr(trim((string) env('FACTURA_TIPO_LETRA', 'A')), 0, 1));
        $codigoAfipRaw = trim((string) env('FACTURA_CODIGO_COMPROBANTE', ''));
        if ($codigoAfipRaw === '') {
            $codigoAfipRaw = $tipoLetra === 'B' ? '6' : ($tipoLetra === 'C' ? '11' : '1');
        }

        $cotizDisplay = $cot?->coti_num !== null ? (string) $cot->coti_num : (string) ($factura->cotizacion_id ?? '—');
        $otNumerosRaw = trim((string) env('FACTURA_OT_NUMEROS', ''));
        if ($otNumerosRaw !== '') {
            $otNumerosFmt = preg_replace('/\s*,\s*/', ' ', $otNumerosRaw);
            $lineaCotizOt = 'Cotiz.: ' . htmlspecialchars($cotizDisplay, ENT_QUOTES, 'UTF-8')
                . ' OTs: ' . htmlspecialchars($otNumerosFmt, ENT_QUOTES, 'UTF-8');
        } else {
            $otSingle = trim((string) env('FACTURA_OT_NUMERO', ''));
            $lineaCotizOt = 'Cotiz.: ' . htmlspecialchars($cotizDisplay, ENT_QUOTES, 'UTF-8')
                . ' OT: ' . htmlspecialchars($otSingle !== '' ? $otSingle : '—', ENT_QUOTES, 'UTF-8');
        }

        $fechaTrabajo = $fechaEmisionFmt;
        if ($cot?->coti_fechaaprobado) {
            $fechaTrabajo = Carbon::parse($cot->coti_fechaaprobado)->format('j/n/Y');
        }

        $ocVal = trim((string) ($cot?->coti_oc_referencia ?? ''));
        if ($ocVal === '') {
            $ocVal = trim((string) env('FACTURA_OC_NUMERO', ''));
        }
        $solVal = trim((string) ($cot?->coti_responsable ?? $cot?->coti_contacto ?? ''));
        if ($solVal === '') {
            $solVal = trim((string) env('FACTURA_SOLICITANTE', ''));
        }
        $lineaOcSolic = '<span class="lbl">OC:</span> ' . htmlspecialchars($ocVal !== '' ? $ocVal : '—', ENT_QUOTES, 'UTF-8')
            . ' &nbsp; <span class="lbl">SOLIC.:</span> ' . htmlspecialchars($solVal !== '' ? $solVal : '—', ENT_QUOTES, 'UTF-8');

        $codCli = trim((string) ($cot?->coti_codigocli ?? ''));
        $cotiCodigoClienteBloque = $codCli !== ''
            ? ' <span class="cli-cod">(' . htmlspecialchars($codCli, ENT_QUOTES, 'UTF-8') . ')</span>'
            : '';

        $cotiTel = trim((string) ($cot?->coti_telefono ?? ''));
        $cotiTelefonoHtml = $cotiTel !== '' ? htmlspecialchars($cotiTel, ENT_QUOTES, 'UTF-8') : '—';

        $zonaCli = trim((string) env('FACTURA_CLIENTE_ZONA', ''));
        if ($zonaCli === '' && $cot) {
            $zonaCli = trim((string) ($cot->coti_sector ?? ''));
        }
        $clienteZona = $zonaCli !== '' ? htmlspecialchars($zonaCli, ENT_QUOTES, 'UTF-8') : '—';

        $facturaClienteEnv = static function (string $key, string $empty = '—'): string {
            $v = trim((string) env($key, ''));

            return $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : $empty;
        };

        $empresaLogoHtml = $this->buildFacturaLogoImgHtml();
        $qrCodeHtml = $this->buildFacturaQrImgHtml($factura->qr_code ?? null);
        $afipLogoHtml = $this->buildFacturaAfipLogoImgHtml();

        // Reemplazar placeholders
        $replacements = [
            '{{numero_factura}}' => htmlspecialchars($factura->numero_factura ?? 'N/A'),
            '{{empresa_nombre}}' => htmlspecialchars(env('EMPRESA_NOMBRE', 'Industria y Ambiente S.A.')),
            '{{empresa_subtitulo}}' => htmlspecialchars(env('EMPRESA_SUBTITULO', 'Laboratorio de Análisis Industriales y Ambientales. Ingeniería Industrial y Ambiental.')),
            '{{empresa_direccion}}' => htmlspecialchars(env('EMPRESA_DIRECCION', 'Dirección del Laboratorio')),
            '{{empresa_contacto_linea}}' => nl2br(htmlspecialchars(env('EMPRESA_CONTACTO_LINEA', "Tel: —\r\nLa Tablada - Buenos Aires\r\nEmail: —\r\nWeb: —"), ENT_QUOTES, 'UTF-8')),
            '{{empresa_cuit}}' => htmlspecialchars(env('EMPRESA_CUIT', '20-12345678-9')),
            '{{empresa_iibb}}' => htmlspecialchars(env('EMPRESA_IIBB', '—')),
            '{{empresa_fecha_inicio}}' => htmlspecialchars(env('EMPRESA_FECHA_INICIO', '01/10/1999')),
            '{{empresa_condicion_iva}}' => htmlspecialchars(env('EMPRESA_CONDICION_IVA', 'Responsable Inscripto')),
            '{{tipo_comprobante}}' => htmlspecialchars($tipoLetra),
            '{{titulo_factura}}' => htmlspecialchars('Factura ' . $tipoLetra, ENT_QUOTES, 'UTF-8'),
            '{{codigo_comprobante_afip}}' => htmlspecialchars($codigoAfipRaw, ENT_QUOTES, 'UTF-8'),
            '{{punto_venta}}' => htmlspecialchars(substr($factura->numero_factura ?? '0000-00000000', 0, 4)),
            '{{comp_nro}}' => htmlspecialchars(substr($factura->numero_factura ?? '0000-00000000', 5)),
            '{{fecha_emision}}' => $fechaEmisionFmt,
            '{{periodo_desde}}' => $fechaEmisionFmt,
            '{{periodo_hasta}}' => $fechaEmisionFmt,
            '{{fecha_vencimiento_cae}}' => $fechaVtoCae,
            '{{fecha_vencimiento_pago}}' => $fechaVtoPagoFmt,
            '{{coti_num}}' => htmlspecialchars((string) ($factura->cotizacion_id ?? ($cot?->coti_num ?? '—'))),
            '{{coti_cuit}}' => htmlspecialchars($fiscalBill['cuit'] !== '' ? $fiscalBill['cuit'] : 'N/A'),
            '{{coti_empresa}}' => htmlspecialchars($fiscalBill['razon_social'] !== '' ? $fiscalBill['razon_social'] : 'N/A'),
            '{{coti_direccioncli}}' => htmlspecialchars($fiscalBill['domicilio'] !== '' ? $fiscalBill['domicilio'] : 'N/A'),
            '{{coti_localidad_partido}}' => htmlspecialchars($locPart !== '' ? $locPart : '—'),
            '{{coti_condicion_iva}}' => htmlspecialchars(env('FACTURA_CLIENTE_CONDICION_IVA', 'Responsable Inscripto')),
            '{{coti_telefono}}' => $cotiTelefonoHtml,
            '{{coti_codigo_cliente_bloque}}' => $cotiCodigoClienteBloque,
            '{{cliente_zona}}' => $clienteZona,
            '{{cliente_mov}}' => $facturaClienteEnv('FACTURA_CLIENTE_MOV'),
            '{{cliente_origen}}' => $facturaClienteEnv('FACTURA_CLIENTE_ORIGEN'),
            '{{cliente_r}}' => $facturaClienteEnv('FACTURA_CLIENTE_R'),
            '{{cliente_v}}' => $facturaClienteEnv('FACTURA_CLIENTE_V'),
            '{{linea_cotiz_ot}}' => $lineaCotizOt,
            '{{linea_oc_solic}}' => $lineaOcSolic,
            '{{linea_refs_cotizacion}}' => $lineaRefsCotizacion,
            '{{fecha_trabajo}}' => $fechaTrabajo,
            '{{condiciones_venta}}' => $condicionesVenta,
            '{{remito_o_ref}}' => $remitoRef,
            '{{items_table}}' => $itemsTable,
            '{{neto}}' => number_format($neto, 2, ',', '.'),
            '{{iva}}' => number_format($iva, 2, ',', '.'),
            '{{total}}' => number_format($total, 2, ',', '.'),
            '{{iva_alicuota}}' => htmlspecialchars((string) env('FACTURA_IVA_ALICUOTA', '21')),
            '{{moneda_simbolo}}' => htmlspecialchars(env('FACTURA_MONEDA_SIMBOLO', '$')),
            '{{leyenda_tipo_cambio_block}}' => $leyendaTipoCambioBlock,
            '{{cae}}' => htmlspecialchars($factura->cae ?? 'N/A'),
            '{{qr_code}}' => htmlspecialchars($factura->qr_code ?? ''),
            '{{empresa_logo_html}}' => $empresaLogoHtml,
            '{{qr_code_html}}' => $qrCodeHtml,
            '{{afip_logo_html}}' => $afipLogoHtml,
            '{{observaciones_block}}' => !empty($factura->observaciones) 
                ? '<div style="margin-top: 15px; padding: 10px; border: 1px solid #000; font-size: 9.5px;"><strong>Notas:</strong><br>' . nl2br(htmlspecialchars($factura->observaciones)) . '</div>'
                : '',
        ];
        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        file_put_contents(storage_path('app/debug_bill.html'), $html);
        Log::debug('Processed HTML saved to storage/app/debug_bill.html');

        $options = [
            'width' => 8,
            'marginLeft' => 0.4,
            'marginRight' => 0.4,
            'marginTop' => 0.4,
            'marginBottom' => 0.4
        ];

        $soloDomPdf = filter_var(env('FACTURA_PDF_SOLO_DOMPDF', false), FILTER_VALIDATE_BOOLEAN);

        if ($soloDomPdf) {
            $pdfContent = $this->generarPdfFacturaDomPdf($html);
            if (!file_put_contents($filePath, $pdfContent)) {
                throw new \Exception('No se pudo guardar el PDF en ' . $filePath);
            }
            Log::info('PDF generado solo con DomPDF (FACTURA_PDF_SOLO_DOMPDF)', ['factura_id' => $factura->id]);
        } else {
            $accessToken = config('afip.access_token');
            if (empty($accessToken)) {
                throw new \Exception('Variable AFIPSDK_ACCESS_TOKEN no configurada. No es posible generar el PDF.');
            }

            $afip = new Afip([
                'CUIT' => config('afip.cuit'),
                'production' => (bool) config('afip.production', false),
                'access_token' => $accessToken,
                'debug' => true,
            ]);
            try {
                $res = $afip->ElectronicBilling->CreatePDF([
                    'html' => $html,
                    'file_name' => $fileName,
                    'options' => $options
                ]);
                Log::debug('CreatePDF response:', ['response' => $res]);
            } catch (\Exception $e) {
                throw new \Exception('Error en CreatePDF: ' . $e->getMessage());
            }

            if (!isset($res['file'])) {
                throw new \Exception('No se generó el archivo PDF. Respuesta: ' . json_encode($res));
            }

            if (filter_var($res['file'], FILTER_VALIDATE_URL)) {
                $pdfContent = $this->descargarPdfDesdeUrl($res['file']);
                if ($pdfContent === null) {
                    Log::warning('No se pudo descargar el PDF desde S3 del SDK AFIP; usando DomPDF', [
                        'url' => $res['file'],
                        'factura_id' => $factura->id,
                    ]);
                    $pdfContent = $this->generarPdfFacturaDomPdf($html);
                }
                if ($pdfContent === '' || strncmp($pdfContent, '%PDF', 4) !== 0) {
                    throw new \Exception('El resultado no es un PDF válido (S3/DomPDF).');
                }
                if (!file_put_contents($filePath, $pdfContent)) {
                    throw new \Exception('No se pudo guardar el PDF en ' . $filePath);
                }
            } else {
                if (!file_exists($res['file'])) {
                    throw new \Exception('El archivo PDF local no existe: ' . $res['file']);
                }
                if (!rename($res['file'], $filePath)) {
                    throw new \Exception('No se pudo mover el archivo PDF de ' . $res['file'] . ' a ' . $filePath);
                }
            }
        }

        $factura->update(['pdf_url' => $fileName]);

        Log::info('PDF de factura generado', [
            'factura_id' => $factura->id,
            'archivo' => $fileName,
            'tamaño' => filesize($filePath) . ' bytes'
        ]);

        return $filePath;
}


/**
 * Logo en base64 para el PDF (DomPDF / CreatePDF no resuelven bien rutas locales).
 */
private function buildFacturaLogoImgHtml(int $maxHeightPx = 88, float $opacity = 0.88): string
{
    $path = null;
    foreach (['logo.png', 'logo_facturacion.png'] as $name) {
        $candidate = public_path('assets/img/' . $name);
        if (is_readable($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === null) {
        return '';
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return '';
    }
    $src = 'data:image/png;base64,' . base64_encode($raw);
    $opacity = max(0.1, min(1.0, $opacity));

    return '<img src="' . $src . '" alt="" style="max-height:' . $maxHeightPx
        . 'px;width:auto;display:block;opacity:' . $opacity . ';">';
}

/**
 * Imagen QR para el pie (URL, data URI o PNG en base64).
 */
private function buildFacturaQrImgHtml(?string $qr): string
{
    $qr = trim((string) $qr);
    if ($qr === '') {
        return '';
    }
    $style = 'width:118px;height:118px;display:block;';
    if (str_starts_with($qr, 'data:image')) {
        return '<img src="' . htmlspecialchars($qr, ENT_QUOTES, 'UTF-8') . '" alt="QR" style="' . $style . '">';
    }
    if (str_starts_with($qr, 'http://') || str_starts_with($qr, 'https://')) {
        return '<img src="' . htmlspecialchars($qr, ENT_QUOTES, 'UTF-8') . '" alt="QR" style="' . $style . '">';
    }
    if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $qr) && strlen($qr) > 60) {
        $clean = preg_replace('/\s+/', '', $qr);

        return '<img src="data:image/png;base64,' . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . '" alt="QR" style="' . $style . '">';
    }

    return '';
}

/**
 * Logo AFIP para el pie (opcional): colocar PNG en public/assets/img/afip_logo.png o logo_afip.png.
 */
private function buildFacturaAfipLogoImgHtml(int $maxHeightPx = 40): string
{
    foreach (['afip_logo.png', 'logo_afip.png'] as $name) {
        $path = public_path('assets/img/' . $name);
        if (!is_readable($path)) {
            continue;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            continue;
        }
        $mime = str_ends_with(strtolower($name), '.png') ? 'image/png' : 'image/jpeg';
        $src = 'data:' . $mime . ';base64,' . base64_encode($raw);

        return '<img src="' . $src . '" alt="AFIP" style="max-height:' . $maxHeightPx . 'px;width:auto;display:inline-block;">';
    }

    return '';
}

private function urlPdfValida($url)
{
    try {
        $headers = get_headers($url);
        return strpos($headers[0], '200') !== false;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * PDF local con DomPDF (mismo HTML que se envía al SDK AFIP).
 */
private function generarPdfFacturaDomPdf(string $html): string
{
    $pdf = Pdf::loadHTML($html);
    $pdf->setPaper('A4', 'portrait');
    $pdf->setOptions([
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true,
        'defaultFont' => 'DejaVu Sans',
    ]);

    return $pdf->output();
}

/**
 * Descarga el PDF desde la URL del bucket S3 del AFIP SDK.
 * En producción suele fallar file_get_contents (allow_url_fopen, SSL, IPv6, firewall).
 */
private function descargarPdfDesdeUrl(string $url): ?string
{
    $verifySsl = filter_var(env('FACTURA_PDF_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN);

    $guzzleOptions = [
        'curl' => [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ],
    ];
    if (!$verifySsl) {
        $guzzleOptions['verify'] = false;
    }

    try {
        $response = Http::timeout(120)
            ->connectTimeout(30)
            ->withOptions($guzzleOptions)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; FacturacionApp/1.0)',
                'Accept' => 'application/pdf,application/octet-stream,*/*',
            ])
            ->get($url);

        if (!$response->successful()) {
            Log::warning('HTTP no exitoso al descargar PDF de factura', [
                'url' => $url,
                'status' => $response->status(),
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            return null;
        }

        $body = $response->body();
        if ($body === '' || strncmp($body, '%PDF', 4) !== 0) {
            Log::warning('Respuesta al descargar PDF no es un archivo PDF', [
                'url' => $url,
                'bytes' => strlen($body),
            ]);

            return null;
        }

        return $body;
    } catch (\Throwable $e) {
        Log::warning('Excepción al descargar PDF de factura: ' . $e->getMessage(), ['url' => $url]);

        return null;
    }
}

/**
 * Calcular el monto total de las muestras pendientes de facturar
 * Usa el mismo método que en facturar para calcular precios con descuentos
 */
private function calcularMontoPendientes(): float
{
    try {
        // Obtener todas las muestras pendientes agrupadas por cotización
        $muestrasPendientes = CotioInstancia::with(['cotizacion'])
            ->where('facturado', false)
            ->where('enable_inform', true)
            ->where('cotio_subitem', 0)
            ->get()
            ->groupBy('cotio_numcoti');

        $montoTotal = 0.0;

        foreach ($muestrasPendientes as $cotiNum => $muestras) {
            try {
                $cotizacion = $muestras->first()->cotizacion;
                
                if (!$cotizacion) {
                    continue;
                }

                // Calcular resumen financiero para esta cotización (una sola vez)
                $tareas = $cotizacion->tareas()
                    ->orderBy('cotio_item')
                    ->orderBy('cotio_subitem')
                    ->get();
                $resumenFinanciero = $this->construirResumenFinanciero($cotizacion, $tareas);
                $muestrasTarifario = $resumenFinanciero['muestras'];
                $descuentoFactor = $resumenFinanciero['descuento_factor'];
                $aumentoFactor = $resumenFinanciero['aumento_factor'] ?? 0.0;

                // Calcular monto para cada muestra de esta cotización
                foreach ($muestras as $muestra) {
                    $item = $muestra->cotio_item;
                    
                    // Obtener precio unitario del tarifario
                    $precioBase = $muestrasTarifario[$item]['precio_unitario'] ?? 0.0;
                    
                    // Verificar que no se esté usando el subtotal en lugar del precio unitario
                    if (isset($muestrasTarifario[$item]['subtotal'])) {
                        $subtotal = $muestrasTarifario[$item]['subtotal'];
                        $cantidad = $muestrasTarifario[$item]['cantidad'] ?? 1;
                        // Si el precio base parece ser el subtotal, calcular el unitario
                        if ($precioBase > 0 && $cantidad > 1 && abs($precioBase - $subtotal) < 0.01) {
                            $precioBase = $subtotal / $cantidad;
                        }
                    }
                    
                    // Aplicar aumento y descuentos
                    $precioConAjustes = $this->aplicarAjustes($precioBase, $aumentoFactor, $descuentoFactor);
                    
                    $montoTotal += $precioConAjustes;
                }
            } catch (\Exception $e) {
                Log::warning('Error al calcular monto para cotización ' . $cotiNum . ': ' . $e->getMessage());
                continue;
            }
        }

        return round($montoTotal, 2);
    } catch (\Exception $e) {
        Log::error('Error al calcular monto de pendientes: ' . $e->getMessage());
        return 0.0;
    }
}





    public function guardarNotas(Request $request, $coti_num)
    {
        try {
            $cotizacion = Coti::findOrFail($coti_num);
            $cotizacion->update([
                'coti_notas_facturacion' => $request->input('observaciones')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notas guardadas correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar notas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateReferencias(Request $request, $coti_num)
    {
        try {
            $cotizacion = Coti::findOrFail($coti_num);
            
            $updateData = [];
            if ($request->has('coti_oc_referencia')) {
                $updateData['coti_oc_referencia'] = $request->input('coti_oc_referencia');
            }
            if ($request->has('coti_refs_facturacion_json')) {
                $updateData['coti_refs_facturacion_json'] = $request->input('coti_refs_facturacion_json');
            }

            if (!empty($updateData)) {
                $cotizacion->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Referencias actualizadas correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar referencias: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateNotasFactura(Request $request, $id)
    {
        try {
            $factura = Factura::findOrFail($id);
            $factura->update([
                'observaciones' => $request->input('observaciones')
            ]);
            
            if ($factura->pdf_url) {
                $filePath = storage_path('app/facturas/' . $factura->pdf_url);
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
                $factura->update(['pdf_url' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notas de la factura actualizadas correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar notas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function exportarIvaVentas(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        $fechaDesde = $request->fecha_desde;
        $fechaHasta = $request->fecha_hasta;

        $fileName = 'IVA_VENTAS_' . str_replace('-', '', $fechaDesde) . '_' . str_replace('-', '', $fechaHasta) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\IvaVentasExport($fechaDesde, $fechaHasta),
            $fileName
        );
    }
}
