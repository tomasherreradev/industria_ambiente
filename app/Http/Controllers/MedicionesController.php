<?php

namespace App\Http\Controllers;

use App\Models\Coti;
use App\Models\CotioInstancia;
use App\Models\Matriz;
use App\Support\CotizacionCanalEnsayo;
use App\Support\CotizacionClienteEtiqueta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicionesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePortalMediciones();

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }

        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $vistaActiva = $this->resolverVistaMediciones($request);

        $baseQuery = $this->queryMedicionesIndex($request);
        $estadisticas = $this->calcularEstadisticasMedicionesIndex($baseQuery);

        $listQuery = clone $baseQuery;
        $this->aplicarFiltroVistaInformePdf($listQuery, $vistaActiva);

        $pagination = $listQuery
            ->orderBy('cotio_numcoti', $request->get('orden_cotizacion', 'desc'))
            ->orderBy('cotio_item')
            ->orderBy('instance_number')
            ->paginate(30)
            ->withQueryString();

        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
            $pagination->getCollection()->map->cotizacion->unique(fn ($c) => $c->coti_num)->filter()->values()
        );

        $medicionesPorCotizacion = $pagination->getCollection()
            ->groupBy('cotio_numcoti')
            ->map(function ($group) {
                $cotizacion = $group->first()->cotizacion;
                $sinSubir = $group->filter(fn ($m) => ! self::instanciaTieneInformePdf($m))->count();
                $subidos = $group->filter(fn ($m) => self::instanciaTieneInformePdf($m))->count();

                return [
                    'cotizacion' => $cotizacion,
                    'muestras' => $group->values(),
                    'sin_subir' => $sinSubir,
                    'subidos' => $subidos,
                ];
            });

        return view('mediciones.index', [
            'medicionesPorCotizacion' => $medicionesPorCotizacion,
            'pagination' => $pagination,
            'matrices' => $matrices,
            'request' => $request,
            'vistaActiva' => $vistaActiva,
            'estadisticas' => $estadisticas,
        ]);
    }

    public static function instanciaTieneInformePdf(CotioInstancia $instancia): bool
    {
        return trim((string) ($instancia->archivo_informe ?? '')) !== '';
    }

    public function show($coti_num, MuestrasController $muestrasController)
    {
        $this->authorizePortalMediciones();

        request()->merge(['portal' => 'mediciones', 'canal' => 'mediciones']);

        return $muestrasController->show($coti_num);
    }

    public function ver(Coti $cotizacion, $item, $instance, MuestrasController $muestrasController)
    {
        $this->authorizePortalMediciones();

        request()->merge(['portal' => 'mediciones', 'canal' => 'mediciones']);

        return $muestrasController->verMuestra($cotizacion, $item, $instance);
    }

    public function subirInforme(Request $request)
    {
        $this->authorizePortalMediciones();
        $request->validate([
            'cotio_numcoti' => 'required|string',
            'cotio_item' => 'required|string',
            'instance_number' => 'required|string',
            'informe_pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $instancia = $this->resolverInstanciaModuloMediciones($request);

        if ($request->hasFile('informe_pdf')) {
            if ($instancia->archivo_informe && Storage::disk('public')->exists($instancia->archivo_informe)) {
                Storage::disk('public')->delete($instancia->archivo_informe);
            }

            $file = $request->file('informe_pdf');
            $filename = time().'_'.$file->getClientOriginalName();
            $instancia->archivo_informe = $file->storeAs('informes_mediciones', $filename, 'public');
            $instancia->aprobado_informe = false;
            $instancia->fecha_aprobacion_informe = null;
            $instancia->aprobado_informe_usuario = null;
            $instancia->enable_inform = false;
            $instancia->save();

            CotioInstancia::where([
                'cotio_numcoti' => $request->cotio_numcoti,
                'cotio_item' => $request->cotio_item,
                'instance_number' => $request->instance_number,
            ])->where('cotio_subitem', '>', 0)->update([
                'aprobado_informe' => false,
                'fecha_aprobacion_informe' => null,
                'aprobado_informe_usuario' => null,
                'enable_inform' => false,
            ]);
        }

        return redirect()->back()->with('success', 'Informe subido correctamente.');
    }

    public function verInforme(Coti $cotizacion, $item, $instance): StreamedResponse
    {
        $this->authorizePortalMediciones();

        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $cotizacion->coti_num,
            'cotio_item' => $item,
            'cotio_subitem' => 0,
            'instance_number' => $instance,
        ])->firstOrFail();

        if (! $instancia->enable_modulo_mediciones) {
            abort(403);
        }

        $path = trim((string) ($instancia->archivo_informe ?? ''));
        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404, 'No hay informe cargado.');
        }

        return Storage::disk('public')->download($path);
    }

    public function eliminarInforme(Request $request)
    {
        $this->authorizePortalMediciones();

        $request->validate([
            'cotio_numcoti' => 'required|string',
            'cotio_item' => 'required|string',
            'instance_number' => 'required|string',
        ]);

        $instancia = $this->resolverInstanciaModuloMediciones($request);

        if ($instancia->archivo_informe && Storage::disk('public')->exists($instancia->archivo_informe)) {
            Storage::disk('public')->delete($instancia->archivo_informe);
        }

        $instancia->archivo_informe = null;
        $instancia->aprobado_informe = false;
        $instancia->fecha_aprobacion_informe = null;
        $instancia->aprobado_informe_usuario = null;
        $instancia->enable_inform = false;
        $instancia->save();

        return redirect()->back()->with('success', 'Informe eliminado.');
    }

    public function aprobarInforme(Request $request)
    {
        $this->authorizePortalMediciones();

        $request->validate([
            'cotio_numcoti' => 'required|string',
            'cotio_item' => 'required|string',
            'instance_number' => 'required|string',
        ]);

        $instancia = $this->resolverInstanciaModuloMediciones($request);

        if (trim((string) ($instancia->archivo_informe ?? '')) === '') {
            return redirect()->back()->with('error', 'Debe subir un informe antes de aprobarlo.');
        }

        DB::beginTransaction();
        try {
            $usuarioAprobador = auth()->user()->usu_codigo;

            $instancia->aprobado_informe = true;
            $instancia->fecha_aprobacion_informe = now();
            $instancia->aprobado_informe_usuario = $usuarioAprobador;
            $instancia->enable_inform = true;
            $instancia->save();

            CotioInstancia::where([
                'cotio_numcoti' => $request->cotio_numcoti,
                'cotio_item' => $request->cotio_item,
                'instance_number' => $request->instance_number,
            ])->where('cotio_subitem', '>', 0)->update([
                'aprobado_informe' => true,
                'fecha_aprobacion_informe' => now(),
                'aprobado_informe_usuario' => $usuarioAprobador,
                'enable_inform' => true,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Informe aprobado. La muestra pasó a informes.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al aprobar informe mediciones', ['error' => $e->getMessage()]);

            return redirect()->back()->with('error', 'No se pudo aprobar el informe.');
        }
    }

    private function resolverInstanciaModuloMediciones(Request $request): CotioInstancia
    {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $request->cotio_numcoti,
            'cotio_item' => $request->cotio_item,
            'cotio_subitem' => 0,
            'instance_number' => $request->instance_number,
        ])->firstOrFail();

        if (! $instancia->enable_modulo_mediciones) {
            abort(403, 'La muestra no está en el módulo de documentación.');
        }

        return $instancia;
    }

    private function authorizePortalMediciones(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return;
        }

        if ($user->hasAnyRole(['coordinador_mediciones', 'coordinador_muestreo'])) {
            return;
        }

        abort(403);
    }

    private function resolverVistaMediciones(Request $request): string
    {
        $vista = $request->get('vista');

        return in_array($vista, ['no_subidos', 'subidos'], true) ? $vista : 'no_subidos';
    }

    /**
     * @return Builder<CotioInstancia>
     */
    private function queryMedicionesIndex(Request $request): Builder
    {
        $query = CotioInstancia::query()
            ->with(['cotizacion.matriz', 'cotizacion.cliente', 'cotizacion.sucursal'])
            ->where('cotio_subitem', 0)
            ->where('enable_modulo_mediciones', true)
            ->whereHas('cotizacion', function ($q) use ($request) {
                $q->where(function ($inner) {
                    $inner->where('cancelada', false)->orWhereNull('cancelada');
                });

                if ($request->filled('estado')) {
                    $q->where('coti_estado', $request->estado);
                } elseif (! $request->query('verTodas')) {
                    $q->where('coti_estado', 'A');
                }

                if ($request->filled('search')) {
                    $searchTerms = explode(' ', trim((string) $request->search));
                    foreach ($searchTerms as $term) {
                        $term = trim($term);
                        if ($term === '') {
                            continue;
                        }
                        $likeTerm = '%'.strtolower($term).'%';
                        $termForNum = '%'.$term.'%';
                        $q->where(function ($subQuery) use ($likeTerm, $termForNum) {
                            $subQuery->where('coti_num', 'LIKE', $termForNum)
                                ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [$likeTerm])
                                ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [$likeTerm])
                                ->orWhereRaw('LOWER(coti_descripcion) LIKE ?', [$likeTerm]);
                        });
                    }
                }

                if ($request->filled('matriz')) {
                    $matriz = $request->matriz;
                    $q->whereHas('matriz', function ($mq) use ($matriz) {
                        $mq->where('matriz_descripcion', 'like', '%'.$matriz.'%')
                            ->orWhere('matriz_codigo', $matriz);
                    });
                }

                if ($request->filled('fecha_inicio_muestreo')) {
                    $q->whereDate('coti_fechaaprobado', '>=', $request->fecha_inicio_muestreo);
                }

                if ($request->filled('fecha_fin_muestreo')) {
                    $q->whereDate('coti_fechaaprobado', '<=', $request->fecha_fin_muestreo);
                }
            })
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('cotio')
                    ->whereColumn('cotio.cotio_numcoti', 'cotio_instancias.cotio_numcoti')
                    ->whereColumn('cotio.cotio_item', 'cotio_instancias.cotio_item')
                    ->where('cotio.cotio_subitem', 0);
                CotizacionCanalEnsayo::aplicarWhereCotioRowEsCanalMediciones($sub);
            });

        return $query;
    }

    /**
     * @param  Builder<CotioInstancia>  $query
     */
    private function aplicarFiltroVistaInformePdf(Builder $query, string $vista): void
    {
        if ($vista === 'subidos') {
            $query->whereNotNull('archivo_informe')->where('archivo_informe', '!=', '');
        } else {
            $query->where(function ($q) {
                $q->whereNull('archivo_informe')->orWhere('archivo_informe', '');
            });
        }
    }

    /**
     * @param  Builder<CotioInstancia>  $baseQuery
     * @return array{sin_subir: int, subidos: int, cotizaciones_sin_subir: int, cotizaciones_subidos: int}
     */
    private function calcularEstadisticasMedicionesIndex(Builder $baseQuery): array
    {
        $sinSubirQuery = clone $baseQuery;
        $this->aplicarFiltroVistaInformePdf($sinSubirQuery, 'no_subidos');

        $subidosQuery = clone $baseQuery;
        $this->aplicarFiltroVistaInformePdf($subidosQuery, 'subidos');

        return [
            'sin_subir' => $sinSubirQuery->count(),
            'subidos' => $subidosQuery->count(),
            'cotizaciones_sin_subir' => (int) (clone $sinSubirQuery)->distinct()->count('cotio_numcoti'),
            'cotizaciones_subidos' => (int) (clone $subidosQuery)->distinct()->count('cotio_numcoti'),
        ];
    }
}
