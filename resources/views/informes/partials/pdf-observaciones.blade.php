{{--
    Observaciones / Notas del informe.
    Variables: $muestra (CotioInstancia), $analisis (collection iterable)
--}}
@php
    $bloquesObs = [];
    foreach (['observaciones', 'observaciones_ot'] as $campo) {
        $t = trim((string) ($muestra->{$campo} ?? ''));
        if ($t !== '' && !in_array($t, $bloquesObs, true)) {
            $bloquesObs[] = $t;
        }
    }
    $analisisCol = isset($analisis) ? collect($analisis) : collect();
    foreach ($analisisCol as $it) {
        $det = trim((string) ($it->cotio_descripcion ?? ''));
        if ($det === '') {
            $det = 'Análisis';
        }
        $parts = array_values(array_filter([
            trim((string) ($it->observacion_resultado   ?? '')),
            trim((string) ($it->observacion_resultado_2 ?? '')),
            trim((string) ($it->observacion_resultado_3 ?? '')),
            trim((string) ($it->observacion_resultado_final ?? '')),
        ], static fn ($s) => $s !== ''));
        if ($parts !== []) {
            $linea = $det . ': ' . implode(' ', $parts);
            if (!in_array($linea, $bloquesObs, true)) {
                $bloquesObs[] = $linea;
            }
        }
    }

    $json    = $muestra->protocolo_informe_json ?? [];
    $obsAdic = trim((string) ($json['observaciones_adicionales'] ?? ''));
    if ($obsAdic !== '' && !in_array($obsAdic, $bloquesObs, true)) {
        $bloquesObs[] = $obsAdic;
    }

    $obsCompleto = implode("\n\n", $bloquesObs);
@endphp

<div class="label">Observaciones</div>
{{--
    Recuadro con min-height para que siempre haya espacio visual,
    aunque las notas sean cortas o estén vacías.
--}}
<div class="obs-box">
    {!! $obsCompleto !== '' ? nl2br(e($obsCompleto)) : '<span style="color:#888;font-style:italic;">Sin Observaciones.</span>' !!}
</div>
