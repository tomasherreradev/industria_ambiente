@php
    $unidadAnalisisForm = trim((string) ($tarea->instancia->cotio_codigoum ?? ''));
    $unidadParametroForm = trim((string) ($tarea->itemCatalogo->unidad_medida ?? $tarea->cotio_codigoum ?? ''));
    if ($unidadAnalisisForm === '') {
        $unidadAnalisisForm = $unidadParametroForm;
    }
    $metodoInfoTarea = \App\Support\EtiquetaMetodoAnalisis::resolver($tarea, $tarea->instancia);
    $analisisBloqueado = ($instanciaActual->cotio_estado_analisis ?? '') === 'analizado';

    $descNorm = $normalizeStr($tarea->cotio_descripcion);
    $valorSincronizado = $medicionesMapa->get($descNorm);
    $esSincronizadoFinal = ! is_null($valorSincronizado);
    $valorFinalInput = $esSincronizadoFinal ? $valorSincronizado : ($tarea->instancia->resultado_final ?? '');

    $replicas = [
        ['label' => 'R1', 'field' => 'resultado', 'obs' => 'observacion_resultado', 'valor' => $tarea->instancia->resultado ?? '', 'obsVal' => $tarea->instancia->observacion_resultado ?? '', 'fecha' => $tarea->instancia->fecha_carga_resultado_1 ?? null],
        ['label' => 'R2', 'field' => 'resultado_2', 'obs' => 'observacion_resultado_2', 'valor' => $tarea->instancia->resultado_2 ?? '', 'obsVal' => $tarea->instancia->observacion_resultado_2 ?? '', 'fecha' => $tarea->instancia->fecha_carga_resultado_2 ?? null],
        ['label' => 'R3', 'field' => 'resultado_3', 'obs' => 'observacion_resultado_3', 'valor' => $tarea->instancia->resultado_3 ?? '', 'obsVal' => $tarea->instancia->observacion_resultado_3 ?? '', 'fecha' => $tarea->instancia->fecha_carga_resultado_3 ?? null],
    ];

    $fmtFecha = static function ($f) {
        if ($f === null || $f === '') {
            return null;
        }
        if ($f instanceof \DateTimeInterface) {
            return $f->format('d/m/Y H:i');
        }

        return (string) $f;
    };

    $historialCambios = $historialCambios ?? collect();
    $tieneHist = static function (string $campo) use ($historialCambios, $tarea) {
        return isset($historialCambios[$tarea->instancia->id])
            && $historialCambios[$tarea->instancia->id]->where('campo_modificado', $campo)->isNotEmpty();
    };
@endphp

<div class="op-resultados-compact">
    <div class="op-resultados-compact__toolbar">
        @if($metodoInfoTarea['codigo'] !== '')
            <span class="op-resultados-compact__method-mobile d-md-none text-muted small">
                {{ $metodoInfoTarea['etiqueta'] }}
            </span>
        @endif
        <button type="button"
            class="btn btn-sm btn-outline-primary ms-auto"
            data-bs-toggle="modal"
            data-bs-target="#editAnalisisModal{{ $tarea->cotio_subitem }}">
            Vista analista
        </button>
    </div>

    <div class="analisis-ot-modal__meta">
        <div class="analisis-ot-modal__meta-card d-none d-md-block">
            <span class="analisis-ot-modal__meta-label">Método de análisis</span>
            <div class="analisis-ot-modal__meta-value analisis-ot-modal__meta-value--method op-resultados-compact__method-short">
                @if($metodoInfoTarea['codigo'] !== '')
                    {{ $metodoInfoTarea['etiqueta'] }}
                    @if($metodoInfoTarea['etiqueta'] !== $metodoInfoTarea['codigo'])
                        <span class="text-muted">({{ $metodoInfoTarea['codigo'] }})</span>
                    @endif
                @else
                    <span class="text-muted">Sin método</span>
                @endif
            </div>
        </div>
        <div class="analisis-ot-modal__meta-card analisis-ot-modal__meta-card--unit">
            <label class="analisis-ot-modal__meta-label" for="u_med_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}">Unidad</label>
            <input type="text"
                class="form-control form-control-sm"
                id="u_med_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                name="u_med_resultado"
                value="{{ $unidadAnalisisForm }}"
                placeholder="mg/l, UNT…"
                maxlength="50"
                title="Unidad para esta OT / informe"
                @if($analisisBloqueado) readonly @endif>
        </div>
    </div>

    <div class="op-resultados-compact__replicas">
        @foreach($replicas as $rep)
            <div class="op-resultados-compact__rep">
                <div class="op-resultados-compact__rep-head">
                    <span class="op-resultados-compact__rep-label">{{ $rep['label'] }}</span>
                    @if($tieneHist($rep['field']))
                        <button type="button" class="op-resultados-compact__hist btn-historial-resultado"
                            data-instancia-id="{{ $tarea->instancia->id }}"
                            data-campo="{{ $rep['field'] }}"
                            data-bs-toggle="modal"
                            data-bs-target="#historialResultadoModal"
                            title="Historial">
                            <x-heroicon-o-clock style="width: 14px; height: 14px;" />
                        </button>
                    @endif
                </div>
                <input type="text"
                    class="form-control form-control-sm resultado-input mb-1"
                    name="{{ $rep['field'] }}"
                    id="{{ $rep['field'] }}_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                    value="{{ $rep['valor'] }}"
                    placeholder="Valor"
                    @if($analisisBloqueado) readonly @endif>
                <input type="text"
                    class="form-control form-control-sm observacion-input"
                    name="{{ $rep['obs'] }}"
                    id="{{ $rep['obs'] }}_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                    value="{{ $rep['obsVal'] }}"
                    placeholder="Obs."
                    @if($analisisBloqueado) readonly @endif>
                @if($fmtFecha($rep['fecha']))
                    <div class="op-resultados-compact__rep-meta">Carga: {{ $fmtFecha($rep['fecha']) }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="op-resultados-compact__final">
        <div class="op-resultados-compact__final-head">
            <span class="op-resultados-compact__final-title">
                Resultado final
                @if($esSincronizadoFinal)
                    <span class="badge bg-info ms-1" style="font-size: 0.65rem;">Campo</span>
                @endif
            </span>
            @if($tieneHist('resultado_final'))
                <button type="button" class="op-resultados-compact__hist btn-historial-resultado"
                    data-instancia-id="{{ $tarea->instancia->id }}"
                    data-campo="resultado_final"
                    data-bs-toggle="modal"
                    data-bs-target="#historialResultadoModal"
                    title="Historial">
                    <x-heroicon-o-clock style="width: 14px; height: 14px;" />
                </button>
            @endif
        </div>
        <div class="row g-2">
            <div class="col-md-5">
                <input type="text"
                    class="form-control form-control-sm resultado-input @if($esSincronizadoFinal) bg-light fw-semibold text-info @endif"
                    name="resultado_final"
                    id="resultado_final_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                    value="{{ $valorFinalInput }}"
                    placeholder="Resultado consolidado"
                    @if($analisisBloqueado || $esSincronizadoFinal) readonly @endif
                    @if($esSincronizadoFinal) title="Sincronizado con medición de campo" @endif>
            </div>
            <div class="col-md-7">
                <input type="text"
                    class="form-control form-control-sm observacion-input"
                    name="observacion_resultado_final"
                    id="observacion_resultado_final_{{ $tarea->cotio_item }}_{{ $tarea->cotio_subitem }}"
                    value="{{ $tarea->instancia->observacion_resultado_final ?? '' }}"
                    placeholder="Observación del análisis"
                    @if($analisisBloqueado) readonly @endif>
            </div>
        </div>
        @if($fmtFecha($tarea->instancia->fecha_carga_ot ?? null))
            <div class="op-resultados-compact__rep-meta mt-1">Carga final: {{ $fmtFecha($tarea->instancia->fecha_carga_ot) }}</div>
        @endif
    </div>
</div>
