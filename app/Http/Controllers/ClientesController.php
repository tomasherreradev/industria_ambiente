<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use App\Models\Clientes;
use App\Models\CondicionIva;
use App\Models\Zona;
use App\Models\CondicionPago;
use App\Models\ListaPrecio;
use App\Models\TipoCliente;
use App\Models\ClienteEmpresaRelacionada;
use App\Models\ClienteRazonSocialFacturacion;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller {
    
    public function index(Request $request)
    {
        // Construir query con filtros
        $query = Clientes::query();
        
        // Filtro por búsqueda general (usar ILIKE para búsqueda case-insensitive en PostgreSQL)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('cli_codigo', 'ILIKE', "%{$search}%")
                  ->orWhere('cli_razonsocial', 'ILIKE', "%{$search}%")
                  ->orWhere('cli_fantasia', 'ILIKE', "%{$search}%")
                  ->orWhere('cli_cuit', 'ILIKE', "%{$search}%");
            });
        }
        
        // Filtro por estado
        if ($request->filled('estado')) {
            $query->where('cli_estado', $request->estado);
        }
        
        // Ordenar y paginar
        $clientes = $query->orderBy('cli_razonsocial', 'asc')
            ->paginate(20)
            ->withQueryString();

        return View::make('clientes.index', compact('clientes'));
    }

    public function create()
    {
        // Cargar datos para los selectores
        $condicionesIva = CondicionIva::where('civa_estado', true)
            ->orderBy('civa_descripcion')
            ->get();
        
        $zonas = Zona::where('zon_estado', true)
            ->orderBy('zon_descripcion')
            ->get();
        
        $condicionesPago = CondicionPago::where('pag_estado', true)
            ->orderBy('pag_descripcion')
            ->get();

        // Cargar listas de precios si existe la tabla
        try {
            $listasPrecios = ListaPrecio::where('lp_estado', true)
                ->orderBy('lp_descripcion')
                ->get();
        } catch (\Exception $e) {
            // Si no existe la tabla lp, usar valores por defecto
            $listasPrecios = collect([
                (object)['lp_codigo' => 'UNO  ', 'lp_descripcion' => 'Lista Principal'],
                (object)['lp_codigo' => 'DOS  ', 'lp_descripcion' => 'Lista Secundaria'],
                (object)['lp_codigo' => 'TRES ', 'lp_descripcion' => 'Lista Especial'],
            ]);
        }

        // Cargar tipos de cliente
        $tiposCliente = TipoCliente::orderBy('tcli_descripcion')->get();

        return view('clientes.create', compact('condicionesIva', 'zonas', 'condicionesPago', 'listasPrecios', 'tiposCliente'));
    }

    public function store(Request $request)
    {
        try {
            Log::info('=== INICIO CREACIÓN DE CLIENTE ===');
            Log::info('Datos recibidos en store:', $request->all());
            
            // Validar datos básicos
            Log::info('Iniciando validación de datos básicos');
            $request->validate([
                'razon_social' => 'required|string|max:255',
                'activo' => 'required|boolean'
            ], [
                'razon_social.required' => 'La Razón Social es obligatoria',
                'razon_social.max' => 'La Razón Social no puede superar los 255 caracteres',
                'activo.required' => 'El estado es obligatorio'
            ]);
            Log::info('Validación de datos básicos completada');

            // Generar código automático si no se proporciona
            Log::info('Generando código de cliente');
            $codigo = $request->codigo;
            if (empty($codigo)) {
                Log::info('Código no proporcionado, generando automáticamente');
                $ultimoCliente = Clientes::orderBy('cli_codigo', 'desc')->first();
                if ($ultimoCliente) {
                    $ultimoCodigo = intval(trim($ultimoCliente->cli_codigo));
                    $codigo = str_pad($ultimoCodigo + 1, 10, ' ', STR_PAD_RIGHT);
                    Log::info('Último cliente encontrado, nuevo código:', ['ultimo' => $ultimoCodigo, 'nuevo' => trim($codigo)]);
                } else {
                    $codigo = str_pad('1', 10, ' ', STR_PAD_RIGHT);
                    Log::info('No hay clientes previos, usando código 1');
                }
            } else {
                $codigo = str_pad($codigo, 10, ' ', STR_PAD_RIGHT);
                Log::info('Usando código proporcionado:', ['codigo' => trim($codigo)]);
            }

            // Crear el cliente
            Log::info('Creando instancia de cliente');
        $cliente = new Clientes();
            
            // Campos principales
            Log::info('Asignando campos principales');
            $cliente->cli_codigo = $codigo;
            $cliente->cli_razonsocial = str_pad($request->razon_social ?? '', 60, ' ', STR_PAD_RIGHT);
            $cliente->cli_fantasia = $request->fantasia ? str_pad($request->fantasia, 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_direccion = $request->direccion ? str_pad($request->direccion, 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_partido = $request->partido ? str_pad($request->partido, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_localidad = $request->localidad ? str_pad($request->localidad, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigopostal = $request->codigo_postal ? str_pad($request->codigo_postal, 10, ' ', STR_PAD_RIGHT) : null;
            
            Log::info('Campos principales asignados:', [
                'codigo' => trim($cliente->cli_codigo),
                'razon_social' => trim($cliente->cli_razonsocial),
                'fantasia' => $cliente->cli_fantasia ? trim($cliente->cli_fantasia) : null
            ]);
            
            // Campos de país y provincia
            Log::info('Asignando campos de país y provincia');
            // Truncar y padear el código de país a 5 caracteres máximo
            $paisCodigo = $request->pais_codigo ? substr(trim($request->pais_codigo), 0, 5) : 'ARG';
            $cliente->cli_codigopais = str_pad($paisCodigo, 5, ' ', STR_PAD_RIGHT);
            
            $provinciaCodigo = $request->provincia_codigo ? substr(trim($request->provincia_codigo), 0, 5) : null;
            $cliente->cli_codigoprv = $provinciaCodigo ? str_pad($provinciaCodigo, 5, ' ', STR_PAD_RIGHT) : null;
            
            Log::info('País asignado:', ['codigo' => $cliente->cli_codigopais, 'original' => $request->pais_codigo]);
            Log::info('Provincia asignada:', ['codigo' => $cliente->cli_codigoprv ? trim($cliente->cli_codigoprv) : null, 'original' => $request->provincia_codigo]);
            
            // Estado
            Log::info('Asignando estado');
            $cliente->cli_estado = $request->activo == '1';
            Log::info('Estado asignado:', ['estado' => $cliente->cli_estado]);
            
            // Es Consultor
            Log::info('Asignando es_consultor');
            $cliente->es_consultor = $request->has('es_consultor') && $request->es_consultor == '1';
            Log::info('Es consultor asignado:', ['es_consultor' => $cliente->es_consultor]);
            
            // Fecha de alta
            Log::info('Asignando fecha de alta');
            $cliente->cli_fechaalta = $request->fecha_alta ?? now()->format('Y-m-d');
            Log::info('Fecha de alta asignada:', ['fecha' => $cliente->cli_fechaalta]);
            
            // Zona
            Log::info('Asignando zona');
            $cliente->cli_codigozon = $request->zona_codigo ? str_pad($request->zona_codigo, 5, ' ', STR_PAD_RIGHT) : null;
            Log::info('Zona asignada:', ['zona' => $cliente->cli_codigozon ? trim($cliente->cli_codigozon) : null]);
            
            // Autoriza
            Log::info('Asignando autoriza');
            $cliente->cli_autoriza = $request->autoriza_codigo ? str_pad($request->autoriza_codigo, 20, ' ', STR_PAD_RIGHT) : null;
            
            // Rubro
            Log::info('Asignando rubro');
            $cliente->cli_codigocrub = $request->rubro_codigo ? str_pad($request->rubro_codigo, 5, ' ', STR_PAD_RIGHT) : null;
            Log::info('Rubro asignado:', ['rubro' => $cliente->cli_codigocrub ? trim($cliente->cli_codigocrub) : null]);
            
            // Carpeta (campo eliminado, mantener null)
            $cliente->cli_carpeta = null;
            
            // Documentación/Observaciones generales (campo eliminado)
            // Nota: cli_obsgeneral se usa para el checkbox obs_general más adelante, no asignar aquí
            
            // Zona comercial (campo eliminado, mantener null)
            $cliente->cli_zonacom = null;
            
            // Promotor
            $cliente->cli_promotor = $request->promotor_codigo ? str_pad($request->promotor_codigo, 20, ' ', STR_PAD_RIGHT) : null;
            
            // Fecha de modificación
            if ($request->fecha_modif) {
                $cliente->cli_ultfot = $request->fecha_modif;
            }

            // Campos de contacto
            $cliente->cli_contacto = $request->contacto ? str_pad($request->contacto, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_contacto1 = $request->contacto1 ? str_pad($request->contacto1, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_contacto2 = $request->contacto2 ? str_pad($request->contacto2, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_contacto3 = $request->contacto3 ? str_pad($request->contacto3, 30, ' ', STR_PAD_RIGHT) : null;
            
            // Campos de teléfono
            $cliente->cli_preftel = $request->preftel ? str_pad($request->preftel, 10, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono = $request->telefono ? str_pad($request->telefono, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono1 = $request->telefono1 ? str_pad($request->telefono1, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono2 = $request->telefono2 ? str_pad($request->telefono2, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono3 = $request->telefono3 ? str_pad($request->telefono3, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono4 = $request->telefono4 ? str_pad($request->telefono4, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono5 = $request->telefono5 ? str_pad($request->telefono5, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono6 = $request->telefono6 ? str_pad($request->telefono6, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono7 = $request->telefono7 ? str_pad($request->telefono7, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono8 = $request->telefono8 ? str_pad($request->telefono8, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono9 = $request->telefono9 ? str_pad($request->telefono9, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono10 = $request->telefono10 ? str_pad($request->telefono10, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono11 = $request->telefono11 ? str_pad($request->telefono11, 20, ' ', STR_PAD_RIGHT) : null;
            
            // Campos de teléfonos de pago
            $cliente->cli_telpago1 = $request->tel_pago1 ? str_pad($request->tel_pago1, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telpago2 = $request->tel_pago2 ? str_pad($request->tel_pago2, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telpago3 = $request->tel_pago3 ? str_pad($request->tel_pago3, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telpago4 = $request->tel_pago4 ? str_pad($request->tel_pago4, 20, ' ', STR_PAD_RIGHT) : null;
            
            // Campos de horarios (campos eliminados, mantener null)
            $cliente->cli_horario1 = null;
            $cliente->cli_horario2 = null;
            
            // Campo fax (campo eliminado, mantener null)
            $cliente->cli_fax = null;
            
            // Campos de email y web
            $cliente->cli_email = $request->email ? str_pad($request->email, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_email2 = $request->email2 ? str_pad($request->email2, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_email3 = $request->email3 ? str_pad($request->email3, 30, ' ', STR_PAD_RIGHT) : null;
            // Página web (campo eliminado, mantener null)
            $cliente->cli_webpage = null;
            
            // Campos adicionales
            $cliente->cli_generico = $request->generico ? $request->generico : 'N';
            $cliente->cli_importecredito = $request->importe_credito ? floatval($request->importe_credito) : 0.00;
            $cliente->cli_contactopago = $request->contacto_pago ? str_pad($request->contacto_pago, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_vctomax = $request->vcto_max ? str_pad($request->vcto_max, 3, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_usuario = $request->usuario ? str_pad($request->usuario, 15, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_mailing = $request->mailing ? str_pad($request->mailing, 2, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_diapgo = $request->dia_pago ? str_pad($request->dia_pago, 3, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_diarec = $request->dia_rec ? str_pad($request->dia_rec, 12, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_lapsomax = $request->lapso_max ? intval($request->lapso_max) : null;
            $cliente->cli_montomax = $request->monto_max ? floatval($request->monto_max) : null;
            $cliente->cli_factmax = $request->fact_max ? intval($request->fact_max) : null;
            
            // Campos booleanos
            $cliente->cli_muestras = $request->has('muestras') ? true : false;
            $cliente->cli_informe = $request->has('informe') ? true : false;
            $cliente->cli_facturar = $request->has('facturar') ? true : false;
            $cliente->cli_obsgeneral = $request->has('obs_general') ? true : false;
            
            // Campos adicionales de facturación
            $cliente->cli_debito = $request->debito ? floatval($request->debito) : 0.0000;
            $cliente->cli_ultot = $request->ultima_ot ? str_pad($request->ultima_ot, 15, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_causa = $request->causa ? str_pad($request->causa, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codalt = $request->codigo_alt ? str_pad($request->codigo_alt, 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_interno = $request->interno ? str_pad($request->interno, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_externo = $request->externo ? str_pad($request->externo, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_calidad = $request->calidad ? str_pad($request->calidad, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_gestion = $request->gestion ? str_pad($request->gestion, 20, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_periodicidad = $request->periodicidad ? $request->periodicidad : null;
            $cliente->cli_obscoti = $request->obs_coti ? $request->obs_coti : null;
            $cliente->cli_codigotrans = $request->codigo_trans ? str_pad($request->codigo_trans, 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigoven = $request->codigo_ven ? intval($request->codigo_ven) : null;
            $cliente->cli_fechaultcompra = $request->fecha_ult_compra ? $request->fecha_ult_compra : null;
            $cliente->cli_codigorepar = $request->codigo_repar ? intval($request->codigo_repar) : null;
            $cliente->cli_codigocomi = $request->codigo_comi ? str_pad($request->codigo_comi, 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigocanal = $request->codigo_canal ? str_pad($request->codigo_canal, 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_idcontacto = $request->id_contacto ? intval($request->id_contacto) : null;
            $cliente->cli_fleteabona = $request->flete_abona ? $request->flete_abona : null;
            $cliente->cli_tipoiibb = $request->tipo_iibb ? $request->tipo_iibb : null;
            $cliente->cli_numeroiibb = $request->numero_iibb ? str_pad($request->numero_iibb, 15, ' ', STR_PAD_RIGHT) : null;
            
            // Campos de descuentos adicionales
            $cliente->cli_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje')
                ? floatval($request->sector_laboratorio_porcentaje)
                : 0.00;
            $cliente->cli_sector_higiene_pct = $request->filled('sector_higiene_porcentaje')
                ? floatval($request->sector_higiene_porcentaje)
                : 0.00;
            $cliente->cli_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje')
                ? floatval($request->sector_microbiologia_porcentaje)
                : 0.00;
            $cliente->cli_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje')
                ? floatval($request->sector_cromatografia_porcentaje)
                : 0.00;
            
            // Campo observaciones generales (campo documentacion eliminado, mantener null)
            $cliente->cli_obs = null;

            // Campos de facturación
            Log::info('=== INICIANDO ASIGNACIÓN DE CAMPOS DE FACTURACIÓN ===');
            
            Log::info('Asignando condición IVA');
            if ($request->condicion_iva_codigo) {
                $cliente->cli_codigociva = str_pad($request->condicion_iva_codigo, 5, ' ', STR_PAD_RIGHT);
                Log::info('Condición IVA asignada:', ['codigo' => trim($cliente->cli_codigociva)]);
            } else {
                Log::info('No se proporcionó condición IVA');
            }
            
            Log::info('Asignando condición de pago');
            if ($request->condicion_pago) {
                $cliente->cli_codigopag = str_pad($request->condicion_pago, 5, ' ', STR_PAD_RIGHT);
                Log::info('Condición de pago asignada:', ['codigo' => trim($cliente->cli_codigopag)]);
            } else {
                Log::info('No se proporcionó condición de pago');
            }
            
            // Tipo de cliente (campo eliminado, mantener null)
            $cliente->cli_codigotcli = null;
            
            // Lista de precios (campo eliminado, mantener null)
            $cliente->cli_codigolp = null;
            
            // Asignar número de precio
            Log::info('Asignando número de precio');
            $cliente->cli_nroprecio = $request->nro_lp ? intval($request->nro_lp) : 1;
            Log::info('Número de precio asignado:', ['nro' => $cliente->cli_nroprecio]);
            
            // CUIT - Solo guardar el número, máximo 13 caracteres
            if ($request->cuit_numero) {
                $cuitNumero = trim($request->cuit_numero);
                // Truncar si excede 13 caracteres
                if (strlen($cuitNumero) > 13) {
                    $cuitNumero = substr($cuitNumero, 0, 13);
                }
                $cliente->cli_cuit = str_pad($cuitNumero, 13, ' ', STR_PAD_RIGHT);
            }
            
            // Guardar el tipo de CUIT en cli_formcuit (solo primera letra si es character(1))
            if ($request->cuit_tipo) {
                $cliente->cli_formcuit = substr($request->cuit_tipo, 0, 1);
            }
            
            // Tipo de factura - no hay campo específico en la BD, se podría usar cli_factura
            if ($request->tipo_factura) {
                $cliente->cli_factura = str_pad($request->tipo_factura, 25, ' ', STR_PAD_RIGHT);
            }
            
            // Descuento
            if ($request->descuento) {
                $cliente->cli_descuentoglobal = floatval($request->descuento);
            }
            
            // Observaciones de facturación
            $cliente->cli_obs1 = $request->observaciones_facturacion;

            Log::info('=== PREPARANDO PARA GUARDAR CLIENTE ===');
            Log::info('Datos finales del cliente antes de save:', [
                'cli_codigo' => "'" . $cliente->cli_codigo . "'",
                'cli_razonsocial' => "'" . $cliente->cli_razonsocial . "'",
                'cli_estado' => $cliente->cli_estado,
                'cli_codigociva' => $cliente->cli_codigociva ? "'" . $cliente->cli_codigociva . "'" : 'NULL',
                'cli_codigopag' => $cliente->cli_codigopag ? "'" . $cliente->cli_codigopag . "'" : 'NULL',
                'cli_codigozon' => $cliente->cli_codigozon ? "'" . $cliente->cli_codigozon . "'" : 'NULL',
                'cli_codigolp' => $cliente->cli_codigolp ? "'" . $cliente->cli_codigolp . "'" : 'NULL',
                'cli_nroprecio' => $cliente->cli_nroprecio,
                'cli_codigocrub' => $cliente->cli_codigocrub ? "'" . $cliente->cli_codigocrub . "'" : 'NULL'
            ]);

            Log::info('Ejecutando save()...');
            
            // Intentar guardar con captura de error SQL detallado
            try {
                $cliente->save();
                Log::info('Save() ejecutado exitosamente');
            } catch (\Illuminate\Database\QueryException $e) {
                Log::error('=== ERROR SQL AL GUARDAR CLIENTE ===', [
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'bindings' => $e->getBindings(),
                    'sql' => $e->getSql(),
                    'cliente_data' => $cliente->toArray()
                ]);
                throw $e;
            }

            // Guardar empresas relacionadas (múltiples)
            if ($request->has('empresas_relacionadas') && is_array($request->empresas_relacionadas)) {
                foreach ($request->empresas_relacionadas as $empresaData) {
                    if (!empty($empresaData['razon_social'])) {
                        ClienteEmpresaRelacionada::create([
                            'cli_codigo' => $cliente->cli_codigo,
                            'razon_social' => trim($empresaData['razon_social']),
                            'cuit' => !empty($empresaData['cuit']) ? trim($empresaData['cuit']) : null,
                            'direcciones' => !empty($empresaData['direcciones']) ? trim($empresaData['direcciones']) : null,
                            'localidad' => !empty($empresaData['localidad']) ? trim($empresaData['localidad']) : null,
                            'partido' => !empty($empresaData['partido']) ? trim($empresaData['partido']) : null,
                            'contacto' => !empty($empresaData['contacto']) ? trim($empresaData['contacto']) : null,
                        ]);
                    }
                }
            }

            // Guardar razones sociales de facturación (múltiples)
            if ($request->has('razones_sociales') && is_array($request->razones_sociales)) {
                // Primero, verificar si hay alguna marcada como predeterminada
                $hayPredeterminada = false;
                foreach ($request->razones_sociales as $razonSocialData) {
                    if (!empty($razonSocialData['es_predeterminada']) && $razonSocialData['es_predeterminada'] == '1') {
                        $hayPredeterminada = true;
                        break;
                    }
                }
                
                foreach ($request->razones_sociales as $razonSocialData) {
                    if (!empty($razonSocialData['razon_social'])) {
                        ClienteRazonSocialFacturacion::create([
                            'cli_codigo' => $cliente->cli_codigo,
                            'razon_social' => trim($razonSocialData['razon_social']),
                            'cuit' => !empty($razonSocialData['cuit']) ? trim($razonSocialData['cuit']) : null,
                            'direccion' => !empty($razonSocialData['direccion']) ? trim($razonSocialData['direccion']) : null,
                            'condicion_iva' => !empty($razonSocialData['condicion_iva']) ? trim($razonSocialData['condicion_iva']) : null,
                            'condicion_iva_desc' => !empty($razonSocialData['condicion_iva_desc']) ? trim($razonSocialData['condicion_iva_desc']) : null,
                            'condicion_pago' => !empty($razonSocialData['condicion_pago']) ? trim($razonSocialData['condicion_pago']) : null,
                            'condicion_pago_desc' => !empty($razonSocialData['condicion_pago_desc']) ? trim($razonSocialData['condicion_pago_desc']) : null,
                            'tipo_factura' => !empty($razonSocialData['tipo_factura']) ? trim($razonSocialData['tipo_factura']) : null,
                            'es_predeterminada' => !empty($razonSocialData['es_predeterminada']) && ($razonSocialData['es_predeterminada'] == '1' || $razonSocialData['es_predeterminada'] === 1 || $razonSocialData['es_predeterminada'] === true),
                        ]);
                    }
                }
            }

            Log::info('Cliente creado exitosamente', ['codigo' => trim($cliente->cli_codigo)]);
            Log::info('=== FIN CREACIÓN DE CLIENTE EXITOSA ===');

            return redirect()->route('clientes.index')
                ->with('success', 'Cliente creado exitosamente con código: ' . trim($cliente->cli_codigo));

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERROR DE VALIDACIÓN ===', [
                'errors' => $e->validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERROR AL CREAR CLIENTE ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Error al crear el cliente: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    public function edit($id)
    {
        $cliente = Clientes::find($id);
        
        // Cargar datos para los selectores (mismo que en create)
        $condicionesIva = CondicionIva::where('civa_estado', true)
            ->orderBy('civa_descripcion')
            ->get();
        
        $zonas = Zona::where('zon_estado', true)
            ->orderBy('zon_descripcion')
            ->get();
        
        $condicionesPago = CondicionPago::where('pag_estado', true)
            ->orderBy('pag_descripcion')
            ->get();

        // Cargar listas de precios si existe la tabla
        try {
            $listasPrecios = ListaPrecio::where('lp_estado', true)
                ->orderBy('lp_descripcion')
                ->get();
        } catch (\Exception $e) {
            // Si no existe la tabla lp, usar valores por defecto
            $listasPrecios = collect([
                (object)['lp_codigo' => 'UNO  ', 'lp_descripcion' => 'Lista Principal'],
                (object)['lp_codigo' => 'DOS  ', 'lp_descripcion' => 'Lista Secundaria'],
                (object)['lp_codigo' => 'TRES ', 'lp_descripcion' => 'Lista Especial'],
            ]);
        }

        // Cargar tipos de cliente
        $tiposCliente = TipoCliente::orderBy('tcli_descripcion')->get();
        
        // Cargar empresas relacionadas y preparar para JavaScript
        $empresasRelacionadas = $cliente->empresasRelacionadas()->orderBy('razon_social')->get();
        $empresasRelacionadasJson = $empresasRelacionadas->map(function($empresa) {
            return [
                'id' => $empresa->id,
                'razon_social' => trim($empresa->razon_social),
                'cuit' => trim($empresa->cuit ?? ''),
                'direcciones' => trim($empresa->direcciones ?? ''),
                'localidad' => trim($empresa->localidad ?? ''),
                'partido' => trim($empresa->partido ?? ''),
                'contacto' => trim($empresa->contacto ?? '')
            ];
        })->toArray();
        
        // Cargar razones sociales de facturación y preparar para JavaScript
        $razonesSociales = ClienteRazonSocialFacturacion::where('cli_codigo', $cliente->cli_codigo)
            ->orderBy('es_predeterminada', 'desc')
            ->orderBy('razon_social')
            ->get();
        $razonesSocialesJson = $razonesSociales->map(function($razonSocial) {
            return [
                'id' => $razonSocial->id,
                'razon_social' => trim($razonSocial->razon_social),
                'cuit' => trim($razonSocial->cuit ?? ''),
                'direccion' => trim($razonSocial->direccion ?? ''),
                'condicion_iva' => trim($razonSocial->condicion_iva ?? ''),
                'condicion_iva_desc' => trim($razonSocial->condicion_iva_desc ?? ''),
                'condicion_pago' => trim($razonSocial->condicion_pago ?? ''),
                'condicion_pago_desc' => trim($razonSocial->condicion_pago_desc ?? ''),
                'tipo_factura' => trim($razonSocial->tipo_factura ?? ''),
                'es_predeterminada' => $razonSocial->es_predeterminada ?? false
            ];
        })->toArray();
        
        return View::make('clientes.edit', compact('cliente', 'condicionesIva', 'zonas', 'condicionesPago', 'listasPrecios', 'tiposCliente', 'empresasRelacionadas', 'empresasRelacionadasJson', 'razonesSociales', 'razonesSocialesJson'));
    }
    
    public function update(Request $request, $id)
    {
        try {
            Log::info('=== INICIO ACTUALIZACIÓN DE CLIENTE ===', ['id' => $id]);
            Log::info('Datos recibidos en update:', $request->all());
            
            // Validar datos básicos
            $request->validate([
                'razon_social' => 'required|string|max:255',
                'activo' => 'required|boolean'
            ], [
                'razon_social.required' => 'La Razón Social es obligatoria',
                'razon_social.max' => 'La Razón Social no puede superar los 255 caracteres',
                'activo.required' => 'El estado es obligatorio'
            ]);

            $cliente = Clientes::find($id);
            if (!$cliente) {
                return redirect()->back()
                    ->with('error', 'Cliente no encontrado')
                    ->withInput();
            }

            // Campos principales
            $cliente->cli_razonsocial = str_pad($request->razon_social ?? '', 60, ' ', STR_PAD_RIGHT);
            $cliente->cli_fantasia = $request->fantasia ? str_pad($request->fantasia, 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_direccion = $request->direccion ? str_pad($request->direccion, 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_partido = $request->partido ? str_pad($request->partido, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_localidad = $request->localidad ? str_pad($request->localidad, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigopostal = $request->codigo_postal ? str_pad($request->codigo_postal, 10, ' ', STR_PAD_RIGHT) : null;
            
            // Campos de país y provincia
            $paisCodigo = $request->pais_codigo ? substr(trim($request->pais_codigo), 0, 5) : 'ARG';
            $cliente->cli_codigopais = str_pad($paisCodigo, 5, ' ', STR_PAD_RIGHT);
            
            $provinciaCodigo = $request->provincia_codigo ? substr(trim($request->provincia_codigo), 0, 5) : null;
            $cliente->cli_codigoprv = $provinciaCodigo ? str_pad($provinciaCodigo, 5, ' ', STR_PAD_RIGHT) : null;
            
            // Estado
            $cliente->cli_estado = $request->activo == '1';
            
            // Es Consultor
            $cliente->es_consultor = $request->has('es_consultor') && $request->es_consultor == '1';
            
            // Fecha de alta
            $cliente->cli_fechaalta = $request->fecha_alta ?? $cliente->cli_fechaalta;
            
            // Zona
            $cliente->cli_codigozon = $request->zona_codigo ? str_pad($request->zona_codigo, 5, ' ', STR_PAD_RIGHT) : null;
            
            // Autoriza
            $cliente->cli_autoriza = $request->autoriza_codigo ? str_pad($request->autoriza_codigo, 20, ' ', STR_PAD_RIGHT) : null;
            
            // Rubro
            $cliente->cli_codigocrub = $request->rubro_codigo ? str_pad($request->rubro_codigo, 5, ' ', STR_PAD_RIGHT) : null;
            
            // Carpeta (campo eliminado, mantener null)
            $cliente->cli_carpeta = null;
            
            // Documentación/Observaciones generales (campo eliminado, mantener null)
            // Nota: cli_obsgeneral también se usa para el checkbox obs_general, no lo toquemos aquí
            // $cliente->cli_obsgeneral = null;
            
            // Zona comercial (campo eliminado, mantener null)
            $cliente->cli_zonacom = null;
            
            // Fecha de modificación
            if ($request->fecha_modif) {
                $cliente->cli_ultfot = $request->fecha_modif;
            }

            // Campos de contacto
            $cliente->cli_telefono = $request->telefono ? str_pad($request->telefono, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_telefono1 = $request->telefono1 ? str_pad($request->telefono1, 20, ' ', STR_PAD_RIGHT) : null;
            // Horarios (campos eliminados, mantener null)
            $cliente->cli_horario1 = null;
            $cliente->cli_horario2 = null;
            // Fax (campo eliminado, mantener null)
            $cliente->cli_fax = null;
            $cliente->cli_email = $request->email ? str_pad($request->email, 30, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_email2 = $request->email2 ? str_pad($request->email2, 30, ' ', STR_PAD_RIGHT) : null;
            // Página web (campo eliminado, mantener null)
            $cliente->cli_webpage = null;

            // Campos de facturación
            if ($request->condicion_iva_codigo) {
                $cliente->cli_codigociva = str_pad($request->condicion_iva_codigo, 5, ' ', STR_PAD_RIGHT);
            }
            
            if ($request->condicion_pago) {
                $cliente->cli_codigopag = str_pad($request->condicion_pago, 5, ' ', STR_PAD_RIGHT);
            }
            
            // Tipo de cliente (campo eliminado, mantener null)
            $cliente->cli_codigotcli = null;
            
            // Lista de precios (campo eliminado, mantener null)
            $cliente->cli_codigolp = null;
            
            // Asignar número de precio
            $cliente->cli_nroprecio = $request->nro_lp ? intval($request->nro_lp) : 1;
            
            // CUIT - Solo guardar el número, máximo 13 caracteres
            if ($request->cuit_numero) {
                $cuitNumero = trim($request->cuit_numero);
                if (strlen($cuitNumero) > 13) {
                    $cuitNumero = substr($cuitNumero, 0, 13);
                }
                $cliente->cli_cuit = str_pad($cuitNumero, 13, ' ', STR_PAD_RIGHT);
            }
            
            // Guardar el tipo de CUIT en cli_formcuit (solo primera letra)
            if ($request->cuit_tipo) {
                $cliente->cli_formcuit = substr($request->cuit_tipo, 0, 1);
            }
            
            // Tipo de factura
            if ($request->tipo_factura) {
                $cliente->cli_factura = str_pad($request->tipo_factura, 25, ' ', STR_PAD_RIGHT);
            }
            
            // Descuento
            if ($request->descuento) {
                $cliente->cli_descuentoglobal = floatval($request->descuento);
            }
            
            // Observaciones de facturación
            $cliente->cli_obs1 = $request->observaciones_facturacion;

            // Campos de empresas relacionadas
            $cliente->cli_rel_empresa_razon_social = $request->rel_empresa_razon_social ? str_pad($request->rel_empresa_razon_social, 255, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_rel_empresa_cuit = $request->rel_empresa_cuit ? str_pad($request->rel_empresa_cuit, 13, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_rel_empresa_direcciones = $request->rel_empresa_direcciones ? $request->rel_empresa_direcciones : null;
            $cliente->cli_rel_empresa_localidad = $request->rel_empresa_localidad ? str_pad($request->rel_empresa_localidad, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_rel_empresa_partido = $request->rel_empresa_partido ? str_pad($request->rel_empresa_partido, 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_rel_empresa_contacto = $request->rel_empresa_contacto ? str_pad($request->rel_empresa_contacto, 100, ' ', STR_PAD_RIGHT) : null;

            $cliente->cli_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje')
                ? floatval($request->sector_laboratorio_porcentaje)
                : 0.00;
            $cliente->cli_sector_higiene_pct = $request->filled('sector_higiene_porcentaje')
                ? floatval($request->sector_higiene_porcentaje)
                : 0.00;
            $cliente->cli_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje')
                ? floatval($request->sector_microbiologia_porcentaje)
                : 0.00;
            $cliente->cli_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje')
                ? floatval($request->sector_cromatografia_porcentaje)
                : 0.00;

            // Guardar
            try {
                $cliente->save();
                Log::info('Cliente actualizado exitosamente', ['codigo' => trim($cliente->cli_codigo)]);
            } catch (\Illuminate\Database\QueryException $e) {
                Log::error('=== ERROR SQL AL ACTUALIZAR CLIENTE ===', [
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'cliente_data' => $cliente->toArray()
                ]);
                throw $e;
            }

            // Guardar empresas relacionadas (múltiples)
            // Primero eliminar las existentes
            ClienteEmpresaRelacionada::where('cli_codigo', $cliente->cli_codigo)->delete();
            
            // Guardar las nuevas empresas relacionadas
            if ($request->has('empresas_relacionadas') && is_array($request->empresas_relacionadas)) {
                foreach ($request->empresas_relacionadas as $empresaData) {
                    if (!empty($empresaData['razon_social'])) {
                        ClienteEmpresaRelacionada::create([
                            'cli_codigo' => $cliente->cli_codigo,
                            'razon_social' => trim($empresaData['razon_social']),
                            'cuit' => !empty($empresaData['cuit']) ? trim($empresaData['cuit']) : null,
                            'direcciones' => !empty($empresaData['direcciones']) ? trim($empresaData['direcciones']) : null,
                            'localidad' => !empty($empresaData['localidad']) ? trim($empresaData['localidad']) : null,
                            'partido' => !empty($empresaData['partido']) ? trim($empresaData['partido']) : null,
                            'contacto' => !empty($empresaData['contacto']) ? trim($empresaData['contacto']) : null,
                        ]);
                    }
                }
            }

            // Guardar razones sociales de facturación (múltiples)
            // Primero eliminar las existentes
            ClienteRazonSocialFacturacion::where('cli_codigo', $cliente->cli_codigo)->delete();
            
            // Guardar las nuevas razones sociales
            if ($request->has('razones_sociales') && is_array($request->razones_sociales)) {
                // Primero, verificar si hay alguna marcada como predeterminada
                $hayPredeterminada = false;
                foreach ($request->razones_sociales as $razonSocialData) {
                    if (!empty($razonSocialData['es_predeterminada']) && $razonSocialData['es_predeterminada'] == '1') {
                        $hayPredeterminada = true;
                        break;
                    }
                }
                
                foreach ($request->razones_sociales as $razonSocialData) {
                    if (!empty($razonSocialData['razon_social'])) {
                        ClienteRazonSocialFacturacion::create([
                            'cli_codigo' => $cliente->cli_codigo,
                            'razon_social' => trim($razonSocialData['razon_social']),
                            'cuit' => !empty($razonSocialData['cuit']) ? trim($razonSocialData['cuit']) : null,
                            'direccion' => !empty($razonSocialData['direccion']) ? trim($razonSocialData['direccion']) : null,
                            'condicion_iva' => !empty($razonSocialData['condicion_iva']) ? trim($razonSocialData['condicion_iva']) : null,
                            'condicion_iva_desc' => !empty($razonSocialData['condicion_iva_desc']) ? trim($razonSocialData['condicion_iva_desc']) : null,
                            'condicion_pago' => !empty($razonSocialData['condicion_pago']) ? trim($razonSocialData['condicion_pago']) : null,
                            'condicion_pago_desc' => !empty($razonSocialData['condicion_pago_desc']) ? trim($razonSocialData['condicion_pago_desc']) : null,
                            'tipo_factura' => !empty($razonSocialData['tipo_factura']) ? trim($razonSocialData['tipo_factura']) : null,
                            'es_predeterminada' => !empty($razonSocialData['es_predeterminada']) && ($razonSocialData['es_predeterminada'] == '1' || $razonSocialData['es_predeterminada'] === 1 || $razonSocialData['es_predeterminada'] === true),
                        ]);
                    }
                }
            }

            return redirect()->route('clientes.index')
                ->with('success', 'Cliente actualizado exitosamente');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERROR DE VALIDACIÓN ===', [
                'errors' => $e->validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERROR AL ACTUALIZAR CLIENTE ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->with('error', 'Error al actualizar el cliente: ' . $e->getMessage())
                ->withInput();
        }
    }
    
    public function destroy($id)
    {
        try {
            $cliente = Clientes::find($id);
            if (!$cliente) {
                return redirect()->route('clientes.index', request()->query())
                    ->with('error', 'Cliente no encontrado');
            }
            
            $cliente->delete();
            
            // Preservar los filtros activos en la redirección
            return redirect()->route('clientes.index', request()->query())
                ->with('success', 'Cliente eliminado exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al eliminar cliente:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('clientes.index', request()->query())
                ->with('error', 'Error al eliminar el cliente');
        }
    }
    
}