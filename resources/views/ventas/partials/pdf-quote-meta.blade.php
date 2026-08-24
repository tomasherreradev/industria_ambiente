@php
    $fechaCotPdf = $cotizacion->coti_fechaalta ?? $cotizacion->coti_fechaaltatecnica ?? null;
@endphp
<div class="quote-meta-bar">
    <div class="quote-meta-left">
        <div class="quote-meta-line"><strong>Cotización:</strong> #{{ $cotizacion->coti_num }}</div>
        <div class="quote-meta-line"><strong>Fecha:</strong> {{ $fechaCotPdf ? \Carbon\Carbon::parse($fechaCotPdf)->format('d/m/Y') : '—' }}</div>
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
