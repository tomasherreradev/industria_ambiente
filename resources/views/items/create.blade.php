@extends('layouts.app')

@section('title', 'Nuevo Parámetro')

@section('content')
@include('partials.ucrud-styles')

<div class="container-fluid py-4 ucrud ucrud-layout--fluid">
    @include('partials.ucrud-form-header', [
        'title' => 'Nueva determinación',
        'subtitle' => 'Alta de ítem, métodos, matrices y precio.',
        'backUrl' => route('items.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form method="POST" action="{{ route('items.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="cotio_descripcion" class="form-label">Determinación</label>
                    <input type="text" name="cotio_descripcion" id="cotio_descripcion" value="{{ old('cotio_descripcion') }}" class="form-control @error('cotio_descripcion') is-invalid @enderror" required>
                    @error('cotio_descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="js-campos-no-agrupador {{ old('es_muestra') ? 'd-none' : '' }}">
                <div class="mb-3">
                    <label for="limites_establecidos" class="form-label">Límites establecidos</label>
                    <input type="text" name="limites_establecidos" id="limites_establecidos" value="{{ old('limites_establecidos') }}" class="form-control @error('limites_establecidos') is-invalid @enderror" placeholder="Ej: No especifica, 6,5 <3, etc">
                    @error('limites_establecidos')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @include('items.partials.metodos-campos', [
                    'item' => null,
                    'metodos' => $metodos,
                ])
                </div>

                <div class="mb-3">
                    <label for="matrices" class="form-label">Matrices</label>
                    <select name="matrices[]" id="matrices" class="form-select select2-multiple" multiple data-placeholder="Selecciona las matrices">
                        @foreach($matrices as $matriz)
                            <option value="{{ $matriz->matriz_codigo }}" {{ in_array($matriz->matriz_codigo, old('matrices', [])) ? 'selected' : '' }}>
                                {{ $matriz->matriz_codigo }} - {{ $matriz->matriz_descripcion }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Puedes seleccionar múltiples matrices para este ítem.</small>
                    @error('matrices')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 js-campos-no-agrupador {{ old('es_muestra') ? 'd-none' : '' }}">
                    <label for="unidad_medida" class="form-label">Unidad de medida</label>
                    <input type="text" name="unidad_medida" id="unidad_medima" value="{{ old('unidad_medida') }}" class="form-control @error('unidad_medida') is-invalid @enderror" placeholder="Ej: mg/L, µg/L, etc">
                    @error('unidad_medida')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="precio" class="form-label">Precio</label>
                    <input type="number" name="precio" id="precio" value="{{ old('precio') }}" step="any" min="0" class="form-control @error('precio') is-invalid @enderror" placeholder="Ej: 5000 o 5000.50">
                    <small class="text-muted">Podés ingresar cualquier cantidad de decimales; al guardar se redondea a 2.</small>
                    @error('precio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="nota_imprimible" class="form-label">Nota imprimible (por defecto)</label>
                    <textarea name="nota_imprimible" id="nota_imprimible" rows="3" class="form-control @error('nota_imprimible') is-invalid @enderror" placeholder="Texto sugerido para la nota que verá el cliente en la cotización / PDF">{{ old('nota_imprimible') }}</textarea>
                    <small class="text-muted">Válido para componentes y agrupadores: se puede precargar al usar este ítem en cotizaciones.</small>
                    @error('nota_imprimible')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="nota_interna" class="form-label">Nota interna (por defecto)</label>
                    <textarea name="nota_interna" id="nota_interna" rows="3" class="form-control @error('nota_interna') is-invalid @enderror" placeholder="Texto de uso interno (no impreso en cotización al cliente)">{{ old('nota_interna') }}</textarea>
                    @error('nota_interna')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="card mb-3 border-info">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Notas Predeterminadas para el Informe (PDF)</span>
                        <button type="button" class="btn btn-sm btn-light" id="btn-agregar-nota">
                            <i class="bi bi-plus-lg"></i> Agregar Nota
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="notas-predeterminadas-container">
                            @php
                                $notasPredeterminadas = old('notas_predeterminadas', []);
                            @endphp
                            @foreach($notasPredeterminadas as $index => $nota)
                                <div class="nota-item mb-3 p-3 border rounded bg-light" data-index="{{ $index }}">
                                    <div class="d-flex justify-content-between mb-2">
                                        <h6 class="mb-0">Nota #<span class="nota-numero">{{ $index + 1 }}</span></h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-nota">Eliminar</button>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Título (opcional)</label>
                                        <input type="text" name="notas_predeterminadas[{{ $index }}][titulo]" class="form-control form-control-sm" value="{{ $nota['titulo'] ?? '' }}">
                                    </div>
                                    <div>
                                        <label class="form-label small">Contenido</label>
                                        <textarea name="notas_predeterminadas[{{ $index }}][contenido]" rows="3" class="form-control form-control-sm" required>{{ $nota['contenido'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted">Estas notas aparecerán automáticamente en el informe cuando se use esta determinación.</small>
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="es_muestra" name="es_muestra" value="1" {{ old('es_muestra') ? 'checked' : '' }}>
                    <label class="form-check-label" for="es_muestra">Es agrupador</label>
                </div>

                <div class="form-check form-switch mb-3 {{ old('es_muestra') ? '' : 'd-none' }}" id="agregable_a_comps_wrapper">
                    <input class="form-check-input" type="checkbox" role="switch" id="agregable_a_comps" name="agregable_a_comps" value="1" {{ old('agregable_a_comps') ? 'checked' : '' }}>
                    <label class="form-check-label" for="agregable_a_comps">Agregable como componente</label>
                    <small class="text-muted d-block">Si está marcado, este agrupador podrá ser agregado como componente en las cotizaciones, trayendo consigo sus componentes asociados.</small>
                </div>

                <div class="mb-3 {{ old('es_muestra') ? '' : 'd-none' }}" id="componentes_wrapper">
                    <label for="componentes" class="form-label">Componentes asociados</label>
                    <select name="componentes[]" id="componentes" class="form-select select2-multiple" multiple data-placeholder="Selecciona los componentes">
                        @foreach($componentes as $componente)
                            @php
                                $metodoAnalisisDisplay = optional($componente->metodoAnalitico)->metodo_descripcion;
                                $metodoCodigoAnalisis = trim((string) ($componente->metodo ?? ''));
                                if (!$metodoAnalisisDisplay && $metodoCodigoAnalisis !== '') {
                                    $metodoAnalisisDisplay = \App\Models\Metodo::query()
                                        ->whereRaw('trim(metodo_codigo) = ?', [$metodoCodigoAnalisis])
                                        ->value('metodo_descripcion');
                                }
                                $metodoDisplay = $metodoAnalisisDisplay
                                    ? ($metodoCodigoAnalisis !== '' ? $metodoCodigoAnalisis . ' - ' . trim($metodoAnalisisDisplay) : trim($metodoAnalisisDisplay))
                                    : ($metodoCodigoAnalisis !== '' ? $metodoCodigoAnalisis : 'Sin método');

                                $matrizDisplay = $componente->etiquetaMatrices();
                                $matricesCodigos = $componente->codigosMatrices();
                            @endphp
                            <option value="{{ $componente->id }}"
                                data-precio="{{ number_format($componente->precio ?? 0, 2, '.', '') }}"
                                data-matriz="{{ $matrizDisplay }}"
                                data-matrices='@json($matricesCodigos)'
                                data-metodo="{{ $metodoDisplay }}"
                                data-limites_establecidos="{{ $componente->limites_establecidos ?? 'Sin límites' }}"
                                data-unidad="{{ $componente->unidad_medida ?? 's/u' }}"
                                {{ in_array($componente->id, old('componentes', [])) ? 'selected' : '' }}>
                                {{ $componente->cotio_descripcion }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Cuando este agrupador se utilice en una cotización, estos componentes se sugerirán automáticamente.</small>
                    @error('componentes')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror

                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="fw-semibold">Orden de componentes</div>
                            <div class="text-muted small">Arrastrá y soltá para reordenar</div>
                        </div>
                        <ul class="list-group mt-2" id="componentes_orden_lista"></ul>
                        <div id="componentes_orden_inputs"></div>
                    </div>
                </div>

                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar</button>
                    <a href="{{ route('items.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->any())
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Corrige los errores',
            html: `{!! implode('<br>', $errors->all()) !!}`
        });
    });
    </script>
    @endif

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const agrupadorCheck = document.getElementById('es_muestra');
        const wrapper = document.getElementById('componentes_wrapper');
        const select = $('#componentes');
        const ordenLista = document.getElementById('componentes_orden_lista');
        const ordenInputs = document.getElementById('componentes_orden_inputs');
        const ordenState = [];

        function formatComponenteOption(option) {
            if (!option.id) {
                return option.text;
            }

            const $option = $(option.element);
            const precio = $option.data('precio') || '0.00';
            const matriz = $option.data('matriz') || 'Sin matriz';
            const metodo = $option.data('metodo') || 'Sin método';
            const unidad = $option.data('unidad') || 's/u';
            const limites = $option.data('limites_establecidos') || 'Sin límites';
            const descripcion = option.text;

            return $(
                '<div class="componente-option-item">' +
                    '<div class="fw-semibold mb-1">' + descripcion + '</div>' +
                    '<div class="d-flex flex-wrap gap-3 small text-muted">' +
                        '<span><strong>Límites:</strong> ' + limites + '</span>' +
                        '<span><strong>U. Med:</strong> ' + unidad + '</span>' +
                        '<span><strong>Matriz:</strong> ' + matriz + '</span>' +
                        '<span><strong>Mét. análisis:</strong> ' + metodo + '</span>' +
                    '</div>' +
                '</div>'
            );
        }

        function formatComponenteSelection(option) {
            if (!option.id) {
                return option.text;
            }

            const $option = $(option.element);
            const precio = $option.data('precio') || '0.00';
            const matriz = $option.data('matriz') || 'Sin matriz';
            const metodo = $option.data('metodo') || 'Sin método';
            const descripcion = option.text;

            return descripcion + ' | $' + parseFloat(precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' | ' + matriz + ' | ' + metodo;
        }

        if (select.length) {
            select.select2({
                width: '100%',
                placeholder: select.data('placeholder') || 'Selecciona los componentes',
                templateResult: formatComponenteOption,
                templateSelection: formatComponenteSelection,
                matcher: function(params, data) {
                    if (!data.id || !data.element) {
                        return data;
                    }

                    const matricesSel = getSelectedMatrices();
                    const okMatriz = componenteComparteMatriz(data.element, matricesSel);
                    if (!okMatriz) {
                        return null;
                    }

                    const term = (params.term || '').toString().trim().toLowerCase();
                    if (!term) return data;
                    const text = (data.text || '').toString().toLowerCase();
                    return text.includes(term) ? data : null;
                },
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
        }

        function getSelectedIds() {
            const val = select.val() || [];
            return val.map(v => parseInt(v, 10)).filter(n => Number.isFinite(n));
        }

        function ensureOrdenState(selectedIds) {
            // mantener orden actual, sumar nuevos al final, quitar los que ya no están
            const selectedSet = new Set(selectedIds);

            for (let i = ordenState.length - 1; i >= 0; i--) {
                if (!selectedSet.has(ordenState[i])) {
                    ordenState.splice(i, 1);
                }
            }

            selectedIds.forEach(id => {
                if (!ordenState.includes(id)) {
                    ordenState.push(id);
                }
            });
        }

        function buildOrdenUI() {
            if (!ordenLista || !ordenInputs) return;

            ordenLista.innerHTML = '';
            ordenInputs.innerHTML = '';

            ordenState.forEach(id => {
                const opt = select.find('option[value="' + id + '"]');
                const text = opt.length ? opt.text().trim() : ('ID ' + id);

                const li = document.createElement('li');
                li.className = 'list-group-item d-flex align-items-center justify-content-between componentes-orden-item';
                li.dataset.id = String(id);
                li.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <span class="componentes-orden-handle" title="Arrastrar">≡</span>
                        <span>${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                    </div>
                `;
                ordenLista.appendChild(li);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'componentes_orden[]';
                input.value = String(id);
                ordenInputs.appendChild(input);
            });
        }

        function syncOrdenFromSelect() {
            if (!select.length) return;
            const selectedIds = getSelectedIds();
            ensureOrdenState(selectedIds);
            buildOrdenUI();
        }

        if (select.length) {
            select.on('change', function() {
                syncOrdenFromSelect();
            });
        }

        // Drag & drop del orden
        if (ordenLista) {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js';
            script.onload = function() {
                Sortable.create(ordenLista, {
                    animation: 150,
                    handle: '.componentes-orden-handle',
                    onEnd: function() {
                        const newOrder = Array.from(ordenLista.querySelectorAll('li'))
                            .map(li => parseInt(li.dataset.id, 10))
                            .filter(n => Number.isFinite(n));
                        ordenState.length = 0;
                        newOrder.forEach(id => ordenState.push(id));
                        buildOrdenUI();
                    }
                });
            };
            document.head.appendChild(script);

            ordenLista.addEventListener('click', function(e) {
                const btn = e.target.closest('.js-orden-quitar');
                if (!btn) return;
                const li = btn.closest('li');
                if (!li) return;
                const id = parseInt(li.dataset.id, 10);
                if (!Number.isFinite(id)) return;

                // quitar de select2
                const current = getSelectedIds().filter(x => x !== id).map(String);
                select.val(current).trigger('change');
            });
        }

        // Inicializar select2 para matrices
        const selectMatrices = $('#matrices');
        if (selectMatrices.length) {
            selectMatrices.select2({
                width: '100%',
                placeholder: selectMatrices.data('placeholder') || 'Selecciona las matrices'
            });
        }

        function getSelectedMatrices() {
            const val = selectMatrices.length ? (selectMatrices.val() || []) : [];
            return val.map(v => (v || '').toString().trim()).filter(Boolean);
        }

        function componenteComparteMatriz(optionEl, matricesSeleccionadas) {
            if (!optionEl) return false;
            if (!matricesSeleccionadas || matricesSeleccionadas.length === 0) return true; // sin matriz en agrupador => no filtrar
            const raw = optionEl.getAttribute('data-matrices') || '[]';
            let mats = [];
            try { mats = JSON.parse(raw) || []; } catch (e) { mats = []; }
            const set = new Set(mats.map(m => (m || '').toString().trim()).filter(Boolean));
            return matricesSeleccionadas.some(m => set.has(m));
        }

        function filtrarComponentesPorMatriz() {
            if (!select.length) return;
            const isAgrupador = agrupadorCheck && agrupadorCheck.checked;
            if (!isAgrupador) return;

            const matricesSel = getSelectedMatrices();
            const selectedIds = (select.val() || []).map(v => v.toString());
            const toUnselect = [];

            selectedIds.forEach(id => {
                const opt = select.find('option[value="' + id + '"]')[0];
                if (!opt) return;
                if (!componenteComparteMatriz(opt, matricesSel)) {
                    toUnselect.push(id);
                }
            });

            if (toUnselect.length) {
                const remaining = selectedIds.filter(id => !toUnselect.includes(id));
                select.val(remaining).trigger('change');
            } else {
                select.trigger('change.select2');
            }
        }

        const agregableWrapper = document.getElementById('agregable_a_comps_wrapper');

        function toggleCamposNoAgrupador() {
            const isAgrupador = agrupadorCheck && agrupadorCheck.checked;
            document.querySelectorAll('.js-campos-no-agrupador').forEach(function(el) {
                el.classList.toggle('d-none', !!isAgrupador);
            });
        }

        function toggleComponentes() {
            const isAgrupador = agrupadorCheck && agrupadorCheck.checked;
            toggleCamposNoAgrupador();
            if (!wrapper) return;
            
            if (isAgrupador) {
                wrapper.classList.remove('d-none');
                if (agregableWrapper) {
                    agregableWrapper.classList.remove('d-none');
                }
                syncOrdenFromSelect();
                filtrarComponentesPorMatriz();
            } else {
                wrapper.classList.add('d-none');
                if (agregableWrapper) {
                    agregableWrapper.classList.add('d-none');
                }
                if (select.length) {
                    select.val(null).trigger('change');
                }
            }
        }

        if (agrupadorCheck) {
            agrupadorCheck.addEventListener('change', toggleComponentes);
        }

        if (selectMatrices.length) {
            selectMatrices.on('change', function() {
                filtrarComponentesPorMatriz();
            });
        }

        toggleComponentes();

        // Lógica para notas predeterminadas
        const container = document.getElementById('notas-predeterminadas-container');
        const btnAgregar = document.getElementById('btn-agregar-nota');
        let notaIndex = {{ count($notasPredeterminadas ?? []) }};

        if (btnAgregar && container) {
            btnAgregar.addEventListener('click', function() {
                const div = document.createElement('div');
                div.className = 'nota-item mb-3 p-3 border rounded bg-light';
                div.dataset.index = notaIndex;
                div.innerHTML = `
                    <div class="d-flex justify-content-between mb-2">
                        <h6 class="mb-0">Nota #<span class="nota-numero">${notaIndex + 1}</span></h6>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-nota">Eliminar</button>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Título (opcional)</label>
                        <input type="text" name="notas_predeterminadas[${notaIndex}][titulo]" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small">Contenido</label>
                        <textarea name="notas_predeterminadas[${notaIndex}][contenido]" rows="3" class="form-control form-control-sm" required></textarea>
                    </div>
                `;
                container.appendChild(div);
                notaIndex++;
            });

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('btn-quitar-nota')) {
                    e.target.closest('.nota-item').remove();
                    actualizarNumeracionNotas();
                }
            });
        }

        function actualizarNumeracionNotas() {
            const items = container.querySelectorAll('.nota-item');
            items.forEach((item, idx) => {
                item.querySelector('.nota-numero').textContent = idx + 1;
            });
        }
    });
    </script>

    <style>
    .componente-option-item {
        padding: 0.5rem 0;
    }
    .componente-option-item .fw-semibold {
        color: #495057;
        font-size: 0.9rem;
    }
    .componente-option-item .text-muted {
        font-size: 0.8rem;
        line-height: 1.6;
    }
    .componente-option-item .text-muted span {
        display: inline-block;
        margin-right: 1rem;
    }
    .select2-results__option--highlighted .componente-option-item .fw-semibold {
        color: #ffffff;
    }
    .select2-results__option--highlighted .componente-option-item .text-muted {
        color: rgba(255, 255, 255, 0.8);
    }

    .componentes-orden-handle {
        cursor: grab;
        user-select: none;
        font-weight: 700;
        color: #6c757d;
        width: 1.25rem;
        display: inline-flex;
        justify-content: center;
    }
    .componentes-orden-item:active .componentes-orden-handle {
        cursor: grabbing;
    }
    </style>
</div>
@endsection


