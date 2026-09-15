@php
    use App\Support\CotizacionReferenciasFacturacion;

    $cotizacionActual = $cotizacion ?? null;

    $estadoFuente = old('coti_estado');
    if (! $estadoFuente && $cotizacionActual) {
        $estadoFuente = $cotizacionActual->coti_estado;
    }

    $estadoNormalizado = strtoupper(substr(trim((string) $estadoFuente), 0, 1));
    $forzarVisible = (bool) ($forzarVisible ?? false);
    $mostrarDatosAprobacion = $forzarVisible || $estadoNormalizado === 'A';

    $refsRows = [];
    $refsOld = old('coti_refs_facturacion_json');
    if ($refsOld !== null) {
        if (is_string($refsOld) && trim($refsOld) !== '') {
            $refsRows = CotizacionReferenciasFacturacion::normalizeRows(json_decode($refsOld, true) ?: []);
        } elseif (is_array($refsOld)) {
            $refsRows = CotizacionReferenciasFacturacion::normalizeRows($refsOld);
        }
    } elseif ($cotizacionActual) {
        $refsRows = CotizacionReferenciasFacturacion::rowsFromModel($cotizacionActual);
    }

    $ocReq = (bool) old('coti_oc_requerido_factura', $cotizacionActual?->coti_oc_requerido_factura ?? false);
    $ocValor = old('coti_oc_referencia', optional($cotizacionActual)->coti_oc_referencia);
@endphp

@php
    $wrapperVisible = $mostrarDatosAprobacion ? '1' : '0';
@endphp

<div id="datosAprobacionWrapper" data-visible="{{ $wrapperVisible }}">
<div id="datosAprobacionCard" class="card mt-4" style="{{ $mostrarDatosAprobacion ? '' : 'display:none;' }}">
    <div class="card-header bg-success text-white py-2">
        <h6 class="mb-0">Datos de la cotización</h6>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-8 col-lg-6">
                <label for="coti_oc_referencia" class="form-label">O.C.</label>
                <div class="input-group">
                    <input type="text"
                           class="form-control"
                           id="coti_oc_referencia"
                           name="coti_oc_referencia"
                           value="{{ $ocValor }}"
                           placeholder="Ingrese la orden de compra"
                           maxlength="120">
                    <div class="input-group-text bg-white">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="coti_oc_requerido_factura"
                                   name="coti_oc_requerido_factura" value="1" {{ $ocReq ? 'checked' : '' }}>
                            <label class="form-check-label small" for="coti_oc_requerido_factura" title="Si está marcado, no se podrá facturar sin número de O.C.">
                                Obligatorio para facturar
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-3">

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="form-label mb-0">Referencias (máx. 4)</span>
            <button type="button" class="btn btn-sm btn-outline-success" id="refsFacturacionAdd" title="Agregar referencia">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
            </button>
            <small class="text-muted">Use <strong>REMITO</strong> para el número de recepción. También HES, HAS, GR u OTRO. Puede marcar cada una como obligatoria para facturar.</small>
        </div>

        <div id="refsFacturacionRoot" data-max="4" data-inicial='@json($refsRows)'></div>
        <input type="hidden" name="coti_refs_facturacion_json" id="coti_refs_facturacion_json" value="">

        <template id="tplRefFacturacionRow">
            <div class="row g-2 align-items-end mb-2 refs-fact-row">
                <div class="col-md-3 col-lg-2">
                    <label class="form-label small text-muted">Tipo</label>
                    <select class="form-select form-select-sm ref-tipo">
                        <option value="REMITO">REMITO</option>
                        <option value="HES">HES</option>
                        <option value="HAS">HAS</option>
                        <option value="GR">GR</option>
                        <option value="OTRO">OTRO</option>
                    </select>
                </div>
                <div class="col-md-5 col-lg-4">
                    <label class="form-label small text-muted">Número / detalle</label>
                    <input type="text" class="form-control form-control-sm ref-valor" maxlength="120" placeholder="Ingrese el dato">
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="form-check mt-md-4">
                        <input class="form-check-input ref-oblig" type="checkbox" value="1">
                        <label class="form-check-label small">Obligatorio para facturar</label>
                    </div>
                </div>
                <div class="col-12 col-lg-2">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100 ref-remove">Quitar</button>
                </div>
            </div>
        </template>
    </div>
</div>
</div>

<script>
(function () {
    const root = document.getElementById('refsFacturacionRoot');
    const hidden = document.getElementById('coti_refs_facturacion_json');
    const btnAdd = document.getElementById('refsFacturacionAdd');
    const tpl = document.getElementById('tplRefFacturacionRow');
    if (!root || !hidden || !btnAdd || !tpl) {
        return;
    }

    const max = Math.min(8, Math.max(1, parseInt(root.dataset.max || '4', 10) || 4));

    function readRowsFromDom() {
        const rows = [];
        root.querySelectorAll('.refs-fact-row').forEach(function (row) {
            const tipo = (row.querySelector('.ref-tipo') || {}).value || '';
            const valor = (row.querySelector('.ref-valor') || {}).value || '';
            const oblig = !!(row.querySelector('.ref-oblig') && row.querySelector('.ref-oblig').checked);
            rows.push({ tipo: tipo, valor: (valor || '').trim(), obligatorio_factura: oblig });
        });
        return rows;
    }

    function syncHidden() {
        const rows = readRowsFromDom();
        hidden.value = JSON.stringify(rows);
    }

    function bindRow(row) {
        row.querySelectorAll('.ref-tipo, .ref-valor, .ref-oblig').forEach(function (el) {
            el.addEventListener('change', syncHidden);
            el.addEventListener('input', syncHidden);
        });
        const btnRm = row.querySelector('.ref-remove');
        if (btnRm) {
            btnRm.addEventListener('click', function () {
                row.remove();
                syncHidden();
                updateAddDisabled();
            });
        }
    }

    function addRow(inicial) {
        if (root.querySelectorAll('.refs-fact-row').length >= max) {
            return;
        }
        const frag = tpl.content.cloneNode(true);
        const row = frag.querySelector('.refs-fact-row');
        if (!row) {
            return;
        }
        if (inicial && inicial.tipo) {
            const sel = row.querySelector('.ref-tipo');
            if (sel) {
                sel.value = inicial.tipo;
            }
        }
        if (inicial && typeof inicial.valor === 'string') {
            const inp = row.querySelector('.ref-valor');
            if (inp) {
                inp.value = inicial.valor;
            }
        }
        if (inicial && inicial.obligatorio_factura) {
            const ob = row.querySelector('.ref-oblig');
            if (ob) {
                ob.checked = true;
            }
        }
        root.appendChild(row);
        bindRow(row);
        syncHidden();
        updateAddDisabled();
    }

    function updateAddDisabled() {
        btnAdd.disabled = root.querySelectorAll('.refs-fact-row').length >= max;
    }

    let inicial = [];
    try {
        inicial = JSON.parse(root.dataset.inicial || '[]') || [];
    } catch (e) {
        inicial = [];
    }
    if (Array.isArray(inicial) && inicial.length) {
        inicial.forEach(function (r) {
            addRow(r);
        });
    }
    syncHidden();
    updateAddDisabled();

    btnAdd.addEventListener('click', function () {
        addRow({ tipo: 'REMITO', valor: '', obligatorio_factura: false });
    });

    window.cotizacionReferenciaFactSyncHidden = function () {
        syncHidden();
    };

    /**
     * @param {Object} data — desde clonación u otra fuente
     * @param {string} [data.coti_oc_referencia]
     * @param {boolean} [data.coti_oc_requerido_factura]
     * @param {Array|Object|string|null} [data.coti_refs_facturacion_json]
     */
    window.cotizacionRefsFacturacionCargarDesdeDatos = function (data) {
        if (!data || typeof data !== 'object') {
            return;
        }
        const oc = document.getElementById('coti_oc_referencia');
        const ocChk = document.getElementById('coti_oc_requerido_factura');
        if (oc && data.coti_oc_referencia !== undefined && data.coti_oc_referencia !== null) {
            oc.value = data.coti_oc_referencia;
        }
        if (ocChk) {
            ocChk.checked = !!data.coti_oc_requerido_factura;
        }
        root.querySelectorAll('.refs-fact-row').forEach(function (r) {
            r.remove();
        });
        let rows = [];
        const raw = data.coti_refs_facturacion_json;
        if (Array.isArray(raw)) {
            rows = raw;
        } else if (typeof raw === 'string' && raw.trim() !== '') {
            try {
                rows = JSON.parse(raw) || [];
            } catch (e2) {
                rows = [];
            }
        }
        if (!rows.length) {
            syncHidden();
            updateAddDisabled();
            return;
        }
        rows.forEach(function (r) {
            addRow(r);
        });
    };
})();
</script>
