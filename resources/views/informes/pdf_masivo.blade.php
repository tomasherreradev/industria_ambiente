<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Informes - Cotización #{{ $cotizacion->coti_num }}</title>
    @include('informes.partials.pdf-protocolo-estilos')
</head>

<body>

    <div class="pdf-header">
        <img src="{{ public_path('assets/img/header_informe.png') }}" alt="Header">
    </div>

    @php
        $tituloInformePdf = 'R014 – PROTOCOLO DE ENSAYO';
        $versionInforme = config('cotizacion_documento.informe_version', '2');
        $fechaInforme = config('cotizacion_documento.informe_fecha_emision', '20/02/2020');
    @endphp

    <div class="pdf-footer">
        <div class="footer-version">Versión: {{ $versionInforme }} &nbsp;&nbsp; Fecha de emisión: {{ $fechaInforme }}
        </div>
        <div class="footer-legal">Los resultados del presente informe se relacionan solamente con los ítems sometidos a
            ensayo.</div>
        <div class="footer-legal">Prohibida su reproducción total o parcial. La reproducción de la misma deberá ser
            autorizada por Industria y Ambiente S.A.</div>
        <div style="margin-top: 2mm; font-weight: bold;"></div>
    </div>

    <div class="pdf-title-bar">
        <table class="pdf-title-bar-table" role="presentation">
            <tbody>
                <tr>
                    <td class="pdf-title-cell" valign="middle" align="center">
                        <span class="pdf-title-text">{{ $tituloInformePdf }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="content">
        @foreach($muestras as $muestra)
            <div class="informe-muestra-block">
                @include('informes.partials.pdf-protocolo-contenido', [
                    'muestra' => $muestra,
                    'analisis' => $muestra->analisis ?? collect(),
                    'showMap' => $muestra->showMap ?? false,
                    'localMapPath' => $muestra->localMapPath ?? null,
                    'metodoMuestreoNombre' => $muestra->metodoMuestreoNombre ?? null,
                    'metodosByCodigo' => $metodosByCodigo ?? collect(),
                    'equiposAnalisis' => $muestra->equiposAnalisis ?? collect(),
                    'herramientasMuestreo' => $muestra->herramientasMuestreo ?? collect(),
                ])
            </div>
        @endforeach
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("helvetica", "normal");
                $size = 7;
                $pageText = "Página " . $PAGE_NUM . " de " . $PAGE_COUNT;
                $y = $pdf->get_height() - 20;
                $x = ($pdf->get_width() / 2) - ($fontMetrics->get_text_width($pageText, $font, $size) / 2);
                $pdf->text($x, $y, $pageText, $font, $size);
            ');
        }
    </script>
</body>

</html>
