<?php

namespace App\Http\Controllers;

use App\Models\Coti;
use App\Models\CotioInstancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicionesController extends Controller
{
    public function index(Request $request, MuestrasController $muestrasController): View
    {
        $this->authorizePortalMediciones();

        return $muestrasController->portalListaMediciones($request);
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
}
