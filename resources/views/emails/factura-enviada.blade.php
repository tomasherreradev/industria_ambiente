<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura {{ $factura->numero_factura }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.5;">
    <p>Estimado/a cliente,</p>

    <p>
        Adjuntamos la factura
        <strong>{{ $factura->numero_factura }}</strong>
        @if($factura->cotizacion_id)
            correspondiente a la cotización <strong>{{ $factura->cotizacion_id }}</strong>
        @endif
        .
    </p>

    @if($factura->cae)
        <p><strong>CAE:</strong> {{ $factura->cae }}</p>
    @endif

    @if($factura->monto_total)
        <p><strong>Importe total:</strong> ${{ number_format((float) $factura->monto_total, 2, ',', '.') }}</p>
    @endif

    <p>Ante cualquier consulta, no dude en contactarnos.</p>

    <p style="margin-top: 24px; color: #666; font-size: 12px;">
        Este mensaje fue generado automáticamente por el sistema de facturación.
    </p>
</body>
</html>
