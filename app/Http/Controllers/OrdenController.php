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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\InstanciaResponsableAnalisis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use App\Models\SimpleNotification;

class OrdenController extends Controller
{



    public function index(Request $request)
    {
        // dd($request);

        $verTodas = $request->query('ver_todas');
        $viewType = $request->get('view', 'lista');
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $user = Auth::user();
        $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
        $startOfWeek = $request->get('week') ? Carbon::parse($request->get('week'))->startOfWeek() : now()->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();
    
        // dd($request->all());
        // Vista de Calendario
        if ($viewType === 'calendario') {
            $query = CotioInstancia::query()
                ->where('enable_ot', true)
                ->with(['cotizacion', 'responsablesAnalisis']);
    
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
    
            // Filtros por rango de fechas
            if ($request->has('fecha_inicio_ot') && !empty($request->fecha_inicio_ot)) {
                $query->whereDate('fecha_inicio_ot', '>=', $request->fecha_inicio_ot);
            }
    
            if ($request->has('fecha_fin_ot') && !empty($request->fecha_fin_ot)) {
                $query->whereDate('fecha_fin_ot', '<=', $request->fecha_fin_ot);
            } else {
                // Mostrar por defecto el mes actual si no hay fecha_fin
                $query->whereBetween('fecha_inicio_ot', [
                    $currentMonth->copy()->startOfMonth(),
                    $currentMonth->copy()->endOfMonth()
                ]);
            }
    
            // Obtener resultados ordenados por fecha
            $instancias = $query->orderBy('fecha_inicio_ot', 'asc')->get();
    
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
                    $events->push([
                        'title' => $instancia->cotizacion->coti_empresa . ' - ' . $instancia->cotio_numcoti,
                        'start' => $instancia->fecha_inicio_ot,
                        'cotio_subitem' => $instancia->cotio_subitem,
                        'end' => $instancia->fecha_fin_ot ?? null,
                        'url' => route('categoria.verOrden', [
                            'cotizacion' => $instancia->cotio_numcoti,
                            'item' => $instancia->cotio_item,
                            'cotio_subitem' => $instancia->cotio_subitem,
                            'instance' => $instancia->instance_number
                        ]),
                        'extendedProps' => [
                            'empresa' => $instancia->cotizacion->coti_empresa,
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
    
        // Vista de Lista/Documento
        $baseQuery = CotioInstancia::query()
            ->select('cotio_numcoti')
            ->distinct()
            ->where('enable_ot', true);
    
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $searchTermLike = '%'.$searchTerm.'%';
                $cleanedIdSearch = ltrim(preg_replace('/[^0-9]/', '', $searchTerm), '0');
                
                $baseQuery->where(function($q) use ($searchTermLike, $cleanedIdSearch) {
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
                    
                    $q->orWhere('cotio_codigoum', 'like', $searchTermLike);
                });
            }
    
        if ($request->has('matriz') && !empty($request->matriz)) {
            $baseQuery->whereHas('cotizacion', function($q) use ($request) {
                $q->where('coti_codigomatriz', $request->matriz);
            });
        }
    
        if ($request->has('estado') && !empty($request->estado)) {
            if ($request->estado == 'pendiente por coordinar') {
                $baseQuery->where('enable_ot', true)
                          ->whereNull('cotio_estado_analisis');
            } else {
                $baseQuery->where('cotio_estado_analisis', $request->estado);
            }
        } elseif (!$verTodas) {
            $baseQuery->whereHas('cotizacion', function($q) {
                $q->where('coti_estado', 'A');
            });
        }
    
        // Paginación de cotizaciones únicas
        $pagination = $baseQuery->orderBy('cotio_numcoti', 'desc')
            ->paginate($viewType === 'documento' ? 100 : 100);
    
        // Obtener todas las instancias para las cotizaciones paginadas
        $instancias = CotioInstancia::with([
                'cotizacion.matriz',
                'tarea',
                'responsablesAnalisis',
                'cotizacion.instancias' => function ($q) {
                    $q->select('id', 'cotio_numcoti', 'cotio_estado_analisis', 'es_priori', 'fecha_inicio_ot', 'fecha_muestreo');
                }
            ])
            ->whereIn('cotio_numcoti', $pagination->pluck('cotio_numcoti'))
            ->orderBy('cotio_numcoti', 'desc')
            ->orderBy('cotio_item', 'asc')
            ->orderBy('cotio_subitem', 'asc')
            ->orderBy('instance_number', 'asc')
            ->get();
    
        // Agrupar instancias por cotización
        $ordenes = $instancias->groupBy('cotio_numcoti')->map(function ($group) {
            $cotizacion = $group->first()->cotizacion;
            $total = $group->where('cotio_subitem', '=', 0)->where('enable_ot', '=', 1)->count();
            $completadas = $group->where('cotio_estado_analisis', 'analizado')->where('cotio_subitem', '=', 0)->count();
            $enProceso = $group->where('cotio_estado_analisis', 'en revision analisis')->where('cotio_subitem', '=', 0)->count();
            $coordinadas = $group->where('cotio_estado_analisis', 'coordinado analisis')->where('cotio_subitem', '=', 0)->count();
            $porcentaje = $total > 0 ? round(($completadas / $total) * 100) : 0;
            
            $fecha_orden = $group->min('fecha_inicio_ot') ?? $group->min('fecha_muestreo');
            
            // Modificado: has_priority solo será true si hay muestras prioritarias Y al menos una no está analizada
            $has_priority = $group->contains(function ($instancia) {
                return $instancia->es_priori && strtolower(trim($instancia->cotio_estado_analisis ?? '')) != 'analizado';
            });
            
            // Determinar el estado predominante de la orden para ordenamiento
            $muestrasDelGrupo = $group->where('cotio_subitem', 0);
            $estadoPredominante = $this->determinarEstadoPredominanteConActiveOt($muestrasDelGrupo);
            
            return [
                'instancias' => $group,
                'cotizacion' => $cotizacion,
                'total' => $total,
                'completadas' => $completadas,
                'en_proceso' => $enProceso,
                'coordinadas' => $coordinadas,
                'porcentaje' => $porcentaje,
                'has_suspension' => $group->contains(function ($instancia) {
                    return strtolower(trim($instancia->cotio_estado_analisis)) === 'suspension';
                }),
                'has_priority' => $has_priority,
                'fecha_orden' => $fecha_orden,
                'estado_predominante' => $estadoPredominante
            ];
        });

        // Ordenar las órdenes según el criterio mejorado
        $ordenes = $ordenes->sortBy(function($orden) {
            $esPrioritaria = $orden['has_priority'];
            $estadoPredominante = $orden['estado_predominante'];
            $fechaOrden = $orden['fecha_orden'];
            

            
            return $this->calcularOrdenValorSimple($esPrioritaria, $estadoPredominante);
        });
    
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
            
            $has_priority = $group->contains(function ($instancia) {
                return $instancia->es_priori && strtolower(trim($instancia->cotio_estado_analisis ?? '')) != 'analizado';
            });
            
            return [
                'cotio_numcoti' => $group->first()->cotio_numcoti,
                'cotizacion' => $group->first()->cotizacion->coti_empresa ?? 'N/A',
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
        // Si está analizada y es prioritaria, no va primero
        if ($estadoPredominante === 'analizado' && $esPrioritaria) {
            return 500;
        }
        
        // Orden de prioridad
        if ($esPrioritaria && $estadoPredominante !== 'analizado') {
            return 100 + $this->getEstadoOrden($estadoPredominante);
        }
        
        // Orden por estado
        return 200 + $this->getEstadoOrden($estadoPredominante);
    }

    private function calcularOrdenValorSimple($esPrioritaria, $estadoPredominante)
    {
        // Si está analizada y es prioritaria, no va primero
        if ($estadoPredominante === 'analizado' && $esPrioritaria) {
            return 500;
        }
        
        // Orden de prioridad
        if ($esPrioritaria && $estadoPredominante !== 'analizado') {
            return 100 + $this->getEstadoOrden($estadoPredominante);
        }
        
        // Orden por estado
        return 200 + $this->getEstadoOrden($estadoPredominante);
    }





public function showOrdenes(Request $request)
{
    $user = Auth::user();
    $codigo = trim($user->usu_codigo);
    $viewType = $request->get('view', 'lista');
    $perPage = 50;
    $searchTerm = $request->get('search');
    $fechaInicio = $request->get('fecha_inicio_ot');
    $fechaFin = $request->get('fecha_fin_ot');
    $estado = $request->get('estado');
    
    $currentMonth = $request->get('month') 
        ? Carbon::parse($request->get('month')) 
        : now();

    // Initialize queries
    $queryMuestras = CotioInstancia::with([
        'muestra.cotizado',
        'muestra.vehiculo',
        'vehiculo',
        'herramientas',
        'responsablesAnalisis',
        'tareas.responsablesAnalisis'
    ])
    ->where('cotio_subitem', 0)
    ->where('active_ot', true)
    ->orderBy('fecha_inicio_ot', 'desc')
    ->orderByRaw("CASE WHEN cotio_estado_analisis = 'coordinado' THEN 0 ELSE 1 END");

    $queryAnalisis = CotioInstancia::with([
        'tarea.cotizado',
        'tarea.vehiculo',
        'vehiculo',
        'herramientas',
        'responsablesAnalisis'
    ])
    ->where('cotio_subitem', '>', 0)
    ->where('active_ot', true)
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

    // Apply filters - usar lógica de documento para consistencia
    $esPrivilegiado = ((int) $user->usu_nivel >= 900) || ($user->rol === 'coordinador_lab');
    
    // Para muestras: solo si el usuario es responsable de muestreo O tiene análisis asignados
    $queryMuestras->where(function ($query) use ($codigo) {
        $query->whereHas('responsablesAnalisis', function ($q) use ($codigo) {
            $q->where('usu.usu_codigo', $codigo);
        })->orWhereHas('tareas', function ($q) use ($codigo) {
            $q->where('cotio_subitem', '>', 0)
                ->where('active_ot', true)
                ->whereHas('responsablesAnalisis', function ($subQ) use ($codigo) {
                    $subQ->where('usu.usu_codigo', $codigo);
                });
        });
    });

    // Para análisis: aplicar filtro solo si no es privilegiado (igual que documento)
    if (!$esPrivilegiado) {
        $queryAnalisis->whereHas('responsablesAnalisis', function ($q) use ($codigo) {
            $q->where('usu.usu_codigo', $codigo);
        });
    }

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
    $muestras = $queryMuestras->get();
    $todosAnalisis = $queryAnalisis->get();

    // Build suggested analytes list when filtering by status
    $analitosSugeridos = collect();
    if ($estado) {
        // "$todosAnalisis" ya viene filtrado por estado si se envió "estado",
        // pero aplicamos un filtro defensivo y normalizamos para evitar discrepancias de mayúsculas.
        $estadoLower = strtolower($estado);
        $analitosSugeridos = $todosAnalisis
            ->filter(function ($analito) use ($estadoLower) {
                return strtolower($analito->cotio_estado_analisis ?? '') === $estadoLower;
            })
            // Filtrar por descripción si viene el parámetro
            ->when($request->filled('cotio_descripcion_analisis'), function ($collection) use ($request) {
                $needle = mb_strtolower(trim($request->get('cotio_descripcion_analisis')));
                return $collection->filter(function ($a) use ($needle) {
                    $descripcion = mb_strtolower($a->cotio_descripcion ?? '');
                    return $needle === '' || str_contains($descripcion, $needle);
                });
            })
            // Mantener orden consistente: por descripción y luego por cotización
            ->sortBy([
                fn ($a) => strtolower($a->cotio_descripcion ?? ''),
                fn ($a) => $a->cotio_numcoti,
            ])
            // Evitar duplicados exactos por clave compuesta relevante para el link de detalle
            ->unique(function ($a) {
                return strtolower($a->cotio_descripcion ?? '') . '|' .
                    ($a->cotio_numcoti ?? '') . '|' .
                    ($a->cotio_item ?? '') . '|' .
                    ($a->cotio_subitem ?? '') . '|' .
                    ($a->instance_number ?? '');
            })
            ->values();
    }

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
            
            // Update priority status
            if ($muestra->es_priori) {
                $grupo['has_priority'] = true;
            }

            // Add instance correctly
            $grupo['instancias']->push([
                'muestra' => $muestra->muestra,
                'instancia_muestra' => $muestra,
                'analisis' => collect(),
                'vehiculo' => $muestra->vehiculo ?? null,
                'responsables_muestreo' => $muestra->responsablesAnalisis,
                'is_priority' => $muestra->es_priori
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
                    $relatedSample = CotioInstancia::with(['muestra.cotizado', 'vehiculo', 'responsablesAnalisis'])
                        ->where([
                            'cotio_numcoti' => $analisis->cotio_numcoti,
                            'cotio_item' => $analisis->cotio_item,
                            'instance_number' => $analisis->instance_number,
                            'cotio_subitem' => 0,
                            'active_ot' => true
                        ])->first();

                    if ($relatedSample) {
                        $newInstancia = [
                            'muestra' => $relatedSample->muestra,
                            'instancia_muestra' => $relatedSample,
                            'analisis' => collect([$analisis]),
                            'vehiculo' => $relatedSample->vehiculo ?? null,
                            'responsables_muestreo' => $relatedSample->responsablesAnalisis,
                            'is_priority' => $relatedSample->es_priori
                        ];
                        
                        $grupo['instancias']->push($newInstancia);
                        
                        // Update group priority if needed
                        if ($relatedSample->es_priori) {
                            $grupo['has_priority'] = true;
                        }
                        
                        $ordenesAgrupadas->put($key, $grupo);
                    }
                }
            } else {
                // Si no existe el grupo de la cotización (no había muestras por falta de asignación), crearlo
                $relatedSample = CotioInstancia::with(['muestra.cotizado', 'vehiculo', 'responsablesAnalisis'])
                    ->where([
                        'cotio_numcoti' => $analisis->cotio_numcoti,
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number,
                        'cotio_subitem' => 0,
                        'active_ot' => true
                    ])->first();

                if ($relatedSample) {
                    $ordenesAgrupadas->put($key, [
                        'instancias' => collect([
                            [
                                'muestra' => $relatedSample->muestra,
                                'instancia_muestra' => $relatedSample,
                                'analisis' => collect([$analisis]),
                                'vehiculo' => $relatedSample->vehiculo ?? null,
                                'responsables_muestreo' => $relatedSample->responsablesAnalisis,
                                'is_priority' => $relatedSample->es_priori
                            ]
                        ]),
                        'cotizado' => $relatedSample->muestra->cotizado ?? null,
                        'has_priority' => (bool) $relatedSample->es_priori
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
                'vehiculo' => $muestra->vehiculo ?? null,
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
                $relatedSample = CotioInstancia::with(['muestra.cotizado', 'vehiculo', 'responsablesAnalisis'])
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
                        'vehiculo' => $relatedSample->vehiculo ?? null,
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
            $empresa = $muestra->muestra && $muestra->muestra->cotizacion 
                ? $muestra->muestra->cotizacion->coti_empresa 
                : '';
            
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
                    'priority' => $muestra->es_priori
                ]
            ];
        });
    }

    // Get related quotations
    $cotizacionesIds = $todosAnalisis->pluck('cotio_numcoti')
        ->merge($muestras->pluck('cotio_numcoti'))
        ->unique();
    $cotizaciones = Coti::whereIn('coti_num', $cotizacionesIds)->get()->keyBy('coti_num');

    $userCode = Auth::user()->usu_codigo;
    return view('mis-ordenes.index', [
        'ordenesAgrupadas' => $ordenesAgrupadas,
        'cotizaciones' => $cotizaciones,
        'tareasPaginadas' => $tareasPaginadas,
        'viewType' => $viewType,
        'request' => $request,
        'currentMonth' => $currentMonth,
        'events' => $events,
        'analitosSugeridos' => $analitosSugeridos,
        'currentUserCode' => $userCode

    ]);
}


public function showDetalle($ordenId)
{
    $cotizacion = Coti::findOrFail($ordenId);
    //solo usuario lab y lab1
    $usuarios = User::whereIn('usu_codigo', ['LAB1', 'LAB'])->get();
    $inventario = InventarioLab::all();

    $categoriasHabilitadas = $cotizacion->tareas()
        ->where('cotio_subitem', 0)
        ->orderBy('cotio_item')
        ->get();

    $categoriasIds = $categoriasHabilitadas->pluck('cotio_item')->toArray();

    $tareas = $cotizacion->tareas()
        ->whereIn('cotio_item', $categoriasIds)
        ->where('cotio_subitem', '!=', 0)
        ->orderBy('cotio_item')
        ->orderBy('cotio_subitem')
        ->get();

        $usuarios = User::withCount(['instanciasAnalisis' => function($query) use ($ordenId) {
            $query->where('cotio_numcoti', $ordenId)
                  ->where('cotio_estado_analisis', '!=', 'analizado');
        }])
        ->whereIn('usu_codigo', ['LAB1', 'LAB'])
        ->orderBy('usu_descripcion')
        ->get();

    $agrupadas = [];
    $metodosUnicos = collect();

    foreach ($categoriasHabilitadas as $categoria) {
        $item = $categoria->cotio_item;

        $instanciasMuestra = CotioInstancia::with('herramientas', 'responsablesAnalisis')
            ->where([
                'cotio_numcoti' => $cotizacion->coti_num,
                'cotio_item' => $item,
                'cotio_subitem' => 0,
                'enable_ot' => true
            ])
            ->orderBy('instance_number')
            ->get();

        $tareasDeCategoria = $tareas->where('cotio_item', $item);

        $instanciasConAnalisis = $instanciasMuestra->map(function($instanciaMuestra) use ($tareasDeCategoria, $cotizacion, &$metodosUnicos) {
            $analisisParaInstancia = $tareasDeCategoria->map(function($tarea) use ($instanciaMuestra, $cotizacion, &$metodosUnicos) {
                $tareaClonada = clone $tarea;
                
                $instanciaAnalisis = CotioInstancia::with('herramientas', 'responsablesAnalisis', 'metodoAnalisis')
                    ->where([
                        'cotio_numcoti' => $cotizacion->coti_num,
                        'cotio_item' => $tarea->cotio_item,
                        'cotio_subitem' => $tarea->cotio_subitem,
                        'instance_number' => $instanciaMuestra->instance_number
                    ])
                    ->first();

                if ($instanciaAnalisis) {
                    $tareaClonada->instancia = $instanciaAnalisis;
                    
                    // Recopilar métodos únicos
                    $metodo = $instanciaAnalisis->getMetodoAnalisisConTrim();
                    if ($metodo) {
                        $metodosUnicos->push([
                            'codigo' => trim($instanciaAnalisis->cotio_codigometodo_analisis ?? ''),
                            'metodo' => $metodo
                        ]);
                    }
                }

                return $tareaClonada;
            });

            return [
                'muestra' => $instanciaMuestra,
                'analisis' => $analisisParaInstancia
            ];
        });

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
    $cotizacion = Coti::findOrFail($cotizacion);
    $instance = $instance ?? 1;
    $usuariosAnalistas = User::where('rol', '!=', 'sector')
                ->orderBy('usu_descripcion')
                ->get();

    // Obtener la muestra principal
    $categoria = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $item)
                ->where('cotio_subitem', 0)
                ->firstOrFail();

    // Obtener la instancia de la muestra con responsables de análisis
    $instanciaMuestra = CotioInstancia::with(['responsablesAnalisis', 'valoresVariables'])
                ->where([
                    'cotio_numcoti' => $cotizacion->coti_num,
                    'cotio_item' => $item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instance,
                ])->first();

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

        $instanciaIds = $tareas->map(function ($tarea) use ($instance) {
            return CotioInstancia::where([
                'cotio_numcoti' => $tarea->cotio_numcoti,
                'cotio_item' => $tarea->cotio_item,
                'cotio_subitem' => $tarea->cotio_subitem,
                'instance_number' => $instance,
                'active_ot' => true
            ])->first()?->id;
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
            'usuariosAnalistas' => $usuariosAnalistas
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
            $tarea->instancia = $instancia;
            return $tarea;
        }
        return null;
    })->filter();

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
        'usuariosAnalistas' => $usuariosAnalistas
    ]);
}



public function asignarDetallesAnalisis(Request $request) 
{
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


public function showOrdenesAll($cotio_numcoti, $cotio_item, $cotio_subitem = 0, $instance = null)
{
    $instance = $instance ?? 1;
    $usuario = Auth::user();
    $usuarioActual = trim($usuario->usu_codigo);
    $esPrivilegiado = ((int) $usuario->usu_nivel >= 900) || ($usuario->rol === 'coordinador_lab');
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
            'muestra.vehiculo',
            'muestra.cotizacion',
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
                'error' => 'No se encontró la muestra principal.'
            ]);
        }

        Log::debug('Instancia de muestra encontrada', [
            'instancia_id' => $instanciaMuestra->id,
            'responsables' => $instanciaMuestra->responsablesAnalisis->pluck('usu_codigo')->toArray()
        ]);

        // Obtener análisis - coordinadores pueden ver todos, otros solo los asignados
        $analisisQuery = CotioInstancia::with([
            'tarea.vehiculo',
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

        // Si no es privilegiado, filtrar solo análisis asignados al usuario
        if (!$esPrivilegiado) {
            $analisisQuery->whereHas('responsablesAnalisis', function ($query) use ($usuarioActual) {
                $query->whereRaw('TRIM(instancia_responsable_analisis.usu_codigo) = ?', [$usuarioActual]);
            });
        }

        $analisis = $analisisQuery->orderBy('cotio_subitem')->get();

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
            'allHerramientas' => $allHerramientas
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
            'error' => 'Error al cargar la muestra: ' . $e->getMessage()
        ]);
    }
}



public function updateHerramientas(Request $request, $instanciaId)
{
    $instancia = CotioInstancia::findOrFail($instanciaId);

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





public function asignacionMasiva(Request $request, $ordenId)
{
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

        // 1. Obtener todos los usuarios de los sectores seleccionados
        $usuariosDelSector = collect();
        foreach ($responsablesAnalisis as $responsableCodigo) {
            $responsable = User::where('usu_codigo', $responsableCodigo)->first();
            if (!$responsable) {
                Log::error('Usuario no encontrado', ['responsable_codigo' => $responsableCodigo]);
                throw new \Exception("Usuario con código '$responsableCodigo' no encontrado.");
            }

            // Si el usuario es un líder de sector (LAB, LAB1), obtenemos sus miembros
            if ($responsable->miembros()->exists()) {
                $miembros = $responsable->miembros()->pluck('usu_codigo')->toArray();
                $usuariosDelSector = $usuariosDelSector->merge($responsable->miembros);
                Log::debug('Miembros del sector encontrados', [
                    'sector' => $responsableCodigo,
                    'miembros' => $miembros
                ]);
            }
            // Siempre incluimos al propio responsable (LAB/LAB1)
            $usuariosDelSector->push($responsable);
        }

        // Eliminar duplicados y obtener solo los códigos
        $usuariosASincronizar = $usuariosDelSector->unique('usu_codigo')
            ->pluck('usu_codigo')
            ->map('trim')
            ->toArray();

        Log::info('Usuarios a sincronizar', [
            'usuarios' => $usuariosASincronizar,
            'total' => count($usuariosASincronizar)
        ]);

        // 2. Obtener todas las instancias seleccionadas
        $instanciasSeleccionadas = CotioInstancia::with(['muestra'])
            ->whereIn('id', array_merge($instanciaSelecciones, $tareaSelecciones))
            ->get();

        Log::debug('Instancias seleccionadas', [
            'count' => $instanciasSeleccionadas->count(),
            'ids' => $instanciasSeleccionadas->pluck('id')->toArray()
        ]);

        // 3. Crear mapa de muestras a análisis seleccionados
        $mapaSelecciones = $this->crearMapaSelecciones($instanciasSeleccionadas);
        Log::debug('Mapa de selecciones creado', [
            'mapa' => array_keys($mapaSelecciones)
        ]);

        // 4. Actualizar muestras relacionadas
        $muestrasActualizadas = collect();
        foreach ($instanciasSeleccionadas as $instancia) {
            if ($instancia->cotio_subitem > 0) {
                $muestra = CotioInstancia::where([
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'instance_number' => $instancia->instance_number,
                    'cotio_subitem' => 0
                ])->first();

                if ($muestra && !$muestrasActualizadas->contains($muestra->id)) {
                    $muestra->active_ot = true;
                    $muestra->cotio_estado_analisis = 'coordinado analisis';
                    $muestra->coordinador_codigo_lab = Auth::user()->usu_codigo;
                    $muestra->save();
                    $muestrasActualizadas->push($muestra->id);
                    $updatedCount++;

                    Log::info('Muestra actualizada', [
                        'muestra_id' => $muestra->id,
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'instance_number' => $instancia->instance_number
                    ]);

                    if ($aplicarAGemelas) {
                        foreach ($muestra->gemelos() as $muestraGemela) {
                            $muestraGemela->active_ot = true;
                            $muestraGemela->cotio_estado_analisis = 'coordinado analisis';
                            $muestraGemela->save();
                            $updatedCount++;
                            Log::debug('Muestra gemela actualizada', [
                                'gemela_id' => $muestraGemela->id,
                                'cotio_numcoti' => $instancia->cotio_numcoti,
                                'cotio_item' => $instancia->cotio_item,
                                'instance_number' => $instancia->instance_number
                            ]);
                        }
                    }
                }
            }
        }

        Log::info('Muestras actualizadas', [
            'count' => $muestrasActualizadas->count(),
            'ids' => $muestrasActualizadas->toArray()
        ]);

        // 5. Procesar cada instancia seleccionada
        foreach ($instanciasSeleccionadas as $instancia) {
            $esAnalisisSeleccionado = $this->esInstanciaSeleccionada(
                $instancia,
                $instanciaSelecciones,
                $tareaSelecciones
            );

            $countBefore = $updatedCount;
            $updatedCount += $this->procesarInstancia(
                $instancia,
                $validated,
                $herramientasLab,
                $usuariosASincronizar,
                $esAnalisisSeleccionado,
                $mapaSelecciones
            );

            if ($updatedCount > $countBefore) {
                Log::debug('Instancia procesada', [
                    'instancia_id' => $instancia->id,
                    'es_analisis' => $esAnalisisSeleccionado,
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $instancia->cotio_subitem
                ]);
            }

            if ($aplicarAGemelas) {
                foreach ($instancia->gemelos() as $gemelo) {
                    $countBeforeGemela = $updatedCount;
                    $updatedCount += $this->procesarInstanciaGemela(
                        $gemelo,
                        $validated,
                        $herramientasLab,
                        $usuariosASincronizar,
                        $mapaSelecciones,
                        $instancia
                    );

                    if ($updatedCount > $countBeforeGemela) {
                        Log::debug('Instancia gemela procesada', [
                            'gemela_id' => $gemelo->id,
                            'cotio_numcoti' => $gemelo->cotio_numcoti,
                            'cotio_item' => $gemelo->cotio_item,
                            'cotio_subitem' => $gemelo->cotio_subitem
                        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'La instancia no está en estado analizado.',
            ], 400);
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
            'valoresVariables' => function($query) {
                $query->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad',
                    'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo'
        ])->findOrFail($instancia_id);

        // Obtener todos los análisis de esta muestra (cotio_subitem > 0)
        $analisis = CotioInstancia::with(['responsablesAnalisis'])
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('instance_number', $instancia->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_subitem')
            ->get();

        // Obtener las descripciones de los análisis desde la tabla cotio
        foreach ($analisis as $analisisItem) {
            $cotio = \App\Models\Cotio::where('cotio_numcoti', $analisisItem->cotio_numcoti)
                ->where('cotio_item', $analisisItem->cotio_item)
                ->where('cotio_subitem', $analisisItem->cotio_subitem)
                ->first();
            
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

        DB::beginTransaction();

        $instancia->aprobado_informe = true;
        $instancia->fecha_aprobacion_informe = now();
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
 * Generar contenido HTML del informe
 */
private function generarContenidoInforme($instancia, $analisis)
{
    $cotizacion = $instancia->cotizacion;
    $matriz = $cotizacion->matriz;
    
    $html = '<div class="informe-preliminar">';
    
    // Encabezado del informe
    $html .= '<div class="mb-4">';
    $html .= '<h4 class="text-primary mb-3">📋 Informe de Análisis</h4>';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<p><strong>Cotización:</strong> ' . $cotizacion->coti_num . '</p>';
    $html .= '<p><strong>Empresa:</strong> ' . $cotizacion->coti_empresa . '</p>';
    $html .= '<p><strong>Establecimiento:</strong> ' . $cotizacion->coti_establecimiento . '</p>';
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
    $html .= '<p><strong>O.T.N:</strong> #' . $instancia->otn ?? 'N/A' . '</p>';
    $html .= '</div>';
    $html .= '</div>';
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
            
            // Información del análisis
            $html .= '<div class="row mb-3">';
            $html .= '<div class="col-md-6">';
            $html .= '<small class="text-muted"><strong>Fechas:</strong></small><br>';
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
            $html .= '<div class="col-md-6">';
            
            // Responsables del análisis
            if ($tarea->responsablesAnalisis && $tarea->responsablesAnalisis->count() > 0) {
                $html .= '<small class="text-muted"><strong>👥 Responsables:</strong></small><br>';
                foreach ($tarea->responsablesAnalisis as $responsable) {
                    $html .= '<span class="badge bg-primary me-1 mb-1">' . htmlspecialchars($responsable->usu_descripcion) . '</span>';
                }
            } else {
                $html .= '<small class="text-muted">👥 <strong>Responsables:</strong> Sin asignar</small>';
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
            
            // Tabla de resultados
            $resultados = [
                ['tipo' => '🔬 Resultado Primario', 'valor' => $tarea->resultado, 'obs' => $tarea->observacion_resultado, 'fecha' => $tarea->fecha_carga_resultado_1],
                ['tipo' => '🔬 Resultado Secundario', 'valor' => $tarea->resultado_2, 'obs' => $tarea->observacion_resultado_2, 'fecha' => $tarea->fecha_carga_resultado_2],
                ['tipo' => '🔬 Resultado Terciario', 'valor' => $tarea->resultado_3, 'obs' => $tarea->observacion_resultado_3, 'fecha' => $tarea->fecha_carga_resultado_3],
                ['tipo' => '🏆 Resultado Final', 'valor' => $tarea->resultado_final, 'obs' => $tarea->observacion_resultado_final, 'fecha' => $tarea->fecha_carga_ot]
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
                $html .= '<thead class="table-dark"><tr><th>Tipo de Resultado</th><th>Valor</th><th>Observaciones</th><th>Fecha de Carga</th></tr></thead>';
                $html .= '<tbody>';
                
                foreach ($resultados as $resultado) {
                    if (!empty($resultado['valor'])) {
                        $html .= '<tr>';
                        $html .= '<td><strong>' . $resultado['tipo'] . '</strong></td>';
                        $html .= '<td class="fw-bold text-primary">' . htmlspecialchars($resultado['valor']) . '</td>';
                        $html .= '<td>' . htmlspecialchars($resultado['obs'] ?? 'Sin observaciones') . '</td>';
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
    
    $html .= '</div>'; // Cierre del div principal
    
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

            // Base query para muestra principal + análisis asociados
            $baseQuery = CotioInstancia::where($params)
                ->where(function($query) use ($request) {
                    $query->where('cotio_subitem', $request->cotio_subitem)
                          ->orWhere('cotio_subitem', '>', 0);
                });

            // Verificar si existen registros a afectar
            $total = (clone $baseQuery)->count();
            if ($total === 0) {
                return redirect()->back()->with('info', 'No hay muestras o análisis activos para finalizar.');
            }

            // Actualizar solo pendientes (toggle efectivo)
            $updatedCount = (clone $baseQuery)
                ->where(function ($q) {
                    $q->whereNull('cotio_estado_analisis')
                      ->orWhere('cotio_estado_analisis', '!=', 'analizado');
                })
                ->update(['cotio_estado_analisis' => 'analizado']);

            if ($updatedCount === 0) {
                return redirect()->back()->with('info', 'Todas las muestras y análisis ya se encontraban finalizados.');
            }

            return redirect()->back()->with('success', 'Se finalizaron correctamente ' . $updatedCount . ' registros.');

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

        $vehiculoAsignado = $item->vehiculo_asignado;

        if(Auth::user()->rol == 'coordinador_lab' || Auth::user()->usu_nivel >= '900') {
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

            $responsables = $instanciaAnalisis->responsablesAnalisis()
                ->get()
                ->map(function($responsable) {
                    return trim($responsable->usu_codigo); // Quitar espacios para la respuesta
                })
                ->toArray();

            return response()->json([
                'success' => true,
                'responsables' => $responsables
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
            $nuevosResponsables = $validated['responsables_analisis'] ?? [];
            
            // Validar que si hay responsables, no estén vacíos
            $nuevosResponsables = array_filter($nuevosResponsables, function($responsable) {
                return !empty(trim($responsable));
            });

            // Obtener responsables actuales del análisis específico
            $responsablesActualesAnalisis = $instanciaAnalisis->responsablesAnalisis()
                ->get()
                ->map(function($responsable) {
                    return trim($responsable->usu_codigo); // Normalizar quitando espacios
                })
                ->toArray();

            // Combinar responsables actuales con los nuevos (sin duplicados, comparando sin espacios)
            $todosLosResponsables = array_merge($responsablesActualesAnalisis, $nuevosResponsables);
            $responsablesFinales = array_unique(array_map('trim', $todosLosResponsables));

            // Buscar los códigos exactos en la base de datos para el sync
            $usuariosExactos = User::whereIn('usu_codigo', function($query) use ($responsablesFinales) {
                $query->select('usu_codigo')->from('usu');
                foreach ($responsablesFinales as $codigo) {
                    $query->orWhere('usu_codigo', 'LIKE', trim($codigo) . '%');
                }
            })->get();

            $codigosExactos = $usuariosExactos->pluck('usu_codigo')->toArray();

            Log::info('Editando responsables de análisis específico', [
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number'],
                'responsables_recibidos' => $validated['responsables_analisis'] ?? 'null',
                'responsables_actuales' => $responsablesActualesAnalisis,
                'nuevos_responsables' => $nuevosResponsables,
                'responsables_finales_trimmed' => $responsablesFinales,
                'codigos_exactos_bd' => $codigosExactos,
                'instancia_id' => $instanciaAnalisis->id
            ]);

            // Actualizar responsables del análisis específico usando códigos exactos
            $instanciaAnalisis->responsablesAnalisis()->sync($codigosExactos);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Responsables agregados correctamente al análisis.',
                'debug' => [
                    'responsables_anteriores' => $responsablesActualesAnalisis,
                    'nuevos_responsables' => $nuevosResponsables,
                    'responsables_finales_trimmed' => $responsablesFinales,
                    'codigos_exactos_usados' => $codigosExactos
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
        try {
            $validated = $request->validate([
                'cotio_item' => 'required',
                'cotio_subitem' => 'required',
                'instance_number' => 'required',
                'responsable_codigo' => 'required|exists:usu,usu_codigo'
            ]);

            Log::info('DEBUG - Iniciando quitarResponsable', [
                'datos_recibidos' => $validated,
                'cotio_numcoti' => $cotio_numcoti
            ]);

            DB::beginTransaction();

            // Obtener el análisis específico
            $instanciaAnalisis = CotioInstancia::where([
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => $validated['cotio_subitem'],
                'instance_number' => $validated['instance_number']
            ])->first();

            Log::info('DEBUG - Búsqueda de instancia', [
                'instancia_encontrada' => $instanciaAnalisis ? true : false,
                'instancia_id' => $instanciaAnalisis ? $instanciaAnalisis->id : null,
                'criterios_busqueda' => [
                    'cotio_numcoti' => $cotio_numcoti,
                    'cotio_item' => $validated['cotio_item'],
                    'cotio_subitem' => $validated['cotio_subitem'],
                    'instance_number' => $validated['instance_number']
                ]
            ]);

            if (!$instanciaAnalisis) {
                throw new \Exception('Análisis no encontrado');
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
        $instancia->request_review = false;
        $instancia->observaciones_request_review = null;
        $instancia->save();
        return response()->json(['success' => true]);
    }

    public function finalizarAnalisisSeleccionados(Request $request)
    {
        try {
            $request->validate([
                'instancia_ids' => 'required|array',
                'instancia_ids.*' => 'required|integer|exists:cotio_instancias,id'
            ]);

            $instanciaIds = $request->instancia_ids;

            // Verificar que las instancias estén en OT y no estén ya finalizadas
            // Solo trabajar con análisis (cotio_subitem > 0), no con muestras
            $instancias = CotioInstancia::whereIn('id', $instanciaIds)
                ->where('active_ot', true)
                ->where('cotio_subitem', '>', 0) // Solo análisis, no muestras
                ->where(function ($q) {
                    $q->whereNull('cotio_estado_analisis')
                    ->orWhere('cotio_estado_analisis', '!=', 'analizado');
                })
                ->get();

            if ($instancias->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay análisis válidos para finalizar. Puede que ya estén finalizados o no estén en OT.'
                ], 400);
            }

            // Actualizar estado a 'analizado'
            $updatedCount = CotioInstancia::whereIn('id', $instancias->pluck('id'))
                ->update(['cotio_estado_analisis' => 'analizado']);

            return response()->json([
                'success' => true,
                'message' => "Se finalizaron correctamente {$updatedCount} análisis."
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

            // Buscar los códigos exactos en la base de datos
            $usuariosExactos = User::whereIn('usu_codigo', $responsablesAnalisis)->get();
            $codigosExactos = $usuariosExactos->pluck('usu_codigo')->toArray();

            if (empty($codigosExactos)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron responsables válidos.'
                ], 400);
            }

            DB::beginTransaction();

            $updatedCount = 0;
            foreach ($instancias as $instancia) {
                // Obtener responsables actuales
                $responsablesActuales = $instancia->responsablesAnalisis()
                    ->get()
                    ->map(function($responsable) {
                        return trim($responsable->usu_codigo);
                    })
                    ->toArray();

                // Combinar responsables actuales con los nuevos (sin duplicados)
                $todosLosResponsables = array_merge($responsablesActuales, $codigosExactos);
                $responsablesFinales = array_unique(array_map('trim', $todosLosResponsables));

                // Buscar códigos exactos para el sync
                $codigosFinales = User::whereIn('usu_codigo', $responsablesFinales)
                    ->pluck('usu_codigo')
                    ->toArray();

                // Sincronizar responsables
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
}