<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Coti;
use App\Models\Cotio;
use App\Models\Matriz;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Provincia;
use App\Models\Localidad;
use App\Models\CotioInstancia;
use App\Models\Clientes;
use App\Models\ClienteRazonSocialFacturacion;
use App\Models\Divis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CotiController extends Controller
{
    public function index(Request $request)
    {
        $verTodas = $request->query('verTodas');
        $viewType = $request->get('view', 'lista');
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $user = Auth::user();
        
        $cotizaciones = collect();
        $userToView = $request->get('user_to_view');
        $viewTasks = $request->get('view_tasks', false);
        $usuarios = collect();

        $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();

        $provincias = Provincia::orderBy('nombre')->get();

        if ($request->has('provincia') && !empty($request->provincia)) {
            $localidades = Localidad::where('provincia_id', function($q) use ($request) {
                $q->select('id')->from('provincias')->where('codigo', $request->provincia)->limit(1);
            })->orderBy('nombre')->get();
        } else {
            $localidades = collect(); 
        }

        if ($user->usu_nivel >= 900 && $viewType === 'calendario') {
            $usuarios = User::where('usu_estado', true)
                ->orderBy('usu_descripcion')
                ->get(['usu_codigo', 'usu_descripcion']);
        }
    
        if ($viewType === 'calendario') {
            if ($user->usu_nivel >= 900 && $viewTasks && $userToView) {
                return $this->showUserTasksCalendar($request, $userToView);
            }
            
            $query = Coti::with('matriz');
            
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = '%'.$request->search.'%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('coti_num', 'like', $searchTerm)
                      ->orWhereRaw('LOWER(coti_empresa) LIKE ?', ['%'.strtolower($searchTerm).'%'])
                      ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', ['%'.strtolower($searchTerm).'%']);
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
            
            if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
                $query->where(function($q) use ($request) {
                    $q->whereDate('coti_fechaalta', '>=', $request->fecha_inicio)
                      ->orWhereDate('coti_fechafin', '>=', $request->fecha_inicio);
                });
            }
    
            if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
                $query->where(function($q) use ($request) {
                    $q->whereDate('coti_fechaalta', '<=', $request->fecha_fin)
                      ->orWhereDate('coti_fechafin', '<=', $request->fecha_fin);
                });
            }

            if ($request->has('localidad') && !empty($request->localidad)) {
                $localidadBuscar = strtolower(str_replace(' ', '', $request->localidad));
                $query->whereRaw("REPLACE(LOWER(coti_localidad), ' ', '') = ?", [$localidadBuscar]);
            }
            
            
            $cotizaciones = $query->orderBy('coti_fechafin', 'asc')->get();
        
            $grouped = $cotizaciones->filter(fn($item) => !empty($item->coti_fechafin))
                ->groupBy(function($item) {
                    return \Carbon\Carbon::parse($item->coti_fechafin)->format('Y-m-d');
                });
            
            return view('cotizaciones.index', [
                'cotizaciones' => $grouped,
                'viewType' => $viewType,
                'request' => $request,
                'matrices' => $matrices,
                'userToView' => $userToView,
                'usuarios' => $usuarios,
                'viewTasks' => false,
                'currentMonth' => $currentMonth,
                'provincias' => $provincias,
                'localidades' => $localidades,
            ]);
        }
        
        $query = Coti::with(['matriz', 'responsable']);
        
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%'.$request->search.'%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('coti_num', 'like', $searchTerm)
                  ->orWhereRaw('LOWER(coti_empresa) LIKE ?', ['%'.strtolower($searchTerm).'%'])
                  ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', ['%'.strtolower($searchTerm).'%']);
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
        
        if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
            $query->whereDate('coti_fechaalta', '>=', $request->fecha_inicio);
        }
    
        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $query->whereDate('coti_fechaalta', '<=', $request->fecha_fin);
        }
        
        if ($request->has('provincia') && !empty($request->provincia)) {
            $provincia = Provincia::where('codigo', $request->provincia)->first();
            if ($provincia) {
                $query->where('coti_partido', 'like', '%' . $provincia->nombre . '%');
            }
        }
        
        if ($request->has('localidad') && !empty($request->localidad)) {
            $localidad = Localidad::where('codigo', $request->localidad)->first();
            if ($localidad) {
                $nombreLocalidad = strtolower(str_replace(' ', '', $localidad->nombre));
                $query->whereRaw("REPLACE(LOWER(coti_localidad), ' ', '') = ?", [$nombreLocalidad]);
            }
        }
        
        if (!empty($request->fecha_inicio) || !empty($request->fecha_fin)) {
            $query->orderBy('coti_fechaalta', 'desc');
        } else {
            $query->orderBy('coti_fechaaprobado', 'asc');
        }

        $cotizaciones = $query->paginate(20)->withQueryString();
        
        return view('cotizaciones.index', [
            'cotizaciones' => $cotizaciones,
            'viewType' => $viewType,
            'request' => $request,
            'matrices' => $matrices,
            'userToView' => $userToView,
            'usuarios' => $usuarios,
            'viewTasks' => false,
            'provincias' => $provincias,
            'localidades' => $localidades,
        ]);
    }




















    


    protected function showUserTasksCalendar(Request $request, $userCode)
    {
        $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
        
        $query = Cotio::with(['cotizacion', 'vehiculo'])
            ->where('cotio_subitem', '>', 0)
            // ->where('activo', true)
            ->where('cotio_responsable_codigo', trim($userCode)); 
        
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%'.$request->search.'%';
            $query->whereHas('cotizacion', function($q) use ($searchTerm) {
                $q->where('coti_num', 'LIKE', $searchTerm)
                  ->orWhere('coti_empresa', 'LIKE', $searchTerm)
                  ->orWhere('coti_establecimiento', 'LIKE', $searchTerm);
            });
        }
        
        $query->whereBetween('fecha_fin_muestreo', [
            $currentMonth->copy()->startOfMonth(),
            $currentMonth->copy()->endOfMonth()
        ]);
        
        $query->orderBy('fecha_fin_muestreo', 'asc');
        
        $tareas = $query->get();
        
        $cotizacionesIds = $tareas->pluck('cotio_numcoti')->unique();
        $cotizaciones = Coti::whereIn('coti_num', $cotizacionesIds)->get()->keyBy('coti_num');
        
        $tareasCalendario = $tareas->filter(fn($t) => !empty($t->fecha_fin_muestreo))
        ->mapToGroups(function($item) {
            return [Carbon::parse($item->fecha_fin_muestreo)->format('Y-m-d') => $item];
        })
        ->map(function($items) {
            return $items->sortBy('fecha_fin_muestreo');
        });


        return view('cotizaciones.partials.calendario', [
            'tareasCalendario' => $tareasCalendario,
            'cotizaciones' => collect(), 
            'viewType' => 'calendario',
            'request' => $request,
            'matrices' => Matriz::orderBy('matriz_descripcion')->get(),
            'userToView' => $userCode,
            'usuarios' => User::where('usu_estado', true)
                            ->orderBy('usu_descripcion')
                            ->get(['usu_codigo', 'usu_descripcion']),
            'viewTasks' => true,
            'currentMonth' => $currentMonth 
        ]);
    }


    
    

    
public function showTareas(Request $request)
{
    $user = Auth::user();
    $codigo = trim($user->usu_codigo);
    $viewType = $request->get('view', 'lista');
    $perPage = 50;
    $searchTerm = $request->get('search');
    $fechaInicio = $request->get('fecha_inicio_muestreo');
    $fechaFin = $request->get('fecha_fin_muestreo');
    $estado = $request->get('estado');
    
    $currentMonth = $request->get('month') 
        ? Carbon::parse($request->get('month')) 
        : now();

    // Verificar rol (incluye rol principal y roles adicionales)
    if (!$user->hasRole('muestreador')) {
        return redirect()->route('login')->with('error', 'Acceso denegado. Solo los muestreadores pueden ver estas tareas.');
    }

    // Consultas base - Modificadas para incluir muestreados cuando se filtra por ese estado
    $queryMuestras = CotioInstancia::with([
        'muestra.cotizado',
        'muestra.vehiculo',
        'vehiculo',
        'herramientas',
        'responsablesMuestreo',
        'tareas.responsablesAnalisis'
    ])->where('cotio_subitem', 0);

    $queryAnalisis = CotioInstancia::with([
        'tarea.cotizado',
        'tarea.vehiculo',
        'vehiculo',
        'herramientas',
        'responsablesAnalisis'
    ])->where('cotio_subitem', '>', 0);

    // Solo excluir muestreados si no estamos filtrando específicamente por ese estado
    // if ($estado !== 'muestreado') {
    //     $queryMuestras->where('cotio_estado', '!=', 'muestreado');
    //     $queryAnalisis->where('cotio_estado', '!=', 'muestreado');
    // }

    // Filtros y ordenamiento
    $queryMuestras->where('active_ot', false)
        ->where(function ($query) use ($codigo) {
            $query->whereHas('responsablesMuestreo', function ($q) use ($codigo) {
                $q->where('usu.usu_codigo', $codigo);
            })->orWhereHas('tareas', function ($q) use ($codigo) {
                $q->where('cotio_subitem', '>', 0)
                    ->where('active_ot', false)
                    ->where('active_muestreo', true)
                    ->whereHas('responsablesMuestreo', function ($subQ) use ($codigo) {
                        $subQ->where('usu.usu_codigo', $codigo);
                    });
            });
        })
        ->orderByRaw("CASE WHEN es_priori = true THEN 0 ELSE 1 END")
        ->orderBy('fecha_inicio_muestreo', 'desc')
        ->orderByRaw("CASE WHEN cotio_estado = 'coordinado muestreo' THEN 0 ELSE 1 END");

    $queryAnalisis->where('active_ot', false)
        ->whereHas('responsablesMuestreo', function ($q) use ($codigo) {
            $q->where('usu.usu_codigo', $codigo);
        })
        ->orderByRaw("CASE WHEN es_priori = true THEN 0 ELSE 1 END")
        ->orderBy('fecha_inicio_muestreo', 'desc');

    // Aplicar filtros de búsqueda
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

    // Filtros de fecha
    if ($fechaInicio) {
        $queryAnalisis->whereDate('fecha_inicio_muestreo', '>=', $fechaInicio);
        $queryMuestras->whereDate('fecha_inicio_muestreo', '>=', $fechaInicio);
    }
    if ($fechaFin) {
        $queryAnalisis->whereDate('fecha_fin_muestreo', '<=', $fechaFin);
        $queryMuestras->whereDate('fecha_fin_muestreo', '<=', $fechaFin);
    }

    // Aplicar filtro de estado si está presente
    if ($estado) {
        $queryMuestras->where('cotio_estado', $estado);
        $queryAnalisis->where('cotio_estado', $estado);
    }
    

    // Obtener datos
    $muestras = $queryMuestras->get();
    $todosAnalisis = $queryAnalisis->get();

    // Agrupar tareas
    $tareasAgrupadas = collect();

    if ($viewType === 'lista') {
        // Identificar muestras hermanas (mismo cotio_numcoti, cotio_item, cotio_subitem)
        $muestrasPorGrupo = $muestras->groupBy(function ($muestra) {
            return $muestra->cotio_numcoti . '_' . $muestra->cotio_item . '_' . $muestra->cotio_subitem;
        });

        // Agrupar por muestras hermanas o individuales
        foreach ($muestras as $muestra) {
            $grupoKey = $muestra->cotio_numcoti . '_' . $muestra->cotio_item . '_' . $muestra->cotio_subitem;
            $isHermana = $muestrasPorGrupo[$grupoKey]->count() > 1;
            $key = $isHermana ? $grupoKey : $muestra->cotio_numcoti . '_' . $muestra->instance_number . '_' . $muestra->cotio_item;

            if (!$tareasAgrupadas->has($key)) {
                $tareasAgrupadas->put($key, [
                    'is_hermana' => $isHermana,
                    'instancias' => collect(),
                    'cotizado' => $muestra->muestra->cotizado ?? null,
                ]);
            }

            $tareasAgrupadas[$key]['instancias']->push([
                'muestra' => $muestra->muestra,
                'instancia_muestra' => $muestra,
                'analisis' => collect(),
                'vehiculo' => $muestra->vehiculo ?? null,
                'responsables_muestreo' => $muestra->responsablesMuestreo
            ]);
        }

        // Asignar análisis a sus instancias correspondientes
        foreach ($todosAnalisis as $analisis) {
            // Los análisis tienen cotio_subitem > 0, pero las muestras tienen cotio_subitem = 0
            // Por lo tanto, necesitamos buscar la muestra correspondiente usando cotio_subitem = 0
            $muestraKey = $analisis->cotio_numcoti . '_' . $analisis->cotio_item . '_0';
            
            // Verificar si existe la muestra correspondiente en $muestrasPorGrupo
            if (!$muestrasPorGrupo->has($muestraKey)) {
                // Si no hay muestra correspondiente, saltar este análisis
                continue;
            }
            
            $isHermana = $muestrasPorGrupo[$muestraKey]->count() > 1;
            $key = $isHermana ? $muestraKey : $analisis->cotio_numcoti . '_' . $analisis->instance_number . '_' . $analisis->cotio_item;

            if ($tareasAgrupadas->has($key)) {
                $instancia = $tareasAgrupadas[$key]['instancias']
                    ->firstWhere('instancia_muestra.instance_number', $analisis->instance_number);
                
                if ($instancia) {
                    $instancia['analisis']->push($analisis);
                } else {
                    $relatedSample = CotioInstancia::where([
                        'cotio_numcoti' => $analisis->cotio_numcoti,
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number,
                        'cotio_subitem' => 0
                    ])->first();

                    if ($relatedSample) {
                        $tareasAgrupadas[$key]['instancias']->push([
                            'muestra' => $relatedSample->muestra,
                            'instancia_muestra' => $relatedSample,
                            'analisis' => collect([$analisis]),
                            'vehiculo' => $relatedSample->vehiculo ?? null,
                            'responsables_muestreo' => $relatedSample->responsablesMuestreo
                        ]);
                    }
                }
            }
        }
    }

    // Lógica original para todos los tipos de vista
    if ($viewType !== 'lista') {
        foreach ($muestras as $muestra) {
            $key = $muestra->cotio_numcoti . '_' . $muestra->instance_number . '_' . $muestra->cotio_item;
            $tareasAgrupadas->put($key, [
                'muestra' => $muestra->muestra,
                'instancia_muestra' => $muestra,
                'analisis' => collect(),
                'cotizado' => $muestra->muestra->cotizado ?? null,
                'vehiculo' => $muestra->vehiculo ?? null,
                'responsables_muestreo' => $muestra->responsablesMuestreo
            ]);
        }

        foreach ($todosAnalisis as $analisis) {
            $key = $analisis->cotio_numcoti . '_' . $analisis->instance_number . '_' . $analisis->cotio_item;
            if ($tareasAgrupadas->has($key)) {
                $tareasAgrupadas[$key]['analisis']->push($analisis);
                $tareasAgrupadas[$key]['analisis']->last()->responsables_analisis = $analisis->responsablesAnalisis;
            } else {
                $relatedSample = CotioInstancia::where([
                    'cotio_numcoti' => $analisis->cotio_numcoti,
                    'cotio_item' => $analisis->cotio_item,
                    'instance_number' => $analisis->instance_number,
                    'cotio_subitem' => 0
                ])->first();

                if ($relatedSample) {
                    $tareasAgrupadas->put($key, [
                        'muestra' => $relatedSample->muestra,
                        'instancia_muestra' => $relatedSample,
                        'analisis' => collect([$analisis]),
                        'cotizado' => $relatedSample->muestra->cotizado ?? null,
                        'vehiculo' => $relatedSample->vehiculo ?? null,
                        'responsables_muestreo' => $relatedSample->responsablesMuestreo
                    ]);
                    $tareasAgrupadas[$key]['analisis']->last()->responsables_analisis = $analisis->responsablesAnalisis;
                    Log::debug('Sample added for analysis', [
                        'key' => $key,
                        'analysis_id' => $analisis->id,
                        'sample_id' => $relatedSample->id
                    ]);
                }
            }
        }
    }

    // Obtener cotizaciones relacionadas
    $cotizacionesIds = $todosAnalisis->pluck('cotio_numcoti')
        ->merge($muestras->pluck('cotio_numcoti'))
        ->unique();
    $cotizaciones = Coti::whereIn('coti_num', $cotizacionesIds)->get()->keyBy('coti_num');

    // Paginación
    $allTasks = $muestras->merge($todosAnalisis)->values();
    $tareasPaginadas = new \Illuminate\Pagination\LengthAwarePaginator(
        $allTasks->forPage(\Illuminate\Pagination\Paginator::resolveCurrentPage(), $perPage),
        $allTasks->count(),
        $perPage,
        \Illuminate\Pagination\Paginator::resolveCurrentPage(),
        ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
    );

    if ($viewType === 'calendario') {
        $events = $muestras->map(function ($muestra) use ($user) {
            $descripcion = $muestra->cotio_descripcion ?? ($muestra->muestra->cotio_descripcion ?? 'Muestra sin descripción');
            $empresa = $muestra->muestra && $muestra->muestra->cotizacion 
                ? $muestra->muestra->cotizacion->coti_empresa 
                : '';
            
            $estado = strtolower($muestra->cotio_estado ?? 'pendiente');
            $className = match ($estado) {
                'pendiente', 'coordinado muestreo' => 'fc-event-warning',
                'en proceso', 'en revision muestreo' => 'fc-event-info',
                'muestreado' => 'fc-event-success',
                'suspension' => 'fc-event-danger',
                default => 'fc-event-primary'
            };

            return [
                'id' => $muestra->id,
                'title' => Str::limit($descripcion, 30),
                'start' => $muestra->fecha_inicio_muestreo,
                'end' => $muestra->fecha_fin_muestreo,
                'className' => $className,
                'url' => route('tareas.all.show', [
                    $muestra->cotio_numcoti ?? 'N/A', 
                    $muestra->cotio_item ?? 'N/A', 
                    $muestra->cotio_subitem ?? 'N/A', 
                    $muestra->instance_number ?? 'N/A'
                ]),
                'extendedProps' => [
                    'descripcion' => $descripcion,
                    'empresa' => $empresa,
                    'estado' => $estado,
                    'responsables' => $muestra->responsablesMuestreo->pluck('usu_nombre')->implode(', '),
                    'analisis_count' => $muestra->tareas->count()
                ]
            ];
        });

        return view('tareas.index', [
            'tareasAgrupadas' => $tareasAgrupadas,
            'cotizaciones' => $cotizaciones,
            'tareasPaginadas' => $tareasPaginadas,
            'events' => $events,
            'viewType' => $viewType,
            'request' => $request,
            'currentMonth' => $currentMonth
        ]);
    }

    return view('tareas.index', [
        'tareasAgrupadas' => $tareasAgrupadas,
        'cotizaciones' => $cotizaciones,
        'tareasPaginadas' => $tareasPaginadas,
        'tareasCalendario' => [],
        'muestras' => $muestras,
        'viewType' => $viewType,
        'request' => $request,
        'currentMonth' => $currentMonth
    ]);
}



    
    public function generateFullPdf($cotizacion)
    {
        $cotizacion = Coti::with(['tareas' => function($query) {
            $query->orderBy('cotio_item')
                  ->orderBy('cotio_subitem');
        }])->findOrFail($cotizacion);
    
        $agrupadas = [];
        $categoriaActual = null;
    
        foreach ($cotizacion->tareas as $tarea) {
            if ($tarea->cotio_subitem == 0) {
                $categoriaActual = $tarea;
                $agrupadas[] = [
                    'categoria' => $tarea,
                    'tareas' => []
                ];
            } else {
                if ($categoriaActual) {
                    $index = count($agrupadas) - 1;
                    $agrupadas[$index]['tareas'][] = $tarea;
                }
            }
        }
    
        $agrupadas = array_filter($agrupadas, function($grupo) {
            return collect($grupo['tareas'])->contains(function($tarea) {
                return $tarea->activo;
            });
        });
    
        $data = [
            'cotizacion' => $cotizacion,
            'agrupadas' => $agrupadas
        ];
    
        $pdf = Pdf::loadView('pdf.cotizacion-completa', $data);
        return $pdf->stream("cotizacion-{$cotizacion->coti_num}-completa.pdf");
    }
    


    public function printAllQr($coti_num)
    {
        $cotizacion = Coti::findOrFail($coti_num);
        
        $instancias = CotioInstancia::with(['muestra'])  
                      ->where('cotio_numcoti', $coti_num)
                      ->where('active_muestreo', true)
                      ->where('cotio_subitem', 0)
                      ->orderBy('cotio_item')
                      ->orderBy('instance_number')
                      ->get();
    
        return view('cotizaciones.print-all-qr', [
            'cotizacion' => $cotizacion,
            'instancias' => $instancias
        ]);
    }



    public function showDetalle($cotizacion, Request $request) {
        // Verificar si se solicita una versión específica
        $versionSolicitada = $request->get('version');
        $cotizacionModel = Coti::with(['cliente'])->findOrFail($cotizacion);
        
        $tareas = collect();
        
        if ($versionSolicitada && $versionSolicitada != $cotizacionModel->coti_version) {
            // Cargar versión histórica
            $versionHistorica = \App\Models\CotiVersion::where('coti_num', $cotizacion)
                ->where('version', $versionSolicitada)
                ->first();
            
            if ($versionHistorica) {
                // Obtener datos de la versión histórica
                // IMPORTANTE: coti_data y cotio_data pueden venir como string JSON o como array
                $cotiDataRaw = $versionHistorica->coti_data;
                $cotioDataRaw = $versionHistorica->cotio_data;
                
                // Decodificar coti_data si es string
                if (is_string($cotiDataRaw)) {
                    $cotiData = json_decode($cotiDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando coti_data en showDetalle', [
                            'coti_num' => $cotizacion,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg()
                        ]);
                        $cotiData = [];
                    }
                } else {
                    $cotiData = $cotiDataRaw ?? [];
                }
                
                // Decodificar cotio_data si es string
                if (is_string($cotioDataRaw)) {
                    $cotioData = json_decode($cotioDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando cotio_data en showDetalle', [
                            'coti_num' => $cotizacion,
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
                
                // Crear una instancia temporal de Coti con los datos de la versión
                $cotizacion = new Coti();
                $cotizacion->fill($cotiData);
                $cotizacion->coti_num = $cotizacionModel->coti_num; // Mantener el número original
                
                // Asegurar que el sector tenga el formato correcto (4 caracteres con padding)
                // Si es null, mantenerlo como null explícitamente
                if ($cotizacion->coti_sector !== null && trim($cotizacion->coti_sector) !== '') {
                    $cotizacion->coti_sector = str_pad(trim($cotizacion->coti_sector), 4, ' ', STR_PAD_RIGHT);
                } else {
                    $cotizacion->coti_sector = null;
                }
                
                // Cargar la relación cliente si existe
                if (isset($cotiData['coti_codigocli']) && $cotiData['coti_codigocli']) {
                    $cliente = \App\Models\Clientes::where('cli_codigo', trim($cotiData['coti_codigocli']))->first();
                    if ($cliente) {
                        $cotizacion->setRelation('cliente', $cliente);
                    }
                }
                
                // Log para debugging
                Log::info('Cargando versión histórica en showDetalle', [
                    'coti_num' => $cotizacion,
                    'version_solicitada' => $versionSolicitada,
                    'cotio_data_count' => count($cotioData),
                    'cotio_data_sample' => count($cotioData) > 0 
                        ? array_slice($cotioData, 0, 2) 
                        : []
                ]);
                
                // Limpiar la colección de tareas antes de cargar los items de la versión
                $tareas = collect();
                
                // Cargar items de la versión histórica
                foreach ($cotioData as $itemData) {
                    $cotio = new \App\Models\Cotio();
                    $cotio->fill($itemData);
                    $tareas->push($cotio);
                }
                
                Log::info('Items cargados en showDetalle', [
                    'tareas_count' => $tareas->count(),
                    'ensayos_count' => $tareas->where('cotio_subitem', 0)->count(),
                    'componentes_count' => $tareas->where('cotio_subitem', '>', 0)->count()
                ]);
            } else {
                // Si no se encuentra la versión, usar la actual
                $cotizacion = $cotizacionModel;
                $tareas = $cotizacion->tareas;
            }
        } else {
            // Cargar versión actual
        $cotizacion = Coti::with([
            'tareas' => function($query) {
                $query->orderBy('cotio_item')
                      ->orderBy('cotio_subitem');
            },
            'cliente'
        ])->findOrFail($cotizacion);
    
        // Obtener todas las tareas de la cotización
        $tareas = $cotizacion->tareas;
        }
    
        // Cargar instancias existentes con sus relaciones
        $instanciasExistentes = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
                                ->with(['responsablesMuestreo', 'muestra'])
                                ->get()
                                ->groupBy(['cotio_item', 'cotio_subitem', 'instance_number']);
    
        $agrupadas = [];
        $noRequierenMuestreo = [
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
        
        foreach ($tareas as $tarea) {
            if ($tarea->cotio_subitem == 0) { // Es una muestra
                
                $cantidad = $tarea->cotio_cantidad ?: 1;
                
                // Determinar si requiere muestreo
                $requiereMuestreo = in_array($tarea->cotio_descripcion, $noRequierenMuestreo);
    
                for ($i = 1; $i <= $cantidad; $i++) {
                    $instancia = $this->getOrCreateInstancia(
                        $tarea->cotio_numcoti,
                        $tarea->cotio_item,
                        0, // subitem 0 para muestras
                        $i,
                        $instanciasExistentes
                    );
    
                    $agrupadas[] = [
                        'muestra' => (object) array_merge($tarea->toArray(), [
                            'instance_number' => $i,
                            'original_item' => $tarea->cotio_item,
                            'display_item' => $tarea->cotio_item . '-' . $i,
                            'requiereMuestreo' => $requiereMuestreo
                        ]),
                        'instancia' => $instancia,
                        'analisis' => $this->getAnalisisForMuestra($tareas, $tarea->cotio_item, $i, $instanciasExistentes),
                        'responsables' => $instancia->responsablesMuestreo
                    ];
                }
            }
        }
    
        $cliente = $cotizacion->cliente;
        $sectorCodigoOriginal = $cotizacion->coti_sector ?: optional($cliente)->cli_codigocrub;
        $sectorCodigoNormalizado = $this->normalizarCodigoSector($sectorCodigoOriginal);

        $descuentoGlobalCliente = $cotizacion->coti_descuentoglobal ?: 0.0;
        
        // Obtener descuento de sector de la cotización según el sector normalizado
        $descuentoSectorCliente = 0.0;
        if ($sectorCodigoNormalizado) {
            $descuentoSectorCliente = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigoNormalizado);
        }
        
        // Si no hay descuento en la cotización, usar el del cliente
        if ($descuentoSectorCliente == 0.0 && $cliente) {
            $descuentoSectorCliente = $this->obtenerDescuentoSector($cliente, $sectorCodigoNormalizado);
        }
        
        $descuentoTotalCliente = $descuentoGlobalCliente + $descuentoSectorCliente;
        $sectorEtiqueta = $this->obtenerEtiquetaSector($sectorCodigoNormalizado) ?? $cotizacion->coti_sector ?? $sectorCodigoNormalizado;

        // Obtener empresa relacionada si existe
        $empresaRelacionada = null;
        if ($cotizacion->coti_cli_empresa) {
            $empresa = \App\Models\ClienteEmpresaRelacionada::find($cotizacion->coti_cli_empresa);
            if ($empresa) {
                $empresaRelacionada = $empresa;
            }
        }

        // Obtener razón social de facturación predeterminada si existe
        $razonSocialPredeterminada = null;
        if ($cliente) {
            $razonSocialPredeterminada = ClienteRazonSocialFacturacion::where('cli_codigo', $cliente->cli_codigo)
                ->where('es_predeterminada', true)
                ->first();
        }

        return view('cotizaciones.showDetalle', compact(
            'cotizacion',
            'tareas', // IMPORTANTE: Pasar $tareas a la vista para que use los items correctos de la versión
            'agrupadas',
            'descuentoGlobalCliente',
            'descuentoSectorCliente',
            'descuentoTotalCliente',
            'sectorEtiqueta',
            'empresaRelacionada',
            'razonSocialPredeterminada'
        ));
    }

    // Método auxiliar para obtener o crear instancia (similar al del método show)
    protected function getOrCreateInstancia($numcoti, $item, $subitem, $instanceNumber, $instanciasExistentes)
    {
        if (isset($instanciasExistentes[$item][$subitem][$instanceNumber])) {
            return $instanciasExistentes[$item][$subitem][$instanceNumber]->first();
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
            'instance_number' => $instanceNumber,
            'active_muestreo' => true
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
    
    // Método auxiliar para obtener análisis asociados a una muestra (similar al del método show)
    protected function getAnalisisForMuestra($tareas, $item, $instanceNumber, $instanciasExistentes)
    {
        $analisis = [];
        
        foreach ($tareas as $tarea) {
            if ($tarea->cotio_subitem > 0 && $tarea->cotio_item == $item) {
                $instancia = $this->getOrCreateInstancia(
                    $tarea->cotio_numcoti,
                    $tarea->cotio_item,
                    $tarea->cotio_subitem,
                    $instanceNumber,
                    $instanciasExistentes
                );
    
                $analisis[] = [
                    'tarea' => $tarea,
                    'instancia' => $instancia
                ];
            }
        }
    
        return $analisis;
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
}
