<!DOCTYPE html>
<html>
<head>
    <title>QRs - Cotización {{ $cotizacion->coti_num }}</title>
    <style>
        @page {
            size: 50mm 80mm;
            margin: 0;
        }
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0 !important;
            padding: 0 !important;
            color: #000;
            background: #f8f9fa;
        }
        .page {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 20px;
            justify-content: center;
        }
        .print-container {
            width: 50mm;
            height: 80mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: flex-start;
            padding: 1mm 0 0 1mm;
            gap: 0.5mm;
            overflow: hidden;
            border: 1px solid #dee2e6;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            page-break-inside: avoid;
        }
        .qr-wrapper {
            width: 22mm;
            height: 22mm;
            flex-shrink: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #fff;
            padding: 0;
            box-sizing: border-box;
        }
        .qr-wrapper img, .qr-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
            image-rendering: pixelated;
        }
        .qr-text-wrapper {
            width: 12mm;
            height: 78mm;
            position: relative;
            flex-shrink: 0;
        }
        .qr-content {
            width: 22mm;
            height: 12mm;
            transform: rotate(90deg);
            transform-origin: 0 0;
            position: absolute;
            left: 12mm;
            top: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            text-align: left;
            box-sizing: border-box;
            padding-left: 2mm;
            padding-top: 1mm;
        }
        h1, .qr-title-ct {
            font-size: 8.5px;
            font-weight: 700;
            margin: 0 0 1px 0;
            color: #000;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            white-space: normal !important;
            word-break: break-word;
            line-height: 1.1;
        }
        .item-desc {
            font-size: 7.5px;
            margin: 0 0 1px 0;
            color: #000;
            line-height: 1.1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            white-space: normal !important;
            word-break: break-word;
        }
        .meta-info {
            font-size: 7px;
            margin: 0.5px 0;
            color: #000;
            line-height: 1.1;
        }
        .extra-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 2px;
            font-size: 6.5px;
            font-weight: 600;
            height: 22px;
        }
        .line-under {
            flex-grow: 1;
            border-bottom: 0.5pt solid #000;
            height: 100%;
        }
        .left-extra-wrapper {
            width: 10mm;
            height: 78mm;
            position: relative;
            flex-shrink: 0;
        }
        .left-extra-content {
            width: 22mm;
            height: 10mm;
            transform: rotate(90deg);
            transform-origin: 0 0;
            position: absolute;
            left: 10mm;
            top: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            box-sizing: border-box;
            padding-left: 2mm;
            padding-top: 1mm;
        }
        @media print {
            body {
                background: white;
            }
            .no-print {
                display: none !important;
            }
            .page {
                display: block;
                padding: 0;
                margin: 0;
            }
            .print-container {
                margin: 0;
                padding: 1.5mm;
                border: none;
                box-shadow: none;
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <h2>CTs para Cotización {{ $cotizacion->coti_num }}</h2>
        <p>Cliente: {{ \App\Support\CotizacionClienteEtiqueta::lineaClienteConEstablecimiento($cotizacion) }}</p>
        <button onclick="window.print()" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
            Imprimir Todos los CTs
        </button>
    </div>

    <div class="page">
        @foreach($instancias as $instancia)
            @php
                $categoriaDesc = $instancia->cotio_descripcion
                    ?? optional($instancia->muestra)->cotio_descripcion
                    ?? 'Muestra CT';
            @endphp
            <div class="print-container">
                <div class="left-extra-wrapper">
                    <div class="left-extra-content">
                        <div class="extra-row"><div class="line-under"></div></div>
                        <div class="extra-row" style="margin-top: 5px;"><div class="line-under"></div></div>
                        <div class="extra-row" style="margin-top: 5px;"><div class="line-under"></div></div>
                    </div>
                </div>
                <div class="qr-wrapper">
                    <div id="qr-{{ $instancia->id }}"></div>
                </div>
                <div class="qr-text-wrapper">
                    <div class="qr-content">
                        <h1 class="qr-title-ct">CT {{ $cotizacion->coti_num }}</h1>
                        <div class="item-desc">{{ $categoriaDesc }}</div>
                        <div class="meta-info"><strong>Muestra:</strong> {{ $instancia->instance_number }}</div>
                        <div class="meta-info">
                            <strong>Fecha:</strong>
                            <span style="display:inline-block; min-width: 35px; border-bottom: 1px solid #000; margin-left: 2px;">&nbsp;</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($instancias as $instancia)
                @php
                    $qrTexto = route('qr.universal', [
                        'cotio_numcoti' => $cotizacion->coti_num,
                        'cotio_item' => $instancia->cotio_item,
                        'cotio_subitem' => 0,
                        'instance' => $instancia->instance_number,
                    ]);
                @endphp
                new QRCode(document.getElementById("qr-{{ $instancia->id }}"), {
                    text: @json($qrTexto),
                    width: 110,
                    height: 110,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            @endforeach

            if (new URLSearchParams(window.location.search).has('autoprint')) {
                setTimeout(() => {
                    window.print();
                    setTimeout(() => window.close(), 2000);
                }, 1500);
            }
        });
    </script>
</body>
</html>
