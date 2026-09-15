<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->coti_num }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .logo-container {
            width: 120px;
            margin-right: 20px;
            margin-bottom: 1.5rem;
        }

        .header-info {
            width: 100%;
        }
        .header-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-info td {
            padding: 2px 0;
        }
        .divider {
            border-top: 1px solid #000;
            margin: 10px 0;
        }
        .client-info {
            margin-bottom: 15px;
        }
        .client-info p {
            margin: 3px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .items-table th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 5px 0;
        }
        .items-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .item-description {
            padding-left: 20px;
            font-size: 9pt;
        }
        .page-number {
            text-align: right;
            margin-top: 10px;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <!-- Encabezado con información de cotización -->
    <div class="header">
        <div class="logo-container">
            @if(file_exists(public_path('assets/img/logo.png')))
                <img src="{{ public_path('assets/img/logo.png') }}" alt="Logo" style="width: 100px; height: auto;">
            @else
                <p style="color: #3699cd;">Industria y Ambiente S.A</p>
            @endif
        </div>
        <div class="header-info">
            <table>
                <tr>
                    <td><strong>Cotización:</strong></td>
                    <td><strong>{{ $cotizacion->coti_num ?? 'N/A' }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Fecha Alta:</strong></td>
                    <td>{{ date('d/m/Y', strtotime($cotizacion->coti_fechaalta ?? 'N/A')) }}</td>
                </tr>
                <tr>
                </tr>
            </table>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Información del cliente -->
    <div class="client-info">
        <p><strong>{{ \App\Support\CotizacionClienteEtiqueta::lineaClienteConEstablecimiento($cotizacion) }}</strong></p>
        <p>{{ \App\Support\CotizacionClienteEtiqueta::direccionDestinatarioTexto($cotizacion) ?: 'N/A' }}</p>
        
        <p>Atn. {{ $cotizacion->coti_contacto ?? 'Nombre de contacto' }}<br>
        Tel.: {{ $cotizacion->coti_telefono ?? '-' }}<br>
        Mail: {{ $cotizacion->coti_mail1 ?? '-' }}</p>
        
        <p>C.U.I.T.: {{ $cotizacion->coti_cuit ?? '-' }}<br>
        Cliente: {{ $cotizacion->codigo_cliente ?? '-' }}</p>
    </div>

    <div class="divider"></div>

    <!-- Referencia -->
    <p><strong>Muestra: {{ $categoria->cotio_descripcion ?? 'N/A' }} ({{ $categoria->cotio_cantidad ?? 'N/A' }})</strong></p>

    <!-- Tabla de ítems -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Resultado(s)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tareas as $tarea)
            <tr>
                <td>{{ $tarea->cotio_cantidad ?? 'N/A' }} {{ $tarea->cotio_descripcion ?? 'N/A' }}</td>
                <td>{{ $tarea->resultado ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>