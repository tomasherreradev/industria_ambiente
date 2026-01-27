<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Informes de Cotización #{{ $cotizacion->coti_num }}</title>
    <style>
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            margin: 0;
            padding: 1.5cm;
            color: #333;
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #3699cd; 
            padding-bottom: 10px; 
        }
        .logo-container { 
            width: 120px; 
            max-height: 30px; 
        }
        .logo-container img { 
            max-height: 100%; 
            width: auto; 
        }
        .header-info { 
            text-align: left; 
            margin-top: 25px;
        }
        .header-title {
            font-size: 14pt;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .header-subtitle {
            font-size: 10pt;
            color: #666;
            margin: 5px 0 0 0;
        }
        .document-info {
            font-size: 9pt;
            color: #666;
            margin: 5px 0 0 0;
        }
        .section {
            margin: 25px 0;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #444;
            font-size: 9pt;
        }
        .info-value {
            color: #333;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9pt;
        }
        .table th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        .table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .photo-container {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #eee;
        }
        .photo-container img {
            max-width: 100%;
            max-height: 300px;
        }
        .photo-caption {
            font-size: 8pt;
            color: #666;
            margin-top: 5px;
        }
        .no-data {
            color: #777;
            font-style: italic;
            text-align: center;
            padding: 10px;
            font-size: 9pt;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9pt;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .page-break {
            page-break-after: always;
        }
        @page {
            margin: 1.5cm;
            @bottom-center {
                content: "Página " counter(page);
                font-family: 'Calibri', Arial, sans-serif;
                font-size: 8pt;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-container">
            @if(file_exists(public_path('assets/img/logo.png')))
                <img src="{{ public_path('assets/img/logo.png') }}" alt="Logo" style="width: 100px; height: auto;">
            @else
                <p style="color: #3699cd; font-weight: bold;">Industria y Ambiente S.A</p>
            @endif
        </div>
        <div class="header-info">
            <h1 class="header-title">INFORME DE MUESTREO</h1>
            <p class="header-subtitle">Documento técnico - Confidencial</p>
            <p class="document-info">Cotización: #{{ $cotizacion->coti_num }} | Fecha: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <!-- Iterar sobre múltiples muestras -->
    @foreach($muestras as $index => $muestra)
        <div class="section">
            <div class="d-flex justify-content-between">
                <h2 class="section-title">Reporte #{{ $index + 1 }} - {{ $muestra->cotio_descripcion ?? 'EFLUENTE LÍQUIDO' }}</h2>
                <p style="margin-left: 450px; margin-top: -30px;">O.T.N: #{{ $muestra->otn ?? 'N/A' }}</p>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Cliente:</span>
                    <span class="info-value">{{ $muestra->cotizacion->coti_empresa }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Establecimiento:</span>
                    <span class="info-value">{{ $muestra->cotizacion->coti_establecimiento ?? 'No especificado' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha de Muestreo:</span>
                    <span class="info-value">{{ $muestra->fecha_muestreo ? \Carbon\Carbon::parse($muestra->fecha_muestreo)->format('d/m/Y') : 'No especificada' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Matriz:</span>
                    <span class="info-value">{{ $muestra->cotizacion->matriz->matriz_descripcion ?? 'No especificada' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Identificación:</span>
                    <span class="info-value">{{ $muestra->cotio_identificacion ?? 'No especificada' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Descripción:</span>
                    <span class="info-value">{{ $muestra->cotio_descripcion ?? 'No especificada' }}</span>
                </div>
            </div>
        </div>

        @if($muestra->showMap && $muestra->localMapPath)
        <div class="section">
            <h2 class="section-title">UBICACIÓN GEOGRÁFICA</h2>
            <div class="map-container">
                <img src="{{ $muestra->localMapPath }}" alt="Ubicación de la muestra" style="width: 100%; height: auto;">
                <p class="map-caption">Figura {{ $index + 1 }}. Ubicación geográfica del punto de muestreo (Lat: {{ $muestra->latitud }}, Long: {{ $muestra->longitud }})</p>
            </div>
        </div>
        @endif

        @if($muestra->valoresVariables && $muestra->valoresVariables->count() > 0)
            <div class="section">
                <h2 class="section-title">MEDICIONES DE CAMPO</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Variable</th>
                            <th style="width: 30%;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($muestra->valoresVariables as $variable)
                            <tr>
                                <td>{{ $variable->variable }}</td>
                                <td>{{ $variable->valor }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="section">
                <h2 class="section-title">MEDICIONES DE CAMPO</h2>
                <div class="no-data">No se registraron variables de medición</div>
            </div>
        @endif

        @if(isset($muestra->analisis) && $muestra->analisis->count() > 0)
            <div class="section">
                <h2 class="section-title">ANÁLISIS REALIZADOS</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Análisis</th>
                            <th>Resultado</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($muestra->analisis as $item)
                            <tr>
                                <td>{{ $item->cotio_descripcion ?? 'No especificado' }}</td>
                                <td style="font-weight: bold;">{{ $item->resultado_final . ' ' . ($item->cotio_codigoum ?? '') }}</td>
                                <td>{{ $item->observacion_resultado_final ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="section">
                <h2 class="section-title">ANÁLISIS REALIZADOS</h2>
                <div class="no-data">No se registraron análisis para esta muestra</div>
            </div>
        @endif
    @endforeach

    <div class="footer">
        <p>INDUSTRIA Y AMBIENTE S.A.</p>
        {{-- <p>Av. Ejemplo 1234, Ciudad - Tel: (123) 456-7890</p> --}}
        <p>www.industriayambiente.com - info@industriayambiente.com</p>
        <p style="margin-top: 10px;">Documento confidencial - Prohibida su reproducción sin autorización</p>
    </div>
</body>
</html>