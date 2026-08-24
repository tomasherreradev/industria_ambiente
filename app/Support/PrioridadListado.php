<?php

namespace App\Support;

/**
 * Prioridad en listados y coordinación: instancias coordinadas, ensayos en cotio (ventas) y flag global en coti.
 */
final class PrioridadListado
{
    /**
     * @param  object|null  $cotizacion  Coti/Ventas con coti_prioridad_global
     */
    public static function cotizacionPrioridadGlobal($cotizacion): bool
    {
        return (bool) ($cotizacion->coti_prioridad_global ?? false);
    }

    /**
     * Prioridad definida en ventas para un ensayo (cotio_subitem = 0).
     *
     * @param  object|null  $cotio  Cotio con es_priori
     * @param  object|null  $cotizacion
     */
    public static function cotioEnsayoEsPrioritaria($cotio, $cotizacion = null): bool
    {
        if (self::cotizacionPrioridadGlobal($cotizacion)) {
            return true;
        }

        return (bool) ($cotio->es_priori ?? false);
    }

    /**
     * @param  object  $instancia  CotioInstancia u objeto con es_priori
     */
    public static function instanciaEsPrioritaria($instancia): bool
    {
        return (bool) ($instancia->es_priori ?? false);
    }

    /**
     * Prioridad efectiva: instancia marcada en coordinación y/o ensayo marcado en ventas (cotio.es_priori).
     *
     * @param  object|null  $cotio
     * @param  object|null  $cotizacion
     * @param  object|null  $instancia
     */
    public static function prioridadEfectivaMuestreo($cotio, $cotizacion = null, $instancia = null): bool
    {
        if ($instancia !== null && self::instanciaEsPrioritaria($instancia)) {
            return true;
        }

        return self::cotioEnsayoEsPrioritaria($cotio, $cotizacion);
    }

    /**
     * Valor para heredar al coordinar si el usuario no marca el checkbox explícitamente.
     */
    public static function prioridadPorDefectoAlCoordinar($cotio, $cotizacion = null): bool
    {
        return self::cotioEnsayoEsPrioritaria($cotio, $cotizacion);
    }

    /**
     * @param  iterable<int, object>  $instancias
     * @param  object|null  $cotizacion
     * @param  iterable<int, object>|null  $cotiosEnsayo  Líneas cotio subitem 0 (lleva muestreo)
     */
    public static function grupoTienePrioridad(iterable $instancias, $cotizacion = null, ?iterable $cotiosEnsayo = null): bool
    {
        foreach ($instancias as $instancia) {
            if (self::instanciaEsPrioritaria($instancia)) {
                return true;
            }
        }

        if (self::cotizacionPrioridadGlobal($cotizacion)) {
            return true;
        }

        if ($cotiosEnsayo !== null) {
            foreach ($cotiosEnsayo as $cotio) {
                if ((int) ($cotio->cotio_subitem ?? -1) !== 0) {
                    continue;
                }
                if (self::cotioEnsayoEsPrioritaria($cotio, $cotizacion)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Valor ascendente para sortBy: menor = más arriba. Prioritarias siempre antes que el resto.
     */
    public static function valorOrdenGrupo(bool $esPrioritaria, int $pesoEstado): int
    {
        return ($esPrioritaria ? 0 : 100) + $pesoEstado;
    }

    /**
     * CASE para MIN(prioridad) de instancias de muestreo (cotio_estado) en listados por cotización.
     */
    public static function sqlCasePrioridadInstanciaMuestreo(string $alias = 'ci'): string
    {
        $est = "{$alias}.cotio_estado";

        return "CASE
            WHEN {$alias}.es_priori IS TRUE THEN 0
            WHEN LOWER(TRIM(COALESCE({$est}, ''))) = 'suspension' THEN 1
            WHEN {$est} IS NULL OR TRIM(COALESCE({$est}, '')) = '' THEN 2
            WHEN LOWER(TRIM(COALESCE({$est}, ''))) = 'en revision muestreo' THEN 3
            WHEN LOWER(TRIM(COALESCE({$est}, ''))) = 'coordinado muestreo' THEN 4
            WHEN LOWER(TRIM(COALESCE({$est}, ''))) IN ('muestreado', 'completado') THEN 5
            ELSE 6
        END";
    }

    /**
     * Orden SQL de cotizaciones en /muestras (y portales): prioritarias primero aunque falten instancias (1/3, etc.).
     */
    public static function sqlOrdenJerarquicoListaCotiMuestreo(): string
    {
        $minEstado = self::sqlCasePrioridadInstanciaMuestreo('ci');

        return "(
            CASE
                WHEN COALESCE(coti.coti_prioridad_global, false) IS TRUE THEN 0
                WHEN EXISTS (
                    SELECT 1 FROM cotio c_prio
                    WHERE c_prio.cotio_numcoti = coti.coti_num
                    AND c_prio.cotio_subitem = 0
                    AND c_prio.es_priori IS TRUE
                ) THEN 0
                WHEN EXISTS (
                    SELECT 1 FROM cotio_instancias ci_pri
                    WHERE ci_pri.cotio_numcoti = coti.coti_num
                    AND ci_pri.cotio_subitem = 0
                    AND ci_pri.es_priori IS TRUE
                ) THEN 0
                WHEN (
                    SELECT COALESCE(SUM(cotio_cantidad), 0)
                    FROM cotio
                    WHERE cotio_numcoti = coti.coti_num
                    AND cotio_subitem = 0
                    AND cotio_descripcion NOT IN (
                        'TRABAJO TECNICO EN CAMPO',
                        'TRABAJOS EN CAMPO NOCTURNO - VIATICOS',
                        'VIATICOS'
                    )
                ) > (
                    SELECT COUNT(*)
                    FROM cotio_instancias ci_count
                    WHERE ci_count.cotio_numcoti = coti.coti_num
                    AND ci_count.cotio_subitem = 0
                ) THEN 3
                ELSE COALESCE((
                    SELECT MIN({$minEstado})
                    FROM cotio_instancias ci
                    WHERE ci.cotio_numcoti = coti.coti_num
                    AND ci.cotio_subitem = 0
                ), 3)
            END
        )";
    }
}
