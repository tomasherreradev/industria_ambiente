<?php

namespace App\Http\Controllers;

use App\Models\Coti;
use App\Models\Cotio;
use App\Models\CotioInstancia;
use App\Support\CotizacionCanalEnsayo;
use App\Support\CotizacionClienteEtiqueta;
use App\Support\CotizacionReferenciasFacturacion;
use App\Support\ResumenFacturacionRevision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FacturacionRevisionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAcceso();

        \App\Support\TrabajoTecnicoCampo::sincronizarInstanciasMuestreadasPendientes();

        $query = CotioInstancia::with([
            'cotizacion.matriz',
            'cotizacion.cliente',
            'cotizacion.sucursal',
        ])
            ->where('enable_inform', true)
            ->where('facturacion_aprobada', false)
            ->where('facturado', false)
            ->where('cotio_subitem', 0);

        if ($request->filled('cotizacion')) {
            $query->where('cotio_numcoti', $request->cotizacion);
        }

        if ($request->filled('canal')) {
            $canalFiltro = strtolower(trim((string) $request->canal));
            if ($canalFiltro === 'laboratorio') {
                $query->whereHas('cotizacion.tareas', function ($q) {
                    $q->where('cotio_subitem', 0)
                        ->where(function ($inner) {
                            $inner->whereNull('cotio_canal_especial')
                                ->orWhere('cotio_canal_especial', '');
                        });
                });
            } else {
                $query->whereHas('cotizacion.tareas', function ($q) use ($canalFiltro) {
                    $q->where('cotio_subitem', 0)
                        ->where('cotio_canal_especial', $canalFiltro);
                });
            }
        }

        $pendientesTotal = (clone $query)->count();

        $muestrasPagination = $query
            ->orderBy('cotio_numcoti', $request->get('orden_cotizacion', 'desc'))
            ->orderBy('cotio_item')
            ->orderBy('instance_number')
            ->paginate(20);

        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
            $muestrasPagination->getCollection()->map->cotizacion->unique(fn ($c) => $c->coti_num)->filter()->values()
        );

        $muestrasPagination->getCollection()->transform(function (CotioInstancia $muestra) {
            $cotio = Cotio::query()
                ->where('cotio_numcoti', $muestra->cotio_numcoti)
                ->where('cotio_item', $muestra->cotio_item)
                ->where('cotio_subitem', 0)
                ->first();

            $canal = CotizacionCanalEnsayo::resolverCanalEnsayo(
                $cotio ?? $muestra,
                $muestra->cotizacion?->matriz?->matriz_descripcion
            );

            $muestra->setAttribute('canal_etiqueta', CotizacionCanalEnsayo::etiquetaCanalEnsayo($canal));

            return $muestra;
        });

        $muestrasPorCotizacion = $muestrasPagination->getCollection()->groupBy('cotio_numcoti')->map(function ($group) {
            $cotizacion = $group->first()->cotizacion;

            return [
                'cotizacion' => $cotizacion,
                'muestras' => $group->values(),
                'refs_facturacion' => $cotizacion
                    ? CotizacionReferenciasFacturacion::datosParaVista($cotizacion)
                    : [
                        'remito' => '',
                        'oc' => '',
                        'oc_obligatorio' => false,
                        'filas' => [],
                        'puede_facturar' => true,
                        'mensaje_bloqueo' => null,
                    ],
            ];
        });

        return view('facturacion-revision.index', [
            'muestrasPorCotizacion' => $muestrasPorCotizacion,
            'muestrasPagination' => $muestrasPagination,
            'pendientesTotal' => $pendientesTotal,
            'request' => $request,
        ]);
    }

    public function resumen(CotioInstancia $instancia)
    {
        $this->authorizeAcceso();

        if ((int) $instancia->cotio_subitem !== 0) {
            abort(404);
        }

        if (! $instancia->enable_inform) {
            abort(404, 'La muestra no está en informes.');
        }

        $resumen = ResumenFacturacionRevision::construir($instancia);
        $puedeAprobar = ! $instancia->facturacion_aprobada && ! $instancia->facturado;

        return view('facturacion-revision.partials.resumen-detalle', [
            'resumen' => $resumen,
            'instancia' => $instancia,
            'puedeAprobar' => $puedeAprobar,
        ]);
    }

    public function updateReferencias(Request $request, int $cotio_numcoti)
    {
        $this->authorizeAcceso();

        try {
            $input = [];
            if ($request->has('coti_oc_referencia')) {
                $input['coti_oc_referencia'] = $request->input('coti_oc_referencia');
            }
            if ($request->has('coti_refs_facturacion_json')) {
                $input['coti_refs_facturacion_json'] = $request->input('coti_refs_facturacion_json');
            }

            if ($input === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay datos para actualizar.',
                ], 422);
            }

            CotizacionReferenciasFacturacion::guardarReferenciasDesdeFacturacion($cotio_numcoti, $input);

            return response()->json([
                'success' => true,
                'message' => 'Referencias actualizadas correctamente',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar referencias: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function aprobar(CotioInstancia $instancia)
    {
        $this->authorizeAcceso();
        $this->validarInstanciaPendienteRevision($instancia);

        if ($errorRefs = $this->mensajeReferenciasIncompletas((int) $instancia->cotio_numcoti)) {
            return redirect()->back()->with('error', $errorRefs);
        }

        $this->marcarFacturacionAprobada($instancia);

        return redirect()
            ->back()
            ->with('success', 'Muestra aprobada para facturación.');
    }

    public function aprobarCotizacion(Request $request, int $cotio_numcoti)
    {
        $this->authorizeAcceso();

        $instancias = CotioInstancia::query()
            ->where('cotio_numcoti', $cotio_numcoti)
            ->where('enable_inform', true)
            ->where('facturacion_aprobada', false)
            ->where('facturado', false)
            ->where('cotio_subitem', 0)
            ->get();

        if ($instancias->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', 'No hay muestras pendientes de revisión para esta cotización.');
        }

        if ($errorRefs = $this->mensajeReferenciasIncompletas($cotio_numcoti)) {
            return redirect()->back()->with('error', $errorRefs);
        }

        foreach ($instancias as $instancia) {
            $this->marcarFacturacionAprobada($instancia);
        }

        return redirect()
            ->back()
            ->with('success', 'Se aprobaron ' . $instancias->count() . ' muestra(s) para facturación.');
    }

    private function authorizeAcceso(): void
    {
        if (Auth::user()?->puedeAutorizarFacturacion()) {
            return;
        }

        abort(403, 'No tiene permiso para acceder a la revisión de facturación.');
    }

    private function validarInstanciaPendienteRevision(CotioInstancia $instancia): void
    {
        if ((int) $instancia->cotio_subitem !== 0) {
            abort(422, 'Solo se pueden aprobar instancias de muestra.');
        }

        if (! $instancia->enable_inform || $instancia->facturacion_aprobada || $instancia->facturado) {
            abort(422, 'La muestra no está pendiente de revisión de facturación.');
        }
    }

    private function marcarFacturacionAprobada(CotioInstancia $instancia): void
    {
        $instancia->asignarOtnParaFacturacionSiPendiente();
        $instancia->facturacion_aprobada = true;
        $instancia->fecha_facturacion_aprobada = now();
        $instancia->facturacion_aprobada_usuario = trim((string) Auth::user()->usu_codigo);
        $instancia->save();
    }

    private function mensajeReferenciasIncompletas(int $cotioNumcoti): ?string
    {
        $cotizacion = Coti::find($cotioNumcoti);
        if (! $cotizacion) {
            return null;
        }

        $bloqueo = CotizacionReferenciasFacturacion::mensajeSiNoPuedeFacturar($cotizacion);
        if ($bloqueo === null) {
            return null;
        }

        return $bloqueo . ' Use «Referencias» en la cotización para completarlas antes de aprobar.';
    }
}
