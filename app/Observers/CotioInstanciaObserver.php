<?php

namespace App\Observers;

use App\Models\CotioInstancia;
use App\Models\CotioHistorialCambios;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class CotioInstanciaObserver
{
    protected $campos = [
        'cotio_identificacion',
        'observaciones_medicion_muestreador',
        'observaciones_medicion_coord_muestreo',
        'observaciones_muestreo_coord',
        'observaciones_muestreo_muestreador',
        'resultado',
        'resultado_2',
        'resultado_3',
        'resultado_final',
        'fecha_carga_ot',
    ];

    /**
     * Handle the CotioInstancia "created" event.
     */
    public function created(CotioInstancia $cotioInstancia): void
    {
        // Registrar creación para campos que no sean null
        foreach ($this->campos as $campo) {
            if (!is_null($cotioInstancia->$campo)) {
                CotioHistorialCambios::create([
                    'tabla_afectada' => 'cotio_instancias',
                    'registro_id' => $cotioInstancia->id,
                    'campo_modificado' => $campo,
                    'valor_anterior' => null,
                    'valor_nuevo' => $cotioInstancia->$campo,
                    'usuario_id' => Auth::id() ?? null,
                    'fecha_cambio' => now(),
                    'ip_origen' => Request::ip(),
                    'accion' => 'create',
                ]);
            }
        }
    }

    /**
     * Handle the CotioInstancia "updated" event.
     */
    public function updated(CotioInstancia $cotioInstancia): void
    {
        if ($cotioInstancia->wasChanged('cotio_estado')) {
            \App\Support\TrabajoTecnicoCampo::aplicarSiCorresponde($cotioInstancia);
        }

        foreach ($this->campos as $campo) {
            if ($cotioInstancia->isDirty($campo)) {
                CotioHistorialCambios::create([
                    'tabla_afectada' => 'cotio_instancias',
                    'registro_id' => $cotioInstancia->id,
                    'campo_modificado' => $campo,
                    'valor_anterior' => $cotioInstancia->getOriginal($campo),
                    'valor_nuevo' => $cotioInstancia->$campo,
                    'usuario_id' => Auth::id() ?? null,
                    'fecha_cambio' => now(),
                    'ip_origen' => Request::ip(),
                    'accion' => 'update',
                ]);
            }
        }
    }

    /**
     * Handle the CotioInstancia "deleted" event.
     */
    public function deleted(CotioInstancia $cotioInstancia): void
    {
        // Registrar eliminación para campos que no sean null
        foreach ($this->campos as $campo) {
            if (!is_null($cotioInstancia->$campo)) {
                CotioHistorialCambios::create([
                    'tabla_afectada' => 'cotio_instancias',
                    'registro_id' => $cotioInstancia->id,
                    'campo_modificado' => $campo,
                    'valor_anterior' => $cotioInstancia->$campo,
                    'valor_nuevo' => null,
                    'usuario_id' => Auth::id() ?? null,
                    'fecha_cambio' => now(),
                    'ip_origen' => Request::ip(),
                    'accion' => 'delete',
                ]);
            }
        }
    }

    /**
     * Handle the CotioInstancia "restored" event.
     */
    public function restored(CotioInstancia $cotioInstancia): void
    {
        //
    }

    /**
     * Handle the CotioInstancia "force deleted" event.
     */
    public function forceDeleted(CotioInstancia $cotioInstancia): void
    {
        //
    }
}
