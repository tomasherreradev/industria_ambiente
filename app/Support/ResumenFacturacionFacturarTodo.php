<?php

namespace App\Support;

use App\Models\Coti;
use App\Models\CotioInstancia;

final class ResumenFacturacionFacturarTodo
{
    /**
     * @param  callable(Coti): array{puede_facturar: bool, motivo: ?string, seleccion: array}  $evaluarCotizacion
     * @return array{
     *     total_cotizaciones: int,
     *     total_facturas: int,
     *     items: list<array{
     *         cotizacion_id: int,
     *         cliente: string,
     *         matriz: string,
     *         lineas: list<string>,
     *         puede_facturar: bool,
     *         advertencia: ?string
     *     }>
     * }
     */
    /**
     * @param  'muestras'|'cuotas'  $alcance
     */
    public static function construir(?int $filtroCotizacion, callable $evaluarCotizacion, string $alcance = 'muestras'): array
    {
        $numeros = $alcance === 'cuotas'
            ? FacturacionSeleccionPendientes::numerosCotizacionModalidadCuotas($filtroCotizacion)
            : FacturacionSeleccionPendientes::numerosCotizacionConPendientes($filtroCotizacion, true);
        $items = [];
        $facturables = 0;

        $cotizaciones = Coti::query()
            ->with(['cliente', 'matriz'])
            ->whereIn('coti_num', $numeros)
            ->get()
            ->keyBy('coti_num');

        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($cotizaciones->values());

        foreach ($numeros as $cotiNum) {
            $cotizacion = $cotizaciones->get($cotiNum);
            if (! $cotizacion) {
                continue;
            }

            $eval = $evaluarCotizacion($cotizacion);
            $seleccion = $eval['seleccion'];
            $lineas = self::lineasDetalle($cotizacion, $seleccion);

            if ($eval['puede_facturar']) {
                $facturables++;
            }

            $items[] = [
                'cotizacion_id' => (int) $cotiNum,
                'cliente' => CotizacionClienteEtiqueta::paraLista($cotizacion),
                'matriz' => trim((string) (optional($cotizacion->matriz)->matriz_descripcion ?? '')) ?: '—',
                'lineas' => $lineas,
                'puede_facturar' => (bool) $eval['puede_facturar'],
                'advertencia' => $eval['motivo'],
            ];
        }

        return [
            'total_cotizaciones' => count($items),
            'total_facturas' => $facturables,
            'items' => $items,
        ];
    }

    /**
     * @param  array{muestras: list<int>, analisis: list<int>, cuotas: list<int>, vacio: bool}  $seleccion
     * @return list<string>
     */
    private static function lineasDetalle(Coti $cotizacion, array $seleccion): array
    {
        if ($seleccion['cuotas'] !== []) {
            $mesesEs = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];
            $total = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
            $lineas = [];
            foreach ($seleccion['cuotas'] as $numCuota) {
                $fecha = CotizacionCuotasPeriodo::fechaCuota($cotizacion, (int) $numCuota);
                $mes = $mesesEs[(int) $fecha->format('n')] ?? $fecha->format('m');
                $lineas[] = "Cuota {$numCuota} de {$total} ({$mes} {$fecha->format('Y')})";
            }

            return $lineas;
        }

        if ($seleccion['muestras'] === []) {
            return [];
        }

        $muestras = CotioInstancia::query()
            ->whereIn('id', $seleccion['muestras'])
            ->orderBy('cotio_item')
            ->orderBy('instance_number')
            ->get();

        $lineas = [];
        foreach ($muestras as $muestra) {
            $desc = trim((string) ($muestra->cotio_descripcion ?? ''));
            $id = trim((string) ($muestra->cotio_identificacion ?? ''));
            $partes = array_filter([
                $desc !== '' ? $desc : null,
                $id !== '' ? $id : null,
                '#'.$muestra->instance_number,
            ]);
            $lineas[] = implode(' · ', $partes);
        }

        return $lineas;
    }
}
