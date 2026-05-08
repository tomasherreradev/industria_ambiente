@php
    $fechaCotPdf = $cotizacion->coti_fechaalta ?? $cotizacion->coti_fechaaltatecnica ?? null;
    $codCliPdf = trim((string) ($cotizacion->coti_codigocli ?? ''));
    $cliRsMeta = trim((string) (optional($cliente)->cli_razonsocial ?? ''));
    if ($cliRsMeta === '') {
        $cliRsMeta = trim((string) (optional($cliente)->cli_fantasia ?? ''));
    }
    if ($cliRsMeta === '') {
        $cliRsMeta = trim((string) ($cotizacion->coti_empresa ?? ''));
    }
    $esConsultorMeta = (bool) (optional($cliente)->es_consultor ?? false);
    $empRelRsMeta = '';
    if (!empty($tieneEmpresaRelacionada) && !empty($empresaRelacionada['razon_social'] ?? null)) {
        $empRelRsMeta = trim((string) $empresaRelacionada['razon_social']);
    } elseif ($esConsultorMeta && trim((string) ($cotizacion->coti_para ?? '')) !== '') {
        $paraM = trim((string) $cotizacion->coti_para);
        $paraDistintoCli = $cliRsMeta !== '' && strcasecmp($paraM, $cliRsMeta) !== 0;
        if ((bool) ($cotizacion->coti_para_empresa_rel ?? false)
            || !empty($cotizacion->coti_empresa_rel)
            || !empty($cotizacion->coti_cli_empresa)
            || $paraDistintoCli) {
            $empRelRsMeta = $paraM;
        }
    }
    $lineaClienteMetaPdf = ($empRelRsMeta !== '' && $cliRsMeta !== '')
        ? $cliRsMeta . ' - ' . $empRelRsMeta
        : ($cliRsMeta !== '' ? $cliRsMeta : $empRelRsMeta);
@endphp
<div class="quote-meta-bar">
    <div class="quote-meta-left">
        <div class="quote-meta-line"><strong>Cotización:</strong> #{{ $cotizacion->coti_num }}</div>
        <div class="quote-meta-line"><strong>Fecha:</strong> {{ $fechaCotPdf ? \Carbon\Carbon::parse($fechaCotPdf)->format('d/m/Y') : '—' }}</div>
        @if($codCliPdf !== '' || $lineaClienteMetaPdf !== '')
            <div class="quote-meta-line compact-client-ref">{{ $codCliPdf }}{{ $codCliPdf && $lineaClienteMetaPdf ? ' - ' : '' }}{{ $lineaClienteMetaPdf }}</div>
        @endif
    
    </div>
    <div class="quote-meta-right">
        <table class="doc-control-table" role="presentation">
            <tr>
                <td rowspan="2" class="doc-ctrl-cell doc-ctrl-code">CÓDIGO {{ config('cotizacion_documento.codigo') }}</td>
                <td class="doc-ctrl-cell doc-ctrl-mid-top">VERSIÓN: 2</td>
                <td rowspan="2" class="doc-ctrl-cell doc-ctrl-dp">DP: {{ config('cotizacion_documento.dp') }}</td>
            </tr>
            <tr>
                <td class="doc-ctrl-cell doc-ctrl-mid-bot">FECHA DE EMISIÓN {{ config('cotizacion_documento.fecha_emision') }}</td>
            </tr>
        </table>
    </div>
</div>
<div class="quote-meta-separator"></div>
