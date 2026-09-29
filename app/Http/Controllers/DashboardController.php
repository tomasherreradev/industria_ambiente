<?php

namespace App\Http\Controllers;

use App\Models\Coti;
use App\Models\CotioInstancia;
use App\Models\Vehiculo;
use App\Models\InventarioMuestreo;
use App\Models\InventarioLab;
use App\Models\Informes;
use App\Models\User;
use App\Models\Zona;
use App\Models\Metodo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MuestrasMuestreoExport;
use App\Exports\AnalisisExport;
use App\Models\Matriz;
use App\Support\OrdenesAccesoPorSector;
use App\Support\OrdenesLaboratorioListado;
use App\Support\PrioridadListado;
use App\Support\CotizacionClienteEtiqueta;

class DashboardController extends Controller
{



public function index()
{
    // Resumen general
    $totalCotizaciones = Coti::count();
    $cotizacionesRecientes = Coti::with(['cliente', 'sucursal'])
        ->orderBy('coti_fechaalta', 'desc')->take(5)->get();
    CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($cotizacionesRecientes);
    
    // Estadísticas de muestreo
    $muestrasTotales = CotioInstancia::where('cotio_subitem', 0)->count();
    $muestrasPendientes = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado', 'coordinado muestreo')->count();
    $muestrasEnProceso = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado', 'en revision muestreo')->count();
    $muestrasFinalizadas = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado', 'muestreado')->count();
    
    // Estadísticas de análisis
    $analisisTotales = CotioInstancia::where('cotio_subitem', 0)
        ->where('enable_ot', true)->count();  
    $analisisPendientes = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado_analisis', 'coordinado analisis')->count();
    $analisisEnProceso = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado_analisis', 'en revision analisis')->count();
    $analisisFinalizados = CotioInstancia::where('cotio_subitem', 0)
        ->where('cotio_estado_analisis', 'analizado')->count();
    
    // Muestras próximas a vencer
    $muestrasProximas = CotioInstancia::where('cotio_subitem', 0)
        ->where('fecha_fin_muestreo', '>=', now())
        ->where('fecha_fin_muestreo', '<=', now()->addDays(7))
        ->orderBy('fecha_fin_muestreo')
        ->get();
    
    // Vehículos en uso
    $vehiculosOcupados = Vehiculo::where('estado', 'ocupado')->with('cotioInstancias')->get();
    
    // Informes
    $informesTotales = CotioInstancia::where('cotio_subitem', 0)->where('enable_inform', true)->count();
    
    $modulosAcceso = config('admin_modulos_acceso', []);

    return view('dashboard.admin', compact(
        'totalCotizaciones',
        'cotizacionesRecientes',
        'muestrasTotales',
        'muestrasPendientes',
        'muestrasEnProceso',
        'muestrasFinalizadas',
        'analisisTotales',
        'analisisPendientes',
        'analisisEnProceso',
        'analisisFinalizados',
        'muestrasProximas',
        'vehiculosOcupados',
        'informesTotales',
        'modulosAcceso'
    ));
}


public function dashboardMuestreo(Request $request)
{
    $userCodigo = Auth::user()->usu_codigo;
    $estadoFiltro = $request->get('estado', 'all');
    $muestreadorFiltro = $request->get('muestreador', 'all');
    $vehiculoFiltro = $request->get('vehiculo', 'all');
    $zonaFiltro = $request->get('zona', 'all');
    $cuotasFiltro = $request->get('cuotas', 'all');

    Log::info('[Dashboard Muestreo] Iniciando consulta', [
        'user_codigo' => $userCodigo,
        'estado_filtro' => $estadoFiltro,
        'muestreador_filtro' => $muestreadorFiltro,
        'vehiculo_filtro' => $vehiculoFiltro,
        'zona_filtro' => $zonaFiltro,
        'cuotas_filtro' => $cuotasFiltro
    ]);

    // Verificar si el usuario es coordinador_muestreo
    $esCoordinadorMuestreo = Auth::user()->rol === 'coordinador_muestreo';
    $veTodasLasMuestrasMuestreo = $esCoordinadorMuestreo || (int) Auth::user()->usu_nivel >= 900;

    $restringirAlcanceMuestrasUsuario = function ($instanciaQuery) use ($userCodigo, $veTodasLasMuestrasMuestreo) {
        if ($veTodasLasMuestrasMuestreo) {
            return;
        }

        $instanciaQuery->where(function ($query) use ($userCodigo) {
            $query->where('cotio_instancias.coordinador_codigo', $userCodigo)
                ->orWhereHas('responsablesMuestreo', function ($q) use ($userCodigo) {
                    $q->where('instancia_responsable_muestreo.usu_codigo', $userCodigo);
                });
        });
    };

    $filtroInstanciasMuestreoActivas = function ($instanciaQuery) use ($restringirAlcanceMuestrasUsuario) {
        $instanciaQuery->where('cotio_instancias.cotio_subitem', 0)
            ->where(function ($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            });
        $restringirAlcanceMuestrasUsuario($instanciaQuery);
    };
    
    // Verificar si es día 1 del mes para filtrar por mes actual
    $esDiaUno = now()->day === 1;
    $fechaInicioMes = now()->startOfMonth();
    $fechaFinMes = now()->endOfMonth();
    
    Log::info('[Dashboard Muestreo] Información del usuario', [
        'user_codigo' => $userCodigo,
        'rol' => Auth::user()->rol,
        'es_coordinador_muestreo' => $esCoordinadorMuestreo,
        've_todas_las_muestras_muestreo' => $veTodasLasMuestrasMuestreo,
        'es_dia_uno' => $esDiaUno,
        'fecha_inicio_mes' => $fechaInicioMes->format('Y-m-d'),
        'fecha_fin_mes' => $fechaFinMes->format('Y-m-d')
    ]);

    // Base query para muestras (incluye las ya pasadas a laboratorio/documentación)
    $query = CotioInstancia::where('cotio_instancias.cotio_subitem', 0);
    $restringirAlcanceMuestrasUsuario($query);
    if ($veTodasLasMuestrasMuestreo) {
        Log::info('[Dashboard Muestreo] Alcance completo (coordinador_muestreo o admin)');
    }

    // Contar muestras antes de aplicar filtros adicionales
    $muestrasAntesFiltros = $query->count();
    Log::info('[Dashboard Muestreo] Muestras visibles para el usuario (antes de filtros adicionales)', [
        'count' => $muestrasAntesFiltros
    ]);

    // Aplicar filtro de muestreador - SOLO filtrar por muestreador
    if ($muestreadorFiltro !== 'all') {
        Log::info('[Dashboard Muestreo] Aplicando filtro de muestreador', [
            'muestreador_codigo' => $muestreadorFiltro
        ]);
        
        // Verificar cuántas muestras tiene este muestreador asignadas (sin filtro de usuario)
        $totalMuestrasMuestreador = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
            ->whereHas('responsablesMuestreo', function($q) use ($muestreadorFiltro) {
                $q->where('instancia_responsable_muestreo.usu_codigo', $muestreadorFiltro);
            })
            ->count();
        
        Log::info('[Dashboard Muestreo] Total de muestras asignadas al muestreador (sin filtro de usuario)', [
            'muestreador_codigo' => $muestreadorFiltro,
            'total_muestras' => $totalMuestrasMuestreador
        ]);
        
        // Aplicar el filtro de muestreador a la consulta base
        $query->whereHas('responsablesMuestreo', function($q) use ($muestreadorFiltro) {
            $q->where('instancia_responsable_muestreo.usu_codigo', $muestreadorFiltro);
        });
        
        // Contar muestras después del filtro de muestreador
        $muestrasDespuesFiltro = $query->count();
        Log::info('[Dashboard Muestreo] Muestras después de filtro de muestreador', [
            'count' => $muestrasDespuesFiltro
        ]);
        
        // Obtener IDs de muestras para debugging
        $muestrasIds = $query->pluck('id')->toArray();
        Log::info('[Dashboard Muestreo] IDs de muestras encontradas', [
            'ids' => $muestrasIds,
            'count' => count($muestrasIds)
        ]);
    }

    // Aplicar filtro de estado si no es 'all'
    if ($estadoFiltro !== 'all') {
        if ($estadoFiltro === 'proximos') {
            $query->where('fecha_inicio_muestreo', '>=', now())
                  ->where('fecha_inicio_muestreo', '<=', now()->addDays(3));
        } else {
            $query->where('cotio_estado', $estadoFiltro);
        }
    }

    // Aplicar filtro de vehículo
    if ($vehiculoFiltro !== 'all') {
        $query->where('vehiculo_asignado', $vehiculoFiltro);
    }

    // Aplicar filtro de zona
    if ($zonaFiltro !== 'all') {
        $query->whereHas('cotizacion.cliente', function($q) use ($zonaFiltro) {
            $q->where('cli_codigozon', $zonaFiltro);
        });
    }

    // Aplicar filtro de cuotas
    if ($cuotasFiltro !== 'all') {
        $query->whereHas('cotizacion', function($q) use ($cuotasFiltro) {
            $q->where('coti_cuotas', $cuotasFiltro === 'yes' ? 1 : 0);
        });
    }

    // Si es día 1, filtrar por muestras que finalizan en el mes actual
    if ($esDiaUno) {
        $query->whereBetween('fecha_fin_muestreo', [$fechaInicioMes, $fechaFinMes]);
        Log::info('[Dashboard Muestreo] Aplicando filtro de mes (día 1)', [
            'fecha_inicio' => $fechaInicioMes->format('Y-m-d'),
            'fecha_fin' => $fechaFinMes->format('Y-m-d')
        ]);
    }

    // Obtener muestras con paginación
    $muestras = $query->with(['cotizacion.cliente', 'cotizacion.sucursal', 'cotizacion.cliente.zona', 'cotizacion.matriz', 'vehiculo', 'responsablesMuestreo'])
        ->orderBy('cotio_instancias.fecha_fin_muestreo')
        ->paginate(10)
        ->appends($request->query());

    CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
        $muestras->getCollection()->map->cotizacion->unique(fn ($c) => $c->coti_num)->filter()->values()
    );
    
    Log::info('[Dashboard Muestreo] Resultado final de la consulta', [
        'total_muestras' => $muestras->total(),
        'muestras_en_pagina' => $muestras->count(),
        'muestras_ids' => $muestras->pluck('id')->toArray(),
        'muestras_con_responsables' => $muestras->map(function($muestra) {
            return [
                'id' => $muestra->id,
                'cotio_numcoti' => $muestra->cotio_numcoti,
                'responsables' => $muestra->responsablesMuestreo->pluck('usu_codigo')->toArray()
            ];
        })->toArray()
    ]);
    
    // Función helper para aplicar filtros base a estadísticas
    $aplicarFiltrosBase = function ($query, bool $incluirPasadasLaboratorio = false) use ($esDiaUno, $fechaInicioMes, $fechaFinMes) {
        $query->where('cotio_subitem', 0);

        if (! $incluirPasadasLaboratorio) {
            $query->where(function ($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            });
        }

        // Si es día 1, filtrar por mes
        if ($esDiaUno) {
            $query->whereBetween('fecha_fin_muestreo', [$fechaInicioMes, $fechaFinMes]);
        }

        return $query;
    };
    
    // Estadísticas (sin filtro de estado)
    $totalMuestras = $aplicarFiltrosBase(CotioInstancia::query())->count();
    
    // Cotizaciones aprobadas (estado 'A')
    $cotizacionesAprobadas = Coti::where('coti_estado', 'LIKE', 'A%')->count();
    
    $pendientes = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'coordinado muestreo')
        ->count();
    
    $enProceso = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'en revision muestreo')
        ->count();
    
    // Finalizadas = asignadas en muestreo completado; siguen contando aunque ya pasaron a laboratorio (enable_ot).
    $finalizadas = $aplicarFiltrosBase(CotioInstancia::query(), true)
        ->where('cotio_estado', 'muestreado')
        ->whereHas('responsablesMuestreo')
        ->count();
    
    $suspendidas = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'suspension')
        ->count();
    
    
    // Muestras próximas
    $muestrasProximas = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
        ->where(function($q) {
            $q->where('enable_ot', false)->orWhereNull('enable_ot');
        })
        ->where('fecha_inicio_muestreo', '>=', now())
        ->where('fecha_inicio_muestreo', '<=', now()->addDays(3))
        ->with(['cotizacion', 'vehiculo'])
        ->orderBy('fecha_inicio_muestreo')
        ->get();
    
    // Vehículos asignados
    $vehiculosAsignados = Vehiculo::whereIn('id', 
        $muestras->whereNotNull('vehiculo_asignado')->pluck('vehiculo_asignado')->unique()
    )->get();
    
    // Herramientas en uso
    $herramientasEnUso = InventarioMuestreo::whereHas('cotioInstancias', function($q) {
        $q->where('cotio_instancias.cotio_subitem', 0)
          ->where(function($q2) {
              $q2->where('enable_ot', false)->orWhereNull('enable_ot');
          })
          ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    })->withCount(['cotioInstancias' => function($q) {
        $q->where('cotio_instancias.cotio_subitem', 0)
          ->where(function($q2) {
              $q2->where('enable_ot', false)->orWhereNull('enable_ot');
          })
          ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    }])->get();
    
    // Obtener muestreadores disponibles (usuarios con muestras en el alcance visible)
    $muestreadores = User::whereHas('instanciasMuestreo', $filtroInstanciasMuestreoActivas)
        ->orderBy('usu_descripcion')
        ->get();
    
    // Obtener vehículos disponibles (vehículos con muestras en el alcance visible)
    $vehiculosDisponibles = Vehiculo::whereHas('cotioInstancias', $filtroInstanciasMuestreoActivas)
        ->orderBy('patente')
        ->get();
    
    // Obtener zonas disponibles (zonas de clientes con muestras en el alcance visible)
    $zonasCodigosQuery = CotioInstancia::query();
    $filtroInstanciasMuestreoActivas($zonasCodigosQuery);
    $zonasCodigos = $zonasCodigosQuery
        ->join('coti', 'cotio_instancias.cotio_numcoti', '=', 'coti.coti_num')
        ->join('cli', 'coti.coti_codigocli', '=', 'cli.cli_codigo')
        ->whereNotNull('cli.cli_codigozon')
        ->distinct()
        ->pluck('cli.cli_codigozon');
    
    $zonasDisponibles = \App\Models\Zona::whereIn('zon_codigo', $zonasCodigos)
        ->orderBy('zon_descripcion')
        ->get();
    
    return view('dashboard.muestreo', compact(
        'muestras',
        'totalMuestras',
        'cotizacionesAprobadas',
        'pendientes',
        'enProceso',
        'finalizadas',
        'suspendidas',
        'muestrasProximas',
        'vehiculosAsignados',
        'herramientasEnUso',
        'estadoFiltro',
        'muestreadorFiltro',
        'vehiculoFiltro',
        'zonaFiltro',
        'cuotasFiltro',
        'muestreadores',
        'vehiculosDisponibles',
        'zonasDisponibles',
        'esDiaUno'
    ));
}

public function exportarMuestrasMuestreo(Request $request)
{
    try {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $userCodigo = Auth::user()->usu_codigo;
        $esCoordinadorMuestreo = Auth::user()->rol === 'coordinador_muestreo';
        $veTodasLasMuestrasMuestreo = $esCoordinadorMuestreo || (int) Auth::user()->usu_nivel >= 900;

        $fechaDesde = $request->fecha_desde;
        $fechaHasta = $request->fecha_hasta;

        $nombreArchivo = 'muestras_muestreo_' . now()->format('Y_m_d_H_i') . '.xlsx';

        return Excel::download(
            new MuestrasMuestreoExport($fechaDesde, $fechaHasta, $userCodigo, $veTodasLasMuestrasMuestreo),
            $nombreArchivo
        );

    } catch (\Exception $e) {
        Log::error('Error al exportar muestras de muestreo: ' . $e->getMessage());
        return back()->with('error', 'Error al exportar las muestras: ' . $e->getMessage());
    }
}



public function dashboardAnalisis(Request $request)
{
    $user = Auth::user();
    $metodoFiltro = trim((string) $request->get('metodo', ''));
    $estadoUi = $request->get('estado', 'all');
    if ($estadoUi === null || $estadoUi === '') {
        $estadoUi = 'all';
    }

    $queryParams = collect($request->query())->except('estado')->all();
    $queryRequest = Request::create($request->url(), 'GET', $queryParams);

    $baseQuery = OrdenesLaboratorioListado::baseCotiQuery($queryRequest);
    if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
        OrdenesAccesoPorSector::aplicarFiltroCotiPorSectoresUsuario($baseQuery, $user);
    }

    $cotizaciones = $baseQuery->orderBy('coti_num', 'desc')->get();

    $ordenes = OrdenesLaboratorioListado::construirOrdenesDesdeCotizaciones($cotizaciones);
    if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
        $ordenes = OrdenesAccesoPorSector::filtrarOrdenesPorSector($ordenes, $user);
    }
    $muestras = OrdenesLaboratorioListado::muestrasDesdeOrdenes($ordenes);

    $analisisAgrupados = $this->agruparAnalisisDashboard($muestras, $metodoFiltro, $estadoUi, $user);

    $conteos = OrdenesLaboratorioListado::contarAnalisisPorEstado($muestras, $user);

    $pendientesPorCoordinar = $conteos['pendientes_coordinar'];
    $pendientesDeAnalisis = $conteos['coordinado analisis'];
    $pendientesDeRevision = $conteos['en revision analisis'];
    $finalizados = $conteos['analizado'];
    $anulados = $conteos['anulados'];

    $queryMetodos = CotioInstancia::where('cotio_subitem', '>', 0)
        ->where('active_ot', true)
        ->whereNotNull('cotio_codigometodo_analisis');
    OrdenesLaboratorioListado::aplicarWhereInstanciasDeMuestras($queryMetodos, $muestras);

    if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
        OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($queryMetodos, $user);
    }

    $codigosMetodos = $queryMetodos
        ->distinct()
        ->pluck('cotio_codigometodo_analisis')
        ->map(fn ($codigo) => trim((string) $codigo))
        ->filter()
        ->unique()
        ->values()
        ->toArray();

    $metodosDisponibles = Metodo::whereIn('metodo_codigo', $codigosMetodos)
        ->orderBy('metodo_descripcion')
        ->get();

    $analisisProximosAgrupados = $this->agruparAnalisisDashboard($muestras, $metodoFiltro, 'proximos', $user);

    $herramientasEnUso = InventarioLab::whereHas('cotioInstancias', function ($q) {
        $q->where('cotio_instancias.cotio_subitem', '>', 0)
            ->where('cotio_instancias.active_ot', true)
            ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    })->withCount(['cotioInstancias' => function ($q) {
        $q->where('cotio_instancias.cotio_subitem', '>', 0)
            ->where('cotio_instancias.active_ot', true)
            ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    }])->get();

    $estadoFiltro = $estadoUi;

    return view('dashboard.analisis', compact(
        'analisisAgrupados',
        'pendientesPorCoordinar',
        'pendientesDeAnalisis',
        'pendientesDeRevision',
        'finalizados',
        'anulados',
        'analisisProximosAgrupados',
        'herramientasEnUso',
        'estadoFiltro',
        'metodoFiltro',
        'metodosDisponibles',
        'request'
    ));
}

/**
 * @param  \Illuminate\Support\Collection<int, CotioInstancia>  $muestras
 */
private function agruparAnalisisDashboard($muestras, ?string $metodoFiltro = '', ?string $estadoUi = 'all', ?User $user = null): \Illuminate\Support\Collection
{
    if ($muestras->isEmpty()) {
        return collect();
    }

    $estadoUi = $estadoUi ?? 'all';
    $filtrarPorSector = OrdenesAccesoPorSector::debeFiltrarPorSector($user);

    if ($estadoUi === 'pendientes_coordinar') {
        if ($filtrarPorSector) {
            return collect();
        }

        return $this->agruparMuestrasPendientesCoordinarDashboard($muestras, $metodoFiltro);
    }

    $queryAnalisis = CotioInstancia::where('cotio_subitem', '>', 0)
        ->where('active_ot', true);
    OrdenesLaboratorioListado::aplicarWhereInstanciasDeMuestras($queryAnalisis, $muestras);

    if ($filtrarPorSector) {
        OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($queryAnalisis, $user);
    }

    if ($metodoFiltro !== '') {
        $queryAnalisis->where('cotio_codigometodo_analisis', $metodoFiltro);
    }

    if (! in_array($estadoUi, ['all', '', null], true)) {
        if ($estadoUi === 'proximos') {
            $queryAnalisis->where('fecha_fin_ot', '>=', now())
                ->where('fecha_fin_ot', '<=', now()->addDays(3));
        } else {
            $queryAnalisis->where('cotio_estado_analisis', $estadoUi);
        }
    }

    $analisis = $queryAnalisis
        ->with(['responsablesAnalisis'])
        ->get();

    if ($filtrarPorSector) {
        $sectoresUsuario = OrdenesAccesoPorSector::sectoresUsuario($user);
        $analisis = $analisis->filter(
            fn (CotioInstancia $item) => OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($item, $sectoresUsuario)
        )->values();
    }

    $muestrasPorClave = $muestras->keyBy(function ($m) {
        return $m->cotio_numcoti.'-'.$m->cotio_item.'-'.$m->instance_number;
    });

    $analisis->each(function ($item) use ($muestrasPorClave) {
        $muestraKey = $item->cotio_numcoti.'-'.$item->cotio_item.'-'.$item->instance_number;
        $muestra = $muestrasPorClave->get($muestraKey);
        $item->setRelation('muestra', $muestra);
        if ($muestra?->cotizacion) {
            $item->setRelation('cotizacion', $muestra->cotizacion);
        }
        $item->muestra_instance_number = $muestra?->instance_number;
    });

    $analisisAgrupados = $analisis->groupBy(function ($item) {
        return $item->cotio_numcoti.'-'.$item->cotio_item.'-'.$item->muestra_instance_number;
    });

    if (! $filtrarPorSector && in_array($estadoUi, ['all', '', null], true) && $metodoFiltro === '') {
        $pendientesCoordinar = $this->agruparMuestrasPendientesCoordinarDashboard($muestras, '');

        foreach ($pendientesCoordinar as $key => $grupo) {
            if (! $analisisAgrupados->has($key)) {
                $analisisAgrupados->put($key, $grupo);
            }
        }
    }

    return $this->ordenarGruposAnalisisDashboard($analisisAgrupados);
}

/**
 * @param  \Illuminate\Support\Collection<int, CotioInstancia>  $muestras
 */
private function agruparMuestrasPendientesCoordinarDashboard($muestras, ?string $metodoFiltro = ''): \Illuminate\Support\Collection
{
    if ($metodoFiltro !== '') {
        return collect();
    }

    $queryActivos = CotioInstancia::query()
        ->where('cotio_subitem', '>', 0)
        ->where('active_ot', true)
        ->whereIn('cotio_numcoti', $muestras->pluck('cotio_numcoti')->unique()->filter()->values()->all());

    $muestrasConAnalisisActivos = OrdenesLaboratorioListado::filtrarInstanciasPorMuestras(
        $queryActivos->get(['cotio_numcoti', 'cotio_item', 'instance_number']),
        $muestras
    )->groupBy(fn ($a) => $a->cotio_numcoti.'-'.$a->cotio_item.'-'.$a->instance_number);

    $muestrasPendientes = $muestras->filter(
        fn ($muestra) => OrdenesLaboratorioListado::muestraEstaPendientePorCoordinar($muestra, $muestrasConAnalisisActivos)
    )->values();

    if ($muestrasPendientes->isEmpty()) {
        return collect();
    }

    $queryAnalisisPendientes = CotioInstancia::query()
        ->where('cotio_subitem', '>', 0)
        ->whereIn('cotio_numcoti', $muestrasPendientes->pluck('cotio_numcoti')->unique()->filter()->values()->all());

    $analisisPorMuestra = OrdenesLaboratorioListado::filtrarInstanciasPorMuestras(
        $queryAnalisisPendientes->with(['responsablesAnalisis'])->get(),
        $muestrasPendientes
    )->groupBy(fn ($a) => $a->cotio_numcoti.'-'.$a->cotio_item.'-'.$a->instance_number);

    $analisisAgrupados = collect();

    foreach ($muestrasPendientes as $muestra) {
        $key = $muestra->cotio_numcoti.'-'.$muestra->cotio_item.'-'.$muestra->instance_number;
        $grupo = OrdenesLaboratorioListado::filtrarAnalisisPendientesCoordinacionMuestra(
            $muestra,
            $analisisPorMuestra->get($key, collect())
        );

        if ($grupo->isEmpty()) {
            continue;
        }

        $grupo->each(function ($item) use ($muestra) {
            $item->setRelation('muestra', $muestra);
            $item->setRelation('cotizacion', $muestra->cotizacion);
            $item->muestra_instance_number = $muestra->instance_number;
        });

        $analisisAgrupados->put($key, $grupo);
    }

    unset($muestrasConAnalisisActivos, $analisisPorMuestra, $muestrasPendientes);

    return $this->ordenarGruposAnalisisDashboard($analisisAgrupados);
}

private function ordenarGruposAnalisisDashboard(\Illuminate\Support\Collection $analisisAgrupados): \Illuminate\Support\Collection
{
    return $analisisAgrupados->sortBy(function ($grupo) {
        $primerAnalisis = $grupo->first();
        $muestra = $primerAnalisis?->muestra;

        if (! $primerAnalisis?->id) {
            return PrioridadListado::valorOrdenGrupo((bool) ($muestra->es_priori ?? false), 10);
        }

        $estado = $primerAnalisis->cotio_estado_analisis ?? 'pendiente_coordinar';

        if ($estado === 'analizado') {
            return PrioridadListado::valorOrdenGrupo((bool) ($muestra->es_priori ?? false), 500);
        }

        return PrioridadListado::valorOrdenGrupo(
            (bool) ($muestra->es_priori ?? false),
            OrdenesLaboratorioListado::pesoEstadoOrden($estado)
        );
    });
}

public function exportarAnalisis(Request $request)
{
    try {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $userCodigo = Auth::user()->usu_codigo;
        $fechaDesde = $request->fecha_desde;
        $fechaHasta = $request->fecha_hasta;

        $nombreArchivo = 'analisis_' . now()->format('Y_m_d_H_i') . '.xlsx';

        return Excel::download(
            new AnalisisExport($fechaDesde, $fechaHasta, $userCodigo),
            $nombreArchivo
        );

    } catch (\Exception $e) {
        Log::error('Error al exportar análisis: ' . $e->getMessage());
        return back()->with('error', 'Error al exportar los análisis: ' . $e->getMessage());
    }
}

// Método temporal para debuggear los filtros
public function debugAnalisis(Request $request)
{
    $estadoFiltro = $request->get('estado', 'all');
    
    // Debug info
    $debug = [];
    
    // Contar muestras por estado
    $debug['muestras_por_estado'] = CotioInstancia::where('cotio_subitem', 0)
        ->where('enable_ot', true)
        ->selectRaw('cotio_estado_analisis, active_ot, count(*) as total')
        ->groupBy(['cotio_estado_analisis', 'active_ot'])
        ->get()
        ->toArray();
    
    // Aplicar el filtro actual
    $queryMuestras = CotioInstancia::where('cotio_subitem', 0)
        ->where('enable_ot', true);

    if ($estadoFiltro !== 'all') {
        if ($estadoFiltro === 'pendientes_coordinar') {
            $queryMuestras->whereNull('cotio_estado_analisis');
        } else {
            $queryMuestras->where('cotio_estado_analisis', $estadoFiltro);
        }
    }
    
    $debug['filtro_aplicado'] = $estadoFiltro;
    $debug['muestras_filtradas'] = $queryMuestras->count();
    $debug['muestras_filtradas_detalle'] = $queryMuestras->limit(5)->get(['cotio_numcoti', 'cotio_item', 'instance_number', 'cotio_estado_analisis', 'active_ot'])->toArray();
    
    return response()->json($debug);
}


}
