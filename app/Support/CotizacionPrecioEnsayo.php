<?php

namespace App\Support;

/**
 * Cálculo unificado del precio del ensayo respecto de sus analitos en cotización (cotio).
 */
final class CotizacionPrecioEnsayo
{
    /**
     * Interpreta cotio_precio del renglón ensayo (subitem 0):
     * - Nuevo: solo el adicional por unidad (precio extra del ensayo, sin analitos).
     * - Legado: a veces se guardaba el precio unitario total (= suma de componentes).
     */
    public static function resolverPrecioExtraEnsayoDesdeCotioRow(?float $cotioPrecioEnsayo, float $sumaComponentesUnitaria, bool $esNuevaLogica = false): float
    {
        $cotio = (float) ($cotioPrecioEnsayo ?? 0);
        if ($cotio <= 0) {
            return 0.0;
        }
        
        if ($esNuevaLogica) {
            return $cotio;
        }

        $suma = (float) $sumaComponentesUnitaria;
        if ($suma <= 0.00001) {
            return max(0.0, $cotio);
        }
        if (abs($cotio - $suma) < 0.02) {
            return 0.0;
        }
        // Legado: cotio_precio del ensayo era a veces el precio unitario total del pack (analitos + adicional),
        // típicamente claramente mayor que la suma de analitos sola.
        // Actual: se persiste solo el adicional, que puede ser > suma de analitos sin ser el total del pack.
        if ($cotio > $suma && $cotio > ($suma * 4.0 + 0.0001)) {
            return max(0.0, $cotio - $suma);
        }

        return max(0.0, $cotio);
    }

    /**
     * Suma unitaria de analitos que impactan el precio del ensayo.
     * Excluye parámetros del pack cuando el agrupador tiene precio definido.
     *
     * @param  iterable  $componentesDelEnsayo
     */
    public static function sumaComponentesUnitariaParaEnsayo(
        iterable $componentesDelEnsayo,
        ?float $precioPackEnsayo = null,
        array $idsParametrosPack = []
    ): float {
        $pack = max(0.0, (float) ($precioPackEnsayo ?? 0));
        $idsPack = array_map('strval', $idsParametrosPack);

        return collect($componentesDelEnsayo)->sum(function ($componente) use ($pack, $idsPack) {
            if ($componente->de_agrupador ?? false) {
                return 0.0;
            }
            if ($pack > 0 && !empty($idsPack)) {
                $analisisId = trim((string) ($componente->cotio_codigoprod ?? ''));
                if ($analisisId !== '' && in_array($analisisId, $idsPack, true)) {
                    return 0.0;
                }
            }
            $precio = (float) ($componente->cotio_precio ?? 0);
            $cantidad = (float) ($componente->cotio_cantidad ?? 1);

            return $precio * ($cantidad <= 0 ? 1 : $cantidad);
        });
    }

    /**
     * Precio unitario de la línea ensayo (por u.m.) = suma(analitos × cant.) + extra interpretado desde cotio_precio del ensayo.
     *
     * @param  float  $sumaComponentesUnitaria  Σ (precio × cantidad) de filas con cotio_subitem &gt; 0
     * @param  mixed  $cotioPrecioEnsayoRaw  cotio_precio del renglón subitem 0 (nullable)
     * @param  bool   $esNuevaLogica  Si es true, no intenta deducir si cotio_precio es el total (solo para agrupadores nuevos)
     */
    public static function precioUnitarioLineaEnsayo(float $sumaComponentesUnitaria, $cotioPrecioEnsayoRaw, bool $esNuevaLogica = false): float
    {
        $nullable = null;
        if ($cotioPrecioEnsayoRaw !== null && $cotioPrecioEnsayoRaw !== '') {
            $nullable = (float) $cotioPrecioEnsayoRaw;
        }

        return $sumaComponentesUnitaria + self::resolverPrecioExtraEnsayoDesdeCotioRow($nullable, $sumaComponentesUnitaria, $esNuevaLogica);
    }

    /**
     * Importe del bloque ensayo + analitos: analitos (suma de líneas hijas, una vez) + adicional del ensayo × cantidad de muestras.
     * Alineado con el pie de totales en ventas/create y con el detalle/PDF tras separar columnas.
     */
    public static function importeEnsayoMasAnalitos(float $cantidadMuestras, float $sumaComponentesUnitaria, $cotioPrecioEnsayoRaw, bool $esNuevaLogica = false): float
    {
        $cant = $cantidadMuestras > 0 ? $cantidadMuestras : 1.0;
        $nullable = null;
        if ($cotioPrecioEnsayoRaw !== null && $cotioPrecioEnsayoRaw !== '') {
            $nullable = (float) $cotioPrecioEnsayoRaw;
        }
        $extra = self::resolverPrecioExtraEnsayoDesdeCotioRow($nullable, $sumaComponentesUnitaria, $esNuevaLogica);

        return $sumaComponentesUnitaria + ($extra * $cant);
    }
}
