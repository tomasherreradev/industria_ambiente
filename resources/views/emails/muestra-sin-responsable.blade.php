<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muestra sin responsables asignados</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #dc3545;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
        }
        .info-box {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
            border-radius: 4px;
        }
        .info-row {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #495057;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Muestra sin responsables asignados</h1>
    </div>
    
    <div class="content">
        <p>Hola <strong>{{ $coordinador->usu_descripcion }}</strong>,</p>
        
        <p>Se ha detectado una muestra que está coordinada para muestreo pero no tiene responsables asignados:</p>
        
        <div class="info-box">
            <div class="info-row">
                <span class="label">Descripción:</span> {{ $muestra->cotio_descripcion ?? 'Sin descripción' }}
            </div>
            <div class="info-row">
                <span class="label">COTI:</span> {{ $muestra->cotio_numcoti }}
            </div>
            <div class="info-row">
                <span class="label">Ítem:</span> {{ $muestra->cotio_item }}
            </div>
            <div class="info-row">
                <span class="label">Muestra:</span> {{ $muestra->instance_number }}
            </div>
            <div class="info-row">
                <span class="label">Estado:</span> {{ $muestra->cotio_estado ?? 'Sin estado' }}
            </div>
            @if($muestra->fecha_muestreo)
            <div class="info-row">
                <span class="label">Fecha de coordinación:</span> {{ \Carbon\Carbon::parse($muestra->fecha_muestreo)->format('d/m/Y H:i') }}
            </div>
            @endif
        </div>
        
        <p>Por favor, asigna responsables de muestreo a esta muestra para continuar con el proceso.</p>
        
        <div style="text-align: center;">
            <a href="{{ $url }}" class="button">Ver muestra en el sistema</a>
        </div>
    </div>
    
    <div class="footer">
        <p>Este es un mensaje automático del sistema de gestión de muestras.</p>
        <p>Por favor, no responda a este correo.</p>
    </div>
</body>
</html>
