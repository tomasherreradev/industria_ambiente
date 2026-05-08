<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura {{ $factura->numero_factura }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .invoice-details {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .invoice-left, .invoice-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .invoice-right {
            text-align: right;
        }
        .invoice-number {
            font-size: 16px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .client-info {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .client-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
            color: #007bff;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .totals .total-final {
            font-weight: bold;
            font-size: 16px;
            background-color: #007bff;
            color: white;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .cae-info {
            background-color: #e9ecef;
            padding: 15px;
            margin-top: 30px;
            border-radius: 5px;
        }
        .cae-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
        }
        .badge {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-info">
                <div class="company-name">{{ 'Industria y Ambiente' }}</div>
                <div>Laboratorio de Análisis Químicos y Microbiológicos</div>
                <div>CUIT: {{ env('EMPRESA_CUIT', '20-12345678-9') }}</div>
                <div>{{ env('EMPRESA_DIRECCION', 'Dirección del Laboratorio') }}</div>
            </div>
        </div>

        <div class="invoice-details">
            <div class="invoice-left">
                <div class="invoice-number">FACTURA {{ $factura->numero_factura }}</div>
                <div><strong>Fecha:</strong> {{ $factura->fecha_emision->format('d/m/Y') }}</div>
                <div><strong>Estado:</strong> <span class="badge">{{ strtoupper($factura->estado) }}</span></div>
            </div>
            <div class="invoice-right">
                <div><strong>Cotización:</strong> #{{ $factura->cotizacion_id }}</div>
                @if($factura->cotizacion)
                    <div><strong>Fecha Cotización:</strong> {{ $factura->cotizacion->created_at ? $factura->cotizacion->created_at->format('d/m/Y') : 'N/A' }}</div>
                @endif
            </div>
        </div>

        <div class="client-info">
            <div class="client-title">DATOS DEL CLIENTE</div>
            @if($factura->cotizacion)
                @php
                    $factura->cotizacion->loadMissing('cliente');
                    $fiscalTpl = \App\Support\CotizacionClienteEtiqueta::datosFacturacionFiscales($factura->cotizacion);
                @endphp
                <div><strong>Razón Social:</strong> {{ ($fiscalTpl['razon_social'] ?? '') !== '' ? $fiscalTpl['razon_social'] : 'N/A' }}</div>
                <div><strong>CUIT:</strong> {{ ($fiscalTpl['cuit'] ?? '') !== '' ? $fiscalTpl['cuit'] : 'N/A' }}</div>
                <div><strong>Dirección:</strong> {{ $fiscalTpl['domicilio'] !== '' ? $fiscalTpl['domicilio'] : 'N/A' }}</div>
                <div><strong>Localidad:</strong> {{ $fiscalTpl['localidad'] !== '' ? $fiscalTpl['localidad'] : 'N/A' }}, {{ $fiscalTpl['provincia'] !== '' ? $fiscalTpl['provincia'] : 'N/A' }}</div>
                <div><strong>Email:</strong> {{ $fiscalTpl['email'] !== '' ? $fiscalTpl['email'] : 'N/A' }}</div>
            @else
                <div>Información del cliente no disponible</div>
            @endif
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Precio Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $subtotal = 0;
                    $rawItems = is_string($factura->items) ? json_decode($factura->items, true) : $factura->items;
                    $itemsListado = $rawItems;
                    $resumen = null;

                    if (isset($rawItems['items']) && is_array($rawItems['items'])) {
                        $itemsListado = $rawItems['items'];
                        $resumen = $rawItems['resumen'] ?? null;
                    }
                @endphp
                @if($itemsListado && is_array($itemsListado))
                    @foreach($itemsListado as $item)
                        @php
                            if (!is_array($item)) {
                                continue;
                            }
                            $tipo = $item['tipo'] ?? 'N/A';
                            $cantidad = $item['cantidad'] ?? 1;
                            $precioUnit = $item['precio_unitario'] ?? 0;
                            $subtotalItem = $item['subtotal'] ?? $precioUnit;
                            $subtotal += $subtotalItem;
                        @endphp
                        <tr>
                            <td>
                                {{ $item['descripcion'] ?? 'N/A' }}
                                @if(!empty($item['identificacion']))
                                    <br><small style="color: #666;">ID: {{ $item['identificacion'] }}</small>
                                @endif
                                @if(!empty($item['resultado']))
                                    <br><small style="color: #666;">Resultado: {{ $item['resultado'] }}</small>
                                @endif
                            </td>
                            <td>
                                @if($tipo === 'muestra')
                                    <span class="badge" style="background-color: #007bff;">MUESTRA</span>
                                @elseif($tipo === 'analisis')
                                    <span class="badge" style="background-color: #6f42c1;">ANÁLISIS</span>
                                @else
                                    <span class="badge" style="background-color: #6c757d;">{{ strtoupper($tipo) }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $cantidad }}</td>
                            <td class="text-right">${{ number_format($precioUnit, 2, ',', '.') }}</td>
                            <td class="text-right"><strong>${{ number_format($subtotalItem, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="5" class="text-center">No hay items registrados</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="totals">
            <table>
                @php
                    $total = $factura->monto_total;
                    $neto = round($total / 1.21, 2);
                    $iva = round($total - $neto, 2);
                @endphp
                @if($resumen && isset($resumen['total_bruto']) && $resumen['total_bruto'] > $total)
                    <tr>
                        <td><strong>Total bruto:</strong></td>
                        <td class="text-right">${{ number_format($resumen['total_bruto'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Descuento aplicado:</strong></td>
                        <td class="text-right">-${{ number_format($resumen['total_bruto'] - $total, 2, ',', '.') }}</td>
                    </tr>
                @endif
                <tr>
                    <td><strong>Subtotal (sin IVA):</strong></td>
                    <td class="text-right">${{ number_format($neto, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <td><strong>IVA (21%):</strong></td>
                    <td class="text-right">${{ number_format($iva, 2, ',', '.') }}</td>
                </tr>
                <tr class="total-final">
                    <td><strong>TOTAL:</strong></td>
                    <td class="text-right"><strong>${{ number_format($total, 2, ',', '.') }}</strong></td>
                </tr>
            </table>
        </div>

        <div class="cae-info">
            <div class="cae-title">INFORMACIÓN AFIP</div>
            <div><strong>CAE:</strong> {{ $factura->cae }}</div>
            <div><strong>Fecha Vencimiento CAE:</strong> {{ $factura->fecha_vencimiento_cae ? \Carbon\Carbon::parse($factura->fecha_vencimiento_cae)->format('d/m/Y') : 'N/A' }}</div>
            <div style="margin-top: 10px; font-size: 10px; color: #666;">
                Esta factura fue generada electrónicamente mediante el sistema AFIP en ambiente de homologación.
            </div>
        </div>

        <div class="footer">
            <div>Factura generada electrónicamente - Válida como comprobante fiscal</div>
            <div>{{ config('app.name') }} - {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>
</body>
</html>