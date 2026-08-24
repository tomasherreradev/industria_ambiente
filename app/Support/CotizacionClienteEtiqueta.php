<?php

namespace App\Support;

use App\Models\ClienteEmpresaRelacionada;

/**
 * Texto unificado para columnas "Cliente" en listados (ventas, muestras, órdenes).
 * Cuando coti_para_empresa_rel es true y hay empresa relacionada: "cliente - empresa rel.".
 */
final class CotizacionClienteEtiqueta
{
    /**
     * Precarga empresas relacionadas en una sola consulta y las asocia como relación
     * `empresaRelacionadaListaResuelta` (evita N+1 cuando coti_empresa_rel y coti_cli_empresa difieren del FK de Eloquent).
     *
     * @param  iterable<int, object>  $cotizaciones
     */
    public static function precargarEmpresasRelacionadas(iterable $cotizaciones): void
    {
        $coll = collect($cotizaciones)->filter();
        if ($coll->isEmpty()) {
            return;
        }

        $ids = $coll->map(function ($c) {
            return (int) ($c->coti_empresa_rel ?? $c->coti_cli_empresa ?? 0);
        })->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $map = ClienteEmpresaRelacionada::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($coll as $c) {
            $id = (int) ($c->coti_empresa_rel ?? $c->coti_cli_empresa ?? 0);
            if ($id > 0 && $map->has($id)) {
                $c->setRelation('empresaRelacionadaListaResuelta', $map->get($id));
            }
        }
    }

    /**
     * Etiqueta titular + sucursal para listados, p. ej. "INDUSTRIAS GUIDI SACIF - Burzaco".
     */
    public static function etiquetaSucursalConTitular(object $sucursal, ?string $titular = null): string
    {
        $nombre = self::nombreSucursal($sucursal);
        $titular = trim((string) ($titular ?? ''));

        if ($titular !== '') {
            return $titular . ' - ' . $nombre;
        }

        return $nombre !== '' ? $nombre : 'Sucursal';
    }

    public static function paraLista(object $coti): string
    {
        $cliente = $coti->relationLoaded('cliente') ? $coti->cliente : ($coti->cliente ?? null);

        $base = self::razonSocialTitular($coti);

        if ($coti->relationLoaded('sucursal') && $coti->sucursal) {
            return self::etiquetaSucursalConTitular($coti->sucursal, $base);
        }

        $sucursal = self::resolverSucursal($coti);
        if ($sucursal) {
            return self::etiquetaSucursalConTitular($sucursal, $base);
        }

        $relNombre = self::relacionResueltaNombre($coti);
        $flag = (bool) ($coti->coti_para_empresa_rel ?? false);

        if ($flag && $relNombre !== '') {
            return $base !== '' ? ($base . ' - ' . $relNombre) : $relNombre;
        }

        $snap = trim((string) ($coti->coti_empresa ?? ''));

        return $snap !== '' ? $snap : ($base !== '' ? $base : ($relNombre !== '' ? $relNombre : '—'));
    }

    /**
     * Línea "Cliente" con establecimiento manual opcional (documentos, QR, PDFs compactos).
     */
    public static function lineaClienteConEstablecimiento(object $coti): string
    {
        $coti->loadMissing(['cliente', 'sucursal']);
        $linea = self::paraLista($coti);
        $estab = trim((string) ($coti->coti_establecimiento ?? ''));
        if ($estab !== '' && stripos($linea, $estab) === false) {
            return $linea . ' - ' . $estab;
        }

        return $linea !== '' ? $linea : '—';
    }

    private static function relacionResueltaNombre(object $coti): string
    {
        $er = self::empresaRelacionadaResuelta($coti);

        return $er ? trim((string) ($er->razon_social ?? '')) : '';
    }

    public static function empresaRelacionadaResuelta(object $coti): ?ClienteEmpresaRelacionada
    {
        if ($coti->relationLoaded('empresaRelacionadaListaResuelta') && $coti->getRelation('empresaRelacionadaListaResuelta')) {
            return $coti->getRelation('empresaRelacionadaListaResuelta');
        }

        $idWanted = (int) ($coti->coti_empresa_rel ?? $coti->coti_cli_empresa ?? 0);

        if ($coti->relationLoaded('empresaRelacionada') && $coti->empresaRelacionada && $idWanted > 0
            && (int) $coti->empresaRelacionada->getKey() === $idWanted) {
            return $coti->empresaRelacionada;
        }

        if ($idWanted > 0) {
            return ClienteEmpresaRelacionada::find($idWanted);
        }

        return null;
    }

    /**
     * Dirección del destinatario del trabajo (misma prioridad que en PDF: sucursal → empresa rel. → coti_*).
     *
     * @return array{direccion: string, localidad: string, partido: string}
     */
    public static function direccionDestinatarioPartes(?object $coti): array
    {
        $empty = ['direccion' => '', 'localidad' => '', 'partido' => ''];
        if (!$coti) {
            return $empty;
        }

        $sucursal = self::resolverSucursal($coti);
        if ($sucursal) {
            return [
                'direccion' => trim((string) ($sucursal->cli_direccion ?? '')),
                'localidad' => trim((string) ($sucursal->cli_localidad ?? '')),
                'partido' => trim((string) ($sucursal->cli_partido ?? '')),
            ];
        }

        $emp = self::empresaRelacionadaResuelta($coti);
        if ($emp) {
            $d = trim((string) ($emp->direcciones ?? ''));
            $l = trim((string) ($emp->localidad ?? ''));
            $p = trim((string) ($emp->partido ?? ''));
            if ($d !== '' || $l !== '' || $p !== '') {
                return ['direccion' => $d, 'localidad' => $l, 'partido' => $p];
            }
        }

        return [
            'direccion' => trim((string) ($coti->coti_direccioncli ?? '')),
            'localidad' => trim((string) ($coti->coti_localidad ?? '')),
            'partido' => trim((string) ($coti->coti_partido ?? '')),
        ];
    }

    public static function direccionDestinatarioTexto(?object $coti): string
    {
        $p = self::direccionDestinatarioPartes($coti);
        $partes = array_filter([
            trim((string) ($p['direccion'] ?? '')),
            trim((string) ($p['localidad'] ?? '')),
            trim((string) ($p['partido'] ?? '')),
        ], fn ($x) => $x !== '');

        return implode(', ', $partes);
    }

    public static function direccionDestinatarioMapsQuery(?object $coti): string
    {
        return self::direccionDestinatarioTexto($coti);
    }

    /**
     * Razón social del titular (cliente de la cotización), sin sucursal ni empresa relacionada.
     */
    public static function razonSocialTitular(object $coti): string
    {
        $coti->loadMissing('cliente');
        $cliente = $coti->cliente ?? null;

        $base = trim((string) (optional($cliente)->cli_razonsocial ?? ''));
        if ($base === '') {
            $base = trim((string) (optional($cliente)->cli_fantasia ?? ''));
        }
        if ($base === '') {
            $base = trim((string) ($coti->coti_empresa ?? ''));
        }

        return $base;
    }

    /**
     * Bloque "Sr.(es)." del PDF de cotización: sucursal → empresa relacionada → campo "Para" (coti_para) → datos en coti/cliente.
     * Con sucursal: dRazon = titular; dirección/CUIT de la sucursal; nombre de sucursal en {@see etiquetaSucursalEstablecimiento()}.
     *
     * @return array{dRazon: string, dCuit: string, dDir: string, dLoc: string, dPart: string, dContactoBase: string}
     */
    public static function destinatarioPdfCamposPrincipales(object $coti): array
    {
        $coti->loadMissing(['cliente', 'sucursal']);

        $cliente = $coti->cliente ?? null;

        $cliNombrePdf = self::razonSocialTitular($coti);

        $tieneSucursal = false;
        $sucursalDestinatario = null;
        $sucursal = self::resolverSucursal($coti);
        if ($sucursal) {
            $tieneSucursal = true;
            $sucursalDestinatario = [
                'cuit' => $sucursal->cli_cuit ? trim((string) $sucursal->cli_cuit) : '',
                'direcciones' => $sucursal->cli_direccion ? trim((string) $sucursal->cli_direccion) : '',
                'localidad' => $sucursal->cli_localidad ? trim((string) $sucursal->cli_localidad) : '',
                'partido' => $sucursal->cli_partido ? trim((string) $sucursal->cli_partido) : '',
                'contacto' => $sucursal->cli_contacto ? trim((string) $sucursal->cli_contacto) : '',
            ];
        }

        $tieneEmpresaRelacionada = false;
        $empresaRelacionada = null;
        $idEmpresaRel = $coti->coti_empresa_rel ?? $coti->coti_cli_empresa ?? null;
        if ($idEmpresaRel) {
            $empresa = ClienteEmpresaRelacionada::find($idEmpresaRel);
            if ($empresa) {
                $tieneEmpresaRelacionada = true;
                $empresaRelacionada = [
                    'razon_social' => trim((string) ($empresa->razon_social ?? '')),
                    'cuit' => $empresa->cuit ? trim((string) $empresa->cuit) : '',
                    'direcciones' => $empresa->direcciones ? trim((string) $empresa->direcciones) : '',
                    'localidad' => $empresa->localidad ? trim((string) $empresa->localidad) : '',
                    'partido' => $empresa->partido ? trim((string) $empresa->partido) : '',
                    'contacto' => $empresa->contacto ? trim((string) $empresa->contacto) : '',
                ];
            }
        }

        $dRazon = '';
        $dCuit = '';
        $dDir = '';
        $dLoc = '';
        $dPart = '';
        $dContactoBase = '';

        if ($tieneSucursal && $sucursalDestinatario) {
            $dRazon = $cliNombrePdf;
            $dCuit = trim((string) ($sucursalDestinatario['cuit'] ?? ''));
            $dDir = trim((string) ($sucursalDestinatario['direcciones'] ?? ''));
            $dLoc = trim((string) ($sucursalDestinatario['localidad'] ?? ''));
            $dPart = trim((string) ($sucursalDestinatario['partido'] ?? ''));
            $dContactoBase = trim((string) ($sucursalDestinatario['contacto'] ?? ''));
        } elseif ($tieneEmpresaRelacionada && $empresaRelacionada) {
            $relRazonPdf = trim((string) ($empresaRelacionada['razon_social'] ?? ''));
            $dRazon = ($cliNombrePdf !== '' && $relRazonPdf !== '')
                ? $cliNombrePdf . ' - ' . $relRazonPdf
                : ($relRazonPdf !== '' ? $relRazonPdf : $cliNombrePdf);
            $dCuit = trim((string) ($empresaRelacionada['cuit'] ?? ''));
            $dDir = trim((string) ($empresaRelacionada['direcciones'] ?? ''));
            $dLoc = trim((string) ($empresaRelacionada['localidad'] ?? ''));
            $dPart = trim((string) ($empresaRelacionada['partido'] ?? ''));
            $dContactoBase = trim((string) ($empresaRelacionada['contacto'] ?? ''));
        } elseif (trim((string) ($coti->coti_para ?? '')) !== '') {
            $paraTxt = trim((string) $coti->coti_para);
            $esConsultorPdf = (bool) (optional($cliente)->es_consultor ?? false);
            $empRelMarcadaPdf = (bool) ($coti->coti_para_empresa_rel ?? false);
            $tieneIdEmpRelPdf = !empty($coti->coti_empresa_rel) || !empty($coti->coti_cli_empresa);
            $paraDistintoClientePdf = $cliNombrePdf !== '' && strcasecmp($paraTxt, $cliNombrePdf) !== 0;
            $usarCompositeParaPdf = $esConsultorPdf && $cliNombrePdf !== '' && $paraTxt !== ''
                && ($empRelMarcadaPdf || $tieneIdEmpRelPdf || $tieneEmpresaRelacionada || $paraDistintoClientePdf);
            if ($usarCompositeParaPdf) {
                $dRazon = $cliNombrePdf . ' - ' . $paraTxt;
            } else {
                $dRazon = $paraTxt;
            }
            $dCuit = trim((string) ($coti->coti_cuit ?? optional($cliente)->cli_cuit ?? ''));
            $dDir = trim((string) ($coti->coti_direccioncli ?? optional($cliente)->cli_direccion ?? ''));
            $dLoc = trim((string) ($coti->coti_localidad ?? optional($cliente)->cli_localidad ?? ''));
            $dPart = trim((string) ($coti->coti_partido ?? optional($cliente)->cli_partido ?? ''));
            $dContactoBase = trim((string) ($coti->coti_contacto ?? ''));
        } else {
            $dRazon = trim((string) ($coti->coti_empresa ?? optional($cliente)->cli_razonsocial ?? ''));
            $dCuit = trim((string) ($coti->coti_cuit ?? optional($cliente)->cli_cuit ?? ''));
            $dDir = trim((string) ($coti->coti_direccioncli ?? optional($cliente)->cli_direccion ?? ''));
            $dLoc = trim((string) ($coti->coti_localidad ?? optional($cliente)->cli_localidad ?? ''));
            $dPart = trim((string) ($coti->coti_partido ?? optional($cliente)->cli_partido ?? ''));
            $dContactoBase = trim((string) ($coti->coti_contacto ?? ''));
        }

        // Datos editados en la cotización (coti_*) tienen prioridad sobre sucursal/empresa/cliente.
        $dCuit = self::preferCampoCoti((string) ($coti->coti_cuit ?? ''), $dCuit);
        $dDir = self::preferCampoCoti((string) ($coti->coti_direccioncli ?? ''), $dDir);
        $dLoc = self::preferCampoCoti((string) ($coti->coti_localidad ?? ''), $dLoc);
        $dPart = self::preferCampoCoti((string) ($coti->coti_partido ?? ''), $dPart);
        $dContactoBase = self::preferCampoCoti((string) ($coti->coti_contacto ?? ''), $dContactoBase);

        return [
            'dRazon' => $dRazon,
            'dCuit' => $dCuit,
            'dDir' => $dDir,
            'dLoc' => $dLoc,
            'dPart' => $dPart,
            'dContactoBase' => $dContactoBase,
        ];
    }

    private static function preferCampoCoti(string $cotiVal, string $resolved): string
    {
        $cotiVal = trim($cotiVal);

        return $cotiVal !== '' ? $cotiVal : trim($resolved);
    }

    /**
     * Resuelve la sucursal destinataria (TRIM en código; la FK puede venir con padding).
     */
    public static function resolverSucursal(object $coti): ?\App\Models\Clientes
    {
        $cod = trim((string) ($coti->coti_codigosuc ?? ''));
        if ($cod === '') {
            return null;
        }

        if ($coti->relationLoaded('sucursal') && $coti->sucursal) {
            $relCod = trim((string) ($coti->sucursal->cli_codigo ?? ''));
            if ($relCod === $cod) {
                return $coti->sucursal;
            }
        }

        return \App\Models\Clientes::whereRaw('LTRIM(RTRIM(cli_codigo)) = ?', [$cod])->first();
    }

    /**
     * Nombre legible de la sucursal (prioriza fantasía sobre razón social compartida del titular).
     */
    public static function nombreSucursal(object $sucursal): string
    {
        $fantasia = trim((string) ($sucursal->cli_fantasia ?? ''));
        if ($fantasia !== '') {
            return $fantasia;
        }

        $razon = trim((string) ($sucursal->cli_razonsocial ?? ''));

        return $razon !== '' ? $razon : 'Sucursal';
    }

    /**
     * Texto para la fila "Sucursal / Establecimiento" del presupuesto.
     */
    public static function etiquetaSucursalEstablecimiento(object $coti): string
    {
        $establecimiento = trim((string) ($coti->coti_establecimiento ?? ''));
        if ($establecimiento !== '') {
            return $establecimiento;
        }

        $sucursal = self::resolverSucursal($coti);
        if ($sucursal) {
            return self::nombreSucursal($sucursal);
        }

        $empresa = self::empresaRelacionadaResuelta($coti);
        if ($empresa) {
            return trim((string) ($empresa->razon_social ?? ''));
        }

        return '';
    }

    /**
     * Titular de factura: datos del cliente de la cotización (cli_*), sin sucursal ni empresa relacionada.
     * Fallback a campos de cotización solo si falta información en la ficha del cliente.
     *
     * @return array{razon_social: string, cuit: string, domicilio: string, localidad: string, provincia: string, email: string}
     */
    public static function datosFacturacionFiscales(object $coti): array
    {
        $coti->loadMissing('cliente');
        $cli = $coti->cliente;

        $razon = trim((string) (optional($cli)->cli_razonsocial ?? ''));
        if ($razon === '') {
            $razon = trim((string) (optional($cli)->cli_fantasia ?? ''));
        }
        if ($razon === '') {
            $razon = trim((string) ($coti->coti_empresa ?? ''));
        }

        $cuit = trim((string) (optional($cli)->cli_cuit ?? ''));
        if ($cuit === '') {
            $cuit = trim((string) ($coti->coti_cuit ?? ''));
        }

        $dom = trim((string) (optional($cli)->cli_direccion ?? ''));
        if ($dom === '') {
            $dom = trim((string) ($coti->coti_direccioncli ?? ''));
        }

        $loc = trim((string) (optional($cli)->cli_localidad ?? ''));
        if ($loc === '') {
            $loc = trim((string) ($coti->coti_localidad ?? ''));
        }

        $prov = trim((string) (optional($cli)->cli_partido ?? ''));
        if ($prov === '') {
            $prov = trim((string) ($coti->coti_partido ?? ''));
        }

        $email = trim((string) (optional($cli)->cli_email ?? ''));
        if ($email === '') {
            $email = trim((string) ($coti->coti_mail1 ?? ''));
        }

        return [
            'razon_social' => $razon,
            'cuit' => $cuit,
            'domicilio' => $dom,
            'localidad' => $loc,
            'provincia' => $prov,
            'email' => $email,
        ];
    }
}
