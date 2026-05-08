<?php

namespace App\Support;

use App\Models\Ventas;

class CotizacionReferenciasFacturacion
{
    public const TIPOS = ['REMITO', 'HES', 'HAS', 'GR', 'OTRO'];

    /**
     * @param  array<int, array{tipo?: string, valor?: string, obligatorio_factura?: bool}>  $rows
     * @return array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>
     */
    public static function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $tipo = strtoupper(trim((string) ($r['tipo'] ?? '')));
            if (! in_array($tipo, self::TIPOS, true)) {
                continue;
            }
            $valor = trim((string) ($r['valor'] ?? ''));
            $oblig = ! empty($r['obligatorio_factura']);
            $out[] = [
                'tipo' => $tipo,
                'valor' => $valor,
                'obligatorio_factura' => $oblig,
            ];
            if (count($out) >= 4) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>
     */
    public static function rowsFromAttributes(array $attrs): array
    {
        $json = $attrs['coti_refs_facturacion_json'] ?? null;
        if ($json !== null && $json !== '') {
            $decoded = is_string($json) ? json_decode($json, true) : $json;
            if (is_array($decoded) && count($decoded) > 0) {
                return self::normalizeRows($decoded);
            }
        }

        return self::legacyRowsFromAttributes($attrs);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|array<string, mixed>  $cot
     * @return array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>
     */
    public static function rowsFromModel($cot): array
    {
        $attrs = $cot instanceof \Illuminate\Database\Eloquent\Model ? $cot->getAttributes() : (array) $cot;

        return self::rowsFromAttributes($attrs);
    }

    /**
     * @return array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>
     */
    public static function legacyRowsFromAttributes(array $attrs): array
    {
        $rows = [];
        $refVal = trim((string) ($attrs['coti_referencia_valor'] ?? ''));
        if ($refVal !== '') {
            $rows[] = ['tipo' => 'REMITO', 'valor' => $refVal, 'obligatorio_factura' => false];
        }
        $hesTipo = strtoupper(trim((string) ($attrs['coti_hes_has_tipo'] ?? '')));
        $hesVal = trim((string) ($attrs['coti_hes_has_valor'] ?? ''));
        if (in_array($hesTipo, ['HES', 'HAS'], true) && $hesVal !== '') {
            $rows[] = ['tipo' => $hesTipo, 'valor' => $hesVal, 'obligatorio_factura' => false];
        }
        $grTipo = strtoupper(trim((string) ($attrs['coti_gr_contrato_tipo'] ?? '')));
        $grVal = trim((string) ($attrs['coti_gr_contrato'] ?? ''));
        if ($grVal !== '') {
            if ($grTipo === 'GR') {
                $rows[] = ['tipo' => 'GR', 'valor' => $grVal, 'obligatorio_factura' => false];
            } else {
                $pref = $grTipo === 'CONTRATO' ? 'Contrato: ' : '';
                $rows[] = ['tipo' => 'OTRO', 'valor' => $pref . $grVal, 'obligatorio_factura' => false];
            }
        }
        $otro = trim((string) ($attrs['coti_otro_referencia'] ?? ''));
        if ($otro !== '') {
            $rows[] = ['tipo' => 'OTRO', 'valor' => $otro, 'obligatorio_factura' => false];
        }

        return array_slice(self::normalizeRows($rows), 0, 4);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validarRequest(\Illuminate\Http\Request $request): array
    {
        $errors = [];
        // Se permite guardar la coti con OC obligatoria vacía (se exigirá al facturar)
        /*
        if ($request->boolean('coti_oc_requerido_factura')) {
            if (trim((string) $request->input('coti_oc_referencia', '')) === '') {
                $errors['coti_oc_referencia'] = ['Si marca O.C. obligatoria para facturar, debe ingresar el número.'];
            }
        }
        */
        /*
        $rows = self::parseRowsFromRequest($request);
        foreach ($rows as $idx => $r) {
            if (! empty($r['obligatorio_factura']) && $r['valor'] === '') {
                $n = $idx + 1;
                $tipo = $r['tipo'];
                $errors['coti_refs_facturacion_json'] = $errors['coti_refs_facturacion_json'] ?? [];
                $errors['coti_refs_facturacion_json'][] = "La referencia {$n} ({$tipo}) está marcada como obligatoria para facturar pero no tiene valor.";
            }
        }
        */

        return $errors;
    }

    /**
     * @return array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>
     */
    public static function parseRowsFromRequest(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('coti_refs_facturacion_json');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? self::normalizeRows($decoded) : [];
    }

    public static function mensajeSiNoPuedeFacturar($cot): ?string
    {
        $attrs = $cot instanceof \Illuminate\Database\Eloquent\Model ? $cot->getAttributes() : (array) $cot;

        if (! empty($attrs['coti_oc_requerido_factura'])) {
            if (trim((string) ($attrs['coti_oc_referencia'] ?? '')) === '') {
                return 'La cotización exige orden de compra (O.C.) para facturar, pero el número no está cargado.';
            }
        }
        $rows = self::rowsFromAttributes($attrs);
        foreach ($rows as $r) {
            if (! empty($r['obligatorio_factura']) && $r['valor'] === '') {
                $tipo = $r['tipo'];

                return "La cotización exige completar la referencia ({$tipo}) para facturar.";
            }
        }

        return null;
    }

    /**
     * Primera línea REMITO con valor (compatibilidad con columna Remito de la factura).
     */
    public static function textoPrimerRemitoParaFactura($cot): string
    {
        foreach (self::rowsFromModel($cot) as $r) {
            if ($r['tipo'] === 'REMITO' && $r['valor'] !== '') {
                return $r['valor'];
            }
        }

        return trim((string) ($cot->coti_referencia_valor ?? ''));
    }

    /**
     * HTML: referencias adicionales para la factura (excluye el primer REMITO ya mostrado en columna Remito).
     */
    public static function htmlReferenciasExtraParaFactura($cot): string
    {
        $rows = self::rowsFromModel($cot);
        $parts = [];
        $primerRemitoOmitido = false;
        foreach ($rows as $r) {
            if ($r['valor'] === '') {
                continue;
            }
            if ($r['tipo'] === 'REMITO' && ! $primerRemitoOmitido) {
                $primerRemitoOmitido = true;

                continue;
            }
            $lbl = $r['tipo'];
            $parts[] = '<span class="lbl">' . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . ':</span> '
                . htmlspecialchars($r['valor'], ENT_QUOTES, 'UTF-8');
        }

        return $parts !== [] ? implode(' &nbsp; ', $parts) : '';
    }

    /**
     * @param  array<int, array{tipo: string, valor: string, obligatorio_factura: bool}>  $rows
     */
    public static function sincronizarColumnasLegacyDesdeFilas(Ventas $cotizacion, array $rows): void
    {
        $cotizacion->coti_referencia_tipo = null;
        $cotizacion->coti_referencia_valor = null;
        $cotizacion->coti_hes_has_tipo = null;
        $cotizacion->coti_hes_has_valor = null;
        $cotizacion->coti_gr_contrato_tipo = null;
        $cotizacion->coti_gr_contrato = null;
        $cotizacion->coti_otro_referencia = null;

        if ($rows === []) {
            return;
        }

        $remitoHecho = false;
        $hesHecho = false;
        $grHecho = false;
        $otros = [];

        foreach ($rows as $r) {
            $tipo = $r['tipo'];
            $val = $r['valor'];
            if ($val === '') {
                continue;
            }
            if ($tipo === 'REMITO' && ! $remitoHecho) {
                $cotizacion->coti_referencia_tipo = 'remito';
                $cotizacion->coti_referencia_valor = $val;
                $remitoHecho = true;

                continue;
            }
            if (($tipo === 'HES' || $tipo === 'HAS') && ! $hesHecho) {
                $cotizacion->coti_hes_has_tipo = $tipo;
                $cotizacion->coti_hes_has_valor = $val;
                $hesHecho = true;

                continue;
            }
            if ($tipo === 'GR' && ! $grHecho) {
                $cotizacion->coti_gr_contrato_tipo = 'GR';
                $cotizacion->coti_gr_contrato = $val;
                $grHecho = true;

                continue;
            }
            if ($tipo === 'OTRO') {
                $otros[] = $val;
            }
        }
        if ($otros !== []) {
            $cotizacion->coti_otro_referencia = implode(' | ', $otros);
        }
    }
}
