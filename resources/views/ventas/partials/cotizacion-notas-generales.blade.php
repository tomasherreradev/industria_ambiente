@php
    use App\Models\PresupuestoNota;

    $notasGeneralesIniciales = \App\Support\CotizacionNotasGenerales::listadoDesdeAlmacenamiento($notasGeneralesRaw ?? null);
    $presupuestoNotasCatalogo = PresupuestoNota::activas()->ordenadas()->get(['id', 'titulo', 'contenido']);
    $presupuestoNotasJson = $presupuestoNotasCatalogo->map(fn ($n) => [
        'id' => $n->id,
        'titulo' => trim((string) ($n->titulo ?? '')),
        'contenido' => trim((string) ($n->contenido ?? '')),
    ])->values();
@endphp

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <label class="form-label mb-0 fw-semibold">Notas generales del presupuesto</label>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarNotaGeneral">
                    <x-heroicon-o-plus style="width: 14px; height: 14px;" class="me-1" />
                    Nota en blanco
                </button>
                @if($presupuestoNotasCatalogo->isNotEmpty())
                    <button type="button" class="btn btn-sm btn-primary" id="btnToggleCatalogoNotasPresupuesto">
                        <x-heroicon-o-document-text style="width: 14px; height: 14px;" class="me-1" />
                        Desde plantilla
                    </button>
                @endif
                <a href="{{ route('ventas.notas.index') }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                    Gestionar plantillas
                </a>
            </div>
        </div>

        <small class="text-muted d-block mb-2">Se imprimen al final del presupuesto, en la sección «Notas y condiciones».</small>

        @if($presupuestoNotasCatalogo->isEmpty())
            <div class="alert alert-light border small mb-3">
                Todavía no hay plantillas guardadas.
                <a href="{{ route('ventas.notas.index') }}" target="_blank" rel="noopener">Creá notas predeterminadas</a>
                para reutilizarlas en cada presupuesto.
            </div>
        @else
            <div class="card mb-3 d-none" id="catalogoNotasPresupuestoPanel">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-semibold small">Agregar desde plantilla</div>
                            <div class="text-muted small">Elegí una nota guardada; podés editarla después en la lista de abajo.</div>
                        </div>
                        <button type="button" class="btn-close btn-sm" id="btnCerrarCatalogoNotasPresupuesto" aria-label="Cerrar"></button>
                    </div>
                    <script type="application/json" id="presupuestoNotasCatalogoData">@json($presupuestoNotasJson)</script>
                    <div class="input-group input-group-sm mb-2" style="max-width: 520px;">
                        <span class="input-group-text">
                            <x-heroicon-o-magnifying-glass style="width: 14px; height: 14px;" />
                        </span>
                        <input type="search" class="form-control" id="presupuestoNotaCatalogoBuscar" placeholder="Buscar por título o texto..." autocomplete="off">
                    </div>
                    <div class="list-group list-group-flush border rounded mb-2" id="presupuestoNotaCatalogoResultados" style="max-height: 200px; overflow-y: auto;"></div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAgregarNotaDesdeCatalogo">Agregar al presupuesto</button>
                        <span class="small text-muted" id="presupuestoNotaCatalogoMsg"></span>
                    </div>
                </div>
            </div>
        @endif

        <div id="notasGeneralesContainer"></div>
        <input type="hidden" id="coti_notas" name="coti_notas" value="">
    </div>
</div>

<script>
(function () {
    const notasGeneralesIniciales = @json($notasGeneralesIniciales);

    function escHtml(texto) {
        return String(texto ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renumerarNotasGenerales() {
        const container = document.getElementById('notasGeneralesContainer');
        if (!container) {
            return;
        }
        container.querySelectorAll('.nota-general-item').forEach(function (item, index) {
            const label = item.querySelector('.nota-general-label');
            if (label) {
                label.textContent = 'Nota #' + (index + 1);
            }
        });
    }

    function crearNotaGeneralElemento(contenido) {
        const div = document.createElement('div');
        div.className = 'card mb-2 nota-general-item';
        div.innerHTML = `
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="form-label mb-0 fw-semibold nota-general-label">Nota</span>
                    <button type="button" class="btn btn-sm btn-outline-danger btnEliminarNotaGeneral" title="Eliminar nota">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:14px;height:14px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916A2.25 2.25 0 0 0 13.5 2.25h-3A2.25 2.25 0 0 0 8.25 4.5v.916m7.5 0H8.25" />
                        </svg>
                    </button>
                </div>
                <textarea class="form-control nota-general-contenido" rows="3" placeholder="Texto de la nota...">${escHtml(contenido)}</textarea>
            </div>
        `;
        div.querySelector('.btnEliminarNotaGeneral').addEventListener('click', function () {
            div.remove();
            renumerarNotasGenerales();
            syncNotasGeneralesHidden();
        });
        div.querySelector('.nota-general-contenido').addEventListener('input', syncNotasGeneralesHidden);
        return div;
    }

    function obtenerNotasGeneralesDelDom() {
        const container = document.getElementById('notasGeneralesContainer');
        if (!container) {
            return [];
        }
        const notas = [];
        container.querySelectorAll('.nota-general-item').forEach(function (item) {
            const textarea = item.querySelector('.nota-general-contenido');
            const contenido = textarea ? String(textarea.value || '').trim() : '';
            if (contenido !== '') {
                notas.push({ contenido: contenido });
            }
        });
        return notas;
    }

    function syncNotasGeneralesHidden() {
        const hidden = document.getElementById('coti_notas');
        if (!hidden) {
            return;
        }
        const notas = obtenerNotasGeneralesDelDom();
        hidden.value = notas.length ? JSON.stringify(notas) : '';
    }

    function cargarNotasGenerales(notas) {
        const container = document.getElementById('notasGeneralesContainer');
        if (!container) {
            return;
        }
        container.innerHTML = '';
        (notas || []).forEach(function (nota) {
            const contenido = typeof nota === 'string'
                ? nota
                : (nota && nota.contenido != null ? String(nota.contenido) : '');
            if (!String(contenido).trim()) {
                return;
            }
            container.appendChild(crearNotaGeneralElemento(contenido));
        });
        renumerarNotasGenerales();
        syncNotasGeneralesHidden();
    }

    function agregarNotaGeneral(contenido) {
        const container = document.getElementById('notasGeneralesContainer');
        if (!container) {
            return;
        }
        container.appendChild(crearNotaGeneralElemento(contenido || ''));
        renumerarNotasGenerales();
        syncNotasGeneralesHidden();
        const items = container.querySelectorAll('.nota-general-item');
        const last = items[items.length - 1];
        const ta = last ? last.querySelector('.nota-general-contenido') : null;
        if (ta) {
            ta.focus();
        }
    }

    window.cotizacionNotasGeneralesSyncHidden = syncNotasGeneralesHidden;
    window.cotizacionNotasGeneralesCargar = cargarNotasGenerales;
    window.cotizacionNotasGeneralesAgregar = agregarNotaGeneral;
    window.cotizacionNotasGeneralesCargarDesdeAlmacenamiento = function (raw) {
        if (raw == null || raw === '') {
            cargarNotasGenerales([]);
            return;
        }
        if (Array.isArray(raw)) {
            cargarNotasGenerales(raw);
            return;
        }
        const str = String(raw).trim();
        if (!str) {
            cargarNotasGenerales([]);
            return;
        }
        try {
            const parsed = JSON.parse(str);
            if (Array.isArray(parsed)) {
                cargarNotasGenerales(parsed);
                return;
            }
            if (parsed && typeof parsed === 'object' && parsed.contenido != null) {
                cargarNotasGenerales([parsed]);
                return;
            }
        } catch (e) {
            // texto plano legacy
        }
        cargarNotasGenerales([{ contenido: str }]);
    };

    function initCatalogoNotasPresupuesto() {
        const panel = document.getElementById('catalogoNotasPresupuestoPanel');
        const dataEl = document.getElementById('presupuestoNotasCatalogoData');
        const resultados = document.getElementById('presupuestoNotaCatalogoResultados');
        const buscar = document.getElementById('presupuestoNotaCatalogoBuscar');
        const msg = document.getElementById('presupuestoNotaCatalogoMsg');
        const btnToggle = document.getElementById('btnToggleCatalogoNotasPresupuesto');
        const btnCerrar = document.getElementById('btnCerrarCatalogoNotasPresupuesto');
        const btnAgregar = document.getElementById('btnAgregarNotaDesdeCatalogo');

        if (!panel || !dataEl || !resultados || !btnAgregar) {
            return;
        }

        const catalogo = JSON.parse(dataEl.textContent || '[]');
        let seleccionadaId = null;

        function normalizar(text) {
            return String(text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function etiqueta(nota) {
            if (nota.titulo) {
                return nota.titulo;
            }
            const t = nota.contenido || '';
            return t.length > 70 ? t.slice(0, 70) + '…' : t;
        }

        function resumen(nota) {
            const t = nota.contenido || '';
            return t.length > 100 ? t.slice(0, 100) + '…' : t;
        }

        function filtradas() {
            const q = normalizar(buscar ? buscar.value.trim() : '');
            return catalogo.filter(function (nota) {
                if (q === '') {
                    return true;
                }
                return normalizar(nota.titulo).includes(q) || normalizar(nota.contenido).includes(q);
            });
        }

        function renderResultados() {
            const items = filtradas();
            if (seleccionadaId && !items.some(function (n) { return String(n.id) === String(seleccionadaId); })) {
                seleccionadaId = null;
            }

            if (!items.length) {
                resultados.innerHTML = '<div class="list-group-item text-muted small py-3">No hay plantillas que coincidan.</div>';
            } else {
                resultados.innerHTML = items.map(function (nota) {
                    const activa = String(nota.id) === String(seleccionadaId);
                    return '<button type="button" class="list-group-item list-group-item-action text-start py-2' + (activa ? ' active' : '') + '" data-pick-id="' + nota.id + '">' +
                        '<div class="fw-semibold small">' + escHtml(etiqueta(nota)) + '</div>' +
                        '<div class="small' + (activa ? '' : ' text-muted') + '">' + escHtml(resumen(nota)) + '</div>' +
                        '</button>';
                }).join('');
            }

            if (msg) {
                msg.textContent = items.length + (items.length === 1 ? ' plantilla disponible' : ' plantillas disponibles');
            }
        }

        function agregarSeleccionada() {
            const nota = catalogo.find(function (n) { return String(n.id) === String(seleccionadaId); });
            if (!nota) {
                if (window.Swal) {
                    Swal.fire({ icon: 'info', title: 'Elegí una plantilla', text: 'Seleccioná una nota de la lista.', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
                }
                return;
            }
            agregarNotaGeneral(nota.contenido || '');
            panel.classList.add('d-none');
            seleccionadaId = null;
            if (buscar) {
                buscar.value = '';
            }
            renderResultados();
        }

        if (btnToggle) {
            btnToggle.addEventListener('click', function () {
                panel.classList.toggle('d-none');
                if (!panel.classList.contains('d-none') && buscar) {
                    buscar.focus();
                }
                renderResultados();
            });
        }
        if (btnCerrar) {
            btnCerrar.addEventListener('click', function () {
                panel.classList.add('d-none');
            });
        }
        if (buscar) {
            buscar.addEventListener('input', renderResultados);
            buscar.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const items = filtradas();
                    if (!items.length) {
                        return;
                    }
                    seleccionadaId = seleccionadaId || items[0].id;
                    agregarSeleccionada();
                }
            });
        }
        resultados.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-pick-id]');
            if (!btn) {
                return;
            }
            seleccionadaId = btn.getAttribute('data-pick-id');
            renderResultados();
        });
        resultados.addEventListener('dblclick', function (e) {
            const btn = e.target.closest('[data-pick-id]');
            if (!btn) {
                return;
            }
            seleccionadaId = btn.getAttribute('data-pick-id');
            agregarSeleccionada();
        });
        btnAgregar.addEventListener('click', agregarSeleccionada);
    }

    function initNotasGeneralesUi() {
        const btn = document.getElementById('btnAgregarNotaGeneral');
        if (btn) {
            btn.addEventListener('click', function () {
                agregarNotaGeneral('');
            });
        }
        initCatalogoNotasPresupuesto();
        cargarNotasGenerales(notasGeneralesIniciales);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotasGeneralesUi);
    } else {
        initNotasGeneralesUi();
    }
})();
</script>
