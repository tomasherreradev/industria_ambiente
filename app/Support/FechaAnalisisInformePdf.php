<?php

namespace App\Support;

use App\Models\CotioInstancia;
use Carbon\Carbon;

/**
 * Fecha de análisis mostrada en el informe PDF y vistas de OT.
 * Prioridad: fechas informe (finalización) → fechas informe (inicio) → fecha de carga de resultado.
 */
class FechaAnalisisInformePdf
{
    /**
     * Resuelve la fecha de carga de resultado (último resultado cargado disponible).
     */
    public static function fechaCargaResultado(CotioInstancia $item): ?Carbon
    {
        $fecha = $item->fecha_carga_ot
            ?? $item->fecha_carga_resultado_3
            ?? $item->fecha_carga_resultado_2
            ?? $item->fecha_carga_resultado_1;

        return $fecha ? Carbon::parse($fecha) : null;
    }

    /**
     * Texto para la columna «Fecha de análisis» del protocolo PDF.
     */
    public static function textoColumnaPdf(CotioInstancia $item): string
    {
        $inicio = $item->analista_fecha_inicio ?? null;
        $fin = $item->analista_fecha_fin ?? null;

        if ($fin && $inicio) {
            return Carbon::parse($inicio)->format('d/m/Y') . ' – ' . Carbon::parse($fin)->format('d/m/Y');
        }

        if ($fin) {
            return Carbon::parse($fin)->format('d/m/Y');
        }

        if ($inicio) {
            return Carbon::parse($inicio)->format('d/m/Y');
        }

        $carga = self::fechaCargaResultado($item);

        return $carga ? $carga->format('d/m/Y') : '—';
    }

    /**
     * Texto descriptivo para la OT (incluye aclaración cuando es fecha de carga).
     */
    public static function textoDescriptivoOt(CotioInstancia $item): string
    {
        $inicio = $item->analista_fecha_inicio ?? null;
        $fin = $item->analista_fecha_fin ?? null;

        if ($fin && $inicio) {
            return Carbon::parse($inicio)->format('d/m/Y') . ' – ' . Carbon::parse($fin)->format('d/m/Y');
        }

        if ($fin) {
            return Carbon::parse($fin)->format('d/m/Y');
        }

        if ($inicio) {
            return 'Desde ' . Carbon::parse($inicio)->format('d/m/Y');
        }

        $carga = self::fechaCargaResultado($item);

        return $carga
            ? $carga->format('d/m/Y') . ' (fecha de carga de resultado)'
            : '—';
    }
}
