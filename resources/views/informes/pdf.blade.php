<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Informe - {{ $muestra->cotio_numcoti }}</title>
    @include('informes.partials.pdf-protocolo-estilos')
</head>

<body>

    <div class="pdf-header">
        <img src="{{ public_path('assets/img/header_informe.png') }}" alt="Header">
    </div>

    @php
        $tituloInformePdf = 'R014 – PROTOCOLO DE ENSAYO';
    @endphp

    @include('informes.partials.pdf-footer')

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
        @include('informes.partials.pdf-protocolo-contenido', [
            'muestra' => $muestra,
            'analisis' => $analisis ?? collect(),
            'showMap' => $showMap ?? false,
            'localMapPath' => $localMapPath ?? null,
            'metodoMuestreoNombre' => $metodoMuestreoNombre ?? null,
            'metodosByCodigo' => $metodosByCodigo ?? collect(),
            'equiposAnalisis' => $equiposAnalisis ?? collect(),
            'herramientasMuestreo' => $herramientasMuestreo ?? collect(),
        ])
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script('
                $font = $fontMetrics->get_font("helvetica", "normal");
                $size = 7;
                $pageText = "Página " . $PAGE_NUM . " de " . $PAGE_COUNT;
                $y = $pdf->get_height() - 74;
                $x = ($pdf->get_width() / 2) - ($fontMetrics->get_text_width($pageText, $font, $size) / 2);
                $pdf->text($x, $y, $pageText, $font, $size);
            ');
        }
    </script>
</body>

</html>
