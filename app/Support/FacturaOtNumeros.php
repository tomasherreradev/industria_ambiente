<?php

namespace App\Support;

use App\Models\CotioInstancia;
use App\Models\Factura;

class FacturaOtNumeros
{
    /**
     * @return array<int, string>
     */
    public static function numerosParaFactura(Factura $factura): array
    {
        $wrapper = self::itemsWrapper($factura);
        $muestrasIds = $wrapper['muestras_ids'] ?? [];
        if (is_array($muestrasIds) && $muestrasIds !== []) {
            $ots = self::otsDesdeMuestrasIds($muestrasIds);
            if ($ots !== []) {
                return $ots;
            }
        }

        $analisisIds = $wrapper['analisis_ids'] ?? [];
        if (is_array($analisisIds) && $analisisIds !== []) {
            $ots = self::otsDesdeAnalisisIds($analisisIds);
            if ($ots !== []) {
                return $ots;
            }
        }

        $cotiNum = (int) ($factura->cotizacion_id ?? 0);
        if ($cotiNum <= 0) {
            return [];
        }

        return self::otsDeCotizacion($cotiNum);
    }

    /**
     * @return array<int, string>
     */
    public static function otsDeCotizacion(int $cotiNum): array
    {
        if ($cotiNum <= 0) {
            return [];
        }

        return CotioInstancia::query()
            ->where('cotio_numcoti', $cotiNum)
            ->where('cotio_subitem', 0)
            ->whereNotNull('otn')
            ->where('otn', '!=', '')
            ->orderBy('instance_number')
            ->orderBy('otn')
            ->pluck('otn')
            ->unique()
            ->values()
            ->all();
    }

    public static function lineaCotizOtHtml(Factura $factura, ?string $cotizDisplay): string
    {
        $cotizDisplay = $cotizDisplay !== null && $cotizDisplay !== '' ? $cotizDisplay : '—';
        $ots = self::numerosParaFactura($factura);

        if ($ots === []) {
            return self::lineaDesdeEnv($cotizDisplay);
        }

        $cotEsc = htmlspecialchars($cotizDisplay, ENT_QUOTES, 'UTF-8');
        if (count($ots) === 1) {
            return 'Cotiz.: ' . $cotEsc . ' OT: ' . htmlspecialchars($ots[0], ENT_QUOTES, 'UTF-8');
        }

        return 'Cotiz.: ' . $cotEsc . ' OTs: ' . htmlspecialchars(implode(' ', $ots), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function itemsWrapper(Factura $factura): array
    {
        $raw = $factura->items;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param  array<int|mixed>  $muestrasIds
     * @return array<int, string>
     */
    private static function otsDesdeMuestrasIds(array $muestrasIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $muestrasIds)));
        if ($ids === []) {
            return [];
        }

        return CotioInstancia::query()
            ->whereIn('id', $ids)
            ->where('cotio_subitem', 0)
            ->whereNotNull('otn')
            ->where('otn', '!=', '')
            ->orderBy('instance_number')
            ->orderBy('otn')
            ->pluck('otn')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int|mixed>  $analisisIds
     * @return array<int, string>
     */
    private static function otsDesdeAnalisisIds(array $analisisIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $analisisIds)));
        if ($ids === []) {
            return [];
        }

        $analisis = CotioInstancia::query()
            ->whereIn('id', $ids)
            ->where('cotio_subitem', '>', 0)
            ->get(['cotio_numcoti', 'instance_number']);

        if ($analisis->isEmpty()) {
            return [];
        }

        $ots = [];
        foreach ($analisis as $fila) {
            $otn = CotioInstancia::query()
                ->where('cotio_numcoti', $fila->cotio_numcoti)
                ->where('instance_number', $fila->instance_number)
                ->where('cotio_subitem', 0)
                ->value('otn');
            if ($otn !== null && trim((string) $otn) !== '') {
                $ots[] = trim((string) $otn);
            }
        }

        return array_values(array_unique($ots));
    }

    private static function lineaDesdeEnv(string $cotizDisplay): string
    {
        $cotEsc = htmlspecialchars($cotizDisplay, ENT_QUOTES, 'UTF-8');
        $otNumerosRaw = trim((string) env('FACTURA_OT_NUMEROS', ''));
        if ($otNumerosRaw !== '') {
            $otNumerosFmt = preg_replace('/\s*,\s*/', ' ', $otNumerosRaw);

            return 'Cotiz.: ' . $cotEsc . ' OTs: ' . htmlspecialchars((string) $otNumerosFmt, ENT_QUOTES, 'UTF-8');
        }

        $otSingle = trim((string) env('FACTURA_OT_NUMERO', ''));

        return 'Cotiz.: ' . $cotEsc . ' OT: ' . htmlspecialchars($otSingle !== '' ? $otSingle : '—', ENT_QUOTES, 'UTF-8');
    }
}
