<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Informe - {{ $muestra->cotio_numcoti }}</title>
    <style>
        /*
         * Márgenes:  top=54mm (header membrete + título)
         *            right/left=10mm
         *            bottom=22mm (footer con imagen de pie de página)
         */
        @page {
            margin: 54mm 10mm 22mm 10mm;
        }

        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.15;
            margin: 0;
            color: #000;
        }

        /* ── HEADER (repite en cada página) ── */
        .pdf-header {
            position: fixed;
            left: 0;
            right: 0;
            top: -54mm;
            height: 34mm;
            z-index: 10;
        }

        .pdf-header img {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* ── FOOTER (repite en cada página) ── */
        .pdf-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -22mm;
            height: 22mm;
            z-index: 10;
            box-sizing: border-box;
            padding: 2mm 3mm 0 3mm;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 6pt;
            line-height: 1.5;
            color: #000;
        }

        .pdf-footer .footer-version {
            font-weight: 700;
            margin-bottom: 1mm;
        }

        .pdf-footer .footer-legal {
            font-style: italic;
        }

        /* ── BARRA TÍTULO ── */
        .pdf-title-bar {
            position: fixed;
            left: 10mm;
            right: 10mm;
            top: -13mm;
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
            font-size: 12.5pt;
            line-height: 1.15;
            text-align: left;
            text-decoration: underline;
            text-transform: uppercase;
        }

        .doc-control-table {
            border-collapse: collapse;
            font-size: 6.2pt;
            text-transform: uppercase;
            color: #000;
            background: transparent;
            margin-left: auto;
        }

        .doc-control-table .doc-ctrl-cell {
            border: 1px solid #000;
            padding: 2pt 5pt;
            text-align: center;
            vertical-align: middle;
            line-height: 1.25;
            white-space: nowrap;
        }

        .doc-control-table .doc-ctrl-mid-top,
        .doc-control-table .doc-ctrl-mid-bot {
            font-size: 5.8pt;
            min-width: 62pt;
        }

        /* Page counters handled via script block at the end */

        /* ── CONTENIDO GENERAL ── */
        .content {
            padding: 0;
            margin-top: -2mm;
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

        /* ── TABLA DE ANÁLISIS ── */
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
            /* Celeste institucional */
            background: #b8e0f4;
            font-weight: 700;
            text-align: center;
            color: #000;
        }

        table.data td {
            vertical-align: top;
        }

        /* ── OBSERVACIONES / NOTAS ── */
        .obs-box {
            border: 1px solid #bbb;
            padding: 2mm 3mm;
            margin-top: 1mm;
            min-height: 20mm;
            font-size: 7.2pt;
            line-height: 1.4;
        }

        .notas-box {
            font-size: 8pt;
            line-height: 1.2;
            margin-top: 4mm;
            margin-bottom: 3mm;
            text-align: justify;
            text-indent: 0;
            padding: 0;
            width: 100%;
            display: block;
        }

        .instrumental-section {
            page-break-inside: avoid;
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
            display: inline-block;
        }

        .map-caption {
            font-size: 6.8pt;
            text-align: center;
            margin-top: 1mm;
        }
    </style>
</head>

<body>

    {{-- HEADER — repite en todas las páginas --}}
    <div class="pdf-header">
        <img src="{{ public_path('assets/img/header_informe.png') }}" alt="Header">
    </div>

    @php
        $tituloInformePdf = 'R014 – PROTOCOLO DE ENSAYO';
        $codigoInforme = config('cotizacion_documento.informe_codigo', 'R014');
        $versionInforme = config('cotizacion_documento.informe_version', '2');
        $dpInforme = config('cotizacion_documento.informe_dp', 'PL06');
        $fechaInforme = config('cotizacion_documento.informe_fecha_emision', '20/02/2020');

        $normalizeStr = function ($str) {
            if (empty($str))
                return '';
            $str = mb_strtolower($str, 'UTF-8');
            $str = preg_replace('/\s*\(.*\)\s*/u', '', $str);
            $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'];
            $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'u'];
            $str = str_replace($search, $replace, $str);
            // Quitar cualquier caracter que no sea letra o número para máxima compatibilidad
            $str = preg_replace('/[^a-z0-9]/u', '', $str);
            return trim($str);
        };

        $medicionesMapa = $muestra->valoresVariables->mapWithKeys(function ($v) use ($normalizeStr) {
            return [$normalizeStr($v->variable) => $v->valor];
        });
    @endphp

    {{-- FOOTER — repite en todas las páginas --}}
    <div class="pdf-footer">
        <div class="footer-version">Versión: {{ $versionInforme }} &nbsp;&nbsp; Fecha de emisión: {{ $fechaInforme }}
        </div>
        <div class="footer-legal">Los resultados del presente informe se relacionan solamente con los ítems sometidos a
            ensayo.</div>
        <div class="footer-legal">Prohibida su reproducción total o parcial. La reproducción de la misma deberá ser
            autorizada por Industria y Ambiente S.A.</div>
        <div style="margin-top: 2mm; font-weight: bold;">
            {{-- El número de página se genera mediante el script al final del body --}}
        </div>
    </div>

    {{-- BARRA TÍTULO --}}
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
        @php
            $cab = \App\Support\ProtocoloInformePdfCabecera::forPdf($muestra);
            $boxHtml = function () use ($cab) {
                return view('informes.partials.pdf-protocolo-cabecera-box', ['cab' => $cab])->render();
            };
        @endphp

        {!! $boxHtml() !!}

        @if($showMap && $localMapPath)
            <div class="map-wrap">
                <div class="label">Imagen satelital del predio</div>
                <img class="map-img" src="{{ $localMapPath }}" alt="Imagen satelital">
                <div class="map-caption">Punto N°{{ (int) ($muestra->instance_number ?? 1) }} (Lat: {{ $muestra->latitud }},
                    Long: {{ $muestra->longitud }})</div>
            </div>
            <div class="page-break"></div>
            {!! $boxHtml() !!}
        @endif

        <div class="label">Resultado del análisis</div>
        @if(isset($analisis) && $analisis->count() > 0)
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 25%;">Determinación de:</th>
                        <th style="width: 20%;">Metodologías</th>
                        <th style="width: 15%;">Fecha de análisis</th>
                        <th style="width: 16%;">Resultados</th>
                        <th style="width: 12%;">Unidades</th>
                        <th style="width: 12%;">Límites Establecidos*</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analisis as $item)
                        @php
                            $met = trim((string) ($item->cotio_codigometodo_analisis ?? $item->cotio_codigometodo ?? ''));
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
                            if ($mFi) {
                                $manualRangoAnalisis = \Carbon\Carbon::parse($mFi)->format('d/m/Y');
                            }
                        @endphp
                        <tr>
                            <td>{{ $item->cotio_descripcion ?? '—' }}</td>
                            <td class="center">{{ $metDesc }}</td>
                            <td class="center">
                                @if($manualRangoAnalisis)
                                    {{ $manualRangoAnalisis }}
                                @else
                                    —
                                @endif
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

        @php $metMuestreoNombre = trim((string) ($metodoMuestreoNombre ?? '')); @endphp
        @if($metMuestreoNombre !== '')
            <div class="muted" style="margin-top: 2mm;">{{ $metMuestreoNombre }}</div>
        @endif

        {{-- NOTAS DEL INFORME (Justo debajo de la tabla de resultados) --}}
        @php
            $jsonProtocolo = \App\Support\ProtocoloInformePdfCabecera::forPdf($muestra);
            $notasAdicionales = trim((string) ($jsonProtocolo['notas_informe'] ?? ''));
            $notasPredeterminadas = \App\Support\ProtocoloInformePdfCabecera::obtenerNotasPredeterminadas($muestra);

            $notasFinales = $notasPredeterminadas;
            if ($notasAdicionales !== '') {
                $notasFinales .= ($notasFinales !== '' ? "\n\n" : "") . $notasAdicionales;
            }
        @endphp
        @if($notasFinales !== '')
            <div class="notas-box" style="margin-bottom: 4mm;">
                {!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($notasFinales))) !!}
            </div>
        @endif

        <div class="instrumental-section">
            <div class="label">Instrumental utilizado</div>
            @php
                $equipos = $equiposAnalisis ?? collect();
                if (!$equipos || $equipos->count() === 0) {
                    $equipos = isset($muestra->herramientasLab) ? collect($muestra->herramientasLab) : collect();
                }
                if (!$equipos || $equipos->count() === 0) {
                    $equipos = $herramientasMuestreo ?? collect();
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
                                    {{ $h->equipamiento ?? $h->descripcion ?? $h->nombre ?? ('ID ' . ($h->id ?? '')) }}
                                </td>
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

        {{-- OBSERVACIONES (Al final del protocolo) --}}
        <div style="margin-top: 5mm;">
            @include('informes.partials.pdf-observaciones', ['muestra' => $muestra, 'analisis' => $analisis ?? collect()])
        </div>
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