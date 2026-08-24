<?php

namespace App\Support;

use App\Models\CotioItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cálculo unificado del resumen económico y cuotas de una cotización.
 * Alineado con ventas/create|edit (totales) y cotizaciones/showDetalle.
 */
final class CotizacionResumenEconomico
{
    private static ?Collection $agrupadoresPackPorDescripcion = null;

    private static function agrupadoresPackPorDescripcion(): Collection
    {
        if (self::$agrupadoresPackPorDescripcion === null) {
            self::$agrupadoresPackPorDescripcion = CotioItems::muestras()
                ->with('componentesAsociados:id')
                ->get()
                ->keyBy(fn ($item) => Str::lower(trim($item->cotio_descripcion ?? '')));
        }

        return self::$agrupadoresPackPorDescripcion;
    }

    /**
     * @return array{0: float, 1: bool}  [suma unitaria de componentes, es lógica pack agrupador]
     */
    private static function resolverSumaComponentesEnsayo(Collection $componentesDelEnsayo, object $ensayo, bool $esNuevaLogicaBase): array
    {
        $agrupador = self::agrupadoresPackPorDescripcion()->get(Str::lower(trim($ensayo->cotio_descripcion ?? '')));
        $idsPack = $agrupador
            ? $agrupador->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
            : [];
        $precioPack = max(0.0, (float) ($ensayo->cotio_precio ?? 0));
        $esPack = $esNuevaLogicaBase || ($precioPack > 0 && !empty($idsPack));
        $suma = CotizacionPrecioEnsayo::sumaComponentesUnitariaParaEnsayo($componentesDelEnsayo, $precioPack, $idsPack);

        return [$suma, $esPack];
    }

    /**
     * @param  iterable|\Illuminate\Support\Collection  $tareas  Filas cotio (ensayos + componentes)
     */
    public static function subtotalDesdeTareas(iterable $tareas): float
    {
        $collection = $tareas instanceof Collection ? $tareas : collect($tareas);
        $ensayos = $collection->where('cotio_subitem', 0);
        $componentes = $collection->where('cotio_subitem', '>', 0);

        $esNuevaLogicaBase = $collection->contains(function ($t) {
            return (bool) ($t->de_agrupador ?? false);
        });

        $total = 0.0;

        foreach ($ensayos as $ensayo) {
            $cantidadMuestras = (float) ($ensayo->cotio_cantidad ?? 1);
            if ($cantidadMuestras <= 0) {
                $cantidadMuestras = 1;
            }

            $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);
            [$sumaComponentesUnitaria, $esNuevaLogica] = self::resolverSumaComponentesEnsayo(
                $componentesDelEnsayo,
                $ensayo,
                $esNuevaLogicaBase
            );

            $precioUnitario = CotizacionPrecioEnsayo::precioUnitarioLineaEnsayo(
                (float) $sumaComponentesUnitaria,
                $ensayo->cotio_precio ?? null,
                $esNuevaLogica
            );

            $total += $cantidadMuestras * $precioUnitario;
        }

        $componentesSinEnsayo = $componentes->filter(function ($componente) use ($ensayos) {
            return !$ensayos->contains('cotio_item', $componente->cotio_item);
        });

        foreach ($componentesSinEnsayo as $componente) {
            $precio = (float) ($componente->cotio_precio ?? 0);
            $cantidad = (float) ($componente->cotio_cantidad ?? 1);
            $total += $precio * ($cantidad <= 0 ? 1 : $cantidad);
        }

        return round($total, 2);
    }

    /**
     * Total final = subtotal + aumento% − descuento global% − descuento sector%.
     *
     * @return array{
     *   subtotal: float,
     *   aumento_porcentaje: float,
     *   aumento_monto: float,
     *   descuento_global_porcentaje: float,
     *   descuento_global_monto: float,
     *   descuento_sector_porcentaje: float,
     *   descuento_sector_monto: float,
     *   descuento_total_porcentaje: float,
     *   descuento_total_monto: float,
     *   total_final: float
     * }
     */
    public static function calcular(
        iterable $tareas,
        float $aumentoPorcentaje = 0.0,
        float $descuentoGlobalPorcentaje = 0.0,
        float $descuentoSectorPorcentaje = 0.0,
    ): array {
        $subtotal = self::subtotalDesdeTareas($tareas);

        $aumentoPorcentaje = max(0.0, min($aumentoPorcentaje, 100.0));
        $descuentoGlobalPorcentaje = max(0.0, min($descuentoGlobalPorcentaje, 100.0));
        $descuentoSectorPorcentaje = max(0.0, min($descuentoSectorPorcentaje, 100.0));

        $aumentoMonto = round($subtotal * ($aumentoPorcentaje / 100), 2);
        $descuentoGlobalMonto = round($subtotal * ($descuentoGlobalPorcentaje / 100), 2);
        $descuentoSectorMonto = round($subtotal * ($descuentoSectorPorcentaje / 100), 2);
        $descuentoTotalMonto = round($descuentoGlobalMonto + $descuentoSectorMonto, 2);

        $totalFinal = round(
            $subtotal + $aumentoMonto - $descuentoGlobalMonto - $descuentoSectorMonto,
            2
        );

        return [
            'subtotal' => $subtotal,
            'aumento_porcentaje' => $aumentoPorcentaje,
            'aumento_monto' => $aumentoMonto,
            'descuento_global_porcentaje' => $descuentoGlobalPorcentaje,
            'descuento_global_monto' => $descuentoGlobalMonto,
            'descuento_sector_porcentaje' => $descuentoSectorPorcentaje,
            'descuento_sector_monto' => $descuentoSectorMonto,
            'descuento_total_porcentaje' => $descuentoGlobalPorcentaje + $descuentoSectorPorcentaje,
            'descuento_total_monto' => $descuentoTotalMonto,
            'total_final' => $totalFinal,
        ];
    }

    /**
     * Montos de cuotas a partir del total final del presupuesto (como ventas/edit).
     *
     * @return array{
     *   monto_total: float,
     *   monto_con_interes: float,
     *   monto_individual: float
     * }
     */
    public static function calcularCuotas(float $totalFinal, int $cantidadCuotas, float $interesPorcentaje = 0.0): array
    {
        $cant = max(1, $cantidadCuotas);
        $interes = max(0.0, $interesPorcentaje);
        $montoTotal = round($totalFinal, 2);
        $montoConInteres = round($montoTotal * (1 + $interes / 100), 2);
        $montoIndividual = round($montoConInteres / $cant, 2);

        return [
            'monto_total' => $montoTotal,
            'monto_con_interes' => $montoConInteres,
            'monto_individual' => $montoIndividual,
        ];
    }

    public static function textoCondicionPagoCuotas(
        ?string $descripcionCuota,
        ?int $cantidadCuotas,
        float $montoIndividual,
        float $montoTotal,
    ): string {
        $partes = [];
        $desc = trim((string) ($descripcionCuota ?? ''));

        if ($desc !== '') {
            $partes[] = $desc;
        }

        if ($cantidadCuotas !== null && $cantidadCuotas > 0) {
            $textoCuotas = $cantidadCuotas . ' cuotas de $' . number_format($montoIndividual, 2, ',', '.');
            $partes[] = $textoCuotas;
        } elseif ($montoIndividual > 0) {
            $partes[] = '$' . number_format($montoIndividual, 2, ',', '.');
        }

        if ($montoTotal > 0) {
            $partes[] = 'Total $' . number_format($montoTotal, 2, ',', '.');
        }

        if ($partes === []) {
            return 'Cuotas';
        }

        return 'Cuotas: ' . implode(' | ', $partes);
    }
}
