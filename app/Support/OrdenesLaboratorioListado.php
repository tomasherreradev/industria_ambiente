<?php

namespace App\Support;

use App\Models\Coti;
use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Listado de órdenes de laboratorio: misma base de cotizaciones, filtros y muestras
 * relevantes que /ordenes (vista lista/documento).
 */
final class OrdenesLaboratorioListado
{
    public static function baseCotiQuery(Request $request): Builder
    {
        $verTodas = $request->query('ver_todas');

        $query = Coti::query()
            ->with(['matriz', 'tareas', 'instancias', 'cliente', 'sucursal'])
            ->where(function ($q) {
                $q->whereHas('instancias', function ($subQ) {
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
            })
            ->where(function ($q) {
                $q->whereHas('tareas', function ($subQ) {
                    $subQ->where('cotio_subitem', 0)
                        ->where(function ($subQ2) {
                            $subQ2->where('lleva_muestreo', false)
                                ->orWhereNull('lleva_muestreo');
                        });
                })
                    ->orWhereHas('instancias', function ($subQ) {
                        $subQ->where('cotio_subitem', 0)->where('enable_ot', true);
                    });
            })
            ->whereHas('tareas', function ($tareasQ) {
                $tareasQ->where('cotio_subitem', 0);
                CotizacionCanalEnsayo::aplicarWhereCotioEnsayoIncluidoEnOrdenes($tareasQ);
            });

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchTermLike = '%'.$searchTerm.'%';

            $query->where(function ($q) use ($searchTermLike) {
                $q->where('coti_num', 'like', $searchTermLike)
                    ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [strtolower($searchTermLike)])
                    ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [strtolower($searchTermLike)])
                    ->orWhereHas('instancias', function ($subQ) use ($searchTermLike) {
                        $subQ->where('otn', 'like', $searchTermLike);
                    });
            });
        }

        if ($request->filled('matriz')) {
            $query->where('coti_codigomatriz', $request->matriz);
        }

        if ($request->filled('fecha_inicio_ot')) {
            $query->whereDate('coti_fechaaprobado', '>=', $request->fecha_inicio_ot);
        }
        if ($request->filled('fecha_fin_ot')) {
            $query->whereDate('coti_fechaaprobado', '<=', $request->fecha_fin_ot);
        }

        if ($request->filled('estado')) {
            if ($request->estado === 'pendiente por coordinar') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('instancias', function ($subQ) {
                        $subQ->where('cotio_subitem', 0)->where('enable_ot', true);
                    })
                        ->orWhereHas('instancias', function ($subQ) {
                            $subQ->where('cotio_subitem', 0)
                                ->where('enable_ot', true)
                                ->whereNull('cotio_estado_analisis');
                        });
                });
            } else {
                $query->whereHas('instancias', function ($q) use ($request) {
                    $q->where('cotio_estado_analisis', $request->estado);
                });
            }
        } elseif (! $verTodas) {
            $query->where('coti_estado', 'A');
        }

        return $query;
    }

    /** @var list<string> */
    public const COLUMNAS_ORDEN = ['cotizacion', 'cliente', 'progreso', 'fecha', 'matriz'];

    public static function tieneOrdenExplicito(Request $request): bool
    {
        $sort = strtolower(trim((string) $request->query('sort', '')));

        return in_array($sort, self::COLUMNAS_ORDEN, true);
    }

    public static function aplicarOrdenListado(Builder $query, Request $request): void
    {
        $sort = strtolower(trim((string) $request->query('sort', '')));
        $dir = strtolower((string) $request->query('dir', '')) === 'desc' ? 'desc' : 'asc';

        if ($sort === ListadoProgresoOrden::COLUMNA) {
            return;
        }

        if (! in_array($sort, self::COLUMNAS_ORDEN, true)) {
            $query->orderBy('coti_num', 'desc');

            return;
        }

        match ($sort) {
            'cotizacion' => $query->orderBy('coti_num', $dir),
            'cliente' => $query->orderByRaw("LOWER(COALESCE(coti_empresa, '')) {$dir}"),
            'fecha' => $query->orderBy('coti_fechaaprobado', $dir),
            'matriz' => $query
                ->leftJoin('matriz as matriz_orden_listado', 'coti.coti_codigomatriz', '=', 'matriz_orden_listado.matriz_codigo')
                ->select('coti.*')
                ->orderBy('matriz_orden_listado.matriz_descripcion', $dir),
            default => $query->orderBy('coti_num', 'desc'),
        };

        if ($sort !== 'cotizacion') {
            $query->orderBy('coti_num', 'asc');
        }
    }

    /**
     * @return Collection<int, CotioInstancia>
     */
    public static function muestrasRelevantesParaCoti(Coti $coti): Collection
    {
        $instancias = $coti->instancias->where('cotio_subitem', 0);
        $matrizDescCoti = trim((string) (optional($coti->matriz)->matriz_descripcion ?? ''));
        $tareasMuestra = $coti->tareas
            ->where('cotio_subitem', 0)
            ->filter(function ($tarea) use ($matrizDescCoti) {
                return ! CotizacionCanalEnsayo::ensayoExcluidoDeOrdenes($tarea, $matrizDescCoti);
            });

        $muestrasRelevantes = collect();

        foreach ($tareasMuestra as $tarea) {
            if ($tarea->lleva_muestreo === false) {
                $cantidad = max(1, (int) $tarea->cotio_cantidad);
                for ($i = 1; $i <= $cantidad; $i++) {
                    $inst = $instancias->first(fn ($x) => $x->cotio_item == $tarea->cotio_item && $x->instance_number == $i);
                    if ($inst) {
                        $muestrasRelevantes->push($inst);
                    } else {
                        $muestrasRelevantes->push(new CotioInstancia([
                            'cotio_numcoti' => $coti->coti_num,
                            'cotio_item' => $tarea->cotio_item,
                            'cotio_subitem' => 0,
                            'instance_number' => $i,
                            'cotio_descripcion' => $tarea->cotio_descripcion,
                            'enable_ot' => false,
                            'cotio_estado_analisis' => null,
                        ]));
                    }
                }
            } else {
                foreach ($instancias->where('cotio_item', $tarea->cotio_item)->where('enable_ot', true) as $inst) {
                    $muestrasRelevantes->push($inst);
                }
            }
        }

        return $muestrasRelevantes;
    }

    /**
     * @param  iterable<Coti>  $cotizaciones
     */
    public static function construirOrdenesDesdeCotizaciones(iterable $cotizaciones, bool $preservarOrdenConsulta = false): Collection
    {
        $ordenes = collect();

        foreach ($cotizaciones as $coti) {
            $instancias = $coti->instancias->where('cotio_subitem', 0);
            $matrizDescCoti = trim((string) (optional($coti->matriz)->matriz_descripcion ?? ''));
            $tareasMuestra = $coti->tareas
                ->where('cotio_subitem', 0)
                ->filter(function ($tarea) use ($matrizDescCoti) {
                    return ! CotizacionCanalEnsayo::ensayoExcluidoDeOrdenes($tarea, $matrizDescCoti);
                });

            $muestrasRelevantes = self::muestrasRelevantesParaCoti($coti);

            if ($muestrasRelevantes->isEmpty()) {
                continue;
            }

            $total = $muestrasRelevantes->count();
            $completadas = $muestrasRelevantes->where('cotio_estado_analisis', 'analizado')->count();
            $enProceso = $muestrasRelevantes->where('cotio_estado_analisis', 'en revision analisis')->count();
            $coordinadas = $muestrasRelevantes->where('cotio_estado_analisis', 'coordinado analisis')->count();
            $porcentaje = $total > 0 ? round(($completadas / $total) * 100) : 0;
            $fechaOrden = $muestrasRelevantes->min('fecha_inicio_ot') ?? $muestrasRelevantes->min('fecha_muestreo');

            $hasPriority = PrioridadListado::grupoTienePrioridad($muestrasRelevantes, $coti, $tareasMuestra);
            $hasSuspension = $muestrasRelevantes->contains(function ($instancia) {
                return strtolower(trim($instancia->cotio_estado_analisis ?? '')) === 'suspension';
            });

            $estadoPredominante = 'pendiente_coordinar';
            $conEstado = $muestrasRelevantes->filter(fn ($i) => ! empty(trim($i->cotio_estado_analisis ?? '')));
            if ($conEstado->isNotEmpty()) {
                $estadoPredominante = self::determinarEstadoPredominanteConActiveOt($conEstado);
            }

            $ordenes[$coti->coti_num] = [
                'instancias' => $coti->instancias,
                'muestras_relevantes' => $muestrasRelevantes,
                'cotizacion' => $coti,
                'total' => $total,
                'completadas' => $completadas,
                'en_proceso' => $enProceso,
                'coordinadas' => $coordinadas,
                'porcentaje' => $porcentaje,
                'has_suspension' => $hasSuspension,
                'has_priority' => $hasPriority,
                'fecha_orden' => $fechaOrden,
                'estado_predominante' => $estadoPredominante,
                'tiene_instancias' => $instancias->isNotEmpty(),
            ];
        }

        if ($preservarOrdenConsulta) {
            return collect($cotizaciones)
                ->mapWithKeys(function (Coti $coti) use ($ordenes) {
                    $item = $ordenes->get($coti->coti_num);

                    return $item ? [$coti->coti_num => $item] : [];
                });
        }

        return $ordenes->sortBy(function ($orden) {
            return PrioridadListado::valorOrdenGrupo(
                $orden['has_priority'],
                self::pesoEstadoOrden($orden['estado_predominante'])
            );
        });
    }

    /**
     * @return Collection<int, CotioInstancia>
     */
    public static function muestrasDesdeOrdenes(Collection $ordenes): Collection
    {
        $muestras = collect();

        foreach ($ordenes as $orden) {
            foreach ($orden['muestras_relevantes'] as $muestra) {
                if (! $muestra->relationLoaded('cotizacion')) {
                    $muestra->setRelation('cotizacion', $orden['cotizacion']);
                }
                $muestras->push($muestra);
            }
        }

        return $muestras;
    }

    public static function normalizarEstadoFiltro(?string $estado): ?string
    {
        $estado = trim((string) ($estado ?? ''));

        if ($estado === '' || $estado === 'all') {
            return null;
        }

        if ($estado === 'pendientes_coordinar') {
            return 'pendiente por coordinar';
        }

        return $estado;
    }

    public static function determinarEstadoPredominante(Collection $estadosMuestras): string
    {
        if ($estadosMuestras->isEmpty()) {
            return 'pendiente_coordinar';
        }

        $estadosUnicos = $estadosMuestras->unique()->filter();

        if ($estadosUnicos->contains('suspension')) {
            return 'suspension';
        }

        if ($estadosMuestras->contains(null) || $estadosMuestras->contains('')) {
            return 'pendiente_coordinar';
        }

        if ($estadosUnicos->contains('coordinado analisis')) {
            return 'coordinado analisis';
        }

        if ($estadosUnicos->count() === 1 && $estadosUnicos->first() === 'en revision analisis') {
            return 'en revision analisis';
        }

        if ($estadosUnicos->count() === 1 && $estadosUnicos->first() === 'analizado') {
            return 'analizado';
        }

        if ($estadosUnicos->contains('en revision analisis')) {
            return 'en revision analisis';
        }

        return $estadosUnicos->first() ?? 'pendiente_coordinar';
    }

    /**
     * @param  Collection<int, CotioInstancia>  $muestras
     */
    public static function determinarEstadoPredominanteConActiveOt(Collection $muestras): string
    {
        if ($muestras->isEmpty()) {
            return 'pendiente_coordinar';
        }

        if ($muestras->contains(function ($muestra) {
            return strtolower(trim($muestra->cotio_estado_analisis ?? '')) === 'suspension';
        })) {
            return 'suspension';
        }

        if ($muestras->contains(function ($muestra) {
            return ! $muestra->active_ot;
        })) {
            return 'pendiente_coordinar';
        }

        return self::determinarEstadoPredominante($muestras->pluck('cotio_estado_analisis')->filter());
    }

    public static function pesoEstadoOrden(?string $estado): int
    {
        switch ($estado) {
            case 'pendiente_coordinar':
            case null:
            case '':
                return 10;
            case 'en revision analisis':
                return 20;
            case 'coordinado analisis':
                return 30;
            case 'analizado':
                return 40;
            case 'suspension':
                return 5;
            default:
                return 50;
        }
    }

    /**
     * La muestra tiene al menos un parámetro de laboratorio (subitem > 0) coordinable en OT.
     */
    public static function muestraTieneAnalisisLaboratorioParaCoordinar(CotioInstancia $muestra): bool
    {
        if (TrabajoTecnicoCampo::instanciaEsEnsayoIndependienteTrabajoTecnico($muestra)) {
            return false;
        }

        $cotizacion = $muestra->cotizacion;
        if (! $cotizacion) {
            return CotioInstancia::query()
                ->where('cotio_numcoti', $muestra->cotio_numcoti)
                ->where('cotio_item', $muestra->cotio_item)
                ->where('instance_number', $muestra->instance_number)
                ->where('cotio_subitem', '>', 0)
                ->exists();
        }

        $matrizDesc = trim((string) (optional($cotizacion->matriz)->matriz_descripcion ?? ''));

        return $cotizacion->tareas
            ->where('cotio_item', $muestra->cotio_item)
            ->where('cotio_subitem', '>', 0)
            ->contains(fn ($t) => ! CotizacionCanalEnsayo::ensayoExcluidoDeOrdenes($t, $matrizDesc));
    }

    /**
     * @param  Collection<string, Collection<int, CotioInstancia>>  $analisisActivosPorMuestra
     */
    public static function muestraEstaPendientePorCoordinar(CotioInstancia $muestra, Collection $analisisActivosPorMuestra): bool
    {
        if (! ($muestra->enable_ot ?? false)) {
            return false;
        }

        if (! self::muestraTieneAnalisisLaboratorioParaCoordinar($muestra)) {
            return false;
        }

        $key = $muestra->cotio_numcoti.'-'.$muestra->cotio_item.'-'.$muestra->instance_number;

        if (($muestra->active_ot ?? false) && $analisisActivosPorMuestra->has($key)) {
            return false;
        }

        return true;
    }

    /**
     * Análisis (instancias subitem > 0) a listar en «pendiente por coordinar».
     *
     * @param  Collection<int, CotioInstancia>  $instanciasAnalisisMuestra
     * @return Collection<int, CotioInstancia>
     */
    public static function filtrarAnalisisPendientesCoordinacionMuestra(
        CotioInstancia $muestra,
        Collection $instanciasAnalisisMuestra
    ): Collection {
        $matrizDesc = trim((string) (optional($muestra->cotizacion?->matriz)->matriz_descripcion ?? ''));
        $tareas = $muestra->cotizacion?->tareas ?? collect();

        return $instanciasAnalisisMuestra
            ->filter(function (CotioInstancia $a) use ($tareas, $matrizDesc) {
                $tarea = $tareas->first(
                    fn ($t) => (int) $t->cotio_item === (int) $a->cotio_item
                        && (int) $t->cotio_subitem === (int) $a->cotio_subitem
                );
                if ($tarea && CotizacionCanalEnsayo::ensayoExcluidoDeOrdenes($tarea, $matrizDesc)) {
                    return false;
                }

                if (! ($muestra->active_ot ?? false)) {
                    return true;
                }

                return ! ($a->active_ot ?? false);
            })
            ->values();
    }

    /**
     * @param  Collection<int, CotioInstancia>  $muestras
     * @return \Illuminate\Support\Collection<string, int>
     */
    public static function clavesMuestras(Collection $muestras): \Illuminate\Support\Collection
    {
        return $muestras->mapWithKeys(function (CotioInstancia $m) {
            $key = (int) $m->cotio_numcoti.'-'.(int) $m->cotio_item.'-'.(int) $m->instance_number;

            return [$key => true];
        });
    }

    /**
     * Restringe la query a las muestras indicadas (subitem 0). Usa whereIn por cotización + filtro en PHP cuando conviene.
     *
     * @param  Collection<int, CotioInstancia>  $muestras
     */
    public static function aplicarWhereInstanciasDeMuestras(Builder $query, Collection $muestras): void
    {
        if ($muestras->isEmpty()) {
            $query->whereRaw('0 = 1');

            return;
        }

        $cotizaciones = $muestras->pluck('cotio_numcoti')->unique()->filter()->values()->all();
        $query->whereIn('cotio_numcoti', $cotizaciones);

        $unicas = $muestras->unique(
            fn (CotioInstancia $m) => (int) $m->cotio_numcoti.'-'.(int) $m->cotio_item.'-'.(int) $m->instance_number
        )->values();

        $query->where(function ($outer) use ($unicas) {
            foreach ($unicas->chunk(40) as $chunk) {
                $outer->orWhere(function ($q) use ($chunk) {
                    foreach ($chunk as $muestra) {
                        $q->orWhere(function ($sub) use ($muestra) {
                            $sub->where('cotio_numcoti', $muestra->cotio_numcoti)
                                ->where('cotio_item', $muestra->cotio_item)
                                ->where('instance_number', $muestra->instance_number);
                        });
                    }
                });
            }
        });
    }

    /**
     * @param  Collection<int, CotioInstancia>  $instancias
     * @param  Collection<int, CotioInstancia>  $muestras
     * @return Collection<int, CotioInstancia>
     */
    public static function filtrarInstanciasPorMuestras(Collection $instancias, Collection $muestras): Collection
    {
        if ($muestras->isEmpty()) {
            return collect();
        }

        $claves = self::clavesMuestras($muestras);

        return $instancias->filter(function (CotioInstancia $instancia) use ($claves) {
            $key = (int) $instancia->cotio_numcoti.'-'.(int) $instancia->cotio_item.'-'.(int) $instancia->instance_number;

            return $claves->has($key);
        })->values();
    }

    public static function contarMuestrasPorEstado(Collection $muestras): array
    {
        return [
            'pendientes_coordinar' => $muestras->filter(function ($m) {
                $estado = trim((string) ($m->cotio_estado_analisis ?? ''));

                return $estado === '' || ! ($m->active_ot ?? false);
            })->count(),
            'coordinado analisis' => $muestras->where('cotio_estado_analisis', 'coordinado analisis')->count(),
            'en revision analisis' => $muestras->where('cotio_estado_analisis', 'en revision analisis')->count(),
            'analizado' => $muestras->where('cotio_estado_analisis', 'analizado')->count(),
            'anulados' => $muestras->filter(fn ($m) => (int) ($m->time_annulled ?? 0) > 0)->count(),
        ];
    }

    /**
     * Conteos por estado para el dashboard de análisis: cuenta muestras (instancias subitem 0),
     * no cada parámetro. Una muestra cuenta en un estado si tiene al menos un análisis activo en ese estado.
     *
     * @param  Collection<int, CotioInstancia>  $muestras
     */
    public static function contarAnalisisPorEstado(Collection $muestras, ?User $user = null): array
    {
        if ($muestras->isEmpty()) {
            return [
                'pendientes_coordinar' => 0,
                'coordinado analisis' => 0,
                'en revision analisis' => 0,
                'analizado' => 0,
                'anulados' => 0,
            ];
        }

        $filtrarPorSector = OrdenesAccesoPorSector::debeFiltrarPorSector($user);

        $queryAnalisis = CotioInstancia::query()
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->whereIn('cotio_numcoti', $muestras->pluck('cotio_numcoti')->unique()->filter()->values()->all());

        if ($filtrarPorSector) {
            OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($queryAnalisis, $user);
        }

        $analisisActivosPorMuestra = self::filtrarInstanciasPorMuestras(
            $queryAnalisis->get(['cotio_numcoti', 'cotio_item', 'instance_number', 'cotio_estado_analisis', 'time_annulled']),
            $muestras
        )->groupBy(fn ($a) => $a->cotio_numcoti.'-'.$a->cotio_item.'-'.$a->instance_number);

        $pendientesCoordinar = $filtrarPorSector ? 0 : $muestras->filter(
            fn ($m) => self::muestraEstaPendientePorCoordinar($m, $analisisActivosPorMuestra)
        )->count();

        $contarMuestrasConEstado = function (string $estado) use ($analisisActivosPorMuestra): int {
            return $analisisActivosPorMuestra->filter(
                fn ($grupo) => $grupo->contains(fn ($a) => ($a->cotio_estado_analisis ?? '') === $estado)
            )->count();
        };

        $muestrasConAnulados = $analisisActivosPorMuestra->filter(
            fn ($grupo) => $grupo->contains(fn ($a) => (int) ($a->time_annulled ?? 0) > 0)
        )->count();

        return [
            'pendientes_coordinar' => $pendientesCoordinar,
            'coordinado analisis' => $contarMuestrasConEstado('coordinado analisis'),
            'en revision analisis' => $contarMuestrasConEstado('en revision analisis'),
            'analizado' => $contarMuestrasConEstado('analizado'),
            'anulados' => $muestrasConAnulados,
        ];
    }
}
