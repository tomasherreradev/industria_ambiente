@php
    $normalizeStr = function ($str) {
        if (empty($str)) {
            return '';
        }
        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/\s*\(.*\)\s*/u', '', $str);
        $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'u'];
        $str = str_replace($search, $replace, $str);
        $str = preg_replace('/[^a-z0-9]/u', '', $str);

        return trim($str);
    };

    $medicionesMapa = $muestra->valoresVariables->mapWithKeys(function ($v) use ($normalizeStr) {
        return [$normalizeStr($v->variable) => $v->valor];
    });

    $limiteCtx = \App\Support\ValorLimiteLeyNormativa::contextoDesdeLineaMuestra($muestra->muestra);

    $cab = \App\Support\ProtocoloInformePdfCabecera::forPdf($muestra);
    $boxHtml = function () use ($cab) {
        return view('informes.partials.pdf-protocolo-cabecera-box', ['cab' => $cab])->render();
    };

    $analisis = $analisis ?? collect();
    $showMap = $showMap ?? false;
    $localMapPath = $localMapPath ?? null;
    $metodosByCodigo = $metodosByCodigo ?? collect();
    $equiposAnalisis = $equiposAnalisis ?? collect();
    $herramientasMuestreo = $herramientasMuestreo ?? collect();
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
@if($analisis->count() > 0)
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
                    $fechaAnalisisTexto = \App\Support\FechaAnalisisInformePdf::textoColumnaPdf($item);
                    $unidad = trim((string) ($item->cotio_codigoum ?? ''));

                    $descNorm = $normalizeStr($item->cotio_descripcion);
                    $valorSincronizado = $medicionesMapa->get($descNorm);

                    if (!is_null($valorSincronizado)) {
                        $res = (string) $valorSincronizado;
                    } else {
                        $res = trim((string) ($item->resultado_final ?? $item->resultado ?? ''));
                    }

                    $metDesc = $met !== '' ? (trim((string) optional($metodosByCodigo->get($met))->metodo_descripcion) ?: $met) : '—';

                    $variableLimite = \App\Support\ValorLimiteLeyNormativa::variableParaAnalisis(
                        $item,
                        $limiteCtx['variablesLey'],
                        $limiteCtx['mapVariablePorDescripcion']
                    );
                    $limiteTexto = \App\Support\ValorLimiteLeyNormativa::textoDesdeVariable($variableLimite);
                @endphp
                <tr>
                    <td>{{ $item->cotio_descripcion ?? '—' }}</td>
                    <td class="center">{{ $metDesc }}</td>
                    <td class="center">{{ $fechaAnalisisTexto }}</td>
                    <td class="right"><strong>{{ $res !== '' ? $res : '—' }}</strong></td>
                    <td class="center">{{ $unidad !== '' ? $unidad : '—' }}</td>
                    <td class="center">{{ $limiteTexto }}</td>
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

@php
    $notasFinales = \App\Support\ProtocoloInformePdfCabecera::compilarNotasInformePdf($muestra);
@endphp
@if($notasFinales !== '')
    <div class="notas-box" style="margin-bottom: 4mm;">
        {!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($notasFinales))) !!}
    </div>
@endif

<div class="instrumental-section">
    <div class="label">Instrumental utilizado</div>
    @php
        $equipos = $equiposAnalisis;
        if (!$equipos || $equipos->count() === 0) {
            $equipos = isset($muestra->herramientasLab) ? collect($muestra->herramientasLab) : collect();
        }
        if (!$equipos || $equipos->count() === 0) {
            $equipos = $herramientasMuestreo;
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

<div style="margin-top: 5mm;">
    @include('informes.partials.pdf-observaciones', ['muestra' => $muestra, 'analisis' => $analisis])
</div>
