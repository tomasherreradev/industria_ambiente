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

class DashboardController extends Controller
{



public function index()
{
    // Resumen general
    $totalCotizaciones = Coti::count();
    $cotizacionesRecientes = Coti::orderBy('coti_fechaalta', 'desc')->take(5)->get();
    
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
        'informesTotales'
    ));
}


public function dashboardMuestreo(Request $request)
{
    $userCodigo = Auth::user()->usu_codigo;
    $estadoFiltro = $request->get('estado', 'all');
    $muestreadorFiltro = $request->get('muestreador', 'all');
    $vehiculoFiltro = $request->get('vehiculo', 'all');
    $zonaFiltro = $request->get('zona', 'all');

    Log::info('[Dashboard Muestreo] Iniciando consulta', [
        'user_codigo' => $userCodigo,
        'estado_filtro' => $estadoFiltro,
        'muestreador_filtro' => $muestreadorFiltro,
        'vehiculo_filtro' => $vehiculoFiltro,
        'zona_filtro' => $zonaFiltro
    ]);

    // Verificar si el usuario es coordinador_muestreo
    $esCoordinadorMuestreo = Auth::user()->rol === 'coordinador_muestreo';
    
    // Verificar si es día 1 del mes para filtrar por mes actual
    $esDiaUno = now()->day === 1;
    $fechaInicioMes = now()->startOfMonth();
    $fechaFinMes = now()->endOfMonth();
    
    Log::info('[Dashboard Muestreo] Información del usuario', [
        'user_codigo' => $userCodigo,
        'rol' => Auth::user()->rol,
        'es_coordinador_muestreo' => $esCoordinadorMuestreo,
        'es_dia_uno' => $esDiaUno,
        'fecha_inicio_mes' => $fechaInicioMes->format('Y-m-d'),
        'fecha_fin_mes' => $fechaFinMes->format('Y-m-d')
    ]);

    // Base query para muestras
    // Si el usuario es coordinador_muestreo, puede ver todas las muestras (sin restricción de coordinador_codigo)
    if ($esCoordinadorMuestreo) {
        $query = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
            ->where(function($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            });
        Log::info('[Dashboard Muestreo] Usuario es coordinador_muestreo - sin restricción de coordinador_codigo');
    } else {
        // Para otros usuarios, aplicar restricción de permisos
        $query = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
            ->where(function($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            })
            ->where(function($query) use ($userCodigo) {
                // Mostrar muestras donde el usuario es coordinador O tiene muestras asignadas
                $query->where('cotio_instancias.coordinador_codigo', $userCodigo)
                      ->orWhereHas('responsablesMuestreo', function($q) use ($userCodigo) {
                          $q->where('instancia_responsable_muestreo.usu_codigo', $userCodigo);
                      });
            });
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
            ->where(function($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            })
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

    // Si es día 1, filtrar por muestras que finalizan en el mes actual
    if ($esDiaUno) {
        $query->whereBetween('fecha_fin_muestreo', [$fechaInicioMes, $fechaFinMes]);
        Log::info('[Dashboard Muestreo] Aplicando filtro de mes (día 1)', [
            'fecha_inicio' => $fechaInicioMes->format('Y-m-d'),
            'fecha_fin' => $fechaFinMes->format('Y-m-d')
        ]);
    }

    // Obtener muestras con paginación
    $muestras = $query->with(['cotizacion.cliente.zona', 'vehiculo', 'responsablesMuestreo'])
        ->orderBy('cotio_instancias.fecha_fin_muestreo')
        ->paginate(10)
        ->appends($request->query());
    
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
    $aplicarFiltrosBase = function($query) use ($esDiaUno, $fechaInicioMes, $fechaFinMes) {
        $query->where('cotio_subitem', 0)
            ->where(function($q) {
                $q->where('enable_ot', false)->orWhereNull('enable_ot');
            });
        
        // Si es día 1, filtrar por mes
        if ($esDiaUno) {
            $query->whereBetween('fecha_fin_muestreo', [$fechaInicioMes, $fechaFinMes]);
        }
        
        return $query;
    };
    
    // Estadísticas (sin filtro de estado)
    $totalMuestras = $aplicarFiltrosBase(CotioInstancia::query())->count();
    
    $pendientes = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'coordinado muestreo')
        ->count();
    
    $enProceso = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'en revision muestreo')
        ->count();
    
    $finalizadas = $aplicarFiltrosBase(CotioInstancia::query())
        ->where('cotio_estado', 'muestreado')
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
    
    // Obtener muestreadores disponibles (usuarios que tienen muestras asignadas)
    $muestreadores = User::whereHas('instanciasMuestreo', function($q) use ($userCodigo) {
        $q->where('cotio_instancias.cotio_subitem', 0)
          ->where(function($q2) {
              $q2->where('enable_ot', false)->orWhereNull('enable_ot');
          })
          ->where(function($query) use ($userCodigo) {
              $query->where('cotio_instancias.coordinador_codigo', $userCodigo)
                    ->orWhereHas('responsablesMuestreo', function($q) use ($userCodigo) {
                        $q->where('instancia_responsable_muestreo.usu_codigo', $userCodigo);
                    });
          });
    })->orderBy('usu_descripcion')->get();
    
    // Obtener vehículos disponibles (vehículos que tienen muestras asignadas)
    $vehiculosDisponibles = Vehiculo::whereHas('cotioInstancias', function($q) use ($userCodigo) {
        $q->where('cotio_instancias.cotio_subitem', 0)
          ->where(function($q2) {
              $q2->where('enable_ot', false)->orWhereNull('enable_ot');
          })
          ->where(function($query) use ($userCodigo) {
              $query->where('cotio_instancias.coordinador_codigo', $userCodigo)
                    ->orWhereHas('responsablesMuestreo', function($q) use ($userCodigo) {
                        $q->where('instancia_responsable_muestreo.usu_codigo', $userCodigo);
                    });
          });
    })->orderBy('patente')->get();
    
    // Obtener zonas disponibles (zonas de clientes que tienen muestras)
    // Primero obtener los códigos de zona únicos de las muestras actuales
    $zonasCodigos = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
        ->where(function($q) {
            $q->where('enable_ot', false)->orWhereNull('enable_ot');
        })
        ->where(function($query) use ($userCodigo) {
            $query->where('cotio_instancias.coordinador_codigo', $userCodigo)
                  ->orWhereHas('responsablesMuestreo', function($q) use ($userCodigo) {
                      $q->where('instancia_responsable_muestreo.usu_codigo', $userCodigo);
                  });
        })
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
        'pendientes',
        'enProceso',
        'finalizadas',
        'muestrasProximas',
        'vehiculosAsignados',
        'herramientasEnUso',
        'estadoFiltro',
        'muestreadorFiltro',
        'vehiculoFiltro',
        'zonaFiltro',
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

        $fechaDesde = $request->fecha_desde;
        $fechaHasta = $request->fecha_hasta;

        $nombreArchivo = 'muestras_muestreo_' . now()->format('Y_m_d_H_i') . '.xlsx';

        return Excel::download(
            new MuestrasMuestreoExport($fechaDesde, $fechaHasta, $userCodigo, $esCoordinadorMuestreo),
            $nombreArchivo
        );

    } catch (\Exception $e) {
        Log::error('Error al exportar muestras de muestreo: ' . $e->getMessage());
        return back()->with('error', 'Error al exportar las muestras: ' . $e->getMessage());
    }
}



public function dashboardAnalisis(Request $request)
{
    $userCodigo = Auth::user()->usu_codigo;
    $estadoFiltro = $request->get('estado', 'all');
    $metodoFiltro = $request->get('metodo', '');

    // Obtener métodos disponibles para el filtro (métodos que están siendo usados en análisis activos)
    $codigosMetodos = CotioInstancia::where('cotio_subitem', '>', 0)
        ->where('active_ot', true)
        ->whereNotNull('cotio_codigometodo_analisis')
        ->distinct()
        ->pluck('cotio_codigometodo_analisis')
        ->filter()
        ->map(function($codigo) {
            return trim($codigo);
        })
        ->unique()
        ->values()
        ->toArray();

    $metodosDisponibles = Metodo::whereIn('metodo_codigo', $codigosMetodos)
        ->orderBy('metodo_descripcion')
        ->get();

    // Verificar si es día 1 del mes para filtrar por mes actual
    $esDiaUno = now()->day === 1;
    $fechaInicioMes = now()->startOfMonth();
    $fechaFinMes = now()->endOfMonth();

    // Primero, obtener las muestras según el filtro
    $queryMuestras = CotioInstancia::where('cotio_subitem', 0)
        ->where('enable_ot', true);

    // Aplicar filtro según el estado seleccionado
    if ($estadoFiltro !== 'all') {
        if ($estadoFiltro === 'pendientes_coordinar') {
            // Muestras pendientes por coordinar (sin análisis activos)
            $queryMuestras->where('active_ot', false);
        } elseif ($estadoFiltro === 'proximos') {
            // Próximos 3 días (no aplicar filtro de mes cuando se usa este filtro)
            $queryMuestras->where('fecha_fin_ot', '>=', now())
                  ->where('fecha_fin_ot', '<=', now()->addDays(3));
        } else {
            // Filtrar por estado de análisis de la muestra
            $queryMuestras->where('cotio_estado_analisis', $estadoFiltro);
        }
    }

    // Siempre filtrar por muestras que finalizan en el mes actual (excepto cuando se usa filtro "proximos")
    if ($estadoFiltro !== 'proximos') {
        $queryMuestras->whereBetween('fecha_fin_ot', [$fechaInicioMes, $fechaFinMes]);
    }

    $muestras = $queryMuestras->with(['cotizacion'])
        ->get()
        ->groupBy(fn($m) => $m->cotio_numcoti . '-' . $m->cotio_item . '-' . $m->instance_number);

    // También buscar análisis que tengan fecha_fin_ot en el mes actual
    // y obtener las muestras relacionadas SOLO si los análisis están en el mes actual
    if ($estadoFiltro !== 'proximos') {
        $analisisEnMes = CotioInstancia::where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->whereBetween('fecha_fin_ot', [$fechaInicioMes, $fechaFinMes])
            ->get();

        // Obtener las muestras relacionadas con estos análisis
        if ($analisisEnMes->isNotEmpty()) {
            $muestrasDeAnalisisEnMes = CotioInstancia::where('cotio_subitem', 0)
                ->where('enable_ot', true)
                ->where(function($query) use ($analisisEnMes) {
                    foreach ($analisisEnMes as $analisis) {
                        $query->orWhere(function($subQ) use ($analisis) {
                            $subQ->where('cotio_numcoti', $analisis->cotio_numcoti)
                                 ->where('cotio_item', $analisis->cotio_item)
                                 ->where('instance_number', $analisis->instance_number);
                        });
                    }
                })
                ->with(['cotizacion'])
                ->get()
                ->groupBy(fn($m) => $m->cotio_numcoti . '-' . $m->cotio_item . '-' . $m->instance_number);

            // Combinar muestras encontradas directamente + muestras relacionadas con análisis
            // Combinar las Collections agrupadas manteniendo la estructura
            foreach ($muestrasDeAnalisisEnMes as $key => $muestraGroup) {
                if (!$muestras->has($key)) {
                    $muestras->put($key, $muestraGroup);
                }
            }
        }
    }

    // Ahora obtener los análisis relacionados con estas muestras
    $muestrasIds = collect($muestras->keys())->map(function($key) {
        $parts = explode('-', $key);
        return [
            'cotio_numcoti' => $parts[0],
            'cotio_item' => $parts[1],
            'instance_number' => $parts[2]
        ];
    });

    $analisis = collect();
    if ($muestrasIds->isNotEmpty()) {
        $queryAnalisis = CotioInstancia::where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->where(function($q) use ($muestrasIds) {
                foreach ($muestrasIds as $muestra) {
                    $q->orWhere(function($subQ) use ($muestra) {
                        $subQ->where('cotio_numcoti', $muestra['cotio_numcoti'])
                             ->where('cotio_item', $muestra['cotio_item'])
                             ->where('instance_number', $muestra['instance_number']);
                    });
                }
            });

        // Siempre filtrar análisis por fecha_fin_ot del mes actual (excepto cuando se usa filtro "proximos")
        if ($estadoFiltro !== 'proximos') {
            $queryAnalisis->whereBetween('fecha_fin_ot', [$fechaInicioMes, $fechaFinMes]);
        }

        // Filtrar por método de análisis si se especifica
        if (!empty($metodoFiltro)) {
            $queryAnalisis->where('cotio_codigometodo_analisis', $metodoFiltro);
        }

        $analisis = $queryAnalisis->with(['cotizacion', 'responsablesAnalisis', 'herramientasLab'])
            ->get();
    }

    // Asignar muestras a cada análisis
    $analisis->each(function($item) use ($muestras) {
        $muestraKey = $item->cotio_numcoti . '-' . $item->cotio_item . '-' . $item->instance_number;
        $muestra = $muestras->get($muestraKey)?->first();
        
        $item->setRelation('muestra', $muestra);
        $item->muestra_instance_number = $muestra?->instance_number;
    });
    
    // Agrupar análisis por muestra
    $analisisAgrupados = $analisis->groupBy(function($item) {
        return $item->cotio_numcoti . '-' . $item->cotio_item . '-' . $item->muestra_instance_number;
    });

    // Agregar muestras que no tienen análisis activos
    // Solo si NO estamos filtrando por fecha del mes (es decir, cuando se usa filtro 'proximos')
    // y NO estamos filtrando por método (porque si filtramos por método, solo queremos muestras con análisis de ese método)
    // porque si estamos filtrando por mes, solo queremos mostrar muestras con análisis en ese mes
    if ($estadoFiltro === 'proximos' && empty($metodoFiltro)) {
        foreach ($muestras as $key => $muestraGroup) {
            if (!$analisisAgrupados->has($key)) {
                $muestra = $muestraGroup->first();
                // Crear un análisis ficticio para mantener la estructura
                $analisisFicticio = new CotioInstancia();
                $analisisFicticio->setRelation('muestra', $muestra);
                $analisisFicticio->cotio_numcoti = $muestra->cotio_numcoti;
                $analisisFicticio->cotio_item = $muestra->cotio_item;
                $analisisFicticio->instance_number = $muestra->instance_number;
                $analisisFicticio->muestra_instance_number = $muestra->instance_number;
                $analisisAgrupados->put($key, collect([$analisisFicticio]));
            }
        }
    }

    // Ordenar grupos según prioridad y estado
    $analisisAgrupados = $analisisAgrupados->sortBy(function($grupo, $key) {
        $primerAnalisis = $grupo->first();
        $muestra = $primerAnalisis->muestra;
        
        if (!$muestra) {
            return 9999; // Sin muestra va al final
        }
        
        $estado = $muestra->cotio_estado_analisis ?? 'pendiente_coordinar';
        $esPriori = $muestra->es_priori ?? false;
        
        // Si está analizado, siempre va al final (independientemente de prioridad)
        if ($estado === 'analizado') {
            return 500; // Todas las analizadas al final
        }
        
        // Orden de prioridad (solo para no analizadas)
        if ($esPriori) {
            return 100; // Prioridad va primero
        }
        
        // Orden por estado (solo para no analizadas y no prioritarias)
        switch ($estado) {
            case 'pendiente_coordinar':
            case null:
                return 200; // Pendientes por coordinar - segundo lugar (después de prioritarias)
            case 'en revision analisis':
                return 300; // En revisión (turquesas) - tercer lugar
            case 'coordinado analisis':
                return 400; // Coordinadas (amarillas) - cuarto lugar
            default:
                return 600;
        }
    });
    
    
    // Función helper para aplicar filtros base a estadísticas
    $aplicarFiltrosBase = function($query) use ($esDiaUno, $fechaInicioMes, $fechaFinMes) {
        $query->where('cotio_subitem', 0)
            ->where('enable_ot', true);
        
        if ($esDiaUno) {
            $query->whereBetween('fecha_fin_ot', [$fechaInicioMes, $fechaFinMes]);
        }
    };

    // Estadísticas basadas en las muestras (cotio_subitem = 0)
    $queryPendientesPorCoordinar = CotioInstancia::query();
    $aplicarFiltrosBase($queryPendientesPorCoordinar);
    $pendientesPorCoordinar = $queryPendientesPorCoordinar->where('active_ot', false)->count();
    
    $queryPendientesDeAnalisis = CotioInstancia::query();
    $aplicarFiltrosBase($queryPendientesDeAnalisis);
    $pendientesDeAnalisis = $queryPendientesDeAnalisis->where('cotio_estado_analisis', 'coordinado analisis')->count();
    
    $queryPendientesDeRevision = CotioInstancia::query();
    $aplicarFiltrosBase($queryPendientesDeRevision);
    $pendientesDeRevision = $queryPendientesDeRevision->where('cotio_estado_analisis', 'en revision analisis')->count();
    
    $queryFinalizados = CotioInstancia::query();
    $aplicarFiltrosBase($queryFinalizados);
    $finalizados = $queryFinalizados->where('cotio_estado_analisis', 'analizado')->count();

    $queryAnulados = CotioInstancia::query();
    $aplicarFiltrosBase($queryAnulados);
    $anulados = $queryAnulados->where('time_annulled', '>', 0)->count();

    // Análisis próximos a vencer (basado en muestras)
    $muestrasProximas = CotioInstancia::where('cotio_subitem', 0)
        ->where('enable_ot', true)
        ->where('fecha_fin_ot', '>=', now())
        ->where('fecha_fin_ot', '<=', now()->addDays(3))
        ->where(function($query) use ($userCodigo) {
            $query->where('coordinador_codigo', $userCodigo)
                ->orWhereHas('responsablesAnalisis', function($q) use ($userCodigo) {
                    $q->where('instancia_responsable_analisis.usu_codigo', $userCodigo);
                });
        })
        ->with(['cotizacion'])
        ->orderBy('fecha_fin_ot')
        ->get();

    // Obtener análisis relacionados con estas muestras próximas
    $analisisProximos = collect();
    if ($muestrasProximas->isNotEmpty()) {
        $queryAnalisisProximos = CotioInstancia::where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->where(function($q) use ($muestrasProximas) {
                foreach ($muestrasProximas as $muestra) {
                    $q->orWhere(function($subQ) use ($muestra) {
                        $subQ->where('cotio_numcoti', $muestra->cotio_numcoti)
                             ->where('cotio_item', $muestra->cotio_item)
                             ->where('instance_number', $muestra->instance_number);
                    });
                }
            });

        // Filtrar por método de análisis si se especifica
        if (!empty($metodoFiltro)) {
            $queryAnalisisProximos->where('cotio_codigometodo_analisis', $metodoFiltro);
        }

        $analisisProximos = $queryAnalisisProximos->with(['cotizacion', 'responsablesAnalisis'])
            ->get();
    }

    // Asignar muestras a cada análisis próximo
    $analisisProximos->each(function($item) use ($muestrasProximas) {
        $muestra = $muestrasProximas->where('cotio_numcoti', $item->cotio_numcoti)
                                   ->where('cotio_item', $item->cotio_item)
                                   ->where('instance_number', $item->instance_number)
                                   ->first();
        $item->setRelation('muestra', $muestra);
    });

    // Agrupar análisis próximos por muestra
    $analisisProximosAgrupados = $analisisProximos->groupBy(function($item) {
        return $item->cotio_numcoti . '-' . $item->cotio_item . '-' . $item->instance_number;
    });

    // Agregar muestras sin análisis activos a los próximos
    // Solo si NO estamos filtrando por método (porque si filtramos por método, solo queremos muestras con análisis de ese método)
    if (empty($metodoFiltro)) {
        foreach ($muestrasProximas as $muestra) {
            $key = $muestra->cotio_numcoti . '-' . $muestra->cotio_item . '-' . $muestra->instance_number;
            if (!$analisisProximosAgrupados->has($key)) {
                $analisisFicticio = new CotioInstancia();
                $analisisFicticio->setRelation('muestra', $muestra);
                $analisisFicticio->cotio_numcoti = $muestra->cotio_numcoti;
                $analisisFicticio->cotio_item = $muestra->cotio_item;
                $analisisFicticio->instance_number = $muestra->instance_number;
                $analisisFicticio->fecha_fin = $muestra->fecha_fin_ot;
                $analisisProximosAgrupados->put($key, collect([$analisisFicticio]));
            }
        }
    }
    
    // Herramientas de laboratorio en uso
    $herramientasEnUso = InventarioLab::whereHas('cotioInstancias', function($q) {
        $q->where('cotio_instancias.cotio_subitem', '>', 0)
          ->where('cotio_instancias.enable_ot', true)
          ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    })->withCount(['cotioInstancias' => function($q) {
        $q->where('cotio_instancias.cotio_subitem', '>', 0)
          ->where('cotio_instancias.enable_ot', true)
          ->where('cotio_instancias.cotio_estado', '!=', 'finalizado');
    }])->get();
    
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
        'esDiaUno'
    ));
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
            $queryMuestras->where('active_ot', false);
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
