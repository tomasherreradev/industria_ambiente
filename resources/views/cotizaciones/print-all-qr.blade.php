<!DOCTYPE html>
<html>
<head>
    <title>QRs - Cotización {{ $cotizacion->coti_num }}</title>
    <style>
        @page {
            size: auto;
            margin: 5mm;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .page {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 10px;
        }
        .qr-card {
            width: 200px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            page-break-inside: avoid;
        }
        .qr-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
            word-break: break-word;
        }
        .qr-container {
            margin: 0 auto 10px;
            width: 150px;
            height: 150px;
        }
        .qr-info {
            font-size: 12px;
            color: #666;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .page {
                margin-top: 0;
            }
            @page {
                margin: 0;
                size: auto;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <h2>CTs para Cotización {{ $cotizacion->coti_num }}</h2>
        <p>Cliente: {{ $cotizacion->coti_empresa }} - {{ $cotizacion->coti_establecimiento }}</p>
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Imprimir Todos los CTs
        </button>
    </div>

    <div class="page">
        @foreach($instancias as $instancia)
            <div class="qr-card">
                <div class="qr-title">{{ $instancia->cotio_descripcion }}</div>
                <div class="qr-container" id="qr-{{ $instancia->id }}"></div>
                <div style="width: 100%; max-width: 90%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                    <p></p>
                </div>
                <div style="width: 100%; max-width: 90%; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px; margin: 10px auto;">
                    <p></p>
                </div>
                <div class="qr-info">
                    <p>Muestra: {{ $instancia->instance_number }}</p>
                    <p>Cliente: {{ $cotizacion->coti_empresa }} - {{ $cotizacion->coti_establecimiento }}</p>
                    <p>
                        <strong>Fecha y hora:</strong>
                        <span style="display:inline-block; min-width: 140px; border-bottom: 1px solid #000; margin-left: 4px;">&nbsp;</span>
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($instancias as $instancia)
                new QRCode(document.getElementById("qr-{{ $instancia->id }}"), {
                    text: "{{ route('tareas.all.show', [
                        'cotio_numcoti' => $cotizacion->coti_num, 
                        'cotio_item' => $instancia->cotio_item, 
                        'cotio_subitem' => 0,
                        'instance' => $instancia->instance_number
                    ]) }}",
                    width: 150,
                    height: 150,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            @endforeach

            // Autoimpresión si viene con parámetro
            if(new URLSearchParams(window.location.search).has('autoprint')) {
                setTimeout(() => {
                    window.print();
                    setTimeout(() => window.close(), 1000);
                }, 500);
            }
        });
    </script>
</body>
</html>