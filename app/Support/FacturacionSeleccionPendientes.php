<?php

namespace App\Support;

use App\Models\Coti;
use App\Models\CotioInstancia;
use App\Models\Factura;
use Carbon\Carbon;

class FacturacionSeleccionPendientes
{
    public static function esModalidadCuotas(Coti $cotizacion): bool
    {
        if ($cotizacion->coti_cuotas) {
            return true;
        }

        return strtoupper(trim((string) ($cotizacion->coti_cond_pago ?? ''))) === 'CUOTAS';
    }

    /**
     * Cotizaciones con al menos una muestra (subitem 0) pendiente de facturar.
     *
     * @return list<int>
     */
    public static function numerosCotizacionConPendientes(?int $filtroCotizacion = null, bool $excluirModalidadCuotas = false): array
    {
        $query = CotioInstancia::query()
            ->where('enable_inform', true)
            ->where('facturacion_aprobada', true)
            ->where('facturado', false)
            ->where('cotio_subitem', 0);

        if ($excluirModalidadCuotas) {
            $query->whereHas('cotizacion', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('coti_cuotas', false)->orWhereNull('coti_cuotas');
                })->where(function ($inner) {
                    $inner->whereNull('coti_cond_pago')
                        ->orWhereRaw('UPPER(TRIM(coti_cond_pago)) <> ?', ['CUOTAS']);
                });
            });
        }

        if ($filtroCotizacion !== null && $filtroCotizacion > 0) {
            $query->where('cotio_numcoti', $filtroCotizacion);
        }

        return $query->distinct()
            ->orderBy('cotio_numcoti')
            ->pluck('cotio_numcoti')
            ->map(fn ($n) => (int) $n)
            ->values()
            ->all();
    }

    /**
     * Cotizaciones con condición de pago en cuotas (abono).
     *
     * @return list<int>
     */
    public static function numerosCotizacionModalidadCuotas(?int $filtroCotizacion = null): array
    {
        $query = Coti::query()
            ->where(function ($q) {
                $q->where('coti_cuotas', true)
                    ->orWhereRaw('UPPER(TRIM(coti_cond_pago)) = ?', ['CUOTAS']);
            });

        if ($filtroCotizacion !== null && $filtroCotizacion > 0) {
            $query->where('coti_num', $filtroCotizacion);
        }

        return $query->orderByDesc('coti_num')
            ->pluck('coti_num')
            ->map(fn ($n) => (int) $n)
            ->values()
            ->all();
    }

    /**
     * Selección equivalente a marcar todas las muestras pendientes con sus análisis (misma regla que el JS del formulario).
     *
     * @return array{muestras: list<int>, analisis: list<int>, cuotas: list<int>, vacio: bool}
     */
    public static function paraCotizacion(Coti $cotizacion, bool $soloCuotaCorriente = false): array
    {
        if (self::esModalidadCuotas($cotizacion)) {
            $cuotas = self::cuotasFacturables($cotizacion);
            if ($soloCuotaCorriente && $cuotas !== []) {
                $cuotas = [min($cuotas)];
            }

            return [
                'muestras' => [],
                'analisis' => [],
                'cuotas' => $cuotas,
                'vacio' => $cuotas === [],
            ];
        }

        $muestrasIds = [];
        $analisisIds = [];

        $muestrasPendientes = CotioInstancia::query()
            ->where('cotio_numcoti', $cotizacion->coti_num)
            ->where('enable_inform', true)
            ->where('facturacion_aprobada', true)
            ->where('facturado', false)
            ->where('cotio_subitem', 0)
            ->get();

        foreach ($muestrasPendientes as $muestra) {
            $analisisDisponibles = CotioInstancia::query()
                ->where('cotio_numcoti', $cotizacion->coti_num)
                ->where('cotio_item', $muestra->cotio_item)
                ->where('instance_number', $muestra->instance_number)
                ->where('cotio_subitem', '>', 0)
                ->where('enable_inform', true)
                ->where('facturado', false)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($analisisDisponibles === []) {
                $muestrasIds[] = (int) $muestra->id;
            } else {
                $muestrasIds[] = (int) $muestra->id;
                foreach ($analisisDisponibles as $aid) {
                    $analisisIds[] = $aid;
                }
            }
        }

        return [
            'muestras' => $muestrasIds,
            'analisis' => $analisisIds,
            'cuotas' => [],
            'vacio' => $muestrasIds === [] && $analisisIds === [],
        ];
    }

    /**
     * Cuotas habilitadas en pantalla de facturación (no facturadas, no futuras, dentro del período).
     *
     * @return list<int>
     */
    public static function cuotasFacturables(Coti $cotizacion): array
    {
        $facturas = Factura::where('cotizacion_id', $cotizacion->coti_num)->get();
        $cuotasFacturadas = [];

        foreach ($facturas as $f) {
            $itemsData = $f->items;
            if (is_string($itemsData)) {
                $itemsData = json_decode($itemsData, true);
            }
            $listaItems = $itemsData['items'] ?? [];
            foreach ($listaItems as $item) {
                if (($item['tipo'] ?? '') === 'cuota') {
                    if (preg_match('/Cuota (\d+) de/', (string) ($item['descripcion'] ?? ''), $matches)) {
                        $cuotasFacturadas[] = (int) $matches[1];
                    }
                }
            }
        }

        $total = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
        $hoyInicio = Carbon::now()->startOfMonth();
        $disponibles = [];

        for ($i = 1; $i <= $total; $i++) {
            if (in_array($i, $cuotasFacturadas, true)) {
                continue;
            }
            if (! CotizacionCuotasPeriodo::cuotaDentroDePeriodo($cotizacion, $i)) {
                continue;
            }
            if (CotizacionCuotasPeriodo::cuotaEsFutura($cotizacion, $i, $hoyInicio)) {
                continue;
            }
            $disponibles[] = $i;
        }

        return $disponibles;
    }

    /**
     * Primera cuota habilitada para facturar (mes en curso o vencido, no facturada).
     */
    public static function cuotaCorrienteFacturable(Coti $cotizacion): ?int
    {
        $disponibles = self::cuotasFacturables($cotizacion);

        return $disponibles === [] ? null : min($disponibles);
    }

    /**
     * @return array{
     *     total: int,
     *     facturadas: list<int>,
     *     corriente: ?int,
     *     corriente_facturable: bool,
     *     etiqueta_corriente: ?string,
     *     estado_corriente: string
     * }
     */
    public static function resumenCuotasEnListado(Coti $cotizacion): array
    {
        $mesesEs = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $total = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
        $facturadas = self::numerosCuotasYaFacturadas($cotizacion);
        $corriente = self::cuotaCorrienteFacturable($cotizacion);

        $proximaSinFacturar = null;
        for ($i = 1; $i <= $total; $i++) {
            if (! in_array($i, $facturadas, true)) {
                $proximaSinFacturar = $i;
                break;
            }
        }

        $numRef = $corriente ?? $proximaSinFacturar;
        $etiqueta = null;
        $estado = 'sin_cuotas_pendientes';

        if ($numRef !== null) {
            $fecha = CotizacionCuotasPeriodo::fechaCuota($cotizacion, $numRef);
            $mes = $mesesEs[(int) $fecha->format('n')] ?? $fecha->format('m');
            $etiqueta = "Cuota {$numRef} de {$total} ({$mes} {$fecha->format('Y')})";

            if ($corriente !== null) {
                $estado = 'lista';
            } elseif (CotizacionCuotasPeriodo::cuotaEsFutura($cotizacion, $numRef)) {
                $estado = 'futura';
            } elseif (! CotizacionCuotasPeriodo::cuotaDentroDePeriodo($cotizacion, $numRef)) {
                $estado = 'fuera_periodo';
            } else {
                $estado = 'no_habilitada';
            }
        } elseif ($facturadas !== []) {
            $estado = 'completa';
        }

        return [
            'total' => $total,
            'facturadas' => $facturadas,
            'corriente' => $corriente,
            'corriente_facturable' => $corriente !== null,
            'etiqueta_corriente' => $etiqueta,
            'estado_corriente' => $estado,
        ];
    }

    /**
     * @return list<int>
     */
    public static function numerosCuotasYaFacturadas(Coti $cotizacion): array
    {
        $facturas = Factura::where('cotizacion_id', $cotizacion->coti_num)->get();
        $cuotasFacturadas = [];

        foreach ($facturas as $f) {
            $itemsData = $f->items;
            if (is_string($itemsData)) {
                $itemsData = json_decode($itemsData, true);
            }
            $listaItems = $itemsData['items'] ?? [];
            foreach ($listaItems as $item) {
                if (($item['tipo'] ?? '') === 'cuota') {
                    if (preg_match('/Cuota (\d+) de/', (string) ($item['descripcion'] ?? ''), $matches)) {
                        $cuotasFacturadas[] = (int) $matches[1];
                    }
                }
            }
        }

        return array_values(array_unique($cuotasFacturadas));
    }

    public static function contarCotizacionesConCuotaFacturable(?int $filtroCotizacion = null): int
    {
        $total = 0;
        foreach (self::numerosCotizacionModalidadCuotas($filtroCotizacion) as $num) {
            $cot = Coti::find($num);
            if ($cot && self::cuotaCorrienteFacturable($cot) !== null) {
                $total++;
            }
        }

        return $total;
    }
}
