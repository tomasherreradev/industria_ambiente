<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Informe de Muestreo - {{ $muestra->cotio_numcoti }}</title>
    <style>
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            margin: 0;
            padding: 1.5cm;
            color: #333;
        }
        .header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid #3699cd; padding-bottom: 10px; }
        .logo-container { width: 120px; }
        .header-info { text-align: left; margin-top: 25px;}
        
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
        .map-container {
            margin: 15px 0;
            text-align: center;
        }
        .map-container img {
            max-width: 100%;
            height: auto;
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
        .page-number:after {
            content: counter(page);
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
                <p style="color: #3699cd;">Industria y Ambiente S.A</p>
            @endif
        </div>
        <div class="header-info">
            <h1 class="header-title">INFORME DE MUESTREO</h1>
            <p class="header-subtitle">Documento técnico - Confidencial</p>
            <p class="document-info">Referencia: #{{ $muestra->cotio_numcoti }} | Fecha: {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">INFORMACIÓN GENERAL</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">N° de Cotización:</span>
                <span class="info-value">#{{ $muestra->cotio_numcoti }}</span>
                <span style="margin-left: 300px;" class="info-label">O.T.N:</span>
                <span class="info-value">{{ $muestra->otn ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Muestra:</span>
                <span class="info-value">{{ $muestra->cotio_descripcion ?? ''}} (#{{ $muestra->instance_number }})</span>
            </div>
            <div class="info-item">
                <span class="info-label">Cliente:</span>
                <span class="info-value">
                    @php
                        $empresaRelacionadaInfo = null;
                        if ($muestra->cotizacion->coti_cli_empresa) {
                            $empresaRelacionadaInfo = \App\Models\ClienteEmpresaRelacionada::find($muestra->cotizacion->coti_cli_empresa);
                        }
                    @endphp
                    @if($empresaRelacionadaInfo)
                        {{ $empresaRelacionadaInfo->razon_social }}
                    @else
                        {{ $muestra->cotizacion->coti_empresa }}
                    @endif
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Establecimiento:</span>
                <span class="info-value">{{ $muestra->cotizacion->coti_establecimiento }}</span>
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
        </div>
    </div>

    @if($muestra->image)
    <div class="section">
        <h2 class="section-title">IDENTIFICACIÓN DE MUESTRA</h2>
        <div class="photo-container">
            <img src="{{ storage_path('app/public/images/' . $muestra->image) }}" alt="Imagen de la muestra">
            <p class="photo-caption">Figura 1. Documentación fotográfica del proceso de muestreo</p>
        </div>
    </div>
    @endif

    @if($showMap && $localMapPath)
    <div class="section">
        <h2 class="section-title">UBICACIÓN GEOGRÁFICA</h2>
        <div class="map-container">
            <img src="{{ $localMapPath }}" alt="Ubicación de la muestra" style="width: 100%; height: auto;">
            <p class="map-caption">Figura 2. Ubicación geográfica del punto de muestreo (Lat: {{ $muestra->latitud }}, Long: {{ $muestra->longitud }})</p>
        </div>
    </div>
    @endif

    <div class="section">
        <h2 class="section-title">MEDICIONES DE CAMPO</h2>
        @if($muestra->valoresVariables && $muestra->valoresVariables->count() > 0)
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
        @else
            <div class="no-data">No se registraron variables de medición</div>
        @endif
    </div>

    <div class="section">
        <h2 class="section-title">ANÁLISIS REALIZADOS</h2>
        @if(isset($analisis) && $analisis->count() > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Análisis</th>
                        <th>Resultado</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analisis as $item)
                        <tr>
                            <td>{{ $item->cotio_descripcion }}</td>
                            <td style="font-weight: bold;">{{ $item->resultado_final . ' ' . ($item->cotio_codigoum ?? '') }}</td>
                            <td>{{ $item->observacion_resultado_final }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-data">No se registraron análisis para esta muestra</div>
        @endif
    </div>

    <div class="footer">
        <p>INDUSTRIA Y AMBIENTE S.A.</p>
        {{-- <p>Av. Ejemplo 1234, Ciudad - Tel: (123) 456-7890</p> --}}
        <p>www.industriayambiente.com - info@industriayambiente.com</p>
        <p style="margin-top: 10px;">Documento confidencial - Prohibida su reproducción sin autorización</p>
    </div>
</body>
</html>