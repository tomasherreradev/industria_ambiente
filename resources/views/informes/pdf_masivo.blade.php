<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Informes - Cotización #{{ $cotizacion->coti_num }}</title>
    <style>
        /* Margen superior: espacio header + título; un poco menor para acercar título y cuadro .box */
        @page {
            margin: 54mm 10mm 14mm 10mm;
        }

        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.15;
            margin: 0;
            color: #000;
        }

        .pdf-header {
            position: fixed;
            left: 0;
            right: 0;
            top: -58mm;
            /* se dibuja en el margen superior */
            height: 34mm;
            z-index: 10;
        }

        .pdf-header img {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Título + recuadro: tabla 1 fila (Dompdf alinea mal flex); valign middle centra el título con la caja */
        .pdf-title-bar {
            position: fixed;
            left: 10mm;
            right: 10mm;
            /* Menos negativo = más abajo, más cerca del cuadro de datos */
            top: -7mm;
            z-index: 11;
            width: calc(100% - 20mm);
            box-sizing: border-box;
        }

        .pdf-title-bar-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            table-layout: fixed;
        }

        .pdf-title-bar-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .pdf-title-cell {
            padding-right: 3mm;
        }

        .pdf-title-ctrl-cell {
            width: 38%;
            text-align: right;
            vertical-align: middle;
        }

        .pdf-title-text {
            font-weight: 700;
            font-size: 14pt;
            line-height: 1.15;
            text-align: left;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .pdf-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -14mm;
            color: #000;
        }

        /* Page counters handled via script block at the end */

        .page {
            padding: 0;
            margin-top: -2mm;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .box {
            border: 1px solid #000;
            padding: 3mm;
            margin-bottom: 4mm;
        }

        table.kv {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }

        table.kv td {
            padding: 1.1mm 0.9mm;
            vertical-align: top;
        }

        .k {
            font-weight: 700;
            white-space: nowrap;
        }

        .label {
            font-weight: 700;
            font-size: 7.6pt;
            margin: 3mm 0 1.5mm 0;
            text-decoration: underline;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.2pt;
        }

        table.data th,
        table.data td {
            border: 1px solid #000;
            padding: 1.3mm 1.2mm;
        }

        table.data th {
            background: #f2f5ff;
            font-weight: 700;
            text-align: center;
        }

        table.data td {
            vertical-align: top;
        }

        .muted {
            color: #444;
            font-size: 7.2pt;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .no-data {
            font-style: italic;
            color: #555;
            padding: 2mm 0;
        }

        .map-wrap {
            margin-top: 2mm;
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }

        .map-img {
            width: 85%;
            height: auto;
            border: 1px solid #000;
            max-height: 105mm;
            /* más chica (tipo ejemplo A4) */
            display: inline-block;
        }

        .map-caption {
            font-size: 6.8pt;
            text-align: center;
            margin-top: 1mm;
        }

        .notas-box {
            font-size: 7.2pt;
            line-height: 1.3;
            margin-top: 4mm;
            margin-bottom: 3mm;
            white-space: pre-wrap;
        }

        .instrumental-section {
            page-break-inside: avoid;
        }

        .obs-box {
            border: 1px solid #bbb;
            padding: 2mm 3mm;
            margin-top: 1mm;
            min-height: 20mm;
            font-size: 7.2pt;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <div class="pdf-header">
        <img src="{{ public_path('assets/img/header_informe.png') }}" alt="Header">
    </div>
    <div class="pdf-footer">
        <div style="font-weight: bold;">
            {{-- El número de página se genera mediante el script al final del body --}}
        </div>
    </div>
    @php
        $versionInforme = 2;
        //$fechaDescargaInforme = \Carbon\Carbon::now()->format('d/m/Y');
        $tituloInformePdf = 'PROTOCOLO DE ENSAYO';
        $fechaInforme = '22/12/2023';

        $normalizeStr = function($str) {
            if (empty($str)) return '';
            $str = mb_strtolower($str, 'UTF-8');
            $str = preg_replace('/\s*\(.*\)\s*/u', '', $str);
            $search  = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'];
            $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'u'];
            $str = str_replace($search, $replace, $str);
            // Quitar cualquier caracter que no sea letra o número para máxima compatibilidad
            $str = preg_replace('/[^a-z0-9]/u', '', $str);
            return trim($str);
        };
    @endphp
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

    @foreach($muestras as $muestra)
        @php
            $cab = \App\Support\ProtocoloInformePdfCabecera::forPdf($muestra);
            $analisis = $muestra->analisis ?? collect();
            $herrs = $muestra->herramientasMuestreo ?? collect();

            $medicionesMapa = $muestra->valoresVariables->mapWithKeys(function($v) use ($normalizeStr) {
                return [$normalizeStr($v->variable) => $v->valor];
            });
        @endphp

        <div class="page">
            @include('informes.partials.pdf-protocolo-cabecera-box', ['cab' => $cab])

            @if(($muestra->showMap ?? false) && !empty($muestra->localMapPath))
                <div class="map-wrap">
                    <div class="label">Imagen satelital del predio</div>
                    <img class="map-img" src="{{ $muestra->localMapPath }}" alt="Imagen satelital">
                    <div class="map-caption">Punto N°{{ (int) ($muestra->instance_number ?? 1) }} (Lat: {{ $muestra->latitud }},
                        Long: {{ $muestra->longitud }})</div>
                </div>
                <div class="page-break"></div>
                {{-- Repetir bloque de datos al comenzar la hoja 2 --}}
                @include('informes.partials.pdf-protocolo-cabecera-box', ['cab' => $cab])
            @endif

            <div class="label">Resultado del análisis</div>
            @if($analisis && $analisis->count() > 0)
                <table class="data">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Determinación de:</th>
                            <th style="width: 20%;">Metodologías</th>
                            <th style="width: 15%;">Fecha de análisis</th>
                            <th style="width: 16%;">Resultados</th>
                            <th style="width: 12%;">Unidades</th>
                            <th style="width: 12%;">Límites</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analisis as $item)
                            @php
                                $met = trim((string) ($item->cotio_codigometodo_analisis ?? $item->cotio_codigometodo ?? ''));
                                // Priorizar fecha fin sobre fecha inicio y carga.
                                $fechaAnalisis = $item->analista_fecha_fin ?? $item->fecha_fin_ot ?? $item->analista_fecha_inicio ?? $item->fecha_inicio_ot ?? $item->fecha_carga_ot ?? $item->fecha_carga_resultado_3 ?? $item->fecha_carga_resultado_2 ?? $item->fecha_carga_resultado_1 ?? null;
                                $unidad = trim((string) ($item->cotio_codigoum ?? ''));

                                $descNorm = $normalizeStr($item->cotio_descripcion);
                                $valorSincronizado = $medicionesMapa->get($descNorm);

                                if (!is_null($valorSincronizado)) {
                                    $res = (string) $valorSincronizado;
                                } else {
                                    $res = trim((string) ($item->resultado_final ?? $item->resultado ?? ''));
                                }

                                $metDesc = $met !== '' ? (trim((string) optional(($metodosByCodigo ?? collect())->get($met))->metodo_descripcion) ?: $met) : '—';
                                $leyEnsayo = \App\Support\LeyNormativaPresentacion::textoPlano($item->tarea ?? null);
                                $mFi = $item->analista_fecha_inicio ?? null;
                                $mFf = $item->analista_fecha_fin ?? null;
                                $manualRangoAnalisis = null;
                                if ($mFi && $mFf) {
                                    $manualRangoAnalisis = \Carbon\Carbon::parse($mFi)->format('d/m/Y') . ' – ' . \Carbon\Carbon::parse($mFf)->format('d/m/Y');
                                } elseif ($mFf) {
                                    $manualRangoAnalisis = \Carbon\Carbon::parse($mFf)->format('d/m/Y');
                                } elseif ($mFi) {
                                    $manualRangoAnalisis = \Carbon\Carbon::parse($mFi)->format('d/m/Y');
                                }
                            @endphp
                            <tr>
                                <td>{{ $item->cotio_descripcion ?? '—' }}</td>
                                <td class="center">{{ $metDesc }}</td>
                                <td class="center">
                                    @if($manualRangoAnalisis){{ $manualRangoAnalisis }}@elseif($fechaAnalisis){{ \Carbon\Carbon::parse($fechaAnalisis)->format('d/m/Y') }}@else—@endif
                                </td>
                                <td class="right"><strong>{{ $res !== '' ? $res : '—' }}</strong></td>
                                <td class="center">{{ $unidad !== '' ? $unidad : '—' }}</td>
                                <td class="center">—</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-data">No se registraron análisis para esta muestra.</div>
            @endif

            @php $metMuestreoNombre = trim((string) ($muestra->metodoMuestreoNombre ?? '')); @endphp
            @if($metMuestreoNombre !== '')
                <div class="muted" style="margin-top: 2mm;">{{ $metMuestreoNombre }}</div>
            @endif

            {{-- NOTAS DEL INFORME (Justo debajo de la tabla de resultados) --}}
            @php
                $jsonProtocolo = $muestra->protocolo_informe_json ?? [];
                $notasInformePdf = trim((string) ($jsonProtocolo['notas_informe'] ?? ''));
            @endphp
            @if($notasInformePdf !== '')
                <div class="notas-box" style="margin-bottom: 4mm;">
                    {!! nl2br(e($notasInformePdf)) !!}
                </div>
            @endif

            <div class="instrumental-section">
                <div class="label">Instrumental utilizado</div>
                @php
                    $equipos = $muestra->equiposAnalisis ?? collect();
                    if (!$equipos || $equipos->count() === 0) {
                        $equipos = isset($muestra->herramientasLab) ? collect($muestra->herramientasLab) : collect();
                    }
                    if (!$equipos || $equipos->count() === 0) {
                        $equipos = $herrs ?? collect();
                    }
                @endphp
                @if($equipos && $equipos->count() > 0)
                    <table class="data instrumental">
                        <thead>
                            <tr>
                                <th>Equipo / Instrumento</th>
                                <th style="width: 45%;">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($equipos as $h)
                                <tr>
                                    <td class="center">
                                        {{ $h->equipamiento ?? $h->descripcion ?? $h->nombre ?? ('ID ' . ($h->id ?? '')) }}</td>
                                    <td class="muted center">
                                        @php
                                            $marca = trim((string) ($h->marca_modelo ?? ''));
                                            $serie = trim((string) ($h->n_serie_lote ?? ''));
                                            $ficha = trim((string) ($h->codigo_ficha ?? ''));
                                            $parts = array_filter([$marca, $serie ? ('N° Serie/Lote: ' . $serie) : null, $ficha ? ('Ficha: ' . $ficha) : null]);
                                        @endphp
                                        {{ !empty($parts) ? implode(' · ', $parts) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="no-data">—</div>
                @endif
            </div>

            <div style="margin-top: 5mm;">
                @include('informes.partials.pdf-observaciones', ['muestra' => $muestra, 'analisis' => $analisis])
            </div>
        </div>
    @endforeach

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