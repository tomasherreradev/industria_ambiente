<table>
    <thead>
        <tr>
            <th colspan="12"><strong>Industria y Ambiente S.A.</strong></th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="12" style="text-align: center;"><strong>Mes de
                    {{ \Carbon\Carbon::parse($fechaDesde)->locale('es')->monthName }} del
                    {{ \Carbon\Carbon::parse($fechaDesde)->year }}</strong></th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="4">Fecha Desde {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} Hasta
                {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}</th>
            <th colspan="4">Estado Com.: Todos</th>
        </tr>
        <tr>
            <th colspan="4">Tipo: Con Resumen</th>
            <th colspan="4">Comprobantes:</th>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="4">Fecha del Proceso: {{ now()->format('d/m/Y') }}</th>
        </tr>
        <tr>
            <th><strong>Fecha</strong></th>
            <th><strong>Comprobante</strong></th>
            <th><strong>Cliente</strong></th>
            <th><strong>CUIT</strong></th>
            <th><strong>Neto Gravado</strong></th>
            <th><strong>I.V.A.</strong></th>
            <th><strong>Percepc. IIBB</strong></th>
            <th><strong>Percepc. Ganan.</strong></th>
            <th><strong>Ret. Ganan.</strong></th>
            <th><strong>No Gravado</strong></th>
            <th><strong>Total</strong></th>
            <th><strong>Cuenta Contable</strong></th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalNeto = 0;
            $totalIva = 0;
            $totalFinal = 0;
        @endphp
        @foreach($facturas as $factura)
            @php
                $total = (float) $factura->monto_total;
                // Calculo simple de IVA 21% (puedes ajustar si hay diferentes tasas)
                $neto = $total / 1.21;
                $iva = $total - $neto;

                $totalNeto += $neto;
                $totalIva += $iva;
                $totalFinal += $total;
            @endphp
            <tr>
                <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                <td>{{ $factura->numero_factura }}</td>
                <td>{{ $factura->cliente_razon_social }}</td>
                <td>{{ $factura->cliente_cuit }}</td>
                <td>{{ number_format($neto, 2, '.', '') }}</td>
                <td>{{ number_format($iva, 2, '.', '') }}</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>0.00</td>
                <td>{{ number_format($total, 2, '.', '') }}</td>
                <td>5.09.00.00.03</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="4" style="text-align: right;"><strong>TOTALES:</strong></th>
            <th><strong>{{ number_format($totalNeto, 2, '.', '') }}</strong></th>
            <th><strong>{{ number_format($totalIva, 2, '.', '') }}</strong></th>
            <th><strong>0.00</strong></th>
            <th><strong>0.00</strong></th>
            <th><strong>0.00</strong></th>
            <th><strong>0.00</strong></th>
            <th><strong>{{ number_format($totalFinal, 2, '.', '') }}</strong></th>
            <th></th>
        </tr>
    </tfoot>
</table>