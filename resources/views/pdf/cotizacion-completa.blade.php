<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cotización Completa {{ $cotizacion->coti_num }}</title>
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
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
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
        .categoria-header {
            background-color: #f2f2f2;
            padding: 8px;
            margin: 10px 0 5px 0;
            font-weight: bold;
        }
        .tarea-item {
            padding-left: 20px;
        }
        .separador {
            margin: 20px 0;
            border-top: 1px dashed #ccc;
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
                    <td><strong>Código:</strong></td>
                    <td><strong>{{ config('cotizacion_documento.codigo') }}</strong></td>
                </tr>
                <tr>
                    <td><strong>DP:</strong></td>
                    <td><strong>{{ config('cotizacion_documento.dp') }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Cotización:</strong></td>
                    <td><strong>{{ $cotizacion->coti_num }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Fecha Alta:</strong></td>
                    <td>{{ date('d/m/Y', strtotime($cotizacion->coti_fechaalta)) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="divider"></div>

    <div class="client-info">
        <p><strong>{{ $cotizacion->coti_empresa }} - {{ $cotizacion->coti_establecimiento }}</strong></p>
        <p>{{ $cotizacion->coti_direccioncli }}, {{ $cotizacion->coti_localidad }}, {{ $cotizacion->coti_partido }}</p>
        
        <p>Atn. {{ $cotizacion->coti_contacto }}<br>
        Tel.: {{ $cotizacion->coti_telefono }}<br>
        Mail: {{ $cotizacion->coti_mail1 }}</p>
    </div>

    <div class="divider"></div>

    <h3>Muestras y Tareas Activas</h3>
    
    @foreach($agrupadas as $item)
        @php
            // Filtrar solo tareas activas para esta categoría
            $tareasActivas = array_filter($item['tareas'], function($tarea) {
                return $tarea->activo == true;
            });
            
            // Omitir completamente las categorías de trabajo técnico de campo sin tareas
            $esTrabajoTecnico = $item['categoria']->cotio_descripcion === 'TRABAJO TECNICO DE CAMPO';
            $mostrarCategoria = !$esTrabajoTecnico || count($tareasActivas) > 0;
        @endphp

        @if($mostrarCategoria)
            <div class="categoria-header">
                {{ $item['categoria']->cotio_descripcion }} ({{ $item['categoria']->cotio_cantidad }})
            </div>
            
            @if(count($tareasActivas) > 0)
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th>Resultado(s)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tareasActivas as $tarea)
                        <tr>
                            <td>{{ $tarea->cotio_descripcion }}</td>
                            <td>{{ $tarea->resultado ?? 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @elseif(!$esTrabajoTecnico)
                <p class="tarea-item">No hay tareas activas en esta categoría</p>
            @endif
            
            @if(!$loop->last)
                <div class="separador"></div>
            @endif
        @endif
    @endforeach
</body>
</html>