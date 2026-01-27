<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\Ventas;
use App\Models\Coti;
use App\Models\Cotio;
use App\Models\CotioItems;
use App\Models\Clientes;
use App\Models\Matriz;
use App\Models\Divis;
use App\Models\CondicionPago;
use App\Models\ListaPrecio;
use App\Models\Metodo;
use App\Models\MetodoAnalisis;
use App\Models\MetodoMuestreo;
use App\Models\LeyNormativa;
use App\Models\ClienteEmpresaRelacionada;
use App\Models\ClienteRazonSocialFacturacion;
use App\Models\CotiVersion;

class VentasController extends Controller {
    
    /**
     * Helper para truncar y padear strings correctamente
     */
    private function truncateAndPad($value, $length, $padChar = ' ')
    {
        if (empty($value)) {
            return null;
        }
        return str_pad(substr($value, 0, $length), $length, $padChar, STR_PAD_RIGHT);
    }

    private function sanitizeNullableString($value, $length = null)
    {
        if (is_null($value)) {
            return null;
        }

        $sanitized = trim($value);

        if ($sanitized === '') {
            return null;
        }

        if (!is_null($length)) {
            return mb_substr($sanitized, 0, $length);
        }

        return $sanitized;
    }

    private function parseDecimalValue($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', $value);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }

            if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
                return (float) $matches[0];
            }
        }

        return null;
    }
    public function index(Request $request)
    {
        // Obtener clientes para el filtro
        $clientes = Clientes::where('cli_estado', true)
            ->orderBy('cli_razonsocial')
            ->get();
        
        // Construir query con filtros
        $query = Ventas::query();
        
        // Filtro por cliente
        if ($request->filled('cliente')) {
            $query->where('coti_codigocli', 'LIKE', $request->cliente . '%');
        }
        
        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('coti_estado', 'LIKE', $request->estado . '%');
        }
        
        // Filtro por fecha desde
        if ($request->filled('fecha_desde')) {
            $query->whereDate('coti_fechaalta', '>=', $request->fecha_desde);
        }
        
        // Filtro por fecha hasta
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('coti_fechaalta', '<=', $request->fecha_hasta);
        }
        
        // Ordenar y paginar
        $cotizaciones = $query->orderBy('coti_num', 'desc')
            ->paginate(20)
            ->withQueryString(); // Mantener filtros en la paginación

        // Calcular montos por estado
        Log::info('=== INICIO index ventas ===');
        Log::info('Estado filtro recibido: ' . ($request->get('estado') ?? 'ninguno'));
        
        $montosPorEstado = $this->calcularMontosPorEstado($request);
        
        Log::info('Montos calculados:', $montosPorEstado);
        
        // Determinar monto a mostrar según el filtro activo
        $estadoFiltro = $request->get('estado', '');
        $montoMostrar = $montosPorEstado['total'];
        
        if ($estadoFiltro == 'E') {
            $montoMostrar = $montosPorEstado['enEspera'];
            Log::info("Filtro E seleccionado, monto: {$montoMostrar}");
        } elseif ($estadoFiltro == 'A') {
            $montoMostrar = $montosPorEstado['aprobadas'];
            Log::info("Filtro A seleccionado, monto: {$montoMostrar}");
        } elseif ($estadoFiltro == 'P') {
            $montoMostrar = $montosPorEstado['enProceso'];
            Log::info("Filtro P seleccionado, monto: {$montoMostrar}");
        } elseif ($estadoFiltro == 'R') {
            $montoMostrar = $montosPorEstado['rechazadas'];
            Log::info("Filtro R seleccionado, monto: {$montoMostrar}");
        } else {
            Log::info("Sin filtro de estado, monto total: {$montoMostrar}");
        }

        Log::info("Monto a mostrar en vista: {$montoMostrar}");
        Log::info('=== FIN index ventas ===');

        return View::make('ventas.index', compact('cotizaciones', 'clientes', 'montosPorEstado', 'montoMostrar'));
    }

    /**
     * Calcula los montos totales de las cotizaciones agrupados por estado
     */
    private function calcularMontosPorEstado(Request $request): array
    {
        Log::info('=== INICIO calcularMontosPorEstado ===');
        Log::info('Filtros recibidos:', [
            'cliente' => $request->get('cliente'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ]);

        // Base query con filtros de fecha y cliente (si existen)
        $baseQuery = Ventas::query();
        
        if ($request->filled('cliente')) {
            $baseQuery->where('coti_codigocli', 'LIKE', $request->cliente . '%');
            Log::info("Filtro cliente aplicado: {$request->cliente}");
        }
        
        if ($request->filled('fecha_desde')) {
            $baseQuery->whereDate('coti_fechaalta', '>=', $request->fecha_desde);
            Log::info("Filtro fecha_desde aplicado: {$request->fecha_desde}");
        }
        
        if ($request->filled('fecha_hasta')) {
            $baseQuery->whereDate('coti_fechaalta', '<=', $request->fecha_hasta);
            Log::info("Filtro fecha_hasta aplicado: {$request->fecha_hasta}");
        }

        // Verificar cuántas cotizaciones hay en la query base
        $countBase = $baseQuery->count();
        Log::info("Cotizaciones en query base: {$countBase}");

        // Calcular montos por estado
        Log::info('--- Calculando monto TOTAL ---');
        $totalMonto = $this->calcularMontoTotalCotizaciones((clone $baseQuery));
        Log::info("Monto total calculado: {$totalMonto}");

        Log::info('--- Calculando monto EN ESPERA ---');
        $enEsperaMonto = $this->calcularMontoTotalCotizaciones((clone $baseQuery)->where('coti_estado', 'LIKE', 'E%'));
        Log::info("Monto en espera calculado: {$enEsperaMonto}");

        Log::info('--- Calculando monto APROBADAS ---');
        $aprobadasMonto = $this->calcularMontoTotalCotizaciones((clone $baseQuery)->where('coti_estado', 'LIKE', 'A%'));
        Log::info("Monto aprobadas calculado: {$aprobadasMonto}");

        Log::info('--- Calculando monto EN PROCESO ---');
        $enProcesoMonto = $this->calcularMontoTotalCotizaciones((clone $baseQuery)->where('coti_estado', 'LIKE', 'P%'));
        Log::info("Monto en proceso calculado: {$enProcesoMonto}");

        Log::info('--- Calculando monto RECHAZADAS ---');
        $rechazadasMonto = $this->calcularMontoTotalCotizaciones((clone $baseQuery)->where('coti_estado', 'LIKE', 'R%'));
        Log::info("Monto rechazadas calculado: {$rechazadasMonto}");

        $resultado = [
            'total' => $totalMonto,
            'enEspera' => $enEsperaMonto,
            'aprobadas' => $aprobadasMonto,
            'enProceso' => $enProcesoMonto,
            'rechazadas' => $rechazadasMonto,
        ];

        Log::info('=== FIN calcularMontosPorEstado ===', $resultado);
        
        return $resultado;
    }

    /**
     * Calcula el monto total de las cotizaciones en una query
     */
    private function calcularMontoTotalCotizaciones($query): float
    {
        try {
            $cotizaciones = $query->get();
            $montoTotal = 0.0;

            Log::info('=== INICIO CALCULO MONTO TOTAL ===');
            Log::info('Total cotizaciones encontradas: ' . $cotizaciones->count());

            foreach ($cotizaciones as $cotizacion) {
                Log::info("Procesando cotización #{$cotizacion->coti_num}");
                
                // Obtener muestras (cotio_subitem = 0) - tienen cantidad pero no precio directo
                // Nota: La tabla cotio NO tiene columna cotio_version, solo se filtra por cotio_numcoti
                $muestras = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                    ->where('cotio_subitem', 0)
                    ->get();

                Log::info("Muestras encontradas: {$muestras->count()}");
                if ($muestras->count() > 0) {
                    Log::info("Primera muestra: item={$muestras->first()->cotio_item}, cantidad={$muestras->first()->cotio_cantidad}");
                }

                // Obtener componentes (cotio_subitem > 0) - tienen precio, cantidad siempre es 1
                // Nota: La tabla cotio NO tiene columna cotio_version, solo se filtra por cotio_numcoti
                $componentes = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                    ->where('cotio_subitem', '>', 0)
                    ->get();

                Log::info("Componentes encontrados: {$componentes->count()}");
                if ($componentes->count() > 0) {
                    $primerComponente = $componentes->first();
                    Log::info("Primer componente: item={$primerComponente->cotio_item}, subitem={$primerComponente->cotio_subitem}, precio={$primerComponente->cotio_precio}");
                }

                // Agrupar componentes por muestra (cotio_item)
                $componentesPorMuestra = $componentes->groupBy(function ($componente) {
                    return (int) $componente->cotio_item;
                });

                Log::info("Grupos de componentes por muestra: {$componentesPorMuestra->count()}");

                // Calcular monto de cada muestra
                $subtotalMuestras = 0.0;
                foreach ($muestras as $muestra) {
                    $cantidadMuestra = $this->parseDecimalValue($muestra->cotio_cantidad ?? 1) ?? 1;
                    if ($cantidadMuestra <= 0) {
                        $cantidadMuestra = 1;
                    }

                    // Obtener componentes de esta muestra
                    $componentesMuestra = $componentesPorMuestra->get((int) $muestra->cotio_item, collect());
                    
                    Log::info("Muestra item={$muestra->cotio_item}: cantidad={$cantidadMuestra}, componentes={$componentesMuestra->count()}");
                    
                    // Sumar precios de los componentes (cantidad siempre es 1 para componentes)
                    $precioTotalComponentes = $componentesMuestra->sum(function ($componente) {
                        $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                        Log::debug("  Componente subitem={$componente->cotio_subitem}: precio={$precio}");
                        return $precio;
                    });

                    Log::info("  Precio total componentes: {$precioTotalComponentes}, cantidad muestra: {$cantidadMuestra}");
                    $subtotalMuestra = $precioTotalComponentes * $cantidadMuestra;
                    Log::info("  Subtotal muestra: {$subtotalMuestra}");
                    $subtotalMuestras += $subtotalMuestra;
                }

                Log::info("Subtotal muestras: {$subtotalMuestras}");

                // Calcular componentes sueltos (que no pertenecen a ninguna muestra)
                $muestraItems = $muestras->pluck('cotio_item')->map(function ($item) {
                    return (int) $item;
                });

                Log::info("Items de muestras: " . $muestraItems->implode(', '));

                $componentesSueltos = $componentes->filter(function ($componente) use ($muestraItems) {
                    return !$muestraItems->contains((int) $componente->cotio_item);
                });

                Log::info("Componentes sueltos encontrados: {$componentesSueltos->count()}");

                // Los componentes sueltos tienen precio y cantidad siempre es 1
                $subtotalComponentesSueltos = $componentesSueltos->sum(function ($componente) {
                    $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                    Log::debug("Componente suelto item={$componente->cotio_item}: precio={$precio}");
                    return $precio;
                });

                Log::info("Subtotal componentes sueltos: {$subtotalComponentesSueltos}");

                // Subtotal antes de descuentos
                $subtotal = $subtotalMuestras + $subtotalComponentesSueltos;
                Log::info("Subtotal antes de descuentos: {$subtotal}");

                // Aplicar descuento si existe
                $descuentoPorcentaje = max($this->calcularDescuentoCotizacion($cotizacion), 0);
                $descuentoMonto = $subtotal * ($descuentoPorcentaje / 100);
                $totalConDescuento = $subtotal - $descuentoMonto;

                Log::info("Descuento porcentaje: {$descuentoPorcentaje}%, monto: {$descuentoMonto}, total con descuento: {$totalConDescuento}");

                $montoTotal += max(0, $totalConDescuento);
                Log::info("Monto acumulado hasta ahora: {$montoTotal}");
            }

            $montoFinal = round($montoTotal, 2);
            Log::info("=== FIN CALCULO MONTO TOTAL: {$montoFinal} ===");
            
            return $montoFinal;
        } catch (\Exception $e) {
            Log::error('Error al calcular monto total de cotizaciones: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 0.0;
        }
    }

    public function create() 
    {
        // Cargar datos para los selectores
        try {
            $matrices = Matriz::orderBy('matriz_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando matrices:', ['error' => $e->getMessage()]);
            $matrices = collect();
        }

        try {
            $sectores = Divis::orderBy('divis_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores:', ['error' => $e->getMessage()]);
            $sectores = collect();
        }

        try {
            $condicionesPago = CondicionPago::where('pag_estado', true)
                ->orderBy('pag_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando condiciones de pago:', ['error' => $e->getMessage()]);
            $condicionesPago = collect();
        }

        try {
            $sectoresCliente = Divis::where('divis_lab', true)
                ->orderBy('divis_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores de cliente:', ['error' => $e->getMessage()]);
            $sectoresCliente = collect();
        }

        try {
            $listasPrecios = ListaPrecio::where('lp_estado', true)
                ->orderBy('lp_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando listas de precios:', ['error' => $e->getMessage()]);
            $listasPrecios = collect([
                (object)['lp_codigo' => 'UNO  ', 'lp_descripcion' => 'Lista Principal'],
                (object)['lp_codigo' => 'DOS  ', 'lp_descripcion' => 'Lista Secundaria'],
            ]);
        }

        $cotizacionConfig = [
            'modo' => 'create',
            'puedeEditar' => true,
            'ensayosIniciales' => [],
            'componentesIniciales' => [],
        ];

        return View::make('ventas.create', compact(
            'matrices',
            'sectores',
            'condicionesPago',
            'listasPrecios',
            'cotizacionConfig',
            'sectoresCliente'
        ));
    }

    public function store(Request $request)
    {
        try {
            Log::info('=== INICIO CREACIÓN DE COTIZACIÓN ===');
            Log::info('Datos recibidos en store:', $request->all());

            // Validar datos básicos
            Log::info('Iniciando validación de datos básicos');
            Log::info('Campos para validación:', [
                'coti_codigocli' => $request->coti_codigocli,
                'coti_fechaalta' => $request->coti_fechaalta,
            ]);
            
            $request->validate([
                'coti_codigocli' => 'required|string',
                'coti_fechaalta' => 'required|date',
            ], [
                'coti_codigocli.required' => 'El código de cliente es obligatorio',
                'coti_fechaalta.required' => 'La fecha de alta es obligatoria',
            ]);
            
            Log::info('Validación completada exitosamente');

            // Generar número de cotización automático
            Log::info('Generando número de cotización');
            $ultimaCotizacion = Ventas::orderBy('coti_num', 'desc')->first();
            $nuevoNumero = $ultimaCotizacion ? intval($ultimaCotizacion->coti_num) + 1 : 1;
            Log::info('Número de cotización generado:', ['numero' => $nuevoNumero]);

            // Crear la cotización
            Log::info('Creando instancia de cotización');
            $cotizacion = new Ventas();

            // Obtener datos del cliente
            Log::info('Obteniendo datos del cliente');
            $codigoCliente = $this->truncateAndPad($request->coti_codigocli, 10);
            $cliente = Clientes::where('cli_codigo', $codigoCliente)
                ->where('cli_estado', true)
                ->first();
                
            if (!$cliente) {
                Log::error('Cliente no encontrado:', ['codigo' => $request->coti_codigocli]);
                throw new \Exception('Cliente no encontrado con código: ' . $request->coti_codigocli);
            }
            
            Log::info('Cliente encontrado:', [
                'codigo' => trim($cliente->cli_codigo),
                'razon_social' => trim($cliente->cli_razonsocial),
                'condicion_pago' => $cliente->cli_codigopag ? trim($cliente->cli_codigopag) : null,
                'lista_precios' => $cliente->cli_codigolp ? trim($cliente->cli_codigolp) : null,
            ]);

            // Campos principales
            Log::info('Asignando campos principales');
            $cotizacion->coti_num = $nuevoNumero;
            $cotizacion->coti_para = $this->sanitizeNullableString($request->coti_para, null);
            $cotizacion->coti_cli_empresa = $request->coti_cli_empresa ? (int)$request->coti_cli_empresa : null;
            $cotizacion->coti_descripcion = $request->coti_descripcion;
            $cotizacion->coti_codigocli = $codigoCliente;
            $cotizacion->coti_fechaalta = $request->coti_fechaalta ?: now()->format('Y-m-d');
            $cotizacion->coti_fechafin = $request->coti_fechafin;
            // Sector (se valida más adelante contra divis)
            $cotizacion->coti_sector = null;
            // Mapear estados del formulario a códigos de BD
            $estadosMap = [
                'En Espera' => 'E    ',
                'Aprobado' => 'A    ',
                'Rechazado' => 'R    ',
                'En Proceso' => 'P    ',
            ];
            
            $estadoFormulario = $request->coti_estado ?: 'En Espera';
            $cotizacion->coti_estado = $estadosMap[$estadoFormulario] ?? 'E    ';
            
            Log::info('Estado mapeado:', [
                'estado_formulario' => $estadoFormulario,
                'estado_bd' => $cotizacion->coti_estado
            ]);
            // Campo coti_vigencia eliminado - no existe en la tabla

            // Campos de gestión
            Log::info('Asignando campos de gestión');
            $cotizacion->coti_responsable = $this->truncateAndPad($request->coti_responsable, 20);
            $cotizacion->coti_aprobo = $this->truncateAndPad($request->coti_aprobo, 20);
            $cotizacion->coti_fechaaprobado = $request->coti_fechaaprobado;
            $cotizacion->coti_fechaencurso = $request->coti_fechaencurso;
            $cotizacion->coti_fechaaltatecnica = $request->coti_fechaaltatecnica;

            // Campos técnicos - Solo asignar campos que existen en la tabla
            Log::info('Asignando campos técnicos');
            Log::info('Valores técnicos recibidos:', [
                'coti_codigomatriz' => $request->coti_codigomatriz,
            ]);
            
            // Solo asignar coti_codigomatriz que sí existe en la tabla
            $cotizacion->coti_codigomatriz = $this->truncateAndPad($request->coti_codigomatriz, 15);
            
            Log::info('Valores técnicos asignados:', [
                'coti_codigomatriz' => "'" . $cotizacion->coti_codigomatriz . "'",
            ]);

            // Campos de empresa/cliente (usar datos del cliente como base)
            Log::info('=== ASIGNANDO CAMPOS DE EMPRESA ===');
            Log::info('Datos de empresa recibidos:', [
                'coti_empresa' => $request->coti_empresa,
                'coti_establecimiento' => $request->coti_establecimiento,
                'coti_contacto' => $request->coti_contacto,
                'coti_direccioncli' => $request->coti_direccioncli,
                'coti_localidad' => $request->coti_localidad,
                'coti_partido' => $request->coti_partido,
                'coti_cuit' => $request->coti_cuit,
                'coti_codigopostal' => $request->coti_codigopostal,
                'coti_telefono' => $request->coti_telefono,
            ]);
            
            // Usar datos del formulario si están presentes, sino usar datos del cliente
            // Aplicar truncamiento a campos con límites de caracteres
            $cotizacion->coti_empresa = $this->sanitizeNullableString(
                $request->coti_empresa ?: trim($cliente->cli_razonsocial),
                50
            );
            $cotizacion->coti_establecimiento = $this->sanitizeNullableString(
                $request->coti_establecimiento,
                50
            );
            $cotizacion->coti_contacto = $this->sanitizeNullableString(
                $request->coti_contacto,
                120
            ) ?: ($cliente->cli_contacto ? trim($cliente->cli_contacto) : null);
            $cotizacion->coti_direccioncli = $this->sanitizeNullableString(
                $request->coti_direccioncli ?: 
                ($cliente->cli_direccion ? trim($cliente->cli_direccion) : null),
                50
            );
            $cotizacion->coti_localidad = $this->sanitizeNullableString(
                $request->coti_localidad ?: 
                ($cliente->cli_localidad ? trim($cliente->cli_localidad) : null),
                50
            );
            $cotizacion->coti_partido = $this->sanitizeNullableString($request->coti_partido, 50);
            $cotizacion->coti_cuit = $this->sanitizeNullableString($request->coti_cuit ?: $cliente->cli_cuit, 13);
            $cotizacion->coti_codigopostal = $this->sanitizeNullableString(
                $request->coti_codigopostal ?: 
                ($cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : null),
                10
            );
            $cotizacion->coti_telefono = $request->coti_telefono ?: $cliente->cli_telefono;
            $cotizacion->coti_mail1 = $this->sanitizeNullableString(
                $request->coti_mail1,
                120
            ) ?: ($cliente->cli_email ? trim($cliente->cli_email) : null);
            $sectorFormulario = $this->sanitizeNullableString($request->coti_sector, 4);
            $sectorCliente = $this->sanitizeNullableString($cliente->cli_codigocrub ?? null, 4);

            $sectorCandidato = null;
            if ($sectorFormulario) {
                $sectorFormulario = $this->truncateAndPad($sectorFormulario, 4);
                if (Divis::where('divis_codigo', $sectorFormulario)->exists()) {
                    $sectorCandidato = $sectorFormulario;
                }
            }

            if (!$sectorCandidato && $sectorCliente) {
                $sectorCliente = $this->truncateAndPad($sectorCliente, 4);
                if (Divis::where('divis_codigo', $sectorCliente)->exists()) {
                    $sectorCandidato = $sectorCliente;
                }
            }

            $cotizacion->coti_sector = $sectorCandidato;
            
            Log::info('Campos de empresa asignados (con datos del cliente):', [
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_cuit' => $cotizacion->coti_cuit,
            ]);

            // Campos adicionales - Solo campos que existen en la tabla
            Log::info('Asignando campos adicionales');
            $cotizacion->coti_referencia_tipo = $this->truncateAndPad($request->coti_referencia_tipo, 30);
            $cotizacion->coti_referencia_valor = $this->sanitizeNullableString($request->coti_referencia_valor, 120);
            $cotizacion->coti_oc_referencia = $this->sanitizeNullableString($request->coti_oc_referencia, 120);
            $cotizacion->coti_hes_has_tipo = $this->truncateAndPad($request->coti_hes_has_tipo, 10);
            $cotizacion->coti_hes_has_valor = $this->sanitizeNullableString($request->coti_hes_has_valor, 120);
            $cotizacion->coti_gr_contrato_tipo = $this->truncateAndPad($request->coti_gr_contrato_tipo, 30);
            $cotizacion->coti_gr_contrato = $this->sanitizeNullableString($request->coti_gr_contrato, 120);
            $cotizacion->coti_otro_referencia = $this->sanitizeNullableString($request->coti_otro_referencia, 120);
            $cotizacion->coti_notas = $request->coti_notas;
            $cotizacion->coti_codigosuc = $this->truncateAndPad($request->coti_codigosuc, 10);
            
            // Campos de descuentos
            $cotizacion->coti_descuentoglobal = $request->filled('descuento') ? floatval($request->descuento) : 0.00;
            $cotizacion->coti_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje') ? floatval($request->sector_laboratorio_porcentaje) : 0.00;
            $cotizacion->coti_sector_higiene_pct = $request->filled('sector_higiene_porcentaje') ? floatval($request->sector_higiene_porcentaje) : 0.00;
            $cotizacion->coti_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje') ? floatval($request->sector_microbiologia_porcentaje) : 0.00;
            $cotizacion->coti_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje') ? floatval($request->sector_cromatografia_porcentaje) : 0.00;
            $cotizacion->coti_sector_laboratorio_contacto = $this->sanitizeNullableString($request->sector_laboratorio_contacto, 100);
            $cotizacion->coti_sector_higiene_contacto = $this->sanitizeNullableString($request->sector_higiene_contacto, 100);
            $cotizacion->coti_sector_microbiologia_contacto = $this->sanitizeNullableString($request->sector_microbiologia_contacto, 100);
            $cotizacion->coti_sector_cromatografia_contacto = $this->sanitizeNullableString($request->sector_cromatografia_contacto, 100);
            $cotizacion->coti_sector_laboratorio_observaciones = $this->sanitizeNullableString($request->sector_laboratorio_observaciones);
            $cotizacion->coti_sector_higiene_observaciones = $this->sanitizeNullableString($request->sector_higiene_observaciones);
            $cotizacion->coti_sector_microbiologia_observaciones = $this->sanitizeNullableString($request->sector_microbiologia_observaciones);
            $cotizacion->coti_sector_cromatografia_observaciones = $this->sanitizeNullableString($request->sector_cromatografia_observaciones);
            
            // Campos de cadena de custodia y muestreo
            $cotizacion->coti_cadena_custodia = $request->has('coti_cadena_custodia') && $request->coti_cadena_custodia == '1';
            $cotizacion->coti_muestreo = $request->has('coti_muestreo') && $request->coti_muestreo == '1';
            
            // Campos financieros eliminados - no existen en la tabla real
            Log::info('=== CAMPOS FINANCIEROS OMITIDOS ===');
            Log::info('Los siguientes campos no existen en la tabla: coti_abono, coti_importe, coti_usos, coti_codigopag, coti_codigolp, coti_nroprecio');

            Log::info('=== PREPARANDO PARA GUARDAR COTIZACIÓN ===');
            Log::info('Datos finales de la cotización antes de save:', [
                'coti_num' => $cotizacion->coti_num . ' (tipo: ' . gettype($cotizacion->coti_num) . ')',
                'coti_descripcion' => $cotizacion->coti_descripcion,
                'coti_codigocli' => "'" . $cotizacion->coti_codigocli . "'",
                'coti_fechaalta' => $cotizacion->coti_fechaalta,
                'coti_estado' => "'" . $cotizacion->coti_estado . "'",
                'coti_codigomatriz' => $cotizacion->coti_codigomatriz ? "'" . $cotizacion->coti_codigomatriz . "'" : 'NULL',
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_cuit' => $cotizacion->coti_cuit,
                'coti_codigosuc' => $cotizacion->coti_codigosuc ? "'" . $cotizacion->coti_codigosuc . "'" : 'NULL'
            ]);

            // Verificar que todos los campos requeridos estén presentes
            Log::info('Verificando campos requeridos:');
            if (!$cotizacion->coti_num) {
                Log::error('ERROR: coti_num está vacío');
            }
            if (!$cotizacion->coti_codigocli) {
                Log::error('ERROR: coti_codigocli está vacío');
            }
            if (!$cotizacion->coti_fechaalta) {
                Log::error('ERROR: coti_fechaalta está vacío');
            }

            Log::info('Ejecutando save()...');
            try {
                $result = $cotizacion->save();
                Log::info('Save() ejecutado exitosamente', ['result' => $result]);
                Log::info('ID de cotización guardada:', ['coti_num' => $cotizacion->coti_num]);
            } catch (\Exception $saveException) {
                Log::error('ERROR EN SAVE():', [
                    'message' => $saveException->getMessage(),
                    'file' => $saveException->getFile(),
                    'line' => $saveException->getLine(),
                    'trace' => $saveException->getTraceAsString()
                ]);
                throw $saveException;
            }

            Log::info('Cotización creada exitosamente', ['numero' => $cotizacion->coti_num]);
            
            // Establecer versión inicial
            $cotizacion->coti_version = 1;
            $cotizacion->save();
            
            // Procesar ensayos y componentes
            $this->procesarEnsayosYComponentes($request, $cotizacion->coti_num);
            
            // IMPORTANTE: Guardar versión 1 en coti_versions DESPUÉS de procesar los items
            // para que los items ya estén guardados en la tabla cotio
            Log::info('Guardando versión 1 en coti_versions');
            
            // Obtener todos los datos de la cotización desde la BD
            $cotizacion->refresh(); // Refrescar para obtener todos los campos actualizados
            $cotiData = $cotizacion->getAttributes();
            
            // Asegurar que el sector tenga el formato correcto antes de guardar
            if (isset($cotiData['coti_sector'])) {
                $sectorValue = $cotiData['coti_sector'];
                if ($sectorValue !== null && trim($sectorValue) !== '') {
                    $cotiData['coti_sector'] = $this->truncateAndPad(trim($sectorValue), 4);
                } else {
                    $cotiData['coti_sector'] = null;
                }
            } else {
                $cotiData['coti_sector'] = null;
            }
            
            // Obtener todos los items desde la BD (ya guardados por procesarEnsayosYComponentes)
            $cotioItemsRaw = DB::table('cotio')
                ->where('cotio_numcoti', $cotizacion->coti_num)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();
            
            // Convertir a array asociativo con todos los campos
            $cotioItems = $cotioItemsRaw->map(function($item) {
                return (array) $item;
            })->toArray();
            
            // Log detallado para debugging
            $ensayosCount = collect($cotioItems)->where('cotio_subitem', 0)->count();
            $componentesCount = collect($cotioItems)->where('cotio_subitem', '>', 0)->count();
            
            Log::info('Guardando versión 1 en coti_versions', [
                'coti_num' => $cotizacion->coti_num,
                'cotio_items_total' => count($cotioItems),
                'ensayos_count' => $ensayosCount,
                'componentes_count' => $componentesCount,
                'cotio_items_sample' => array_slice($cotioItems, 0, 3)
            ]);
            
            // Guardar versión 1
            CotiVersion::create([
                'coti_num' => $cotizacion->coti_num,
                'version' => 1,
                'fecha_version' => now(),
                'coti_data' => $cotiData,
                'cotio_data' => $cotioItems,
            ]);
            
            Log::info('Versión 1 guardada exitosamente en coti_versions');
            
            Log::info('=== FIN CREACIÓN DE COTIZACIÓN EXITOSA ===');

            return redirect()->route('ventas.index')
                ->with('success', 'Cotización creada exitosamente con número: ' . $cotizacion->coti_num);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERROR DE VALIDACIÓN EN COTIZACIÓN ===', [
                'errors' => $e->validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERROR AL CREAR COTIZACIÓN ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error al crear la cotización: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id, Request $request)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }
        
        $cotizacion = Ventas::find($id);
        if (!$cotizacion) {
            abort(404, 'Cotización not found');
        }
        
        // Verificar si se solicita una versión específica
        $versionSolicitada = $request->get('version');
        $ensayos = collect();
        $componentes = collect();
        
        if ($versionSolicitada && $versionSolicitada != $cotizacion->coti_version) {
            // Cargar versión histórica
            $versionHistorica = CotiVersion::where('coti_num', $id)
                ->where('version', $versionSolicitada)
                ->first();
            
            if ($versionHistorica) {
                // Actualizar datos de la cotización con los de la versión
                $cotizacion->fill($versionHistorica->coti_data);
                $cotizacion->coti_num = $id; // Mantener el número original
                
                // Asegurar que el sector tenga el formato correcto (4 caracteres con padding)
                // Si es null, mantenerlo como null explícitamente
                if ($cotizacion->coti_sector !== null && trim($cotizacion->coti_sector) !== '') {
                    $cotizacion->coti_sector = $this->truncateAndPad(trim($cotizacion->coti_sector), 4);
                } else {
                    $cotizacion->coti_sector = null;
        }

                // Procesar items de cotio
                // IMPORTANTE: Limpiar las colecciones antes de cargar items de la versión histórica
                $ensayos = collect();
                $componentes = collect();
                
                // Obtener cotio_data y decodificarlo si es necesario
                $cotioDataRaw = $versionHistorica->cotio_data ?? [];
                
                // Decodificar cotio_data si es string JSON
                if (is_string($cotioDataRaw)) {
                    $cotioData = json_decode($cotioDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando cotio_data en edit', [
                            'coti_num' => $id,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg()
                        ]);
                        $cotioData = [];
                    }
                } else {
                    $cotioData = $cotioDataRaw ?? [];
                }
                
                // Asegurar que cotioData sea un array
                if (!is_array($cotioData)) {
                    $cotioData = [];
                }
                
                // Log para debugging
                $ensayosCount = collect($cotioData)->where('cotio_subitem', 0)->count();
                $componentesCount = collect($cotioData)->where('cotio_subitem', '>', 0)->count();
                
                Log::info('Cargando versión histórica en edit', [
                    'coti_num' => $id,
                    'version_solicitada' => $versionSolicitada,
                    'cotio_data_count' => count($cotioData),
                    'ensayos_count' => $ensayosCount,
                    'componentes_count' => $componentesCount,
                    'cotio_data_sample' => array_slice($cotioData, 0, 3)
                ]);
                
                foreach ($cotioData as $itemData) {
                    // Asegurar que itemData sea un array
                    if (!is_array($itemData)) {
                        continue;
                    }
                    
                    $subitem = isset($itemData['cotio_subitem']) ? (int)$itemData['cotio_subitem'] : -1;
                    
                    if ($subitem == 0) {
                        // Es un ensayo
                        $ensayo = new Cotio();
                        $ensayo->fill($itemData);
                        $ensayos->push($ensayo);
                    } else if ($subitem > 0) {
                        // Es un componente
                        $componente = new Cotio();
                        $componente->fill($itemData);
                        $componentes->push($componente);
                    }
                }
                
                Log::info('Items procesados en edit', [
                    'ensayos_count' => $ensayos->count(),
                    'componentes_count' => $componentes->count()
                ]);
            }
        }
        
        // Si no hay versión solicitada o no se encontró, cargar versión actual
        if ($ensayos->isEmpty() && $componentes->isEmpty()) {
        // Cargar items de la cotización (ensayos y componentes) con relaciones
        $ensayos = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', 0)
            ->orderBy('cotio_item')
            ->get();
        
        // Cargar componentes con el método (cotio_codigometodo apunta a tabla metodo)
        $componentes = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_item')
            ->orderBy('cotio_subitem')
            ->get();
        }
        
        // Cargar datos para los selectores
        try {
            $matrices = Matriz::orderBy('matriz_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando matrices:', ['error' => $e->getMessage()]);
            $matrices = collect();
        }

        try {
            $sectoresCliente = Divis::where('divis_lab', true)
                ->orderBy('divis_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores de cliente:', ['error' => $e->getMessage()]);
            $sectoresCliente = collect();
        }

        // Ahora siempre permitimos editar la cotización, incluso si está aprobada.
        $puedeEditar = true;

        $agrupadoresCatalogo = CotioItems::muestras()
            ->with(['componentesAsociados', 'matrices'])
            ->get()
            ->keyBy(function ($item) {
                return Str::lower(trim($item->cotio_descripcion));
            });

        $ensayosIniciales = $ensayos->map(function ($ensayo) use ($componentes, $agrupadoresCatalogo) {
            $cantidad = $ensayo->cotio_cantidad ?? 1;
            $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);
            $precioUnitario = $componentesDelEnsayo->sum(function ($comp) {
                $precio = $comp->cotio_precio ?? 0;
                $cantidad = $comp->cotio_cantidad ?? 1;
                return $precio * $cantidad;
            });

            $descripcionClave = Str::lower(trim($ensayo->cotio_descripcion ?? ''));
            $agrupador = $agrupadoresCatalogo->get($descripcionClave);

            // Obtener matriz desde la tabla pivote o desde matriz_codigo directo
            $matrizCodigo = null;
            $matrizDescripcion = null;
            
            if ($agrupador) {
                if ($agrupador->matrices->isNotEmpty()) {
                    $matriz = $agrupador->matrices->first();
                    $matrizCodigo = $matriz->matriz_codigo;
                    $matrizDescripcion = $matriz->matriz_descripcion;
                } elseif ($agrupador->matriz_codigo) {
                    // Fallback: usar matriz_codigo directo si existe
                    $matrizCodigo = trim($agrupador->matriz_codigo);
                    $matriz = \App\Models\Matriz::where('matriz_codigo', $matrizCodigo)->first();
                    $matrizDescripcion = $matriz ? trim($matriz->matriz_descripcion) : null;
                }
            }

            return [
                'item' => (int) $ensayo->cotio_item,
                'muestra_id' => $agrupador?->id,
                'descripcion' => $ensayo->cotio_descripcion,
                'codigo' => $agrupador ? str_pad($agrupador->id, 15, '0', STR_PAD_LEFT) : ($ensayo->cotio_codigoprod ?? ''),
                'cantidad' => (float) $cantidad,
                'precio' => (float) $precioUnitario,
                'total' => (float) ($precioUnitario * $cantidad),
                'tipo' => 'ensayo',
                'componentes_sugeridos' => $agrupador ? $agrupador->componentesAsociados->pluck('id')->values()->all() : [],
                'nota_tipo' => $ensayo->cotio_nota_tipo ?? null,
                'nota_contenido' => $ensayo->cotio_nota_contenido ?? null,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
            ];
        })->values();

        $componentesIniciales = [];
        $contadorComponentes = 0;
        $maxItemEnsayo = (int) ($ensayos->max('cotio_item') ?? 0);
        foreach ($componentes as $componente) {
            $contadorComponentes++;
            $metodoTexto = '-';

            if ($componente->cotio_codigometodo) {
                $metodoCodigo = trim($componente->cotio_codigometodo);
                $metodo = Metodo::where('metodo_codigo', $metodoCodigo)->first();
                $metodoTexto = $metodo
                    ? $metodo->metodo_codigo . ' - ' . ($metodo->metodo_descripcion ?? '')
                    : $metodoCodigo;
            } elseif ($componente->cotio_codigometodo_analisis) {
                $metodoCodigo = trim($componente->cotio_codigometodo_analisis);
                $metodoAnalisis = MetodoAnalisis::where('codigo', $metodoCodigo)->first();
                $metodoTexto = $metodoAnalisis
                    ? $metodoAnalisis->codigo . ' - ' . ($metodoAnalisis->nombre ?? $metodoAnalisis->descripcion ?? '')
                    : $metodoCodigo;
            }

            // Buscar el analisis_id del componente en el catálogo
            $analisisId = null;
            $codigoProducto = trim($componente->cotio_codigoprod ?? '');
            $descripcionComponente = trim($componente->cotio_descripcion ?? '');
            
            if ($codigoProducto) {
                // Intentar buscar por ID (el código puede ser el ID del componente)
                $codigoLimpio = trim($codigoProducto);
                // Si el código parece ser un número, buscar por ID
                if (is_numeric($codigoLimpio)) {
                    $componenteCatalogo = CotioItems::componentes()->find($codigoLimpio);
                    if ($componenteCatalogo) {
                        $analisisId = $componenteCatalogo->id;
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción exacta
                if (!$analisisId && $descripcionComponente) {
                    $componenteCatalogo = CotioItems::componentes()
                        ->where('cotio_descripcion', $descripcionComponente)
                        ->first();
                    if ($componenteCatalogo) {
                        $analisisId = $componenteCatalogo->id;
                    }
                }
            } elseif ($descripcionComponente) {
                // Si no hay código, buscar solo por descripción
                $componenteCatalogo = CotioItems::componentes()
                    ->where('cotio_descripcion', $descripcionComponente)
                    ->first();
                if ($componenteCatalogo) {
                    $analisisId = $componenteCatalogo->id;
                }
            }

            $componentesIniciales[] = [
                'item' => $maxItemEnsayo + $contadorComponentes,
                'analisis_id' => $analisisId,
                'descripcion' => $componente->cotio_descripcion,
                'codigo' => $componente->cotio_codigoprod ?? '',
                'cantidad' => (float) ($componente->cotio_cantidad ?? 1),
                'precio' => (float) ($componente->cotio_precio ?? 0),
                'total' => (float) (($componente->cotio_precio ?? 0) * ($componente->cotio_cantidad ?? 1)),
                'tipo' => 'componente',
                'ensayo_asociado' => (int) $componente->cotio_item,
                'metodo_analisis_id' => $componente->cotio_codigometodo_analisis ? trim($componente->cotio_codigometodo_analisis) : null,
                'metodo_codigo' => $componente->cotio_codigometodo ? trim($componente->cotio_codigometodo) : null,
                'metodo_descripcion' => $metodoTexto,
                'unidad_medida' => $componente->cotio_codigoum ? trim($componente->cotio_codigoum) : null,
                'limite_deteccion' => $componente->limite_deteccion ?? null,
                'ley_normativa_id' => null,
                'nota_tipo' => $componente->cotio_nota_tipo ?? null,
                'nota_contenido' => $componente->cotio_nota_contenido ?? null,
            ];
        }

        $cotizacionConfig = [
            'modo' => 'edit',
            'puedeEditar' => $puedeEditar,
            'ensayosIniciales' => $ensayosIniciales,
            'componentesIniciales' => $componentesIniciales,
        ];
        
        $descuentoCliente = $this->calcularDescuentoCotizacion($cotizacion);
        
        // Prioridad: primero descuentos de la cotización, luego del cliente
        $descuentoGlobalCliente = 0.0;
        if (isset($cotizacion->coti_descuentoglobal) && $cotizacion->coti_descuentoglobal > 0) {
            $descuentoGlobalCliente = (float) $cotizacion->coti_descuentoglobal;
        } elseif ($cotizacion->cliente) {
            $descuentoGlobalCliente = (float) ($cotizacion->cliente->cli_descuentoglobal ?? 0);
        }
        
        $sectorCodigoOriginal = $cotizacion->coti_sector ?? optional($cotizacion->cliente)->cli_codigocrub;
        $sectorCodigo = $this->normalizarCodigoSector($sectorCodigoOriginal);
        $descuentoSectorAplicado = 0.0;
        if ($sectorCodigo) {
            $descuentoSectorAplicado = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
        }
        if ($descuentoSectorAplicado == 0.0 && $cotizacion->cliente) {
            $descuentoSectorAplicado = $this->obtenerDescuentoSector($cotizacion->cliente, $sectorCodigo);
        }
        
        $sectorEtiqueta = trim(optional($cotizacion->sector)->divis_descripcion ?? $cotizacion->coti_sector ?? '');

        return View::make('ventas.edit', compact(
            'cotizacion',
            'matrices',
            'ensayos',
            'componentes',
            'puedeEditar',
            'cotizacionConfig',
            'descuentoCliente',
            'descuentoGlobalCliente',
            'descuentoSectorAplicado',
            'sectorEtiqueta',
            'sectoresCliente'
        ));
    }
    
    public function destroy($id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }
        
        try {
            $cotizacion = Ventas::find($id);
            if (!$cotizacion) {
                return redirect()->route('ventas.index', request()->query())
                    ->with('error', 'Cotización no encontrada');
            }
            
            $cotizacion->delete();
            
            // Preservar los filtros activos en la redirección
            return redirect()->route('ventas.index', request()->query())
                ->with('success', 'Cotización eliminada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al eliminar cotización:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('ventas.index', request()->query())
                ->with('error', 'Error al eliminar la cotización');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $cotizacion = Ventas::find($id);
            
            if (!$cotizacion) {
                return redirect()->route('ventas.index')
                    ->with('error', 'Cotización no encontrada');
            }
            
            // IMPORTANTE: Guardar versión histórica ANTES de actualizar
            // Debemos guardar el estado ACTUAL (anterior) de la cotización, no el nuevo
            $versionActual = (int)($cotizacion->coti_version ?? 1);
            
            // Obtener todos los datos ACTUALES de la cotización desde la BD (antes de actualizar)
            $cotiData = $cotizacion->getAttributes();
            
            // Asegurar que el sector tenga el formato correcto antes de guardar
            // Si es null o vacío, mantenerlo como null explícitamente
            if (isset($cotiData['coti_sector'])) {
                $sectorValue = $cotiData['coti_sector'];
                if ($sectorValue !== null && trim($sectorValue) !== '') {
                    $cotiData['coti_sector'] = $this->truncateAndPad(trim($sectorValue), 4);
                } else {
                    $cotiData['coti_sector'] = null;
                }
            } else {
                $cotiData['coti_sector'] = null;
            }
            
            // IMPORTANTE: Obtener los items ACTUALES desde la BD (no desde el formulario)
            // Estos son los items que existían ANTES de la actualización
            // Usar DB::table para obtener TODOS los campos, no solo los fillable
            $cotioItemsRaw = DB::table('cotio')
                ->where('cotio_numcoti', $cotizacion->coti_num)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();
            
            // Convertir a array asociativo con todos los campos
            $cotioItems = $cotioItemsRaw->map(function($item) {
                return (array) $item;
            })->toArray();
            
            // Log detallado para debugging
            $ensayosCount = collect($cotioItems)->where('cotio_subitem', 0)->count();
            $componentesCount = collect($cotioItems)->where('cotio_subitem', '>', 0)->count();
            
            Log::info('Guardando versión histórica ANTES de actualizar', [
                'coti_num' => $cotizacion->coti_num,
                'version_actual' => $versionActual,
                'cotio_items_total' => count($cotioItems),
                'ensayos_count' => $ensayosCount,
                'componentes_count' => $componentesCount,
                'cotio_items_sample' => array_slice($cotioItems, 0, 3)
            ]);
            
            // Verificar si ya existe esta versión (puede existir si se guardó al crear la cotización)
            $versionExistente = CotiVersion::where('coti_num', $cotizacion->coti_num)
                ->where('version', $versionActual)
                ->first();
            
            if ($versionExistente) {
                // Si ya existe, actualizarla con los datos actuales
                Log::info('Versión ya existe, actualizando', [
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual
                ]);
                $versionExistente->update([
                    'fecha_version' => now(),
                    'coti_data' => $cotiData,
                    'cotio_data' => $cotioItems,
                ]);
            } else {
                // Si no existe, crearla
                Log::info('Versión no existe, creando nueva', [
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual
                ]);
                CotiVersion::create([
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual,
                    'fecha_version' => now(),
                    'coti_data' => $cotiData,
                    'cotio_data' => $cotioItems,
                ]);
            }
            
            // Actualizar campos principales
            $cotizacion->coti_descripcion = $request->coti_descripcion;
            $cotizacion->coti_para = $this->sanitizeNullableString($request->coti_para, null);
            $cotizacion->coti_cli_empresa = $request->coti_cli_empresa ? (int)$request->coti_cli_empresa : null;
            $cotizacion->coti_codigocli = $this->truncateAndPad($request->coti_codigocli, 10);
            $cotizacion->coti_fechaalta = $request->coti_fechaalta;
            $cotizacion->coti_fechafin = $request->coti_fechafin;
            
            // Mapear estado
            $estadosMap = [
                'E' => 'E    ',
                'A' => 'A    ',
                'R' => 'R    ',
                'P' => 'P    ',
            ];
            
            $estadoFormulario = $request->coti_estado ?: 'E';
            $cotizacion->coti_estado = $estadosMap[$estadoFormulario] ?? 'E    ';
            
            // Campos técnicos
            $cotizacion->coti_codigomatriz = $this->truncateAndPad($request->coti_codigomatriz, 15);
            $cotizacion->coti_codigosuc = $this->truncateAndPad($request->coti_codigosuc, 10);
            
            // Campos de gestión
            $cotizacion->coti_responsable = $this->truncateAndPad($request->coti_responsable, 20);
            $cotizacion->coti_aprobo = $this->truncateAndPad($request->coti_aprobo, 20);
            $cotizacion->coti_fechaaprobado = $request->coti_fechaaprobado;
            $cotizacion->coti_fechaencurso = $request->coti_fechaencurso;
            $cotizacion->coti_fechaaltatecnica = $request->coti_fechaaltatecnica;
            
            // Campos de empresa (aplicar truncamiento a campos con límites de caracteres)
            $cotizacion->coti_empresa = $this->sanitizeNullableString($request->coti_empresa, 50);
            $cotizacion->coti_establecimiento = $this->sanitizeNullableString($request->coti_establecimiento, 50);
            $cotizacion->coti_contacto = $this->sanitizeNullableString($request->coti_contacto, 120);
            $cotizacion->coti_direccioncli = $this->sanitizeNullableString($request->coti_direccioncli, 50);
            $cotizacion->coti_localidad = $this->sanitizeNullableString($request->coti_localidad, 50);
            $cotizacion->coti_partido = $this->sanitizeNullableString($request->coti_partido, 50);
            $cotizacion->coti_cuit = $this->sanitizeNullableString($request->coti_cuit, 13);
            $cotizacion->coti_codigopostal = $this->sanitizeNullableString($request->coti_codigopostal, 10);
            $cotizacion->coti_telefono = $request->coti_telefono;
            $cotizacion->coti_mail1 = $this->sanitizeNullableString($request->coti_mail1, 120);
            
            // Procesar sector correctamente (debe ser 4 caracteres)
            $sectorFormulario = $this->sanitizeNullableString($request->coti_sector, 4);
            $sectorCandidato = null;
            if ($sectorFormulario) {
                $sectorFormulario = $this->truncateAndPad($sectorFormulario, 4);
                if (Divis::where('divis_codigo', $sectorFormulario)->exists()) {
                    $sectorCandidato = $sectorFormulario;
                }
            }
            $cotizacion->coti_sector = $sectorCandidato;
            
            $cotizacion->coti_referencia_tipo = $this->truncateAndPad($request->coti_referencia_tipo, 30);
            $cotizacion->coti_referencia_valor = $this->sanitizeNullableString($request->coti_referencia_valor, 120);
            $cotizacion->coti_oc_referencia = $this->sanitizeNullableString($request->coti_oc_referencia, 120);
            $cotizacion->coti_hes_has_tipo = $this->truncateAndPad($request->coti_hes_has_tipo, 10);
            $cotizacion->coti_hes_has_valor = $this->sanitizeNullableString($request->coti_hes_has_valor, 120);
            $cotizacion->coti_gr_contrato_tipo = $this->truncateAndPad($request->coti_gr_contrato_tipo, 30);
            $cotizacion->coti_gr_contrato = $this->sanitizeNullableString($request->coti_gr_contrato, 120);
            $cotizacion->coti_otro_referencia = $this->sanitizeNullableString($request->coti_otro_referencia, 120);
            
            // Notas
            $cotizacion->coti_notas = $request->coti_notas;
            
            // Campos de descuentos
            $cotizacion->coti_descuentoglobal = $request->filled('descuento') ? floatval($request->descuento) : 0.00;
            $cotizacion->coti_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje') ? floatval($request->sector_laboratorio_porcentaje) : 0.00;
            $cotizacion->coti_sector_higiene_pct = $request->filled('sector_higiene_porcentaje') ? floatval($request->sector_higiene_porcentaje) : 0.00;
            $cotizacion->coti_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje') ? floatval($request->sector_microbiologia_porcentaje) : 0.00;
            $cotizacion->coti_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje') ? floatval($request->sector_cromatografia_porcentaje) : 0.00;
            $cotizacion->coti_sector_laboratorio_contacto = $this->sanitizeNullableString($request->sector_laboratorio_contacto, 100);
            $cotizacion->coti_sector_higiene_contacto = $this->sanitizeNullableString($request->sector_higiene_contacto, 100);
            $cotizacion->coti_sector_microbiologia_contacto = $this->sanitizeNullableString($request->sector_microbiologia_contacto, 100);
            $cotizacion->coti_sector_cromatografia_contacto = $this->sanitizeNullableString($request->sector_cromatografia_contacto, 100);
            $cotizacion->coti_sector_laboratorio_observaciones = $this->sanitizeNullableString($request->sector_laboratorio_observaciones);
            $cotizacion->coti_sector_higiene_observaciones = $this->sanitizeNullableString($request->sector_higiene_observaciones);
            $cotizacion->coti_sector_microbiologia_observaciones = $this->sanitizeNullableString($request->sector_microbiologia_observaciones);
            $cotizacion->coti_sector_cromatografia_observaciones = $this->sanitizeNullableString($request->sector_cromatografia_observaciones);
            
            // Campos de cadena de custodia y muestreo
            $cotizacion->coti_cadena_custodia = $request->has('coti_cadena_custodia') && $request->coti_cadena_custodia == '1';
            $cotizacion->coti_muestreo = $request->has('coti_muestreo') && $request->coti_muestreo == '1';

            // Versionado simple: cada vez que se actualiza, incrementamos la versión.
            // Si no existe (migración recién aplicada), asumimos versión 1 y luego sumamos.
            $cotizacion->coti_version = (int)($cotizacion->coti_version ?? 1) + 1;
            
            $cotizacion->save();

            $this->procesarEnsayosYComponentes($request, $cotizacion->coti_num, true);
            
            return redirect()->route('ventas.index')
                ->with('success', 'Cotización actualizada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al actualizar cotización:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('ventas.index')
                ->with('error', 'Error al actualizar la cotización: ' . $e->getMessage());
        }
    }

    public function imprimir($id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }

        $cotizacion = Ventas::with([
                'cliente.condicionPago',
                'matriz',
                'condicionPago',
                'listaPrecio',
            ])->find($id);

        if (!$cotizacion) {
            abort(404, 'Cotización not found');
        }

        $ensayos = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', 0)
            ->orderBy('cotio_item')
            ->get();

        $componentes = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', '>', 0)
            ->with(['metodoAnalisis', 'metodoMuestreo'])
            ->orderBy('cotio_item')
            ->orderBy('cotio_subitem')
            ->get();

        $componentesAgrupados = $componentes->groupBy(function ($componente) {
            return (int) $componente->cotio_item;
        });

        $items = $ensayos->map(function ($ensayo) use ($componentesAgrupados) {
            $componentesEnsayo = $componentesAgrupados->get((int) $ensayo->cotio_item, collect());

            if (!$componentesEnsayo instanceof \Illuminate\Support\Collection) {
                $componentesEnsayo = collect($componentesEnsayo);
            }

            $cantidadEnsayo = $this->parseDecimalValue($ensayo->cotio_cantidad ?? 1) ?? 1;
            if ($cantidadEnsayo <= 0) {
                $cantidadEnsayo = 1;
            }

            $subtotalComponentes = $componentesEnsayo->sum(function ($componente) {
                $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;

                if ($cantidad <= 0) {
                    $cantidad = 1;
                }

                return $precio * $cantidad;
            });

            $precioUnitario = $subtotalComponentes;
            $total = $precioUnitario * $cantidadEnsayo;

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

            return [
                'item' => (int) $ensayo->cotio_item,
                'descripcion' => trim($ensayo->cotio_descripcion ?? 'Sin descripción'),
                'cantidad' => $cantidadEnsayo,
                'precio_unitario' => $precioUnitario,
                'total' => $total,
                'notas' => $notasImprimibles,
                'componentes' => $componentesEnsayo->map(function ($componente) {
                    $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                    $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;

                    if ($cantidad <= 0) {
                        $cantidad = 1;
                    }

                    // Obtener nombre del método
                    $metodoCodigo = trim($componente->cotio_codigometodo_analisis ?? $componente->cotio_codigometodo ?? '');
                    $metodoNombre = '';
                    
                    if ($metodoCodigo) {
                        // Intentar desde MetodoAnalisis
                        if ($componente->metodoAnalisis) {
                            $metodoNombre = trim($componente->metodoAnalisis->nombre ?? '');
                        }
                        // Si no, intentar desde MetodoMuestreo
                        if (!$metodoNombre && $componente->metodoMuestreo) {
                            $metodoNombre = trim($componente->metodoMuestreo->nombre ?? '');
                        }
                        // Si no, buscar en tabla metodo (legacy)
                        if (!$metodoNombre) {
                            $metodo = Metodo::where('metodo_codigo', $metodoCodigo)->first();
                            if ($metodo) {
                                $metodoNombre = trim($metodo->metodo_descripcion ?? '');
                            }
                        }
                    }

                    return [
                        'descripcion' => trim($componente->cotio_descripcion ?? ''),
                        'metodo' => $metodoNombre ?: $metodoCodigo,
                        'metodo_codigo' => $metodoCodigo,
                        'unidad' => trim($componente->cotio_codigoum ?? ''),
                        'cantidad' => $cantidad,
                        'precio' => $precio,
                        'total' => $precio * $cantidad,
                    ];
                })->values(),
            ];
        })->sortBy('item')->values();

        $ensayoItems = $ensayos->pluck('cotio_item')->map(function ($item) {
            return (int) $item;
        });

        $componentesSueltos = $componentes->filter(function ($componente) use ($ensayoItems) {
            return !$ensayoItems->contains((int) $componente->cotio_item);
        })->map(function ($componente) {
            $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
            $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;

            if ($cantidad <= 0) {
                $cantidad = 1;
            }

            // Obtener nombre del método
            $metodoCodigo = trim($componente->cotio_codigometodo_analisis ?? $componente->cotio_codigometodo ?? '');
            $metodoNombre = '';
            
            if ($metodoCodigo) {
                // Intentar desde MetodoAnalisis
                if ($componente->metodoAnalisis) {
                    $metodoNombre = trim($componente->metodoAnalisis->nombre ?? '');
                }
                // Si no, intentar desde MetodoMuestreo
                if (!$metodoNombre && $componente->metodoMuestreo) {
                    $metodoNombre = trim($componente->metodoMuestreo->nombre ?? '');
                }
                // Si no, buscar en tabla metodo (legacy)
                if (!$metodoNombre) {
                    $metodo = Metodo::where('metodo_codigo', $metodoCodigo)->first();
                    if ($metodo) {
                        $metodoNombre = trim($metodo->metodo_descripcion ?? '');
                    }
                }
            }

            return [
                'descripcion' => trim($componente->cotio_descripcion ?? ''),
                'metodo' => $metodoNombre ?: $metodoCodigo,
                'metodo_codigo' => $metodoCodigo,
                'unidad' => trim($componente->cotio_codigoum ?? ''),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $precio * $cantidad,
            ];
        })->values();

        $subtotalItems = $items->sum(function ($item) {
            return $item['total'];
        });

        $totalComponentesSueltos = $componentesSueltos->sum(function ($componente) {
            return $componente['total'];
        });

        $subtotal = $subtotalItems + $totalComponentesSueltos;

        $cliente = $cotizacion->cliente;
        $descuentoPorcentaje = max($this->calcularDescuentoCotizacion($cotizacion), 0);
        $descuentoMonto = $subtotal * ($descuentoPorcentaje / 100);
        $totalConDescuento = $subtotal - $descuentoMonto;

        $totalMuestras = $ensayos->sum(function ($ensayo) {
            $cantidad = $this->parseDecimalValue($ensayo->cotio_cantidad ?? 1) ?? 1;
            return $cantidad > 0 ? $cantidad : 1;
        });

        $condicionPago = optional($cotizacion->condicionPago)->pag_descripcion
            ?? optional($cliente?->condicionPago)->pag_descripcion
            ?? 'Contra entrega';

        // Verificar si hay empresas relacionadas usando el nuevo campo coti_cli_empresa
        $tieneEmpresaRelacionada = false;
        $empresaRelacionada = null;
        
        if ($cotizacion->coti_cli_empresa) {
            $empresa = ClienteEmpresaRelacionada::find($cotizacion->coti_cli_empresa);
            if ($empresa) {
                $tieneEmpresaRelacionada = true;
                $empresaRelacionada = [
                    'razon_social' => trim($empresa->razon_social),
                    'cuit' => $empresa->cuit ? trim($empresa->cuit) : null,
                    'direcciones' => $empresa->direcciones ? trim($empresa->direcciones) : null,
                    'localidad' => $empresa->localidad ? trim($empresa->localidad) : null,
                    'partido' => $empresa->partido ? trim($empresa->partido) : null,
                    'contacto' => $empresa->contacto ? trim($empresa->contacto) : null,
                ];
            }
        }

        // Obtener razón social de facturación predeterminada si existe
        $razonSocialPredeterminada = null;
        if ($cliente) {
            $razonSocialPredeterminada = ClienteRazonSocialFacturacion::where('cli_codigo', $cliente->cli_codigo)
                ->where('es_predeterminada', true)
                ->first();
        }

        $data = [
            'cotizacion' => $cotizacion,
            'items' => $items,
            'componentesSueltos' => $componentesSueltos,
            'totales' => [
                'subtotal_items' => $subtotalItems,
                'subtotal_componentes' => $totalComponentesSueltos,
                'subtotal' => $subtotal,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_monto' => $descuentoMonto,
                'total' => $totalConDescuento,
                'total_muestras' => $totalMuestras,
            ],
            'condicionPagoDescripcion' => $condicionPago,
            'fechaActual' => Carbon::now(),
            'tieneEmpresaRelacionada' => $tieneEmpresaRelacionada,
            'empresaRelacionada' => $empresaRelacionada,
            'razonSocialPredeterminada' => $razonSocialPredeterminada,
        ];

        $pdf = Pdf::loadView('ventas.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $fileName = 'Cotizacion_' . trim((string) $cotizacion->coti_num) . '.pdf';

        return $pdf->stream($fileName);
    }

    // API para buscar clientes
    public function buscarClientes(Request $request)
    {
        $termino = $request->get('q', '');
        
        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        try {
            $clientes = Clientes::where('cli_estado', true)
                ->where(function($query) use ($termino) {
                    $query->where('cli_codigo', 'ILIKE', "%{$termino}%")
                          ->orWhere('cli_razonsocial', 'ILIKE', "%{$termino}%")
                          ->orWhere('cli_fantasia', 'ILIKE', "%{$termino}%");
                })
                ->limit(10)
                ->get()
                ->map(function($cliente) {
                    return [
                        'id' => trim($cliente->cli_codigo),
                        'codigo' => trim($cliente->cli_codigo),
                        'text' => trim($cliente->cli_codigo) . ' - ' . trim($cliente->cli_razonsocial),
                        'razon_social' => trim($cliente->cli_razonsocial),
                        'fantasia' => $cliente->cli_fantasia ? trim($cliente->cli_fantasia) : null,
                        'direccion' => $cliente->cli_direccion ? trim($cliente->cli_direccion) : null,
                        'localidad' => $cliente->cli_localidad ? trim($cliente->cli_localidad) : null,
                        'cuit' => $cliente->cli_cuit,
                        'codigo_postal' => $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : null,
                        'telefono' => $cliente->cli_telefono,
                        'email' => $cliente->cli_email ? trim($cliente->cli_email) : null,
                        'contacto' => $cliente->cli_contacto ? trim($cliente->cli_contacto) : null,
                        'sector' => $cliente->cli_codigocrub ? trim($cliente->cli_codigocrub) : null,
                    ];
                });

            return response()->json($clientes);

        } catch (\Exception $e) {
            Log::error('Error buscando clientes:', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    // API para obtener empresas relacionadas de un cliente
    public function obtenerEmpresasRelacionadas($codigoCliente)
    {
        try {
            // Normalizar el código del cliente (trim y padding)
            $codigoTrimmed = trim($codigoCliente);
            $codigoNormalizado = str_pad($codigoTrimmed, 10, ' ', STR_PAD_RIGHT);
            
            Log::info('Buscando empresas relacionadas:', [
                'codigo_original' => $codigoCliente,
                'codigo_trimmed' => $codigoTrimmed,
                'codigo_normalizado' => "'" . $codigoNormalizado . "'"
            ]);
            
            // Buscar con el código normalizado
            $empresas = ClienteEmpresaRelacionada::where('cli_codigo', $codigoNormalizado)
                ->orderBy('razon_social')
                ->get();
            
            // Si no se encontraron, intentar buscar sin padding (por si acaso)
            if ($empresas->isEmpty() && $codigoTrimmed !== $codigoNormalizado) {
                Log::info('No se encontraron empresas con código normalizado, intentando con código trimmed');
                $empresas = ClienteEmpresaRelacionada::whereRaw("TRIM(cli_codigo) = ?", [$codigoTrimmed])
                    ->orderBy('razon_social')
                    ->get();
            }
            
            Log::info('Empresas encontradas:', ['count' => $empresas->count()]);
            
            $empresasMapeadas = $empresas->map(function($empresa) {
                return [
                    'id' => $empresa->id,
                    'razon_social' => trim($empresa->razon_social),
                    'cuit' => $empresa->cuit ? trim($empresa->cuit) : null,
                    'direcciones' => $empresa->direcciones ? trim($empresa->direcciones) : null,
                    'localidad' => $empresa->localidad ? trim($empresa->localidad) : null,
                    'partido' => $empresa->partido ? trim($empresa->partido) : null,
                    'contacto' => $empresa->contacto ? trim($empresa->contacto) : null,
                ];
            });

            return response()->json($empresasMapeadas);
        } catch (\Exception $e) {
            Log::error('Error obteniendo empresas relacionadas:', [
                'codigo' => $codigoCliente,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([]);
        }
    }

    // API para obtener datos de un cliente específico
    public function obtenerCliente($codigo)
    {
        try {
            Log::info('=== API OBTENER CLIENTE ===');
            Log::info('Código recibido:', ['codigo_original' => $codigo]);
            
            $codigoPadded = str_pad($codigo, 10, ' ', STR_PAD_RIGHT);
            Log::info('Código con padding:', ['codigo_padded' => "'" . $codigoPadded . "'"]);
            
            $cliente = Clientes::where('cli_codigo', $codigoPadded)
                ->where('cli_estado', true)
                ->first();

            if (!$cliente) {
                Log::warning('Cliente no encontrado:', ['codigo' => $codigo]);
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }

            // Buscar razón social de facturación predeterminada
            $razonSocialPredeterminada = ClienteRazonSocialFacturacion::where('cli_codigo', $codigoPadded)
                ->where('es_predeterminada', true)
                ->first();

            // Si hay una razón social predeterminada, usar esos datos para la solapa Empresa
            // Si no, usar los datos por defecto del cliente
            $razonSocialEmpresa = $razonSocialPredeterminada ? trim($razonSocialPredeterminada->razon_social) : trim($cliente->cli_razonsocial);
            $direccionEmpresa = $razonSocialPredeterminada && $razonSocialPredeterminada->direccion 
                ? trim($razonSocialPredeterminada->direccion) 
                : ($cliente->cli_direccion ? trim($cliente->cli_direccion) : '');
            $cuitEmpresa = $razonSocialPredeterminada && $razonSocialPredeterminada->cuit 
                ? trim($razonSocialPredeterminada->cuit) 
                : ($cliente->cli_cuit ?: '');
            
            // Para localidad y código postal, usar los del cliente (no están en razones sociales)
            $localidadEmpresa = $cliente->cli_localidad ? trim($cliente->cli_localidad) : '';
            $codigoPostalEmpresa = $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : '';

            $clienteData = [
                'codigo' => trim($cliente->cli_codigo),
                'razon_social' => trim($cliente->cli_razonsocial),
                'fantasia' => $cliente->cli_fantasia ? trim($cliente->cli_fantasia) : '',
                'direccion' => $cliente->cli_direccion ? trim($cliente->cli_direccion) : '',
                'localidad' => $cliente->cli_localidad ? trim($cliente->cli_localidad) : '',
                'cuit' => $cliente->cli_cuit ?: '',
                'codigo_postal' => $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : '',
                'telefono' => $cliente->cli_telefono ?: '',
                'email' => $cliente->cli_email ?: '',
                'contacto' => $cliente->cli_contacto ? trim($cliente->cli_contacto) : '',
                'sector' => $cliente->cli_codigocrub ? trim($cliente->cli_codigocrub) : '',
                'condicion_pago' => $cliente->cli_codigopag ? trim($cliente->cli_codigopag) : '',
                'lista_precios' => $cliente->cli_codigolp ? trim($cliente->cli_codigolp) : '',
                'nro_precio' => $cliente->cli_nroprecio ?: 1,
                'descuento_global' => (float) ($cliente->cli_descuentoglobal ?? 0),
                'descuentos_sector' => $this->obtenerDescuentosSectorCliente($cliente),
                'es_consultor' => (bool) ($cliente->es_consultor ?? false),
                // Datos de la razón social predeterminada para la solapa Empresa
                'razon_social_facturacion' => $razonSocialEmpresa,
                'direccion_facturacion' => $direccionEmpresa,
                'cuit_facturacion' => $cuitEmpresa,
                'localidad_facturacion' => $localidadEmpresa,
                'codigo_postal_facturacion' => $codigoPostalEmpresa,
                'tiene_razon_social_predeterminada' => $razonSocialPredeterminada !== null,
            ];
            
            Log::info('Datos del cliente encontrado:', $clienteData);
            return response()->json($clienteData);

        } catch (\Exception $e) {
            Log::error('Error obteniendo cliente:', ['codigo' => $codigo, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * API para obtener ensayos (muestras) disponibles
     */
    public function obtenerEnsayos(Request $request)
    {
        $termino = $request->get('q', '');
        
        $ensayos = CotioItems::muestras()
            ->when($termino, function($query, $termino) {
                return $query->buscar($termino);
            })
            ->with(['metodoAnalitico', 'metodoMuestreo', 'componentesAsociados:id,cotio_descripcion', 'matrices'])
            ->orderBy('cotio_descripcion')
            ->get();

        return response()->json($ensayos->map(function($ensayo) {
            // Intentar obtener el método desde la relación primero
            $metodoCodigo = optional($ensayo->metodoAnalitico)->metodo_codigo 
                ? trim(optional($ensayo->metodoAnalitico)->metodo_codigo) 
                : ($ensayo->metodo ? trim($ensayo->metodo) : null);
            
            $metodoDescripcion = optional($ensayo->metodoAnalitico)->metodo_descripcion;
            
            // Si tenemos el código pero no la descripción, intentar cargar el método
            if ($metodoCodigo && !$metodoDescripcion) {
                $metodo = \App\Models\Metodo::where('metodo_codigo', trim($metodoCodigo))->first();
                $metodoDescripcion = $metodo ? $metodo->metodo_descripcion : null;
            }
            
            // Obtener método de muestreo
            $metodoMuestreoCodigo = optional($ensayo->metodoMuestreo)->metodo_codigo 
                ? trim(optional($ensayo->metodoMuestreo)->metodo_codigo) 
                : ($ensayo->metodo_muestreo ? trim($ensayo->metodo_muestreo) : null);
            
            $metodoMuestreoDescripcion = optional($ensayo->metodoMuestreo)->metodo_descripcion;
            
            // Si tenemos el código pero no la descripción, intentar cargar el método de muestreo
            if ($metodoMuestreoCodigo && !$metodoMuestreoDescripcion) {
                $metodoMuestreo = \App\Models\Metodo::where('metodo_codigo', trim($metodoMuestreoCodigo))->first();
                $metodoMuestreoDescripcion = $metodoMuestreo ? $metodoMuestreo->metodo_descripcion : null;
            }
            
            // Obtener matriz desde la tabla pivote o desde matriz_codigo directo (para compatibilidad)
            $matrizCodigo = null;
            $matrizDescripcion = null;
            
            if ($ensayo->matrices->isNotEmpty()) {
                $matriz = $ensayo->matrices->first();
                $matrizCodigo = $matriz->matriz_codigo;
                $matrizDescripcion = $matriz->matriz_descripcion;
            } elseif ($ensayo->matriz_codigo) {
                // Fallback: usar matriz_codigo directo si existe
                $matrizCodigo = trim($ensayo->matriz_codigo);
                $matriz = \App\Models\Matriz::where('matriz_codigo', $matrizCodigo)->first();
                $matrizDescripcion = $matriz ? trim($matriz->matriz_descripcion) : null;
            }
            
            return [
                'id' => $ensayo->id,
                'codigo' => str_pad($ensayo->id, 15, '0', STR_PAD_LEFT), // Generar código
                'descripcion' => $ensayo->cotio_descripcion,
                'es_muestra' => $ensayo->es_muestra,
                'metodo_codigo' => $metodoCodigo,
                'metodo_descripcion' => $metodoDescripcion,
                'metodo_muestreo_codigo' => $metodoMuestreoCodigo,
                'metodo_muestreo_descripcion' => $metodoMuestreoDescripcion,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'text' => $ensayo->cotio_descripcion, // Para select2
                'componentes_default' => $ensayo->componentesAsociados->pluck('id')->values(),
            ];
        }));
    }

    /**
     * API para obtener componentes (análisis) disponibles
     */
    public function obtenerComponentes(Request $request)
    {
        $termino = $request->get('q', '');
        $matrizCodigo = $request->get('matriz_codigo', '');
        $incluirAgrupadores = $request->get('incluir_agrupadores', false); // Nuevo parámetro
        
        // Incluir tanto componentes como agrupadores si se solicita
        $query = CotioItems::query();
        
        if (!$incluirAgrupadores) {
            // Por defecto, solo componentes (comportamiento original)
            $query->componentes();
        }
        // Si incluirAgrupadores es true, no filtrar por es_muestra (incluir ambos)
        
        // Si hay filtro de matriz e incluirAgrupadores, los agrupadores no deben filtrarse por matriz
        if ($matrizCodigo && $incluirAgrupadores) {
            // Obtener componentes filtrados por matriz
            $componentesFiltrados = $query
                ->componentes() // Solo componentes
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->whereHas('matrices', function($q) use ($matrizCodigo) {
                    $matrizCodigoLimpio = trim($matrizCodigo);
                    $q->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [trim($matrizCodigoLimpio)]);
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->get();
            
            // Obtener solo agrupadores con agregable_a_comps = true (sin filtro de matriz)
            $agrupadores = CotioItems::muestras()
                ->where('agregable_a_comps', true)
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->get();
            
            // Combinar ambos
            $componentes = $componentesFiltrados->merge($agrupadores)->sortBy('cotio_descripcion')->values();
        } else {
            // Comportamiento normal: filtrar todos por matriz si se especifica
            $componentes = $query
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->when($matrizCodigo, function($query, $matrizCodigo) {
                    // Filtrar componentes que están relacionados con la matriz en la tabla pivote
                    // Limpiar espacios en blanco del código de matriz
                    $matrizCodigoLimpio = trim($matrizCodigo);
                    return $query->whereHas('matrices', function($q) use ($matrizCodigoLimpio) {
                        // Comparar con trim para manejar espacios en blanco
                        // Especificar explícitamente la tabla pivote para evitar ambigüedad
                        // La relación belongsToMany usa la tabla pivote cotio_items_matriz
                        $q->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [trim($matrizCodigoLimpio)]);
                    });
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->orderBy('cotio_descripcion')
                ->get();
        }

        return response()->json($componentes->map(function($componente) {
            // Intentar obtener el método desde la relación primero
            $metodoCodigo = optional($componente->metodoAnalitico)->metodo_codigo 
                ? trim(optional($componente->metodoAnalitico)->metodo_codigo) 
                : ($componente->metodo ? trim($componente->metodo) : null);
            
            $metodoDescripcion = optional($componente->metodoAnalitico)->metodo_descripcion;
            
            // Si tenemos el código pero no la descripción, intentar cargar el método
            if ($metodoCodigo && !$metodoDescripcion) {
                $metodo = \App\Models\Metodo::where('metodo_codigo', trim($metodoCodigo))->first();
                $metodoDescripcion = $metodo ? $metodo->metodo_descripcion : null;
            }
            
            // Obtener matrices relacionadas desde la tabla pivote
            $matricesRelacionadas = $componente->matrices->map(function($matriz) {
                return [
                    'codigo' => $matriz->matriz_codigo,
                    'descripcion' => $matriz->matriz_descripcion
                ];
            });
            
            // Para compatibilidad, usar la primera matriz relacionada o null
            $matrizCodigo = $matricesRelacionadas->isNotEmpty() 
                ? $matricesRelacionadas->first()['codigo'] 
                : null;
            $matrizDescripcion = $matricesRelacionadas->isNotEmpty() 
                ? $matricesRelacionadas->first()['descripcion'] 
                : null;
            
            $precio = $componente->precio ?? 5000.00;

            // Si es agrupador, incluir IDs de componentes asociados
            $componentesAsociadosIds = [];
            if ($componente->es_muestra && $componente->componentesAsociados) {
                $componentesAsociadosIds = $componente->componentesAsociados->pluck('id')->values()->all();
            }
            
            return [
                'id' => $componente->id,
                'codigo' => str_pad($componente->id, 15, '0', STR_PAD_LEFT), // Generar código
                'descripcion' => $componente->cotio_descripcion,
                'es_muestra' => $componente->es_muestra,
                'metodo_codigo' => $metodoCodigo,
                'metodo_descripcion' => $metodoDescripcion,
                'unidad_medida' => $componente->unidad_medida,
                'limites_establecidos' => $componente->limites_establecidos,
                'precio' => $precio, // Precio por defecto 5000 si no existe
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'matrices' => $matricesRelacionadas->values()->all(), // Todas las matrices relacionadas
                'ley_normativa_id' => $componente->ley_normativa_id ?? null,
                'componentes_asociados' => $componentesAsociadosIds, // IDs de componentes asociados si es agrupador
                'text' => $componente->cotio_descripcion // Para select2
            ];
        }));
    }

    /**
     * API para obtener métodos de muestreo
     */
    public function obtenerMetodosMuestreo(Request $request)
    {
        $termino = $request->get('q', '');
        
        $metodos = Metodo::when($termino, function($query, $termino) {
                $query->where('metodo_codigo', 'ILIKE', "%{$termino}%")
                      ->orWhere('metodo_descripcion', 'ILIKE', "%{$termino}%");
            })
            ->orderBy('metodo_codigo')
            ->get();

        return response()->json($metodos->map(function($m) {
            $codigo = trim($m->metodo_codigo);
            return [
                'id' => $codigo,
                'codigo' => $codigo,
                'descripcion' => $m->metodo_descripcion,
                'text' => $codigo . ' - ' . $m->metodo_descripcion,
            ];
        }));
    }

    /**
     * API para obtener métodos de análisis
     */
    public function obtenerMetodosAnalisis(Request $request)
    {
        $termino = $request->get('q', '');
        
        $metodos = Metodo::when($termino, function($query, $termino) {
                $query->where('metodo_codigo', 'ILIKE', "%{$termino}%")
                      ->orWhere('metodo_descripcion', 'ILIKE', "%{$termino}%");
            })
            ->orderBy('metodo_codigo')
            ->get();

        return response()->json($metodos->map(function($m) {
            $codigo = trim($m->metodo_codigo);
            return [
                'id' => $codigo,
                'codigo' => $codigo,
                'descripcion' => $m->metodo_descripcion,
                'text' => $codigo . ' - ' . $m->metodo_descripcion,
            ];
        }));
    }

    /**
     * API para obtener leyes normativas
     */
    public function obtenerLeyesNormativas(Request $request)
    {
        $termino = $request->get('q', '');
        
        $leyes = LeyNormativa::activas()
            ->when($termino, function($query, $termino) {
                return $query->buscar($termino);
            })
            ->orderBy('grupo')
            ->orderBy('codigo')
            ->get();

        return response()->json($leyes->map(function($ley) {
            return [
                'id' => $ley->id,
                'codigo' => $ley->codigo,
                'nombre' => $ley->nombre,
                'grupo' => $ley->grupo,
                'articulo' => $ley->articulo,
                'descripcion' => $ley->descripcion,
                'organismo_emisor' => $ley->organismo_emisor,
                'fecha_vigencia' => $ley->fecha_vigencia,
                'nombre_completo' => $ley->nombre_completo,
                'text' => $ley->codigo . ' - ' . $ley->nombre_completo // Para select2
            ];
        }));
    }

    /**
     * Construir array de cotio_data desde los datos del formulario
     * Esto se usa para guardar la versión histórica con los items que el usuario está editando
     */
    private function construirCotioDataDesdeFormulario(Request $request, $cotiNum)
    {
        $ensayosData = $request->ensayos_data ? json_decode($request->ensayos_data, true) : [];
        $componentesData = $request->componentes_data ? json_decode($request->componentes_data, true) : [];
        
        $cotioItems = [];
        
        // Procesar ensayos (cotio_subitem = 0)
        foreach ($ensayosData as $ensayo) {
            // Buscar el código de producto
            $prodCodigo = $this->buscarCodigoProducto($ensayo['descripcion'] ?? '', true);
            
            $cotioItem = [
                'cotio_numcoti' => $cotiNum,
                'cotio_item' => $ensayo['item'] ?? 0,
                'cotio_subitem' => 0,
                'cotio_codigoprod' => $prodCodigo ?: null,
                'cotio_cantidad' => $this->parseDecimalValue($ensayo['cantidad'] ?? 1) ?? 1,
                'cotio_precio' => null, // Los ensayos no tienen precio directo
                'cotio_descripcion' => $ensayo['descripcion'] ?? '',
                'cotio_codigoum' => null,
                'cotio_codigometodo' => null,
                'cotio_codigometodo_analisis' => null,
                'cotio_nota_tipo' => !empty(trim($ensayo['nota_contenido'] ?? '')) ? ($ensayo['nota_tipo'] ?? 'imprimible') : null,
                'cotio_nota_contenido' => !empty(trim($ensayo['nota_contenido'] ?? '')) ? trim($ensayo['nota_contenido']) : null,
            ];
            
            $cotioItems[] = $cotioItem;
        }
        
        // Procesar componentes (cotio_subitem > 0)
        foreach ($componentesData as $componente) {
            // Encontrar el ensayo asociado
            $ensayoAsociado = collect($ensayosData)->firstWhere('item', $componente['ensayo_asociado'] ?? 0);
            
            if (!$ensayoAsociado) {
                continue;
            }
            
            // Buscar el código de producto
            $prodCodigo = $this->buscarCodigoProducto($componente['descripcion'] ?? '', false);
            
            // Determinar el subitem basado en el orden de los componentes con el mismo ensayo_asociado
            // Contar cuántos componentes anteriores tienen el mismo ensayo_asociado
            $subitem = 1;
            foreach ($componentesData as $comp) {
                if ($comp === $componente) {
                    break;
                }
                if (($comp['ensayo_asociado'] ?? 0) == ($componente['ensayo_asociado'] ?? 0)) {
                    $subitem++;
                }
            }
            
            $cotioItem = [
                'cotio_numcoti' => $cotiNum,
                'cotio_item' => $ensayoAsociado['item'] ?? 0,
                'cotio_subitem' => $subitem,
                'cotio_codigoprod' => $prodCodigo ?: null,
                'cotio_cantidad' => $this->parseDecimalValue($componente['cantidad'] ?? 1) ?? 1,
                'cotio_precio' => $this->parseDecimalValue($componente['precio'] ?? null),
                'cotio_descripcion' => $componente['descripcion'] ?? '',
                'cotio_codigoum' => null,
                'cotio_codigometodo' => null,
                'cotio_codigometodo_analisis' => null,
                'limite_deteccion' => $this->parseDecimalValue($componente['limite_deteccion'] ?? null),
                'limite_cuantificacion' => $this->parseDecimalValue($componente['limite_cuantificacion'] ?? null),
                'ley_aplicacion' => !empty($componente['ley_normativa_id'] ?? '') ? trim($componente['ley_normativa_id']) : null,
                'cotio_nota_tipo' => !empty(trim($componente['nota_contenido'] ?? '')) ? ($componente['nota_tipo'] ?? 'imprimible') : null,
                'cotio_nota_contenido' => !empty(trim($componente['nota_contenido'] ?? '')) ? trim($componente['nota_contenido']) : null,
            ];
            
            // Procesar unidad de medida
            if (!empty($componente['unidad_medida'] ?? '')) {
                $unidadTrim = trim($componente['unidad_medida']);
                if ($unidadTrim !== '') {
                    $cotioItem['cotio_codigoum'] = $this->truncateAndPad($unidadTrim, 10);
                }
            }
            
            // Procesar método (cotio_codigometodo)
            if (!empty($componente['metodo_codigo'] ?? '')) {
                $metodoCodigoTrim = trim($componente['metodo_codigo']);
                if ($metodoCodigoTrim !== '') {
                    $cotioItem['cotio_codigometodo'] = $this->truncateAndPad($metodoCodigoTrim, 15);
                }
            }
            
            // Procesar método de análisis (cotio_codigometodo_analisis)
            if (!empty($componente['metodo_analisis_id'] ?? '')) {
                $metodoAnalisisTrim = trim($componente['metodo_analisis_id']);
                if ($metodoAnalisisTrim !== '') {
                    $cotioItem['cotio_codigometodo_analisis'] = $this->truncateAndPad($metodoAnalisisTrim, 15);
                }
            }
            
            $cotioItems[] = $cotioItem;
        }
        
        // Ordenar por item y subitem
        usort($cotioItems, function($a, $b) {
            if ($a['cotio_item'] != $b['cotio_item']) {
                return $a['cotio_item'] <=> $b['cotio_item'];
            }
            return $a['cotio_subitem'] <=> $b['cotio_subitem'];
        });
        
        return $cotioItems;
    }

    /**
     * Procesar ensayos y componentes para crear registros en cotio
     */
    private function procesarEnsayosYComponentes(Request $request, $cotiNum, bool $reemplazarExistentes = false)
    {
        Log::info('=== PROCESANDO ENSAYOS Y COMPONENTES ===');
        
        try {
            $ensayosData = $request->ensayos_data ? json_decode($request->ensayos_data, true) : [];
            $componentesData = $request->componentes_data ? json_decode($request->componentes_data, true) : [];
            
            Log::info('Datos recibidos:', [
                'ensayos_count' => count($ensayosData),
                'componentes_count' => count($componentesData),
                'ensayos_raw' => $request->ensayos_data,
                'componentes_raw' => $request->componentes_data
            ]);

            if ($reemplazarExistentes) {
                Cotio::where('cotio_numcoti', $cotiNum)->delete();
                Log::info('Registros anteriores de cotio eliminados para la cotización.', ['coti_num' => $cotiNum]);
            }

            // Procesar ensayos (muestras con cotio_subitem = 0)
            foreach ($ensayosData as $ensayo) {
                Log::info('Procesando ensayo:', $ensayo);
                
                // Buscar el código de producto correcto en la tabla prod
                $prodCodigo = $this->buscarCodigoProducto($ensayo['descripcion'], true);
                
                if (!$prodCodigo) {
                    Log::warning('No se encontró código de producto para ensayo:', $ensayo);
                    continue;
                }
                
                // Obtener método de muestreo desde CotioItems
                $metodoMuestreoCodigo = null;
                
                // Primero intentar desde muestra_id si está disponible
                if (!empty($ensayo['muestra_id'])) {
                    $muestraItem = CotioItems::find($ensayo['muestra_id']);
                    if ($muestraItem && $muestraItem->metodo_muestreo) {
                        $metodoMuestreoCodigo = trim($muestraItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigo)->exists()) {
                            $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigo, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por ID', [
                                'muestra_id' => $ensayo['muestra_id'],
                                'metodo_muestreo' => $metodoMuestreoCodigo
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoMuestreoCodigo
                            ]);
                            $metodoMuestreoCodigo = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoMuestreoCodigo && !empty($ensayo['descripcion'])) {
                    $muestraItem = CotioItems::muestras()
                        ->where('cotio_descripcion', $ensayo['descripcion'])
                        ->first();
                    
                    if ($muestraItem && $muestraItem->metodo_muestreo) {
                        $metodoMuestreoCodigo = trim($muestraItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigo)->exists()) {
                            $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigo, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por descripción', [
                                'descripcion' => $ensayo['descripcion'],
                                'metodo_muestreo' => $metodoMuestreoCodigo
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoMuestreoCodigo
                            ]);
                            $metodoMuestreoCodigo = null;
                        }
                    }
                }
                
                // También intentar desde el campo metodo_muestreo_codigo si viene en el request
                if (!$metodoMuestreoCodigo && !empty($ensayo['metodo_muestreo_codigo'])) {
                    $metodoMuestreoCodigoTrim = trim($ensayo['metodo_muestreo_codigo']);
                    if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoTrim)->exists()) {
                        $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigoTrim, 15);
                        Log::info('Método de muestreo obtenido desde request', [
                            'metodo_muestreo' => $metodoMuestreoCodigo
                        ]);
                    }
                }
                
                // Obtener método (análisis) desde CotioItems para ensayos también
                $metodoAnalisisCodigo = null;
                
                // Primero intentar desde muestra_id si está disponible
                if (!empty($ensayo['muestra_id'])) {
                    $muestraItem = CotioItems::find($ensayo['muestra_id']);
                    if ($muestraItem && $muestraItem->metodo) {
                        $metodoAnalisisCodigo = trim($muestraItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisisCodigo = $this->truncateAndPad($metodoAnalisisCodigo, 15);
                            Log::info('Método de análisis obtenido desde CotioItems por ID para ensayo', [
                                'muestra_id' => $ensayo['muestra_id'],
                                'metodo' => $metodoAnalisisCodigo
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo para ensayo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                            $metodoAnalisisCodigo = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoAnalisisCodigo && !empty($ensayo['descripcion'])) {
                    $muestraItem = CotioItems::muestras()
                        ->where('cotio_descripcion', $ensayo['descripcion'])
                        ->first();
                    
                    if ($muestraItem && $muestraItem->metodo) {
                        $metodoAnalisisCodigo = trim($muestraItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisisCodigo = $this->truncateAndPad($metodoAnalisisCodigo, 15);
                            Log::info('Método de análisis obtenido desde CotioItems por descripción para ensayo', [
                                'descripcion' => $ensayo['descripcion'],
                                'metodo' => $metodoAnalisisCodigo
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo para ensayo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                            $metodoAnalisisCodigo = null;
                        }
                    }
                }
                
                $cotioEnsayo = new Cotio();
                $cotioEnsayo->cotio_numcoti = $cotiNum;
                $cotioEnsayo->cotio_item = $ensayo['item'];
                $cotioEnsayo->cotio_subitem = 0; // Las muestras siempre tienen subitem 0
                $cotioEnsayo->cotio_codigoprod = $prodCodigo;
                $cotioEnsayo->cotio_cantidad = $this->parseDecimalValue($ensayo['cantidad'] ?? 1) ?? 1;
                $precioEnsayo = isset($ensayo['precio']) && $ensayo['precio'] !== ''
                    ? $this->parseDecimalValue($ensayo['precio'])
                    : null;
                $cotioEnsayo->cotio_precio = ($precioEnsayo && $precioEnsayo > 0) ? $precioEnsayo : null;
                $cotioEnsayo->cotio_descripcion = $ensayo['descripcion'];
                $cotioEnsayo->cotio_codigoum = null;
                $cotioEnsayo->cotio_codigometodo = $metodoMuestreoCodigo; // Copiar método de muestreo desde CotioItems.metodo_muestreo
                $cotioEnsayo->cotio_codigometodo_analisis = $metodoAnalisisCodigo; // Copiar método de análisis desde CotioItems.metodo
                $cotioEnsayo->enable_muestreo = false;
                
                // Guardar datos de nota solo si hay contenido
                $notaContenido = trim($ensayo['nota_contenido'] ?? '');
                if (!empty($notaContenido)) {
                    $cotioEnsayo->cotio_nota_tipo = $ensayo['nota_tipo'] ?? 'imprimible';
                    $cotioEnsayo->cotio_nota_contenido = $notaContenido;
                } else {
                    $cotioEnsayo->cotio_nota_tipo = null;
                    $cotioEnsayo->cotio_nota_contenido = null;
                }
                
                $cotioEnsayo->save();
                Log::info('Ensayo guardado:', ['cotio_id' => $cotioEnsayo->id, 'prod_codigo' => $prodCodigo]);
            }

            // Procesar componentes (análisis con cotio_subitem > 0)
            foreach ($componentesData as $componente) {
                Log::info('Procesando componente:', $componente);
                
                // Encontrar el ensayo asociado para obtener el cotio_item correcto
                $ensayoAsociado = collect($ensayosData)->firstWhere('item', $componente['ensayo_asociado']);
                
                if (!$ensayoAsociado) {
                    Log::warning('Ensayo asociado no encontrado para componente:', $componente);
                    continue;
                }
                
                // Buscar el código de producto correcto en la tabla prod
                $prodCodigo = $this->buscarCodigoProducto($componente['descripcion'], false);
                
                if (!$prodCodigo) {
                    Log::warning('No se encontró código de producto para componente:', $componente);
                    continue;
                }
                
                // Contar cuántos componentes ya existen para este ensayo para asignar el subitem
                $componentesExistentes = Cotio::where('cotio_numcoti', $cotiNum)
                    ->where('cotio_item', $ensayoAsociado['item'])
                    ->where('cotio_subitem', '>', 0)
                    ->count();
                
                // Validar que tenemos todos los campos requeridos antes de crear el objeto
                if (empty($cotiNum) || empty($ensayoAsociado['item']) || empty($prodCodigo) || empty($componente['descripcion'])) {
                    Log::error('Campos requeridos faltantes para componente:', [
                        'cotiNum' => $cotiNum,
                        'ensayo_item' => $ensayoAsociado['item'] ?? 'NO ENCONTRADO',
                        'prodCodigo' => $prodCodigo,
                        'descripcion' => $componente['descripcion'] ?? 'NO ENCONTRADA'
                    ]);
                    continue;
                }
                
                $cotioComponente = new Cotio();
                $cotioComponente->cotio_numcoti = $cotiNum;
                $cotioComponente->cotio_item = $ensayoAsociado['item'];
                $cotioComponente->cotio_subitem = $componentesExistentes + 1; // Incrementar subitem
                $cotioComponente->cotio_codigoprod = $prodCodigo;
                $cotioComponente->cotio_cantidad = $this->parseDecimalValue($componente['cantidad'] ?? 1) ?? 1;
                $cotioComponente->cotio_precio = $this->parseDecimalValue($componente['precio'] ?? null);
                $cotioComponente->cotio_descripcion = $componente['descripcion'];
                $unidadMedida = $componente['unidad_medida'] ?? null;
                $metodoCodigo = $componente['metodo_codigo'] ?? null;
                $metodoAnalisis = $componente['metodo_analisis_id'] ?? null;
                $metodoMuestreo = $componente['metodo_muestreo_id'] ?? null;
                $limiteDeteccion = $this->parseDecimalValue($componente['limite_deteccion'] ?? null);
                
                // Obtener método de análisis desde CotioItems (se relaciona con tabla metodo)
                // Primero intentar desde analisis_id si está disponible
                if (!$metodoAnalisis && !empty($componente['analisis_id'])) {
                    $analisisItem = CotioItems::find($componente['analisis_id']);
                    if ($analisisItem && $analisisItem->metodo) {
                        $metodoAnalisisCodigo = trim($analisisItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisis = $metodoAnalisisCodigo;
                            Log::info('Método de análisis obtenido desde CotioItems por ID', [
                                'analisis_id' => $componente['analisis_id'],
                                'metodo' => $metodoAnalisis
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoAnalisis && !empty($componente['descripcion'])) {
                    $analisisItem = CotioItems::componentes()
                        ->where('cotio_descripcion', $componente['descripcion'])
                        ->first();
                    
                    if ($analisisItem && $analisisItem->metodo) {
                        $metodoAnalisisCodigo = trim($analisisItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisis = $metodoAnalisisCodigo;
                            Log::info('Método de análisis obtenido desde CotioItems por descripción', [
                                'descripcion' => $componente['descripcion'],
                                'metodo' => $metodoAnalisis
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                        }
                    }
                }

                // Procesar unidad de medida
                if ($unidadMedida) {
                    $unidadTrim = trim($unidadMedida);
                    if ($unidadTrim !== '') {
                        $unidadCodigo = $this->truncateAndPad($unidadTrim, 10);
                        $unidadExiste = DB::table('um')->where('um_codigo', $unidadCodigo)->exists();

                        if (!$unidadExiste) {
                            try {
                                $columns = DB::getSchemaBuilder()->getColumnListing('um');
                                $payload = [];

                                if (in_array('um_codigo', $columns)) {
                                    $payload['um_codigo'] = $unidadCodigo;
                                }

                                if (in_array('um_descripcion', $columns)) {
                                    $payload['um_descripcion'] = Str::upper($unidadTrim);
                                }

                                if (in_array('um_factor', $columns)) {
                                    $payload['um_factor'] = 1;
                                }

                                if (in_array('um_estado', $columns)) {
                                    $payload['um_estado'] = true;
                                }

                                if (in_array('created_at', $columns)) {
                                    $payload['created_at'] = now();
                                }

                                if (in_array('updated_at', $columns)) {
                                    $payload['updated_at'] = now();
                                }

                                if (!empty($payload)) {
                                    DB::table('um')->insert($payload);
                                    Log::info('Unidad de medida creada automáticamente', [
                                        'unidad' => $unidadTrim,
                                        'unidad_codigo' => $unidadCodigo
                                    ]);
                                } else {
                                    Log::warning('No se pudo crear unidad de medida: sin columnas conocidas', [
                                        'unidad' => $unidadTrim
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::error('Error creando unidad de medida automáticamente', [
                                    'unidad' => $unidadTrim,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        if (DB::table('um')->where('um_codigo', $unidadCodigo)->exists()) {
                            $cotioComponente->cotio_codigoum = $unidadCodigo;
                            Log::info('Unidad de medida asignada al componente', [
                                'unidad_codigo' => $unidadCodigo,
                                'componente' => $componente['descripcion']
                            ]);
                        } else {
                            $cotioComponente->cotio_codigoum = null;
                        }
                    }
                }

                // Obtener método de muestreo desde CotioItems para componentes también
                $metodoMuestreoCodigoComp = null;
                
                // Primero intentar desde analisis_id si está disponible
                if (!empty($componente['analisis_id'])) {
                    $analisisItem = CotioItems::find($componente['analisis_id']);
                    if ($analisisItem && $analisisItem->metodo_muestreo) {
                        $metodoMuestreoCodigoComp = trim($analisisItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoComp)->exists()) {
                            $metodoMuestreoCodigoComp = $this->truncateAndPad($metodoMuestreoCodigoComp, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por ID para componente', [
                                'analisis_id' => $componente['analisis_id'],
                                'metodo_muestreo' => $metodoMuestreoCodigoComp
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo para componente', [
                                'metodo_codigo' => $metodoMuestreoCodigoComp
                            ]);
                            $metodoMuestreoCodigoComp = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoMuestreoCodigoComp && !empty($componente['descripcion'])) {
                    $analisisItem = CotioItems::componentes()
                        ->where('cotio_descripcion', $componente['descripcion'])
                        ->first();
                    
                    if ($analisisItem && $analisisItem->metodo_muestreo) {
                        $metodoMuestreoCodigoComp = trim($analisisItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoComp)->exists()) {
                            $metodoMuestreoCodigoComp = $this->truncateAndPad($metodoMuestreoCodigoComp, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por descripción para componente', [
                                'descripcion' => $componente['descripcion'],
                                'metodo_muestreo' => $metodoMuestreoCodigoComp
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo para componente', [
                                'metodo_codigo' => $metodoMuestreoCodigoComp
                            ]);
                            $metodoMuestreoCodigoComp = null;
                        }
                    }
                }
                
                // Para componentes: copiar ambos métodos desde CotioItems
                // - cotio_codigometodo desde CotioItems.metodo_muestreo
                // - cotio_codigometodo_analisis desde CotioItems.metodo
                $cotioComponente->cotio_codigometodo = $metodoMuestreoCodigoComp;
                
                // Asignar método de análisis desde CotioItems.metodo
                if ($metodoAnalisis) {
                    $codigoMetodoAnalisisTrim = trim($metodoAnalisis);
                    // Verificar que existe en la tabla metodo
                    if (Metodo::where('metodo_codigo', $codigoMetodoAnalisisTrim)->exists()) {
                        $cotioComponente->cotio_codigometodo_analisis = $this->truncateAndPad($codigoMetodoAnalisisTrim, 15);
                        Log::info('Método de análisis asignado al componente desde CotioItems.metodo (tabla metodo)', [
                            'metodo_analisis' => $codigoMetodoAnalisisTrim,
                            'componente' => $componente['descripcion']
                        ]);
                    } else {
                        Log::warning('Método de análisis no encontrado en tabla metodo', [
                            'codigo' => $codigoMetodoAnalisisTrim,
                            'componente' => $componente['descripcion']
                        ]);
                        $cotioComponente->cotio_codigometodo_analisis = null;
                    }
                } else {
                    $cotioComponente->cotio_codigometodo_analisis = null;
                    Log::warning('No se encontró método de análisis desde CotioItems para componente', [
                        'componente' => $componente['descripcion']
                    ]);
                }

                if (!is_null($limiteDeteccion)) {
                    $cotioComponente->limite_deteccion = $limiteDeteccion;
                }

                $cotioComponente->enable_muestreo = false;
                
                // Guardar datos de nota solo si hay contenido
                $notaContenido = trim($componente['nota_contenido'] ?? '');
                if (!empty($notaContenido)) {
                    $cotioComponente->cotio_nota_tipo = $componente['nota_tipo'] ?? 'imprimible';
                    $cotioComponente->cotio_nota_contenido = $notaContenido;
                } else {
                    $cotioComponente->cotio_nota_tipo = null;
                    $cotioComponente->cotio_nota_contenido = null;
                }
                
                // Intentar guardar el componente con manejo de errores detallado
                try {
                    Log::info('Intentando guardar componente:', [
                        'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                        'cotio_item' => $cotioComponente->cotio_item,
                        'cotio_subitem' => $cotioComponente->cotio_subitem,
                        'cotio_descripcion' => $cotioComponente->cotio_descripcion,
                        'cotio_codigoprod' => $cotioComponente->cotio_codigoprod,
                        'cotio_codigometodo' => $cotioComponente->cotio_codigometodo,
                        'cotio_codigometodo_analisis' => $cotioComponente->cotio_codigometodo_analisis,
                    ]);
                    
                    $cotioComponente->save();
                    Log::info('Componente guardado exitosamente:', [
                        'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                        'cotio_item' => $cotioComponente->cotio_item,
                        'cotio_subitem' => $cotioComponente->cotio_subitem,
                        'prod_codigo' => $prodCodigo
                    ]);
                } catch (\Exception $saveException) {
                    Log::error('Error al guardar componente:', [
                        'error' => $saveException->getMessage(),
                        'file' => $saveException->getFile(),
                        'line' => $saveException->getLine(),
                        'componente_data' => [
                            'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                            'cotio_item' => $cotioComponente->cotio_item,
                            'cotio_subitem' => $cotioComponente->cotio_subitem,
                            'cotio_descripcion' => $cotioComponente->cotio_descripcion,
                            'cotio_codigoprod' => $cotioComponente->cotio_codigoprod,
                            'cotio_codigometodo' => $cotioComponente->cotio_codigometodo,
                            'cotio_codigometodo_analisis' => $cotioComponente->cotio_codigometodo_analisis,
                        ],
                        'trace' => $saveException->getTraceAsString()
                    ]);
                    // Continuar con el siguiente componente en lugar de detener todo el proceso
                    continue;
                }
            }
            
            Log::info('Ensayos y componentes procesados exitosamente');
            
        } catch (\Exception $e) {
            Log::error('Error procesando ensayos y componentes:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar la excepción para no interrumpir la creación de la cotización
        }
    }

    /**
     * Buscar código de producto en la tabla prod basándose en la descripción
     */
    private function buscarCodigoProducto($descripcion, $esMuestra = true)
    {
        try {
            Log::info('Buscando código de producto:', [
                'descripcion' => $descripcion,
                'es_muestra' => $esMuestra
            ]);
            
            // Buscar en la tabla prod por descripción exacta
            $producto = DB::table('prod')
                ->where('prod_descripcion', $descripcion)
                ->where('prod_estado', true)
                ->first();
            
            if ($producto) {
                Log::info('Producto encontrado por descripción exacta:', [
                    'prod_codigo' => $producto->prod_codigo,
                    'descripcion' => $producto->prod_descripcion
                ]);
                return $producto->prod_codigo;
            }
            
            // Si no se encuentra por descripción exacta, buscar por descripción similar
            $producto = DB::table('prod')
                ->where('prod_descripcion', 'ILIKE', "%{$descripcion}%")
                ->where('prod_estado', true)
                ->first();
            
            if ($producto) {
                Log::info('Producto encontrado por descripción similar:', [
                    'prod_codigo' => $producto->prod_codigo,
                    'descripcion' => $producto->prod_descripcion
                ]);
                return $producto->prod_codigo;
            }
            
            // Si no se encuentra, crear un código genérico basado en el tipo
            $codigoGenerico = $esMuestra ? '000010000000000' : '000010000100006'; // Códigos de ejemplo de la investigación
            
            Log::warning('No se encontró producto, usando código genérico:', [
                'descripcion' => $descripcion,
                'codigo_generico' => $codigoGenerico
            ]);
            
            return $codigoGenerico;
            
        } catch (\Exception $e) {
            Log::error('Error buscando código de producto:', [
                'error' => $e->getMessage(),
                'descripcion' => $descripcion
            ]);
            
            // Retornar código genérico en caso de error
            return $esMuestra ? '000010000000000' : '000010000100006';
        }
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

        $primerosTres = substr($valor, 0, 3);
        $mapBasico = [
            'LAB' => 'LAB',
            'HYS' => 'HYS',
            'MIC' => 'MIC',
            'CRO' => 'CRO',
        ];

        return $mapBasico[$primerosTres] ?? null;
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

    private function obtenerDescuentoSector(?Clientes $cliente, ?string $sector): float
    {
        if (!$cliente) {
            return 0.0;
        }

        $codigoSector = $this->normalizarCodigoSector($sector);
        if (!$codigoSector) {
            return 0.0;
        }

        $descuentos = $this->obtenerDescuentosSectorCliente($cliente);

        return (float) ($descuentos[$codigoSector] ?? 0.0);
    }

    private function calcularDescuentoCliente(?Clientes $cliente, ?string $sector): float
    {
        if (!$cliente) {
            return 0.0;
        }

        $global = (float) ($cliente->cli_descuentoglobal ?? 0.0);
        $sectorExtra = $this->obtenerDescuentoSector($cliente, $sector);

        return $global + $sectorExtra;
    }

    private function calcularDescuentoCotizacion(?Ventas $cotizacion): float
    {
        if (!$cotizacion) {
            return 0.0;
        }

        $cliente = $cotizacion->cliente;
        $sectorCodigoOriginal = $cotizacion->coti_sector ?? optional($cliente)->cli_codigocrub;
        $sectorCodigo = $this->normalizarCodigoSector($sectorCodigoOriginal);

        // Prioridad: primero descuentos de la cotización, luego del cliente
        // Descuento global: usar el de la cotización si existe, sino el del cliente
        $descuentoGlobal = 0.0;
        if (isset($cotizacion->coti_descuentoglobal) && $cotizacion->coti_descuentoglobal > 0) {
            $descuentoGlobal = (float) $cotizacion->coti_descuentoglobal;
        } elseif ($cliente) {
            $descuentoGlobal = (float) ($cliente->cli_descuentoglobal ?? 0.0);
        }

        // Descuento sector: usar el de la cotización si existe, sino el del cliente
        $descuentoSector = 0.0;
        if ($sectorCodigo) {
            $descuentoSector = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
        }
        
        // Si no hay descuento de sector en la cotización, usar el del cliente
        if ($descuentoSector == 0.0 && $cliente) {
            $descuentoSector = $this->obtenerDescuentoSector($cliente, $sectorCodigo);
        }

        return $descuentoGlobal + $descuentoSector;
    }

    private function obtenerDescuentosSectorCotizacion(?Ventas $cotizacion): array
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

    private function obtenerDescuentoSectorCotizacion(?Ventas $cotizacion, ?string $sectorCodigo): float
    {
        if (!$cotizacion || !$sectorCodigo) {
            return 0.0;
        }

        $descuentos = $this->obtenerDescuentosSectorCotizacion($cotizacion);
        return (float) ($descuentos[$sectorCodigo] ?? 0.0);
    }

    /**
     * API para obtener todas las versiones de una cotización
     */
    public function obtenerVersiones($cotiNum)
    {
        try {
            $versiones = CotiVersion::where('coti_num', $cotiNum)
                ->orderBy('version', 'desc')
                ->get()
                ->map(function($version) {
                    return [
                        'id' => $version->id,
                        'version' => $version->version,
                        'fecha_version' => $version->fecha_version->format('d/m/Y H:i'),
                        'fecha_version_raw' => $version->fecha_version->format('Y-m-d H:i:s'),
                    ];
                });

            // Agregar la versión actual
            $cotizacion = Ventas::find($cotiNum);
            if ($cotizacion) {
                $versionActual = (int)($cotizacion->coti_version ?? 1);
                $versiones->prepend([
                    'id' => null,
                    'version' => $versionActual,
                    'fecha_version' => 'Actual',
                    'fecha_version_raw' => now()->format('Y-m-d H:i:s'),
                    'es_actual' => true,
                ]);
            }

            return response()->json($versiones->values());
        } catch (\Exception $e) {
            Log::error('Error obteniendo versiones:', [
                'coti_num' => $cotiNum,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al obtener versiones'], 500);
        }
    }

    /**
     * API para cargar una versión específica de una cotización
     */
    public function cargarVersion($cotiNum, $version)
    {
        try {
            // Si es la versión actual, cargar desde la tabla principal
            $cotizacion = Ventas::find($cotiNum);
            if (!$cotizacion) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            $versionActual = (int)($cotizacion->coti_version ?? 1);
            
            // Normalizar version para comparación (puede venir como string o int)
            $versionSolicitada = (int)$version;
            
            Log::info('Comparando versiones en cargarVersion', [
                'coti_num' => $cotiNum,
                'version_solicitada' => $versionSolicitada,
                'version_actual' => $versionActual,
                'son_iguales' => ($versionSolicitada == $versionActual)
            ]);
            
            if ($versionSolicitada == $versionActual) {
                // Cargar versión actual desde tabla principal
                $cotiData = $cotizacion->getAttributes();
                
                // Usar DB::table para obtener TODOS los campos
                $cotioItemsRaw = DB::table('cotio')
                    ->where('cotio_numcoti', $cotiNum)
                    ->orderBy('cotio_item')
                    ->orderBy('cotio_subitem')
                    ->get();
                
                $cotioItems = $cotioItemsRaw->map(function($item) {
                    return (array) $item;
                })->toArray();
            } else {
                // Cargar versión histórica
                // Usar $versionSolicitada normalizada para la búsqueda
                $versionHistorica = CotiVersion::where('coti_num', $cotiNum)
                    ->where('version', $versionSolicitada)
                    ->first();
                
                if (!$versionHistorica) {
                    Log::warning('Versión histórica no encontrada', [
                        'coti_num' => $cotiNum,
                        'version_solicitada' => $versionSolicitada
                    ]);
                    return response()->json(['error' => 'Versión no encontrada'], 404);
                }
                
                Log::info('Versión histórica encontrada', [
                    'coti_num' => $cotiNum,
                    'version' => $versionSolicitada,
                    'version_id' => $versionHistorica->id,
                    'coti_data_tipo' => gettype($versionHistorica->coti_data),
                    'cotio_data_tipo' => gettype($versionHistorica->cotio_data)
                ]);
                
                // Obtener datos de la versión histórica
                // IMPORTANTE: coti_data y cotio_data pueden venir como string JSON o como array
                // Asegurarse de decodificarlos correctamente
                $cotiDataRaw = $versionHistorica->coti_data;
                $cotioItemsRaw = $versionHistorica->cotio_data;
                
                // Decodificar coti_data si es string
                if (is_string($cotiDataRaw)) {
                    $cotiData = json_decode($cotiDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando coti_data', [
                            'coti_num' => $cotiNum,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg(),
                            'coti_data_raw_length' => strlen($cotiDataRaw)
                        ]);
                        $cotiData = [];
                    }
                } else {
                    $cotiData = $cotiDataRaw ?? [];
                }
                
                // Decodificar cotio_data si es string
                if (is_string($cotioItemsRaw)) {
                    $cotioItems = json_decode($cotioItemsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando cotio_data en cargarVersion', [
                            'coti_num' => $cotiNum,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg(),
                            'cotio_data_raw_length' => strlen($cotioItemsRaw)
                        ]);
                        $cotioItems = [];
                    }
                } else {
                    $cotioItems = $cotioItemsRaw ?? [];
                }
                
                // Asegurar que cotioItems sea un array
                if (!is_array($cotioItems)) {
                    Log::warning('cotioItems no es un array después de decodificar', [
                        'coti_num' => $cotiNum,
                        'version' => $versionSolicitada,
                        'tipo' => gettype($cotioItems)
                    ]);
                    $cotioItems = [];
                }
                
                Log::info('Datos decodificados de versión histórica', [
                    'coti_num' => $cotiNum,
                    'version' => $versionSolicitada,
                    'cotio_items_count' => count($cotioItems),
                    'cotio_items_sample' => array_slice($cotioItems, 0, 2)
                ]);
                
                // Normalizar el sector si existe (asegurar formato de 4 caracteres)
                // Si es null, mantenerlo como null explícitamente
                if (isset($cotiData['coti_sector']) && $cotiData['coti_sector'] !== null) {
                    $sectorTrimmed = trim($cotiData['coti_sector']);
                    if ($sectorTrimmed !== '') {
                        $cotiData['coti_sector'] = $this->truncateAndPad($sectorTrimmed, 4);
                    } else {
                        $cotiData['coti_sector'] = null;
                    }
                } else {
                    $cotiData['coti_sector'] = null;
                }
            }

            // Asegurar que cotioItems sea siempre un array, incluso si está vacío
            $cotioItemsArray = is_array($cotioItems) ? $cotioItems : [];
            
            // Log para debugging
            Log::info('Cargando versión desde API', [
                'coti_num' => $cotiNum,
                'version' => $version,
                'version_actual' => $versionActual,
                'es_version_actual' => ($version == $versionActual),
                'cotio_items_count' => count($cotioItemsArray),
                'cotio_items_sample' => array_slice($cotioItemsArray, 0, 2),
                'cotio_items_tipo' => gettype($cotioItemsArray)
            ]);
            
            return response()->json([
                'coti_data' => $cotiData,
                'cotio_data' => $cotioItemsArray, // Siempre un array
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            Log::error('Error cargando versión:', [
                'coti_num' => $cotiNum,
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al cargar versión'], 500);
        }
    }

    /**
     * Buscar cotizaciones para clonar
     */
    public function buscarParaClonar(Request $request)
    {
        try {
            $query = Ventas::query()
                ->leftJoin('cli', 'coti.coti_codigocli', '=', 'cli.cli_codigo')
                ->select('coti.*', 'cli.cli_razonsocial');

            // Filtro por número de cotización
            if ($request->filled('numero')) {
                $query->where('coti.coti_num', 'LIKE', '%' . $request->numero . '%');
            }

            // Filtro por descripción
            if ($request->filled('descripcion')) {
                $query->where('coti.coti_descripcion', 'LIKE', '%' . $request->descripcion . '%');
            }

            // Filtro por cliente (nombre o código)
            if ($request->filled('cliente')) {
                $query->where(function($q) use ($request) {
                    $q->where('cli.cli_razonsocial', 'LIKE', '%' . $request->cliente . '%')
                      ->orWhere('coti.coti_codigocli', 'LIKE', '%' . $request->cliente . '%');
                });
            }

            // Filtro por estado
            if ($request->filled('estado')) {
                $estadosMap = [
                    'En Espera' => 'E    ',
                    'Aprobado' => 'A    ',
                    'Rechazado' => 'R    ',
                    'En Proceso' => 'P    ',
                ];
                $estadoBd = $estadosMap[$request->estado] ?? $request->estado;
                // Buscar exactamente el estado con espacios (la BD guarda con espacios fijos)
                $query->where('coti.coti_estado', $estadoBd);
            }

            // Filtro por fecha desde
            if ($request->filled('fecha_desde')) {
                $query->whereDate('coti.coti_fechaalta', '>=', $request->fecha_desde);
            }

            // Filtro por fecha hasta
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('coti.coti_fechaalta', '<=', $request->fecha_hasta);
            }

            $cotizaciones = $query->orderBy('coti.coti_num', 'desc')
                ->limit(50)
                ->get()
                ->map(function($cotizacion) {
                    // Mapear estado de BD a formulario
                    $estadosMap = [
                        'E    ' => 'En Espera',
                        'A    ' => 'Aprobado',
                        'R    ' => 'Rechazado',
                        'P    ' => 'En Proceso',
                    ];
                    // Buscar el estado con espacios (como está en la BD)
                    $estadoBd = $cotizacion->coti_estado;
                    $estadoFormulario = $estadosMap[$estadoBd] ?? trim($estadoBd);

                    return [
                        'coti_num' => $cotizacion->coti_num,
                        'coti_descripcion' => $cotizacion->coti_descripcion,
                        'coti_codigocli' => trim($cotizacion->coti_codigocli),
                        'cliente_nombre' => trim($cotizacion->cli_razonsocial ?? ''),
                        'coti_estado' => $estadoFormulario,
                        'coti_fechaalta' => $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('Y-m-d') : null,
                    ];
                });

            return response()->json(['cotizaciones' => $cotizaciones]);
        } catch (\Exception $e) {
            Log::error('Error buscando cotizaciones para clonar:', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al buscar cotizaciones'], 500);
        }
    }

    /**
     * Obtener datos completos de una cotización para clonar
     */
    public function obtenerParaClonar($cotiNum)
    {
        try {
            $cotizacion = Ventas::where('coti_num', $cotiNum)->first();

            if (!$cotizacion) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            // Obtener ensayos (cotio_subitem = 0)
            $ensayos = Cotio::where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', 0)
                ->orderBy('cotio_item')
                ->get();

            // Obtener componentes (cotio_subitem > 0)
            $componentes = Cotio::where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', '>', 0)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();

            // Mapear estado de BD a formulario
            $estadosMap = [
                'E    ' => 'En Espera',
                'A    ' => 'Aprobado',
                'R    ' => 'Rechazado',
                'P    ' => 'En Proceso',
            ];
            $estado = trim($cotizacion->coti_estado);
            $estadoFormulario = isset($estadosMap[$estado]) ? $estadosMap[$estado] : $estado;

            // Preparar datos de la cotización
            $datosCotizacion = [
                'coti_codigocli' => trim($cotizacion->coti_codigocli),
                'coti_descripcion' => $cotizacion->coti_descripcion,
                'coti_fechaalta' => $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('Y-m-d') : null,
                'coti_fechafin' => $cotizacion->coti_fechafin ? $cotizacion->coti_fechafin->format('Y-m-d') : null,
                'coti_estado' => $estadoFormulario,
                'coti_codigosuc' => trim($cotizacion->coti_codigosuc ?? ''),
                'coti_para' => $cotizacion->coti_para,
                'coti_cli_empresa' => $cotizacion->coti_cli_empresa,
                'coti_contacto' => $cotizacion->coti_contacto,
                'coti_mail1' => $cotizacion->coti_mail1,
                'coti_telefono' => $cotizacion->coti_telefono,
                'coti_sector' => trim($cotizacion->coti_sector ?? ''),
                'coti_notas' => $cotizacion->coti_notas,
                'descuento' => $cotizacion->coti_descuentoglobal ?? 0.00,
                'coti_cadena_custodia' => $cotizacion->coti_cadena_custodia ?? false,
                'coti_muestreo' => $cotizacion->coti_muestreo ?? false,
                'coti_responsable' => $cotizacion->coti_responsable,
                'coti_fechaaprobado' => $cotizacion->coti_fechaaprobado ? $cotizacion->coti_fechaaprobado->format('Y-m-d') : null,
                'coti_aprobo' => $cotizacion->coti_aprobo,
                'coti_fechaencurso' => $cotizacion->coti_fechaencurso ? $cotizacion->coti_fechaencurso->format('Y-m-d') : null,
                'coti_fechaaltatecnica' => $cotizacion->coti_fechaaltatecnica ? $cotizacion->coti_fechaaltatecnica->format('Y-m-d') : null,
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_establecimiento' => $cotizacion->coti_establecimiento,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_partido' => $cotizacion->coti_partido,
                'coti_cuit' => $cotizacion->coti_cuit,
                'coti_codigopostal' => $cotizacion->coti_codigopostal,
            ];

            // Preparar ensayos
            $ensayosData = $ensayos->map(function($ensayo) {
                return [
                    'item' => $ensayo->cotio_item,
                    'descripcion' => $ensayo->cotio_descripcion,
                    'cantidad' => $ensayo->cotio_cantidad ?? 1,
                    'codigo' => $ensayo->cotio_codigoprod ?? '',
                    'no_requiere_custodia' => !$ensayo->cotio_cadena_custodia ?? false,
                    'flexible' => $ensayo->cotio_flexible ?? false,
                    'bonificado' => $ensayo->cotio_bonificado ?? false,
                    'ley_normativa' => $ensayo->cotio_ley_normativa ?? null,
                    'notas' => $ensayo->cotio_nota_contenido ? [[
                        'tipo' => $ensayo->cotio_nota_tipo ?? 'imprimible',
                        'contenido' => $ensayo->cotio_nota_contenido
                    ]] : [],
                ];
            })->toArray();

            // Preparar componentes
            $componentesData = $componentes->map(function($componente) {
                return [
                    'item' => $componente->cotio_item,
                    'subitem' => $componente->cotio_subitem,
                    'ensayo_asociado' => $componente->cotio_item,
                    'analisis' => [$componente->cotio_descripcion],
                    'codigo' => $componente->cotio_codigoprod ?? '',
                    'precio' => $componente->cotio_precio ?? 0.00,
                    'no_requiere_custodia' => !$componente->cotio_cadena_custodia ?? false,
                    'flexible' => $componente->cotio_flexible ?? false,
                    'bonificado' => $componente->cotio_bonificado ?? false,
                ];
            })->toArray();

            return response()->json([
                'cotizacion' => $datosCotizacion,
                'ensayos' => $ensayosData,
                'componentes' => $componentesData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo cotización para clonar:', [
                'coti_num' => $cotiNum,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al obtener cotización'], 500);
        }
    }

}