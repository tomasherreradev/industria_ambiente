<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CotioInstancia;
use App\Models\Coti;
use App\Models\Cotio;
use App\Models\Matriz;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InventarioMuestreo;
use App\Models\Vehiculo;
use App\Models\CotioInventarioMuestreo;
use App\Http\Controllers\CotioController;
use App\Models\VariableRequerida;
use App\Models\CotioValorVariable;
use App\Models\CotioHistorialCambios;
use App\Models\InstanciaResponsableMuestreo;


class MuestrasController extends Controller {

protected $cotioController;

/**
 * Determina la prioridad numérica de un estado de muestra.
 * Menor número = mayor prioridad en el ordenamiento.
 */
private function getEstadoPriority($cotio_estado, $es_priori = false)
{
    $estado = strtolower(trim($cotio_estado ?? ''));
    
    // 1. Grupos con al menos una muestra prioritaria distinta de "muestreado"
    if ($es_priori && $estado != 'muestreado') {
        return 1;
    }
    
    // 2. Grupos con al menos una muestra en suspensión
    if ($estado == 'suspension') {
        return 2;
    }
    
    // 3. Grupos con al menos una muestra inexistente (null o vacío)
    if (empty($estado)) {
        return 3;
    }
    
    // 4. Grupos con muestras en revisión de muestreo (turquesa)
    if ($estado == 'en revision muestreo') {
        return 4;
    }
    
    // 5. Grupos con muestras coordinado muestreo (amarillas)
    if ($estado == 'coordinado muestreo') {
        return 5;
    }
    
    // 6. Grupos donde todas las muestras están muestreadas (verdes)
    if ($estado == 'muestreado') {
        return 6;
    }
    
    // Estados no reconocidos van al final
    return 7;
}

/**
 * Determina el estado de mayor jerarquía de un grupo de muestras.
 * Retorna la prioridad más alta (número más bajo) encontrada en el grupo.
 */
private function getGrupoMaxPriority($instancias)
{
    $maxPriority = 7; // Valor por defecto (menor prioridad)
    
    foreach ($instancias as $instancia) {
        $priority = $this->getEstadoPriority(
            $instancia->cotio_estado ?? null, 
            $instancia->es_priori ?? false
        );
        
        // Si encontramos una prioridad mayor (número menor), la usamos
        if ($priority < $maxPriority) {
            $maxPriority = $priority;
        }
        
        // Si ya encontramos la máxima prioridad posible, no necesitamos seguir
        if ($maxPriority === 1) {
            break;
        }
    }
    
    return $maxPriority;
}

public function __construct(CotioController $cotioController)
{
    $this->cotioController = $cotioController;
}

    
public function index(Request $request)
{
    $verTodas = $request->query('verTodas');
    $viewType = $request->get('view', 'lista');
    $matrices = Matriz::orderBy('matriz_descripcion')->get();
    $user = Auth::user();
    
    $muestras = collect();
    $userToView = $request->get('user_to_view');
    $viewTasks = $request->get('view_tasks', false);
    $usuarios = collect();

    $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
    $startOfWeek = $request->get('week') ? Carbon::parse($request->get('week')) : now()->startOfWeek();
    $endOfWeek = $startOfWeek->copy()->endOfWeek();

    if ($user->usu_nivel >= 900 && $viewType === 'calendario') {
        $usuarios = User::where('usu_estado', true)
            ->orderBy('usu_descripcion')
            ->get(['usu_codigo', 'usu_descripcion']);
    }

    if ($viewType === 'calendario') {
        // Caso especial para usuarios con nivel >= 900 que ven tareas de otro usuario
        if ($user->usu_nivel >= 900 && $viewTasks && $userToView) {
            return $this->showUserTasksCalendar($request, $userToView);
        }
        
        // Construcción de la consulta base para instancias
        $query = CotioInstancia::with([
            'cotizacion.matriz',
            'tarea',
            // Cargar todas las instancias de la misma cotización para verificar suspensiones y prioridades
            'cotizacion.instancias' => function ($q) {
                $q->select('id', 'cotio_numcoti', 'cotio_estado', 'es_priori');
            }
        ])
            ->where('cotio_subitem', 0) // Solo instancias de muestras originales
            ->whereNotNull('fecha_inicio_muestreo') // Solo instancias con fecha de muestreo
            // Filtro: solo cotizaciones con cadena_custodia, muestreo o ensayo de visita técnica
            ->where(function($q) {
                $q->whereHas('cotizacion', function($subQ) {
                    $subQ->where('coti_cadena_custodia', true)
                         ->orWhere('coti_muestreo', true);
                })
                ->orWhereHas('cotizacion.tareas', function($subQ) {
                    $subQ->where('cotio_subitem', 0)
                         ->where(function($subQ2) {
                             $subQ2->whereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%TRABAJO TECNICO%'")
                                   ->orWhereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%VISITA TECNICA%'");
                         });
                });
            });
        
        // Filtro por término de búsqueda
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%'.$request->search.'%';
            $query->whereHas('cotizacion', function($q) use ($searchTerm) {
                $q->where('coti_num', 'like', $searchTerm)
                    ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [strtolower($searchTerm)])
                    ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [strtolower($searchTerm)])
                    ->orWhereRaw('LOWER(coti_descripcion) LIKE ?', [strtolower($searchTerm)]);
            });
        }
        
        // Filtro por matriz
        if ($request->has('matriz') && !empty($request->matriz)) {
            $query->whereHas('cotizacion.matriz', function($q) use ($request) {
                $q->where('matriz_descripcion', 'like', '%'.$request->matriz.'%')
                    ->orWhere('matriz_codigo', $request->matriz);
            });
        }
        
        // Filtro por estado de la cotización
        if ($request->has('estado') && !empty($request->estado)) {
            $query->whereHas('cotizacion', function($q) use ($request) {
                $q->where('coti_estado', $request->estado);
            });
        } elseif (!$verTodas) {
            $query->whereHas('cotizacion', function($q) {
                $q->where('coti_estado', 'A');
            });
        }
        
        // Filtros por rango de fechas
        if ($request->has('fecha_inicio_muestreo') && !empty($request->fecha_inicio_muestreo)) {
            $query->whereDate('fecha_muestreo', '>=', $request->fecha_inicio_muestreo);
        }
        
        if ($request->has('fecha_fin_muestreo') && !empty($request->fecha_fin_muestreo)) {
            $query->whereDate('fecha_muestreo', '<=', $request->fecha_fin_muestreo);
        } else {
            // Si no hay fecha fin, mostrar por defecto el mes actual
            $query->whereBetween('fecha_muestreo', [
                $currentMonth->copy()->startOfMonth(),
                $currentMonth->copy()->endOfMonth()
            ]);
        }
        
        $query->orderBy('fecha_muestreo', 'asc');
        
        // Obtención de los resultados
        $instancias = $query->get();
        
        // Verificar suspensiones y prioridades para cada instancia
        $instancias->each(function ($instancia) {
            $hasSuspension = $instancia->cotizacion->instancias->contains(function ($relatedInstancia) {
                return strtolower(trim($relatedInstancia->cotio_estado)) === 'suspension';
            });
            
            $hasPriority = $instancia->cotizacion->instancias->contains(function ($relatedInstancia) {
                return $relatedInstancia->es_priori && strtolower(trim($relatedInstancia->cotio_estado ?? '')) !== 'muestreado';
            });
            
            $instancia->has_suspension = $hasSuspension;
            $instancia->has_priority = $hasPriority;
        });
        
        // Agrupamiento por fecha de muestreo
        $tareasCalendario = $instancias
            ->filter(fn($item) => !empty($item->fecha_muestreo))
            ->mapToGroups(function($instancia) {
                return [\Carbon\Carbon::parse($instancia->fecha_muestreo)->format('Y-m-d') => $instancia];
            })
            ->map(function($items) {
                return $items->sortBy('fecha_muestreo');
            });
        
        // Muestras sin fecha programada
        $unscheduled = $instancias->filter(fn($instancia) => empty($instancia->fecha_muestreo));
        if ($unscheduled->isNotEmpty()) {
            $tareasCalendario->put('sin-fecha', $unscheduled);
        }
        

        $events = collect();

        // Determinar si el usuario puede ver detalles de muestra (admin o coordinador_muestreo)
        $canViewMuestraDetails = $user->usu_nivel >= 900 || $user->hasRole('coordinador_muestreo');
        
        foreach ($tareasCalendario as $date => $instancias) {
            foreach ($instancias as $instancia) {
                // URL según permisos del usuario
                $eventUrl = $canViewMuestraDetails 
                    ? route('categoria.verMuestra', [
                        'cotizacion' => $instancia->cotio_numcoti,
                        'item' => $instancia->cotio_item,
                        'cotio_subitem' => $instancia->cotio_subitem,
                        'instance' => $instancia->instance_number
                    ])
                    : route('cotizaciones.ver-detalle', ['cotizacion' => $instancia->cotio_numcoti]);

                $events->push([
                    'title' => $instancia->cotizacion->coti_empresa . ' - ' . $instancia->cotio_numcoti,
                    'start' => $instancia->fecha_inicio_muestreo,
                    'end' => $instancia->fecha_fin_muestreo ?? null,
                    'url' => $eventUrl,
                    'extendedProps' => [
                        'empresa' => $instancia->cotizacion->coti_empresa,
                        'descripcion' => $instancia->cotizacion->coti_descripcion,
                        'estado' => $instancia->cotio_estado,
                        'analisis_count' => $instancia->analisis_count ?? 0,
                    ],
                    'className' => $this->getEventClass($instancia),
                ]);
            }
        }
        
        return view('muestras.index', [
            'events' => $events, 
            'muestras' => $tareasCalendario,
            'viewType' => $viewType,
            'request' => $request,
            'matrices' => $matrices,
            'userToView' => $userToView,
            'usuarios' => $usuarios,
            'viewTasks' => false,
            'currentMonth' => $currentMonth,
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek
        ]);
    }
    
    $query = Coti::with(['matriz', 'tareas.instancias' => function($q) {
        $q->where('cotio_subitem', 0);
    }, 'instancias' => function($q) {
        $q->where('cotio_subitem', 0);
    }])
    ->select('coti.*')
    ->leftJoin('cotio_instancias', function($join) {
        $join->on('coti.coti_num', '=', 'cotio_instancias.cotio_numcoti')
             ->where('cotio_instancias.cotio_subitem', 0);
    })
    ->groupBy('coti.coti_num')
    // Filtro: solo cotizaciones con cadena_custodia, muestreo o ensayo de visita técnica
    ->where(function($q) {
        $q->where('coti_cadena_custodia', true)
          ->orWhere('coti_muestreo', true)
          ->orWhereExists(function($subQ) {
              $subQ->select(DB::raw(1))
                   ->from('cotio')
                   ->whereColumn('cotio.cotio_numcoti', 'coti.coti_num')
                   ->where('cotio.cotio_subitem', 0)
                   ->where(function($subQ2) {
                       $subQ2->whereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%TRABAJO TECNICO%'")
                             ->orWhereRaw("UPPER(TRIM(cotio_descripcion)) LIKE '%VISITA TECNICA%'");
                   });
          });
    });

    // Filtros (se mantienen igual)
    if ($request->has('search') && !empty($request->search)) {
        $searchTerms = explode(' ', trim($request->search));
        
        $query->where(function($q) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                if (!empty(trim($term))) {
                    $likeTerm = '%'.strtolower($term).'%';
                    $termForNum = '%'.$term.'%';
                    $q->where(function($subQuery) use ($likeTerm, $termForNum) {
                        $subQuery->where('coti_num', 'LIKE', $termForNum)
                            ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [$likeTerm])
                            ->orWhereRaw('LOWER(coti_descripcion) LIKE ?', [$likeTerm]);
                    });
                }
            }
        });
    }
    
    if ($request->has('matriz') && !empty($request->matriz)) {
        $query->whereHas('matriz', function($q) use ($request) {
            $q->where('matriz_descripcion', 'like', '%'.$request->matriz.'%')
                ->orWhere('matriz_codigo', $request->matriz);
        });
    }
    
    if ($request->has('estado') && !empty($request->estado)) {
        $query->where('coti_estado', $request->estado);
    } elseif (!$verTodas) {
        $query->where('coti_estado', 'A');
    }
    
    if ($request->has('fecha_inicio_muestreo') && !empty($request->fecha_inicio_muestreo)) {
        $query->whereDate('coti_fechaalta', '>=', $request->fecha_inicio_muestreo);
    }

    if ($request->has('fecha_fin_muestreo') && !empty($request->fecha_fin_muestreo)) {
        $query->whereDate('coti_fechaalta', '<=', $request->fecha_fin_muestreo);
    }



    // Aplicar ordenamiento jerárquico optimizado a nivel de base de datos
    if (empty($request->fecha_inicio_muestreo) && empty($request->fecha_fin_muestreo)) {
        // Aplicar ordenamiento jerárquico considerando instancias faltantes como inexistentes
        $query->orderByRaw('(
            CASE 
                -- Verificar si faltan instancias por crear (total esperado > instancias creadas)
                WHEN (
                    SELECT COALESCE(SUM(cotio_cantidad), 0) 
                    FROM cotio 
                    WHERE cotio_numcoti = coti.coti_num 
                    AND cotio_subitem = 0
                    AND cotio_descripcion NOT IN (\'TRABAJO TECNICO EN CAMPO\', \'TRABAJOS EN CAMPO NOCTURNO - VIATICOS\', \'VIATICOS\')
                ) > (
                    SELECT COUNT(*) 
                    FROM cotio_instancias ci_count 
                    WHERE ci_count.cotio_numcoti = coti.coti_num 
                    AND ci_count.cotio_subitem = 0
                ) THEN 3 -- Prioridad 3: Inexistente (faltan instancias)
                
                -- Si todas las instancias existen, evaluar sus estados
                ELSE COALESCE((
                    SELECT MIN(
                        CASE 
                            -- 1. Prioritario no muestreado (mayor prioridad)
                            WHEN ci.es_priori = true AND LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) != \'muestreado\' THEN 1
                            -- 2. Suspensión
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'suspension\' THEN 2
                            -- 3. Inexistente (estado vacío o null)
                            WHEN ci.cotio_estado IS NULL OR TRIM(COALESCE(ci.cotio_estado, \'\')) = \'\' THEN 3
                            -- 4. En revisión muestreo
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'en revision muestreo\' THEN 4
                            -- 5. Coordinado muestreo
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'coordinado muestreo\' THEN 5
                            -- 6. Muestreado
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'muestreado\' THEN 6
                            -- Estados no reconocidos
                            ELSE 7
                        END
                    )
                    FROM cotio_instancias ci 
                    WHERE ci.cotio_numcoti = coti.coti_num 
                    AND ci.cotio_subitem = 0
                ), 3) -- Si no hay instancias, considera como inexistente
            END
        ) ASC'
        )
        // Ordenamiento secundario por fecha de aprobación (más antigua primero)
        ->orderBy('coti_fechaaprobado', 'asc');
        
        $muestras = $query->paginate(20)->appends($request->query());
    } else {
        // Si hay filtros de fecha, aplicar ordenamiento jerárquico considerando instancias faltantes
        $query->orderByRaw('(
            CASE 
                -- Verificar si faltan instancias por crear (total esperado > instancias creadas)
                WHEN (
                    SELECT COALESCE(SUM(cotio_cantidad), 0) 
                    FROM cotio 
                    WHERE cotio_numcoti = coti.coti_num 
                    AND cotio_subitem = 0
                    AND cotio_descripcion NOT IN (\'TRABAJO TECNICO EN CAMPO\', \'TRABAJOS EN CAMPO NOCTURNO - VIATICOS\', \'VIATICOS\')
                ) > (
                    SELECT COUNT(*) 
                    FROM cotio_instancias ci_count 
                    WHERE ci_count.cotio_numcoti = coti.coti_num 
                    AND ci_count.cotio_subitem = 0
                ) THEN 3 -- Prioridad 3: Inexistente (faltan instancias)
                
                -- Si todas las instancias existen, evaluar sus estados
                ELSE COALESCE((
                    SELECT MIN(
                        CASE 
                            -- 1. Prioritario no muestreado (mayor prioridad)
                            WHEN ci.es_priori = true AND LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) != \'muestreado\' THEN 1
                            -- 2. Suspensión
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'suspension\' THEN 2
                            -- 3. Inexistente (estado vacío o null)
                            WHEN ci.cotio_estado IS NULL OR TRIM(COALESCE(ci.cotio_estado, \'\')) = \'\' THEN 3
                            -- 4. En revisión muestreo
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'en revision muestreo\' THEN 4
                            -- 5. Coordinado muestreo
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'coordinado muestreo\' THEN 5
                            -- 6. Muestreado
                            WHEN LOWER(TRIM(COALESCE(ci.cotio_estado, \'\'))) = \'muestreado\' THEN 6
                            -- Estados no reconocidos
                            ELSE 7
                        END
                    )
                    FROM cotio_instancias ci 
                    WHERE ci.cotio_numcoti = coti.coti_num 
                    AND ci.cotio_subitem = 0
                ), 3) -- Si no hay instancias, considera como inexistente
            END
        ) ASC'
        )
        // Con filtros de fecha, ordenar por fecha como criterio secundario
        ->orderBy('coti_fechaalta', 'desc');
        
        $muestras = $query->paginate(20)->appends($request->query());
    }

    // Procesamiento de las muestras (se mantiene igual)
    $muestras->each(function($coti) {
        $muestrasOriginales = $coti->tareas->where('cotio_subitem', 0)
            ->reject(function ($tarea) {
                $descripcion = trim($tarea->cotio_descripcion);
                return in_array($descripcion, [
                    'TRABAJO TECNICO EN CAMPO',
                    'TRABAJOS EN CAMPO NOCTURNO - VIATICOS',
                    'VIATICOS'
                ]);
            });
    
        $totalInstancias = $muestrasOriginales->sum('cotio_cantidad');
        $instancias = $coti->instancias->where('cotio_subitem', 0);
        
        $muestreadas = $instancias->filter(function($instancia) {
            return strtolower(trim($instancia->cotio_estado ?? '')) === 'muestreado';
        })->count();
        
        $enRevision = $instancias->filter(function($instancia) {
            return strtolower(trim($instancia->cotio_estado ?? '')) === 'en revision muestreo';
        })->count();
        
        $coordinadas = $instancias->filter(function($instancia) {
            return strtolower(trim($instancia->cotio_estado ?? '')) === 'coordinado muestreo';
        })->count();
    
        $hasSuspension = $instancias->contains(function ($instancia) {
            return strtolower(trim($instancia->cotio_estado ?? '')) === 'suspension';
        });

        $hasPriority = $instancias->contains(function ($instancia) {
            return $instancia->es_priori && strtolower(trim($instancia->cotio_estado ?? '')) !== 'muestreado';
        });
        
        $porcentajes = [
            'muestreadas' => $totalInstancias > 0 ? ($muestreadas / $totalInstancias) * 100 : 0,
            'en_revision' => $totalInstancias > 0 ? ($enRevision / $totalInstancias) * 100 : 0,
            'coordinadas' => $totalInstancias > 0 ? ($coordinadas / $totalInstancias) * 100 : 0,
            'total' => $totalInstancias > 0 ? (($muestreadas + $enRevision + $coordinadas) / $totalInstancias) * 100 : 0
        ];
    
        $coti->total_instancias = $totalInstancias;
        $coti->instancias_completadas = $muestreadas + $enRevision + $coordinadas;
        $coti->porcentaje_progreso = $porcentajes;
        $coti->has_suspension = $hasSuspension;
        $coti->has_priority = $hasPriority;
    });
    
    return view('muestras.index', [
        'muestras' => $muestras,
        'viewType' => $viewType,
        'request' => $request,
        'matrices' => $matrices,
        'userToView' => $userToView,
        'usuarios' => $usuarios,
        'viewTasks' => false
    ]);
}


protected function showUserTasksCalendar(Request $request, $userCode)
{
    $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
    $startOfWeek = $request->get('week') ? Carbon::parse($request->get('week')) : now()->startOfWeek();
    $endOfWeek = $startOfWeek->copy()->endOfWeek();
    
    $query = CotioInstancia::with(['cotizacion.matriz', 'tarea'])
        ->where('cotio_subitem', 0);
    
    if ($request->has('search') && !empty($request->search)) {
        $searchTerm = '%'.$request->search.'%';
        $query->whereHas('cotizacion', function($q) use ($searchTerm) {
            $q->where('coti_num', 'LIKE', $searchTerm)
                ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [strtolower($searchTerm)])
                ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [strtolower($searchTerm)]);
        });
    }
    
    if ($request->has('matriz') && !empty($request->matriz)) {
        $query->whereHas('cotizacion.matriz', function($q) use ($request) {
            $q->where('matriz_descripcion', 'like', '%'.$request->matriz.'%')
                ->orWhere('matriz_codigo', $request->matriz);
        });
    }
    
    if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
        $query->whereDate('fecha_muestreo', '>=', $request->fecha_inicio);
    }
    
    if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
        $query->whereDate('fecha_muestreo', '<=', $request->fecha_fin);
    } else {
        $query->whereBetween('fecha_muestreo', [
            $currentMonth->copy()->startOfMonth(),
            $currentMonth->copy()->endOfMonth()
        ]);
    }
    
    $query->orderBy('fecha_muestreo', 'asc');
    
    $instancias = $query->get();
    
    $tareasCalendario = $instancias
        ->filter(fn($instancia) => !empty($instancia->fecha_muestreo))
        ->mapToGroups(function($instancia) {
            return [\Carbon\Carbon::parse($instancia->fecha_muestreo)->format('Y-m-d') => $instancia];
        })
        ->map(function($items) {
            return $items->sortBy('fecha_muestreo');
        });
    
    $unscheduled = $instancias->filter(fn($instancia) => empty($instancia->fecha_muestreo));
    if ($unscheduled->isNotEmpty()) {
        $tareasCalendario->put('sin-fecha', $unscheduled);
    }

    // Generar eventos para el calendario
    $user = Auth::user();
    $canViewMuestraDetails = $user->usu_nivel >= 900 || $user->hasRole('coordinador_muestreo');
    
    $events = collect();
    foreach ($tareasCalendario as $date => $instanciasGrupo) {
        foreach ($instanciasGrupo as $instancia) {
            // URL según permisos del usuario
            $eventUrl = $canViewMuestraDetails 
                ? route('categoria.verMuestra', [
                    'cotizacion' => $instancia->cotio_numcoti,
                    'item' => $instancia->cotio_item,
                    'cotio_subitem' => $instancia->cotio_subitem,
                    'instance' => $instancia->instance_number
                ])
                : route('cotizaciones.ver-detalle', ['cotizacion' => $instancia->cotio_numcoti]);

            $events->push([
                'title' => ($instancia->cotizacion->coti_empresa ?? 'Sin empresa') . ' - ' . $instancia->cotio_numcoti,
                'start' => $instancia->fecha_inicio_muestreo ?? $instancia->fecha_muestreo,
                'end' => $instancia->fecha_fin_muestreo ?? null,
                'url' => $eventUrl,
                'extendedProps' => [
                    'empresa' => $instancia->cotizacion->coti_empresa ?? 'Sin empresa',
                    'descripcion' => $instancia->cotizacion->coti_descripcion ?? '',
                    'estado' => $instancia->cotio_estado,
                    'analisis_count' => $instancia->analisis_count ?? 0,
                ],
                'className' => $this->getEventClass($instancia),
            ]);
        }
    }
    
    return view('muestras.partials.calendario', [
        'tareasCalendario' => $tareasCalendario,
        'events' => $events,
        'cotizaciones' => collect(),
        'viewType' => 'calendario',
        'request' => $request,
        'matrices' => Matriz::orderBy('matriz_descripcion')->get(),
        'userToView' => User::where('usu_codigo', $userCode)->value('usu_descripcion') ?? $userCode,
        'usuarios' => User::where('usu_estado', true)
                        ->orderBy('usu_descripcion')
                        ->get(['usu_codigo', 'usu_descripcion']),
        'viewTasks' => true,
        'currentMonth' => $currentMonth,
        'startOfWeek' => $startOfWeek,
        'endOfWeek' => $endOfWeek
    ]);
}



protected function getEventClass($instancia)
{
    switch (strtolower($instancia->cotio_estado)) {
        case 'coordinado muestreo': return 'fc-event-warning';
        case 'en revision muestreo': return 'fc-event-info';
        case 'muestreado': return 'fc-event-success';
        default: return 'fc-event-primary';
    }
}




public function removerResponsable(Request $request)
{
    $validated = $request->validate([
        'instancia_id' => 'required|integer|exists:cotio_instancias,id',
        'user_codigo' => 'required|string|exists:usu,usu_codigo',
        'todos' => 'required|string|in:true,false' // Validar como string primero
    ]);

    try {
        DB::beginTransaction();

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);
        $userCodigo = $validated['user_codigo'];

        // Convertir el string 'true'/'false' a booleano
        $todos = $validated['todos'] === 'true';

        if ($todos) {
            $instancias = CotioInstancia::where([
                'cotio_numcoti' => $instancia->cotio_numcoti,
                'cotio_item' => $instancia->cotio_item,
                'instance_number' => $instancia->instance_number,
            ])->get();

            $totalEliminados = 0;
            foreach ($instancias as $inst) {
                // Eliminar de muestreo
                $deletedMuestreo = DB::table('instancia_responsable_muestreo')
                    ->where('cotio_instancia_id', $inst->id)
                    ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                    ->delete();
                
                // También eliminar de análisis por si está ahí
                $deletedAnalisis = DB::table('instancia_responsable_analisis')
                    ->where('cotio_instancia_id', $inst->id)
                    ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                    ->delete();
                
                $totalEliminados += $deletedMuestreo + $deletedAnalisis;
            }
        } else {
            // Eliminar de muestreo
            $deletedMuestreo = DB::table('instancia_responsable_muestreo')
                ->where('cotio_instancia_id', $instancia->id)
                ->whereRaw('TRIM(usu_codigo) = ?', [$userCodigo])
                ->delete();
            
            // También eliminar de análisis por si está ahí
            $deletedAnalisis = DB::table('instancia_responsable_analisis')
                ->where('cotio_instancia_id', $instancia->id)
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


public function show($coti_num)
{
    $cotizacion = Coti::findOrFail($coti_num);
    $inventario = InventarioMuestreo::all();

    // Regla de vehículo único por TIPO de muestra (mismo cotio_descripcion):
    // Por cada tipo (descripción, ej. "EMISIONES GASEOSAS"), si ya hay una instancia coordinada con vehículo asignado,
    // las demás muestras de ese mismo tipo solo pueden usar ese mismo vehículo.
    $vehiculoFijadoPorTipo = CotioInstancia::where('cotio_numcoti', $coti_num)
        ->where('cotio_subitem', 0)
        ->whereIn('cotio_estado', ['coordinado muestreo', 'muestreado'])
        ->whereNotNull('vehiculo_asignado')
        ->get()
        ->filter(fn ($i) => trim((string) ($i->cotio_descripcion ?? '')) !== '')
        ->groupBy(fn ($i) => trim((string) $i->cotio_descripcion))
        ->map(fn ($g) => (int) $g->first()->vehiculo_asignado)
        ->toArray();

    $vehiculos = Vehiculo::all();

    // Lista de tareas que no requieren muestreo
    $descripcionesNoRequierenMuestreo = [
        'Prestacion de Hora Hombre Profesional',
        'Certificado de Cadena de Custodia y Prot. Of. - Res. 41/14',
        'Modelo de Disp. Etapa 2 - SCREEN 3',
        'TRABAJO TECNICO EN CAMPO',
        'TRABAJOS EN CAMPO NOCTURNO - VIATICOS',
        'TRABAJO EN CAMPO DIURNO - VIATICOS',
        'TRABAJO EN CAMPO - VIATICOS',
        'CONSULTORIA SEGURIDAD E HIGIENE EN OBRAS',
        'Programa de Seguridad en Obra - Dto 911/96',
    ];

    // Cargar tareas con relaciones necesarias
    $tareas = $cotizacion->tareas()
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();

    // Obtener variables requeridas por tipo de muestra
    $tiposMuestra = $tareas->pluck('cotio_descripcion')->unique()->toArray();
    $variablesRequeridas = VariableRequerida::whereIn('cotio_descripcion', $tiposMuestra)
        ->get()
        ->groupBy('cotio_descripcion')
        ->mapWithKeys(function ($variables, $tipoMuestra) {
            return [$tipoMuestra => $variables->pluck('nombre', 'id')->toArray()];
        });

    // Cargar instancias existentes con sus responsables
    $instanciasExistentes = CotioInstancia::where('cotio_numcoti', $coti_num)
                        ->with(['responsablesMuestreo']) // Cargar la relación
                        ->get()
                        ->groupBy(['cotio_item', 'cotio_subitem', 'instance_number']);

    // Obtener usuarios muestreadores
    $usuarios = User::withCount(['tareas' => function($query) use ($coti_num) {
                    $query->where('cotio_numcoti', $coti_num);
                }])
                ->where('usu_nivel', '<=', 500)
                ->orderBy('usu_descripcion')
                ->get();

    $agrupadas = [];

    foreach ($tareas as $tarea) {
        if ($tarea->cotio_subitem == 0) { // Es una muestra
            $cantidad = $tarea->cotio_cantidad ?: 1;

            for ($i = 1; $i <= $cantidad; $i++) {
                $instancia = $this->getOrCreateInstancia(
                    $tarea->cotio_numcoti,
                    $tarea->cotio_item,
                    0, // subitem 0 para muestras
                    $i,
                    $instanciasExistentes
                );

                $analisisMuestra = $this->getAnalisisForMuestra($tareas, $tarea->cotio_item, $i, $instanciasExistentes);

                $requiereMuestreo = false;

                foreach ($analisisMuestra as $subtarea) {
                    foreach ($descripcionesNoRequierenMuestreo as $descNoReq) {
                        if (stripos(trim(strtolower($subtarea->cotio_descripcion)), strtolower($descNoReq)) !== false) {
                            $requiereMuestreo = true;
                            break 2; 
                        }
                    }
                }
                

                $agrupadas[] = [
                    'categoria' => (object) array_merge($tarea->toArray(), [
                        'instance_number' => $i,
                        'original_item' => $tarea->cotio_item,
                        'display_item' => $tarea->cotio_item . '-' . $i,
                        'requiere_muestreo' => $requiereMuestreo,
                        'enable_ot' => $instancia->enable_ot ?? false, // Usar el valor de la instancia
                    ]),
                    'instancia' => $instancia,
                    'tareas' => $analisisMuestra,
                    'responsables' => $instancia->responsablesMuestreo
                ];
            }
        }
    }

    return view('muestras.show', compact(
        'cotizacion',
        'tareas',
        'usuarios',
        'agrupadas',
        'inventario',
        'vehiculos',
        'vehiculoFijadoPorTipo',
        'variablesRequeridas'
    ));
}


public function pasarDirectoAOT(Request $request)
{
    $cotio_numcoti = $request->input('cotio_numcoti');
    $cotio_item = $request->input('cotio_item');
    $instance_number = $request->input('instance_number');

    // 1. Obtener muestra (subitem 0)
    $muestra = Cotio::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', 0)
        ->first();

    if (!$muestra) {
        return response()->json([
            'success' => false,
            'message' => 'Muestra no encontrada'
        ], 404);
    }

    // 2. Obtener análisis (subitems > 0)
    $analisis = Cotio::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', '>', 0)
        ->get();

    // 3. Verificar si ya existe instancia de la muestra
    $existeInstancia = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', 0)
        ->where('instance_number', $instance_number)
        ->exists();

    if ($existeInstancia) {
        // Actualizar todas las instancias existentes y copiar métodos desde Cotio
        $instancias = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
            ->where('cotio_item', $cotio_item)
            ->where('instance_number', $instance_number)
            ->get();
        
        // Obtener la instancia de la muestra para asignar número OT si no tiene
        $instanciaMuestra = $instancias->firstWhere('cotio_subitem', 0);
        $numeroOT = null;
        
        // Solo generar número OT si es una muestra y no tiene uno ya asignado
        if ($instanciaMuestra && !$instanciaMuestra->otn) {
            $numeroOT = CotioInstancia::generarNumeroOT();
        }
        
        foreach ($instancias as $instancia) {
            // Obtener datos de Cotio para copiar métodos
            $cotio = Cotio::where('cotio_numcoti', $cotio_numcoti)
                ->where('cotio_item', $cotio_item)
                ->where('cotio_subitem', $instancia->cotio_subitem)
                ->first();
            
            $updateData = ['enable_ot' => true];
            
            // Asignar número OT solo a la muestra (cotio_subitem = 0)
            if ($instancia->cotio_subitem == 0 && $numeroOT) {
                $updateData['otn'] = $numeroOT;
            }
            
            // Copiar ambos métodos desde Cotio si están disponibles
            if ($cotio) {
                if ($cotio->cotio_codigometodo) {
                    $updateData['cotio_codigometodo'] = $cotio->cotio_codigometodo;
                }
                if ($cotio->cotio_codigometodo_analisis) {
                    $updateData['cotio_codigometodo_analisis'] = $cotio->cotio_codigometodo_analisis;
                }
            }
            
            $instancia->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Instancias actualizadas correctamente'
        ]);
    }

    // 4. Generar número OT para la muestra
    $numeroOT = CotioInstancia::generarNumeroOT();
    
    // 5. Crear nueva instancia para la muestra (copiar ambos métodos desde Cotio)
    CotioInstancia::create([
        'cotio_numcoti' => $muestra->cotio_numcoti,
        'cotio_item' => $muestra->cotio_item,
        'cotio_subitem' => $muestra->cotio_subitem,
        'cotio_descripcion' => $muestra->cotio_descripcion,
        'cotio_codigometodo' => $muestra->cotio_codigometodo, // Método de muestreo
        'cotio_codigometodo_analisis' => $muestra->cotio_codigometodo_analisis, // Método de análisis
        'instance_number' => $instance_number,
        'cotio_estado' => null, 
        'enable_ot' => true,
        'otn' => $numeroOT // Asignar número OT solo a la muestra
    ]);

    // 6. Crear nuevas instancias para cada análisis (copiar ambos métodos desde Cotio)
    // NOTA: Los análisis NO reciben número OT, solo las muestras
    foreach ($analisis as $a) {
        CotioInstancia::create([
            'cotio_numcoti' => $a->cotio_numcoti,
            'cotio_item' => $a->cotio_item,
            'cotio_subitem' => $a->cotio_subitem,
            'cotio_descripcion' => $a->cotio_descripcion,
            'cotio_codigometodo' => $a->cotio_codigometodo, // Método de muestreo
            'cotio_codigometodo_analisis' => $a->cotio_codigometodo_analisis, // Método de análisis
            'instance_number' => $instance_number,
            'cotio_estado' => null,
            'enable_ot' => true
            // No se asigna otn a los análisis
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Instancias creadas correctamente'
    ]);
}



public function quitarDirectoAOT($cotio_numcoti, $cotio_item, $instance_number, $isFromCoordinador = false)
{

    // Verificar si alguna instancia tiene active_ot = true
    $instanciaActiva = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('active_ot', true)
        ->exists();

    if ($instanciaActiva) {
        return response()->json([
            'success' => false,
            'message' => 'La muestra está activa en OT, no se puede realizar la acción',
            'active_ot' => false
        ], 400);
    }

    // Eliminar instancias correspondientes (muestra + análisis)
    CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->update([
            'enable_ot' => false,
            'cotio_estado_analisis' => null,
            'active_ot' => false
        ]);

    return response()->json([
        'success' => true,
        'message' => 'Instancias eliminadas correctamente de OT'
    ]);
}


public function quitarDirectoAOTFromCoordinador($cotio_numcoti, $cotio_item, $instance_number)
{
    $isFromCoordinador = request()->input('isFromCoordinador', false);

    // Si NO es desde coordinador, verificamos si está activa en OT
    if (!$isFromCoordinador) {
        $instanciaActiva = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
            ->where('cotio_item', $cotio_item)
            ->where('instance_number', $instance_number)
            ->where('active_ot', true)
            ->exists();

        if ($instanciaActiva) {
            return back()->with('error', 'La muestra está activa en OT, no se puede realizar la acción');
        }
    }

    // Actualizar las instancias (se ejecuta siempre si es desde coordinador)
    $updated = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->update([
            'enable_ot' => false,
            'cotio_estado_analisis' => $isFromCoordinador ? null : DB::raw('cotio_estado_analisis'),
            'active_ot' => false
        ]);

    if ($updated) {
        return back()->with('success', 'Instancias eliminadas correctamente de OT');
    }

    return back()->with('error', 'No se pudo realizar la acción');
}





protected function getOrCreateInstancia($numcoti, $item, $subitem, $instance, $instanciasExistentes)
{
    if (isset($instanciasExistentes[$item][$subitem][$instance])) {
        return $instanciasExistentes[$item][$subitem][$instance]->first();
    }
    
    return new CotioInstancia([
        'cotio_numcoti' => $numcoti,
        'cotio_item' => $item,
        'cotio_subitem' => $subitem,
        'instance_number' => $instance,
        'responsable_muestreo' => null,
        'fecha_muestreo' => null,
        'enable_muestreo' => false,
        'enable_ot' => false,
        'cotio_estado' => 'pendiente',
        'active_ot' => false
    ]);
}

protected function getAnalisisForMuestra($tareas, $item, $instance, $instanciasExistentes)
{
    $analisis = [];
    
    foreach ($tareas as $tarea) {
        if ($tarea->cotio_item == $item && $tarea->cotio_subitem != 0) {
            $instanciaAnalisis = $this->getOrCreateInstancia(
                $tarea->cotio_numcoti,
                $tarea->cotio_item,
                $tarea->cotio_subitem,
                $instance,
                $instanciasExistentes
            );
            
            if ($instanciaAnalisis->exists) {
                $instanciaAnalisis->refresh(); 
            }
            
            $tareaClonada = clone $tarea;
            $tareaClonada->instancia = $instanciaAnalisis;
            $tareaClonada->original_item = $tarea->cotio_item;
            
            $analisis[] = $tareaClonada;
        }
    }
    
    return $analisis;
}



public function verMuestra($cotizacion, $item, $instance = null)
{
    $cotizacion = Coti::findOrFail($cotizacion);
    $instance = $instance ?? 1;
    $usuariosMuestreo = User::where('rol', 'muestreador')
                ->orderBy('usu_descripcion')
                ->get();
    
    // Obtener la muestra principal
    $categoria = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $item)
                ->where('cotio_subitem', 0)
                ->firstOrFail();
    
    // Obtener la instancia de la muestra con sus variables y relaciones
    $instanciaMuestra = CotioInstancia::with(['valoresVariables'])
                ->where([
                    'cotio_numcoti' => $cotizacion->coti_num,
                    'cotio_item' => $item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instance,
                    'active_muestreo' => true
                ])->first();
            

    // Preparar datos adicionales
    $herramientasMuestra = collect();
    $variablesOrdenadas = collect();
    $historialCambios = collect();
    
    if ($instanciaMuestra) {
        // Obtener herramientas
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
            
        // Ordenar variables si existen
        if ($instanciaMuestra->valoresVariables) {
            $variablesOrdenadas = $instanciaMuestra->valoresVariables
                ->sortBy('variable')
                ->values();
        }

        $historialCambios = CotioHistorialCambios::where('tabla_afectada', 'cotio_valores_variables')
        ->whereIn('registro_id', $variablesOrdenadas->pluck('id'))
        ->with(['usuario' => function ($query) {
            $query->select('usu_codigo', 'usu_descripcion');
        }])
        ->orderBy('fecha_cambio', 'desc')
        ->get()
        ->groupBy('registro_id');
        }
    
    if (!$instanciaMuestra) {
        $usuariosAnalistas = User::where('rol', '!=', 'sector')
                    ->orderBy('usu_descripcion')
                    ->get();
        
        return view('muestras.tareasporcategoria', [
            'cotizacion' => $cotizacion,
            'categoria' => $categoria,
            'tareas' => collect(),
            'usuarios' => collect(),
            'usuariosMuestreo' => $usuariosMuestreo,
            'inventario' => collect(),
            'instance' => $instance,
            'instanciaActual' => null, 
            'instanciasMuestra' => collect(),
            'variablesMuestra' => collect(),
            'herramientasMuestra' => collect(),
            'historialCambios' => collect()
        ]);
    }

    // Obtener tareas (análisis)
    $tareas = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $item)
                ->where('cotio_subitem', '!=', 0)
                ->orderBy('cotio_subitem')
                ->get();
    
    $tareasConInstancias = $tareas->map(function($tarea) use ($instance) {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $tarea->cotio_numcoti,
            'cotio_item' => $tarea->cotio_item,
            'cotio_subitem' => $tarea->cotio_subitem,
            'instance_number' => $instance,
            'active_muestreo' => true
        ])->first();
        
        if ($instancia) {
            // Obtener herramientas manualmente para cada análisis
            $herramientasAnalisis = DB::table('cotio_inventario_muestreo')
                ->where('cotio_numcoti', $instancia->cotio_numcoti)
                ->where('cotio_item', $instancia->cotio_item)
                ->where('cotio_subitem', $instancia->cotio_subitem)
                ->where('instance_number', $instancia->instance_number)
                ->join('inventario_muestreo', 'cotio_inventario_muestreo.inventario_muestreo_id', '=', 'inventario_muestreo.id')
                ->select(
                    'inventario_muestreo.*',
                    'cotio_inventario_muestreo.cantidad',
                    'cotio_inventario_muestreo.observaciones as pivot_observaciones'
                )
                ->get();
                
            $tarea->herramientas = $herramientasAnalisis;
            $tarea->instancia = $instancia;
            return $tarea;
        }
        return null;
    })->filter();
    
    $usuarios = User::where('usu_nivel', '<=', 500)
                ->orderBy('usu_descripcion')
                ->get();
    
    $inventario = InventarioMuestreo::all();

    // Regla de vehículo único por TIPO de muestra (cotio_descripcion): para este tipo si ya hay
    // una instancia coordinada con vehículo asignado, solo se permite ese vehículo.
    $descripcionMuestra = trim((string) ($instanciaMuestra->cotio_descripcion ?? ''));
    $vehiculoFijadoId = null;
    if ($descripcionMuestra !== '') {
        $vehiculoFijadoId = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', 0)
            ->whereIn('cotio_estado', ['coordinado muestreo', 'muestreado'])
            ->whereNotNull('vehiculo_asignado')
            ->whereRaw('TRIM(cotio_descripcion) = ?', [$descripcionMuestra])
            ->value('vehiculo_asignado');
    }

    if ($vehiculoFijadoId) {
        $vehiculos = Vehiculo::where('id', $vehiculoFijadoId)->get();
    } else {
        $vehiculos = Vehiculo::all();
    }
    
    $instanciasMuestra = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
                            ->where('cotio_item', $item)
                            ->where('cotio_subitem', 0)
                            ->where('active_muestreo', true)
                            ->get()
                            ->keyBy('instance_number');
    
    // Obtener todos los responsables únicos de todas las tareas de la instancia actual
    $todosResponsablesTareas = collect();
    foreach ($tareasConInstancias as $tarea) {
        if ($tarea->instancia && $tarea->instancia->responsablesMuestreo) {
            $todosResponsablesTareas = $todosResponsablesTareas->merge($tarea->instancia->responsablesMuestreo);
        }
    }
    $todosResponsablesTareas = $todosResponsablesTareas->unique('usu_codigo');
    
    return view('muestras.tareasporcategoria', [
        'cotizacion' => $cotizacion,
        'categoria' => $categoria,
        'tareas' => $tareasConInstancias,
        'usuarios' => $usuarios,
        'usuariosMuestreo' => $usuariosMuestreo,
        'inventario' => $inventario,
        'instance' => $instance,
        'vehiculos' => $vehiculos,
        'instanciaActual' => $instanciaMuestra, 
        'instanciasMuestra' => $instanciasMuestra,
        'variablesMuestra' => $variablesOrdenadas,
        'herramientasMuestra' => $herramientasMuestra,
        'todosResponsablesTareas' => $todosResponsablesTareas,
        'historialCambios' => $historialCambios
    ]);
}


public function updateVariable(Request $request)
{
    $request->validate([
        'id' => 'required|exists:cotio_valores_variables,id',
        'valor' => 'required|string|max:255'
    ]);

    try {
        // Asumo que tienes un modelo para las variables de muestreo
        $variable = CotioValorVariable::findOrFail($request->id);
        $variable->valor = $request->valor;
        $variable->save();

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}

public function updateAllData(Request $request)
{
    try {
        $request->validate([
            'instancia_id' => 'required|exists:cotio_instancias,id',
            'variables' => 'nullable|array', // Hacer variables opcional
            'variables.*.id' => 'required_if:variables,!=,[]|exists:cotio_valores_variables,id', // ID requerido solo si se envía una variable
            'variables.*.valor' => 'nullable|string|max:255', // Valor opcional
            'observaciones' => 'nullable|string|max:1000'
        ], [
            'instancia_id.required' => 'El ID de la instancia es requerido.',
            'instancia_id.exists' => 'La instancia especificada no existe.',
            'variables.*.id.required_if' => 'El ID de la variable es requerido.',
            'variables.*.id.exists' => 'La variable especificada no existe.',
            'variables.*.valor.max' => 'El valor de la variable no puede exceder 255 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.'
        ]);

        // Validar que al menos haya una variable con valor o una observación
        if (empty($request->variables) && empty(trim($request->observaciones ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'Debe ingresar al menos un valor de variable o una observación.'
            ], 422);
        }

        DB::beginTransaction();

        // Actualizar variables si se enviaron
        if (!empty($request->variables)) {
            foreach ($request->variables as $variableData) {
                $variable = CotioValorVariable::findOrFail($variableData['id']);
                $variable->valor = trim($variableData['valor'] ?? '');
                $variable->save();
            }
        }

        // Actualizar observaciones en la instancia
        $instancia = CotioInstancia::findOrFail($request->instancia_id);
        $instancia->observaciones_medicion_coord_muestreo = trim($request->observaciones ?? '');
        $instancia->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Variables y observaciones actualizadas correctamente'
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error de validación: ' . implode(', ', array_merge(...array_values($e->errors()))),
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        DB::rollback();
        Log::error('Error al actualizar datos: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ], 500);
    }
}





public function asignacionMasiva(Request $request)
{
    $itemsSeleccionados = json_decode($request->items_seleccionados, true);
    $parametrosSeleccionados = json_decode($request->parametros_seleccionados, true);

    // Validar la solicitud
    $request->validate([
        'cotio_numcoti' => 'required|string',
        'items_seleccionados' => 'required|json',
        'responsables_muestreo' => 'nullable|array',
        'responsables_muestreo.*' => 'nullable|string|exists:usu,usu_codigo',
        'herramientas' => 'nullable|array',
        'herramientas.*' => 'nullable|integer|exists:inventario_muestreo,id',
        'vehiculo' => 'nullable|integer|exists:vehiculos,id',
        'fecha_inicio_muestreo' => 'required|date',
        'fecha_fin_muestreo' => 'required|date|after_or_equal:fecha_inicio_muestreo',
        'habilitar_frecuencia' => 'required|boolean',
        'frecuencia' => 'nullable|in:diario,semanal,quincenal,mensual,trimestral,cuatrimestral,semestral,anual',
        'parametros_seleccionados' => 'required|json',
        'parametros_seleccionados.*.item' => 'required_with:parametros_seleccionados|integer',
        'parametros_seleccionados.*.subitem' => 'required_with:parametros_seleccionados|integer',
        'parametros_seleccionados.*.instance' => 'required_with:parametros_seleccionados|integer',
        'parametros_seleccionados.*.variables' => 'required_with:parametros_seleccionados|array',
        'parametros_seleccionados.*.variables.*' => 'integer|exists:variables_requeridas,id',
        'es_priori' => 'nullable|boolean'
    ]);

    DB::beginTransaction();
    try {
        $cotioNumcoti = $request->cotio_numcoti;

        // Regla de vehículo único por TIPO de muestra (mismo cotio_descripcion): por cada tipo ya coordinado con vehículo,
        // las nuevas asignaciones de ese tipo deben usar el mismo vehículo.
        $vehiculoFijadoPorTipo = CotioInstancia::where('cotio_numcoti', $cotioNumcoti)
            ->where('cotio_subitem', 0)
            ->whereIn('cotio_estado', ['coordinado muestreo', 'muestreado'])
            ->whereNotNull('vehiculo_asignado')
            ->get()
            ->filter(fn ($i) => trim((string) ($i->cotio_descripcion ?? '')) !== '')
            ->groupBy(fn ($i) => trim((string) $i->cotio_descripcion))
            ->map(fn ($g) => (int) $g->first()->vehiculo_asignado)
            ->toArray();

        if ($request->filled('vehiculo')) {
            $vehiculoSolicitado = (int) $request->vehiculo;
            $muestrasSeleccionadas = collect($itemsSeleccionados)->where('subitem', '0');
            foreach ($muestrasSeleccionadas as $itemData) {
                $descripcion = isset($itemData['descripcion']) ? trim((string) $itemData['descripcion']) : '';
                if ($descripcion === '') continue;
                $fijado = $vehiculoFijadoPorTipo[$descripcion] ?? null;
                if ($fijado !== null && $vehiculoSolicitado !== $fijado) {
                    return response()->json([
                        'success' => false,
                        'message' => "Para el tipo \"{$descripcion}\" ya está asignado un vehículo. Todas las muestras de ese tipo deben usar el mismo vehículo."
                    ], 422);
                }
            }
        }
        $itemsData = $itemsSeleccionados;
        $parametrosSeleccionados = $parametrosSeleccionados;
        $userId = Auth::user()->usu_codigo;
        $updatedCount = 0;
        $esPrioridad = $request->es_priori ?? false;
        // Mapa de frecuencias a unidades y valores
        $frecuenciaMap = [
            'diario' => ['unit' => 'day', 'value' => 1],
            'semanal' => ['unit' => 'week', 'value' => 1],
            'quincenal' => ['unit' => 'week', 'value' => 2],
            'mensual' => ['unit' => 'month', 'value' => 1],
            'trimestral' => ['unit' => 'month', 'value' => 3],
            'cuatrimestral' => ['unit' => 'month', 'value' => 4],
            'semestral' => ['unit' => 'month', 'value' => 6],
            'anual' => ['unit' => 'year', 'value' => 1],
        ];

        // Mapear IDs de variables a nombres
        $variableNames = VariableRequerida::pluck('nombre', 'id')->toArray();

        // Obtener variables obligatorias por tipo de muestra
        $mandatoryVariables = VariableRequerida::where('obligatorio', true)
            ->get()
            ->groupBy('cotio_descripcion')
            ->mapWithKeys(function ($variables, $tipoMuestra) {
                return [$tipoMuestra => $variables->pluck('id')->toArray()];
            });

        // Precargar ítems desde Cotio para validar
        $allItems = Cotio::where('cotio_numcoti', $cotioNumcoti)
            ->get()
            ->keyBy(function ($item) {
                return "{$item->cotio_item}-{$item->cotio_subitem}";
            });

        // Función auxiliar para obtener descripción y precio
        $getCotioData = function ($item, $subitem) use ($allItems) {
            $key = "{$item}-{$subitem}";
            $cotio = $allItems->get($key);
            return [
                'descripcion' => $cotio?->cotio_descripcion,
                'precio' => $cotio?->cotio_precio ? $cotio->cotio_precio : null,
                'cotio_codigoum' => $cotio?->cotio_codigoum ? $cotio->cotio_codigoum : null,
                'cotio_codigometodo' => $cotio?->cotio_codigometodo,
                'cotio_codigometodo_analisis' => $cotio?->cotio_codigometodo_analisis
            ];
        };

        // Validar muestras para frecuencia
        $muestras = collect($itemsData)->where('subitem', '0')->values();
        if ($request->habilitar_frecuencia && $request->frecuencia) {
            if ($muestras->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren al menos dos muestras para habilitar frecuencia.'
                ], 422);
            }
            $firstMuestra = $muestras->first();
            $valid = $muestras->every(fn($item) => 
                $item['item'] === $firstMuestra['item'] && 
                $item['descripcion'] === $firstMuestra['descripcion']
            );
            if (!$valid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Las muestras seleccionadas deben ser del mismo tipo para habilitar frecuencia.'
                ], 422);
            }
        }

        // Agrupar muestras por cotio_item para calcular índices específicos
        $muestrasPorItem = collect($itemsData)
            ->where('subitem', '0')
            ->groupBy('item')
            ->map(function ($group) {
                return $group->sortBy('instance')->values();
            });

        // Procesar los ítems manualmente seleccionados
        $manualSelections = collect($itemsData)->where('isManual', true);
        $processedInstances = [];
        $affectedInstances = collect();

        foreach ($manualSelections as $itemData) {
            $item = $itemData['item'];
            $subitem = $itemData['subitem'];
            $instance = $itemData['instance'];
            $cotioKey = "{$item}-{$subitem}";
            if (!$allItems->has($cotioKey)) continue;

            $instancia = CotioInstancia::firstOrNew([
                'cotio_numcoti' => $cotioNumcoti,
                'cotio_item' => $item,
                'cotio_subitem' => $subitem,
                'instance_number' => $instance
            ]);

            // Obtener datos de cotio una sola vez
            $cotioData = $getCotioData($item, $subitem);
            
            // Solo modificar si es nueva instancia o fue selección manual
            if (!$instancia->exists || $itemData['isManual']) {
                if ($cotioData['descripcion'] && !$instancia->cotio_descripcion) {
                    $instancia->cotio_descripcion = $cotioData['descripcion'];
                }
                if ($cotioData['precio'] !== null) {
                    $instancia->monto = $cotioData['precio'];
                }
                if ($cotioData['cotio_codigoum'] !== null) {
                    $instancia->cotio_codigoum = $cotioData['cotio_codigoum'];
                }
                
                $instancia->active_muestreo = $itemData['isManual'];
                $instancia->fecha_muestreo = $itemData['isManual'] ? now() : null;
                $instancia->coordinador_codigo = $userId;
                $instancia->cotio_estado = 'coordinado muestreo';
                $instancia->es_priori = $esPrioridad;
            }
            
            // Copiar ambos métodos siempre desde Cotio (tanto para nuevas instancias como para actualizaciones)
            if (isset($cotioData['cotio_codigometodo']) && $cotioData['cotio_codigometodo'] !== null) {
                $instancia->cotio_codigometodo = $cotioData['cotio_codigometodo'];
            }
            if (isset($cotioData['cotio_codigometodo_analisis']) && $cotioData['cotio_codigometodo_analisis'] !== null) {
                $instancia->cotio_codigometodo_analisis = $cotioData['cotio_codigometodo_analisis'];
            }

            // Actualizar campos comunes (solo para selecciones manuales)
            if ($itemData['isManual']) {
                $startDate = Carbon::parse($request->fecha_inicio_muestreo);
                if ($request->habilitar_frecuencia && $request->frecuencia && isset($frecuenciaMap[$request->frecuencia])) {
                    $frecuencia = $frecuenciaMap[$request->frecuencia];
                    $muestraIndex = 0;
                    if ($subitem == '0') {
                        $muestrasGrupo = $muestrasPorItem->get($item, collect());
                        $muestraIndex = $muestrasGrupo->search(fn($m) => $m['instance'] == $instance) ?: 0;
                    } else {
                        $muestraAsociada = $muestras->firstWhere(fn($m) => $m['item'] == $item && $m['instance'] == $instance);
                        if ($muestraAsociada) {
                            $muestrasGrupo = $muestrasPorItem->get($item, collect());
                            $muestraIndex = $muestrasGrupo->search(fn($m) => $m['instance'] == $muestraAsociada['instance']) ?: 0;
                        }
                    }
                    $startDate->addUnit($frecuencia['unit'], $frecuencia['value'] * $muestraIndex);
                    $instancia->es_frecuente = true;
                }
                $endDate = Carbon::parse($request->fecha_fin_muestreo)->setDateFrom($startDate);

                $instancia->fecha_inicio_muestreo = $startDate;
                $instancia->fecha_fin_muestreo = $endDate;

                if ($request->filled('vehiculo')) {
                    $instancia->vehiculo_asignado = $request->vehiculo;
                }
            }

            // Guardar la instancia
            $instancia->save();
            $updatedCount++;

            // Sincronizar responsables solo para ítems seleccionados manualmente
            if ($itemData['isManual'] && !empty($request->responsables_muestreo)) {
                $instancia->responsablesMuestreo()->sync($request->responsables_muestreo);
                Log::debug('Asignando responsables_muestreo a ítem seleccionado manualmente', [
                    'instancia_id' => $instancia->id,
                    'item' => $item,
                    'subitem' => $subitem,
                    'instance' => $instance,
                    'responsables_muestreo' => $request->responsables_muestreo
                ]);
            } elseif ($itemData['isManual']) {
                $instancia->responsablesMuestreo()->detach();
                Log::debug('Limpiando responsables_muestreo para ítem seleccionado manualmente', [
                    'instancia_id' => $instancia->id,
                    'item' => $item,
                    'subitem' => $subitem,
                    'instance' => $instance
                ]);
            }

            // Procesar variables seleccionadas
            $parametrosInstancia = collect($parametrosSeleccionados)
                ->firstWhere(fn($p) => $p['item'] == $item && $p['subitem'] == $subitem && $p['instance'] == $instance);

            $selectedVariableIds = $parametrosInstancia['variables'] ?? [];
            $mandatoryVariableIds = $mandatoryVariables[$instancia->cotio_descripcion] ?? [];
            $allVariableIds = array_unique(array_merge($selectedVariableIds, $mandatoryVariableIds));

            // Limpiar variables existentes
            CotioValorVariable::where('cotio_instancia_id', $instancia->id)->delete();

            // Asignar variables en la tabla pivote
            foreach ($allVariableIds as $variableId) {
                $variableNombre = $variableNames[$variableId] ?? null;
                if (!$variableNombre) continue;

                CotioValorVariable::create([
                    'cotio_instancia_id' => $instancia->id,
                    'variable' => $variableNombre,
                    'valor' => null
                ]);
            }

            // Registrar instancia procesada
            $processedInstances["{$item}-{$subitem}-{$instance}"] = true;
            
            // Registrar instancias afectadas (solo muestras principales)
            if ($subitem == '0') {
                $affectedInstances->push([
                    'item' => $item,
                    'instance' => $instance,
                    'fecha_inicio_muestreo' => $instancia->fecha_inicio_muestreo,
                    'fecha_fin_muestreo' => $instancia->fecha_fin_muestreo
                ]);
            }

            // Asignar herramientas si se especifican
            if ($itemData['isManual'] && !empty($request->herramientas)) {
                $this->actualizarHerramientas(
                    $cotioNumcoti,
                    $item,
                    $subitem,
                    $instance,
                    $request->herramientas,
                    $instancia->exists
                );
            }
        }

        // Procesar análisis no seleccionados para las instancias afectadas
        foreach ($affectedInstances->unique() as $mainItem) {
            $this->procesarAnalisisDeCategoria(
                $cotioNumcoti,
                $mainItem['item'],
                $mainItem['instance'],
                $allItems,
                $request,
                $userId,
                $updatedCount,
                $mainItem['fecha_inicio_muestreo'],
                $mainItem['fecha_fin_muestreo'],
                $request->habilitar_frecuencia && $request->frecuencia,
                $esPrioridad
            );
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "Asignación completada para $updatedCount instancias",
            'updated_count' => $updatedCount
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en asignacionMasiva', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error en asignación masiva: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
}

protected function procesarAnalisisDeCategoria($cotioNumcoti, $item, $instance, $allItems, $request, $userId, &$updatedCount, $fechaInicio, $fechaFin, $esFrecuente, $esPrioridad)
{
    // Obtener todos los análisis para esta categoría
    $analisisItems = $allItems->filter(function ($cotioItem) use ($item) {
        return $cotioItem->cotio_item == $item && $cotioItem->cotio_subitem > 0;
    });

    // Mapear IDs de variables a nombres
    $variableNames = VariableRequerida::pluck('nombre', 'id')->toArray();

    // Obtener variables obligatorias por tipo de muestra
    $mandatoryVariables = VariableRequerida::where('obligatorio', true)
        ->get()
        ->groupBy('cotio_descripcion')
        ->mapWithKeys(function ($variables, $tipoMuestra) {
            return [$tipoMuestra => $variables->pluck('id')->toArray()];
        });

    // Función auxiliar para obtener descripción y precio
    $getCotioData = function ($item, $subitem) use ($allItems) {
        $key = "{$item}-{$subitem}";
        $cotio = $allItems->get($key);
        return [
            'descripcion' => $cotio?->cotio_descripcion,
            'precio' => $cotio?->cotio_precio ? $cotio->cotio_precio : null,
            'cotio_codigoum' => $cotio?->cotio_codigoum ? $cotio->cotio_codigoum : null,
            'cotio_codigometodo' => $cotio?->cotio_codigometodo,
            'cotio_codigometodo_analisis' => $cotio?->cotio_codigometodo_analisis
        ];
    };

    foreach ($analisisItems as $analisis) {
        $instAn = CotioInstancia::firstOrNew([
            'cotio_numcoti' => $cotioNumcoti,
            'cotio_item' => $item,
            'cotio_subitem' => $analisis->cotio_subitem,
            'instance_number' => $instance,
        ]);

        // Obtener datos de cotio
        $cotioData = $getCotioData($item, $analisis->cotio_subitem);
        
        // Solo modificar si es nueva instancia
        if (!$instAn->exists) {
            if ($cotioData['descripcion'] && !$instAn->cotio_descripcion) {
                $instAn->cotio_descripcion = $cotioData['descripcion'];
            }
            if ($cotioData['precio'] !== null) {
                $instAn->monto = $cotioData['precio'];
            }
            if (isset($cotioData['cotio_codigoum']) && $cotioData['cotio_codigoum'] !== null) {
                $instAn->cotio_codigoum = $cotioData['cotio_codigoum'];
            }

            $instAn->active_muestreo = false; // Por defecto no activar
            $instAn->cotio_estado = 'coordinado muestreo';
            $instAn->fecha_inicio_muestreo = $fechaInicio;
            $instAn->fecha_fin_muestreo = $fechaFin;
            $instAn->fecha_muestreo = $fechaInicio;
            $instAn->coordinador_codigo = $userId;
            $instAn->es_frecuente = $esFrecuente;
            $instAn->es_priori = $esPrioridad;
        }
        
        // Copiar ambos métodos siempre desde Cotio (tanto para nuevas instancias como para actualizaciones)
        if (isset($cotioData['cotio_codigometodo']) && $cotioData['cotio_codigometodo'] !== null) {
            $instAn->cotio_codigometodo = $cotioData['cotio_codigometodo'];
        }
        if (isset($cotioData['cotio_codigometodo_analisis']) && $cotioData['cotio_codigometodo_analisis'] !== null) {
            $instAn->cotio_codigometodo_analisis = $cotioData['cotio_codigometodo_analisis'];
        }
        
        $instAn->save();
        $updatedCount++;

        // Procesar variables seleccionadas para este análisis
        $parametrosInstancia = collect(json_decode($request->parametros_seleccionados, true))
            ->firstWhere(fn($p) => $p['item'] == $item && $p['subitem'] == $analisis->cotio_subitem && $p['instance'] == $instance);

        $selectedVariableIds = $parametrosInstancia['variables'] ?? [];
        $mandatoryVariableIds = $mandatoryVariables[$instAn->cotio_descripcion] ?? [];
        $allVariableIds = array_unique(array_merge($selectedVariableIds, $mandatoryVariableIds));

        // Limpiar variables existentes
        CotioValorVariable::where('cotio_instancia_id', $instAn->id)->delete();

        // Asignar variables en la tabla pivote
        foreach ($allVariableIds as $variableId) {
            $variableNombre = $variableNames[$variableId] ?? null;
            if (!$variableNombre) continue;

            CotioValorVariable::create([
                'cotio_instancia_id' => $instAn->id,
                'variable' => $variableNombre,
                'valor' => null
            ]);
        }

        // Asignar herramientas si se especifican
        if (!empty($request->herramientas)) {
            $this->actualizarHerramientas(
                $cotioNumcoti,
                $item,
                $analisis->cotio_subitem,
                $instance,
                $request->herramientas,
                $instAn->exists
            );
        }
    }
}

protected function actualizarHerramientas($cotioNumcoti, $item, $subitem, $instance, $herramientas, $instanciaExistente = false)
{
    try {
        if (empty($herramientas)) {
            return true;
        }

        // Eliminar solo las asignaciones previas si es una nueva instancia
        if (!$instanciaExistente) {
            CotioInventarioMuestreo::where([
                'cotio_numcoti' => $cotioNumcoti,
                'cotio_item' => $item,
                'cotio_subitem' => $subitem,
                'instance_number' => $instance
            ])->delete();
        }

        // Verificar si ya tiene herramientas asignadas
        $tieneHerramientas = CotioInventarioMuestreo::where([
            'cotio_numcoti' => $cotioNumcoti,
            'cotio_item' => $item,
            'cotio_subitem' => $subitem,
            'instance_number' => $instance
        ])->exists();

        // Si ya tiene herramientas y la instancia existía, no hacer nada
        if ($instanciaExistente && $tieneHerramientas) {
            return true;
        }

        // Agregar nuevas asignaciones
        foreach ($herramientas as $herramientaId) {
            CotioInventarioMuestreo::create([
                'cotio_numcoti' => $cotioNumcoti,
                'cotio_item' => $item,
                'cotio_subitem' => $subitem,
                'instance_number' => $instance,
                'inventario_muestreo_id' => $herramientaId,
                'cantidad' => 1
            ]);
        }

        return true;
    } catch (\Exception $e) {
        Log::error("Error al asignar herramientas: " . $e->getMessage(), [
            'cotio_numcoti' => $cotioNumcoti,
            'cotio_item' => $item,
            'cotio_subitem' => $subitem,
            'instance_number' => $instance
        ]);
        throw new \Exception("Error al asignar herramientas: " . $e->getMessage());
    }
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
            'active_muestreo' => true
        ];


        $instancias = CotioInstancia::where($params)
            ->where(function($query) use ($request) {
                $query->where('cotio_subitem', $request->cotio_subitem) // Muestra principal
                      ->orWhere('cotio_subitem', '>', 0); // Análisis asociados
            })
            ->get();


        if ($instancias->isEmpty()) {
            return redirect()->back()->with('info', 'No hay muestras o análisis activos para finalizar.');
        }

        $updatedCount = 0;
        foreach ($instancias as $instancia) {
            
            $result = $instancia->update([
                'cotio_estado' => 'muestreado',
            ]);

            if ($result) {
                $updatedCount++;
            }
        }

        return redirect()->back()->with('success', 'Todas las muestras y análisis activos han sido finalizados correctamente.');

    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Error al finalizar muestras y análisis: ' . $e->getMessage());
    }
}


public function getDatosRecoordinacion(CotioInstancia $instancia)
{
    // Verificar que la instancia esté en estado suspensión
    if ($instancia->cotio_estado !== 'suspension' && $instancia->cotio_estado !== 'coordinado muestreo' && $instancia->enable_ot == true) {
        return response()->json(['error' => 'La instancia no está en estado suspensión'], 400);
    }

    // Obtener variables requeridas para esta categoría
    $variablesRequeridas = VariableRequerida::where('cotio_descripcion', $instancia->cotio_descripcion)
        ->get()
        ->groupBy('cotio_descripcion')
        ->map(function ($variables) {
            return $variables->mapWithKeys(function ($variable) {
                return [$variable->id => [
                    'id' => $variable->id,
                    'nombre' => $variable->nombre,
                    'obligatorio' => $variable->obligatorio
                ]];
            });
        });

    // Obtener variables actualmente seleccionadas
    $variablesSeleccionadas = $instancia->variablesMuestreo->pluck('variable_id')->toArray();

    return response()->json([
        'fecha_inicio_muestreo' => $instancia->fecha_inicio_muestreo?->format('Y-m-d\TH:i'),
        'fecha_fin_muestreo' => $instancia->fecha_fin_muestreo?->format('Y-m-d\TH:i'),
        'vehiculo_asignado' => $instancia->vehiculo_asignado,
        'cotio_observaciones_suspension' => $instancia->cotio_observaciones_suspension,
        'es_priori' => $instancia->es_priori,
        'responsables' => $instancia->responsablesMuestreo->toArray(),
        'herramientas' => $instancia->herramientas->toArray(),
        'variables_requeridas' => $variablesRequeridas,
        'variables_seleccionadas' => $variablesSeleccionadas
    ]);
}

public function recoordinar(Request $request)
{
    $validated = $request->validate([
        'instancia_id' => 'required|exists:cotio_instancias,id',
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|integer',
        'instance_number' => 'required|integer',
        'fecha_inicio_muestreo' => 'required|date',
        'fecha_fin_muestreo' => 'required|date|after_or_equal:fecha_inicio_muestreo',
        'responsables_muestreo' => 'nullable|array',
        'responsables_muestreo.*' => 'nullable|exists:usu,usu_codigo',
        'vehiculo_asignado' => 'nullable|exists:vehiculos,id',
        'herramientas' => 'nullable|array',
        'herramientas.*' => 'nullable|exists:inventario_muestreo,id',
        // 'cotio_observaciones_suspension' => 'nullable|string',
        'variables_seleccionadas' => 'nullable|array',
        'variables_seleccionadas.*' => 'nullable|exists:variables_requeridas,id',
        'es_priori' => 'nullable|in:0,1,true,false'
    ]);

    Log::info('Validated data', $validated);
    Log::info('es_priori value:', ['es_priori' => $validated['es_priori'], 'type' => gettype($validated['es_priori'])]);

    try {
        DB::beginTransaction();

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);

        // Regla de vehículo único por TIPO de muestra (cotio_descripcion): si ya hay una instancia
        // de este mismo tipo (misma descripción) con vehículo asignado, sólo se permite usar ese mismo vehículo.
        $descripcionMuestra = trim((string) ($instancia->cotio_descripcion ?? ''));
        $vehiculoFijadoId = null;
        if ($descripcionMuestra !== '') {
            $vehiculoFijadoId = CotioInstancia::where('cotio_numcoti', $validated['cotio_numcoti'])
                ->where('cotio_subitem', 0)
                ->whereIn('cotio_estado', ['coordinado muestreo', 'muestreado'])
                ->whereNotNull('vehiculo_asignado')
                ->whereRaw('TRIM(cotio_descripcion) = ?', [$descripcionMuestra])
                ->value('vehiculo_asignado');
        }

        if (
            $vehiculoFijadoId &&
            !empty($validated['vehiculo_asignado']) &&
            (int) $validated['vehiculo_asignado'] !== (int) $vehiculoFijadoId
        ) {
            throw new \Exception('Ya existe un vehículo asignado para muestras de este mismo tipo (misma descripción). Todas las muestras del tipo deben usar el mismo vehículo.');
        }

        if ($instancia->cotio_estado !== 'suspension' && $instancia->cotio_estado !== 'coordinado muestreo' && $instancia->enable_ot == true) {
            throw new \Exception('La muestra no puede ser recoordinada. Se encuentra en órdenes de trabajo o su estado es avanzado.');
        }

        // Obtener datos de Cotio para copiar métodos
        $cotio = Cotio::where('cotio_numcoti', $validated['cotio_numcoti'])
            ->where('cotio_item', $validated['cotio_item'])
            ->where('cotio_subitem', $instancia->cotio_subitem)
            ->first();
        
        // Actualizar datos principales
        $updateData = [
            'fecha_inicio_muestreo' => $validated['fecha_inicio_muestreo'],
            'fecha_fin_muestreo' => $validated['fecha_fin_muestreo'],
            'vehiculo_asignado' => $validated['vehiculo_asignado'],
            // 'cotio_observaciones_suspension' => $validated['cotio_observaciones_suspension'],
            'cotio_estado' => 'coordinado muestreo',
            'coordinador_codigo' => Auth::user()->usu_codigo,
            'es_priori' => $validated['es_priori']
        ];
        
        // Copiar ambos métodos desde Cotio si están disponibles
        if ($cotio) {
            if ($cotio->cotio_codigometodo) {
                $updateData['cotio_codigometodo'] = $cotio->cotio_codigometodo;
            }
            if ($cotio->cotio_codigometodo_analisis) {
                $updateData['cotio_codigometodo_analisis'] = $cotio->cotio_codigometodo_analisis;
            }
        }
        
        $instancia->update($updateData);

        Log::info('Instancia actualizada', $instancia->toArray());
        Log::info('es_priori después de actualizar:', ['es_priori' => $instancia->es_priori]);

        // Eliminar y actualizar responsables
        $instancia->responsablesMuestreo()->detach();
        if (!empty($validated['responsables_muestreo'])) {
            $instancia->responsablesMuestreo()->attach($validated['responsables_muestreo']);
        }

        // Eliminar todas las herramientas existentes usando todos los campos de la clave única
        DB::table('cotio_inventario_muestreo')
            ->where([
                'cotio_numcoti' => $validated['cotio_numcoti'],
                'cotio_item' => $validated['cotio_item'],
                'cotio_subitem' => 0,
                'instance_number' => $validated['instance_number']
            ])
            ->delete();

        // Insertar las nuevas herramientas si existen
        if (!empty($validated['herramientas'])) {
            $herramientasData = array_map(function($herramientaId) use ($validated, $instancia) {
                return [
                    'inventario_muestreo_id' => $herramientaId,
                    'cotio_instancia_id' => $instancia->id,
                    'cantidad' => 1,
                    'cotio_numcoti' => $validated['cotio_numcoti'],
                    'cotio_item' => $validated['cotio_item'],
                    'cotio_subitem' => 0,
                    'instance_number' => $validated['instance_number']
                ];
            }, $validated['herramientas']);

            // Insertar todas las herramientas de una vez
            DB::table('cotio_inventario_muestreo')->insert($herramientasData);
        }

        // Actualizar variables seleccionadas
        if (isset($validated['variables_seleccionadas'])) {
            $instancia->variablesMuestreo()->delete();
            $variables = VariableRequerida::whereIn('id', $validated['variables_seleccionadas'])->get();

            foreach ($validated['variables_seleccionadas'] as $variableId) {
                $variable = $variables->firstWhere('id', $variableId);
                if ($variable) {
                    $instancia->variablesMuestreo()->create([
                        'variable_id' => $variableId,
                        'variable' => $variable->nombre,
                        'valor' => null
                    ]);
                }
            }
        }

        // Actualizar análisis asociados y copiar métodos desde Cotio
        $analisisInstancias = CotioInstancia::where('cotio_numcoti', $validated['cotio_numcoti'])
            ->where('cotio_item', $validated['cotio_item'])
            ->where('instance_number', $validated['instance_number'])
            ->where('cotio_subitem', '>', 0)
            ->get();
        
        foreach ($analisisInstancias as $analisisInst) {
            $cotioAnalisis = Cotio::where('cotio_numcoti', $validated['cotio_numcoti'])
                ->where('cotio_item', $validated['cotio_item'])
                ->where('cotio_subitem', $analisisInst->cotio_subitem)
                ->first();
            
            $updateDataAnalisis = [
                'cotio_estado' => 'coordinado muestreo',
                'fecha_inicio_muestreo' => $validated['fecha_inicio_muestreo'],
                'fecha_fin_muestreo' => $validated['fecha_fin_muestreo'],
                'es_priori' => $validated['es_priori']
            ];
            
            // Copiar ambos métodos desde Cotio si están disponibles
            if ($cotioAnalisis) {
                if ($cotioAnalisis->cotio_codigometodo) {
                    $updateDataAnalisis['cotio_codigometodo'] = $cotioAnalisis->cotio_codigometodo;
                }
                if ($cotioAnalisis->cotio_codigometodo_analisis) {
                    $updateDataAnalisis['cotio_codigometodo_analisis'] = $cotioAnalisis->cotio_codigometodo_analisis;
                }
            }
            
            $analisisInst->update($updateDataAnalisis);
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Muestra recoordinada exitosamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al recoordinar instancia', [
            'instancia_id' => $validated['instancia_id'] ?? null,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error al recoordinar: ' . $e->getMessage()
        ], 500);
    }
}

    // Endpoints para gestionar responsables de muestreo
public function editarResponsablesMuestreo(Request $request)
{
    try {
        $validated = $request->validate([
            'instancia_id' => 'required|exists:cotio_instancias,id',
            'responsables' => 'required|array|min:1',
            'responsables.*' => 'exists:usu,usu_codigo'
        ]);

        DB::beginTransaction();

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);
        
        Log::info('DEBUG - Editando responsables de muestreo', [
            'instancia_id' => $instancia->id,
            'responsables_nuevos' => $validated['responsables']
        ]);

        // Obtener responsables actuales (con trim para comparación)
        $responsablesActuales = $instancia->responsablesMuestreo()
            ->select('usu.usu_codigo', 'usu.usu_descripcion')
            ->get()
            ->map(function($responsable) {
                return trim($responsable->usu_codigo);
            })
            ->toArray();

        Log::info('DEBUG - Responsables actuales', [
            'responsables_actuales' => $responsablesActuales
        ]);

        // Obtener códigos exactos (con padding) para sync
        $codigosExactos = User::whereIn('usu_codigo', function($query) use ($validated) {
            $query->select('usu_codigo')
                    ->from('usu')
                    ->whereIn(DB::raw('TRIM(usu_codigo)'), array_map('trim', $validated['responsables']));
        })->pluck('usu_codigo')->toArray();

        Log::info('DEBUG - Códigos exactos para sync', [
            'codigos_exactos' => $codigosExactos
        ]);

        // Obtener responsables actuales exactos (especificando la tabla para evitar ambigüedad)
        $responsablesActualesExactos = $instancia->responsablesMuestreo()
            ->pluck('usu.usu_codigo')
            ->toArray();

        // Filtrar solo los responsables que no están ya asignados
        $nuevosResponsables = array_diff($codigosExactos, $responsablesActualesExactos);

        if (!empty($nuevosResponsables)) {
            // Agregar solo los nuevos responsables
            $instancia->responsablesMuestreo()->attach($nuevosResponsables);
            
            Log::info('DEBUG - Responsables agregados', [
                'nuevos_responsables' => $nuevosResponsables
            ]);
        } else {
            Log::info('DEBUG - No hay nuevos responsables para agregar');
        }

        DB::commit();

        $mensaje = !empty($nuevosResponsables) 
            ? 'Responsables agregados correctamente'
            : 'Los responsables seleccionados ya estaban asignados';

        return response()->json([
            'success' => true,
            'message' => $mensaje
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al editar responsables de muestreo: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar responsables: ' . $e->getMessage()
        ]);
    }
}

public function quitarResponsableMuestreo(Request $request)
{
    try {
        $validated = $request->validate([
            'instancia_id' => 'required|exists:cotio_instancias,id',
            'responsable_codigo' => 'required|string'
        ]);

        DB::beginTransaction();

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);
        $responsableCodigo = trim($validated['responsable_codigo']);

        Log::info('DEBUG - Quitando responsable de muestreo', [
            'instancia_id' => $instancia->id,
            'responsable_codigo' => $responsableCodigo
        ]);

        // Obtener responsables actuales con información completa
        $responsablesActuales = $instancia->responsablesMuestreo()->get();
        
        Log::info('DEBUG - Responsables actuales muestreo', [
            'total_responsables' => $responsablesActuales->count(),
            'responsables' => $responsablesActuales->map(function($responsable) {
                return [
                    'usu_codigo' => $responsable->usu_codigo,
                    'usu_codigo_trimmed' => trim($responsable->usu_codigo),
                    'usu_descripcion' => $responsable->usu_descripcion
                ];
            })->toArray()
        ]);

        // Encontrar el responsable exacto (con padding)
        $responsableExacto = $responsablesActuales->first(function($responsable) use ($responsableCodigo) {
            return trim($responsable->usu_codigo) === $responsableCodigo;
        });

        if (!$responsableExacto) {
            return response()->json([
                'success' => false,
                'message' => 'El responsable no está asignado a esta muestra'
            ]);
        }

        $codigoExacto = $responsableExacto->usu_codigo;

        Log::info('DEBUG - Código exacto encontrado', [
            'codigo_recibido' => "'{$responsableCodigo}'",
            'codigo_exacto_bd' => "'{$codigoExacto}'"
        ]);

        // Intentar detach con el código exacto
        $resultadoDetach = $instancia->responsablesMuestreo()->detach($codigoExacto);

        Log::info('DEBUG - Resultado detach muestreo', [
            'resultado' => $resultadoDetach,
            'responsable_quitado_exacto' => $codigoExacto
        ]);

        // Si detach no funciona, usar SQL directo
        if ($resultadoDetach == 0) {
            Log::info('DEBUG - Detach devolvió 0, usando SQL directo para muestreo');
            
            $resultadoSQL = DB::table('instancia_responsable_muestreo')
                ->where('cotio_instancia_id', $instancia->id)
                ->where('usu_codigo', $codigoExacto)
                ->delete();
            
            Log::info('DEBUG - Resultado SQL directo muestreo', [
                'resultado' => $resultadoSQL,
                'instancia_id' => $instancia->id,
                'codigo_exacto' => $codigoExacto
            ]);

            if ($resultadoSQL == 0) {
                // Intentar con LIKE como último recurso
                $resultadoLike = DB::table('instancia_responsable_muestreo')
                    ->where('cotio_instancia_id', $instancia->id)
                    ->where('usu_codigo', 'LIKE', $responsableCodigo . '%')
                    ->delete();
                
                Log::info('DEBUG - Resultado LIKE muestreo', [
                    'resultado' => $resultadoLike,
                    'patron_like' => "'{$responsableCodigo}%'"
                ]);
                
                $resultadoDetach = $resultadoLike;
            } else {
                $resultadoDetach = $resultadoSQL;
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Responsable de muestreo eliminado correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al quitar responsable de muestreo: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al quitar responsable: ' . $e->getMessage()
        ]);
    }
}

public function getResponsablesMuestreo(Request $request)
{
    try {
        $validated = $request->validate([
            'instancia_id' => 'required|exists:cotio_instancias,id'
        ]);

        $instancia = CotioInstancia::findOrFail($validated['instancia_id']);
        
        // Obtener responsables con información completa, especificando tablas para evitar ambigüedad
        $responsables = $instancia->responsablesMuestreo()
            ->select('usu.usu_codigo', 'usu.usu_descripcion')
            ->get()
            ->map(function($responsable) {
                return [
                    'usu_codigo' => $responsable->usu_codigo,
                    'usu_descripcion' => $responsable->usu_descripcion
                ];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'responsables' => $responsables
        ]);

    } catch (\Exception $e) {
        Log::error('Error al obtener responsables de muestreo: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener responsables: ' . $e->getMessage()
        ]);
    }
}


}
