@php
    use App\Support\EtiquetaMetodoAnalisis;

    $tareaLinea = $item->tarea;
    $metodoInfo = EtiquetaMetodoAnalisis::resolver($tareaLinea ?? $item, $item);

    $unidadInstancia = trim((string) ($item->cotio_codigoum ?? ''));
    $unidadParametro = trim((string) ($tareaLinea?->itemCatalogo?->unidad_medida ?? ''));
    if ($unidadParametro === '') {
        $unidadParametro = trim((string) ($tareaLinea?->cotio_codigoum ?? ''));
    }
    $unidadEditable = $unidadInstancia !== '' ? $unidadInstancia : $unidadParametro;

    $variableLeyModal = ($variablesLey ?? collect())->first(function ($v) use ($item) {
        $itemProdCode = trim((string) ($item->tarea->cotio_codigoprod ?? ''));
        $varCatalogId = trim((string) ($v->cotio_item_id ?? ''));
        return $itemProdCode !== '' && (int) $itemProdCode === (int) $varCatalogId;
    });
    if (! $variableLeyModal && isset($mapVariablePorDescripcion)) {
        $variableLeyModal = $mapVariablePorDescripcion->get(trim(strtolower($item->cotio_descripcion)));
    }

    $descNormModal = $normalizeStr($item->cotio_descripcion);
    $valorSincronizadoModal = $medicionesMapa->get($descNormModal);
    $esSincronizadoModal = ! is_null($valorSincronizadoModal);
    $resultadoFinalModal = $esSincronizadoModal ? $valorSincronizadoModal : ($item->resultado_final ?? '');
@endphp

<div class="modal fade analisis-ot-modal" id="editAnalisisModal{{ $item->cotio_subitem }}" tabindex="-1" aria-hidden="true" data-subitem="{{ $item->cotio_subitem }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" class="analisis-ot-modal__form"
                action="{{ route('tareas.onlyUpdateResultado', [
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $item->cotio_subitem,
                    'instance' => $instanceNumber,
                ]) }}"
                data-update-url="{{ route('tareas.updateResultado', [
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $item->cotio_subitem,
                    'instance' => $instanceNumber,
                ]) }}"
                data-only-url="{{ route('tareas.onlyUpdateResultado', [
                    'cotio_numcoti' => $instancia->cotio_numcoti,
                    'cotio_item' => $instancia->cotio_item,
                    'cotio_subitem' => $item->cotio_subitem,
                    'instance' => $instanceNumber,
                ]) }}">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div class="pe-3">
                        <h5 class="analisis-ot-modal__title">
                            {{ $item->cotio_descripcion }}
                        </h5>
                        @if($metodoInfo['codigo'] !== '')
                            <p class="analisis-ot-modal__subtitle mb-0">
                                Método de análisis: {{ $metodoInfo['etiqueta'] }}
                            </p>
                        @endif
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    @if($variableLeyModal && ($leyNormativa ?? null))
                        <div class="analisis-ot-modal__ley">
                            <strong>Ley {{ $leyNormativa->codigo }}:</strong>
                            límite {{ $variableLeyModal->pivot->valor_limite ?? 'N/A' }}
                            {{ $variableLeyModal->pivot->unidad_medida ?? '' }}
                        </div>
                    @endif

                    <div class="analisis-ot-modal__meta">
                        <div class="analisis-ot-modal__meta-card d-none d-md-block">
                            <span class="analisis-ot-modal__meta-label">Método de análisis</span>
                            <div class="analisis-ot-modal__meta-value analisis-ot-modal__meta-value--method">
                                @if($metodoInfo['codigo'] !== '')
                                    {{ $metodoInfo['etiqueta'] }}
                                    @if($metodoInfo['etiqueta'] !== $metodoInfo['codigo'])
                                        <span class="text-muted">({{ $metodoInfo['codigo'] }})</span>
                                    @endif
                                @else
                                    <span class="text-muted">Sin método asignado en la cotización</span>
                                @endif
                            </div>
                        </div>
                        <div class="analisis-ot-modal__meta-card analisis-ot-modal__meta-card--unit">
                            <label class="analisis-ot-modal__meta-label" for="u_med_{{ $item->cotio_subitem }}">Unidad de medición</label>
                            <input type="text"
                                class="form-control form-control-sm"
                                id="u_med_{{ $item->cotio_subitem }}"
                                name="u_med_resultado"
                                value="{{ $unidadEditable }}"
                                placeholder="Ej. mg/l, UpH, °C"
                                maxlength="50"
                                autocomplete="off">
                            @if($unidadParametro !== '' && $unidadInstancia === '')
                                <p class="analisis-ot-modal__unit-hint mb-0">Sugerida del parámetro: {{ $unidadParametro }}. Solo aplica a esta OT / informe.</p>
                            @else
                                <p class="analisis-ot-modal__unit-hint mb-0">Editable para esta muestra; no modifica el catálogo.</p>
                            @endif
                        </div>
                    </div>

                    <div class="analisis-ot-modal__replicas">
                        @foreach([
                            ['n' => 1, 'field' => 'resultado', 'obs' => 'observacion_resultado', 'label' => 'Réplica 1'],
                            ['n' => 2, 'field' => 'resultado_2', 'obs' => 'observacion_resultado_2', 'label' => 'Réplica 2'],
                            ['n' => 3, 'field' => 'resultado_3', 'obs' => 'observacion_resultado_3', 'label' => 'Réplica 3'],
                        ] as $rep)
                            <div class="analisis-ot-modal__replica">
                                <div class="analisis-ot-modal__replica-title">{{ $rep['label'] }}</div>
                                <input type="text"
                                    class="form-control form-control-sm mb-2"
                                    name="{{ $rep['field'] }}"
                                    value="{{ $item->{$rep['field']} }}"
                                    placeholder="Valor">
                                <input type="text"
                                    class="form-control form-control-sm"
                                    name="{{ $rep['obs'] }}"
                                    value="{{ $item->{$rep['obs']} }}"
                                    placeholder="Observación">
                            </div>
                        @endforeach
                    </div>

                    <div class="analisis-ot-modal__section">
                        <div class="analisis-ot-modal__section-title">
                            Resultado final
                            @if($esSincronizadoModal)
                                <span class="badge bg-info">Campo</span>
                            @endif
                        </div>
                        <textarea class="form-control" name="resultado_final" rows="3"
                            @if($esSincronizadoModal) readonly @endif
                            placeholder="{{ $esSincronizadoModal ? 'Sincronizado con medición de campo' : 'Resultado consolidado del análisis' }}">{{ $resultadoFinalModal }}</textarea>
                        @if($esSincronizadoModal)
                            <div class="form-text text-info">Proviene de mediciones de campo y no puede editarse aquí.</div>
                        @endif
                    </div>

                    <div class="analisis-ot-modal__section">
                        <div class="analisis-ot-modal__section-title">Imagen del resultado (opcional)</div>
                        <input type="file" class="form-control form-control-sm" name="image_resultado_final" accept="image/*">
                        @if ($item->image_resultado_final)
                            <div class="analisis-ot-modal__img-preview mt-2">
                                <img src="{{ asset('storage/' . $item->image_resultado_final) }}" alt="Resultado">
                                <div class="mt-1">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ asset('storage/' . $item->image_resultado_final) }}" target="_blank" rel="noopener">Ver imagen</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="analisis-ot-modal__section mb-0">
                        <label class="analisis-ot-modal__section-title mb-2" for="obs_final_{{ $item->cotio_subitem }}">Observación del análisis</label>
                        <textarea class="form-control" id="obs_final_{{ $item->cotio_subitem }}" name="observacion_resultado_final" rows="2"
                            placeholder="Observaciones del analista">{{ $item->observacion_resultado_final ?? '' }}</textarea>
                    </div>

                    <div class="analisis-ot-modal__coord mt-3">
                        <label class="form-label fw-semibold text-dark small mb-1">Observaciones del coordinador</label>
                        <textarea class="form-control form-control-sm bg-white" name="observaciones_ot" rows="2" readonly
                            placeholder="Sin observaciones del coordinador">{{ $item->observaciones_ot ?? '' }}</textarea>
                    </div>

                    <p class="text-muted small mt-2 mb-0">
                        Al guardar, el sistema registra fecha y responsable según corresponda.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-only-save">Guardar</button>
                    <button type="button" class="btn btn-success btn-save-send">Guardar y enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>
