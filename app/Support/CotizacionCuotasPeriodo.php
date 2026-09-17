<?php

namespace App\Support;

use App\Models\Coti;
use App\Models\Ventas;
use Carbon\Carbon;

/**
 * Período de ejecución / facturación de cuotas (abono mensual).
 */
final class CotizacionCuotasPeriodo
{
    /**
     * Mes desde el cual corre la cuota 1.
     * Prioridad: fecha de inicio de ejecución → aprobación → alta.
     */
    public static function fechaInicioEjecucion(Coti|Ventas $cotizacion): Carbon
    {
        if ($cotizacion->coti_cuota_fecha_inicio) {
            return Carbon::parse($cotizacion->coti_cuota_fecha_inicio)->startOfMonth();
        }

        return Carbon::parse(
            $cotizacion->coti_fechaaprobado
            ?? $cotizacion->coti_fechaalta
            ?? now()
        )->startOfMonth();
    }

    public static function fechaFinEjecucion(Coti|Ventas $cotizacion): ?Carbon
    {
        if (! $cotizacion->coti_cuota_fecha_fin) {
            return null;
        }

        return Carbon::parse($cotizacion->coti_cuota_fecha_fin)->startOfMonth();
    }

    public static function fechaCuota(Coti|Ventas $cotizacion, int $numCuota): Carbon
    {
        return self::fechaInicioEjecucion($cotizacion)->copy()->addMonths(max(0, $numCuota - 1));
    }

    /**
     * Cantidad de meses inclusivos entre inicio y fin (mismo mes = 1).
     */
    public static function cantidadMesesEntre(?Carbon $inicio, ?Carbon $fin): ?int
    {
        if (! $inicio || ! $fin || $fin->lt($inicio)) {
            return null;
        }

        return $inicio->diffInMonths($fin) + 1;
    }

    public static function cuotaDentroDePeriodo(Coti|Ventas $cotizacion, int $numCuota): bool
    {
        $fin = self::fechaFinEjecucion($cotizacion);
        if (! $fin) {
            return true;
        }

        return self::fechaCuota($cotizacion, $numCuota)->startOfMonth()->lte($fin);
    }

    public static function cuotaEsFutura(Coti|Ventas $cotizacion, int $numCuota, ?Carbon $referencia = null): bool
    {
        $referencia = ($referencia ?? now())->copy()->startOfMonth();
        $fechaCuota = self::fechaCuota($cotizacion, $numCuota)->startOfMonth();

        return $fechaCuota->gt($referencia);
    }
}
