<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coti;
use App\Models\Matriz;
use Illuminate\Support\Facades\DB;
use App\Models\Cotio;
use App\Models\User;
use App\Models\InventarioLab;
use App\Models\Vehiculo;
use App\Models\CotioInstancia;
use App\Models\CotioHistorialCambios;
use App\Models\MetodoAnalisis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\InstanciaResponsableAnalisis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use App\Models\SimpleNotification;
use App\Support\CotizacionCanalEnsayo;
use App\Support\CotizacionClienteEtiqueta;
use App\Support\AsignacionSectorLaboratorio;
use App\Support\LeyNormativaPresentacion;
use App\Support\PrioridadListado;
use App\Support\EtiquetaMetodoAnalisis;
use App\Support\ListadoProgresoOrden;
use App\Support\OrdenesLaboratorioListado;
use App\Support\OrdenesAccesoPorSector;
use App\Support\AnalisisResultadoValidacion;
use App\Support\FechaAnalisisInformePdf;
use App\Models\Metodo;

class OrdenController extends Controller
{



    public function index(Request $request)
    {
        $verTodas = $request->query('ver_todas');
        $viewType = $request->get('view', 'lista');
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $user = Auth::user();
        $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
        $startOfWeek = $request->get('week') ? Carbon::parse($request->get('week'))->startOfWeek() : now()->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();
    
        // Vista de Calendario (instancias en laboratorio, por muestra)
        if ($viewType === 'calendario') {
            $query = CotioInstancia::query()
                ->where('cotio_subitem', 0)
                ->whereHas('tarea', function ($q) {
                    $q->where('cotio_subitem', 0);
                    CotizacionCanalEnsayo::aplicarWhereCotioEnsayoIncluidoEnOrdenes($q);
                })
                ->with(['cotizacion.cliente', 'cotizacion.sucursal', 'responsablesAnalisis', 'tarea'])
                // Alinear con lista/documento: directo a lab (sin muestreo) O ya en circuito lab (enable_ot)
                ->where(function ($outer) {
                    $outer->whereHas('tarea', function ($q) {
                        $q->where('cotio_subitem', 0)
                            ->where(function ($subQ) {
                                $subQ->where('lleva_muestreo', false)
                                    ->orWhereNull('lleva_muestreo');
                            });
                    })->orWhere(function ($q2) {
                        $q2->where('enable_ot', true)
                            ->whereHas('tarea', function ($tq) {
                                $tq->where('cotio_subitem', 0);
                            });
                    });
                })
                // Misma regla que lista/documento sobre la cotización: si hay muestra con enable_ot,
                // se muestra aunque otras líneas lleven req_cadena_custodia; si no, aplican exclusiones.
                ->whereHas('cotizacion', function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereHas('instancias', function ($subQ) {
                            $subQ->where('cotio_subitem', 0)->where('enable_ot', true);
                        })->orWhere(function ($q2) {
                            $q2->whereDoesntHave('tareas', function ($subQ) {
                                $subQ->where('req_cadena_custodia', true);
                            })
                                ->whereDoesntHave('tareas', function ($q3) {
                                    $q3->where('cotio_subitem', 0)
                                        ->where(function ($subQ2) {
                                            $subQ2->whereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%TRABAJO TECNICO%'")
                                                ->orWhereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%VISITA TECNICA%'");
                                        });
                                });
                        });
                    });
                });
    
            // Aplicar filtros
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $searchTermLike = '%'.$searchTerm.'%';
                $cleanedIdSearch = ltrim(preg_replace('/[^0-9]/', '', $searchTerm), '0');
                
                $query->where(function($q) use ($searchTermLike, $cleanedIdSearch) {
                    // Búsqueda normal en campos de cotización
                    $q->whereHas('cotizacion', function($subQuery) use ($searchTermLike) {
                        $subQuery->where('coti_num', 'like', $searchTermLike)
                            ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [strtolower($searchTermLike)])
                            ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [strtolower($searchTermLike)]);
                    });
                    
                    // Si es búsqueda por ID de instancia
                    if (is_numeric($cleanedIdSearch)) {
                        $q->orWhereIn('cotio_numcoti', function($subQuery) use ($cleanedIdSearch) {
                            $subQuery->select('cotio_numcoti')
                                ->from('cotio_instancias')
                                ->where('id', $cleanedIdSearch);
                        });
                    }
                    
                    // Búsqueda por número de OT
                    $q->orWhere('otn', 'like', $searchTermLike);
                    
                    $q->orWhere('cotio_codigoum', 'like', $searchTermLike);
                });
            }
    
            if ($request->has(trim('matriz')) && !empty($request->matriz)) {
                $query->whereHas('cotizacion', function($q) use ($request) {
                    $q->where('coti_codigomatriz', $request->matriz);
                });
            }
    
            if ($request->has('estado') && !empty($request->estado)) {
                if ($request->estado == 'pendiente por coordinar') {
                    $query->where('enable_ot', true)
                          ->whereNull('cotio_estado_analisis');
                } else {
                    $query->where('cotio_estado_analisis', $request->estado);
                }
            } elseif (!$verTodas) {
                $query->whereHas('cotizacion', function($q) {
                    $q->where('coti_estado', 'A');
                });
            }
    
            // Filtros Desde/Hasta: por fecha de aprobación de la cotización (coti_fechaaprobado).
            // Sin esos parámetros: mes de navegación por fecha de inicio OT **o** por fecha de aprobación
            // (muchas muestras directo a lab aún no tienen fecha_inicio_ot y quedaban fuera del calendario).
            $filtroFechaAprobacion = $request->filled('fecha_inicio_ot') || $request->filled('fecha_fin_ot');
            if ($filtroFechaAprobacion) {
                $query->whereHas('cotizacion', function ($q) use ($request) {
                    if ($request->filled('fecha_inicio_ot')) {
                        $q->whereDate('coti_fechaaprobado', '>=', $request->fecha_inicio_ot);
                    }
                    if ($request->filled('fecha_fin_ot')) {
                        $q->whereDate('coti_fechaaprobado', '<=', $request->fecha_fin_ot);
                    }
                });
            } else {
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();
                $monthStartDate = $monthStart->toDateString();
                $monthEndDate = $monthEnd->toDateString();
                $query->where(function ($q) use ($monthStart, $monthEnd, $monthStartDate, $monthEndDate) {
                    $q->whereBetween('fecha_inicio_ot', [$monthStart, $monthEnd])
                        ->orWhereHas('cotizacion', function ($cq) use ($monthStartDate, $monthEndDate) {
                            $cq->whereDate('coti_fechaaprobado', '>=', $monthStartDate)
                                ->whereDate('coti_fechaaprobado', '<=', $monthEndDate);
                        });
                });
            }

            if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
                OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($query, $user);
            }
    
            // Obtener resultados ordenados por fecha
            $instancias = $query->orderBy('fecha_inicio_ot', 'asc')->get();

            CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
                $instancias->map->cotizacion->unique(fn ($c) => $c->coti_num)->values()
            );
    
            // Verificar suspensiones
            $instancias->each(function ($instancia) {
                $instancia->has_suspension = $instancia->cotizacion->instancias->contains(function ($i) {
                    return strtolower(trim($i->cotio_estado_analisis)) === 'suspension';
                });
            });
    
            // Agrupar por fecha de inicio
            $tareasCalendario = $instancias
                ->filter(fn($item) => !empty($item->fecha_inicio_ot))
                ->mapToGroups(function($instancia) {
                    return [Carbon::parse($instancia->fecha_inicio_ot)->format('Y-m-d') => $instancia];
                })
                ->map(function($items) {
                    return $items->sortBy('fecha_inicio_ot');
                });
    
            // Instancias sin fecha programada
            $unscheduled = $instancias->filter(fn($instancia) => empty($instancia->fecha_inicio_ot));
            if ($unscheduled->isNotEmpty()) {
                $tareasCalendario->put('sin-fecha', $unscheduled);
            }
    
            // Generar eventos para FullCalendar
            $events = collect();
            foreach ($tareasCalendario as $date => $instancias) {
                foreach ($instancias as $instancia) {
                    $start = $instancia->fecha_inicio_ot;
                    $sintetizoInicio = empty($start);
                    if ($sintetizoInicio) {
                        $fa = $instancia->cotizacion?->coti_fechaaprobado;
                        $start = $fa
                            ? Carbon::parse($fa)->setTime(8, 0, 0)
                            : Carbon::now()->setTime(8, 0, 0);
                    }
                    $end = $instancia->fecha_fin_ot;
                    if ($sintetizoInicio && empty($end)) {
                        $end = Carbon::parse($start)->copy()->addHour();
                    }

                    $events->push([
                        'title' => CotizacionClienteEtiqueta::paraLista($instancia->cotizacion) . ' - ' . $instancia->cotio_numcoti,
                        'start' => $start,
                        'cotio_subitem' => $instancia->cotio_subitem,
                        'end' => $end,
                        'url' => route('categoria.verOrden', [
                            'cotizacion' => $instancia->cotio_numcoti,
                            'item' => $instancia->cotio_item,
                            'cotio_subitem' => $instancia->cotio_subitem,
                            'instance' => $instancia->instance_number
                        ]),
                        'extendedProps' => [
                            'empresa' => CotizacionClienteEtiqueta::paraLista($instancia->cotizacion),
                            'descripcion' => $instancia->cotizacion->coti_descripcion ?? '',
                            'estado' => $instancia->cotio_estado_analisis,
                            'analisis_count' => $instancia->responsablesAnalisis->count() ?? 0,
                            'has_suspension' => $instancia->has_suspension
                        ],
                        'className' => $this->getEventClass($instancia),
                    ]);
                }
            }
    
            return view('ordenes.index', [
                'events' => $events,
                'tareasCalendario' => $tareasCalendario,
                'startOfWeek' => $startOfWeek,
                'endOfWeek' => $endOfWeek,
                'viewType' => $viewType,
                'matrices' => $matrices,
                'request' => $request,
                'currentMonth' => $currentMonth,
                'userToView' => null,
                'usuarios' => collect(),
                'viewTasks' => false
            ]);
        }
    
        // Vista de Lista/Documento - misma base que OrdenesLaboratorioListado
        $baseQuery = OrdenesLaboratorioListado::baseCotiQuery($request);

        if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
            OrdenesAccesoPorSector::aplicarFiltroCotiPorSectoresUsuario($baseQuery, $user);
        }

        $perPage = 100;
        $tieneOrdenExplicito = OrdenesLaboratorioListado::tieneOrdenExplicito($request);
        $ordenPorProgreso = ListadoProgresoOrden::esOrdenPorProgreso($request->query('sort'));
        $dirProgreso = strtolower((string) $request->query('dir', '')) === 'desc' ? 'desc' : 'asc';

        if ($ordenPorProgreso) {
            $cotizaciones = $baseQuery->get();
            CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($cotizaciones);

            $ordenes = OrdenesLaboratorioListado::construirOrdenesDesdeCotizaciones($cotizaciones, true);

            if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
                $ordenes = OrdenesAccesoPorSector::filtrarOrdenesPorSector($ordenes, $user);
            }

            $ordenesOrdenadas = ListadoProgresoOrden::ordenarOrdenesLaboratorio($ordenes, $dirProgreso);
            $pagination = ListadoProgresoOrden::paginar($ordenesOrdenadas, $perPage, $request);
            $ordenes = collect($pagination->items())->mapWithKeys(function (array $orden) {
                return [(int) $orden['cotizacion']->coti_num => $orden];
            });
        } else {
            OrdenesLaboratorioListado::aplicarOrdenListado($baseQuery, $request);

            $pagination = $baseQuery
                ->paginate($perPage)
                ->withQueryString();

            CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($pagination->getCollection());

            $ordenes = OrdenesLaboratorioListado::construirOrdenesDesdeCotizaciones(
                $pagination->getCollection(),
                $tieneOrdenExplicito
            );

            if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
                $ordenes = OrdenesAccesoPorSector::filtrarOrdenesPorSector($ordenes, $user);
            }
        }
    
        return view('ordenes.index', [
            'ordenes' => $ordenes,
            'viewType' => $viewType,
            'matrices' => $matrices,
            'pagination' => $pagination,
            'request' => $request,
            'currentMonth' => $currentMonth
        ]);
    }
    
    protected function getEventClass($instancia)
    {
        switch (strtolower($instancia->cotio_estado_analisis)) {
            case 'coordinado analisis':
                return 'fc-event-warning';
            case 'en revision analisis':
                return 'fc-event-info';
            case 'analizado':
                return 'fc-event-success';
            case 'suspension':
                return 'fc-event-danger';
            default:
                return 'fc-event-primary';
        }
    }

    /**
     * Determina el estado predominante de una orden basado en los estados de sus muestras
     */
    protected function determinarEstadoPredominante($estadosMuestras)
    {
        if ($estadosMuestras->isEmpty()) {
            return 'pendiente_coordinar';
        }
        
        $estadosUnicos = $estadosMuestras->unique()->filter();
        
        // Si hay suspensión, tiene prioridad
        if ($estadosUnicos->contains('suspension')) {
            return 'suspension';
        }
        
        // Si hay al menos una sin coordinar (null o vacío), el estado es pendiente
        if ($estadosMuestras->contains(null) || $estadosMuestras->contains('')) {
            return 'pendiente_coordinar';
        }
        
        // Si hay al menos una en "coordinado analisis", el grupo se mantiene en ese estado
        if ($estadosUnicos->contains('coordinado analisis')) {
            return 'coordinado analisis';
        }
        
        // Si TODAS están en "en revision analisis", el grupo pasa a ese estado
        if ($estadosUnicos->count() === 1 && $estadosUnicos->first() === 'en revision analisis') {
            return 'en revision analisis';
        }
        
        // Si TODAS están en "analizado", el grupo pasa a ese estado
        if ($estadosUnicos->count() === 1 && $estadosUnicos->first() === 'analizado') {
            return 'analizado';
        }
        
        // Si hay mezcla de "en revision" y "analizado", se mantiene en revisión
        if ($estadosUnicos->contains('en revision analisis')) {
            return 'en revision analisis';
        }
        
        // Por defecto, tomar el primer estado encontrado
        return $estadosUnicos->first() ?? 'pendiente_coordinar';
    }

    /**
     * Determina el estado predominante considerando tanto cotio_estado_analisis como active_ot
     */
    protected function determinarEstadoPredominanteConActiveOt($muestras)
    {
        if ($muestras->isEmpty()) {
            return 'pendiente_coordinar';
        }
        

        
        // Primero verificar si hay suspensiones
        if ($muestras->contains(function ($muestra) {
            return strtolower(trim($muestra->cotio_estado_analisis ?? '')) === 'suspension';
        })) {
            return 'suspension';
        }
        
        // Verificar si hay muestras pendientes por coordinar (active_ot = false)
        if ($muestras->contains(function ($muestra) {
            return !$muestra->active_ot;
        })) {
            return 'pendiente_coordinar';
        }
        
        // Si todas tienen active_ot = true, usar la lógica de estados normal
        $estadosAnalisisFiltrados = $muestras->pluck('cotio_estado_analisis')->filter();
        $resultado = $this->determinarEstadoPredominante($estadosAnalisisFiltrados);
        return $resultado;
    }

    /**
     * Obtiene el valor numérico para ordenar por estado
     */
    protected function getEstadoOrden($estado)
    {
        switch ($estado) {
            case 'pendiente_coordinar':
            case null:
            case '':
                return 10; // Pendientes por coordinar - segundo lugar (después de prioritarias)
            case 'en revision analisis':
                return 20; // En revisión (turquesas) - tercer lugar
            case 'coordinado analisis':
                return 30; // Coordinadas (amarillas) - cuarto lugar
            case 'analizado':
                return 40; // Analizadas al final
            case 'suspension':
                return 5; // Suspendidas tienen prioridad especial
            default:
                return 50;
        }
    }

    // Método temporal para debuggear el ordenamiento
    public function debugOrdenamiento(Request $request)
    {
        $verTodas = $request->query('ver_todas');
        
        // Obtener las órdenes específicas que vemos en la imagen
        $cotizacionesEspecificas = ['372', '87', '185', '373'];
        
        $instancias = CotioInstancia::with(['cotizacion'])
            ->whereIn('cotio_numcoti', $cotizacionesEspecificas)
            ->orderBy('cotio_numcoti', 'desc')
            ->get();

        $ordenes = $instancias->groupBy('cotio_numcoti')->map(function ($group) {
            $muestrasDelGrupo = $group->where('cotio_subitem', 0);
            $estadoPredominante = $this->determinarEstadoPredominanteConActiveOt($muestrasDelGrupo);
            
            $has_priority = PrioridadListado::grupoTienePrioridad($group);
            
            return [
                'cotio_numcoti' => $group->first()->cotio_numcoti,
                'cotizacion' => CotizacionClienteEtiqueta::paraLista($group->first()->cotizacion) ?: 'N/A',
                'estado_predominante' => $estadoPredominante,
                'has_priority' => $has_priority,
                'muestras_active_ot' => $muestrasDelGrupo->pluck('active_ot')->toArray(),
                'estados_analisis' => $muestrasDelGrupo->pluck('cotio_estado_analisis')->toArray(),
                'enable_ot' => $muestrasDelGrupo->pluck('enable_ot')->toArray(),
                'orden_valor' => $this->calcularOrdenValor($has_priority, $estadoPredominante)
            ];
        });

        // Mostrar antes y después del ordenamiento
        $ordenesAntes = $ordenes->sortBy('cotio_numcoti');
        $ordenesDespues = $ordenes->sortBy('orden_valor');

        return response()->json([
            'antes_del_ordenamiento' => $ordenesAntes->values(),
            'despues_del_ordenamiento' => $ordenesDespues->values(),
            'valores_orden_estado' => [
                'pendiente_coordinar' => $this->getEstadoOrden('pendiente_coordinar'),
                'coordinado_analisis' => $this->getEstadoOrden('coordinado analisis'),
                'en_revision_analisis' => $this->getEstadoOrden('en revision analisis'),
                'analizado' => $this->getEstadoOrden('analizado')
            ]
        ]);
    }

    private function calcularOrdenValor($esPrioritaria, $estadoPredominante)
    {
        return PrioridadListado::valorOrdenGrupo($esPrioritaria, $this->getEstadoOrden($estadoPredominante));
    }

    private function calcularOrdenValorSimple($esPrioritaria, $estadoPredominante)
    {
        return PrioridadListado::valorOrdenGrupo($esPrioritaria, $this->getEstadoOrden($estadoPredominante));
    }





public function showOrdenes(Request $request)
{
    $user = Auth::user();
    $codigo = trim($user->usu_codigo);
    $viewType = $request->get('view', 'lista');
    $perPage = 50;
    $searchTerm = $request->get('search');
    $nombreAnalisis = trim((string) $request->get('cotio_descripcion_analisis', ''));
    $fechaInicio = $request->get('fecha_inicio_ot');
    $fechaFin = $request->get('fecha_fin_ot');
    $estado = $request->get('estado');
    
    $currentMonth = $request->get('month') 
        ? Carbon::parse($request->get('month')) 
        : now();

    // Initialize queries
    $queryMuestras = CotioInstancia::with([
        'muestra.cotizado',
        'herramientas',
        'responsablesAnalisis',
        'tareas.responsablesAnalisis'
    ])
    ->where('cotio_subitem', 0)
    ->where('active_ot', true)
    ->whereHas('tarea', function ($q) {
        $q->where('cotio_subitem', 0);
        CotizacionCanalEnsayo::aplicarWhereCotioEnsayoIncluidoEnOrdenes($q);
    })
    ->orderBy('fecha_inicio_ot', 'desc')
    ->orderByRaw("CASE WHEN cotio_estado_analisis = 'coordinado' THEN 0 ELSE 1 END");

    $queryAnalisis = CotioInstancia::with([
        'tarea' => fn ($q) => $q->with(['metodoLegacy', 'metodoMuestreo', 'metodoAnalisis']),
        'tarea.cotizado',
        'herramientas',
        'responsablesAnalisis',
    ])
    ->where('cotio_subitem', '>', 0)
    ->where('active_ot', true)
    ->whereHas('tarea', function ($q) {
        $q->where('cotio_subitem', 0);
        CotizacionCanalEnsayo::aplicarWhereCotioEnsayoIncluidoEnOrdenes($q);
    })
    ->orderBy('fecha_inicio_ot', 'desc')
    ->orderByRaw("CASE WHEN cotio_estado_analisis = 'coordinado' THEN 0 ELSE 1 END");

    // Exclude 'analizado' by default only if not specifically requested
    if (!$estado || $estado !== 'analizado') {
        // Solo excluir si no es la vista lista y no se solicita específicamente
        if ($viewType !== 'lista') {
            $queryMuestras->where('cotio_estado_analisis', '!=', 'analizado');
            $queryAnalisis->where('cotio_estado_analisis', '!=', 'analizado');
        }
    }

    $esPrivilegiado = $this->usuarioEsPrivilegiadoMisOrdenes($user);
    $puedeAlternarVistaAsignaciones = $esPrivilegiado;
    $soloMisAsignaciones = $this->resolverSoloMisAsignacionesMisOrdenes($user, $request);

    if ($soloMisAsignaciones) {
        if (OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
            OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($queryAnalisis, $user);
        } else {
            $this->aplicarFiltroUsuarioResponsableAnalisisInstancia($queryAnalisis, $codigo);
            $queryMuestras->whereRaw('1 = 0');
        }
    }
    // Privilegiado sin filtro: todas las muestras/análisis activos (resto de filtros aplican después)

    // Apply search filter
    if ($searchTerm) {
        $searchTerms = array_filter(explode(' ', trim($searchTerm)));
        $searchClosure = function ($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $searchTerm = '%' . strtolower($term) . '%';
                $q->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->where('coti_num', 'LIKE', $searchTerm)
                            ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(coti_descripcion) LIKE ?', [$searchTerm]);
                });
            }
        };

        $queryAnalisis->whereHas('tarea.cotizado', $searchClosure);
        $queryMuestras->whereHas('muestra.cotizado', $searchClosure);
    }

    if ($nombreAnalisis !== '') {
        $this->aplicarFiltroNombreAnalisisMisOrdenes($queryAnalisis, $queryMuestras, $nombreAnalisis);
    }

    // Apply date filters
    if ($fechaInicio) {
        $queryAnalisis->whereDate('fecha_inicio_ot', '>=', $fechaInicio);
        $queryMuestras->whereDate('fecha_inicio_ot', '>=', $fechaInicio);
    }
    if ($fechaFin) {
        $queryAnalisis->whereDate('fecha_fin_ot', '<=', $fechaFin);
        $queryMuestras->whereDate('fecha_fin_ot', '<=', $fechaFin);
    }

    // Apply status filter
    if ($estado) {
        $queryMuestras->where('cotio_estado_analisis', $estado);
        $queryAnalisis->where('cotio_estado_analisis', $estado);
    }

    // Get data
    $todosAnalisis = $queryAnalisis->get()->each(function ($item) {
        $item->setRelation('vehiculo', null);
    });

    if ($esPrivilegiado && !$soloMisAsignaciones) {
        $muestras = $queryMuestras->get()->each(function ($item) {
            $item->setRelation('vehiculo', null);
        });
    } else {
        $muestras = $this->cargarMuestrasPadreDeAnalisisAsignados($todosAnalisis, [
            'muestra.cotizado',
            'herramientas',
            'responsablesAnalisis',
            'tareas.responsablesAnalisis',
        ]);
    }

    $analitosSugeridos = $this->construirAnalitosSugeridosMisOrdenes(
        $todosAnalisis,
        $estado,
        $nombreAnalisis
    );

    // Group data correctly
    $ordenesAgrupadas = collect();

    if ($viewType === 'lista') {
        // Group by cotio_numcoti
        $muestras->each(function ($muestra) use (&$ordenesAgrupadas) {
            $key = $muestra->cotio_numcoti;
            
            if (!$ordenesAgrupadas->has($key)) {
                $ordenesAgrupadas->put($key, [
                    'instancias' => collect(),
                    'cotizado' => $muestra->muestra->cotizado ?? null,
                    'has_priority' => false
                ]);
            }

            $grupo = $ordenesAgrupadas->get($key);
            
            $cotioLinea = $muestra->muestra ?? $muestra->tarea ?? null;
            $esPrioriFila = PrioridadListado::prioridadEfectivaMuestreo($cotioLinea, $grupo['cotizado'] ?? null, $muestra);

            if ($esPrioriFila) {
                $grupo['has_priority'] = true;
            }

            // Add instance correctly
            $grupo['instancias']->push([
                'muestra' => $muestra->muestra,
                'instancia_muestra' => $muestra,
                'analisis' => collect(),
                'vehiculo' => null,
                'responsables_muestreo' => $muestra->responsablesAnalisis,
                'is_priority' => $esPrioriFila,
            ]);

            $ordenesAgrupadas->put($key, $grupo);
        });

        // Assign analyses correctly
        $todosAnalisis->each(function ($analisis) use (&$ordenesAgrupadas) {
            $key = $analisis->cotio_numcoti;
            
            if ($ordenesAgrupadas->has($key)) {
                $grupo = $ordenesAgrupadas->get($key);
                $instancia = $grupo['instancias']->firstWhere('instancia_muestra.instance_number', $analisis->instance_number);
                
                if ($instancia) {
                    $instancia['analisis']->push($analisis);
                } else {
                    $relatedSample = CotioInstancia::with(['muestra.cotizado', 'responsablesAnalisis'])
                        ->where([
                            'cotio_numcoti' => $analisis->cotio_numcoti,
                            'cotio_item' => $analisis->cotio_item,
                            'instance_number' => $analisis->instance_number,
                            'cotio_subitem' => 0,
                            'active_ot' => true
                        ])->first();

                    if ($relatedSample) {
                        $cotioLineaRel = $relatedSample->muestra ?? null;
                        $cotizadoRel = $cotioLineaRel->cotizado ?? null;
                        $esPrioriRel = PrioridadListado::prioridadEfectivaMuestreo($cotioLineaRel, $cotizadoRel, $relatedSample);

                        $newInstancia = [
                            'muestra' => $relatedSample->muestra,
                            'instancia_muestra' => $relatedSample,
                            'analisis' => collect([$analisis]),
                            'vehiculo' => null,
                            'responsables_muestreo' => $relatedSample->responsablesAnalisis,
                            'is_priority' => $esPrioriRel,
                        ];
                        
                        $grupo['instancias']->push($newInstancia);
                        
                        if ($esPrioriRel) {
                            $grupo['has_priority'] = true;
                        }
                        
                        $ordenesAgrupadas->put($key, $grupo);
                    }
                }
            } else {
                // Si no existe el grupo de la cotización (no había muestras por falta de asignación), crearlo
                $relatedSample = CotioInstancia::with(['muestra.cotizado', 'responsablesAnalisis'])
                    ->where([
                        'cotio_numcoti' => $analisis->cotio_numcoti,
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number,
                        'cotio_subitem' => 0,
                        'active_ot' => true
                    ])->first();

                if ($relatedSample) {
                    $cotioLineaRel = $relatedSample->muestra ?? null;
                    $cotizadoRel = $cotioLineaRel->cotizado ?? null;
                    $esPrioriRel = PrioridadListado::prioridadEfectivaMuestreo($cotioLineaRel, $cotizadoRel, $relatedSample);

                    $ordenesAgrupadas->put($key, [
                        'instancias' => collect([
                            [
                                'muestra' => $relatedSample->muestra,
                                'instancia_muestra' => $relatedSample,
                                'analisis' => collect([$analisis]),
                                'vehiculo' => null,
                                'responsables_muestreo' => $relatedSample->responsablesAnalisis,
                                'is_priority' => $esPrioriRel,
                            ]
                        ]),
                        'cotizado' => $cotizadoRel,
                        'has_priority' => $esPrioriRel,
                    ]);
                }
            }
        });

        // Aplicar el mismo criterio de ordenamiento que en el dashboard
        $ordenesAgrupadas = $ordenesAgrupadas->map(function ($grupo) {
            // Determinar el estado predominante del grupo considerando active_ot
            $muestrasDelGrupo = $grupo['instancias']->pluck('instancia_muestra');
            $estadoPredominante = $this->determinarEstadoPredominanteConActiveOt($muestrasDelGrupo);
            $grupo['estado_predominante'] = $estadoPredominante;
            return $grupo;
        });

        // Ordenar grupos según el criterio mejorado
        $ordenesAgrupadas = $ordenesAgrupadas->sortBy(function($grupo) {
            $esPrioritario = $grupo['has_priority'];
            $estadoPredominante = $grupo['estado_predominante'];
            

            
            return $this->calcularOrdenValorSimple($esPrioritario, $estadoPredominante);
        });

        // Sort instances within each group: priority first
        $ordenesAgrupadas = $ordenesAgrupadas->map(function ($grupo) {
            $grupo['instancias'] = $grupo['instancias']->sortByDesc('is_priority');
            return $grupo;
        });
    } else {
        // Logic for other view types
        $muestras->each(function ($muestra) use (&$ordenesAgrupadas) {
            $key = $muestra->cotio_numcoti . '_' . $muestra->instance_number . '_' . $muestra->cotio_item;
            $ordenesAgrupadas->put($key, [
                'muestra' => $muestra->muestra,
                'instancia_muestra' => $muestra,
                'analisis' => collect(),
                'cotizado' => $muestra->muestra->cotizado ?? null,
                'vehiculo' => null,
                'responsables_muestreo' => $muestra->responsablesAnalisis,
                'is_priority' => $muestra->es_priori
            ]);
        });

        $todosAnalisis->each(function ($analisis) use (&$ordenesAgrupadas) {
            $key = $analisis->cotio_numcoti . '_' . $analisis->instance_number . '_' . $analisis->cotio_item;
            
            if ($ordenesAgrupadas->has($key)) {
                $grupo = $ordenesAgrupadas->get($key);
                $grupo['analisis']->push($analisis);
                $ordenesAgrupadas->put($key, $grupo);
            } else {
                // Si la muestra no está presente (p.ej., no asignada explícitamente),
                // intentar traer la instancia de muestra relacionada para poder agrupar el análisis asignado
                $relatedSample = CotioInstancia::with(['muestra.cotizado', 'responsablesAnalisis'])
                    ->where([
                        'cotio_numcoti' => $analisis->cotio_numcoti,
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number,
                        'cotio_subitem' => 0,
                        'active_ot' => true
                    ])->first();

                if ($relatedSample) {
                    $ordenesAgrupadas->put($key, [
                        'muestra' => $relatedSample->muestra,
                        'instancia_muestra' => $relatedSample,
                        'analisis' => collect([$analisis]),
                        'cotizado' => $relatedSample->muestra->cotizado ?? null,
                        'vehiculo' => null,
                        'responsables_muestreo' => $relatedSample->responsablesAnalisis,
                        'is_priority' => $relatedSample->es_priori
                    ]);
                }
            }
        });
    }

    // Prepare pagination
    $allTasks = $muestras->merge($todosAnalisis)->values();
    $tareasPaginadas = new LengthAwarePaginator(
        $allTasks->forPage(LengthAwarePaginator::resolveCurrentPage(), $perPage),
        $allTasks->count(),
        $perPage,
        LengthAwarePaginator::resolveCurrentPage(),
        ['path' => LengthAwarePaginator::resolveCurrentPath()]
    );

    // Prepare calendar data if needed
    $events = collect();
    if ($viewType === 'calendario') {
        $events = $muestras->map(function ($muestra) use ($user) {
            $descripcion = $muestra->cotio_descripcion ?? ($muestra->muestra->cotio_descripcion ?? 'Muestra sin descripción');
            $cotC = optional($muestra->muestra)->cotizacion;
            $empresa = $cotC ? CotizacionClienteEtiqueta::paraLista($cotC) : '';
            
            $estado = strtolower($muestra->cotio_estado_analisis ?? 'coordinado');
            $className = match ($estado) {
                'coordinado', 'coordinado muestreo', 'coordinado analisis' => 'fc-event-warning',
                'en proceso', 'en revision muestreo', 'en revision analisis' => 'fc-event-info',
                'finalizado', 'muestreado', 'analizado' => 'fc-event-success',
                'suspension' => 'fc-event-danger',
                default => 'fc-event-primary'
            };
            
            if ($muestra->es_priori) {
                $className .= ' fc-event-priority';
            }
            
            return [
                'id' => $muestra->id,
                'title' => Str::limit($descripcion, 30),
                'start' => $muestra->fecha_inicio_ot,
                'end' => $muestra->fecha_fin_ot,
                'className' => $className,
                'url' => route('ordenes.all.show', [
                    $muestra->cotio_numcoti ?? 'N/A', 
                    $muestra->cotio_item ?? 'N/A', 
                    $muestra->cotio_subitem ?? 'N/A', 
                    $muestra->instance_number ?? 'N/A'
                ]),
                'extendedProps' => [
                    'descripcion' => $descripcion,
                    'empresa' => $empresa,
                    'estado' => $estado,
                    'priority' => $muestra->es_priori,
                    'otn' => $muestra->otn,
                ]
            ];
        });
    }

    // Get related quotations
    $cotizacionesIds = $todosAnalisis->pluck('cotio_numcoti')
        ->merge($muestras->pluck('cotio_numcoti'))
        ->unique();
    $cotizaciones = Coti::with(['cliente', 'sucursal'])
        ->whereIn('coti_num', $cotizacionesIds)
        ->get()
        ->keyBy('coti_num');
    CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($cotizaciones->values());

    $userCode = Auth::user()->usu_codigo;

    $view = $request->boolean('print')
        ? 'mis-ordenes.print'
        : 'mis-ordenes.index';

    return view($view, [
        'ordenesAgrupadas' => $ordenesAgrupadas,
        'cotizaciones' => $cotizaciones,
        'tareasPaginadas' => $tareasPaginadas,
        'viewType' => $viewType,
        'request' => $request,
        'currentMonth' => $currentMonth,
        'events' => $events,
        'analitosSugeridos' => $analitosSugeridos,
        'currentUserCode' => $userCode,
        'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones,
        'soloMisAsignaciones' => $soloMisAsignaciones,

    ]);
}


public function showDetalle($ordenId)
{
    $user = Auth::user();
    $cotizacion = Coti::findOrFail($ordenId);

    if (! OrdenesAccesoPorSector::cotizacionVisibleParaUsuario((int) $cotizacion->coti_num, $user)) {
        abort(403, 'No tiene acceso a esta orden de trabajo.');
    }

    $filtrarPorSector = OrdenesAccesoPorSector::debeFiltrarPorSector($user);
    $sectoresUsuario = $filtrarPorSector ? OrdenesAccesoPorSector::sectoresUsuario($user) : collect();

    $inventario = InventarioLab::all();

    // Obtener categorías (muestras) que van a lab: sin muestreo (lleva_muestreo=false)
    // o que ya pasaron muestreo (tienen instancia con enable_ot)
    $todasLasCategorias = $cotizacion->tareas()
        ->where('cotio_subitem', 0)
        ->orderBy('cotio_item')
        ->get();

    $matrizDescOrden = trim((string) (optional($cotizacion->matriz)->matriz_descripcion ?? ''));
    $categoriasHabilitadas = $todasLasCategorias->filter(function ($cat) use ($cotizacion, $matrizDescOrden) {
        if (CotizacionCanalEnsayo::ensayoExcluidoDeOrdenes($cat, $matrizDescOrden)) {
            return false;
        }
        // Sin muestreo (explícitamente false): va directo a lab
        if ($cat->lleva_muestreo === false) {
            return true;
        }
        // Con muestreo (true o null): incluir solo si ya tiene instancia con enable_ot (pasó muestreo)
        return CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_item', $cat->cotio_item)
            ->where('cotio_subitem', 0)
            ->where('enable_ot', true)
            ->exists();
    })->values();

    $categoriasIds = $categoriasHabilitadas->pluck('cotio_item')->toArray();

    // Obtener todos los análisis (subitems > 0) de las categorías
    $tareas = $cotizacion->tareas()
        ->with(['metodoAnalisis', 'metodoLegacy', 'metodoMuestreo'])
        ->whereIn('cotio_item', $categoriasIds)
        ->where('cotio_subitem', '!=', 0)
        ->orderBy('cotio_item')
        ->orderBy('cotio_subitem')
        ->get();

    $usuarios = User::where('rol', 'sector')
        ->orderBy('usu_descripcion')
        ->get();

    $agrupadas = [];
    $metodosUnicos = collect();

    foreach ($categoriasHabilitadas as $categoria) {
        $item = $categoria->cotio_item;
        $cantidad = max(1, (int)$categoria->cotio_cantidad); // Mínimo 1 instancia

        // Obtener instancias existentes
        $instanciasExistentes = CotioInstancia::with('herramientas', 'responsablesAnalisis', 'coordinadorLab')
            ->where([
                'cotio_numcoti' => $cotizacion->coti_num,
                'cotio_item' => $item,
                'cotio_subitem' => 0
            ])
            ->orderBy('instance_number')
            ->get()
            ->keyBy('instance_number');

        // Qué instancias mostrar: si va directo a lab (lleva_muestreo=false), todas (1..cantidad);
        // si pasó por muestreo, solo las que tienen enable_ot
        $numerosInstanciaAMostrar = $categoria->lleva_muestreo === false
            ? range(1, $cantidad)
            : $instanciasExistentes->where('enable_ot', true)->keys()->sort()->values()->all();

        $tareasDeCategoria = $tareas->where('cotio_item', $item);
        $instanciasConAnalisis = collect();

        foreach ($numerosInstanciaAMostrar as $instanceNumber) {
            // Verificar si existe una instancia real
            $instanciaMuestra = $instanciasExistentes->get($instanceNumber);

            if (!$instanciaMuestra) {
                // Crear una instancia "virtual" para mostrar en la vista
                $instanciaMuestra = new CotioInstancia([
                    'cotio_numcoti' => $cotizacion->coti_num,
                    'cotio_item' => $item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instanceNumber,
                    'active_ot' => false,
                    'enable_ot' => false,
                ]);
                $instanciaMuestra->id = null; // Marcar como no persistida
                $instanciaMuestra->setRelation('responsablesAnalisis', collect());
                $instanciaMuestra->setRelation('herramientas', collect());
            }

            // Mapear análisis para esta instancia
            $analisisParaInstancia = $tareasDeCategoria->map(function($tarea) use ($instanciaMuestra, $cotizacion, &$metodosUnicos, $instanceNumber) {
                $tareaClonada = clone $tarea;
                
                // Buscar instancia de análisis existente
                $instanciaAnalisis = CotioInstancia::with('herramientas', 'responsablesAnalisis', 'metodoAnalisis')
                    ->where([
                        'cotio_numcoti' => $cotizacion->coti_num,
                        'cotio_item' => $tarea->cotio_item,
                        'cotio_subitem' => $tarea->cotio_subitem,
                        'instance_number' => $instanceNumber
                    ])
                    ->first();

                if ($instanciaAnalisis) {
                    $tareaClonada->instancia = $instanciaAnalisis;
                } else {
                    $instanciaVirtual = new CotioInstancia([
                        'cotio_numcoti' => (int)$cotizacion->coti_num,
                        'cotio_item' => (int)$tarea->cotio_item,
                        'cotio_subitem' => (int)$tarea->cotio_subitem,
                        'instance_number' => (int)$instanceNumber,
                        'active_ot' => false,
                        'enable_ot' => false,
                        'cotio_codigometodo_analisis' => $tarea->cotio_codigometodo_analisis,
                        'cotio_codigometodo' => $tarea->cotio_codigometodo,
                    ]);
                    $instanciaVirtual->id = (int)$cotizacion->coti_num . "_" . (int)$tarea->cotio_item . "_" . (int)$tarea->cotio_subitem . "_" . (int)$instanceNumber;
                    $tareaClonada->instancia = $instanciaVirtual;
                }

                $codigoMetodo = EtiquetaMetodoAnalisis::codigo($tareaClonada);
                if ($codigoMetodo !== '') {
                    $metodoLegacy = Metodo::whereRaw('TRIM(metodo_codigo) = ?', [$codigoMetodo])->first();
                    if ($metodoLegacy) {
                        $metodosUnicos->push([
                            'codigo' => $codigoMetodo,
                            'metodo' => $metodoLegacy,
                        ]);
                    }
                }

                return $tareaClonada;
            })->filter(function ($tareaClonada) use ($filtrarPorSector, $sectoresUsuario) {
                if (! $filtrarPorSector) {
                    return true;
                }

                $instanciaAnalisis = $tareaClonada->instancia ?? null;
                if (! $instanciaAnalisis || ! $instanciaAnalisis->id) {
                    return false;
                }

                return OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($instanciaAnalisis, $sectoresUsuario);
            })->values();

            if ($filtrarPorSector && $analisisParaInstancia->isEmpty()) {
                continue;
            }

            $instanciasConAnalisis->push([
                'muestra' => $instanciaMuestra,
                'analisis' => $analisisParaInstancia
            ]);
        }

        $agrupadas[] = [
            'categoria' => $categoria,
            'instancias' => $instanciasConAnalisis
        ];
    }

    // Obtener métodos únicos (sin duplicados)
    $metodosUnicos = $metodosUnicos->unique(function ($item) {
        return $item['codigo'];
    })->map(function ($item) {
        return $item['metodo'];
    })->filter()->sortBy('metodo_descripcion')->values();

    return view('ordenes.show', compact('cotizacion', 'usuarios', 'agrupadas', 'inventario', 'metodosUnicos'));
}




public function verOrden($cotizacion, $item, $instance = null)
{
    if (! $cotizacion instanceof Coti) {
        $cotizacion = Coti::findOrFail($cotizacion);
    }
    $instance = $instance ?? 1;
    $user = Auth::user();

    if (! OrdenesAccesoPorSector::cotizacionVisibleParaUsuario((int) $cotizacion->coti_num, $user)) {
        abort(403, 'No tiene acceso a esta orden de trabajo.');
    }

    $filtrarPorSector = OrdenesAccesoPorSector::debeFiltrarPorSector($user);
    $sectoresUsuario = $filtrarPorSector ? OrdenesAccesoPorSector::sectoresUsuario($user) : collect();

    $usuariosAnalistas = User::where('rol', '!=', 'sector')
                ->orderBy('usu_descripcion')
                ->get();
    $usuariosSectores = User::where('rol', 'sector')
                ->orderBy('usu_descripcion')
                ->get();

    // Obtener la muestra principal
    $categoria = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $item)
                ->where('cotio_subitem', 0)
                ->firstOrFail();

    // Obtener la instancia de la muestra con responsables de análisis
    $instanciaMuestra = CotioInstancia::with(['responsablesAnalisis', 'valoresVariables', 'aprobadorInforme'])
                ->where([
                    'cotio_numcoti' => $cotizacion->coti_num,
                    'cotio_item' => $item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instance,
                ])->first();

    if ($instanciaMuestra) {
        AnalisisResultadoValidacion::sincronizarEstadoMuestraDesdeAnalisis(
            $instanciaMuestra->cotio_numcoti,
            $instanciaMuestra->cotio_item,
            $instanciaMuestra->instance_number
        );
        $instanciaMuestra->refresh();
    }

    $variablesOrdenadas = collect();
    if ($instanciaMuestra && $instanciaMuestra->valoresVariables) {
        $variablesOrdenadas = $instanciaMuestra->valoresVariables
            ->sortBy('variable')
            ->values();
    }

    // Obtener herramientas manualmente para la instancia de muestra
    $herramientasMuestra = collect();
    if ($instanciaMuestra) {
        $herramientasMuestra = DB::table('cotio_inventario_muestreo')
            ->where('cotio_numcoti', $instanciaMuestra->cotio_numcoti)
            ->where('cotio_item', $instanciaMuestra->cotio_item)
            ->where('cotio_subitem', $instanciaMuestra->cotio_subitem)
            ->where('instance_number', $instanciaMuestra->instance_number)
            ->join('inventario_muestreo', 'cotio_inventario_muestreo.inventario_muestreo_id', '=', 'inventario_muestreo.id')
            ->select(
                'inventario_muestreo.*',
                'cotio_inventario_muestreo.cantidad',
                'cotio_inventario_muestreo.observaciones as pivot_observaciones'
            )
            ->get();

        // Evitar propiedades dinámicas: exponer como relación en memoria
        $instanciaMuestra->setRelation('herramientas', $herramientasMuestra);
    }

    // Obtener historial de cambios para los resultados de análisis
    $historialCambios = collect();
    if ($instanciaMuestra) {
        $tareas = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_item', $item)
            ->where('cotio_subitem', '!=', 0)
            ->orderBy('cotio_subitem')
            ->get();

        $instanciaIds = $tareas->map(function ($tarea) use ($instance, $filtrarPorSector, $sectoresUsuario) {
            $instanciaAnalisis = CotioInstancia::with('responsablesAnalisis')->where([
                'cotio_numcoti' => $tarea->cotio_numcoti,
                'cotio_item' => $tarea->cotio_item,
                'cotio_subitem' => $tarea->cotio_subitem,
                'instance_number' => $instance,
                'active_ot' => true
            ])->first();

            if (! $instanciaAnalisis) {
                return null;
            }

            if ($filtrarPorSector && ! OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($instanciaAnalisis, $sectoresUsuario)) {
                return null;
            }

            return $instanciaAnalisis->id;
        })->filter()->values();

        if ($instanciaIds->isNotEmpty()) {
            $historialCambios = CotioHistorialCambios::where('tabla_afectada', 'cotio_instancias')
                ->whereIn('registro_id', $instanciaIds)
                ->whereIn('campo_modificado', ['resultado', 'resultado_2', 'resultado_3', 'resultado_final'])
                ->with(['usuario' => function ($query) {
                    $query->select('usu_codigo', 'usu_descripcion');
                }])
                ->orderBy('fecha_cambio', 'desc')
                ->get()
                ->groupBy('registro_id');
        }
    }

    if (!$instanciaMuestra) {
        return view('ordenes.tareasporcategoria', [
            'cotizacion' => $cotizacion,
            'categoria' => $categoria,
            'tareas' => collect(),
            'usuarios' => collect(),
            'inventario' => collect(),
            'instance' => $instance,
            'instanciaActual' => null,
            'variablesMuestra' => $variablesOrdenadas,
            'instanciasMuestra' => collect(),
            'historialCambios' => collect(),
            'usuariosAnalistas' => $usuariosAnalistas,
            'usuariosSectores' => $usuariosSectores,
        ]);
    }

    // Obtener tareas (análisis)
    $tareas = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $item)
                ->where('cotio_subitem', '!=', 0)
                ->orderBy('cotio_subitem')
                ->get();

    $tareasConInstancias = $tareas->map(function($tarea) use ($instance) {
        $instancia = CotioInstancia::with(['responsablesAnalisis', 'valoresVariables'])
            ->where([
                'cotio_numcoti' => $tarea->cotio_numcoti,
                'cotio_item' => $tarea->cotio_item,
                'cotio_subitem' => $tarea->cotio_subitem,
                'instance_number' => $instance,
                'active_ot' => true
            ])->first();

        if ($instancia) {
            // Obtener herramientas manualmente para cada análisis
            $herramientasAnalisis = DB::table('cotio_inventario_lab')
                ->where('cotio_numcoti', $instancia->cotio_numcoti)
                ->where('cotio_item', $instancia->cotio_item)
                ->where('cotio_subitem', $instancia->cotio_subitem)
                ->where('instance_number', $instancia->instance_number)
                ->join('inventario_lab', 'cotio_inventario_lab.inventario_lab_id', '=', 'inventario_lab.id')
                ->select(
                    'inventario_lab.*',
                    'cotio_inventario_lab.cantidad',
                    'cotio_inventario_lab.observaciones as pivot_observaciones'
                )
                ->get();

            // Evitar propiedades dinámicas: exponer como relación en memoria
            $instancia->setRelation('herramientasLab', $herramientasAnalisis);
            $instancia->setAttribute(
                'puede_editar_fechas_informe',
                $this->userCanEditAnalistaFechasInforme(Auth::user(), $instancia)
                    && ! $this->instanciaMuestraEstaAnalizada($instancia)
            );
            $tarea->instancia = $instancia;
            return $tarea;
        }
        return null;
    })->filter(function ($tarea) use ($filtrarPorSector, $sectoresUsuario) {
        if (! $tarea || ! $filtrarPorSector) {
            return (bool) $tarea;
        }

        return OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($tarea->instancia, $sectoresUsuario);
    });

    if ($filtrarPorSector && $instanciaMuestra && ! OrdenesAccesoPorSector::muestraTieneAnalisisEnSectores($instanciaMuestra, $sectoresUsuario)) {
        abort(403, 'No tiene acceso a los análisis de esta muestra en su laboratorio.');
    }

    $usuarios = User::where('usu_nivel', '<=', 500)
                ->orderBy('usu_descripcion')
                ->get();

    $inventario = InventarioLab::all();
    $vehiculos = Vehiculo::all();

    // Obtener todas las instancias de muestra con responsables de análisis
    $instanciasMuestra = CotioInstancia::with(['responsablesAnalisis', 'valoresVariables'])
                        ->where('cotio_numcoti', $cotizacion->coti_num)
                        ->where('cotio_item', $item)
                        ->where('cotio_subitem', 0)
                        ->get()
                        ->keyBy('instance_number');

    // Obtener todos los responsables únicos de todas las tareas de la instancia actual
    $todosResponsablesTareas = collect();
    foreach ($tareasConInstancias as $tarea) {
        if ($tarea->instancia && $tarea->instancia->responsablesAnalisis) {
            $todosResponsablesTareas = $todosResponsablesTareas->merge($tarea->instancia->responsablesAnalisis);
        }
    }
    $todosResponsablesTareas = $todosResponsablesTareas->unique('usu_codigo');

    return view('ordenes.tareasporcategoria', [
        'cotizacion' => $cotizacion,
        'categoria' => $categoria,
        'tareas' => $tareasConInstancias,
        'usuarios' => $usuarios,
        'inventario' => $inventario,
        'instance' => $instance,
        'vehiculos' => $vehiculos,
        'instanciaActual' => $instanciaMuestra,
        'instanciasMuestra' => $instanciasMuestra,
        'variablesMuestra' => $variablesOrdenadas,
        'todosResponsablesTareas' => $todosResponsablesTareas,
        'historialCambios' => $historialCambios,
        'usuariosAnalistas' => $usuariosAnalistas,
        'usuariosSectores' => $usuariosSectores,
    ]);
}



public function asignarDetallesAnalisis(Request $request) 
{
    $this->authorizeGestionOrdenes();

    try {
        DB::beginTransaction();
        
        $actualizarRegistro = function($registro) use ($request) {
            // Actualizar vehículo si está en la solicitud
            if ($request->filled('vehiculo_asignado')) {
                $vehiculoAnterior = $registro->vehiculo_asignado;
                $nuevoVehiculo = $request->vehiculo_asignado;

                $registro->vehiculo_asignado = $nuevoVehiculo;
                if ($nuevoVehiculo) {
                    Vehiculo::where('id', $nuevoVehiculo)->update(['estado' => 'ocupado']);
                }

                if ($vehiculoAnterior && $vehiculoAnterior != $nuevoVehiculo) {
                    Vehiculo::where('id', $vehiculoAnterior)->update(['estado' => 'libre']);
                }
            }

            // Actualizar responsable si está en la solicitud
            if ($request->filled('responsable_codigo')) {
                $registro->responsable_analisis = $request->responsable_codigo === 'NULL' ? null : $request->responsable_codigo;
            }

            // Actualizar fechas si están en la solicitud
            if ($request->filled('fecha_inicio_ot')) {
                $registro->fecha_inicio_ot = $request->fecha_inicio_ot;
            }
            if ($request->filled('fecha_fin_ot')) {
                $registro->fecha_fin_ot = $request->fecha_fin_ot;
            }

            $registro->save();
        };

        $actualizarHerramientas = function($cotio_numcoti, $cotio_item, $cotio_subitem, $instance_number) use ($request) {
            if ($request->filled('herramientas')) {
                // Primero eliminamos todas las herramientas existentes para esta instancia
                DB::table('cotio_inventario_lab')
                    ->where('cotio_numcoti', $cotio_numcoti)
                    ->where('cotio_item', $cotio_item)
                    ->where('cotio_subitem', $cotio_subitem)
                    ->where('instance_number', $instance_number)
                    ->delete();

                // Insertamos las nuevas herramientas
                foreach ($request->herramientas as $herramientaId) {
                    DB::table('cotio_inventario_lab')->insert([
                        'cotio_numcoti' => $cotio_numcoti,
                        'cotio_item' => $cotio_item,
                        'cotio_subitem' => $cotio_subitem,
                        'instance_number' => $instance_number,
                        'inventario_lab_id' => $herramientaId,
                        'cantidad' => 1,
                        'observaciones' => null
                    ]);
                    
                    // Actualizamos el estado del inventario
                    InventarioLab::where('id', $herramientaId)->update(['estado' => 'ocupado']);
                }
            }
        };

        // 1. Actualizar la instancia de la muestra principal (subitem = 0)
        $instanciaMuestra = CotioInstancia::where('cotio_numcoti', $request->cotio_numcoti)
            ->where('cotio_item', $request->cotio_item)
            ->where('cotio_subitem', 0)
            ->where('instance_number', $request->instance_number)
            ->first();

        if ($instanciaMuestra) {
            $actualizarRegistro($instanciaMuestra);
            $actualizarHerramientas(
                $request->cotio_numcoti, 
                $request->cotio_item, 
                0, 
                $request->instance_number
            );
        }

        // 2. Actualizar instancias de tareas seleccionadas si existen
        if ($request->tareas_seleccionadas && count($request->tareas_seleccionadas) > 0) {
            foreach ($request->tareas_seleccionadas as $tarea) {
                $instanciaTarea = CotioInstancia::where('cotio_numcoti', $request->cotio_numcoti)
                    ->where('cotio_item', $tarea['item'])
                    ->where('cotio_subitem', $tarea['subitem'])
                    ->where('instance_number', $request->instance_number)
                    ->first();

                if ($instanciaTarea) {
                    $actualizarRegistro($instanciaTarea);
                    $actualizarHerramientas(
                        $request->cotio_numcoti, 
                        $tarea['item'], 
                        $tarea['subitem'], 
                        $request->instance_number
                    );
                } else {
                    // Si no existe la instancia, la creamos
                    // Obtener datos desde cotio para copiar métodos
                    $cotioData = Cotio::where('cotio_numcoti', $request->cotio_numcoti)
                        ->where('cotio_item', $tarea['item'])
                        ->where('cotio_subitem', $tarea['subitem'])
                        ->first();
                    
                    // Copiar ambos métodos siempre desde Cotio
                    $nuevaInstancia = CotioInstancia::create([
                        'cotio_numcoti' => $request->cotio_numcoti,
                        'cotio_item' => $tarea['item'],
                        'cotio_subitem' => $tarea['subitem'],
                        'instance_number' => $request->instance_number,
                        'responsable_analisis' => $request->responsable_codigo === 'NULL' ? null : $request->responsable_codigo,
                        'fecha_inicio_ot' => $request->fecha_inicio_ot,
                        'fecha_fin_ot' => $request->fecha_fin_ot,
                        'vehiculo_asignado' => $request->vehiculo_asignado,
                        'active_ot' => true,
                        'cotio_codigometodo' => $cotioData->cotio_codigometodo ?? null,
                        'cotio_codigometodo_analisis' => $cotioData->cotio_codigometodo_analisis ?? null
                    ]);

                    if ($request->filled('herramientas')) {
                        $actualizarHerramientas(
                            $request->cotio_numcoti, 
                            $tarea['item'], 
                            $tarea['subitem'], 
                            $request->instance_number
                        );
                    }
                }
            }
        }

        DB::commit();
        return response()->json([
            'success' => true, 
            'message' => 'Elementos asignados correctamente a las instancias'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar las instancias: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]
        ], 500);
    }
}


public function pasarAnalisis(Request $request)
{
    try {
        $cotizacionId = $request->cotizacion_id;
        $cambios = $request->cambios;

        // Verificar si ya existen instancias activas para los análisis seleccionados
        foreach ($cambios as $cambio) {
            $instanciaExistente = CotioInstancia::where([
                'cotio_numcoti' => $cotizacionId,
                'cotio_item' => $cambio['item'],
                'cotio_subitem' => $cambio['subitem'],
                'instance_number' => $cambio['instance'],
                'active_ot' => true
            ])->first();

            if ($instanciaExistente) {
                return response()->json([
                    'success' => false,
                    'message' => "El análisis ya está activo en la instancia {$cambio['instance']}. Por favor, desactive la instancia actual antes de crear una nueva."
                ]);
            }
        }

        DB::beginTransaction();

        foreach ($cambios as $cambio) {
            // Crear nueva instancia para el análisis
            $instancia = new CotioInstancia();
            $instancia->cotio_numcoti = $cotizacionId;
            $instancia->cotio_item = $cambio['item'];
            $instancia->cotio_subitem = $cambio['subitem'];
            $instancia->instance_number = $cambio['instance'];
            $instancia->active_ot = $cambio['activo'];
            $instancia->cotio_estado_analisis = 'pendiente';
            $instancia->save();

            // Actualizar estado en la tabla cotio
            Cotio::where([
                'cotio_numcoti' => $cotizacionId,
                'cotio_item' => $cambio['item'],
                'cotio_subitem' => $cambio['subitem']
            ])->update([
                'cotio_estado_analisis' => 'pendiente'
            ]);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Análisis pasados correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al pasar a análisis: ' . $e->getMessage()
        ]);
    }
}


public function showOrdenesAll(Request $request, $cotio_numcoti, $cotio_item, $cotio_subitem = 0, $instance = null)
{
    $instance = $instance ?? 1;
    $usuario = Auth::user();
    $usuarioActual = trim($usuario->usu_codigo);
    $esPrivilegiado = $this->usuarioEsPrivilegiadoMisOrdenes($usuario);
    $puedeAlternarVistaAsignaciones = $esPrivilegiado;
    $soloMisAsignaciones = $this->resolverSoloMisAsignacionesMisOrdenes($usuario, $request);
    $allHerramientas = InventarioLab::all();

    try {
        // Depurar datos en instancia_responsable_analisis
        $responsablesAsignados = DB::table('instancia_responsable_analisis')
            ->join('cotio_instancias', 'instancia_responsable_analisis.cotio_instancia_id', '=', 'cotio_instancias.id')
            ->where('cotio_instancias.cotio_numcoti', $cotio_numcoti)
            ->where('cotio_instancias.cotio_item', $cotio_item)
            ->where('cotio_instancias.instance_number', $instance)
            ->select('instancia_responsable_analisis.usu_codigo', 'cotio_instancias.id', 'cotio_instancias.cotio_subitem')
            ->get();

        Log::debug('Responsables asignados encontrados', [
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'instance' => $instance,
            'responsables' => $responsablesAsignados->map(function ($item) {
                return ['usu_codigo' => $item->usu_codigo, 'instancia_id' => $item->id, 'cotio_subitem' => $item->cotio_subitem];
            })->toArray()
        ]);

        // Obtener la instancia de muestra principal sin exigir responsable directo
        $instanciaMuestra = CotioInstancia::with([
            'muestra.cotizacion',
            'muestra.leyNormativa.variables',
            'valoresVariables' => function ($query) {
                $query->select('id', 'cotio_instancia_id', 'variable', 'valor')
                      ->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function ($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            }
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', 0)
        ->where('instance_number', $instance)
        ->first();

        if ($instanciaMuestra) {
            $instanciaMuestra->setRelation('vehiculo', null);
        }

        if (!$instanciaMuestra) {
            Log::warning('No se encontró instancia de muestra', [
                'user' => $usuarioActual,
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $cotio_item,
                'instance' => $instance
            ]);
            return view('mis-ordenes.show-by-categoria', [
                'instancia' => null,
                'analisis' => collect(),
                'instanceNumber' => $instance,
                'allHerramientas' => $allHerramientas,
                'error' => 'No se encontró la muestra principal.',
                'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones,
                'soloMisAsignaciones' => $soloMisAsignaciones,
            ]);
        }

        Log::debug('Instancia de muestra encontrada', [
            'instancia_id' => $instanciaMuestra->id,
            'responsables' => $instanciaMuestra->responsablesAnalisis->pluck('usu_codigo')->toArray()
        ]);

        // Obtener análisis - coordinadores pueden ver todos, otros solo los asignados
        $analisisQuery = CotioInstancia::with([
            'tarea' => fn ($q) => $q->with(['metodoLegacy', 'metodoMuestreo', 'metodoAnalisis']),
            'tarea.cotizacion',
            'responsablesAnalisis',
            'herramientasLab' => function ($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            }
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', '>', 0)
        ->where('active_ot', true)
        ->where('instance_number', $instance);

        if ($soloMisAsignaciones) {
            if (OrdenesAccesoPorSector::debeFiltrarPorSector($usuario)) {
                OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($analisisQuery, $usuario);
            } else {
                $this->aplicarFiltroUsuarioResponsableAnalisisInstancia($analisisQuery, $usuarioActual);
            }
        }

        $analisis = $analisisQuery->orderBy('cotio_subitem')->get()->each(function ($item) {
            $item->setRelation('vehiculo', null);
        });

        if ($soloMisAsignaciones && $analisis->isEmpty()) {
            return view('mis-ordenes.show-by-categoria', [
                'instancia' => $instanciaMuestra,
                'analisis' => collect(),
                'instanceNumber' => $instance,
                'allHerramientas' => $allHerramientas,
                'error' => 'No tiene análisis asignados en esta muestra.',
                'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones,
                'soloMisAsignaciones' => $soloMisAsignaciones,
            ]);
        }

        foreach ($analisis as $itemAnalisis) {
            $itemAnalisis->setAttribute(
                'puede_editar_fechas_informe',
                $this->userCanEditAnalistaFechasInforme($usuario, $itemAnalisis)
                    && ! $this->instanciaMuestraEstaAnalizada($instanciaMuestra)
            );
        }

        Log::debug('Análisis encontrados', [
            'count' => $analisis->count(),
            'instancia_ids' => $analisis->pluck('id')->toArray(),
            'responsables' => $analisis->map(function ($item) {
                return $item->responsablesAnalisis->pluck('usu_codigo')->toArray();
            })->toArray()
        ]);

        return view('mis-ordenes.show-by-categoria', [
            'instancia' => $instanciaMuestra,
            'analisis' => $analisis,
            'instanceNumber' => $instance,
            'allHerramientas' => $allHerramientas,
            'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones,
            'soloMisAsignaciones' => $soloMisAsignaciones,
        ]);

    } catch (\Exception $e) {
        Log::error('Error al mostrar órdenes', [
            'user' => $usuarioActual,
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance' => $instance,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return view('mis-ordenes.show-by-categoria', [
            'instancia' => null,
            'analisis' => collect(),
            'instanceNumber' => $instance,
            'allHerramientas' => $allHerramientas,
            'error' => 'Error al cargar la muestra: ' . $e->getMessage(),
            'puedeAlternarVistaAsignaciones' => $puedeAlternarVistaAsignaciones ?? false,
            'soloMisAsignaciones' => $soloMisAsignaciones ?? true,
        ]);
    }
}



public function updateHerramientas(Request $request, $instanciaId)
{
    $instancia = CotioInstancia::findOrFail($instanciaId);

    if ($this->instanciaMuestraEstaAnalizada($instancia)) {
        return response()->json([
            'success' => false,
            'message' => 'No se pueden editar herramientas: la muestra ya está analizada.',
        ], 403);
    }

    $request->validate([
        'herramientas' => 'nullable|array',
        'herramientas.*' => 'exists:inventario_lab,id',
        'cantidades' => 'nullable|array',
        'cantidades.*' => 'integer|min:1',
        'observaciones' => 'nullable|array',
    ]);

    $herramientasData = [];
    if ($request->herramientas) {
        foreach ($request->herramientas as $herramientaId) {
            $herramientasData[$herramientaId] = [
                'cantidad' => $request->cantidades[$herramientaId] ?? 1,
                'observaciones' => $request->observaciones[$herramientaId] ?? null,
                'cotio_numcoti' => $instancia->cotio_numcoti,
                'cotio_item' => $instancia->cotio_item,
                'cotio_subitem' => $instancia->cotio_subitem,
                'instance_number' => $instancia->instance_number
            ];
        }
    }

    $instancia->herramientasLab()->sync($herramientasData);

    return response()->json([
        'success' => true,
        'message' => 'Estado actualizado correctamente'
    ]);
}

/**
 * La muestra principal (cotio_subitem = 0) de la instancia indicada está en estado analizado.
 */
private function instanciaMuestraEstaAnalizada(CotioInstancia $instancia): bool
{
    $muestra = (int) $instancia->cotio_subitem === 0
        ? $instancia
        : CotioInstancia::query()
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('cotio_subitem', 0)
            ->where('instance_number', $instancia->instance_number)
            ->first();

    if (! $muestra) {
        return false;
    }

    return strtolower(trim((string) ($muestra->cotio_estado_analisis ?? ''))) === 'analizado';
}

/**
 * Puede editar fechas manuales de análisis (informe) para una instancia de ensayo (cotio_subitem > 0):
 * admin, coordinador de lab, roles con "gerente"/"jefe", o analista asignado a ese ensayo.
 */
private function userCanEditAnalistaFechasInforme(User $user, CotioInstancia $instanciaAnalisis): bool
{
    if ((int) $instanciaAnalisis->cotio_subitem <= 0) {
        return false;
    }
    if ((int) ($user->usu_nivel ?? 0) >= 900) {
        return true;
    }
    if ($user->hasAnyRole(['coordinador_lab'])) {
        return true;
    }
    foreach ($user->all_roles as $rol) {
        $lr = strtolower((string) $rol);
        if (str_contains($lr, 'gerente') || str_contains($lr, 'jefe')) {
            return true;
        }
    }
    $codigo = trim((string) $user->usu_codigo);

    return DB::table('instancia_responsable_analisis')
        ->where('cotio_instancia_id', $instanciaAnalisis->id)
        ->whereRaw('TRIM(usu_codigo) = ?', [$codigo])
        ->exists();
}

public function updateAnalistaFechasAnalisis(Request $request, CotioInstancia $instancia)
{
    if ((int) $instancia->cotio_subitem <= 0) {
        abort(404);
    }

    $user = Auth::user();
    if (! $this->userCanEditAnalistaFechasInforme($user, $instancia)) {
        abort(403, 'No autorizado para editar estas fechas.');
    }
    $this->authorizeAccesoInstanciaAnalisis($instancia);

    if ($this->instanciaMuestraEstaAnalizada($instancia)) {
        abort(403, 'No se pueden editar fechas: la muestra ya está analizada.');
    }

    $inicio = $request->input('analista_fecha_inicio');
    $fin = $request->input('analista_fecha_fin');
    $request->merge([
        'analista_fecha_inicio' => ($inicio !== null && $inicio !== '') ? $inicio : null,
        'analista_fecha_fin' => ($fin !== null && $fin !== '') ? $fin : null,
    ]);

    $validated = $request->validate([
        'analista_fecha_inicio' => 'nullable|date',
        'analista_fecha_fin' => 'nullable|date|after_or_equal:analista_fecha_inicio',
    ]);

    $instancia->update([
        'analista_fecha_inicio' => $validated['analista_fecha_inicio'] ?? null,
        'analista_fecha_fin' => $validated['analista_fecha_fin'] ?? null,
    ]);

    return redirect()
        ->back()
        ->with('success', 'Fechas de análisis para el informe guardadas correctamente.');
}





public function asignacionMasiva(Request $request, $ordenId)
{
    $this->authorizeGestionOrdenes();

    Log::info('Iniciando asignación masiva', [
        'ordenId' => $ordenId,
        'user' => Auth::user()->usu_codigo ?? 'unknown',
        'request' => $request->all()
    ]);

    $cotizacion = Coti::findOrFail($ordenId);

    $validated = $request->validate([
        'instancia_selecciones' => 'required_without:tarea_selecciones|array',
        'instancia_selecciones.*' => 'string',
        'tarea_selecciones' => 'required_without:instancia_selecciones|array',
        'tarea_selecciones.*' => 'string',
        'responsables_analisis' => 'nullable|array',
        'responsables_analisis.*' => 'exists:usu,usu_codigo',
        'herramientas_lab' => 'nullable|array',
        'herramientas_lab.*' => 'exists:inventario_lab,id',
        'fecha_inicio_ot' => 'nullable|date',
        'fecha_fin_ot' => 'nullable|date|after:fecha_inicio_ot',
        'aplicar_a_gemelas' => 'boolean'
    ]);

    DB::beginTransaction();
    try {
        $instanciaSelecciones = $validated['instancia_selecciones'] ?? [];
        $tareaSelecciones = $validated['tarea_selecciones'] ?? [];
        $herramientasLab = $validated['herramientas_lab'] ?? [];
        $responsablesAnalisis = array_map('trim', $validated['responsables_analisis'] ?? []);
        $aplicarAGemelas = $validated['aplicar_a_gemelas'] ?? false;
        $updatedCount = 0;

        Log::debug('Datos validados', [
            'instancia_selecciones' => $instanciaSelecciones,
            'tarea_selecciones' => $tareaSelecciones,
            'responsables_analisis' => $responsablesAnalisis,
            'herramientas_lab' => $herramientasLab,
            'aplicar_a_gemelas' => $aplicarAGemelas
        ]);

        // 1. Expandir sectores (rol=sector) y líderes legacy a usuarios reales
        $usuariosParaAsignar = AsignacionSectorLaboratorio::expandirSeleccionAResponsables($responsablesAnalisis);
        if ($responsablesAnalisis !== [] && $usuariosParaAsignar->isEmpty()) {
            throw new \Exception('No se encontraron usuarios para los sectores o responsables seleccionados.');
        }

        $usuariosASincronizar = $usuariosParaAsignar->pluck('usu_codigo')->values()->all();

        Log::info('Usuarios a sincronizar', [
            'usuarios' => $usuariosASincronizar,
            'total' => count($usuariosASincronizar)
        ]);

        // 2. Obtener/crear todas las instancias seleccionadas
        $allSelections = array_merge($instanciaSelecciones, $tareaSelecciones);
        $instanciasSeleccionadas = collect();
        
        Log::info('=== INICIO PROCESAMIENTO ASIGNACION MASIVA ===', [
            'instancia_selecciones_raw' => $instanciaSelecciones,
            'tarea_selecciones_raw' => $tareaSelecciones,
            'all_selections' => $allSelections
        ]);
        
        // Mapa para trackear IDs virtuales -> IDs reales
        $mapaIdsVirtualesAReales = [];
        
        // Separar IDs numéricos de IDs virtuales (formato: numcoti_item_subitem_instance)
        $idsNumericos = [];
        $idsVirtuales = [];
        
        foreach ($allSelections as $id) {
            $idStr = (string)$id;
            Log::debug('Procesando ID', ['id' => $id, 'tipo' => gettype($id), 'is_numeric' => is_numeric($id), 'tiene_underscore' => strpos($idStr, '_') !== false]);
            
            if (is_numeric($id)) {
                $idsNumericos[] = (int)$id;
            } elseif (strpos($idStr, '_') !== false) {
                $idsVirtuales[] = $idStr;
            }
        }
        
        Log::debug('IDs clasificados', [
            'numericos' => $idsNumericos,
            'virtuales' => $idsVirtuales
        ]);
        
        // Obtener instancias existentes por IDs numéricos
        if (!empty($idsNumericos)) {
            $instanciasExistentes = CotioInstancia::with(['muestra'])
                ->whereIn('id', $idsNumericos)
                ->get();
            $instanciasSeleccionadas = $instanciasSeleccionadas->merge($instanciasExistentes);
            
            // Agregar al mapa de IDs
            foreach ($instanciasExistentes as $inst) {
                $mapaIdsVirtualesAReales[$inst->id] = $inst->id;
            }
        }
        
        // Crear instancias para IDs virtuales
        foreach ($idsVirtuales as $idVirtual) {
            $parts = explode('_', $idVirtual);
            if (count($parts) === 4) {
                [$numcoti, $item, $subitem, $instance] = $parts;
                
                // Obtener la tarea original de Cotio para copiar sus datos
                $tarea = Cotio::where([
                    'cotio_numcoti' => (int)$numcoti,
                    'cotio_item' => (int)$item,
                    'cotio_subitem' => (int)$subitem
                ])->first();
                
                // Buscar o crear la instancia
                $instancia = CotioInstancia::firstOrCreate(
                    [
                        'cotio_numcoti' => (int)$numcoti,
                        'cotio_item' => (int)$item,
                        'cotio_subitem' => (int)$subitem,
                        'instance_number' => (int)$instance
                    ],
                    [
                        'active_ot' => false,
                        'enable_ot' => false,
                        'responsable_muestreo' => Auth::user()->usu_codigo
                    ]
                );
                
                // Guardar el mapa de ID virtual a ID real
                $mapaIdsVirtualesAReales[$idVirtual] = $instancia->id;
                
                // Copiar datos desde Cotio si existen y la instancia no los tiene
                if ($tarea) {
                    $camposACopiar = false;
                    
                    if ($tarea->cotio_descripcion && !$instancia->cotio_descripcion) {
                        $instancia->cotio_descripcion = $tarea->cotio_descripcion;
                        $camposACopiar = true;
                    }
                    if ($tarea->cotio_precio && !$instancia->monto) {
                        $instancia->monto = $tarea->cotio_precio;
                        $camposACopiar = true;
                    }
                    if ($tarea->cotio_codigometodo && !$instancia->cotio_codigometodo) {
                        $instancia->cotio_codigometodo = $tarea->cotio_codigometodo;
                        $camposACopiar = true;
                    }
                    if ($tarea->cotio_codigometodo_analisis && !$instancia->cotio_codigometodo_analisis) {
                        $instancia->cotio_codigometodo_analisis = $tarea->cotio_codigometodo_analisis;
                        $camposACopiar = true;
                    }
                    if ($tarea->cotio_codigoum && !$instancia->cotio_codigoum) {
                        $instancia->cotio_codigoum = $tarea->cotio_codigoum;
                        $camposACopiar = true;
                    }
                    
                    if ($camposACopiar) {
                        $instancia->save();
                        Log::debug('Datos copiados desde Cotio a instancia', [
                            'instancia_id' => $instancia->id,
                            'descripcion' => $instancia->cotio_descripcion,
                            'monto' => $instancia->monto,
                            'metodo' => $instancia->cotio_codigometodo,
                            'metodo_analisis' => $instancia->cotio_codigometodo_analisis,
                            'codigoum' => $instancia->cotio_codigoum
                        ]);
                    }
                }
                
                $instancia->load('muestra');
                $instanciasSeleccionadas->push($instancia);
                
                Log::debug('Instancia creada desde ID virtual', [
                    'id_virtual' => $idVirtual,
                    'instancia_id' => $instancia->id,
                    'subitem' => $subitem
                ]);
            }
        }

        Log::debug('Instancias seleccionadas', [
            'count' => $instanciasSeleccionadas->count(),
            'ids' => $instanciasSeleccionadas->pluck('id')->toArray(),
            'mapa_ids' => $mapaIdsVirtualesAReales
        ]);

        // 3. Procesar TODAS las instancias seleccionadas directamente
        // Primero procesar las muestras (subitem=0), luego los análisis
        $muestrasActualizadas = collect();
        $analisisActualizados = collect();
        
        // Separar muestras de análisis (usar filter para asegurar comparación correcta)
        $muestrasSeleccionadas = $instanciasSeleccionadas->filter(function($inst) {
            return (int)$inst->cotio_subitem === 0;
        });
        $analisisSeleccionados = $instanciasSeleccionadas->filter(function($inst) {
            return (int)$inst->cotio_subitem > 0;
        });
        
        Log::debug('Separación de instancias', [
            'muestras_count' => $muestrasSeleccionadas->count(),
            'muestras' => $muestrasSeleccionadas->map(fn($m) => ['id' => $m->id, 'subitem' => $m->cotio_subitem])->toArray(),
            'analisis_count' => $analisisSeleccionados->count(),
            'analisis' => $analisisSeleccionados->map(fn($a) => ['id' => $a->id, 'subitem' => $a->cotio_subitem])->toArray()
        ]);
        
        // 4. Procesar muestras directamente seleccionadas
        foreach ($muestrasSeleccionadas as $muestra) {
            if (!$muestrasActualizadas->contains($muestra->id)) {
                // Copiar datos desde Cotio si no existen
                $tareaMuestra = Cotio::where([
                    'cotio_numcoti' => $muestra->cotio_numcoti,
                    'cotio_item' => $muestra->cotio_item,
                    'cotio_subitem' => 0
                ])->first();
                
                if ($tareaMuestra) {
                    if ($tareaMuestra->cotio_descripcion && !$muestra->cotio_descripcion) {
                        $muestra->cotio_descripcion = $tareaMuestra->cotio_descripcion;
                    }
                    if ($tareaMuestra->cotio_precio && !$muestra->monto) {
                        $muestra->monto = $tareaMuestra->cotio_precio;
                    }
                    if ($tareaMuestra->cotio_codigometodo && !$muestra->cotio_codigometodo) {
                        $muestra->cotio_codigometodo = $tareaMuestra->cotio_codigometodo;
                    }
                    if ($tareaMuestra->cotio_codigometodo_analisis && !$muestra->cotio_codigometodo_analisis) {
                        $muestra->cotio_codigometodo_analisis = $tareaMuestra->cotio_codigometodo_analisis;
                    }
                    if ($tareaMuestra->cotio_codigoum && !$muestra->cotio_codigoum) {
                        $muestra->cotio_codigoum = $tareaMuestra->cotio_codigoum;
                    }
                }
                
                $muestra->active_ot = true;
                $muestra->enable_ot = true;
                $muestra->cotio_estado_analisis = 'coordinado analisis';
                $muestra->coordinador_codigo_lab = Auth::user()->usu_codigo;
                
                // Generar OTN si no existe y no es canal especial
                if (!$muestra->otn) {
                    if ($muestra->debeLlevarOTN()) {
                        $muestra->otn = CotioInstancia::generarNumeroOT();
                    }
                }
                
                // Asignar fechas
                if (!empty($validated['fecha_inicio_ot'])) {
                    $muestra->fecha_inicio_ot = $validated['fecha_inicio_ot'];
                }
                if (!empty($validated['fecha_fin_ot'])) {
                    $muestra->fecha_fin_ot = $validated['fecha_fin_ot'];
                }
                
                $muestra->save();
                
                // Asignar responsables
                if (!empty($usuariosASincronizar)) {
                    $muestra->responsablesAnalisis()->sync($usuariosASincronizar);
                }
                
                // Asignar herramientas
                $this->asignarHerramientas($muestra, $herramientasLab);
                
                $muestrasActualizadas->push($muestra->id);
                $updatedCount++;
                
                Log::info('Muestra procesada', [
                    'muestra_id' => $muestra->id,
                    'otn' => $muestra->otn,
                    'cotio_numcoti' => $muestra->cotio_numcoti,
                    'cotio_item' => $muestra->cotio_item,
                    'instance_number' => $muestra->instance_number
                ]);
            }
        }
        
        // 5. Procesar análisis seleccionados
        foreach ($analisisSeleccionados as $analisis) {
            // Primero asegurar que la muestra padre exista y esté actualizada
            $muestra = CotioInstancia::firstOrCreate(
                [
                    'cotio_numcoti' => $analisis->cotio_numcoti,
                    'cotio_item' => $analisis->cotio_item,
                    'instance_number' => $analisis->instance_number,
                    'cotio_subitem' => 0
                ],
                [
                    'active_ot' => false,
                    'enable_ot' => false,
                    'responsable_muestreo' => Auth::user()->usu_codigo
                ]
            );
            
            // Actualizar la muestra si no ha sido actualizada
            if (!$muestrasActualizadas->contains($muestra->id)) {
                // Copiar datos desde Cotio si no existen
                $tareaMuestra = Cotio::where([
                    'cotio_numcoti' => $muestra->cotio_numcoti,
                    'cotio_item' => $muestra->cotio_item,
                    'cotio_subitem' => 0
                ])->first();
                
                if ($tareaMuestra) {
                    if ($tareaMuestra->cotio_descripcion && !$muestra->cotio_descripcion) {
                        $muestra->cotio_descripcion = $tareaMuestra->cotio_descripcion;
                    }
                    if ($tareaMuestra->cotio_precio && !$muestra->monto) {
                        $muestra->monto = $tareaMuestra->cotio_precio;
                    }
                    if ($tareaMuestra->cotio_codigometodo && !$muestra->cotio_codigometodo) {
                        $muestra->cotio_codigometodo = $tareaMuestra->cotio_codigometodo;
                    }
                    if ($tareaMuestra->cotio_codigometodo_analisis && !$muestra->cotio_codigometodo_analisis) {
                        $muestra->cotio_codigometodo_analisis = $tareaMuestra->cotio_codigometodo_analisis;
                    }
                    if ($tareaMuestra->cotio_codigoum && !$muestra->cotio_codigoum) {
                        $muestra->cotio_codigoum = $tareaMuestra->cotio_codigoum;
                    }
                }
                
                $muestra->active_ot = true;
                $muestra->enable_ot = true;
                $muestra->cotio_estado_analisis = 'coordinado analisis';
                $muestra->coordinador_codigo_lab = Auth::user()->usu_codigo;
                
                if (!$muestra->otn) {
                    if ($muestra->debeLlevarOTN()) {
                        $muestra->otn = CotioInstancia::generarNumeroOT();
                    }
                }
                
                if (!empty($validated['fecha_inicio_ot'])) {
                    $muestra->fecha_inicio_ot = $validated['fecha_inicio_ot'];
                }
                if (!empty($validated['fecha_fin_ot'])) {
                    $muestra->fecha_fin_ot = $validated['fecha_fin_ot'];
                }
                
                $muestra->save();

                // No copiar responsables ni herramientas del análisis a la muestra padre (evita pisar otros sectores)
                $muestrasActualizadas->push($muestra->id);
                $updatedCount++;
                
                Log::info('Muestra padre creada/actualizada para análisis', [
                    'muestra_id' => $muestra->id,
                    'otn' => $muestra->otn,
                    'descripcion' => $muestra->cotio_descripcion
                ]);
            }
            
            // Ahora actualizar el análisis - copiar datos desde Cotio si no existen
            if (!$analisisActualizados->contains($analisis->id)) {
                $tareaAnalisis = Cotio::where([
                    'cotio_numcoti' => $analisis->cotio_numcoti,
                    'cotio_item' => $analisis->cotio_item,
                    'cotio_subitem' => $analisis->cotio_subitem
                ])->first();
                
                if ($tareaAnalisis) {
                    if ($tareaAnalisis->cotio_descripcion && !$analisis->cotio_descripcion) {
                        $analisis->cotio_descripcion = $tareaAnalisis->cotio_descripcion;
                    }
                    if ($tareaAnalisis->cotio_precio && !$analisis->monto) {
                        $analisis->monto = $tareaAnalisis->cotio_precio;
                    }
                    if ($tareaAnalisis->cotio_codigometodo && !$analisis->cotio_codigometodo) {
                        $analisis->cotio_codigometodo = $tareaAnalisis->cotio_codigometodo;
                    }
                    if ($tareaAnalisis->cotio_codigometodo_analisis && !$analisis->cotio_codigometodo_analisis) {
                        $analisis->cotio_codigometodo_analisis = $tareaAnalisis->cotio_codigometodo_analisis;
                    }
                    if ($tareaAnalisis->cotio_codigoum && !$analisis->cotio_codigoum) {
                        $analisis->cotio_codigoum = $tareaAnalisis->cotio_codigoum;
                    }
                }
                
                $analisis->active_ot = true;
                $analisis->enable_ot = true;
                $analisis->cotio_estado_analisis = 'coordinado analisis';
                
                if (!empty($validated['fecha_inicio_ot'])) {
                    $analisis->fecha_inicio_ot = $validated['fecha_inicio_ot'];
                }
                if (!empty($validated['fecha_fin_ot'])) {
                    $analisis->fecha_fin_ot = $validated['fecha_fin_ot'];
                }
                
                $analisis->save();
                
                if (!empty($usuariosASincronizar)) {
                    $analisis->responsablesAnalisis()->sync($usuariosASincronizar);
                }
                
                $this->asignarHerramientas($analisis, $herramientasLab);
                $analisisActualizados->push($analisis->id);
                $updatedCount++;
                
                Log::info('Análisis procesado', [
                    'analisis_id' => $analisis->id,
                    'cotio_subitem' => $analisis->cotio_subitem,
                    'descripcion' => $analisis->cotio_descripcion
                ]);
            }
        }

        Log::info('Resumen de procesamiento', [
            'muestras_actualizadas' => $muestrasActualizadas->toArray(),
            'analisis_actualizados' => $analisisActualizados->toArray(),
            'total_count' => $updatedCount
        ]);
        
        // 6. Procesar gemelas si aplica
        if ($aplicarAGemelas) {
            foreach ($instanciasSeleccionadas as $instancia) {
                foreach ($instancia->gemelos() as $gemelo) {
                    if ($instancia->cotio_subitem == 0) {
                        // Muestra gemela
                        if (!$muestrasActualizadas->contains($gemelo->id)) {
                            $gemelo->active_ot = true;
                            $gemelo->enable_ot = true;
                            $gemelo->cotio_estado_analisis = 'coordinado analisis';
                            if (!$gemelo->otn) {
                                if ($gemelo->debeLlevarOTN()) {
                                    $gemelo->otn = CotioInstancia::generarNumeroOT();
                                }
                            }
                            if (!empty($validated['fecha_inicio_ot'])) {
                                $gemelo->fecha_inicio_ot = $validated['fecha_inicio_ot'];
                            }
                            if (!empty($validated['fecha_fin_ot'])) {
                                $gemelo->fecha_fin_ot = $validated['fecha_fin_ot'];
                            }
                            $gemelo->save();
                            if (!empty($usuariosASincronizar)) {
                                $gemelo->responsablesAnalisis()->sync($usuariosASincronizar);
                            }
                            $this->asignarHerramientas($gemelo, $herramientasLab);
                            $muestrasActualizadas->push($gemelo->id);
                            $updatedCount++;
                        }
                    } else {
                        // Análisis gemelo
                        if (!$analisisActualizados->contains($gemelo->id)) {
                            $gemelo->active_ot = true;
                            $gemelo->enable_ot = true;
                            $gemelo->cotio_estado_analisis = 'coordinado analisis';
                            if (!empty($validated['fecha_inicio_ot'])) {
                                $gemelo->fecha_inicio_ot = $validated['fecha_inicio_ot'];
                            }
                            if (!empty($validated['fecha_fin_ot'])) {
                                $gemelo->fecha_fin_ot = $validated['fecha_fin_ot'];
                            }
                            $gemelo->save();
                            if (!empty($usuariosASincronizar)) {
                                $gemelo->responsablesAnalisis()->sync($usuariosASincronizar);
                            }
                            $this->asignarHerramientas($gemelo, $herramientasLab);
                            $analisisActualizados->push($gemelo->id);
                            $updatedCount++;
                        }
                    }
                }
            }
        }

        DB::commit();

        Log::info('Asignación masiva completada', [
            'ordenId' => $ordenId,
            'updated_count' => $updatedCount,
            'usuarios_sincronizados' => $usuariosASincronizar,
            'instancias_procesadas' => $instanciasSeleccionadas->pluck('id')->toArray()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asignación masiva completada para ' . $updatedCount . ' instancias',
            'updated_count' => $updatedCount
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en asignación masiva', [
            'ordenId' => $ordenId,
            'user' => Auth::user()->usu_codigo ?? 'unknown',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error en asignación masiva: ' . $e->getMessage(),
            'error' => $e->getTraceAsString()
        ], 500);
    }
}

protected function procesarInstancia(
    CotioInstancia $instancia, 
    array $validated, 
    array $herramientasLab, 
    array $usuariosASincronizar,
    bool $esSeleccionada,
    array $mapaSelecciones
): int {
    $updatedCount = 0;

    if ($esSeleccionada || ($instancia->cotio_subitem == 0 && isset($mapaSelecciones[$instancia->id]))) {
        $this->actualizarInstancia($instancia, $validated);
        $this->asignarHerramientas($instancia, $herramientasLab);
        
        if (!empty($usuariosASincronizar)) {
            $instancia->responsablesAnalisis()->sync($usuariosASincronizar);
        }
        $updatedCount++;
    }

    return $updatedCount;
}


/**
 * Crea un mapa de muestras a análisis seleccionados
 */
protected function crearMapaSelecciones($instanciasSeleccionadas)
{
    $mapa = [];
    
    foreach ($instanciasSeleccionadas as $instancia) {
        if ($instancia->cotio_subitem == 0) { // Es una muestra
            $mapa[$instancia->id] = [
                'muestra' => $instancia,
                'analisis_ids' => $instancia->tareas->pluck('id')->toArray()
            ];
        } else { // Es un análisis
            $muestra = $instancia->muestra;
            if ($muestra) {
                if (!isset($mapa[$muestra->id])) {
                    $mapa[$muestra->id] = [
                        'muestra' => $muestra,
                        'analisis_ids' => []
                    ];
                }
                $mapa[$muestra->id]['analisis_ids'][] = $instancia->id;
            }
        }
    }
    
    return $mapa;
}

/**
 * Determina si una instancia fue seleccionada directamente
 */
protected function esInstanciaSeleccionada($instancia, $instanciaSelecciones, $tareaSelecciones)
{
    return in_array($instancia->id, $instanciaSelecciones) || 
           in_array($instancia->id, $tareaSelecciones);
}


/**
 * Procesa una instancia gemela
 */
protected function procesarInstanciaGemela(
    CotioInstancia $gemelo, 
    array $validated, 
    array $herramientasLab, 
    array $responsablesAnalisis,
    array $mapaSelecciones,
    CotioInstancia $instanciaOriginal
): int {
    $updatedCount = 0;

    // Si la original es una muestra con análisis seleccionados
    if ($instanciaOriginal->cotio_subitem == 0 && isset($mapaSelecciones[$instanciaOriginal->id])) {
        // Actualizar la muestra gemela
        $this->actualizarInstancia($gemelo, $validated);
        $this->asignarHerramientas($gemelo, $herramientasLab);
        
        if (!empty($responsablesAnalisis)) {
            $gemelo->responsablesAnalisis()->sync($responsablesAnalisis);
        }
        $updatedCount++;

        // Obtener los análisis gemelos correspondientes a los seleccionados en la original
        $analisisSeleccionadosOriginal = $mapaSelecciones[$instanciaOriginal->id]['analisis_ids'];
        $subitemsSeleccionados = CotioInstancia::whereIn('id', $analisisSeleccionadosOriginal)
            ->pluck('cotio_subitem')
            ->unique()
            ->toArray();

        // Actualizar solo los análisis gemelos con los mismos subitems que los seleccionados
        foreach ($gemelo->tareas as $analisisGemelo) {
            if (in_array($analisisGemelo->cotio_subitem, $subitemsSeleccionados)) {
                $this->actualizarInstancia($analisisGemelo, $validated);
                $this->asignarHerramientas($analisisGemelo, $herramientasLab);
                
                if (!empty($responsablesAnalisis)) {
                    $analisisGemelo->responsablesAnalisis()->sync($responsablesAnalisis);
                }
                $updatedCount++;
            }
        }
    }
    // Si la original es un análisis seleccionado directamente
    elseif ($instanciaOriginal->cotio_subitem > 0) {
        // Actualizar solo el análisis gemelo correspondiente
        $this->actualizarInstancia($gemelo, $validated);
        $this->asignarHerramientas($gemelo, $herramientasLab);
        
        if (!empty($responsablesAnalisis)) {
            $gemelo->responsablesAnalisis()->sync($responsablesAnalisis);
        }
        $updatedCount++;
    }

    return $updatedCount;
}


protected function actualizarInstancia(CotioInstancia $instancia, array $validated)
{
    $instancia->active_ot = true;
    $instancia->cotio_estado_analisis = 'coordinado analisis';

    if (isset($validated['responsable_codigo'])) {
        $instancia->responsable_analisis = $validated['responsable_codigo'] === 'NULL' ? null : $validated['responsable_codigo'];
    }

    if (!empty($validated['fecha_inicio_ot'])) {
        $instancia->fecha_inicio_ot = $validated['fecha_inicio_ot'];
    }

    if (!empty($validated['fecha_fin_ot'])) {
        $instancia->fecha_fin_ot = $validated['fecha_fin_ot'];
    }

    $instancia->save();
}

/**
 * Resuelve códigos de usuario (con espacios legacy en BD) a partir de valores trimmeados.
 *
 * @param  array<int, string>  $codigos
 * @return array<int, string>
 */
protected function resolverCodigosUsuExactos(array $codigos): array
{
    $codigos = array_values(array_unique(array_filter(array_map('trim', $codigos))));

    if ($codigos === []) {
        return [];
    }

    return User::query()
        ->where(function ($q) use ($codigos) {
            foreach ($codigos as $codigo) {
                $q->orWhereRaw('TRIM(usu_codigo) = ?', [$codigo]);
            }
        })
        ->pluck('usu_codigo')
        ->values()
        ->all();
}

protected function asignarHerramientas(CotioInstancia $instancia, array $herramientasLab)
{
    if (!empty($herramientasLab)) {
        $syncData = [];
        foreach ($herramientasLab as $herramientaId) {
            $exists = DB::table('cotio_inventario_lab')
                ->where([
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $instancia->cotio_subitem,
                    'instance_number' => $instancia->instance_number,
                    'inventario_lab_id' => $herramientaId,
                ])
                ->exists();

            if (!$exists) {
                $syncData[$herramientaId] = [
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $instancia->cotio_subitem,
                    'instance_number' => $instancia->instance_number,
                    'cantidad' => 1,
                    'observaciones' => null,
                ];
            }
        }
        if (!empty($syncData)) {
            $instancia->herramientasLab()->syncWithoutDetaching($syncData);
            Log::debug('Asignando herramientas', [
                'instancia_id' => $instancia->id,
                'cotio_numcoti' => $instancia->cotio_numcoti,
                'cotio_item' => $instancia->cotio_item,
                'cotio_subitem' => $instancia->cotio_subitem,
                'instance_number' => $instancia->instance_number,
                'herramientas' => array_keys($syncData)
            ]);
        }
    } else {
        $instancia->herramientasLab()->detach();
        Log::debug('Eliminando herramientas', [
            'instancia_id' => $instancia->id,
            'cotio_numcoti' => $instancia->cotio_numcoti,
            'cotio_item' => $instancia->cotio_item,
            'cotio_subitem' => $instancia->cotio_subitem,
            'instance_number' => $instancia->instance_number
        ]);
    }
}

protected function resolveInstancia($key)
{
    // If key is a single ID
    if (is_numeric($key)) {
        return CotioInstancia::find($key);
    }

    // If key is composite (numcoti_item_subitem_instance)
    $parts = explode('_', $key);
    if (count($parts) === 4) {
        [$numcoti, $item, $subitem, $instance] = $parts;
        return CotioInstancia::where([
            'cotio_numcoti' => $numcoti,
            'cotio_item' => $item,
            'cotio_subitem' => $subitem,
            'instance_number' => $instance,
            'enable_ot' => true
        ])->first();
    }

    return null;
}

protected function getInstanciasGemelas(CotioInstancia $instancia)
{
    return CotioInstancia::where([
        'cotio_numcoti' => $instancia->cotio_numcoti,
        'cotio_item' => $instancia->cotio_item,
        'cotio_subitem' => $instancia->cotio_subitem,
        'enable_ot' => true
    ])
    ->where('instance_number', '!=', $instancia->instance_number)
    ->get();
}




public function removerResponsable(Request $request, $ordenId)
{
    $this->authorizeGestionOrdenes();

    $validated = $request->validate([
        'instancia_id' => 'required|integer|exists:cotio_instancias,id',
        'user_codigo' => 'required|string|exists:usu,usu_codigo',
        'todos' => 'required|string|in:true,false' // Validar como string primero
    ]);

    // Convertir el string 'true'/'false' a booleano
    $todos = $validated['todos'] === 'true';

    try {
        DB::beginTransaction();

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);
        $userCodigo = $validated['user_codigo'];

        if ($todos) {
            // Encontrar todas las instancias (muestra y tareas) con mismo cotio_numcoti, cotio_item, instance_number
            $instancias = CotioInstancia::where([
                'cotio_numcoti' => $instancia->cotio_numcoti,
                'cotio_item' => $instancia->cotio_item,
                'instance_number' => $instancia->instance_number,
            ])->get();


            $totalEliminados = 0;
            // Eliminar usuario de instancia_responsable_analisis para todas las instancias coincidentes
            foreach ($instancias as $inst) {
                // Verificar qué responsables están asignados a esta instancia
                $responsablesActuales = DB::table('instancia_responsable_analisis')
                    ->where('cotio_instancia_id', $inst->id)
                    ->get();
                
                // Eliminar de análisis
                $deletedAnalisis = DB::table('instancia_responsable_analisis')
                    ->where('cotio_instancia_id', $inst->id)
                    ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                    ->delete();
                
                // También eliminar de muestreo por si está ahí
                $deletedMuestreo = DB::table('instancia_responsable_muestreo')
                    ->where('cotio_instancia_id', $inst->id)
                    ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                    ->delete();
                
                $totalEliminados += $deletedAnalisis + $deletedMuestreo;
            }

        } else {
            // Eliminar usuario solo de la instancia especificada
            $deletedAnalisis = DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $validated['instancia_id'])
                ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                ->delete();

            $deletedMuestreo = DB::table('instancia_responsable_muestreo')
                ->where('cotio_instancia_id', $validated['instancia_id'])
                ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                ->delete();
        }

        DB::commit();
        return response()->json([
            'success' => true,
            'message' => 'Responsable eliminado correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al eliminar responsable: ' . $e->getMessage()
        ], 500);
    }
}



public function enableInforme(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required|integer|exists:cotio_instancias,cotio_numcoti',
        'cotio_item' => 'required|integer',
        'cotio_subitem' => 'required|integer',
        'instance' => 'required|integer',
    ]);

    try {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $request->cotio_numcoti,
            'cotio_item' => $request->cotio_item,
            'cotio_subitem' => $request->cotio_subitem,
            'instance_number' => $request->instance,
        ])->firstOrFail();

        if ($instancia->cotio_estado_analisis !== 'analizado') {
            return redirect()->back()->with('error', 'La instancia no está en estado analizado.');
        }

        if (! AnalisisResultadoValidacion::muestraPuedeMarcarseAnalizada($instancia)) {
            return redirect()->back()->with('error', AnalisisResultadoValidacion::mensajeMuestraNoAnalizable($instancia));
        }

        DB::beginTransaction();
        $instancia->enable_inform = true;
        $instancia->fecha_creacion_inform = now();
        $instancia->save();
        DB::commit();

        return redirect()->back()->with('success', 'Informe habilitado exitosamente.');
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->back()->with('error', 'Error al habilitar el informe: ' . $e->getMessage());
    }
}

public function disableInforme(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required|integer|exists:cotio_instancias,cotio_numcoti',
        'cotio_item' => 'required|integer',
        'cotio_subitem' => 'required|integer',
        'instance' => 'required|integer',
    ]);

    try {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $request->cotio_numcoti,
            'cotio_item' => $request->cotio_item,
            'cotio_subitem' => $request->cotio_subitem, 
            'instance_number' => $request->instance,
        ])->firstOrFail();

        DB::beginTransaction();
        $instancia->enable_inform = false;
        $instancia->fecha_creacion_inform = null;
        $instancia->save();
        DB::commit();

        return redirect()->back()->with('success', 'Informe deshabilitado exitosamente.');
    } catch (\Exception $e) {
        DB::rollBack();
        return redirect()->back()->with('error', 'Error al deshabilitar el informe: ' . $e->getMessage());
    }
}

/**
 * Ver informe preliminar de una instancia
 */
public function verInformePreliminar($instancia_id)
{
    try {
        $instancia = CotioInstancia::with([
            'cotizacion.matriz',
            'muestra.leyNormativa.variables',
            'valoresVariables' => function($query) {
                $query->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad',
                    'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo',
        ])->findOrFail($instancia_id);

        // Obtener todos los análisis de esta muestra (cotio_subitem > 0), con línea Cotio y ley
        $analisis = CotioInstancia::query()
            ->with(['tarea.leyNormativa'])
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('instance_number', $instancia->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_subitem')
            ->get();

        foreach ($analisis as $analisisItem) {
            $cotio = $analisisItem->tarea;
            $analisisItem->cotio_descripcion = $cotio ? $cotio->cotio_descripcion : 'Análisis #' . $analisisItem->cotio_subitem;
        }

        if (!$instancia->enable_inform) {
            return response()->json([
                'success' => false,
                'message' => 'Esta instancia no tiene el informe habilitado.'
            ], 400);
        }

        // Generar el contenido HTML del informe
        $contenidoInforme = $this->generarContenidoInforme($instancia, $analisis);

        return response()->json([
            'success' => true,
            'informe' => $contenidoInforme
        ]);

    } catch (\Exception $e) {
        Log::error('Error al generar informe preliminar: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al generar el informe preliminar: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Aprobar informe de una instancia
 */
public function aprobarInforme($instancia_id)
{
    try {
        $instancia = CotioInstancia::findOrFail($instancia_id);

        if (!$instancia->enable_inform) {
            return response()->json([
                'success' => false,
                'message' => 'Esta instancia no tiene el informe habilitado.'
            ], 400);
        }

        if ($instancia->aprobado_informe) {
            return response()->json([
                'success' => false,
                'message' => 'Este informe ya está aprobado.'
            ], 400);
        }

        if ((int) $instancia->cotio_subitem === 0 && ! AnalisisResultadoValidacion::muestraPuedeMarcarseAnalizada($instancia)) {
            return response()->json([
                'success' => false,
                'message' => AnalisisResultadoValidacion::mensajeMuestraNoAnalizable($instancia),
            ], 422);
        }

        DB::beginTransaction();

        $instancia->aprobado_informe = true;
        $instancia->fecha_aprobacion_informe = now();
        $instancia->aprobado_informe_usuario = Auth::user()->usu_codigo;
        $instancia->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Informe aprobado correctamente.'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al aprobar informe: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al aprobar el informe: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Texto HTML (escapado) de legislación / normativa según fila Cotio y relación leyes_normativas.
     */
    private function textoLeyNormativaParaInforme(?Cotio $lineaCotio): string
    {
        return LeyNormativaPresentacion::fragmentoHtml($lineaCotio);
    }

    /**
     * Texto de «fecha de análisis» alineado al informe PDF: fechas manuales del ensayo o respaldo por carga de resultado.
     */
    private function textoFechaAnalisisParaInforme(CotioInstancia $tarea): string
    {
        return FechaAnalisisInformePdf::textoDescriptivoOt($tarea);
    }

    /**
     * Generar contenido HTML del informe
     */
        private function generarContenidoInforme($instancia, $analisis)
    {
        $cotizacion = $instancia->cotizacion;
        $cotizacion->loadMissing(['cliente', 'sucursal']);
        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas([$cotizacion]);
        $matriz = $cotizacion->matriz;

        $codigosCargaResultados = collect();
        if ($analisis && $analisis->count() > 0) {
            foreach ($analisis as $t) {
                foreach ([
                    $t->responsable_resultado_1,
                    $t->responsable_resultado_2,
                    $t->responsable_resultado_3,
                    $t->responsable_resultado_final,
                ] as $c) {
                    if ($c !== null && trim((string) $c) !== '') {
                        $codigosCargaResultados->push(trim((string) $c));
                    }
                }
            }
        }
        $usuariosPorCodigoCarga = collect();
        if ($codigosCargaResultados->isNotEmpty()) {
            $usuariosPorCodigoCarga = User::whereIn('usu_codigo', $codigosCargaResultados->unique()->values()->all())
                ->get()
                ->keyBy(fn ($u) => trim((string) $u->usu_codigo));
        }
        $nombreUsuarioCarga = function (?string $codigo) use ($usuariosPorCodigoCarga): string {
            if ($codigo === null || trim((string) $codigo) === '') {
                return '—';
            }
            $k = trim((string) $codigo);
            $u = $usuariosPorCodigoCarga->get($k);

            return $u ? htmlspecialchars((string) ($u->usu_descripcion ?? $k)) : htmlspecialchars($k);
        };

        $html = '<div class="informe-preliminar">';
        
        // Encabezado del informe
        $html .= '<div class="mb-4">';
        $html .= '<h4 class="text-primary mb-3">📋 Informe de Análisis</h4>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<p><strong>Cotización:</strong> ' . $cotizacion->coti_num . '</p>';
        $html .= '<p><strong>Empresa:</strong> ' . e(CotizacionClienteEtiqueta::paraLista($cotizacion)) . '</p>';
        $html .= '<p><strong>Establecimiento:</strong> ' . e(CotizacionClienteEtiqueta::etiquetaSucursalEstablecimiento($cotizacion) ?: 'N/A') . '</p>';
        $html .= '</div>';
        $html .= '<div class="col-md-6">';
        $html .= '<p><strong>Matriz:</strong> ' . ($matriz ? $matriz->matriz_descripcion : 'N/A') . '</p>';
        $fechaMuestreoFormateada = 'N/A';
        if ($instancia->fecha_muestreo) {
            try {
                $fechaMuestreo = is_string($instancia->fecha_muestreo) ? \Carbon\Carbon::parse($instancia->fecha_muestreo) : $instancia->fecha_muestreo;
                $fechaMuestreoFormateada = $fechaMuestreo->format('d/m/Y');
            } catch (\Exception $e) {
                $fechaMuestreoFormateada = 'Fecha inválida';
            }
        }
        $html .= '<p><strong>Fecha de Muestreo:</strong> ' . $fechaMuestreoFormateada . '</p>';
        $otnTxt = ($instancia->otn !== null && trim((string) $instancia->otn) !== '')
            ? '#' . htmlspecialchars(trim((string) $instancia->otn))
            : 'N/A';
        $html .= '<p><strong>O.T.N:</strong> ' . $otnTxt . '</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div class="mb-4 border rounded p-3 bg-light">';
        $html .= '<h5 class="text-secondary mb-3">🏷️ Muestra</h5>';
        $html .= '<p class="mb-2"><strong>Identificación de muestra:</strong> ';
        $html .= htmlspecialchars((string) (trim((string) ($instancia->cotio_identificacion ?? '')) !== ''
            ? $instancia->cotio_identificacion
            : '—'));
        $html .= '</p>';
        $lineaMuestra = $instancia->relationLoaded('muestra') ? $instancia->muestra : null;
        if ($lineaMuestra === null && $instancia->cotio_numcoti !== null) {
            $lineaMuestra = Cotio::query()
                ->where('cotio_numcoti', $instancia->cotio_numcoti)
                ->where('cotio_item', $instancia->cotio_item)
                ->where('cotio_subitem', 0)
                ->with('leyNormativa.variables')
                ->first();
        }
        $html .= '<p class="mb-0"><strong>Legislación / normativa (categoría):</strong> ';
        $html .= $this->textoLeyNormativaParaInforme($lineaMuestra);
        $html .= '</p>';
        $html .= '</div>';

        // Variables de medición
        if ($instancia->valoresVariables && $instancia->valoresVariables->count() > 0) {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="text-secondary mb-3">🔬 Variables de Medición</h5>';
            $html .= '<table class="table table-bordered table-sm">';
            $html .= '<thead class="table-light"><tr><th>Variable</th><th>Valor</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($instancia->valoresVariables as $variable) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($variable->variable) . '</td>';
                $html .= '<td>' . htmlspecialchars($variable->valor) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        // Lógica para mapear límites de la ley de la muestra
        $leyNormativaMuestra = $lineaMuestra ? $lineaMuestra->leyNormativa : null;
        $variablesLey = $leyNormativaMuestra ? $leyNormativaMuestra->variables : collect();
        $mapVariablePorDescripcion = collect();
        
        if ($variablesLey->isNotEmpty()) {
            $catalogIdsEnLey = $variablesLey->pluck('cotio_item_id')->filter()->unique()->toArray();
            $itemsCatalogo = \App\Models\CotioItems::whereIn('id', $catalogIdsEnLey)->get();
            foreach ($itemsCatalogo as $itemCat) {
                $variable = $variablesLey->firstWhere('cotio_item_id', $itemCat->id);
                if ($variable) {
                    $mapVariablePorDescripcion[trim(strtolower((string)$itemCat->cotio_descripcion))] = $variable;
                }
            }
        }

        // Análisis de la muestra
        if ($analisis && $analisis->count() > 0) {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="text-secondary mb-3">📊 Análisis de la Muestra</h5>';
            
            foreach ($analisis as $tarea) {
                // Determinar el estado del análisis
                $estadoAnalisis = $tarea->cotio_estado_analisis ?? 'pendiente';
                $badgeClass = match (strtolower($estadoAnalisis)) {
                    'analizado' => 'success',
                    'en proceso' => 'info',
                    'coordinado analisis' => 'warning',
                    'en revision analisis' => 'info',
                    'suspension' => 'danger',
                    default => 'secondary'
                };
                
                $html .= '<div class="card mb-3 border-start border-4 border-' . $badgeClass . '">';
                $html .= '<div class="card-header bg-light d-flex justify-content-between align-items-center">';
                $html .= '<div>';
                $html .= '<h6 class="mb-0">' . htmlspecialchars($tarea->cotio_descripcion) . '</h6>';
                $html .= '<small class="text-muted">Análisis #' . $tarea->cotio_subitem . '</small>';
                $html .= '</div>';
                $html .= '<span class="badge bg-' . $badgeClass . '">' . ucfirst($estadoAnalisis) . '</span>';
                $html .= '</div>';
                $html .= '<div class="card-body">';
                $lineaEnsayo = $tarea->tarea;
                $html .= '<p class="small text-muted mb-3 border-bottom pb-2">';
                $html .= '<strong>Legislación / normativa (ensayo):</strong> ';
                $html .= $this->textoLeyNormativaParaInforme($lineaEnsayo);
                $html .= '</p>';

                // Mostrar valor límite si existe en la ley de la muestra
                $variableLey = $variablesLey->first(function($v) use ($tarea) {
                    $itemProdCode = trim((string)($tarea->tarea->cotio_codigoprod ?? ''));
                    $varCatalogId = trim((string)($v->cotio_item_id ?? ''));
                    return $itemProdCode !== '' && (int)$itemProdCode === (int)$varCatalogId;
                });

                if (!$variableLey) {
                    $desc = trim(strtolower((string)($tarea->cotio_descripcion ?? '')));
                    $variableLey = $mapVariablePorDescripcion->get($desc);
                }

                if ($variableLey) {
                    $html .= '<div class="alert alert-info py-1 px-2 mb-3 d-inline-block">';
                    $html .= '<small><strong>📍 Valor Límite (Referencia Ley):</strong> ' . 
                             htmlspecialchars((string)($variableLey->pivot->valor_limite ?? 'N/A')) . ' ' . 
                             htmlspecialchars((string)($variableLey->pivot->unidad_medida ?? '')) . '</small>';
                    $html .= '</div>';
                }

                // Información del análisis
                $html .= '<div class="row mb-3">';
                $html .= '<div class="col-12">';
                $html .= '<small><strong>Fecha de análisis (informe):</strong> ' . htmlspecialchars($this->textoFechaAnalisisParaInforme($tarea)) . '</small><br>';
                $html .= '<small class="text-muted"><strong>Fechas OT / carga:</strong></small><br>';
                if ($tarea->fecha_inicio_ot) {
                    try {
                        $fechaInicio = is_string($tarea->fecha_inicio_ot) ? \Carbon\Carbon::parse($tarea->fecha_inicio_ot) : $tarea->fecha_inicio_ot;
                        $html .= '<small>📅 <strong>Inicio:</strong> ' . $fechaInicio->format('d/m/Y H:i') . '</small><br>';
                    } catch (\Exception $e) {
                        $html .= '<small>📅 <strong>Inicio:</strong> Fecha inválida</small><br>';
                    }
                }
                if ($tarea->fecha_fin_ot) {
                    try {
                        $fechaFin = is_string($tarea->fecha_fin_ot) ? \Carbon\Carbon::parse($tarea->fecha_fin_ot) : $tarea->fecha_fin_ot;
                        $html .= '<small>🏁 <strong>Fin:</strong> ' . $fechaFin->format('d/m/Y H:i') . '</small><br>';
                    } catch (\Exception $e) {
                        $html .= '<small>🏁 <strong>Fin:</strong> Fecha inválida</small><br>';
                    }
                }
                if ($tarea->fecha_carga_ot) {
                    try {
                        $fechaCarga = is_string($tarea->fecha_carga_ot) ? \Carbon\Carbon::parse($tarea->fecha_carga_ot) : $tarea->fecha_carga_ot;
                        $html .= '<small>💾 <strong>Carga:</strong> ' . $fechaCarga->format('d/m/Y H:i') . '</small>';
                    } catch (\Exception $e) {
                        $html .= '<small>💾 <strong>Carga:</strong> Fecha inválida</small>';
                    }
                }
                $html .= '</div>';
                $html .= '</div>';
                
                // Observaciones del coordinador
                if ($tarea->observaciones_ot) {
                    $html .= '<div class="alert alert-info py-2 mb-3">';
                    $html .= '<small><strong>💬 Observaciones del Coordinador:</strong><br>';
                    $html .= htmlspecialchars($tarea->observaciones_ot) . '</small>';
                    $html .= '</div>';
                }
                
                // Tabla de resultados (responsable = quien cargó cada resultado)
                $resultados = [
                    ['tipo' => '🔬 Resultado Primario', 'valor' => $tarea->resultado, 'obs' => $tarea->observacion_resultado, 'fecha' => $tarea->fecha_carga_resultado_1, 'responsable' => $tarea->responsable_resultado_1],
                    ['tipo' => '🔬 Resultado Secundario', 'valor' => $tarea->resultado_2, 'obs' => $tarea->observacion_resultado_2, 'fecha' => $tarea->fecha_carga_resultado_2, 'responsable' => $tarea->responsable_resultado_2],
                    ['tipo' => '🔬 Resultado Terciario', 'valor' => $tarea->resultado_3, 'obs' => $tarea->observacion_resultado_3, 'fecha' => $tarea->fecha_carga_resultado_3, 'responsable' => $tarea->responsable_resultado_3],
                    ['tipo' => '🏆 Resultado Final', 'valor' => $tarea->resultado_final, 'obs' => $tarea->observacion_resultado_final, 'fecha' => $tarea->fecha_carga_ot, 'responsable' => $tarea->responsable_resultado_final],
                ];
                
                $tieneResultados = false;
                foreach ($resultados as $resultado) {
                    if (!empty($resultado['valor'])) {
                        $tieneResultados = true;
                        break;
                    }
                }
                
                if ($tieneResultados) {
                    $html .= '<div class="table-responsive">';
                    $html .= '<table class="table table-sm table-striped mb-0">';
                    $html .= '<thead class="table-dark"><tr><th>Tipo de Resultado</th><th>Valor</th><th>Observaciones</th><th>Cargado por</th><th>Fecha de Carga</th></tr></thead>';
                    $html .= '<tbody>';
                    
                    foreach ($resultados as $resultado) {
                        if (!empty($resultado['valor'])) {
                            $html .= '<tr>';
                            $html .= '<td><strong>' . $resultado['tipo'] . '</strong></td>';
                            $html .= '<td class="fw-bold text-primary">' . htmlspecialchars($resultado['valor']) . '</td>';
                            $html .= '<td>' . htmlspecialchars($resultado['obs'] ?? 'Sin observaciones') . '</td>';
                            $html .= '<td><small>' . $nombreUsuarioCarga($resultado['responsable'] ?? null) . '</small></td>';
                            $fechaFormateada = 'N/A';
                            if ($resultado['fecha']) {
                                try {
                                    $fecha = is_string($resultado['fecha']) ? \Carbon\Carbon::parse($resultado['fecha']) : $resultado['fecha'];
                                    $fechaFormateada = $fecha->format('d/m/Y H:i');
                                } catch (\Exception $e) {
                                    $fechaFormateada = 'Fecha inválida';
                                }
                            }
                            $html .= '<td><small>' . $fechaFormateada . '</small></td>';
                            $html .= '</tr>';
                        }
                    }
                    
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="alert alert-warning py-2">';
                    $html .= '<small><strong>⚠️ Sin resultados:</strong> Este análisis aún no tiene resultados cargados.</small>';
                    $html .= '</div>';
                }
                
                // Información adicional si está habilitado para informe
                if ($tarea->enable_inform) {
                    $html .= '<div class="mt-2">';
                    $html .= '<span class="badge bg-success"><i class="fas fa-check"></i> Habilitado para informe</span>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="mt-2">';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="text-secondary mb-3">📊 Análisis de la Muestra</h5>';
            $html .= '<div class="alert alert-info">';
            $html .= '<strong>ℹ️ Sin análisis:</strong> Esta muestra no tiene análisis asociados.';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Herramientas utilizadas
        if ($instancia->herramientasLab && $instancia->herramientasLab->count() > 0) {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="text-secondary mb-3">🔧 Herramientas Utilizadas</h5>';
            $html .= '<ul class="list-group">';
            foreach ($instancia->herramientasLab as $herramienta) {
                $html .= '<li class="list-group-item d-flex justify-content-between align-items-center">';
                $html .= '<span>' . htmlspecialchars($herramienta->equipamiento);
                if ($herramienta->marca_modelo) {
                    $html .= ' <small class="text-muted">(' . htmlspecialchars($herramienta->marca_modelo) . ')</small>';
                }
                $html .= '</span>';
                if (isset($herramienta->cantidad) && $herramienta->cantidad > 1) {
                    $html .= '<span class="badge bg-primary rounded-pill">' . $herramienta->cantidad . '</span>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Observaciones
        if ($instancia->observaciones_medicion_coord_muestreo || $instancia->observaciones_medicion_muestreador) {
            $html .= '<div class="mb-4">';
            $html .= '<h5 class="text-secondary mb-3">💬 Observaciones</h5>';
            
            if ($instancia->observaciones_medicion_coord_muestreo) {
                $html .= '<div class="alert alert-info">';
                $html .= '<strong>Coordinador de Muestreo:</strong><br>';
                $html .= htmlspecialchars($instancia->observaciones_medicion_coord_muestreo);
                $html .= '</div>';
            }
            
            if ($instancia->observaciones_medicion_muestreador) {
                $html .= '<div class="alert alert-warning">';
                $html .= '<strong>Muestreador:</strong><br>';
                $html .= htmlspecialchars($instancia->observaciones_medicion_muestreador);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Pie del informe
        $html .= '<div class="mt-4 pt-3 border-top">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-6">';
        $html .= '<small class="text-muted">';
        $html .= '<strong>Fecha de generación:</strong> ' . now()->format('d/m/Y H:i');
        $html .= '</small>';
        $html .= '</div>';
        $html .= '<div class="col-md-6 text-end">';
        $html .= '<small class="text-muted">';
        $html .= '<strong>Estado:</strong> ' . ucfirst($instancia->cotio_estado_analisis);
        $html .= '</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

public function finalizarTodas(Request $request)
{

    try {
        $request->validate([
            'cotio_numcoti' => 'required',
            'cotio_item' => 'required',
            'cotio_subitem' => 'required',
            'instance_number' => 'required',
        ]);

        $params = [
            'cotio_numcoti' => $request->cotio_numcoti,
            'cotio_item' => $request->cotio_item,
            'instance_number' => $request->instance_number,
            'active_ot' => true
        ];

            $pendientes = CotioInstancia::where($params)
                ->where(function ($query) use ($request) {
                    $query->where('cotio_subitem', $request->cotio_subitem)
                          ->orWhere('cotio_subitem', '>', 0);
                })
                ->where(function ($q) {
                    $q->whereNull('cotio_estado_analisis')
                      ->orWhere('cotio_estado_analisis', '!=', 'analizado');
                })
                ->get();

            if ($pendientes->isEmpty()) {
                return redirect()->back()->with('info', 'No hay muestras o análisis activos para finalizar.');
            }

            $omitidos = collect();
            $updatedCount = 0;

            foreach ($pendientes as $instancia) {
                if (! AnalisisResultadoValidacion::analisisPuedeMarcarseAnalizado($instancia)) {
                    $omitidos->push($instancia);
                    continue;
                }

                $instancia->update(['cotio_estado_analisis' => 'analizado']);
                $updatedCount++;
            }

            if ($updatedCount === 0) {
                return redirect()->back()->with(
                    'error',
                    AnalisisResultadoValidacion::mensajeVariasSinResultado($omitidos->unique('id'))
                );
            }

            AnalisisResultadoValidacion::sincronizarEstadoMuestraDesdeAnalisis(
                $request->cotio_numcoti,
                $request->cotio_item,
                $request->instance_number
            );

            $mensaje = 'Se finalizaron correctamente ' . $updatedCount . ' registros.';
            if ($omitidos->isNotEmpty()) {
                $mensaje .= ' ' . AnalisisResultadoValidacion::mensajeVariasSinResultado($omitidos->unique('id'));
            }

            return redirect()->back()->with('success', $mensaje);

    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Error al finalizar muestras y análisis: ' . $e->getMessage());
    }
}





public function actualizarEstado(Request $request)
{
    $validated = $request->validate([
        'cotio_numcoti' => 'required|numeric',
        'cotio_item' => 'required|numeric',
        'cotio_subitem' => 'required|numeric',
        'instance_number' => 'required|numeric',
        'estado' => 'required|in:coordinado analisis,en revision analisis,analizado,suspension,coordinado muestreo,en revision muestreo,muestreado',
        'fecha_carga_ot' => 'nullable|date',
        'observaciones_ot' => 'nullable|string|max:1000',
    ]);

    try {
        DB::beginTransaction();

        $item = CotioInstancia::where([
            'cotio_numcoti' => $validated['cotio_numcoti'],
            'cotio_item' => $validated['cotio_item'],
            'cotio_subitem' => $validated['cotio_subitem'],
            'instance_number' => $validated['instance_number']
        ])->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Elemento no encontrado'
            ], 404);
        }

        if ((int) $validated['cotio_subitem'] > 0) {
            $this->authorizeAccesoInstanciaAnalisis($item);
        } elseif (OrdenesAccesoPorSector::debeFiltrarPorSector(Auth::user()) && ! Auth::user()->puedeGestionarOrdenes()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para modificar el estado de la muestra.',
            ], 403);
        }

        $vehiculoAsignado = $item->vehiculo_asignado;

        if ($validated['estado'] === 'analizado' && ! AnalisisResultadoValidacion::analisisPuedeMarcarseAnalizado($item)) {
            return response()->json([
                'success' => false,
                'message' => AnalisisResultadoValidacion::mensajeNoAnalizable($item),
            ], 422);
        }

        if(Auth::user()->hasRole('coordinador_lab') || Auth::user()->usu_nivel >= '900') {
            $item->cotio_estado_analisis = $validated['estado'];
            
            // Actualizar la fecha de carga OT si se proporcionó
            if (isset($validated['fecha_carga_ot']) && $validated['fecha_carga_ot']) {
                $item->fecha_carga_ot = $validated['fecha_carga_ot'];
            } elseif ($validated['estado'] === 'analizado' && !$item->fecha_carga_ot) {
                // Si el estado es 'analizado' y no hay fecha de carga, establecer la fecha actual
                $item->fecha_carga_ot = now();
            }
            
            // Actualizar las observaciones del coordinador si se proporcionaron
            if (isset($validated['observaciones_ot'])) {
                $item->observaciones_ot = $validated['observaciones_ot'];
            }
        } 

        if ($validated['estado'] === 'finalizado') {
            if (empty($item->fecha_fin)) {
                $item->fecha_fin = now();
            }
            
            if ($vehiculoAsignado) {
                $item->vehiculo_asignado = null;
                
                Vehiculo::where('id', $vehiculoAsignado)
                    ->update(['estado' => 'libre']);
            }

            $herramientasAsignadas = DB::table('cotio_inventario_muestreo')
                ->where('cotio_numcoti', $validated['cotio_numcoti'])
                ->where('cotio_item', $validated['cotio_item'])
                ->where('cotio_subitem', $validated['cotio_subitem'])
                ->where('instance_number', $validated['instance_number'])
                ->pluck('inventario_muestreo_id');

            if ($herramientasAsignadas->isNotEmpty()) {
                DB::table('cotio_inventario_muestreo')
                    ->where('cotio_numcoti', $validated['cotio_numcoti'])
                    ->where('cotio_item', $validated['cotio_item'])
                    ->where('cotio_subitem', $validated['cotio_subitem'])
                    ->where('instance_number', $validated['instance_number'])
                    ->delete();

                InventarioLab::whereIn('id', $herramientasAsignadas)
                    ->update(['estado' => 'libre']);
            }
        }

        $item->save();

        if ((int) $validated['cotio_subitem'] > 0 && $validated['estado'] === 'analizado') {
            AnalisisResultadoValidacion::sincronizarEstadoMuestraDesdeAnalisis(
                $validated['cotio_numcoti'],
                $validated['cotio_item'],
                $validated['instance_number']
            );
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Análisis actualizado correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en actualizarEstado: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ], 500);
    }
}




public function apiHerramientasInstancia($instanciaId)
{
    Log::info("Obteniendo herramientas para la instancia ID: {$instanciaId}");

    try {
        $instancia = \App\Models\CotioInstancia::findOrFail($instanciaId);
        Log::info("Instancia encontrada: ID {$instancia->id}");

        $todasHerramientas = \App\Models\InventarioLab::all();
        Log::info("Cantidad total de herramientas encontradas: " . $todasHerramientas->count());

        $herramientasAsignadas = $instancia->herramientasLab 
            ? $instancia->herramientasLab->pluck('id')->toArray() 
            : [];

        Log::info("Herramientas asignadas a la instancia: ", $herramientasAsignadas);

        $data = $todasHerramientas->map(function($h) use ($herramientasAsignadas) {
            return [
                'id' => $h->id,
                'nombre' => $h->equipamiento . ($h->marca_modelo ? ' (' . $h->marca_modelo . ')' : ''),
                'asignada' => in_array($h->id, $herramientasAsignadas),
            ];
        });

        Log::info("Datos procesados correctamente. Total: " . $data->count());

        return response()->json(['herramientas' => $data]);

    } catch (\Exception $e) {
        Log::error("Error al obtener herramientas de la instancia ID {$instanciaId}: " . $e->getMessage());
        return response()->json(['error' => 'No se pudo obtener la información de herramientas'], 500);
    }
}





    public function deshacerAsignaciones(Request $request)
    {
        $this->authorizeGestionOrdenes();

        try {
            $instanciaId = $request->instancia_id;
            $cotizacionId = $request->cotizacion_id;
            $currentUser = Auth::user();

            DB::beginTransaction();

            // Obtener la instancia de la muestra
            $instanciaMuestra = CotioInstancia::findOrFail($instanciaId);

            // Verificar que sea una instancia de muestra (subitem = 0)
            if ($instanciaMuestra->cotio_subitem !== 0) {
                throw new \Exception('Solo se pueden deshacer asignaciones de muestras');
            }

            // Obtener todas las instancias de análisis asociadas
            $instanciasAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotizacionId,
                'cotio_item' => $instanciaMuestra->cotio_item,
                'instance_number' => $instanciaMuestra->instance_number
            ])->where('cotio_subitem', '!=', 0)->get();

            // 1. Eliminar notificaciones relacionadas con esta muestra y sus análisis
            $idsInstancias = $instanciasAnalisis->pluck('id')->push($instanciaMuestra->id);
            
            DB::table('simple_notifications')
                ->whereIn('instancia_id', $idsInstancias)
                ->delete();

            // 2. Desactivar todas las instancias de análisis asociadas
            foreach ($instanciasAnalisis as $instancia) {
                $instancia->update([
                    'active_ot' => false,
                    'cotio_estado_analisis' => null,
                    'coordinador_codigo' => null,
                    'fecha_coordinacion' => null,
                    'fecha_inicio_ot' => null,
                    'fecha_fin_ot' => null,
                ]);

                DB::table('instancia_responsable_analisis')
                    ->where('cotio_instancia_id', $instancia->id)
                    ->delete();
                    
                DB::table('cotio_inventario_lab')
                    ->where('cotio_instancia_id', $instancia->id)
                    ->delete();
            }

            // 3. Desactivar la instancia principal
            $instanciaMuestra->update([
                'active_ot' => false,
                'cotio_estado_analisis' => null,
                'coordinador_codigo' => null,
                'fecha_coordinacion' => null,
                'fecha_inicio_ot' => null,
                'fecha_fin_ot' => null,
                'time_annulled' => $instanciaMuestra->time_annulled + 1,
            ]);
            
            DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $instanciaMuestra->id)
                ->delete();
                
            DB::table('cotio_inventario_lab')
                ->where('cotio_instancia_id', $instanciaMuestra->id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Asignaciones deshechas y notificaciones eliminadas correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al deshacer asignaciones: ' . $e->getMessage()
            ]);
        }
    }

    public function getResponsablesAnalisis(Request $request)
    {
        try {
            $validated = $request->validate([
                'cotio_numcoti' => 'required',
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required'
            ]);

            // Obtener el análisis específico
            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $validated['cotio_numcoti'],
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            if (!$instanciaAnalisis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Análisis no encontrado'
                ]);
            }

            $responsables = $instanciaAnalisis->responsablesAnalisis()->get();

            return response()->json([
                'success' => true,
                'responsables' => $responsables
                    ->map(fn ($responsable) => trim($responsable->usu_codigo))
                    ->toArray(),
                'sectores' => AsignacionSectorLaboratorio::sectoresConResponsablesAsignadosParaApi($responsables),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener responsables: ' . $e->getMessage()
            ]);
        }
    }
    public function editarResponsables(Request $request, $cotio_numcoti)
    {
        $this->authorizeGestionOrdenes();

        try {
            $validated = $request->validate([
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required',
                'responsables_analisis' => 'nullable|array',
                'responsables_analisis.*' => 'exists:usu,usu_codigo'
            ]);

            DB::beginTransaction();

            // Obtener el análisis específico
            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            if (!$instanciaAnalisis) {
                throw new \Exception('Análisis no encontrado');
            }

            // Obtener responsables enviados, asegurándonos de que sea un array válido
            $nuevosResponsables = array_values(array_filter(array_map('trim', $validated['responsables_analisis'] ?? [])));

            $responsablesActualesAnalisis = $instanciaAnalisis->codigosResponsablesAnalisisAsignados();

            $codigosNuevosUsuarios = AsignacionSectorLaboratorio::codigosUsuariosParaAsignar($nuevosResponsables);

            $responsablesFinales = array_values(array_unique(array_map(
                'trim',
                array_merge(
                    array_map('trim', $responsablesActualesAnalisis),
                    $codigosNuevosUsuarios
                )
            )));

            $codigosExactosParaSync = $this->resolverCodigosUsuExactos($responsablesFinales);

            Log::info('Editando responsables de análisis específico', [
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number'],
                'responsables_recibidos' => $validated['responsables_analisis'] ?? 'null',
                'responsables_actuales' => $responsablesActualesAnalisis,
                'nuevos_responsables' => $nuevosResponsables,
                'codigos_finales' => $responsablesFinales,
                'codigos_exactos_sync' => $codigosExactosParaSync,
                'instancia_id' => $instanciaAnalisis->id
            ]);

            $instanciaAnalisis->responsablesAnalisis()->sync($codigosExactosParaSync);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Responsables agregados correctamente al análisis.',
                'debug' => [
                    'responsables_anteriores' => $responsablesActualesAnalisis,
                    'nuevos_responsables' => $nuevosResponsables,
                    'codigos_finales' => $responsablesFinales,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error editando responsables', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar responsables: ' . $e->getMessage()
            ]);
        }
    }

    public function quitarResponsable(Request $request, $cotio_numcoti)
    {
        $this->authorizeGestionOrdenes();

        try {
            $validated = $request->validate([
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required',
                'responsable_codigo' => 'required_without:sector_codigo|nullable|string',
                'sector_codigo' => 'required_without:responsable_codigo|nullable|string',
            ]);

            Log::info('DEBUG - Iniciando quitarResponsable', [
                'datos_recibidos' => $validated,
                'cotio_numcoti' => $cotio_numcoti
            ]);

            DB::beginTransaction();

            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            if (!$instanciaAnalisis) {
                throw new \Exception('Análisis no encontrado');
            }

            if (! empty($validated['sector_codigo'])) {
                $sectorCodigo = trim((string) $validated['sector_codigo']);
                $responsablesActuales = $instanciaAnalisis->responsablesAnalisis()->get();
                $porCodigoExacto = $responsablesActuales->keyBy(
                    fn (User $r) => trim((string) $r->usu_codigo)
                );

                $miembrosSector = AsignacionSectorLaboratorio::usuariosDelSector($sectorCodigo)
                    ->filter(fn (User $u) => $porCodigoExacto->has(trim((string) $u->usu_codigo)));

                if ($miembrosSector->isEmpty()) {
                    throw new \Exception('No hay responsables de ese sector asignados a este análisis');
                }

                foreach ($miembrosSector as $miembro) {
                    $asignado = $porCodigoExacto->get(trim((string) $miembro->usu_codigo));
                    if ($asignado) {
                        $instanciaAnalisis->responsablesAnalisis()->detach($asignado->usu_codigo);
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Sector removido correctamente del análisis.',
                ]);
            }

            $responsableCodigo = $validated['responsable_codigo'];

            // Obtener todos los responsables actuales ANTES de verificar
            $responsablesActuales = $instanciaAnalisis->responsablesAnalisis()->get();
            Log::info('DEBUG - Responsables actuales', [
                'total_responsables' => $responsablesActuales->count(),
                'responsables' => $responsablesActuales->map(function($r) {
                    return [
                        'usu_codigo' => $r->usu_codigo,
                        'usu_codigo_trimmed' => trim($r->usu_codigo),
                        'usu_descripcion' => $r->usu_descripcion
                    ];
                })->toArray()
            ]);

            // SOLUCIÓN: Buscar el código exacto con espacios incluidos
            $responsableExacto = $responsablesActuales->first(function($responsable) use ($responsableCodigo) {
                return trim($responsable->usu_codigo) === trim($responsableCodigo);
            });

            if (!$responsableExacto) {
                Log::info('DEBUG - Responsable no encontrado con trim', [
                    'buscado' => $responsableCodigo,
                    'disponibles' => $responsablesActuales->pluck('usu_codigo')->toArray()
                ]);
                throw new \Exception('El responsable no está asignado a este análisis');
            }

            // Usar el código exacto de la base de datos (con espacios)
            $codigoExacto = $responsableExacto->usu_codigo;

            Log::info('DEBUG - Códigos comparados', [
                'codigo_recibido' => "'{$responsableCodigo}'",
                'codigo_exacto_bd' => "'{$codigoExacto}'",
                'son_iguales_trim' => trim($responsableCodigo) === trim($codigoExacto)
            ]);

            // Verificar que el responsable esté asignado (usando código exacto)
            $estaAsignado = $instanciaAnalisis->responsablesAnalisis()
                ->where('usu.usu_codigo', $codigoExacto)
                ->exists();

            Log::info('DEBUG - Verificación de asignación', [
                'responsable_codigo_recibido' => $responsableCodigo,
                'responsable_codigo_exacto' => $codigoExacto,
                'esta_asignado' => $estaAsignado
            ]);

            if (!$estaAsignado) {
                throw new \Exception('El responsable no está asignado a este análisis');
            }

            // Quitar responsable del análisis específico
            Log::info('DEBUG - Antes de detach', [
                'responsable_a_quitar_original' => $responsableCodigo,
                'responsable_a_quitar_exacto' => $codigoExacto,
                'instancia_id' => $instanciaAnalisis->id
            ]);

            // Método 1: Usando detach de Eloquent con código exacto
            $resultadoDetach = $instanciaAnalisis->responsablesAnalisis()->detach($codigoExacto);
            
            Log::info('DEBUG - Resultado de detach', [
                'resultado' => $resultadoDetach,
                'responsable_quitado_exacto' => $codigoExacto
            ]);

            // Método 2: Si detach no funciona, verificar tabla pivot directamente
            if ($resultadoDetach == 0) {
                Log::info('DEBUG - Detach devolvió 0, verificando tabla pivot directamente');
                
                // Verificar qué hay exactamente en la tabla pivot
                $registrosPivot = DB::table('instancia_responsable_analisis')
                    ->where('cotio_instancia_id', $instanciaAnalisis->id)
                    ->get();
                
                Log::info('DEBUG - Registros en tabla pivot', [
                    'instancia_id' => $instanciaAnalisis->id,
                    'total_registros' => $registrosPivot->count(),
                    'registros' => $registrosPivot->map(function($r) {
                        return [
                            'cotio_instancia_id' => $r->cotio_instancia_id,
                            'usu_codigo' => "'{$r->usu_codigo}'",
                            'created_at' => $r->created_at,
                            'updated_at' => $r->updated_at
                        ];
                    })->toArray()
                ]);
                
                // Intentar con diferentes variaciones del código
                $variacionesCodigo = [
                    $codigoExacto,
                    trim($codigoExacto),
                    $responsableCodigo,
                    trim($responsableCodigo)
                ];
                
                $resultadoSQL = 0;
                foreach ($variacionesCodigo as $variacion) {
                    $resultadoSQL = DB::table('instancia_responsable_analisis')
                        ->where('cotio_instancia_id', $instanciaAnalisis->id)
                        ->where('usu_codigo', $variacion)
                        ->delete();
                    
                    if ($resultadoSQL > 0) {
                        Log::info('DEBUG - SQL exitoso con variación', [
                            'variacion_exitosa' => "'{$variacion}'",
                            'resultado' => $resultadoSQL
                        ]);
                        break;
                    }
                }
                
                if ($resultadoSQL == 0) {
                    // Intentar con LIKE para encontrar coincidencias parciales
                    $resultadoLike = DB::table('instancia_responsable_analisis')
                        ->where('cotio_instancia_id', $instanciaAnalisis->id)
                        ->where('usu_codigo', 'LIKE', trim($responsableCodigo) . '%')
                        ->delete();
                    
                    Log::info('DEBUG - Resultado con LIKE', [
                        'patron_like' => "'" . trim($responsableCodigo) . "%'",
                        'resultado' => $resultadoLike
                    ]);
                    
                    $resultadoSQL = $resultadoLike;
                }
                
                // Si aún no funciona, intentar fuera de la transacción
                if ($resultadoSQL == 0) {
                    Log::info('DEBUG - Intentando fuera de transacción');
                    
                    DB::commit(); // Commit temporal
                    
                    $resultadoSinTransaccion = DB::table('instancia_responsable_analisis')
                        ->where('cotio_instancia_id', $instanciaAnalisis->id)
                        ->where('usu_codigo', 'LIKE', trim($responsableCodigo) . '%')
                        ->delete();
                    
                    Log::info('DEBUG - Resultado sin transacción', [
                        'resultado' => $resultadoSinTransaccion
                    ]);
                    
                    DB::beginTransaction(); // Reiniciar transacción
                    $resultadoSQL = $resultadoSinTransaccion;
                }
                
                Log::info('DEBUG - Resultado SQL final', [
                    'resultado' => $resultadoSQL,
                    'instancia_id' => $instanciaAnalisis->id,
                    'codigo_buscado' => $codigoExacto
                ]);
                
                $resultadoDetach = $resultadoSQL; // Para el log final
            }

            // Verificar responsables después del detach
            $responsablesDespues = $instanciaAnalisis->responsablesAnalisis()->get();
            Log::info('DEBUG - Responsables después de detach', [
                'total_responsables' => $responsablesDespues->count(),
                'responsables' => $responsablesDespues->map(function($r) {
                    return [
                        'usu_codigo' => $r->usu_codigo,
                        'usu_descripcion' => $r->usu_descripcion
                    ];
                })->toArray()
            ]);

            // NUEVA FUNCIONALIDAD: Verificar si el responsable sigue asignado a otros análisis
            $todosLosAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'instance_number' => $validated['instance_number']
            ])->where('cotio_subitem', '>', 0)->get();

            $responsableEnOtrosAnalisis = false;
            foreach ($todosLosAnalisis as $analisis) {
                if ($analisis->responsablesAnalisis()->where('usu.usu_codigo', $codigoExacto)->exists()) {
                    $responsableEnOtrosAnalisis = true;
                    break;
                }
            }

            Log::info('DEBUG - Verificación de responsable en otros análisis', [
                'responsable_codigo' => $codigoExacto,
                'total_analisis_verificados' => $todosLosAnalisis->count(),
                'esta_en_otros_analisis' => $responsableEnOtrosAnalisis
            ]);

            $quitadoDeMuestra = false;
            if (!$responsableEnOtrosAnalisis) {
                // El responsable no está en ningún análisis, quitarlo también de la muestra principal
                $muestraPrincipal = CotioInstancia::where([
                    'cotio_numcoti' => $cotio_numcoti,
                    'cotio_item' => $validated['cotio_item'],
                    'cotio_subitem' => 0,
                    'instance_number' => $validated['instance_number']
                ])->first();

                if ($muestraPrincipal) {
                    $resultadoMuestra = $muestraPrincipal->responsablesAnalisis()->detach($codigoExacto);
                    $quitadoDeMuestra = $resultadoMuestra > 0;
                    
                    Log::info('DEBUG - Limpieza de muestra principal', [
                        'muestra_principal_id' => $muestraPrincipal->id,
                        'responsable_quitado_de_muestra' => $quitadoDeMuestra,
                        'resultado_detach_muestra' => $resultadoMuestra
                    ]);
                }
            }

            DB::commit();

            // Obtener nombre del responsable para la respuesta
            $responsable = User::where('usu_codigo', $codigoExacto)->first();
            $nombreResponsable = $responsable ? $responsable->usu_descripcion : trim($responsableCodigo);

            Log::info('Responsable quitado exitosamente', [
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number'],
                'responsable_quitado_original' => $responsableCodigo,
                'responsable_quitado_exacto' => $codigoExacto,
                'instancia_id' => $instanciaAnalisis->id,
                'resultado_detach' => $resultadoDetach,
                'quitado_de_muestra_principal' => $quitadoDeMuestra,
                'estaba_en_otros_analisis' => $responsableEnOtrosAnalisis
            ]);

            // Crear mensaje dinámico según lo que se hizo
            $mensaje = "Responsable {$nombreResponsable} quitado correctamente del análisis.";
            if ($quitadoDeMuestra) {
                $mensaje .= " También se quitó de la muestra principal al no estar asignado a ningún otro análisis.";
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'debug' => [
                    'responsables_antes' => $responsablesActuales->count(),
                    'responsables_despues' => $responsablesDespues->count(),
                    'detach_result' => $resultadoDetach,
                    'quitado_de_muestra' => $quitadoDeMuestra,
                    'verificacion_otros_analisis' => !$responsableEnOtrosAnalisis
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error quitando responsable', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'datos_request' => $validated ?? 'No validados'
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al quitar responsable: ' . $e->getMessage()
            ]);
        }
    }

    // Método temporal para eliminar directamente - SOLO PARA DEBUG
    public function forzarEliminacion(Request $request, $cotio_numcoti)
    {
        try {
            $validated = $request->validate([
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required',
                'responsable_codigo' => 'required'
            ]);

            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            if (!$instanciaAnalisis) {
                return response()->json(['error' => 'Instancia no encontrada']);
            }

            // Mostrar todos los registros de la tabla pivot para esta instancia
            $registros = DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $instanciaAnalisis->id)
                ->get();

            // Intentar eliminar usando LIKE con el código trimmed
            $eliminados = DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $instanciaAnalisis->id)
                ->where('usu_codigo', 'LIKE', trim($validated['responsable_codigo']) . '%')
                ->delete();

            return response()->json([
                'instancia_id' => $instanciaAnalisis->id,
                'registros_antes' => $registros->toArray(),
                'codigo_buscado' => $validated['responsable_codigo'],
                'patron_like' => trim($validated['responsable_codigo']) . '%',
                'eliminados' => $eliminados
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ]);
        }
    }

    // Método temporal para debug - eliminar después de resolver el problema
    public function debugResponsables(Request $request, $cotio_numcoti)
    {
        try {
            $validated = $request->validate([
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required'
            ]);

            // Obtener el análisis específico
            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            if (!$instanciaAnalisis) {
                return response()->json(['error' => 'Instancia no encontrada']);
            }

            // Verificar directamente en la tabla pivot
            $responsablesPivot = DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $instanciaAnalisis->id)
                ->get();

            // Verificar usando la relación
            $responsablesRelacion = $instanciaAnalisis->responsablesAnalisis()->get();

            return response()->json([
                'instancia_id' => $instanciaAnalisis->id,
                'instancia_info' => [
                    'cotio_numcoti' => $instanciaAnalisis->cotio_numcoti,
                    'cotio_item' => $instanciaAnalisis->cotio_item,
                    'cotio_subitem' => $instanciaAnalisis->cotio_subitem,
                    'instance_number' => $instanciaAnalisis->instance_number,
                ],
                'responsables_pivot_directo' => $responsablesPivot->toArray(),
                'responsables_relacion' => $responsablesRelacion->map(function($r) {
                    return [
                        'usu_codigo' => $r->usu_codigo,
                        'usu_descripcion' => $r->usu_descripcion
                    ];
                })->toArray(),
                'total_pivot' => $responsablesPivot->count(),
                'total_relacion' => $responsablesRelacion->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }


    public function requestReview(Request $request, $instance)
    {
        $instancia = CotioInstancia::findOrFail($instance);
        $this->authorizeAccesoInstanciaAnalisis($instancia);
        $instancia->request_review = true;
        $instancia->observaciones_request_review = $request->observaciones;
        $instancia->save();

        // Notificar a todos los responsables de análisis asignados
        try {
            $usuario = Auth::user();
            $senderCodigo = $usuario?->usu_codigo;
            $observaciones = (string) ($request->input('observaciones') ?? '');

            foreach ($instancia->responsablesAnalisis as $responsable) {
                $mensaje = sprintf(
                    'Se solicitó revisión de resultados para "%s" (COTI %s, ítem %s/%s, instancia %s). %s%s',
                    $instancia->cotio_descripcion,
                    $instancia->cotio_numcoti,
                    $instancia->cotio_item,
                    $instancia->cotio_subitem,
                    $instancia->instance_number,
                    $senderCodigo ? 'Solicitado por: ' . ($usuario->usu_descripcion ?? $senderCodigo) . '. ' : '',
                    $observaciones !== '' ? ('Obs: ' . $observaciones) : ''
                );

                SimpleNotification::create([
                    'coordinador_codigo' => $responsable->usu_codigo, // receptor: responsable de análisis
                    'sender_codigo' => $senderCodigo,
                    'instancia_id' => $instancia->id,
                    'mensaje' => $mensaje,
                    'url' => SimpleNotification::generarUrlPorRol($responsable->usu_codigo, $instancia->id),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron enviar notificaciones de requestReview', [
                'instancia_id' => $instancia->id,
                'error' => $e->getMessage(),
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function requestReviewCancel(Request $request, $instance)
    {
        $instancia = CotioInstancia::findOrFail($instance);
        $this->authorizeAccesoInstanciaAnalisis($instancia);
        $instancia->request_review = false;
        $instancia->observaciones_request_review = null;
        $instancia->save();
        return response()->json(['success' => true]);
    }

    public function solicitudCambioOrden(Request $request, $cotizacion)
    {
        $user = Auth::user();
        if (! $user || ! OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
            abort(403, 'No autorizado para solicitar cambios en esta orden.');
        }

        $validated = $request->validate([
            'motivo' => 'required|string|max:2000',
            'cotio_item' => 'nullable|integer|min:1',
            'instance_number' => 'nullable|integer|min:1',
            'instancia_id' => 'nullable|integer|exists:cotio_instancias,id',
        ]);

        $cotizacionModel = Coti::findOrFail($cotizacion);
        if (! OrdenesAccesoPorSector::cotizacionVisibleParaUsuario((int) $cotizacionModel->coti_num, $user)) {
            abort(403, 'No tiene acceso a esta orden de trabajo.');
        }

        $instanciaReferencia = null;
        if (! empty($validated['instancia_id'])) {
            $instanciaReferencia = CotioInstancia::find($validated['instancia_id']);
        } elseif (! empty($validated['cotio_item']) && ! empty($validated['instance_number'])) {
            $instanciaReferencia = CotioInstancia::where([
                'cotio_numcoti' => $cotizacionModel->coti_num,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => 0,
                'instance_number' => $validated['instance_number'],
            ])->first();
        }

        if ($instanciaReferencia && AnalisisResultadoValidacion::instanciaEstaAnalizada($instanciaReferencia)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede solicitar cambios: la muestra ya está analizada.',
            ], 422);
        }

        $motivo = trim((string) $validated['motivo']);
        $contexto = sprintf(
            'COTI %s%s',
            $cotizacionModel->coti_num,
            $instanciaReferencia
                ? sprintf(' (muestra ítem %s, instancia %s)', $instanciaReferencia->cotio_item, $instanciaReferencia->instance_number)
                : ''
        );

        $mensajeBase = sprintf(
            'Solicitud de cambio en orden de trabajo %s. Solicitado por: %s (%s). Motivo: %s',
            $contexto,
            $user->usu_descripcion ?? $user->usu_codigo,
            $user->usu_codigo,
            $motivo
        );

        try {
            foreach (OrdenesAccesoPorSector::usuariosGestoresOrdenes() as $gestor) {
                $urlGestor = $instanciaReferencia
                    ? (SimpleNotification::generarUrlPorRol($gestor->usu_codigo, $instanciaReferencia->id)
                        ?? route('ordenes.ver-detalle', ['cotizacion' => $cotizacionModel->coti_num], true))
                    : route('ordenes.ver-detalle', ['cotizacion' => $cotizacionModel->coti_num], true);

                SimpleNotification::create([
                    'coordinador_codigo' => $gestor->usu_codigo,
                    'sender_codigo' => $user->usu_codigo,
                    'instancia_id' => $instanciaReferencia?->id,
                    'mensaje' => $mensajeBase,
                    'url' => $urlGestor,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron enviar notificaciones de solicitud de cambio', [
                'cotizacion' => $cotizacionModel->coti_num,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar la solicitud. Intente nuevamente.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud de cambio enviada a los coordinadores con permiso de gestión.',
        ]);
    }

    public function finalizarAnalisisSeleccionados(Request $request)
    {
        try {
            $request->validate([
                'instancia_ids' => 'required|array',
                'instancia_ids.*' => 'required|integer|exists:cotio_instancias,id'
            ]);

            $instanciaIds = $request->instancia_ids;
            $user = Auth::user();

            // Verificar que las instancias estén en OT y no estén ya finalizadas
            // Solo trabajar con análisis (cotio_subitem > 0), no con muestras
            $instancias = CotioInstancia::whereIn('id', $instanciaIds)
                ->where('active_ot', true)
                ->where('cotio_subitem', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('cotio_estado_analisis')
                    ->orWhere('cotio_estado_analisis', '!=', 'analizado');
                })
                ->get()
                ->filter(function (CotioInstancia $instancia) use ($user) {
                    if (! OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
                        return true;
                    }

                    return OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores(
                        $instancia,
                        OrdenesAccesoPorSector::sectoresUsuario($user)
                    );
                })
                ->values();

            if ($instancias->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay análisis válidos para finalizar en su laboratorio. Puede que ya estén finalizados o no estén en OT.'
                ], 400);
            }

            $sinResultado = $instancias->filter(
                fn (CotioInstancia $instancia) => ! AnalisisResultadoValidacion::instanciaTieneResultado($instancia)
            );
            $instanciasValidas = $instancias->reject(
                fn (CotioInstancia $instancia) => ! AnalisisResultadoValidacion::instanciaTieneResultado($instancia)
            );

            if ($instanciasValidas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => AnalisisResultadoValidacion::mensajeVariasSinResultado($sinResultado),
                ], 422);
            }

            $updatedCount = CotioInstancia::whereIn('id', $instanciasValidas->pluck('id'))
                ->update(['cotio_estado_analisis' => 'analizado']);

            $instanciasValidas
                ->unique(fn (CotioInstancia $i) => $i->cotio_numcoti.'|'.$i->cotio_item.'|'.$i->instance_number)
                ->each(function (CotioInstancia $analisis) {
                    AnalisisResultadoValidacion::sincronizarEstadoMuestraDesdeAnalisis(
                        $analisis->cotio_numcoti,
                        $analisis->cotio_item,
                        $analisis->instance_number
                    );
                });

            $mensaje = "Se finalizaron correctamente {$updatedCount} análisis.";
            if ($sinResultado->isNotEmpty()) {
                $mensaje .= ' ' . AnalisisResultadoValidacion::mensajeVariasSinResultado($sinResultado);
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al finalizar análisis seleccionados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar los análisis: ' . $e->getMessage()
            ], 500);
        }
    }

    public function asignarResponsablesAnalisisSeleccionados(Request $request)
    {
        $this->authorizeGestionOrdenes();

        try {
            $request->validate([
                'instancia_ids' => 'required|array',
                'instancia_ids.*' => 'required|integer|exists:cotio_instancias,id',
                'responsables_analisis' => 'required|array',
                'responsables_analisis.*' => 'required|string|exists:usu,usu_codigo'
            ]);

            $instanciaIds = $request->instancia_ids;
            $responsablesAnalisis = array_map('trim', $request->responsables_analisis);

            // Verificar que las instancias sean análisis válidos (cotio_subitem > 0)
            $instancias = CotioInstancia::whereIn('id', $instanciaIds)
                ->where('active_ot', true)
                ->where('cotio_subitem', '>', 0) // Solo análisis, no muestras
                ->get();

            if ($instancias->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron análisis válidos para asignar responsables.'
                ], 400);
            }

            $codigosExactos = AsignacionSectorLaboratorio::codigosUsuariosParaAsignar($responsablesAnalisis);

            if (empty($codigosExactos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron responsables válidos.'
                ], 400);
            }

            DB::beginTransaction();

            $updatedCount = 0;
            foreach ($instancias as $instancia) {
                $responsablesActuales = $instancia->codigosResponsablesAnalisisAsignados();

                $todosLosResponsables = array_merge(
                    array_map('trim', $responsablesActuales),
                    array_map('trim', $codigosExactos)
                );
                $responsablesFinales = array_values(array_unique($todosLosResponsables));

                $codigosFinales = $this->resolverCodigosUsuExactos($responsablesFinales);

                $instancia->responsablesAnalisis()->sync($codigosFinales);
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Se asignaron correctamente los responsables a {$updatedCount} análisis."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al asignar responsables a análisis seleccionados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar responsables: ' . $e->getMessage()
            ], 500);
        }
    }

    private function usuarioEsPrivilegiadoMisOrdenes(User $user): bool
    {
        if ((int) $user->usu_nivel >= 900) {
            return true;
        }

        return $user->hasRole('coordinador_lab') && $user->puedeGestionarOrdenes();
    }

    private function authorizeGestionOrdenes(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->puedeGestionarOrdenes()) {
            abort(403, 'No autorizado para gestionar órdenes de trabajo.');
        }
    }

    private function authorizeAccesoInstanciaAnalisis(CotioInstancia $instancia): void
    {
        $user = Auth::user();
        if (! $user || ! OrdenesAccesoPorSector::debeFiltrarPorSector($user)) {
            return;
        }

        $sectores = OrdenesAccesoPorSector::sectoresUsuario($user);
        if (! OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($instancia, $sectores)) {
            abort(403, 'No tiene acceso a este análisis.');
        }
    }

    /**
     * Analistas: siempre solo sus asignaciones. Coordinador/admin: según ?solo_mis_asignaciones=1
     */
    private function resolverSoloMisAsignacionesMisOrdenes(User $user, Request $request): bool
    {
        if (!$this->usuarioEsPrivilegiadoMisOrdenes($user)) {
            return true;
        }

        return $request->boolean('solo_mis_asignaciones');
    }

    /**
     * Lista de analitos para la vista de sugerencias (filtro por estado y/o nombre).
     */
    private function construirAnalitosSugeridosMisOrdenes($todosAnalisis, ?string $estado, string $nombreAnalisis)
    {
        if (! $estado && $nombreAnalisis === '') {
            return collect();
        }

        $coleccion = $todosAnalisis;

        if ($estado) {
            $estadoLower = strtolower($estado);
            $coleccion = $coleccion->filter(function ($analito) use ($estadoLower) {
                return strtolower($analito->cotio_estado_analisis ?? '') === $estadoLower;
            });
        }

        if ($nombreAnalisis !== '' && $estado) {
            $needle = mb_strtolower($nombreAnalisis);
            $coleccion = $coleccion->filter(function ($analito) use ($needle) {
                $descripcionInstancia = mb_strtolower(trim((string) ($analito->cotio_descripcion ?? '')));
                if (str_contains($descripcionInstancia, $needle)) {
                    return true;
                }

                $descripcionTarea = mb_strtolower(trim((string) ($analito->tarea->cotio_descripcion ?? '')));

                return str_contains($descripcionTarea, $needle);
            });
        }

        return $coleccion
            ->sortBy([
                fn ($a) => strtolower($a->cotio_descripcion ?? ''),
                fn ($a) => $a->cotio_numcoti,
            ])
            ->unique(function ($a) {
                return strtolower($a->cotio_descripcion ?? '').'|'.
                    ($a->cotio_numcoti ?? '').'|'.
                    ($a->cotio_item ?? '').'|'.
                    ($a->cotio_subitem ?? '').'|'.
                    ($a->instance_number ?? '');
            })
            ->values();
    }

    /**
     * Filtro por nombre/descripción de análisis (instancia o línea cotio de la tarea).
     */
    private function aplicarFiltroNombreAnalisisMisOrdenes($queryAnalisis, $queryMuestras, string $nombreAnalisis): void
    {
        $needle = '%'.mb_strtolower($nombreAnalisis).'%';
        $tablaInstancias = (new CotioInstancia)->getTable();

        $filtroPorDescripcionAnalisis = function ($sub, string $aliasInstancia) use ($needle) {
            $sub->whereRaw(
                'LOWER(TRIM(COALESCE('.$aliasInstancia.'.cotio_descripcion, \'\'))) LIKE ?',
                [$needle]
            )->orWhereExists(function ($ex) use ($needle, $aliasInstancia) {
                $ex->select(DB::raw(1))
                    ->from('cotio')
                    ->whereColumn('cotio.cotio_numcoti', $aliasInstancia.'.cotio_numcoti')
                    ->whereColumn('cotio.cotio_item', $aliasInstancia.'.cotio_item')
                    ->whereColumn('cotio.cotio_subitem', $aliasInstancia.'.cotio_subitem')
                    ->whereRaw('LOWER(TRIM(COALESCE(cotio.cotio_descripcion, \'\'))) LIKE ?', [$needle]);
            });
        };

        $queryAnalisis->where(function ($q) use ($filtroPorDescripcionAnalisis, $tablaInstancias) {
            $filtroPorDescripcionAnalisis($q, $tablaInstancias);
        });

        $queryMuestras->whereExists(function ($ex) use ($filtroPorDescripcionAnalisis, $tablaInstancias) {
            $ex->select(DB::raw(1))
                ->from($tablaInstancias.' as ci_filtro_analisis')
                ->whereColumn('ci_filtro_analisis.cotio_numcoti', $tablaInstancias.'.cotio_numcoti')
                ->whereColumn('ci_filtro_analisis.cotio_item', $tablaInstancias.'.cotio_item')
                ->whereColumn('ci_filtro_analisis.instance_number', $tablaInstancias.'.instance_number')
                ->where('ci_filtro_analisis.cotio_subitem', '>', 0)
                ->where('ci_filtro_analisis.active_ot', true)
                ->where(function ($sub) use ($filtroPorDescripcionAnalisis) {
                    $filtroPorDescripcionAnalisis($sub, 'ci_filtro_analisis');
                });
        });
    }

    /**
     * Filtra instancias cotio por responsable de análisis (pivot instancia_responsable_analisis).
     */
    private function aplicarFiltroUsuarioResponsableAnalisisInstancia($query, string $usuCodigo): void
    {
        $usuCodigo = trim($usuCodigo);
        if ($usuCodigo === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($sub) use ($usuCodigo) {
            $sub->select(DB::raw(1))
                ->from('instancia_responsable_analisis as ira')
                ->whereColumn('ira.cotio_instancia_id', 'cotio_instancias.id')
                ->whereRaw('TRIM(ira.usu_codigo) = ?', [$usuCodigo]);
        });
    }

    /**
     * Carga filas muestra (subitem 0) asociadas a análisis ya filtrados por usuario.
     *
     * @param  \Illuminate\Support\Collection<int, CotioInstancia>  $analisis
     * @param  array<int, string>  $with
     * @return \Illuminate\Support\Collection<int, CotioInstancia>
     */
    private function cargarMuestrasPadreDeAnalisisAsignados($analisis, array $with = []): \Illuminate\Support\Collection
    {
        if ($analisis->isEmpty()) {
            return collect();
        }

        $claves = $analisis
            ->map(fn (CotioInstancia $a) => $a->cotio_numcoti.'|'.$a->cotio_item.'|'.$a->instance_number)
            ->unique()
            ->values();

        $query = CotioInstancia::query()
            ->where('cotio_subitem', 0)
            ->where('active_ot', true);

        if ($with !== []) {
            $query->with($with);
        }

        $query->where(function ($outer) use ($claves) {
            foreach ($claves as $clave) {
                [$numcoti, $item, $instance] = explode('|', $clave, 3);
                $outer->orWhere(function ($w) use ($numcoti, $item, $instance) {
                    $w->where('cotio_numcoti', $numcoti)
                        ->where('cotio_item', $item)
                        ->where('instance_number', $instance);
                });
            }
        });

        return $query->get()->each(function ($item) {
            $item->setRelation('vehiculo', null);
        });
    }
}