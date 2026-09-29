<script>
(function () {
    const initCotizacionScripts = function () {
        const config = window.cotizacionConfig || {
        modo: 'create',
        puedeEditar: true,
        ensayosIniciales: [],
        componentesIniciales: [],
        coti_req_cadena_custodia_relacionada: false,
        coti_prioridad_global: false,
    };

    const MAX_NOTA_ITEM_CARACTERES = 150;

    function truncarTextoNotaItem(texto) {
        const s = String(texto ?? '');
        return s.length > MAX_NOTA_ITEM_CARACTERES ? s.slice(0, MAX_NOTA_ITEM_CARACTERES) : s;
    }

    function aplicarLimiteNotasItem(notas) {
        if (!Array.isArray(notas)) {
            return [];
        }
        return notas
            .map(function (nota) {
                const contenido = truncarTextoNotaItem(nota && nota.contenido != null ? nota.contenido : '');
                if (!contenido) {
                    return null;
                }
                return {
                    tipo: (nota && nota.tipo) || 'imprimible',
                    contenido: contenido,
                };
            })
            .filter(Boolean);
    }

    const ADJUNTOS_MAX_BYTES = 10 * 1024 * 1024;
    const ADJUNTOS_EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    function esArchivoAdjuntoValido(file) {
        if (!file) {
            return false;
        }
        const extension = String(file.name || '').split('.').pop().toLowerCase();
        if (!ADJUNTOS_EXTENSIONES.includes(extension)) {
            return false;
        }
        return file.size <= ADJUNTOS_MAX_BYTES;
    }

    function iconoAdjunto(nombre) {
        const extension = String(nombre || '').split('.').pop().toLowerCase();
        return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)
            ? 'fa-image text-info'
            : 'fa-file-pdf text-danger';
    }

    function renderEnsayoAdjuntosEnModal(listaId, ensayo) {
        const lista = document.getElementById(listaId);
        if (!lista || !ensayo) {
            return;
        }

        lista.innerHTML = '';
        const items = [];

        (ensayo.adjuntos_existentes || []).forEach(function (adj) {
            if ((ensayo.adjuntos_eliminar || []).includes(adj.id)) {
                return;
            }
            items.push({
                tipo: 'existente',
                id: adj.id,
                name: adj.name,
                url: adj.url,
            });
        });

        (ensayo.adjuntos_pendientes || []).forEach(function (file, index) {
            items.push({
                tipo: 'pendiente',
                index: index,
                name: file.name,
            });
        });

        if (items.length === 0) {
            lista.innerHTML = '<li class="list-group-item text-muted small py-1">Sin archivos adjuntos.</li>';
            return;
        }

        items.forEach(function (item) {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center py-1 px-2';

            const info = document.createElement('div');
            info.className = 'd-flex align-items-center gap-2 text-truncate me-2';
            info.innerHTML = '<i class="fas ' + iconoAdjunto(item.name) + '"></i>';

            if (item.url) {
                const link = document.createElement('a');
                link.href = item.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.className = 'text-truncate';
                link.textContent = item.name;
                info.appendChild(link);
            } else {
                const span = document.createElement('span');
                span.className = 'text-truncate';
                span.textContent = item.name + ' (nuevo)';
                info.appendChild(span);
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-danger';
            btn.innerHTML = '<i class="fas fa-times"></i>';
            btn.addEventListener('click', function () {
                if (item.tipo === 'existente') {
                    ensayo.adjuntos_eliminar = ensayo.adjuntos_eliminar || [];
                    if (!ensayo.adjuntos_eliminar.includes(item.id)) {
                        ensayo.adjuntos_eliminar.push(item.id);
                    }
                } else {
                    ensayo.adjuntos_pendientes = (ensayo.adjuntos_pendientes || []).filter(function (_, idx) {
                        return idx !== item.index;
                    });
                }
                renderEnsayoAdjuntosEnModal(listaId, ensayo);
            });

            li.appendChild(info);
            li.appendChild(btn);
            lista.appendChild(li);
        });
    }

    function capturarAdjuntosDesdeInput(inputId, ensayo, listaId) {
        const input = document.getElementById(inputId);
        if (!input || !ensayo) {
            return;
        }

        const nuevos = Array.from(input.files || []).filter(esArchivoAdjuntoValido);
        if (nuevos.length === 0) {
            return;
        }

        ensayo.adjuntos_pendientes = (ensayo.adjuntos_pendientes || []).concat(nuevos);
        input.value = '';
        renderEnsayoAdjuntosEnModal(listaId, ensayo);
    }

    function limpiarAdjuntosModalAgregar() {
        const input = document.getElementById('ensayo_adjuntos_input');
        if (input) {
            input.value = '';
        }
        const lista = document.getElementById('ensayoAdjuntosLista');
        if (lista) {
            lista.innerHTML = '';
        }
    }

    function appendAdjuntosAlFormulario(form) {
        if (!form) {
            return;
        }

        form.querySelectorAll('.ensayo-adjunto-dinamico').forEach(function (el) {
            el.remove();
        });

        let eliminarInput = form.querySelector('#ensayos_adjuntos_eliminar');
        if (!eliminarInput) {
            eliminarInput = document.createElement('input');
            eliminarInput.type = 'hidden';
            eliminarInput.name = 'ensayos_adjuntos_eliminar';
            eliminarInput.id = 'ensayos_adjuntos_eliminar';
            form.appendChild(eliminarInput);
        }

        const todosEliminar = [];
        state.ensayos.forEach(function (ensayo) {
            (ensayo.adjuntos_eliminar || []).forEach(function (id) {
                todosEliminar.push(id);
            });

            (ensayo.adjuntos_pendientes || []).forEach(function (file) {
                const input = document.createElement('input');
                input.type = 'file';
                input.name = 'ensayo_adjuntos[' + ensayo.item + '][]';
                input.className = 'ensayo-adjunto-dinamico d-none';
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                form.appendChild(input);
            });
        });

        eliminarInput.value = JSON.stringify(todosEliminar);
    }

        // console.log('[cotizacion] Inicializando state con config:', {
        //     modo: config.modo,
        //     puedeEditar: config.puedeEditar,
        //     ensayosInicialesCount: (config.ensayosIniciales || []).length,
        //     componentesInicialesCount: (config.componentesIniciales || []).length,
        //     ensayosInicialesSample: (config.ensayosIniciales || []).slice(0, 2),
        //     componentesInicialesSample: (config.componentesIniciales || []).slice(0, 2)
        // });
        
        const ensayosInicialesNorm = (config.ensayosIniciales || []).map(normalizarEnsayo);
        if (config.coti_prioridad_global) {
            ensayosInicialesNorm.forEach(function (e) {
                e.es_priori = true;
            });
        }
        const state = {
        ensayos: ensayosInicialesNorm,
        componentes: (config.componentesIniciales || []).map(normalizarComponente),
        contador: calcularContadorInicial(
            config.ensayosIniciales || [],
            config.componentesIniciales || []
        ),
        puedeEditar: config.puedeEditar !== false,
        soloReferenciasFacturacion: config.soloReferenciasFacturacion === true,
        modo: config.modo || 'create',
        clienteSeleccionado: null,
        ensayosColapsados: new Set(), // Guardar estado de colapso de ensayos
        divisaCodigo: null,
        puedeBajarPrecio: config.puedeBajarPrecio === true,
    };
    
    // console.log('[cotizacion] State inicializado:', {
    //     ensayosEnState: state.ensayos.length,
    //     componentesEnState: state.componentes.length,
    //     contador: state.contador
    // });

        const catalogs = {
        ensayos: [],
        componentes: [],
        metodosAnalisis: [],
        leyes: [],
        ensayosDefaultsById: {},
        ensayosDefaultsByCodigo: {},
    };

        const elements = {
        form: document.getElementById('cotizacionForm'),
        tablaItems: document.getElementById('tablaItems'),
        totalGeneral: document.getElementById('totalGeneral'),
        totalConAjustes: document.getElementById('totalConAjustes'),
        descuentoGlobalMonto: document.getElementById('descuentoGlobalMonto'),
        descuentoGlobalPorcentaje: document.getElementById('descuentoGlobalPorcentaje'),
        aumentoGlobalMonto: document.getElementById('aumentoGlobalMonto'),
        aumentoGlobalPorcentaje: document.getElementById('aumentoGlobalPorcentaje'),
        descuentoHidden: document.getElementById('cliente_descuento_hidden'),
        ensayosHidden: document.getElementById('ensayos_data'),
        componentesHidden: document.getElementById('componentes_data'),
        modalEnsayo: document.getElementById('modalAgregarEnsayo'),
        modalComponente: document.getElementById('modalAgregarComponente'),
        estadoSelects: Array.from(document.querySelectorAll('select[name="coti_estado"]')),
        datosAprobacionWrapper: document.getElementById('datosAprobacionWrapper'),
        datosAprobacionCard: document.getElementById('datosAprobacionCard'),
        selectEnsayo: document.getElementById('ensayo_muestra'),
        selectComponente: document.getElementById('componente_analisis'),
        selectEnsayoAsociado: document.getElementById('componente_ensayo_asociado'),
        selectEnsayoLeyNormativa: document.getElementById('ensayo_ley_normativa'),
        campoCodigoEnsayo: document.getElementById('ensayo_codigo'),
        campoCodigoComponente: document.getElementById('componente_codigo'),
        campoCantidadEnsayo: document.getElementById('cantidad_ensayo'),
        campoPrecioComponente: document.getElementById('comp_precio_final'),
        infoMetodoEnsayo: document.getElementById('ensayo_metodo_info'),
        componentesResumenLista: document.getElementById('componentesResumenLista'),
        componentesResumenPlaceholder: document.getElementById('componentesResumenPlaceholder'),
        componentesSeleccionInfo: document.getElementById('componentesSeleccionInfo'),
        btnAgregarEnsayo: document.getElementById('btnAbrirModalEnsayo'),
        btnAgregarComponente: document.getElementById('btnAbrirModalComponente'),
        sectorField: document.getElementById('sector'),
        loadingOverlay: document.getElementById('cotizacionLoadingOverlay'),
        clienteResultados: document.getElementById('clienteResultados'),
        clienteBuscadorWrapper: document.getElementById('clienteBuscadorWrapper'),
        clienteAyuda: document.getElementById('clienteBusquedaAyuda'),
        divisaSelect: document.getElementById('divisa_codigo'),
        divisaLabel: document.getElementById('divisaLabel'),
        razonSocialSelect: document.getElementById('razon_social_facturacion_select'),
        razonSocialHelp: document.getElementById('razon_social_facturacion_help'),
    };

        elements.contactoSelects = Array.from(document.querySelectorAll('.contacto-select'));

        const cotiReqCadenaRelHidden = document.getElementById('input_coti_req_cadena_custodia_relacionada');
        function setCotiReqCadenaRelHidden(val) {
            if (cotiReqCadenaRelHidden) {
                cotiReqCadenaRelHidden.value = val ? '1' : '0';
            }
        }
        function valorCotiReqCadenaRelHidden() {
            return !!(cotiReqCadenaRelHidden && cotiReqCadenaRelHidden.value === '1');
        }
        function syncCotiReqCadenaRelCheckboxesFromHidden() {
            const v = valorCotiReqCadenaRelHidden();
            ['ensayo_chk_req_cadena_relacionada', 'edit_ensayo_chk_req_cadena_relacionada'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) {
                    el.checked = v;
                }
            });
        }
        function inicializarCotiReqCadenaCustodiaRelacionadaUi() {
            if (cotiReqCadenaRelHidden && typeof config.coti_req_cadena_custodia_relacionada !== 'undefined') {
                setCotiReqCadenaRelHidden(!!config.coti_req_cadena_custodia_relacionada);
            }
            syncCotiReqCadenaRelCheckboxesFromHidden();
            ['ensayo_chk_req_cadena_relacionada', 'edit_ensayo_chk_req_cadena_relacionada'].forEach(function (id) {
                const el = document.getElementById(id);
                if (!el || el.dataset.cotiReqRelBound === '1') {
                    return;
                }
                el.dataset.cotiReqRelBound = '1';
                el.addEventListener('change', function () {
                    setCotiReqCadenaRelHidden(!!this.checked);
                    syncCotiReqCadenaRelCheckboxesFromHidden();
                });
            });
        }

        function ensayoEsPrioritarioEnState(ensayo) {
            return !!ensayo.es_priori;
        }
        function aplicarPrioridadATodosLosEnsayos(marcar) {
            state.ensayos.forEach(function (ensayo) {
                ensayo.es_priori = !!marcar;
            });
        }
        function sincronizarCheckboxGlobalPrioridad() {
            const chkGlobal = document.getElementById('coti_prioridad_global_chk');
            if (!chkGlobal) {
                return;
            }
            if (!state.ensayos.length) {
                chkGlobal.checked = false;
                return;
            }
            chkGlobal.checked = state.ensayos.every(function (e) {
                return !!e.es_priori;
            });
        }
        function prepararCheckboxPrioridadModalAgregar() {
            const chk = document.getElementById('ensayo_es_priori');
            const chkGlobal = document.getElementById('coti_prioridad_global_chk');
            if (chk) {
                chk.checked = !!(chkGlobal && chkGlobal.checked);
            }
        }
        function inicializarCotiPrioridadGlobalUi() {
            const chkGlobal = document.getElementById('coti_prioridad_global_chk');
            if (!chkGlobal) {
                return;
            }
            sincronizarCheckboxGlobalPrioridad();
            if (chkGlobal.dataset.cotiPrioridadBound !== '1') {
                chkGlobal.dataset.cotiPrioridadBound = '1';
                chkGlobal.addEventListener('change', function () {
                    aplicarPrioridadATodosLosEnsayos(!!this.checked);
                    renderTabla();
                    sincronizarCheckboxGlobalPrioridad();
                });
            }
            if (elements.btnAgregarEnsayo && elements.btnAgregarEnsayo.dataset.prioridadModalBound !== '1') {
                elements.btnAgregarEnsayo.dataset.prioridadModalBound = '1';
                elements.btnAgregarEnsayo.addEventListener('click', prepararCheckboxPrioridadModalAgregar);
            }
        }

        elements.camposComponenteInteractivos = [
        elements.campoPrecioComponente,
    ].filter(Boolean);

        // console.log('[cotizacion] elementos iniciales', {
        //     tieneForm: !!elements.form,
        //     selectsEstado: elements.estadoSelects ? elements.estadoSelects.length : 0,
        //     tieneBloqueAprobacion: !!elements.datosAprobacionWrapper
        // });

        inicializar();

        async function inicializar() {
        toggleLoading(true);
        try {
            inicializarTooltips();
            inicializarTabsLog();
            inicializarBusquedaClientes();
            inicializarSelectContactos();
            inicializarContactosUI();
            inicializarEventosTabla();
            inicializarBotonesAccion();
            inicializarBloqueAprobacion();
            await cargarCatalogos();
            inicializarEventosModales();
            inicializarCotiReqCadenaCustodiaRelacionadaUi();
            inicializarCotiPrioridadGlobalUi();
            inicializarEventosSector();
            inicializarEventosDescuentos();
            inicializarEventosDivisa();
            inicializarCondicionPagoYCuotas();
            sincronizarTotales();
            renderTabla();
            actualizarEnsayosDisponiblesParaComponentes();
            aplicarRestriccionEdicionSiCorresponde();
            cargarDatosClienteInicial();
            // Sincronizar descuentos del formulario después de cargar todo
            actualizarDescuentosDesdeFormulario();
            actualizarTotalGeneral();
        } finally {
            toggleLoading(false);
        }
    }

    function toggleLoading(show) {
        if (!elements.loadingOverlay) {
            return;
        }
        if (show) {
            elements.loadingOverlay.classList.add('is-visible');
        } else {
            elements.loadingOverlay.classList.remove('is-visible');
        }
    }

    function inicializarTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                new window.bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
                });
            }
        });
    }

    function inicializarTabsLog() {
        const tabs = document.querySelectorAll('#cotizacionTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function (event) {
                // console.log('Solapa activa:', event.target.textContent.trim());
            });
        });
    }

    function inicializarSelectContactos() {
        elements.contactoSelects = Array.from(document.querySelectorAll('.contacto-select'));
        if (!elements.contactoSelects.length) {
            return;
        }

        elements.contactoSelects.forEach((select) => {
            if (select.dataset.listenerContactos) {
                return;
            }
            select.addEventListener('change', function () {
                const indice = this.dataset.contactoIndex || '1';
                const option = this.options[this.selectedIndex];
                if (!option || !option.dataset) {
                    return;
                }

                const sufijo = indice === '1' ? '' : indice;
                const nombre = option.dataset.nombre || '';
                const email = option.dataset.email || '';
                const telefono = option.dataset.telefono || '';
                const tipo = option.dataset.tipo || '';

                asignarValorSiExiste('contacto' + sufijo, nombre);
                asignarValorSiExiste('correo' + sufijo, email);
                asignarValorSiExiste('telefono' + sufijo, telefono);
                if (tipo) {
                    asignarValorSiExiste('coti_contacto_tipo' + sufijo, tipo);
                }
            });
            select.dataset.listenerContactos = '1';
        });
    }

    function configurarContactosCliente(contactos) {
        elements.contactoSelects = Array.from(document.querySelectorAll('.contacto-select'));
        if (!elements.contactoSelects.length) {
            return;
        }

        const lista = Array.isArray(contactos) ? contactos : [];

        elements.contactoSelects.forEach((select) => {
            // Limpiar opciones actuales
            select.innerHTML = '';

            const opcionDefault = document.createElement('option');
            opcionDefault.value = '';
            opcionDefault.textContent = 'Seleccionar contacto registrado...';
            select.appendChild(opcionDefault);

            if (!lista.length) {
                select.disabled = true;
                return;
            }

            lista.forEach((contacto, index) => {
                const option = document.createElement('option');
                const id =
                    contacto.id ||
                    contacto.codigo ||
                    contacto.contacto_id ||
                    String(index + 1);
                const nombre =
                    contacto.nombre ||
                    contacto.contacto ||
                    contacto.descripcion ||
                    '';
                const email = contacto.email || contacto.correo || '';
                const telefono = contacto.telefono || '';
                const tipo =
                    contacto.tipo ||
                    contacto.tipo_contacto ||
                    contacto.contacto_tipo ||
                    '';

                option.value = id;
                option.textContent = nombre || `Contacto ${index + 1}`;
                if (email) {
                    option.textContent += ` - ${email}`;
                }

                option.dataset.nombre = nombre;
                option.dataset.email = email;
                option.dataset.telefono = telefono;
                option.dataset.tipo = tipo;

                select.appendChild(option);
            });

            select.disabled = false;
        });
    }

    function inicializarContactosUI() {
        const btnAgregar = document.getElementById('btnAgregarContacto');
        const blocks = [2, 3, 4].map((i) => document.getElementById('contacto-block-' + i));

        function getBlock(index) {
            return document.getElementById('contacto-block-' + index);
        }

        function getContactoValues(index) {
            const idx = String(index);
            const sufijo = (idx === '1') ? '' : idx;
            const tipoId = 'coti_contacto_tipo' + (idx === '1' ? '1' : idx);
            return {
                nombre: (document.getElementById('contacto' + sufijo) || {}).value || '',
                correo: (document.getElementById('correo' + sufijo) || {}).value || '',
                telefono: (document.getElementById('telefono' + sufijo) || {}).value || '',
                tipo: (document.getElementById(tipoId) || {}).value || '',
            };
        }

        function contactoYaExisteEnCliente(v) {
            const selects = document.querySelectorAll('.contacto-select');
            if (!selects.length) return false;

            const nombreNuevo = (v.nombre || '').trim().toLowerCase();
            const correoNuevo = (v.correo || '').trim().toLowerCase();
            const telefonoNuevo = (v.telefono || '').trim().toLowerCase();
            const tipoNuevo = (v.tipo || '').trim().toLowerCase();

            let existe = false;

            selects.forEach((select) => {
                Array.from(select.options).forEach((opt) => {
                    if (!opt.value) return;
                    const nombre = (opt.dataset.nombre || '').trim().toLowerCase();
                    const correo = (opt.dataset.email || '').trim().toLowerCase();
                    const telefono = (opt.dataset.telefono || '').trim().toLowerCase();
                    const tipo = (opt.dataset.tipo || '').trim().toLowerCase();

                    if (
                        nombre && nombre === nombreNuevo &&
                        (correo === correoNuevo || (!correo && !correoNuevo)) &&
                        (telefono === telefonoNuevo || (!telefono && !telefonoNuevo)) &&
                        (tipo === tipoNuevo || (!tipo && !tipoNuevo))
                    ) {
                        existe = true;
                    }
                });
            });

            return existe;
        }

        function visibleCount() {
            return 1 + blocks.filter((el) => el && !el.classList.contains('d-none')).length;
        }

        function updateBtnAgregar() {
            if (!btnAgregar) return;
            btnAgregar.disabled = visibleCount() >= 4;
        }

        // Al cargar: mostrar bloques 2-4 que tengan nombre
        [2, 3, 4].forEach((i) => {
            const block = getBlock(i);
            const nombreEl = document.getElementById('contacto' + i);
            if (block && nombreEl && nombreEl.value.trim() !== '') {
                block.classList.remove('d-none');
            }
        });
        updateBtnAgregar();

        // + Agregar otro contacto
        if (btnAgregar && !btnAgregar.dataset.contactosUiInit) {
            btnAgregar.addEventListener('click', function () {
                for (let i = 2; i <= 4; i++) {
                    const block = getBlock(i);
                    if (block && block.classList.contains('d-none')) {
                        block.classList.remove('d-none');
                        updateBtnAgregar();
                        break;
                    }
                }
            });
            btnAgregar.dataset.contactosUiInit = '1';
        }

        // Quitar contacto
        document.querySelectorAll('.btn-quitar-contacto').forEach((btn) => {
            if (btn.dataset.contactosQuitarInit) return;
            btn.addEventListener('click', function () {
                const idx = this.dataset.contactoIndex;
                if (!idx) return;
                const block = getBlock(idx);
                const nombreEl = document.getElementById('contacto' + idx);
                const correoEl = document.getElementById('correo' + idx);
                const telefonoEl = document.getElementById('telefono' + idx);
                const tipoEl = document.getElementById('coti_contacto_tipo' + idx);
                const selectEl = document.getElementById('contacto' + idx + '_selector');
                if (nombreEl) nombreEl.value = '';
                if (correoEl) correoEl.value = '';
                if (telefonoEl) telefonoEl.value = '';
                if (tipoEl) tipoEl.value = '';
                if (selectEl) selectEl.value = '';
                if (block) block.classList.add('d-none');
                updateBtnAgregar();
            });
            btn.dataset.contactosQuitarInit = '1';
        });

        // Guardar como contacto del cliente
        document.querySelectorAll('.btn-guardar-contacto').forEach((btn) => {
            if (btn.dataset.contactosGuardarInit) return;
            btn.addEventListener('click', async function () {
                if (this.dataset.guardandoContacto === '1') {
                    return;
                }

                const idx = this.dataset.contactoIndex || '1';
                const codigo = (document.getElementById('cliente_codigo') || {}).value || '';
                if (!codigo.trim()) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'warning', title: 'Sin cliente', text: 'Seleccioná un cliente antes de guardar el contacto.' });
                    } else {
                        alert('Seleccioná un cliente antes de guardar el contacto.');
                    }
                    return;
                }
                const v = getContactoValues(idx);
                if (!v.nombre.trim()) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'warning', title: 'Nombre requerido', text: 'El nombre del contacto es obligatorio.' });
                    } else {
                        alert('El nombre del contacto es obligatorio.');
                    }
                    return;
                }

                // Evitar guardar contactos duplicados para el mismo cliente
                if (contactoYaExisteEnCliente(v)) {
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'info',
                            title: 'Contacto ya existente',
                            text: 'Este contacto ya está registrado para el cliente. Podés seleccionarlo desde la columna "Seleccionar".',
                        });
                    } else {
                        alert('Este contacto ya está registrado para el cliente.');
                    }
                    return;
                }
                const url = `/api/clientes/${encodeURIComponent(codigo.trim())}/contactos`;
                const body = {
                    nombre: v.nombre.trim(),
                    telefono: v.telefono.trim() || null,
                    email: v.correo.trim() || null,
                    tipo: v.tipo.trim() || null,
                };
                const csrfToken = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const btnGuardar = this;
                const textoOriginal = btnGuardar.innerHTML;
                btnGuardar.dataset.guardandoContacto = '1';
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = 'Guardando...';

                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value) || '',
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(data.message || data.error || 'Error al guardar el contacto');
                    }
                    const contactos = data.contactos || [];
                    configurarContactosCliente(contactos);
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'success', title: 'Contacto guardado', text: 'Se agregó el contacto al cliente. Ya podés elegirlo desde "Usar contacto registrado".', timer: 2500, showConfirmButton: false });
                    } else {
                        alert('Contacto guardado correctamente.');
                    }
                } catch (e) {
                    if (window.Swal) {
                        window.Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'Error al guardar el contacto' });
                    } else {
                        alert(e.message || 'Error al guardar el contacto');
                    }
                } finally {
                    btnGuardar.dataset.guardandoContacto = '0';
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = textoOriginal;
                }
            });
            btn.dataset.contactosGuardarInit = '1';
        });
    }

    function configurarRazonesSocialesFacturacion(lista) {
        const select = elements.razonSocialSelect;
        const help = elements.razonSocialHelp;

        if (!select || !help) {
            return;
        }

        const razones = Array.isArray(lista) ? lista : [];

        // Limpiar opciones actuales
        select.innerHTML = '';

        const opcionDefault = document.createElement('option');
        opcionDefault.value = '';
        opcionDefault.textContent = 'Seleccionar razón social de facturación...';
        select.appendChild(opcionDefault);

        if (!razones.length) {
            select.classList.add('d-none');
            help.classList.add('d-none');
            return;
        }

        razones.forEach((razon) => {
            const option = document.createElement('option');
            option.value = razon.id;
            option.textContent = razon.razon_social || '';
            if (razon.cuit) {
                option.textContent += ` - CUIT ${razon.cuit}`;
            }
            if (razon.es_predeterminada) {
                option.textContent += ' (Predeterminada)';
                option.dataset.predeterminada = '1';
            }

            option.dataset.razonSocial = razon.razon_social || '';
            option.dataset.cuit = razon.cuit || '';
            option.dataset.direccion = razon.direccion || '';

            select.appendChild(option);
        });

        select.classList.remove('d-none');
        help.classList.remove('d-none');

        if (!select.dataset.listenerRazones) {
            select.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                if (!opt || !opt.value) {
                    return;
                }

                const razonSocial = opt.dataset.razonSocial || '';
                const cuit = opt.dataset.cuit || '';
                const direccion = opt.dataset.direccion || '';

                asignarValorSiExiste('empresa_nombre', razonSocial);
                asignarValorSiExiste('cuit_cliente', cuit);
                asignarValorSiExiste('direccion_cliente', direccion);
            });
            select.dataset.listenerRazones = '1';
        }
    }

    function inicializarBusquedaClientes() {
        const clienteInput = document.getElementById('cliente_codigo');
        const clienteNombre = document.getElementById('cliente_nombre');
        const btnBuscarCliente = document.getElementById('btnBuscarCliente');

        if (!clienteInput || !clienteNombre || !btnBuscarCliente) {
            return;
        }

        let searchTimeout = null;
        let ultimoTermino = '';
        const cacheResultadosClientes = {};

        clienteInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            const termino = this.value.trim();

            if (termino.length < 2) {
                clienteNombre.value = '';
                ocultarResultadosClientes();
                return;
            }

            mostrarAyudaBusqueda(false);

            searchTimeout = setTimeout(() => {
                if (termino !== ultimoTermino) {
                    ultimoTermino = termino;
                    buscarClientes(termino, clienteNombre, { cache: cacheResultadosClientes });
                } else if (termino in cacheResultadosClientes) {
                    mostrarResultadosClientes(cacheResultadosClientes[termino]);
                }
            }, 300);
        });

        clienteInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 2) {
                buscarClientes(this.value.trim(), clienteNombre, { cache: cacheResultadosClientes });
            } else {
                mostrarAyudaBusqueda(true);
            }
        });

        clienteInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const termino = this.value.trim();
                if (termino.length >= 2) {
                    buscarClientes(termino, clienteNombre, { forzar: true, cache: cacheResultadosClientes });
                } else {
                    mostrarAyudaBusqueda(true);
                }
            } else if (event.key === 'Escape') {
                ocultarResultadosClientes(true);
            }
        });

        btnBuscarCliente.addEventListener('click', function () {
            const termino = clienteInput.value.trim();
            if (termino.length >= 2) {
                buscarClientes(termino, clienteNombre, { forzar: true, cache: cacheResultadosClientes });
            }
        });

        document.addEventListener('click', function (event) {
            if (!elements.clienteBuscadorWrapper) {
                return;
            }
            if (!elements.clienteBuscadorWrapper.contains(event.target)) {
                ocultarResultadosClientes(true);
            }
        });

        mostrarAyudaBusqueda(true);
    }

    function buscarClientes(termino, clienteNombre, opciones = {}) {
        if (clienteNombre) {
            clienteNombre.value = 'Buscando...';
        }

        fetch(`/api/clientes/buscar?q=${encodeURIComponent(termino)}`)
            .then(response => response.json())
            .then(data => {
                if (opciones.cache) {
                    opciones.cache[termino] = Array.isArray(data) ? data : [];
                }

                if (!Array.isArray(data) || data.length === 0) {
                    if (clienteNombre) {
                        clienteNombre.value = 'Cliente no encontrado';
                    }
                    mostrarResultadosClientes([]);
                    return;
                }

                if (clienteNombre) {
                    clienteNombre.value = '';
                }

                if (opciones.forzar) {
                    mostrarResultadosClientes(data);
                    return;
                }

                const coincidenciaExacta = data.find(
                    cliente => cliente.codigo.trim().toLowerCase() === termino.toLowerCase()
                );

                if (coincidenciaExacta) {
                    seleccionarCliente(coincidenciaExacta.codigo);
                    ocultarResultadosClientes();
                    return;
                }

                mostrarResultadosClientes(data);
            })
            .catch(error => {
                // console.error('Error buscando clientes:', error);
                if (clienteNombre) {
                    clienteNombre.value = 'Error en la búsqueda';
                }

                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Búsqueda',
                        text: 'No se pudo realizar la búsqueda de clientes. Verifique su conexión e intente nuevamente.',
                        confirmButtonText: 'Entendido',
                    });
                }
                mostrarResultadosClientes([]);
            });
    }

    function seleccionarCliente(codigoCliente, opciones = {}) {
        if (!codigoCliente) {
            return;
        }

        fetch(`/api/clientes/${encodeURIComponent(codigoCliente)}`)
            .then(response => response.json())
            .then(cliente => {
                if (cliente.error) {
                    throw new Error(cliente.error);
                }

                completarDatosCliente(cliente, opciones);

                if (!opciones.soloDescuento) {
                    ocultarResultadosClientes(true);
                    mostrarAyudaBusqueda(false);
                }

                if (!opciones.silencioso && window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cliente Seleccionado',
                        text: `Se han autocompletado los datos de ${cliente.razon_social}`,
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    });
                }
            })
            .catch(error => {
                // console.error('Error obteniendo datos del cliente:', error);

                if (!opciones.silencioso && window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al Cargar Cliente',
                        text: 'No se pudieron cargar los datos del cliente seleccionado. Intente nuevamente.',
                        confirmButtonText: 'Entendido',
                    });
                }
            });
    }

    function completarDatosCliente(cliente, opciones = {}) {
        const soloDescuento = opciones.soloDescuento === true;
        const clienteInput = document.getElementById('cliente_codigo');
        const clienteNombre = document.getElementById('cliente_nombre');
        const clonOverride = (typeof window !== 'undefined' && window.__clonCotizacionOverrideActive && window.__clonCotizacionOverride)
            ? window.__clonCotizacionOverride
            : null;

        const descuentoGlobalCliente = parseFloat(
            cliente.descuento_global ?? cliente.descuentoglobal ?? cliente.descuento ?? 0
        );
        state.clienteSeleccionado = {
            codigo: cliente.codigo || '',
            descuento_global: isNaN(descuentoGlobalCliente) ? 0 : descuentoGlobalCliente,
            es_consultor: cliente.es_consultor === true || cliente.es_consultor === 1 || cliente.es_consultor === '1',
        };

        if (elements.descuentoHidden) {
            elements.descuentoHidden.dataset.descuentoGlobal = state.clienteSeleccionado.descuento_global.toFixed(2);
        }

        if (!soloDescuento) {
            if (clienteInput) {
                clienteInput.value = cliente.codigo || '';
            }
            if (clienteNombre) {
                clienteNombre.value = cliente.razon_social || '';
            }

            // Si hay una razón social de facturación predeterminada, usar esos datos para la solapa Empresa
            // Si no, usar los datos por defecto del cliente
            const razonSocialEmpresa = cliente.razon_social_facturacion || cliente.razon_social;
            const direccionEmpresa = cliente.direccion_facturacion || cliente.direccion;
            const cuitEmpresa = cliente.cuit_facturacion || cliente.cuit;
            const localidadEmpresa = cliente.localidad_facturacion || cliente.localidad;
            const codigoPostalEmpresa = cliente.codigo_postal_facturacion || cliente.codigo_postal;

            // Campos de la solapa Empresa (usar datos de razón social predeterminada si existe)
            asignarValorSiExiste('empresa_nombre', razonSocialEmpresa);
            asignarValorSiExiste('direccion_cliente', direccionEmpresa);
            asignarValorSiExiste('localidad_cliente', localidadEmpresa);
            asignarValorSiExiste('cuit_cliente', cuitEmpresa);
            asignarValorSiExiste('codigo_postal_cliente', codigoPostalEmpresa);

            // Configurar selector de razones sociales de facturación (si existen)
            configurarRazonesSocialesFacturacion(
                cliente.razones_sociales_facturacion || []
            );
            
            // Campos de la solapa General (usar siempre datos del cliente),
            // salvo que estemos clonando una cotización y debamos conservar los del presupuesto.
            if (!clonOverride) {
                asignarValorSiExiste('telefono', cliente.telefono);
                asignarValorSiExiste('correo', cliente.email);
                asignarValorSiExiste('sector', cliente.sector);
                asignarValorSiExiste('contacto', cliente.contacto);
            }
            // Configurar contactos registrados del cliente (si existen)
            configurarContactosCliente(
                cliente.contactos ||
                cliente.contactos_cliente ||
                []
            );
            
            // Condición de pago:
            // - En creación de cotización, siempre se toma la del cliente
            // - En edición, solo se actualiza si el select está vacío (no pisar lo ya guardado)
            const selCondPago = document.getElementById('coti_cond_pago');
            const puedeActualizarCondicion =
                state.modo === 'create' ||
                !selCondPago ||
                !selCondPago.value;
            if (puedeActualizarCondicion) {
                asignarValorSiExiste('coti_cond_pago', cliente.condicion_pago);
                // Actualizar visibilidad del panel de cuotas según condición de pago
                if (selCondPago) {
                    selCondPago.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // Campos hidden (usar datos de razón social predeterminada si existe para los campos de empresa)
            asignarValorSiExiste('cliente_razon_social_hidden', razonSocialEmpresa);
            asignarValorSiExiste('cliente_direccion_hidden', direccionEmpresa);
            asignarValorSiExiste('cliente_localidad_hidden', localidadEmpresa);
            asignarValorSiExiste('cliente_cuit_hidden', cuitEmpresa);
            asignarValorSiExiste('cliente_codigo_postal_hidden', codigoPostalEmpresa);
            asignarValorSiExiste('cliente_telefono_hidden', cliente.telefono);
            asignarValorSiExiste('cliente_correo_hidden', cliente.email);
            asignarValorSiExiste('cliente_sector_hidden', cliente.sector);
            
            // Cargar empresas relacionadas del cliente solo si es consultor
            actualizarVisibilidadNavEmpresaRelacionada();
            if (state.clienteSeleccionado.es_consultor) {
                limpiarPanelEmpresaRelacionada();
                cargarEmpresasRelacionadas(cliente.codigo);
            } else {
                // Si no es consultor, asegurar que el campo "Para" sea un input de texto
                resetearCampoPara();
                limpiarPanelEmpresaRelacionada();
            }

            // Configurar sucursales si el cliente tiene
            if (Array.isArray(cliente.sucursales) && cliente.sucursales.length > 0) {
                configurarSucursalesCliente(cliente.sucursales);
            } else {
                resetearSucursalesCliente();
            }

            // Re-aplicar sucursal/contactos del clon DESPUÉS de autocompletar datos del cliente.
            if (clonOverride) {
                const aplicarOverride = () => {
                    try {
                        const suc = (clonOverride.sucursal || '').toString().trim();
                        const sucursalInput = document.getElementById('sucursal');
                        const sucursalSelect = document.getElementById('sucursal_select');
                        if (sucursalInput) {
                            sucursalInput.value = suc;
                        }
                        if (sucursalSelect && suc) {
                            fijarValorSucursalSelect(sucursalSelect, suc);
                        }

                        const conts = clonOverride.contactos || {};
                        const setContacto = (idx, v) => {
                            const suf = idx === 1 ? '' : String(idx);
                            const nombre = (v && v.nombre) ? v.nombre : '';
                            const correo = (v && v.correo) ? v.correo : '';
                            const tel = (v && v.tel) ? v.tel : '';
                            const tipo = (v && v.tipo) ? v.tipo : '';

                            if (idx > 1 && (nombre || correo || tel || tipo)) {
                                const block = document.getElementById(`contacto-block-${idx}`);
                                if (block) block.classList.remove('d-none');
                            }

                            asignarValorSiExiste('contacto' + suf, nombre);
                            asignarValorSiExiste('correo' + suf, correo);
                            asignarValorSiExiste('telefono' + suf, tel);
                            if (tipo) {
                                asignarValorSiExiste('coti_contacto_tipo' + (idx === 1 ? '1' : String(idx)), tipo);
                            }
                        };
                        setContacto(1, conts.c1);
                        setContacto(2, conts.c2);
                        setContacto(3, conts.c3);
                        setContacto(4, conts.c4);

                        window.__clonCotizacionOverrideActive = false;
                    } catch (e) {
                        // no-op
                    }
                };

                setTimeout(aplicarOverride, 0);
                setTimeout(aplicarOverride, 200);
                setTimeout(aplicarOverride, 600);
            }
        }

        actualizarDescuentoCliente();
        actualizarTotalGeneral();
    }

    function etiquetaPrincipalSucursal(sucursal) {
        const fantasia = (sucursal.fantasia || '').trim();
        const localidad = (sucursal.localidad || '').trim();
        const direccion = (sucursal.direccion || '').trim();
        const codigo = (sucursal.codigo || '').trim();

        if (fantasia) {
            return fantasia;
        }
        if (localidad) {
            return localidad;
        }
        if (direccion) {
            return direccion;
        }
        if (codigo) {
            return `Sucursal ${codigo}`;
        }

        return 'Sucursal';
    }

    function detallesSucursal(sucursal) {
        const partes = [];
        const codigo = (sucursal.codigo || '').trim();
        const localidad = (sucursal.localidad || '').trim();
        const partido = (sucursal.partido || '').trim();
        const direccion = (sucursal.direccion || '').trim();
        const fantasia = (sucursal.fantasia || '').trim();

        if (codigo) {
            partes.push(`Cód. ${codigo}`);
        }
        if (localidad && localidad !== fantasia) {
            partes.push(localidad);
        }
        if (partido && partido !== localidad) {
            partes.push(partido);
        }
        if (direccion && direccion !== fantasia) {
            partes.push(direccion);
        }

        return partes;
    }

    function etiquetaSeleccionSucursal(sucursal) {
        const principal = etiquetaPrincipalSucursal(sucursal);
        const detalles = detallesSucursal(sucursal);
        const extra = detalles.find(function (detalle) {
            return !detalle.startsWith('Cód.') && detalle !== principal;
        });

        if (extra) {
            return `${principal} · ${extra}`;
        }

        const codigo = (sucursal.codigo || '').trim();
        if (codigo && !principal.includes(codigo)) {
            return `${principal} (${codigo})`;
        }

        return principal;
    }

    function destruirSelect2Sucursal(sucursalSelect) {
        if (!sucursalSelect || !$.fn.select2) {
            return;
        }

        if ($(sucursalSelect).hasClass('select2-hidden-accessible')) {
            $(sucursalSelect).select2('destroy');
        }
    }

    function inicializarSelect2Sucursal(sucursalSelect) {
        if (!sucursalSelect || !$.fn.select2) {
            return;
        }

        destruirSelect2Sucursal(sucursalSelect);

        $(sucursalSelect).select2({
            width: '100%',
            placeholder: 'Seleccionar sucursal...',
            allowClear: true,
            dropdownAutoWidth: true,
            templateResult: function (sucursal) {
                if (!sucursal.id) {
                    return sucursal.text;
                }

                const $option = $(sucursal.element);
                const titulo = $option.data('titulo') || sucursal.text;
                const detalles = ($option.data('detalles') || '').toString();
                const detallesHtml = detalles
                    ? `<div class="small text-muted mt-1">${detalles}</div>`
                    : '';

                return $(
                    '<div class="sucursal-option-item">' +
                        `<div class="fw-semibold">${titulo}</div>` +
                        detallesHtml +
                    '</div>'
                );
            },
            templateSelection: function (sucursal) {
                if (!sucursal.id) {
                    return sucursal.text;
                }

                const $option = $(sucursal.element);
                return $option.data('etiquetaCorta') || sucursal.text;
            },
            escapeMarkup: function (markup) {
                return markup;
            },
        });
    }

    function fijarValorSucursalSelect(sucursalSelect, codigo) {
        if (!sucursalSelect) {
            return;
        }

        const codigoNormalizado = (codigo || '').trim();
        if (!codigoNormalizado) {
            sucursalSelect.value = '';
            if ($.fn.select2 && $(sucursalSelect).hasClass('select2-hidden-accessible')) {
                $(sucursalSelect).val('').trigger('change');
            }
            return;
        }

        const opcion = Array.from(sucursalSelect.options || []).find(function (o) {
            return (o.value || '').trim() === codigoNormalizado;
        });

        if (!opcion) {
            return;
        }

        sucursalSelect.value = opcion.value;
        if ($.fn.select2 && $(sucursalSelect).hasClass('select2-hidden-accessible')) {
            $(sucursalSelect).val(opcion.value).trigger('change');
        } else {
            sucursalSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function configurarSucursalesCliente(sucursales) {
        const sucursalInput = document.getElementById('sucursal');
        const sucursalSelect = document.getElementById('sucursal_select');

        if (!sucursalInput || !sucursalSelect) {
            return;
        }

        state.sucursalesCliente = Array.isArray(sucursales) ? sucursales : [];

        destruirSelect2Sucursal(sucursalSelect);
        sucursalSelect.innerHTML = '';
        const opcionDefault = document.createElement('option');
        opcionDefault.value = '';
        opcionDefault.textContent = 'Seleccionar sucursal...';
        sucursalSelect.appendChild(opcionDefault);

        state.sucursalesCliente.forEach((sucursal) => {
            const option = document.createElement('option');
            option.value = sucursal.codigo || '';
            const titulo = etiquetaPrincipalSucursal(sucursal);
            const detalles = detallesSucursal(sucursal).join(' · ');
            option.textContent = titulo;
            option.dataset.titulo = titulo;
            option.dataset.detalles = detalles;
            option.dataset.etiquetaCorta = etiquetaSeleccionSucursal(sucursal);
            option.dataset.fantasia = sucursal.fantasia || '';
            option.dataset.localidad = sucursal.localidad || '';
            option.dataset.codigo = sucursal.codigo || '';
            sucursalSelect.appendChild(option);
        });

        sucursalInput.classList.add('d-none');
        sucursalSelect.classList.remove('d-none');
        inicializarSelect2Sucursal(sucursalSelect);

        if (!sucursalSelect.dataset.listenerSucursal) {
            sucursalSelect.addEventListener('change', manejarCambioSucursal);
            sucursalSelect.dataset.listenerSucursal = '1';
        }
    }

    function resetearSucursalesCliente() {
        const sucursalInput = document.getElementById('sucursal');
        const sucursalSelect = document.getElementById('sucursal_select');
        if (!sucursalInput || !sucursalSelect) {
            return;
        }

        state.sucursalesCliente = [];
        destruirSelect2Sucursal(sucursalSelect);
        sucursalSelect.classList.add('d-none');
        sucursalInput.classList.remove('d-none');
        sucursalSelect.innerHTML = '<option value=\"\">Seleccionar sucursal...</option>';
    }

    function manejarCambioSucursal(event) {
        const codigoSeleccionado = (event.target.value || '').trim();
        const sucursalInput = document.getElementById('sucursal');
        if (sucursalInput) {
            sucursalInput.value = codigoSeleccionado;
        }
        // No aplicar datos de la sucursal a la solapa Empresa (facturación).
        // La sucursal es el destinatario; la solapa Empresa es solo razón social de facturación.
    }

    function cargarEmpresasRelacionadas(codigoCliente, empresaIdPreseleccionado = null) {
        if (!codigoCliente) {
            resetearCampoPara();
            return;
        }

        // Verificar que el cliente sea consultor antes de cargar empresas relacionadas
        if (!state.clienteSeleccionado || !state.clienteSeleccionado.es_consultor) {
            resetearCampoPara();
            return;
        }

        // console.log('Cargando empresas relacionadas para cliente consultor:', codigoCliente);
        
        fetch(`/api/clientes/${encodeURIComponent(codigoCliente)}/empresas-relacionadas`)
            .then(response => {
                // console.log('Respuesta de API empresas relacionadas:', response.status);
                return response.json();
            })
            .then(empresas => {
                // console.log('Empresas relacionadas recibidas:', empresas);
                
                const inputPara = document.getElementById('coti_para');
                const selectPara = document.getElementById('coti_para_select');
                const hiddenEmpresaId = document.getElementById('coti_empresa_rel');
                
                if (!inputPara || !selectPara || !hiddenEmpresaId) {
                    // console.error('Elementos del DOM no encontrados:', {
                    //     inputPara: !!inputPara,
                    //     selectPara: !!selectPara,
                    //     hiddenEmpresaId: !!hiddenEmpresaId
                    // });
                    return;
                }

                if (empresas && Array.isArray(empresas) && empresas.length > 0) {
                    // console.log('Convirtiendo a select. Empresas encontradas:', empresas.length);
                    // Hay empresas relacionadas, convertir a select
                    selectPara.innerHTML = '<option value="">Seleccionar empresa relacionada...</option>';
                    
                    empresas.forEach(empresa => {
                        const option = document.createElement('option');
                        option.value = empresa.id; // Usar ID en lugar de razón social
                        option.textContent = empresa.razon_social;
                        option.dataset.empresaId = empresa.id;
                        option.dataset.razonSocial = empresa.razon_social;
                        option.dataset.cuit = empresa.cuit || '';
                        option.dataset.direcciones = empresa.direcciones || '';
                        option.dataset.localidad = empresa.localidad || '';
                        option.dataset.partido = empresa.partido || '';
                        option.dataset.contacto = empresa.contacto || '';
                        // Preseleccionar si el ID coincide (comparar como string para evitar tipo número vs string)
                        const idCoincide = empresaIdPreseleccionado && (String(empresa.id) === String(empresaIdPreseleccionado));
                        if (idCoincide) {
                            option.selected = true;
                            hiddenEmpresaId.value = String(empresa.id);
                            inputPara.value = empresa.razon_social || '';
                        }
                        
                        selectPara.appendChild(option);
                    });

                    // Ocultar input y mostrar select
                    inputPara.classList.add('d-none');
                    selectPara.classList.remove('d-none');
                    
                    // Inicializar Select2 con template personalizado
                    if ($.fn.select2) {
                        // Destruir Select2 si ya existe
                        if ($(selectPara).hasClass('select2-hidden-accessible')) {
                            $(selectPara).select2('destroy');
                        }
                        
                        $(selectPara).select2({
                            width: '100%',
                            placeholder: 'Seleccionar empresa relacionada...',
                            allowClear: true,
                            templateResult: function(empresa) {
                                if (!empresa.id) {
                                    return empresa.text;
                                }
                                
                                const $option = $(empresa.element);
                                const razonSocial = $option.data('razonSocial') || empresa.text;
                                const cuit = $option.data('cuit') || '';
                                const direcciones = $option.data('direcciones') || '';
                                const localidad = $option.data('localidad') || '';
                                const partido = $option.data('partido') || '';
                                const contacto = $option.data('contacto') || '';
                                
                                let detalles = [];
                                if (direcciones) detalles.push(`<strong>Dirección:</strong> ${direcciones}`);
                                if (localidad) detalles.push(`<strong>Localidad:</strong> ${localidad}`);
                                if (partido) detalles.push(`<strong>Partido:</strong> ${partido}`);
                                if (cuit) detalles.push(`<strong>CUIT:</strong> ${cuit}`);
                                if (contacto) detalles.push(`<strong>Contacto:</strong> ${contacto}`);
                                
                                const detallesHtml = detalles.length > 0 
                                    ? `<div class="small text-muted mt-1">${detalles.join(' | ')}</div>` 
                                    : '';
                                
                                return $(
                                    '<div class="empresa-option-item">' +
                                        `<div class="fw-semibold">${razonSocial}</div>` +
                                        detallesHtml +
                                    '</div>'
                                );
                            },
                            templateSelection: function(empresa) {
                                if (!empresa.id) {
                                    return empresa.text;
                                }
                                const $option = $(empresa.element);
                                const razonSocial = ($option.data('razonSocial') || empresa.text || '').trim();
                                const localidad = ($option.data('localidad') || '').trim();
                                const cuit = ($option.data('cuit') || '').trim();

                                if (localidad && razonSocial.length > 26) {
                                    return `${localidad} — ${razonSocial}`;
                                }
                                if (cuit && razonSocial.length > 30) {
                                    return `${cuit} — ${razonSocial}`;
                                }

                                return razonSocial;
                            },
                            escapeMarkup: function(markup) {
                                return markup;
                            }
                        });
                        $(selectPara).on('select2:select', function () {
                            setTimeout(actualizarEmpresaSeleccionada, 0);
                        });
                        $(selectPara).on('select2:clear', function () {
                            setTimeout(actualizarEmpresaSeleccionada, 0);
                        });
                    }
                    
                    // Agregar event listener para actualizar el campo hidden cuando cambie la selección
                    selectPara.removeEventListener('change', actualizarEmpresaSeleccionada);
                    selectPara.addEventListener('change', actualizarEmpresaSeleccionada);
                    
                    // Si hay selección (incl. preselección en edición), solo actualizar campo "Para" e ID;
                    // y pisar los datos base con la empresa relacionada (consultoras).
                    const opcionSeleccionada = selectPara.options[selectPara.selectedIndex];
                    if (opcionSeleccionada && opcionSeleccionada.value) {
                        if (hiddenEmpresaId) hiddenEmpresaId.value = opcionSeleccionada.value;
                        if (inputPara) {
                            inputPara.value = opcionSeleccionada.dataset.razonSocial || opcionSeleccionada.getAttribute('data-razon-social') || opcionSeleccionada.textContent;
                        }
                        aplicarDatosEmpresaRelacionada(opcionSeleccionada);
                    }
                    
                    // Si hay un ID preseleccionado y no se encontró en las opciones, mantenerlo en el input
                    if (empresaIdPreseleccionado && !selectPara.value) {
                        inputPara.value = '';
                        inputPara.classList.remove('d-none');
                        selectPara.classList.add('d-none');
                        hiddenEmpresaId.value = empresaIdPreseleccionado;
                    }
                    
                    // Forzar que Select2 muestre la opción preseleccionada (edición)
                    if (selectPara.value && $.fn.select2) {
                        $(selectPara).val(selectPara.value).trigger('change');
                    }
                } else {
                    // No hay empresas relacionadas: si había ID guardado (edición), dejar input visible con su valor
                    if (empresaIdPreseleccionado) {
                        const inputPara = document.getElementById('coti_para');
                        const selectPara = document.getElementById('coti_para_select');
                        if (inputPara && selectPara) {
                            inputPara.classList.remove('d-none');
                            selectPara.classList.add('d-none');
                            selectPara.innerHTML = '<option value="">Seleccionar empresa relacionada...</option>';
                        }
                    } else {
                        resetearCampoPara();
                    }
                }
            })
            .catch(error => {
                // console.error('Error cargando empresas relacionadas:', error);
                resetearCampoPara();
            });
    }

    function actualizarEmpresaSeleccionada() {
        const selectPara = document.getElementById('coti_para_select');
        const hiddenEmpresaId = document.getElementById('coti_empresa_rel');
        const inputPara = document.getElementById('coti_para');
        
        if (!selectPara || !hiddenEmpresaId) {
            return;
        }
        
        const valorSeleccionado = (selectPara.value || '').trim();
        const selectedOption = valorSeleccionado
            ? Array.from(selectPara.options).find(function (o) { return String(o.value || '').trim() === String(valorSeleccionado).trim(); })
            : null;
        
        if (selectedOption && selectedOption.value) {
            hiddenEmpresaId.value = selectedOption.value;
            if (inputPara) {
                inputPara.value = selectedOption.dataset.razonSocial || selectedOption.getAttribute('data-razon-social') || selectedOption.textContent;
            }
            // Si el usuario selecciona una empresa relacionada (consultoras), reemplazar los datos
            // inicialmente completados desde el cliente con los de la empresa relacionada.
            aplicarDatosEmpresaRelacionada(selectedOption);
        } else {
            hiddenEmpresaId.value = '';
            if (inputPara) {
                inputPara.value = '';
            }
            restablecerDatosDesdeClienteOSucursal();
        }
    }

    /**
     * Aplica los datos de la empresa relacionada seleccionada a Empresa, Dirección, Localidad, etc.
     * Replica la lógica de manejarCambioSucursal pero con los datos de la opción seleccionada.
     */
    function aplicarDatosEmpresaRelacionada(option) {
        if (!option) {
            return;
        }
        const getData = function (camelKey) {
            const kebab = camelKey.replace(/([A-Z])/g, '-$1').toLowerCase();
            return (option.dataset && option.dataset[camelKey]) || option.getAttribute('data-' + kebab) || '';
        };
        const razonSocial = (getData('razonSocial') || '').toString().trim();
        const direcciones = (getData('direcciones') || '').toString().trim();
        const localidad = (getData('localidad') || '').toString().trim();
        const partido = (getData('partido') || '').toString().trim();
        const cuit = (getData('cuit') || '').toString().trim();
        const contacto = (getData('contacto') || '').toString().trim();

        rellenarPanelEmpresaRelacionada({
            razonSocial: razonSocial,
            direccion: direcciones,
            localidad: localidad,
            partido: partido,
            cuit: cuit,
            contacto: contacto,
        });
    }

    function obtenerRefsPanelEmpresaRelacionada() {
        return {
            nav: document.getElementById('empresaRelacionadaTabNav'),
            sinSel: document.getElementById('empresa_rel_sin_seleccion'),
            wrap: document.getElementById('empresa_rel_datos_wrapper'),
            razon: document.getElementById('empresa_rel_razon_social'),
            dir: document.getElementById('empresa_rel_direccion'),
            loc: document.getElementById('empresa_rel_localidad'),
            part: document.getElementById('empresa_rel_partido'),
            cuit: document.getElementById('empresa_rel_cuit'),
            contacto: document.getElementById('empresa_rel_contacto'),
        };
    }

    function limpiarPanelEmpresaRelacionada() {
        const r = obtenerRefsPanelEmpresaRelacionada();
        if (!r.sinSel || !r.wrap) {
            return;
        }
        [r.razon, r.dir, r.loc, r.part, r.cuit, r.contacto].forEach(function (el) {
            if (el) {
                el.value = '';
            }
        });
        r.sinSel.classList.remove('d-none');
        r.wrap.classList.add('d-none');
    }

    function rellenarPanelEmpresaRelacionada(data) {
        const r = obtenerRefsPanelEmpresaRelacionada();
        if (!r.sinSel || !r.wrap) {
            return;
        }
        const razon = (data && data.razonSocial) ? String(data.razonSocial).trim() : '';
        const dir = (data && data.direccion) ? String(data.direccion).trim() : '';
        const loc = (data && data.localidad) ? String(data.localidad).trim() : '';
        const part = (data && data.partido) ? String(data.partido).trim() : '';
        const cuit = (data && data.cuit) ? String(data.cuit).trim() : '';
        const contacto = (data && data.contacto) ? String(data.contacto).trim() : '';
        const tiene = !!(razon || dir || loc || part || cuit || contacto);
        if (!tiene) {
            limpiarPanelEmpresaRelacionada();
            return;
        }
        if (r.razon) {
            r.razon.value = razon;
        }
        if (r.dir) {
            r.dir.value = dir;
        }
        if (r.loc) {
            r.loc.value = loc;
        }
        if (r.part) {
            r.part.value = part;
        }
        if (r.cuit) {
            r.cuit.value = cuit;
        }
        if (r.contacto) {
            r.contacto.value = contacto;
        }
        r.sinSel.classList.add('d-none');
        r.wrap.classList.remove('d-none');
    }

    function actualizarVisibilidadNavEmpresaRelacionada() {
        const r = obtenerRefsPanelEmpresaRelacionada();
        if (!r.nav) {
            return;
        }
        const ok = !!(state.clienteSeleccionado && state.clienteSeleccionado.es_consultor);
        r.nav.classList.toggle('d-none', !ok);
    }

    /**
     * Restaura los campos Empresa/Dirección/Contacto etc. a los datos base:
     * primero los del cliente (hidden) y luego, si hay sucursal seleccionada, aplica los de la sucursal.
     */
    function restablecerDatosDesdeClienteOSucursal() {
        const razonSocialCliente = document.getElementById('cliente_razon_social_hidden')?.value || '';
        const direccionCliente = document.getElementById('cliente_direccion_hidden')?.value || '';
        const localidadCliente = document.getElementById('cliente_localidad_hidden')?.value || '';
        const codigoPostalCliente = document.getElementById('cliente_codigo_postal_hidden')?.value || '';
        const telefonoCliente = document.getElementById('cliente_telefono_hidden')?.value || '';
        const correoCliente = document.getElementById('cliente_correo_hidden')?.value || '';
        const cuitCliente = document.getElementById('cliente_cuit_hidden')?.value || '';

        asignarValorSiExiste('empresa_nombre', razonSocialCliente);
        asignarValorSiExiste('direccion_cliente', direccionCliente);
        asignarValorSiExiste('localidad_cliente', localidadCliente);
        asignarValorSiExiste('partido', '');
        asignarValorSiExiste('codigo_postal_cliente', codigoPostalCliente);
        asignarValorSiExiste('cuit_cliente', cuitCliente);
        asignarValorSiExiste('contacto', '');
        asignarValorSiExiste('correo', correoCliente);
        asignarValorSiExiste('telefono', telefonoCliente);

        const sucursalSelect = document.getElementById('sucursal_select');
        if (sucursalSelect && !sucursalSelect.classList.contains('d-none') && sucursalSelect.value) {
            manejarCambioSucursal({ target: sucursalSelect });
        }

        limpiarPanelEmpresaRelacionada();
    }

    function resetearCampoPara() {
        const inputPara = document.getElementById('coti_para');
        const selectPara = document.getElementById('coti_para_select');
        const hiddenEmpresaId = document.getElementById('coti_empresa_rel');
        
        if (inputPara && selectPara) {
            inputPara.classList.remove('d-none');
            selectPara.classList.add('d-none');
            selectPara.innerHTML = '<option value="">Seleccionar empresa relacionada...</option>';
        }
        
        if (hiddenEmpresaId) {
            hiddenEmpresaId.value = '';
        }

        limpiarPanelEmpresaRelacionada();
    }

    function actualizarDescuentoCliente() {
        if (!elements.descuentoHidden || !state.clienteSeleccionado) {
            return;
        }

        const descuentoGlobal = Number(state.clienteSeleccionado.descuento_global) || 0;

        elements.descuentoHidden.value = descuentoGlobal.toFixed(2);
        elements.descuentoHidden.dataset.descuentoGlobal = descuentoGlobal.toFixed(2);
    }

    function obtenerSectorActual() {
        const campoSector = elements.sectorField || document.getElementById('sector');
        if (!campoSector) {
            return '';
        }

        if (campoSector.tagName === 'SELECT') {
            return campoSector.value || '';
        }

        return campoSector.value || '';
    }

    function obtenerSectorEtiqueta() {
        const campoSector = elements.sectorField || document.getElementById('sector');
        if (!campoSector) {
            return '';
        }

        if (campoSector.tagName === 'SELECT') {
            const opcionSeleccionada = campoSector.selectedOptions && campoSector.selectedOptions.length
                ? campoSector.selectedOptions[0]
                : campoSector.options[campoSector.selectedIndex];
            if (opcionSeleccionada) {
                return opcionSeleccionada.textContent.trim();
            }
            return campoSector.value || '';
        }

        return campoSector.value || '';
    }

    function normalizarMapaDescuentos(descuentosRaw) {
        const mapaBase = {
            LAB: 0,
            HYS: 0,
            MIC: 0,
            CRO: 0,
        };

        if (!descuentosRaw || typeof descuentosRaw !== 'object') {
            return mapaBase;
        }

        Object.entries(descuentosRaw).forEach(([clave, valor]) => {
            const claveNormalizada = normalizarClaveSector(clave);
            if (!claveNormalizada || !(claveNormalizada in mapaBase)) {
                return;
            }
            const numero = parseFloat(valor);
            if (!isNaN(numero)) {
                mapaBase[claveNormalizada] = numero;
            }
        });

        return mapaBase;
    }

    function normalizarClaveSector(valor) {
        if (!valor) {
            return null;
        }

        const texto = valor.toString().trim().toUpperCase();
        if (!texto) {
            return null;
        }

        const mapa = {
            'LABORATORIO': 'LAB',
            'LAB': 'LAB',
            'HIGIENE Y SEGURIDAD': 'HYS',
            'HYS': 'HYS',
            'MICROBIOLOGIA': 'MIC',
            'MIC': 'MIC',
            'CROMATOGRAFIA': 'CRO',
            'CRO': 'CRO',
        };

        if (mapa[texto]) {
            return mapa[texto];
        }

        const abreviado = texto.slice(0, 3);
        return mapa[abreviado] ?? null;
    }

    function inicializarEventosSector() {
        const sectorInput = elements.sectorField || document.getElementById('sector');
        if (!sectorInput) {
            return;
        }

        const eventos = new Set(['change']);
        if (sectorInput.tagName !== 'SELECT') {
            eventos.add('input');
            eventos.add('blur');
        }

        eventos.forEach(evento => {
            sectorInput.addEventListener(evento, () => {
                actualizarDescuentosDesdeFormulario();
                if (state.clienteSeleccionado) {
                    actualizarDescuentoCliente();
                }
                actualizarTotalGeneral();
            });
        });
    }

    function inicializarEventosDescuentos() {
        // Listener para descuento global
        const descuentoGlobalInput = document.getElementById('descuento');
        if (descuentoGlobalInput) {
            descuentoGlobalInput.addEventListener('input', () => {
                actualizarDescuentosDesdeFormulario();
                actualizarTotalGeneral();
            });
            descuentoGlobalInput.addEventListener('change', () => {
                actualizarDescuentosDesdeFormulario();
                actualizarTotalGeneral();
            });
        }

        // Listener para aumento global
        const aumentoGlobalInput = document.getElementById('aumento');
        if (aumentoGlobalInput) {
            const handler = () => {
                renderTabla();
                actualizarTotalGeneral();
            };
            aumentoGlobalInput.addEventListener('input', handler);
            aumentoGlobalInput.addEventListener('change', handler);
        }

    }

    function inicializarEventosDivisa() {
        const select = elements.divisaSelect || document.getElementById('divisa_codigo');
        if (!select) {
            return;
        }

        const handler = () => {
            sincronizarDivisaDesdeFormulario();
            renderTabla();
            actualizarTotalGeneral();
        };

        select.addEventListener('change', handler);

        // Sincronizar una vez al inicio
        sincronizarDivisaDesdeFormulario();
    }

    function sincronizarDivisaDesdeFormulario() {
        const select = elements.divisaSelect || document.getElementById('divisa_codigo');
        let codigo = 'PES';
        let etiqueta = '';

        if (select) {
            codigo = (select.value || 'PES').toString().trim() || 'PES';
            const opt = select.selectedOptions && select.selectedOptions.length
                ? select.selectedOptions[0]
                : null;
            etiqueta = opt ? opt.textContent.trim() : codigo;
        }

        state.divisaCodigo = codigo;

        if (elements.divisaLabel) {
            elements.divisaLabel.textContent = etiqueta;
        }
    }

    function inicializarCondicionPagoYCuotas() {
        const selectCondPago = document.getElementById('coti_cond_pago');
        const cuotasPanel = document.getElementById('cuotasPanel');
        if (!selectCondPago) return;

        // Añadir opción "Cuotas" si no existe
        if (!Array.from(selectCondPago.options).some(o => o.value === 'CUOTAS')) {
            const opt = document.createElement('option');
            opt.value = 'CUOTAS';
            opt.textContent = 'Cuotas';
            selectCondPago.appendChild(opt);
        }

        // Si el panel viene visible desde el servidor (cotización con cuotas),
        // forzar que el select quede en "CUOTAS" para que el JS respete ese estado.
        const panelVisibleInicialmente = cuotasPanel && !cuotasPanel.classList.contains('d-none');
        if (panelVisibleInicialmente) {
            const optCuotas = Array.from(selectCondPago.options).find(o => o.value === 'CUOTAS');
            if (optCuotas) {
                selectCondPago.value = 'CUOTAS';
            }
        }

        function toggleCuotasPanelVisibility() {
            if (!cuotasPanel) return;
            const esCuotas = selectCondPago.value === 'CUOTAS';
            if (esCuotas) {
                cuotasPanel.classList.remove('d-none');
                // Al mostrar el panel, sincronizar inmediatamente el monto total y recalcular el individual
                actualizarTotalGeneral();
            } else {
                cuotasPanel.classList.add('d-none');
            }
        }

        selectCondPago.addEventListener('change', toggleCuotasPanelVisibility);
        toggleCuotasPanelVisibility();

        // Auto-calcular monto individual = (monto total × (1 + interés%)) / cantidad cuotas
        function recalcularMontoIndividual() {
            const montoTotal  = parseFloat(document.getElementById('coti_cuota_monto_total')?.value)  || 0;
            const cantidad    = parseInt(document.getElementById('coti_cuota_cant')?.value)            || 1;
            const interesPct  = parseFloat(document.getElementById('coti_cuota_interes')?.value)       || 0;
            const montoIndivInput = document.getElementById('coti_cuota_monto_indiv');
            if (montoIndivInput && cantidad > 0) {
                const montoConInteres = montoTotal * (1 + interesPct / 100);
                montoIndivInput.value = (montoConInteres / cantidad).toFixed(2);
            }
        }

        const cantInput        = document.getElementById('coti_cuota_cant');
        const montoTotalInput  = document.getElementById('coti_cuota_monto_total');
        const interesInput     = document.getElementById('coti_cuota_interes');
        const fechaInicioInput = document.getElementById('coti_cuota_fecha_inicio');
        const fechaFinInput    = document.getElementById('coti_cuota_fecha_fin');

        function mesesEntreFechas(inicioStr, finStr) {
            if (!inicioStr || !finStr) return null;
            const inicio = new Date(inicioStr + 'T12:00:00');
            const fin = new Date(finStr + 'T12:00:00');
            if (Number.isNaN(inicio.getTime()) || Number.isNaN(fin.getTime()) || fin < inicio) {
                return null;
            }
            return (fin.getFullYear() - inicio.getFullYear()) * 12 + (fin.getMonth() - inicio.getMonth()) + 1;
        }

        function recalcularCantidadDesdeFechas() {
            if (!cantInput || !fechaInicioInput || !fechaFinInput) return;
            const meses = mesesEntreFechas(fechaInicioInput.value, fechaFinInput.value);
            if (meses && meses >= 1) {
                cantInput.value = meses;
                recalcularMontoIndividual();
            }
        }

        if (fechaInicioInput) {
            fechaInicioInput.addEventListener('change', recalcularCantidadDesdeFechas);
        }
        if (fechaFinInput) {
            fechaFinInput.addEventListener('change', recalcularCantidadDesdeFechas);
        }

        if (cantInput) {
            cantInput.addEventListener('input',  recalcularMontoIndividual);
            cantInput.addEventListener('change', recalcularMontoIndividual);
        }
        if (montoTotalInput) {
            montoTotalInput.addEventListener('input',  recalcularMontoIndividual);
            montoTotalInput.addEventListener('change', recalcularMontoIndividual);
        }
        if (interesInput) {
            interesInput.addEventListener('input',  recalcularMontoIndividual);
            interesInput.addEventListener('change', recalcularMontoIndividual);
        }
    }

    function actualizarDescuentosDesdeFormulario() {
        // Leer descuento global del formulario
        const descuentoGlobalInput = document.getElementById('descuento');
        const descuentoGlobal = descuentoGlobalInput 
            ? parseFloat(descuentoGlobalInput.value) || 0 
            : 0;

        // Actualizar el hidden field y sus data attributes
        if (elements.descuentoHidden) {
            elements.descuentoHidden.value = descuentoGlobal.toFixed(2);
            elements.descuentoHidden.dataset.descuentoGlobal = descuentoGlobal.toFixed(2);
        }

        // Actualizar state.clienteSeleccionado si existe
        if (state.clienteSeleccionado) {
            state.clienteSeleccionado.descuento_global = descuentoGlobal;
        }
    }

    function cargarDatosClienteInicial() {
        const clienteInput = document.getElementById('cliente_codigo');
        const codigoActual = (clienteInput?.value || '').trim();

        if (!codigoActual) {
            return;
        }

        const descuentoHidden = elements.descuentoHidden;
        const descuentoGlobalDataset = descuentoHidden
            ? parseFloat(descuentoHidden.dataset.descuentoGlobal ?? descuentoHidden.value ?? '0')
            : 0;

        // Establecer valores básicos primero
        state.clienteSeleccionado = {
            codigo: codigoActual,
            descuento_global: isNaN(descuentoGlobalDataset) ? 0 : descuentoGlobalDataset,
            es_consultor: false, // Se actualizará cuando se cargue el cliente
        };

        actualizarDescuentoCliente();
        actualizarDescuentosDesdeFormulario();
        actualizarTotalGeneral();

        // Cargar datos completos del cliente para obtener es_consultor
        // Esto actualizará el state.clienteSeleccionado con es_consultor y manejará el campo "Para"
        fetch(`/api/clientes/${encodeURIComponent(codigoActual)}`)
            .then(response => response.json())
            .then(cliente => {
                if (cliente.error) {
                    return;
                }
                
                // Actualizar es_consultor en el state
                if (state.clienteSeleccionado) {
                    state.clienteSeleccionado.es_consultor = cliente.es_consultor === true || cliente.es_consultor === 1 || cliente.es_consultor === '1';
                }

                // Configurar contactos registrados del cliente también en carga inicial (edición)
                configurarContactosCliente(
                    cliente.contactos ||
                    cliente.contactos_cliente ||
                    []
                );

                // Configurar sucursales y preseleccionar coti_codigosuc si existe
                if (Array.isArray(cliente.sucursales) && cliente.sucursales.length > 0) {
                    configurarSucursalesCliente(cliente.sucursales);
                    const sucursalInput = document.getElementById('sucursal');
                    const sucursalSelect = document.getElementById('sucursal_select');
                    const codigoSucursalGuardado = (sucursalInput && sucursalInput.value) ? sucursalInput.value.trim() : '';
                    if (codigoSucursalGuardado && sucursalSelect) {
                        fijarValorSucursalSelect(sucursalSelect, codigoSucursalGuardado);
                    }
                } else {
                    resetearSucursalesCliente();
                }

                // Si hay un ID de empresa guardado (edición), cargar empresas y preseleccionar siempre.
                // Si no hay ID pero el cliente es consultor, cargar lista de empresas.
                const cotiEmpresaRel = document.getElementById('coti_empresa_rel');
                const empresaIdGuardado = cotiEmpresaRel && (cotiEmpresaRel.value || '').trim();
                if (empresaIdGuardado) {
                    cargarEmpresasRelacionadas(codigoActual, empresaIdGuardado);
                } else if (state.clienteSeleccionado && state.clienteSeleccionado.es_consultor) {
                    cargarEmpresasRelacionadas(codigoActual, null);
                } else {
                    resetearCampoPara();
                }

                actualizarVisibilidadNavEmpresaRelacionada();
                if (!state.clienteSeleccionado || !state.clienteSeleccionado.es_consultor) {
                    limpiarPanelEmpresaRelacionada();
                }
            })
            .catch(error => {
                // console.error('Error cargando datos del cliente:', error);
            });
    }

    function mostrarResultadosClientes(clientes = null) {
        if (!elements.clienteResultados) {
            return;
        }

        const contenedor = elements.clienteResultados;
        contenedor.innerHTML = '';

        if (!Array.isArray(clientes)) {
            contenedor.classList.remove('show');
            contenedor.style.display = 'none';
            return;
        }

        if (!clientes.length) {
            const sinResultados = document.createElement('div');
            sinResultados.className = 'dropdown-item text-muted';
            sinResultados.textContent = 'No se encontraron clientes.';
            contenedor.appendChild(sinResultados);
        } else {
            clientes.forEach(cliente => {
                const opcion = document.createElement('button');
                opcion.type = 'button';
                opcion.className = 'dropdown-item text-start';
                opcion.dataset.codigo = cliente.codigo;
                opcion.innerHTML = `
                    <div class="fw-semibold">${escapeHtml((cliente.codigo || '').trim())}</div>
                    <div class="small text-muted">${escapeHtml(cliente.text || '')}</div>
                `;
                opcion.addEventListener('click', () => {
                    seleccionarCliente(cliente.codigo);
                });
                contenedor.appendChild(opcion);
            });
        }

        contenedor.classList.add('show');
        contenedor.style.display = 'block';
    }

    function ocultarResultadosClientes(mantenerAyuda = false) {
        if (!elements.clienteResultados) {
            return;
        }
        elements.clienteResultados.classList.remove('show');
        elements.clienteResultados.style.display = 'none';
        if (!mantenerAyuda) {
            mostrarAyudaBusqueda(false);
        }
    }

    function mostrarAyudaBusqueda(visible) {
        if (!elements.clienteAyuda) {
            return;
        }
        elements.clienteAyuda.style.display = visible ? '' : 'none';
    }

    function asignarValorSiExiste(id, valor) {
        const elemento = document.getElementById(id);
        if (!elemento) {
            return;
        }

        const normalizado = valor ?? '';

        if (elemento.tagName === 'INPUT' || elemento.tagName === 'TEXTAREA') {
            elemento.value = normalizado;
            return;
        }

        if (elemento.tagName === 'SELECT') {
            const opciones = Array.from(elemento.options || []);
            const coincide = opciones.find(opcion => opcion.value.trim() === normalizado.toString().trim());
            if (coincide) {
                elemento.value = coincide.value;
            } else {
                elemento.value = '';
            }
            return;
        }

        elemento.textContent = normalizado;
    }

    async function cargarCatalogos() {
        try {
            const [ensayosRes, componentesRes, metodosRes, leyesRes] = await Promise.all([
                fetch('/api/ensayos'),
                fetch('/api/componentes?incluir_agrupadores=1'), // Incluir agrupadores
                fetch('/api/metodos-analisis'),
                fetch('/api/leyes-normativas'),
            ]);

            catalogs.ensayos = await ensayosRes.json();
            catalogs.componentes = await componentesRes.json();

            catalogs.metodosAnalisis = await metodosRes.json();
            catalogs.leyes = await leyesRes.json();

            catalogs.ensayosDefaultsById = {};
            catalogs.ensayosDefaultsByCodigo = {};

            catalogs.ensayos.forEach(ensayo => {
                const defaults = Array.isArray(ensayo.componentes_default)
                    ? ensayo.componentes_default.map(id => id.toString())
                    : [];

                if (ensayo.id !== undefined && ensayo.id !== null) {
                    catalogs.ensayosDefaultsById[ensayo.id.toString()] = defaults;
                }

                if (ensayo.codigo) {
                    catalogs.ensayosDefaultsByCodigo[String(ensayo.codigo).trim()] = defaults;
                }
            });

            window.ensayosDisponibles = catalogs.ensayos;
            window.componentesDisponibles = catalogs.componentes;
        } catch (error) {
            // console.error('Error cargando catálogos:', error);
        }
    }

    function inicializarEventosModales() {
        if (elements.modalEnsayo) {
            elements.modalEnsayo.addEventListener('hidden.bs.modal', function () {
                destruirSelectLeyNormativa(elements.selectEnsayoLeyNormativa);
            });

            elements.modalEnsayo.addEventListener('shown.bs.modal', function () {
                cargarOpcionesEnsayos();
                cargarLeyesNormativas();
                const precioExtraNuevo = document.getElementById('ensayo_precio_extra');
                if (precioExtraNuevo) {
                    precioExtraNuevo.value = '0';
                }
                const chkNoMuestreoEnsayo = document.getElementById('ensayo_no_lleva_muestreo');
                if (chkNoMuestreoEnsayo) {
                    chkNoMuestreoEnsayo.checked = false;
                    chkNoMuestreoEnsayo.disabled = false;
                }
                if (window.$ && window.$('#ensayo_muestra').length) {
                    window.$('#ensayo_muestra').select2({
                        dropdownParent: window.$('#modalAgregarEnsayo'),
                    });
                    window.$('#ensayo_muestra').off('change.cotizacionEnsayoNotas').on('change.cotizacionEnsayoNotas', function () {
                        if (elements.selectEnsayo) {
                            handleCambioEnsayoModal({ target: elements.selectEnsayo });
                        }
                    });
                }
                if (elements.selectEnsayo) {
                    handleCambioEnsayoModal({ target: elements.selectEnsayo });
                }
                syncCotiReqCadenaRelCheckboxesFromHidden();
            });
        }

        const modalEditarEnsayo = document.getElementById('modalEditarEnsayo');
        if (modalEditarEnsayo) {
            modalEditarEnsayo.addEventListener('shown.bs.modal', function () {
                if (state.soloReferenciasFacturacion) {
                    aplicarModoEdicionLimitadaModalEnsayo(modalEditarEnsayo);
                }
            });
            modalEditarEnsayo.addEventListener('hidden.bs.modal', function () {
                destruirSelectLeyNormativa(document.getElementById('edit_ensayo_ley_normativa'));
                if (window.$ && window.$('#edit_ensayo_muestra').length && window.$('#edit_ensayo_muestra').data('select2')) {
                    window.$('#edit_ensayo_muestra').select2('destroy');
                }
            });
        }

        const modalEditarComponente = document.getElementById('modalEditarComponente');
        if (modalEditarComponente) {
            modalEditarComponente.addEventListener('shown.bs.modal', function () {
                if (state.soloReferenciasFacturacion) {
                    aplicarModoEdicionLimitadaModalComponente(modalEditarComponente);
                }
            });
            modalEditarComponente.addEventListener('hidden.bs.modal', function () {
                destruirSelectLeyNormativa(document.getElementById('edit_componente_ley_normativa'));
            });
        }

        if (elements.modalComponente) {
            // Limpiar Select2 cuando se cierra el modal
            elements.modalComponente.addEventListener('hidden.bs.modal', function () {
                if (window.$ && window.$('#componente_analisis').length) {
                    const $select = window.$('#componente_analisis');
                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }
                    // Limpiar selección
                    $select.val(null);
                }
                const buscar = document.getElementById('componentes_seleccionados_buscar');
                if (buscar) {
                    buscar.value = '';
                }
            });

            elements.modalComponente.addEventListener('shown.bs.modal', async function () {
                // Determinar si hay un ensayo seleccionado para filtrar desde el inicio
                const ensayoItemId = elements.selectEnsayoAsociado ? elements.selectEnsayoAsociado.value : null;
                let matrizCodigoInicial = null;

                if (ensayoItemId) {
                    const ensayo = state.ensayos.find(e => e.item === Number(ensayoItemId));
                    if (ensayo && ensayo.matriz_codigo) {
                        matrizCodigoInicial = ensayo.matriz_codigo.toString().trim();
                    }
                }

                // Cargar componentes con filtro si hay un ensayo seleccionado
                await cargarOpcionesComponentes(matrizCodigoInicial);
                actualizarEnsayosDisponiblesParaComponentes();

                // Inicializar Select2 de forma estándar
                if (window.$ && window.$('#componente_analisis').length) {
                    const $selectComponentes = window.$('#componente_analisis');
                    
                    // Inicializar Select2 de forma estándar
                    $selectComponentes.select2({
                        dropdownParent: window.$('#modalAgregarComponente'),
                        width: '100%',
                        placeholder: 'Buscar y agregar análisis...',
                        closeOnSelect: false,
                        templateResult: renderComponenteOptionTemplate,
                        templateSelection: renderComponenteSelectionTemplate,
                        escapeMarkup: function (markup) {
                            return markup;
                        }
                    });
                    
                    // Evento change simple
                    $selectComponentes.on('change.cotizacionComponentes', function() {
                        handleCambioComponenteModal();
                    });
                    
                    // Preseleccionar componentes si hay un ensayo seleccionado
                    if (ensayoItemId) {
                        preseleccionarComponentesDeEnsayo(ensayoItemId, false);
                    }
                }

                handleCambioComponenteModal();
            });
        }

        if (elements.selectEnsayo) {
            elements.selectEnsayo.addEventListener('change', handleCambioEnsayoModal);
        }

        if (elements.selectComponente) {
            elements.selectComponente.addEventListener('change', handleCambioComponenteModal);
        }

        inicializarPanelSeleccionComponentes();
    }

    function inicializarBotonesAccion() {
        const confirmarEnsayo = document.getElementById('btnConfirmarEnsayo');
        if (confirmarEnsayo) {
            confirmarEnsayo.addEventListener('click', agregarEnsayo);
        }

        const confirmarComponente = document.getElementById('btnConfirmarComponente');
        if (confirmarComponente) {
            confirmarComponente.addEventListener('click', agregarComponente);
        }

        const guardarComponenteEditado = document.getElementById('btnGuardarComponenteEditado');
        if (guardarComponenteEditado) {
            guardarComponenteEditado.addEventListener('click', guardarComponenteEditadoHandler);
        }

        const guardarEnsayoEditado = document.getElementById('btnGuardarEnsayoEditado');
        if (guardarEnsayoEditado) {
            guardarEnsayoEditado.addEventListener('click', guardarEnsayoEditadoHandler);
        }

        // Limpiar error de cadena de custodia en modal "Agregar" al tildar cualquier opción
        document.querySelectorAll('.custodia-chk-agregar').forEach(function(chk) {
            chk.addEventListener('change', function() {
                const group = document.getElementById('custodia_group_agregar');
                const error = document.getElementById('custodia_error_agregar');
                const alguno = Array.from(document.querySelectorAll('.custodia-chk-agregar')).some(c => c.checked);
                if (alguno) {
                    if (group) group.classList.remove('border-danger');
                    if (error) error.classList.add('d-none');
                }
            });
        });

        // Limpiar error de cadena de custodia en modal "Editar" al tildar cualquier opción
        document.querySelectorAll('.custodia-chk-editar').forEach(function(chk) {
            chk.addEventListener('change', function() {
                const group = document.getElementById('custodia_group_editar');
                const error = document.getElementById('custodia_error_editar');
                const alguno = Array.from(document.querySelectorAll('.custodia-chk-editar')).some(c => c.checked);
                if (alguno) {
                    if (group) group.classList.remove('border-danger');
                    if (error) error.classList.add('d-none');
                }
            });
        });

        // Event listeners para botones de agregar nota
        const btnAgregarNotaEnsayo = document.getElementById('btnAgregarNotaEnsayo');
        if (btnAgregarNotaEnsayo) {
            btnAgregarNotaEnsayo.addEventListener('click', function() {
                agregarNotaAlContenedor('notasEnsayoContainer');
            });
        }

        const btnAgregarNotaEditEnsayo = document.getElementById('btnAgregarNotaEditEnsayo');
        if (btnAgregarNotaEditEnsayo) {
            btnAgregarNotaEditEnsayo.addEventListener('click', function() {
                agregarNotaAlContenedor('notasEditEnsayoContainer');
            });
        }

        // Event listener para cambio de análisis en modal de edición
        const editComponenteAnalisis = document.getElementById('edit_componente_analisis');
        if (editComponenteAnalisis) {
            editComponenteAnalisis.addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                if (option && option.dataset) {
                    document.getElementById('edit_componente_precio').value = parseFloat(option.dataset.precio || 0).toFixed(2);
                    document.getElementById('edit_componente_unidad').value = option.dataset.unidadMedida || '';
                    if (option.dataset.metodoAnalisisId) {
                        const selectMetodo = document.getElementById('edit_componente_metodo');
                        if (selectMetodo) {
                            selectMetodo.value = option.dataset.metodoAnalisisId;
                        }
                    }
                }
                const compCat = catalogs.componentes.find(c => String(c.id) === String(this.value));
                const impEd = document.getElementById('edit_comp_nota_imprimible');
                const intEd = document.getElementById('edit_comp_nota_interna');
                if (compCat && impEd && intEd) {
                    impEd.value = compCat.nota_imprimible != null ? String(compCat.nota_imprimible) : '';
                    intEd.value = compCat.nota_interna != null ? String(compCat.nota_interna) : '';
                }
            });
        }
    }

    function guardarComponenteEditadoHandler() {
        if (!state.puedeEditar && !state.soloReferenciasFacturacion) {
            return;
        }

        const itemId = Number(document.getElementById('edit_componente_item_id').value);
        if (!itemId) {
            return;
        }

        const componente = state.componentes.find(c => c.item === itemId);
        if (!componente) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se encontró el componente a editar.',
                });
            }
            return;
        }

        if (state.soloReferenciasFacturacion) {
            const selectLeyComp = document.getElementById('edit_componente_ley_normativa');
            if (selectLeyComp) {
                const lc = selectLeyComp.value ? String(selectLeyComp.value).trim() : '';
                componente.ley_normativa_id = lc || null;
            }
            renderTabla();

            const modalLimitado = document.getElementById('modalEditarComponente');
            if (modalLimitado && window.bootstrap && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(modalLimitado);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            return;
        }

        // Obtener valores del formulario
        const selectAnalisis = document.getElementById('edit_componente_analisis');
        const analisisId = selectAnalisis ? selectAnalisis.value : null;
        const option = selectAnalisis && selectAnalisis.selectedIndex >= 0 
            ? selectAnalisis.options[selectAnalisis.selectedIndex] 
            : null;

        if (!analisisId || !option) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Debe seleccionar un análisis.',
                });
            }
            return;
        }

        const precio = toPositiveNumber(document.getElementById('edit_componente_precio').value, 0);
        const unidadMedida = document.getElementById('edit_componente_unidad').value || '';
        const metodoId = document.getElementById('edit_componente_metodo').value || null;

        const chkReqCadena = document.getElementById('edit_comp_req_cadena_custodia');
        const chkReqProt = document.getElementById('edit_comp_req_prot_mapba');

        // Actualizar componente
        componente.analisis_id = analisisId;
        componente.descripcion = option.dataset.descripcion || option.textContent.replace(/\s*\(ID: \d+\)$/, '') || componente.descripcion;
        componente.codigo = option.dataset.codigo || componente.codigo;
        componente.precio = precio;
        const ensayoPadre = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
        const wrapCantidad = document.getElementById('edit_componente_cantidad_wrap');
        const inputCantidad = document.getElementById('edit_componente_cantidad');
        if (cantidadComponenteEditable(ensayoPadre) && inputCantidad) {
            componente.cantidad = toPositiveInt(inputCantidad.value, 1);
        }
        componente.total = precio * (parseFloat(componente.cantidad) || 1);
        componente.unidad_medida = unidadMedida || option.dataset.unidadMedida || componente.unidad_medida;
        componente.metodo_analisis_id = metodoId;
        componente.metodo_codigo = option.dataset.metodoCodigo || componente.metodo_codigo;

        // Verificar si el nuevo análisis es un componente sugerido del ensayo asociado
        const ensayoAsociado = state.ensayos.find(e => e.item === componente.ensayo_asociado);
        componente.de_agrupador = resolverDeAgrupadorComponente(ensayoAsociado, analisisId, componente.de_agrupador);

        // Actualizar requerimientos
        if (chkReqCadena) {
            componente.req_cadena_custodia = !!chkReqCadena.checked;
        }
        if (chkReqProt) {
            componente.req_prot_mapba = !!chkReqProt.checked;
        }

        const impNota = document.getElementById('edit_comp_nota_imprimible');
        const intNota = document.getElementById('edit_comp_nota_interna');
        const impT = impNota ? String(impNota.value || '').trim() : '';
        const intT = intNota ? String(intNota.value || '').trim() : '';
        const notasArr = notasPredeterminadasDesdeCatalogo(impT || null, intT || null);
        const npComp = notasAObjetoPersistencia(notasArr);
        componente.nota_tipo = npComp.nota_tipo;
        componente.nota_contenido = npComp.nota_contenido;
        componente.notas = notasArr.length ? notasArr : null;

        const selectLeyComp = document.getElementById('edit_componente_ley_normativa');
        if (selectLeyComp) {
            const lc = selectLeyComp.value ? String(selectLeyComp.value).trim() : '';
            componente.ley_normativa_id = lc || null;
        }
        
        // Actualizar método descripción
        if (metodoId) {
            const metodo = catalogs.metodosAnalisis.find(m => m.codigo == metodoId);
            if (metodo) {
                componente.metodo_descripcion = metodo.text || '';
            }
        }

        // Recalcular precios del ensayo asociado
        recalcularPreciosEnsayo(componente.ensayo_asociado);
        
        // Re-renderizar tabla
        renderTabla();

        // Cerrar modal
        const modal = document.getElementById('modalEditarComponente');
        if (modal && window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = window.bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Componente actualizado',
                text: 'Los cambios se han guardado correctamente.',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
        }
    }

    function inicializarBloqueAprobacion() {
        if (!elements.datosAprobacionWrapper || !elements.datosAprobacionCard) {
            return;
        }

        const contenedor = elements.datosAprobacionWrapper;
        const card = elements.datosAprobacionCard;

        const mostrarCard = () => {
            // console.log('[cotizacion] mostrarCard', {
            //     cardExiste: !!card,
            //     isConnected: card ? card.isConnected : null,
            //     contenedorTieneHijos: contenedor ? contenedor.children.length : null
            // });
            if (!card.isConnected) {
                contenedor.appendChild(card);
                // console.log('[cotizacion] card reinsertado');
            }
            card.style.display = '';
            contenedor.dataset.visible = '1';
            // console.log('[cotizacion] card visible', { display: card.style.display, hijos: contenedor.children.length });
        };

        const ocultarCard = () => {
            // console.log('[cotizacion] ocultarCard', {
            //     cardExiste: !!card,
            //     isConnected: card ? card.isConnected : null
            // });
            if (card.isConnected) {
                card.remove();
                // console.log('[cotizacion] card removido');
            }
            contenedor.dataset.visible = '0';
        };

        if (state.soloReferenciasFacturacion) {
            mostrarCard();
            return;
        }

        const actualizarVisibilidad = (fuente = 'init') => {
            const selectActivo = obtenerSelectEstadoActivo();
            const visible = selectActivo ? estadoEsAprobado(selectActivo.value) : false;
            // console.log('[cotizacion] actualizarVisibilidad', {
            //     fuente,
            //     selectEncontrado: !!selectActivo,
            //     valor: selectActivo ? selectActivo.value : null,
            //     visible
            // });

            if (visible) {
                mostrarCard();
            } else {
                ocultarCard();
            }
        };

        const visibleInicial = contenedor.dataset.visible === '1';
        if (!visibleInicial) {
            ocultarCard();
        } else {
            mostrarCard();
        }

        elements.estadoSelects.forEach(select => {
            const handler = () => {
                // console.log('[cotizacion] estadoSelect handler', {
                //     evento: 'change/input',
                //     valor: select.value
                // });
                setTimeout(() => actualizarVisibilidad('select-event'), 0);
            };

            select.addEventListener('change', handler);
            select.addEventListener('input', handler);
        });

        document.addEventListener('coti:estado-actualizado', () => {
            // console.log('[cotizacion] evento coti:estado-actualizado');
            setTimeout(() => actualizarVisibilidad('custom-event'), 0);
        });

        const observer = new MutationObserver(() => {
            // console.log('[cotizacion] mutation observer disparado');
            setTimeout(() => actualizarVisibilidad('mutation'), 0);
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        setTimeout(() => actualizarVisibilidad('init-check'), 0);
    }

    function inicializarEventosTabla() {
        if (!elements.tablaItems) {
            return;
        }

        elements.tablaItems.addEventListener('input', function (event) {
            if (!state.puedeEditar) {
                return;
            }

            if (event.target.classList.contains('input-cantidad-ensayo')) {
                const itemId = Number(event.target.dataset.item);
                const valor = toPositiveInt(event.target.value, 1);
                event.target.value = valor;
                actualizarCantidadEnsayo(itemId, valor);
            } else if (event.target.classList.contains('input-cantidad-componente')) {
                const itemId = Number(event.target.dataset.item);
                const valor = toPositiveInt(event.target.value, 1);
                event.target.value = valor;
                actualizarCantidadComponente(itemId, valor);
            } else if (event.target.classList.contains('input-precio-componente')) {
                const itemId = Number(event.target.dataset.item);
                const componente = state.componentes.find(c => c.item === itemId);
                const minBase = componente ? (parseFloat(componente.precio_minimo_venta) || 0) : 0;

                let valorIngresado = toPositiveNumber(event.target.value, 0);
                const factorAumento = 1 + (clampPercent(getAumentoGlobal()) / 100);
                let precioBase = factorAumento > 0 ? valorIngresado / factorAumento : valorIngresado;

                if (!state.puedeBajarPrecio && minBase > 0 && precioBase < minBase) {
                    precioBase = minBase;
                    valorIngresado = precioBase * factorAumento;
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Precio mínimo',
                            text: 'No tenés autorización para bajar el precio por debajo del mínimo de venta.',
                            timer: 2000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                        });
                    }
                }

                event.target.value = formatNumber(valorIngresado);
                actualizarPrecioComponente(itemId, precioBase);
            }
        });
    }

    function estadoEsAprobado(valor) {
        if (!valor) {
            return false;
        }

        const normalizado = valor.toString().trim().toUpperCase();
        return normalizado === 'A' || normalizado === 'APROBADO';
    }

    function obtenerSelectEstadoActivo() {
        if (!elements.estadoSelects || elements.estadoSelects.length === 0) {
            // console.log('[cotizacion] obtenerSelectEstadoActivo -> sin selects encontrados');
            return null;
        }

        if (elements.estadoSelects.length === 1) {
            const unico = elements.estadoSelects[0];
            // console.log('[cotizacion] obtenerSelectEstadoActivo -> único select', { valor: unico.value });
            return unico;
        }

        const visibleSelect = elements.estadoSelects.find(select => {
            return select.offsetParent !== null || window.getComputedStyle(select).display !== 'none';
        });

        const seleccionado = visibleSelect || elements.estadoSelects[0];
        // console.log('[cotizacion] obtenerSelectEstadoActivo -> seleccionado', {
        //     tieneVisible: !!visibleSelect,
        //     valor: seleccionado ? seleccionado.value : null
        // });
        return seleccionado;
    }

    function actualizarCantidadEnsayo(itemId, cantidad) {
        const ensayo = state.ensayos.find(e => e.item === itemId);
        if (!ensayo) {
            return;
        }

        ensayo.cantidad = toPositiveInt(cantidad, 1);
        recalcularPreciosEnsayo(itemId);
        actualizarTotalesEnsayoEnDOM(itemId);
        actualizarTotalGeneral();
    }

    function actualizarCantidadComponente(itemId, cantidad) {
        const componente = state.componentes.find(c => c.item === itemId);
        if (!componente) {
            return;
        }

        const ensayo = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
        if (!cantidadComponenteEditable(ensayo)) {
            return;
        }

        componente.cantidad = toPositiveInt(cantidad, 1);
        componente.total = (parseFloat(componente.precio) || 0) * componente.cantidad;
        actualizarTotalComponenteEnDOM(itemId);
        recalcularPreciosEnsayo(componente.ensayo_asociado);
        actualizarTotalesEnsayoEnDOM(componente.ensayo_asociado);
        actualizarTotalGeneral();
    }

    function actualizarPrecioComponente(itemId, precio) {
        const componente = state.componentes.find(c => c.item === itemId);
        if (!componente) {
            return;
        }

        componente.precio = precio;
        componente.total = precio * (parseFloat(componente.cantidad) || 1);
        actualizarTotalComponenteEnDOM(itemId);
        recalcularPreciosEnsayo(componente.ensayo_asociado);
        actualizarTotalesEnsayoEnDOM(componente.ensayo_asociado);
        actualizarTotalGeneral();
    }

    function actualizarTotalesEnsayoEnDOM(ensayoItem) {
        const ensayo = state.ensayos.find(e => e.item === ensayoItem);
        if (!ensayo) {
            return;
        }

        const unitario = document.querySelector(`[data-ensayo-unitario="${ensayoItem}"]`);
        const total = document.querySelector(`[data-ensayo-total="${ensayoItem}"]`);

        const factorAumento = 1 + (clampPercent(getAumentoGlobal()) / 100);
        if (unitario) {
            const valorUnitMostrado = precioAdicionalEnsayoPorUm(ensayo) * factorAumento;
            const spanVal = unitario.querySelector('.cotizacion-precio-ensayo-valor');
            if (spanVal) {
                spanVal.textContent = formatCurrency(valorUnitMostrado);
            } else {
                unitario.textContent = formatCurrency(valorUnitMostrado);
            }
        }

        if (total) {
            total.textContent = formatCurrency((parseFloat(ensayo.total) || 0) * factorAumento);
        }
    }

    function actualizarTotalComponenteEnDOM(itemId) {
        const celda = document.querySelector(`[data-componente-total="${itemId}"]`);
        const componente = state.componentes.find(c => c.item === itemId);
        if (celda && componente) {
            const ensayo = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
            const esParametroPack = componenteEsParametroPackAgrupador(ensayo, componente);
            const factorAumento = 1 + (clampPercent(getAumentoGlobal()) / 100);
            const totalBase = esParametroPack ? 0 : (parseFloat(componente.total) || 0);
            const totalConAumento = totalBase * factorAumento;
            
            const spanVal = celda.querySelector('.cotizacion-total-componente-valor');
            if (spanVal) {
                spanVal.textContent = formatCurrency(totalConAumento);
            } else {
                celda.textContent = formatCurrency(totalConAumento);
            }
        }
    }

    function cargarOpcionesEnsayos() {
        if (!elements.selectEnsayo) {
            return;
        }

        elements.selectEnsayo.innerHTML = '<option value="">Seleccionar muestra...</option>';

        catalogs.ensayos.forEach(ensayo => {
            const option = document.createElement('option');
            option.value = ensayo.id;
            option.textContent = (ensayo.descripcion || '') + ` (ID: ${ensayo.id})`;
            option.dataset.descripcion = ensayo.descripcion || '';
            option.dataset.codigo = ensayo.codigo;
            option.dataset.metodoCodigo = (ensayo.metodo_codigo || ensayo.metodo || '').toString().trim();
            option.dataset.metodoDescripcion = ensayo.metodo_descripcion || '';
            option.dataset.componentes = JSON.stringify(Array.isArray(ensayo.componentes_default) ? ensayo.componentes_default : []);
            // Guardar matriz_codigo y matriz_descripcion en el option
            option.dataset.matrizCodigo = (ensayo.matriz_codigo || '').toString().trim();
            option.dataset.matrizDescripcion = ensayo.matriz_descripcion || '';
            const precioNum = parseFloat(ensayo.precio);
            const precioOk = Number.isFinite(precioNum) && precioNum >= 0;
            option.dataset.precio = precioOk ? precioNum.toFixed(2) : '';
            option.dataset.precioRaw = precioOk ? String(precioNum) : '0';
            elements.selectEnsayo.appendChild(option);
        });
    }

    async function cargarOpcionesComponentes(matrizCodigoFiltro = null) {
        if (!elements.selectComponente) {
            return;
        }

        elements.selectComponente.innerHTML = '';

        let componentesParaMostrar = [];

        // Si hay un filtro de matriz, cargar desde el API con el filtro
        if (matrizCodigoFiltro) {
            try {
                // Limpiar espacios en blanco del código de matriz
                const matrizCodigoLimpio = matrizCodigoFiltro.toString().trim();
                if (!matrizCodigoLimpio) {
                    // Si después de trim está vacío, usar catálogo completo
                    componentesParaMostrar = catalogs.componentes;
                } else {
                    const response = await fetch(`/api/componentes?matriz_codigo=${encodeURIComponent(matrizCodigoLimpio)}&incluir_agrupadores=1`);
                    if (response.ok) {
                        componentesParaMostrar = await response.json();
                        // console.log(`Componentes filtrados por matriz ${matrizCodigoLimpio}:`, componentesParaMostrar.length);
                    } else {
                        // console.error('Error cargando componentes filtrados:', response.statusText);
                        // Fallback: usar catálogo completo si falla el filtro
                        componentesParaMostrar = catalogs.componentes;
                    }
                }
            } catch (error) {
                // console.error('Error cargando componentes filtrados:', error);
                // Fallback: usar catálogo completo si falla el filtro
                componentesParaMostrar = catalogs.componentes;
            }
        } else {
            // Sin filtro: usar catálogo completo
            componentesParaMostrar = catalogs.componentes;
        }

        // Guardar valores seleccionados actuales para restaurarlos después
        const valoresSeleccionados = window.$ && window.$('#componente_analisis').length 
            ? (window.$('#componente_analisis').val() || []) 
            : Array.from(elements.selectComponente.options)
                .filter(opt => opt.selected)
                .map(opt => opt.value.toString());

        componentesParaMostrar.forEach(componente => {
            const option = document.createElement('option');
            const precio = Number(componente.precio || 0);
            const metodoAnalisisId = (componente.metodo_analisis_id || '').toString().trim();
            const metodoCodigo = (componente.metodo_codigo || '').toString().trim(); // muestreo
            option.value = componente.id;
            // Si es agrupador, agregar indicador visual
            const esAgrupador = componente.es_muestra === true || componente.es_muestra === 1;
            option.textContent = (esAgrupador ? `[AGRUPADOR] ` : '') + (componente.descripcion || '') + ` (ID: ${componente.id})`;
            option.dataset.descripcion = componente.descripcion || '';
            option.dataset.codigo = componente.codigo || '';
            option.dataset.unidadMedida = componente.unidad_medida || '';
            option.dataset.precio = precio.toFixed(2);
            option.dataset.precioRaw = precio;
            option.dataset.precioDefinido = (componente.precio_definido === true || componente.precio_definido === 1 || componente.precio_definido === '1') ? '1' : '0';
            option.dataset.metodoAnalisisId = metodoAnalisisId;
            option.dataset.metodoCodigo = metodoCodigo;
            option.dataset.metodoDescripcion = componente.metodo_descripcion || '';
            option.dataset.limitesEstablecidos = componente.limites_establecidos || '';
            option.dataset.matrizCodigo = (componente.matriz_codigo || '').toString().trim();
            option.dataset.matrizDescripcion = componente.matriz_descripcion || '';
            option.dataset.leyId = componente.ley_normativa_id || '';
            option.dataset.esAgrupador = esAgrupador ? '1' : '0';
            // Guardar IDs de componentes asociados si es agrupador
            if (esAgrupador && componente.componentes_asociados && Array.isArray(componente.componentes_asociados)) {
                option.dataset.componentesAsociados = JSON.stringify(componente.componentes_asociados);
            }
            
            // Restaurar selección si estaba seleccionado antes
            if (valoresSeleccionados.includes(componente.id.toString())) {
                option.selected = true;
            }
            
            elements.selectComponente.appendChild(option);
        });

        // Agrupadores: si hay un ensayo seleccionado con componentes_sugeridos, asegurar que esas opciones existan
        // (el filtro por matriz puede no devolverlos; los añadimos desde el catálogo completo)
        const ensayoItemId = elements.selectEnsayoAsociado ? elements.selectEnsayoAsociado.value : null;
        if (ensayoItemId && catalogs.componentes && catalogs.componentes.length) {
            const ensayo = state.ensayos.find(e => e.item === Number(ensayoItemId));
            const sugeridos = ensayo ? obtenerComponentesSugeridosDeEnsayo(ensayo) : [];
            const opcionesYaValores = new Set(Array.from(elements.selectComponente.options).map(opt => opt.value.toString()));
            const idsFaltantes = sugeridos.filter(id => !opcionesYaValores.has(id.toString()));
            if (idsFaltantes.length > 0) {
                idsFaltantes.forEach(idStr => {
                    const comp = catalogs.componentes.find(c => String(c.id) === String(idStr));
                    if (!comp) return;
                    const option = document.createElement('option');
                    const precio = Number(comp.precio || 0);
                    const metodoAnalisisIdFalt = (comp.metodo_analisis_id || '').toString().trim();
                    const metodoCodigoFalt = (comp.metodo_codigo || '').toString().trim(); // muestreo
                    option.value = comp.id;
                    const esAgrupador = comp.es_muestra === true || comp.es_muestra === 1;
                    option.textContent = (esAgrupador ? `[AGRUPADOR] ` : '') + (comp.descripcion || '') + ` (ID: ${comp.id})`;
                    option.dataset.descripcion = comp.descripcion || '';
                    option.dataset.codigo = comp.codigo || '';
                    option.dataset.unidadMedida = comp.unidad_medida || '';
                    option.dataset.precio = precio.toFixed(2);
                    option.dataset.precioRaw = precio;
                    option.dataset.precioDefinido = (comp.precio_definido === true || comp.precio_definido === 1 || comp.precio_definido === '1') ? '1' : '0';
                    option.dataset.metodoAnalisisId = metodoAnalisisIdFalt;
                    option.dataset.metodoCodigo = metodoCodigoFalt;
                    option.dataset.metodoDescripcion = comp.metodo_descripcion || '';
                    option.dataset.limitesEstablecidos = comp.limites_establecidos || '';
                    option.dataset.matrizCodigo = (comp.matriz_codigo || '').toString().trim();
                    option.dataset.matrizDescripcion = comp.matriz_descripcion || '';
                    option.dataset.leyId = comp.ley_normativa_id || '';
                    option.dataset.esAgrupador = esAgrupador ? '1' : '0';
                    if (esAgrupador && comp.componentes_asociados && Array.isArray(comp.componentes_asociados)) {
                        option.dataset.componentesAsociados = JSON.stringify(comp.componentes_asociados);
                    }
                    if (valoresSeleccionados.includes(comp.id.toString())) option.selected = true;
                    elements.selectComponente.appendChild(option);
                });
            }
        }

        // Si estamos usando Select2, actualizar valores seleccionados
        if (window.$ && window.$('#componente_analisis').length && window.$('#componente_analisis').data('select2')) {
            const $select = window.$('#componente_analisis');
            if (valoresSeleccionados.length > 0) {
                $select.val(valoresSeleccionados).trigger('change');
            } else {
                $select.val(null).trigger('change');
            }
        }

        handleCambioComponenteModal();
    }

    function obtenerOpcionesSeleccionadasComponente() {
        if (!elements.selectComponente) {
            return [];
        }
        return Array.from(elements.selectComponente.options || []).filter(option => option.selected);
    }

    function textoOpcionLeyNormativa(ley) {
        const cod = ley.codigo != null && ley.codigo !== '' ? String(ley.codigo).trim() : '';
        if (!cod) {
            return '';
        }
        if (ley.text) {
            return ley.text;
        }
        const nombre = ley.nombre_completo || ley.nombre || '';
        return nombre ? `${cod} - ${nombre}` : cod;
    }

    function poblarSelectLeyesNormativas(selectEl, leyesCatalogo) {
        if (!selectEl) {
            return;
        }

        selectEl.innerHTML = '<option value="">Seleccionar normativa...</option>';

        if (!leyesCatalogo || !Array.isArray(leyesCatalogo)) {
            return;
        }

        leyesCatalogo.forEach(ley => {
            const cod = ley.codigo != null && ley.codigo !== '' ? String(ley.codigo).trim() : '';
            if (!cod) {
                return;
            }
            const option = document.createElement('option');
            option.value = cod;
            option.textContent = textoOpcionLeyNormativa(ley);
            option.dataset.codigo = cod;
            option.dataset.grupo = ley.grupo || '';
            selectEl.appendChild(option);
        });
    }

    function destruirSelectLeyNormativa(selectEl) {
        if (!selectEl || !window.$ || !window.$.fn.select2) {
            return;
        }
        const $select = window.$(selectEl);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    function inicializarSelectLeyNormativa(selectEl, modalId) {
        if (!selectEl || !window.$ || !window.$.fn.select2) {
            return;
        }

        destruirSelectLeyNormativa(selectEl);

        const $select = window.$(selectEl);
        const $parent = modalId ? window.$(`#${modalId}`) : $select.closest('.modal');

        $select.select2({
            width: '100%',
            placeholder: 'Buscar ley o normativa...',
            allowClear: true,
            dropdownParent: $parent.length ? $parent : undefined,
            language: {
                noResults: function () {
                    return 'No se encontraron normativas';
                },
                searching: function () {
                    return 'Buscando...';
                },
            },
        });
    }

    function cargarLeyesNormativas() {
        if (!elements.selectEnsayoLeyNormativa) {
            return;
        }

        poblarSelectLeyesNormativas(elements.selectEnsayoLeyNormativa, catalogs.leyes);
        inicializarSelectLeyNormativa(elements.selectEnsayoLeyNormativa, 'modalAgregarEnsayo');
    }

    function actualizarEnsayosDisponiblesParaComponentes() {
        if (!elements.selectEnsayoAsociado) {
            return;
        }

        elements.selectEnsayoAsociado.innerHTML = '';

        if (state.ensayos.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No hay ensayos agregados';
            option.disabled = true;
            elements.selectEnsayoAsociado.appendChild(option);
            return;
        }

        const ensayosOrdenados = state.ensayos.slice().sort((a, b) => a.item - b.item);
        ensayosOrdenados.forEach(ensayo => {
            const option = document.createElement('option');
            option.value = ensayo.item;
            option.textContent = `Item ${ensayo.item} - ${ensayo.descripcion || 'Ensayo'}`;
            option.dataset.componentes = JSON.stringify(obtenerComponentesSugeridosDeEnsayo(ensayo));
            elements.selectEnsayoAsociado.appendChild(option);
        });

        elements.selectEnsayoAsociado.value = ensayosOrdenados[ensayosOrdenados.length - 1].item;
        preseleccionarComponentesDeEnsayo(elements.selectEnsayoAsociado.value, false);

        elements.selectEnsayoAsociado.onchange = async function () {
            const ensayoItemId = this.value;
            if (ensayoItemId) {
                const ensayo = state.ensayos.find(e => e.item === Number(ensayoItemId));
                // Siempre cargar opciones: con matriz si existe (ensayos normales), o catálogo completo si no (agrupadores sin matriz)
                const matrizCodigo = (ensayo && ensayo.matriz_codigo) ? ensayo.matriz_codigo.toString().trim() : null;
                await cargarOpcionesComponentes(matrizCodigo || null);
            }
            preseleccionarComponentesDeEnsayo(ensayoItemId, false);
        };
    }

    function handleCambioEnsayoModal(event) {
        const select = event.target;
        const option = select.options[select.selectedIndex];

        if (!option || !select.value) {
            if (elements.campoCodigoEnsayo) {
                elements.campoCodigoEnsayo.value = '';
            }
            if (elements.infoMetodoEnsayo) {
                elements.infoMetodoEnsayo.textContent = '';
            }
            cargarNotasEnContenedor('notasEnsayoContainer', []);
            return;
        }

        if (elements.campoCodigoEnsayo) {
            elements.campoCodigoEnsayo.value = option.dataset.codigo || '';
        }

        if (elements.infoMetodoEnsayo) {
            const metodoCodigo = option.dataset.metodoCodigo || '';
            const metodoDescripcion = option.dataset.metodoDescripcion || '';
            elements.infoMetodoEnsayo.textContent = metodoCodigo
                ? `Método asociado: ${metodoCodigo}${metodoDescripcion ? ` - ${metodoDescripcion}` : ''}`
                : '';
        }

        const ensCat = catalogs.ensayos.find(e => String(e.id) === String(select.value));
        const defNotas = ensCat
            ? notasPredeterminadasDesdeCatalogo(ensCat.nota_imprimible, ensCat.nota_interna)
            : [];
        cargarNotasEnContenedor('notasEnsayoContainer', defNotas);

        const precioExtraNuevo = document.getElementById('ensayo_precio_extra');
        if (precioExtraNuevo && ensCat) {
            const p = parseFloat(ensCat.precio);
            const v = Number.isFinite(p) && p >= 0 ? p : 0;
            precioExtraNuevo.value = formatNumber(v);
        }

        // Regla automática de muestreo según canal detectado
        const canal = canalEspecialDesdeMatrizYDescripcion(option.dataset.matrizDescripcion, option.dataset.descripcion);
        const isEdit = select.id === 'edit_ensayo_muestra';
        const chkId = isEdit ? 'edit_ensayo_lleva_muestreo' : 'ensayo_no_lleva_muestreo';
        const chk = document.getElementById(chkId);
        aplicarEstadoCheckboxNoLlevaMuestreo(chk, canal);
    }

    function handleCambioComponenteModal() {
        const selectedOptions = obtenerOpcionesSeleccionadasComponente();

        actualizarResumenComponentesSeleccionados(selectedOptions);
        actualizarPanelSeleccionComponentes();
        aplicarEstadoCamposComponente(selectedOptions);

        if (selectedOptions.length !== 1) {
            limpiarCamposComponente();
            return;
        }

        aplicarDatosComponenteDesdeOption(selectedOptions[0]);
        const cat = catalogs.componentes.find(c => String(c.id) === String(selectedOptions[0].value));
        aplicarTextareasNotasComponenteDesdeCatalogo(cat || null);
    }

    function limpiarCamposComponente() {
        if (elements.campoCodigoComponente) {
            elements.campoCodigoComponente.value = '';
        }
        if (elements.campoPrecioComponente) {
            elements.campoPrecioComponente.value = '0.00';
        }
        limpiarTextareasNotasComponenteAgregar();
    }

    function aplicarDatosComponenteDesdeOption(option) {
        if (!option || !option.dataset) {
            return;
        }
        const dataset = option.dataset;

        if (elements.campoCodigoComponente) {
            elements.campoCodigoComponente.value = dataset.codigo || '';
        }
        if (elements.campoPrecioComponente) {
            const precio = precioReferenciaDesdeOptionDataset(dataset);
            elements.campoPrecioComponente.value = precio.toFixed(2);
        }
    }

    function setSelectValue(selectElement, value, labelText, selector) {
        if (!selectElement) {
            return;
        }

        const normalizado = (value || '').toString().trim();
        if (!normalizado) {
            selectElement.value = '';
            actualizarSelect2(selector, '');
            return;
        }

        let option = Array.from(selectElement.options || []).find(opt => opt.value === normalizado);
        if (!option) {
            option = new Option(labelText || normalizado, normalizado, true, true);
            selectElement.add(option);
        } else {
            option.selected = true;
        }

        selectElement.value = normalizado;
        actualizarSelect2(selector, normalizado);
    }

    function actualizarSelect2(selector, value) {
        if (!selector || !window.$) {
            return;
        }
        const $control = window.$(selector);
        if ($control && $control.length) {
            $control.val(value || '').trigger('change.select2');
        }
    }

    function aplicarEstadoCamposComponente(selectedOptions) {
        const multiples = selectedOptions.length > 1;

        if (elements.componentesSeleccionInfo) {
            elements.componentesSeleccionInfo.classList.toggle('d-none', !multiples);
        }

        (elements.camposComponenteInteractivos || []).forEach(control => {
            if (!control) {
                return;
            }
            const esSelect = control.tagName === 'SELECT';
            control.disabled = multiples && esSelect;
            control.readOnly = multiples && !esSelect;
            control.classList.toggle('campo-multi-disabled', multiples);

            if (window.$ && esSelect && control.id) {
                const $control = window.$(`#${control.id}`);
                if ($control && $control.length && $control.data('select2')) {
                    $control.prop('disabled', multiples).trigger('change.select2');
                }
            }
        });
    }

    function actualizarResumenComponentesSeleccionados(opciones) {
        if (!elements.componentesResumenLista || !elements.componentesResumenPlaceholder) {
            return;
        }

        elements.componentesResumenLista.innerHTML = '';

        if (!opciones.length) {
            elements.componentesResumenPlaceholder.classList.remove('d-none');
            return;
        }

        elements.componentesResumenPlaceholder.classList.add('d-none');

        opciones.forEach(option => {
            const dataset = option.dataset || {};
            const descripcion = dataset.descripcion || option.textContent.trim();
            const codigo = (dataset.codigo || '').trim();
            const meta = construirMetaComponente(dataset) || 'Sin información adicional';
            const titulo = codigo ? `[${codigo}] ${descripcion}` : descripcion;

            const item = document.createElement('li');
            item.className = 'componentes-resumen-item';
            item.innerHTML = `
                <div class="fw-semibold mb-1">${escapeHtml(titulo)}</div>
                <div class="componentes-resumen-meta">${escapeHtml(meta)}</div>
            `;
            elements.componentesResumenLista.appendChild(item);
        });
    }

    const UMBRAL_FILTRO_LISTA_COMPONENTES = 8;

    function quitarValoresComponenteSelect(ids) {
        if (!ids || !ids.length || !elements.selectComponente) {
            return;
        }

        const idsSet = new Set(ids.map(id => id.toString()));

        if (window.$ && window.$('#componente_analisis').length) {
            const $select = window.$('#componente_analisis');
            const actuales = ($select.val() || []).map(v => v.toString());
            const finales = actuales.filter(v => !idsSet.has(v));
            $select.val(finales.length ? finales : null).trigger('change');
            return;
        }

        Array.from(elements.selectComponente.options || []).forEach(option => {
            if (idsSet.has(option.value.toString())) {
                option.selected = false;
            }
        });
        elements.selectComponente.dispatchEvent(new Event('change'));
    }

    function actualizarModoAgregarSelect2Componentes(cantidad) {
        if (!window.$ || !window.$('#componente_analisis').length) {
            return;
        }

        const $select = window.$('#componente_analisis');
        if (!$select.data('select2')) {
            return;
        }

        const $container = $select.next('.select2-container');
        $container.toggleClass('select2-solo-agregar', cantidad > 0);

        const placeholder = cantidad > 0 ? 'Buscar y agregar más...' : 'Buscar y agregar análisis...';
        $container.find('.select2-search__field').attr('placeholder', placeholder);
    }

    function actualizarPanelSeleccionComponentes() {
        const panel = document.getElementById('componentes_seleccionados_panel');
        const lista = document.getElementById('componentes_seleccionados_lista');
        const countSpan = document.getElementById('componentes_seleccionados_count');
        const buscar = document.getElementById('componentes_seleccionados_buscar');
        const btnVaciar = document.getElementById('btnComponentesQuitarTodos');
        if (!panel || !lista) {
            return;
        }

        const opciones = obtenerOpcionesSeleccionadasComponente();
        actualizarModoAgregarSelect2Componentes(opciones.length);

        if (!opciones.length) {
            panel.classList.add('d-none');
            lista.innerHTML = '';
            if (countSpan) {
                countSpan.textContent = '0';
            }
            if (buscar) {
                buscar.classList.add('d-none');
                buscar.value = '';
            }
            return;
        }

        panel.classList.remove('d-none');
        if (countSpan) {
            countSpan.textContent = String(opciones.length);
        }
        if (btnVaciar) {
            btnVaciar.classList.toggle('d-none', opciones.length < 2);
        }
        if (buscar) {
            buscar.classList.toggle('d-none', opciones.length < UMBRAL_FILTRO_LISTA_COMPONENTES);
        }

        const filtro = ((buscar && buscar.value) || '').trim().toLowerCase();
        lista.innerHTML = '';
        let visibles = 0;

        opciones.forEach(option => {
            const id = option.value.toString();
            const dataset = option.dataset || {};
            let descripcion = (dataset.descripcion || option.textContent || '').trim();
            descripcion = descripcion.replace(/^\[AGRUPADOR\]\s*/, '').replace(/\s*\(ID:\s*\d+\)\s*$/, '').trim();
            const metodo = construirEtiquetaMetodoAnalisis(dataset);
            const textoBusqueda = `${descripcion} ${id} ${metodo || ''}`.toLowerCase();

            if (filtro && !textoBusqueda.includes(filtro)) {
                return;
            }

            visibles++;

            const row = document.createElement('div');
            row.className = 'componentes-seleccionados-item';
            row.dataset.componenteId = id;

            const info = document.createElement('div');
            info.className = 'componentes-seleccionados-item-info';

            const nombre = document.createElement('div');
            nombre.className = 'componentes-seleccionados-item-nombre';
            nombre.textContent = descripcion;

            info.appendChild(nombre);
            if (metodo) {
                const meta = document.createElement('div');
                meta.className = 'componentes-seleccionados-item-meta';
                meta.textContent = metodo;
                info.appendChild(meta);
            }

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger componentes-seleccionados-item-quitar';
            removeBtn.textContent = 'Quitar';
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                quitarValoresComponenteSelect([id]);
            });

            row.appendChild(info);
            row.appendChild(removeBtn);
            lista.appendChild(row);
        });

        if (filtro && visibles === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'componentes-seleccionados-vacio-filtro';
            vacio.textContent = 'Ningún análisis coincide con el filtro.';
            lista.appendChild(vacio);
        }
    }

    function inicializarPanelSeleccionComponentes() {
        const buscar = document.getElementById('componentes_seleccionados_buscar');
        const btnTodos = document.getElementById('btnComponentesQuitarTodos');

        if (buscar) {
            buscar.addEventListener('input', actualizarPanelSeleccionComponentes);
        }

        if (btnTodos) {
            btnTodos.addEventListener('click', function () {
                const opciones = obtenerOpcionesSeleccionadasComponente();
                if (!opciones.length) {
                    return;
                }

                const ejecutar = () => {
                    quitarValoresComponenteSelect(opciones.map(opt => opt.value));
                };

                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: '¿Vaciar la lista?',
                        text: `Se quitarán los ${opciones.length} análisis seleccionados.`,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, vaciar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#d33',
                    }).then(result => {
                        if (result.isConfirmed) {
                            ejecutar();
                        }
                    });
                } else if (confirm(`¿Vaciar los ${opciones.length} análisis seleccionados?`)) {
                    ejecutar();
                }
            });
        }
    }

    function construirEtiquetaMatriz(dataset = {}) {
        const codigo = (dataset.matrizCodigo || '').trim();
        const descripcion = (dataset.matrizDescripcion || '').trim();

        if (!codigo && !descripcion) {
            return null;
        }

        if (descripcion) {
            return descripcion;
        }

        return codigo;
    }

    function construirEtiquetaMetodoAnalisis(dataset = {}) {
        const codigo = (dataset.metodoAnalisisId || '').trim();
        const descripcion = (dataset.metodoDescripcion || '').trim();

        if (!codigo && !descripcion) {
            return null;
        }

        if (codigo && descripcion) {
            return `${codigo} - ${descripcion}`;
        }

        return codigo || descripcion;
    }

    function construirMetaComponente(dataset = {}) {
        const partes = [];
        const matrizEtiqueta = construirEtiquetaMatriz(dataset);
        if (matrizEtiqueta) {
            partes.push(`Matriz: ${matrizEtiqueta}`);
        }
        const metodoAnalisis = construirEtiquetaMetodoAnalisis(dataset);
        if (metodoAnalisis) {
            partes.push(`Mét. análisis: ${metodoAnalisis}`);
        }
        if (dataset.unidadMedida) {
            partes.push(`U.M.: ${dataset.unidadMedida}`);
        }
        const precio = parseFloat(dataset.precio ?? dataset.precioRaw);
        if (dataset.precioDefinido === '1' && !isNaN(precio) && precio > 0) {
            partes.push(`Precio: ${formatCurrency(precio)}`);
        }

        return partes.join(' • ');
    }

    function renderComponenteOptionTemplate(data) {
        if (!data.id || !data.element) {
            return data.text;
        }
        const dataset = data.element.dataset || {};
        let descripcion = data.text || dataset.descripcion || '';
        // Limpiar el prefijo [AGRUPADOR] si existe
        descripcion = descripcion.replace(/^\[AGRUPADOR\]\s*/, '');
        const esAgrupador = dataset.esAgrupador === '1';
        const meta = construirMetaComponente(dataset) || 'Sin información adicional';
        
        // Si es agrupador, obtener cantidad de componentes asociados
        let infoAgrupador = '';
        if (esAgrupador && dataset.componentesAsociados) {
            try {
                const componentesAsociados = JSON.parse(dataset.componentesAsociados);
                infoAgrupador = `<span class="badge bg-info text-dark ms-2">Agrupador (${componentesAsociados.length} componentes)</span>`;
            } catch (e) {
                infoAgrupador = '<span class="badge bg-info text-dark ms-2">Agrupador</span>';
            }
        }

        return `
            <div class="componente-option">
                <div class="componente-option-title">
                    ${escapeHtml(descripcion)} (ID: ${data.id})
                    ${infoAgrupador}
                </div>
                <div class="componente-option-meta">${escapeHtml(meta)}</div>
            </div>
        `;
    }

    function renderComponenteSelectionTemplate(data) {
        if (!data.id || !data.element) {
            return data.text;
        }
        const dataset = data.element.dataset || {};
        let descripcion = data.text || dataset.descripcion || '';
        descripcion = descripcion.replace(/^\[AGRUPADOR\]\s*/, '').replace(/\s*\(ID:\s*\d+\)\s*$/, '').trim();
        const esAgrupador = dataset.esAgrupador === '1';
        const metodoAnalisis = construirEtiquetaMetodoAnalisis(dataset);

        let label = escapeHtml(descripcion) + ` (ID: ${data.id})`;
        if (metodoAnalisis) {
            label += ` | ${escapeHtml(metodoAnalisis)}`;
        }
        if (esAgrupador) {
            label += ' [AGRUPADOR]';
        }
        return label;
    }

    function handleComponenteChangeFromSelect(selectEl) {
        if (!selectEl) {
            return;
        }

        const selectedOptions = Array.from(selectEl.selectedOptions || []);
        const selectedOption = selectedOptions.length ? selectedOptions[selectedOptions.length - 1] : null;
        const selectedValue = selectedOption ? selectedOption.value : '';

        // console.log('[componente change] (native) value=', selectedValue, 'metodoCodigo=', selectedOption && selectedOption.dataset ? selectedOption.dataset.metodoCodigo : undefined);

        const codigoField = document.getElementById('componente_codigo');
        if (codigoField) {
            codigoField.value = selectedOption && selectedOption.dataset && selectedOption.dataset.codigo ? selectedOption.dataset.codigo : '';
        }
        const unidadField = document.getElementById('comp_unidad_medida');
        if (unidadField) {
            unidadField.value = selectedOption && selectedOption.dataset ? (selectedOption.dataset.unidadMedida || '').trim() : '';
        }

        const info = document.getElementById('componente_metodo_info');
        const metodoAnalisisId = selectedOption && selectedOption.dataset ? (selectedOption.dataset.metodoAnalisisId || '').trim() : '';
        let metodoDesc = selectedOption && selectedOption.dataset ? (selectedOption.dataset.metodoDescripcion || '').trim() : '';
        let unidadMedida = selectedOption && selectedOption.dataset ? selectedOption.dataset.unidadMedida : '';
        let limiteEstablecido = selectedOption && selectedOption.dataset ? selectedOption.dataset.limitesEstablecidos : '';
        const precio = selectedOption && selectedOption.dataset
            ? precioReferenciaDesdeOptionDataset(selectedOption.dataset)
            : 0;

        if (info) {
            info.textContent = '';
            if (metodoAnalisisId || metodoDesc) {
                info.textContent += `Mét. análisis: ${metodoAnalisisId}${metodoDesc ? ` - ${metodoDesc}` : ''}`;
            }
            if (unidadMedida) {
                info.textContent += `U.M.: ${unidadMedida}`;
            }
            if (limiteEstablecido) {
                info.textContent += `Límite: ${limiteEstablecido}`;
            }
        }

        // Autocompletar precio del componente (solo si tiene precio definido en catálogo)
        const precioField = document.getElementById('comp_precio_final');
        if (precioField) {
            precioField.value = (parseFloat(precio) || 0).toFixed(2);
        }
    }

    // Funciones para manejar múltiples notas
    function crearElementoNota(notaIndex, notaTipo = 'imprimible', notaContenido = '', containerId) {
        const notaId = `nota_${containerId}_${notaIndex}`;
        const div = document.createElement('div');
        div.className = 'card mb-2 nota-item';
        div.dataset.notaIndex = notaIndex;
        div.innerHTML = `
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <label class="form-label mb-0 fw-semibold">Nota #${notaIndex + 1}</label>
                    <button type="button" class="btn btn-sm btn-outline-danger btnEliminarNota" data-nota-id="${notaId}">
                        <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                    </button>
                </div>
                <div class="row mb-2">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="nota_tipo_${containerId}_${notaIndex}" id="${notaId}_imprimible" value="imprimible" ${notaTipo === 'imprimible' ? 'checked' : ''}>
                            <label class="form-check-label" for="${notaId}_imprimible">Imprimible</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="nota_tipo_${containerId}_${notaIndex}" id="${notaId}_interna" value="interna" ${notaTipo === 'interna' ? 'checked' : ''}>
                            <label class="form-check-label" for="${notaId}_interna">Interna</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="nota_tipo_${containerId}_${notaIndex}" id="${notaId}_fact" value="fact" ${notaTipo === 'fact' ? 'checked' : ''}>
                            <label class="form-check-label" for="${notaId}_fact">Fact.</label>
                        </div>
                    </div>
                </div>
                <textarea class="form-control nota-contenido" id="${notaId}_contenido" rows="3" maxlength="${MAX_NOTA_ITEM_CARACTERES}" placeholder="Escriba el contenido de la nota...">${truncarTextoNotaItem(notaContenido)}</textarea>
                <small class="text-muted d-block mt-1">Máximo ${MAX_NOTA_ITEM_CARACTERES} caracteres.</small>
            </div>
        `;
        return div;
    }

    function agregarNotaAlContenedor(containerId, notaTipo = 'imprimible', notaContenido = '') {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const notaIndex = container.querySelectorAll('.nota-item').length;
        const elementoNota = crearElementoNota(notaIndex, notaTipo, notaContenido, containerId);
        container.appendChild(elementoNota);
        
        // Agregar evento para eliminar nota
        const btnEliminar = elementoNota.querySelector('.btnEliminarNota');
        if (btnEliminar) {
            btnEliminar.addEventListener('click', function() {
                elementoNota.remove();
                // Renumerar las notas restantes
                renumerarNotas(containerId);
            });
        }
    }

    function renumerarNotas(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const notas = container.querySelectorAll('.nota-item');
        notas.forEach((nota, index) => {
            const label = nota.querySelector('.form-label');
            if (label) {
                label.textContent = `Nota #${index + 1}`;
            }
            nota.dataset.notaIndex = index;
        });
    }

    function obtenerNotasDelContenedor(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return [];
        
        const notas = [];
        const notaItems = container.querySelectorAll('.nota-item');
        
        notaItems.forEach((notaItem) => {
            const tipoRadio = notaItem.querySelector('input[type="radio"]:checked');
            const contenidoTextarea = notaItem.querySelector('.nota-contenido');
            
            if (tipoRadio && contenidoTextarea) {
                const tipo = tipoRadio.value;
                const contenido = contenidoTextarea.value.trim();
                
                if (contenido) { // Solo agregar si tiene contenido
                    notas.push({
                        tipo: tipo,
                        contenido: truncarTextoNotaItem(contenido)
                    });
                }
            }
        });
        
        return notas;
    }

    /**
     * Notas sugeridas desde catálogo cotio_items (imprimible / interna).
     */
    function notasPredeterminadasDesdeCatalogo(notaImprimible, notaInterna) {
        const notas = [];
        const ni = notaImprimible != null && String(notaImprimible).trim() !== '' ? String(notaImprimible).trim() : '';
        const nin = notaInterna != null && String(notaInterna).trim() !== '' ? String(notaInterna).trim() : '';
        if (ni) {
            notas.push({ tipo: 'imprimible', contenido: truncarTextoNotaItem(ni) });
        }
        if (nin) {
            notas.push({ tipo: 'interna', contenido: truncarTextoNotaItem(nin) });
        }
        return notas;
    }

    function notasAObjetoPersistencia(notas) {
        if (!notas || notas.length === 0) {
            return { nota_tipo: null, nota_contenido: null };
        }
        return {
            nota_tipo: notas[0].tipo || null,
            nota_contenido: JSON.stringify(notas),
        };
    }

    /**
     * Al agregar componente(s): con un solo ítem seleccionado, las cajas del modal mandan;
     * con varios, cada fila usa las notas por defecto de su ítem de catálogo.
     */
    function notasParaNuevoComponenteDesdeModalYCatalogo(catalogoEntry, seleccionUnica) {
        if (seleccionUnica) {
            const impEl = document.getElementById('comp_nota_imprimible_texto');
            const intEl = document.getElementById('comp_nota_interna_texto');
            const imp = impEl ? String(impEl.value || '').trim() : '';
            const intern = intEl ? String(intEl.value || '').trim() : '';
            if (imp || intern) {
                return notasPredeterminadasDesdeCatalogo(imp || null, intern || null);
            }
            return notasPredeterminadasDesdeCatalogo(
                catalogoEntry ? catalogoEntry.nota_imprimible : null,
                catalogoEntry ? catalogoEntry.nota_interna : null
            );
        }
        return notasPredeterminadasDesdeCatalogo(
            catalogoEntry ? catalogoEntry.nota_imprimible : null,
            catalogoEntry ? catalogoEntry.nota_interna : null
        );
    }

    function aplicarTextareasNotasComponenteDesdeCatalogo(catalogoEntry) {
        const impEl = document.getElementById('comp_nota_imprimible_texto');
        const intEl = document.getElementById('comp_nota_interna_texto');
        if (!impEl || !intEl) {
            return;
        }
        if (!catalogoEntry) {
            impEl.value = '';
            intEl.value = '';
            return;
        }
        impEl.value = catalogoEntry.nota_imprimible != null
            ? truncarTextoNotaItem(String(catalogoEntry.nota_imprimible))
            : '';
        intEl.value = catalogoEntry.nota_interna != null
            ? truncarTextoNotaItem(String(catalogoEntry.nota_interna))
            : '';
    }

    function limpiarTextareasNotasComponenteAgregar() {
        aplicarTextareasNotasComponenteDesdeCatalogo(null);
    }

    function cargarNotasEnContenedor(containerId, notas) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Limpiar contenedor
        container.innerHTML = '';
        
        // Si hay notas, cargarlas
        if (notas && notas.length > 0) {
            notas.forEach(nota => {
                agregarNotaAlContenedor(containerId, nota.tipo || nota.nota_tipo, nota.contenido || nota.nota_contenido);
            });
        }
    }

    function canalEspecialDesdeMatrizYDescripcion(matrizDesc, descripcion) {
        const mat = ((matrizDesc || '') + '').toString().toLowerCase();
        const desc = ((descripcion || '') + '').toString().toLowerCase();
        const hay = function (needle) {
            return mat.includes(needle) || desc.includes(needle);
        };
        if (hay('consultoria')) {
            return 'consultoria';
        }
        if (hay('clarke fire') || hay('clarke-fire') || hay('clarke_fire') || (hay('clarke') && hay('fire'))) {
            return 'clarke_fire';
        }
        if (hay('asp')) {
            return 'asp';
        }
        if (hay('mediciones')) {
            return 'mediciones';
        }
        return null;
    }

    /** Canales que nunca llevan muestreo en campo (checkbox bloqueado en true). */
    function canalSinMuestreoEnCampo(canal) {
        return ['consultoria', 'clarke_fire', 'asp'].includes(canal);
    }

    function ensayoEsClarkeFire(ensayo) {
        if (!ensayo) {
            return false;
        }
        if ((ensayo.canal_especial || '').toString().trim() === 'clarke_fire') {
            return true;
        }
        const matriz = ensayo.matriz_descripcion || '';
        const desc = ensayo.descripcion || '';
        return canalEspecialDesdeMatrizYDescripcion(matriz, desc) === 'clarke_fire';
    }

    function cantidadComponenteEditable(ensayo) {
        return ensayoEsClarkeFire(ensayo);
    }

    function resolverLlevaMuestreoEnsayo(chkNoLlevaMuestreo, canalDetectado) {
        if (canalDetectado === 'mediciones') {
            return true;
        }
        if (canalSinMuestreoEnCampo(canalDetectado)) {
            return false;
        }
        if (chkNoLlevaMuestreo && chkNoLlevaMuestreo.checked) {
            return false;
        }
        return !canalDetectado;
    }

    function aplicarEstadoCheckboxNoLlevaMuestreo(chk, canal) {
        if (!chk) {
            return;
        }
        if (canal === 'mediciones') {
            chk.checked = false;
            chk.disabled = true;
            return;
        }
        if (canalSinMuestreoEnCampo(canal)) {
            chk.checked = true;
            chk.disabled = true;
            return;
        }
        chk.checked = false;
        chk.disabled = false;
    }

    function agregarEnsayo() {
        if (!state.puedeEditar) {
            return;
        }

        if (!elements.selectEnsayo) {
            return;
        }

        const muestraId = elements.selectEnsayo.value;
        if (!muestraId) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecciona un ensayo',
                    text: 'Debes elegir una muestra antes de continuar.',
                });
            } else {
                alert('Debe seleccionar una muestra/ensayo');
            }
            return;
        }

        const option = elements.selectEnsayo.options[elements.selectEnsayo.selectedIndex];
        const descripcion = option ? (option.dataset.descripcion || option.textContent.replace(/\s*\(ID: \d+\)$/, '')) : '';
        const codigo = option ? option.dataset.codigo : '';
        const componentesSugeridos = option && option.dataset && option.dataset.componentes
            ? JSON.parse(option.dataset.componentes)
            : (catalogs.ensayosDefaultsById[muestraId] || []);

        // Capturar matriz_codigo y matriz_descripcion del option
        const matrizCodigo = option && option.dataset.matrizCodigo ? option.dataset.matrizCodigo.trim() : null;
        const matrizDescripcion = option && option.dataset.matrizDescripcion ? option.dataset.matrizDescripcion : null;

        const cantidad = toPositiveInt(elements.campoCantidadEnsayo ? elements.campoCantidadEnsayo.value : 1, 1);
        const precioExtraEnsayo = Math.max(0, parseFloat(document.getElementById('ensayo_precio_extra')?.value) || 0);

        // Validar que al menos una opción de cadena de custodia esté seleccionada
        const _chkNoCust = document.getElementById('no_requiere_custodia');
        const _chkMapba = document.getElementById('req_prot_mapba');
        const _chkRelAgregar = document.getElementById('ensayo_chk_req_cadena_relacionada');
        const _algunoSeleccionado = (_chkNoCust && _chkNoCust.checked)
            || (_chkMapba && _chkMapba.checked)
            || (_chkRelAgregar && _chkRelAgregar.checked);

        const _groupAgregar = document.getElementById('custodia_group_agregar');
        const _errorAgregar = document.getElementById('custodia_error_agregar');

        if (!_algunoSeleccionado) {
            if (_groupAgregar) _groupAgregar.classList.add('border-danger');
            if (_errorAgregar) _errorAgregar.classList.remove('d-none');
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Debe seleccionar al menos una opción de cadena de custodia.',
                });
            }
            return;
        }
        if (_groupAgregar) _groupAgregar.classList.remove('border-danger');
        if (_errorAgregar) _errorAgregar.classList.add('d-none');

        // Cadena de custodia: id no_requiere_custodia — marcado = NO lleva cadena (req_cadena_custodia = false)
        const noRequiereCustodiaCheckbox = document.getElementById('no_requiere_custodia');
        const reqCadenaCustodia = noRequiereCustodiaCheckbox
            ? !noRequiereCustodiaCheckbox.checked
            : false;

        // Lleva muestreo: igual que en edición — checkbox "No lleva muestreo" fuerza false; si no, regla por canal (matriz/descripción).
        const canalDetectado = canalEspecialDesdeMatrizYDescripcion(matrizDescripcion, descripcion);
        const chkNoLlevaMuestreo = document.getElementById('ensayo_no_lleva_muestreo');
        const llevaMuestreo = resolverLlevaMuestreoEnsayo(chkNoLlevaMuestreo, canalDetectado);

        // Protocolo MAPBA
        const reqProtMapbaCheckbox = document.getElementById('req_prot_mapba');
        const reqProtMapba = reqProtMapbaCheckbox ? !!reqProtMapbaCheckbox.checked : false;

        const leyNormSelect = elements.selectEnsayoLeyNormativa;
        const leyNormativaCodigo = (leyNormSelect && leyNormSelect.value)
            ? String(leyNormSelect.value).trim()
            : null;

        // Capturar múltiples notas del modal
        const notas = obtenerNotasDelContenedor('notasEnsayoContainer');
        
        // Para compatibilidad con el backend, guardar como JSON en nota_contenido
        // y el primer tipo en nota_tipo (o null si no hay notas)
        const notaTipo = notas.length > 0 ? notas[0].tipo : null;
        const notaContenido = notas.length > 0 ? JSON.stringify(notas) : null;

        state.contador += 1;

        const nuevoEnsayo = normalizarEnsayo({
            item: state.contador,
            muestra_id: muestraId,
            descripcion: descripcion,
            codigo: codigo,
            cantidad: cantidad,
            precio: 0,
            total: 0,
            componentes_sugeridos: componentesSugeridos,
            nota_tipo: notaTipo,
            nota_contenido: notaContenido,
            notas: notas, // Guardar también como array para uso interno
            matriz_codigo: matrizCodigo,
            matriz_descripcion: matrizDescripcion,
            req_cadena_custodia: reqCadenaCustodia,
            req_prot_mapba: reqProtMapba,
            lleva_muestreo: llevaMuestreo,
            canal_especial: canalDetectado,
            precio_extra_ensayo: precioExtraEnsayo,
            ley_normativa_id: leyNormativaCodigo || null,
            es_priori: (function () {
                const chk = document.getElementById('ensayo_es_priori');
                return !!(chk && chk.checked);
            })(),
            adjuntos_pendientes: [],
            adjuntos_existentes: [],
            adjuntos_eliminar: [],
        });

        capturarAdjuntosDesdeInput('ensayo_adjuntos_input', nuevoEnsayo, 'ensayoAdjuntosLista');

        state.ensayos.push(nuevoEnsayo);
        recalcularPreciosEnsayo(nuevoEnsayo.item);
        renderTabla();
        cerrarModal(elements.modalEnsayo, 'formEnsayo');

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Ensayo agregado',
                text: 'El ensayo se añadió a la cotización.',
                timer: 1800,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
        }
    }

    function agregarComponente() {
        if (!state.puedeEditar) {
            return;
        }
 
        if (!elements.selectEnsayoAsociado || !elements.selectComponente) {
            return;
        }
 
        const ensayoAsociado = Number(elements.selectEnsayoAsociado.value);
        if (!ensayoAsociado) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecciona un ensayo',
                    text: 'El componente debe asociarse a un ensayo existente.',
                });
            } else {
                alert('Debe seleccionar un ensayo asociado.');
            }
            return;
        }
 
        const ensayoRegistro = state.ensayos.find(e => e.item === ensayoAsociado);
        if (!ensayoRegistro) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Ensayo no válido',
                    text: 'Selecciona un ensayo válido antes de agregar componentes.',
                });
            } else {
                alert('El ensayo seleccionado no es válido.');
            }
            return;
        }
 
        const selectedOptions = Array.from(elements.selectComponente.selectedOptions || []).filter(opt => opt.value);
        if (!selectedOptions.length) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Selecciona al menos un análisis',
                    text: 'Debes elegir uno o varios análisis para continuar.',
                });
            } else {
                alert('Debe seleccionar al menos un análisis.');
            }
            return;
        }
 
        const precioManual = toPositiveNumber(elements.campoPrecioComponente ? elements.campoPrecioComponente.value : 0, 0);
        const metodoAnalisisId = null;
        const leyNormativaId = null;

        // Cadena de custodia del componente: marcado = NO lleva cadena
        const compNoRequiereCustodiaCheckbox = document.getElementById('comp_no_requiere_custodia');
        const compReqCadenaCustodia = compNoRequiereCustodiaCheckbox
            ? !compNoRequiereCustodiaCheckbox.checked
            : false;

        // Protocolo MAPBA del componente
        const compReqProtMapbaCheckbox = document.getElementById('comp_req_prot_mapba');
        const compReqProtMapba = compReqProtMapbaCheckbox ? !!compReqProtMapbaCheckbox.checked : false;

        const seleccionUnicaAnalisis = selectedOptions.length === 1;

        let agregados = 0;
        let omitidos = 0;

        // Agregar en orden visual: por cada selección, si es agrupador insertar sus asociados
        // inmediatamente después, respetando el orden recibido en `componentesAsociados`.
        let componentesDeAgrupadoresAgregados = 0;
        let agrupadoresAgregados = 0;
        const asociadosVistos = new Set();

        function componenteYaExiste(analisisId) {
            return state.componentes.some(comp =>
                comp.analisis_id?.toString() === analisisId.toString() &&
                comp.ensayo_asociado === ensayoAsociado
            );
        }

        function agregarComponenteDesdeCatalogo(analisisId, deAgrupador, precioForzado = null) {
            if (componenteYaExiste(analisisId)) {
                omitidos += 1;
                return false;
            }

            const componenteCatalogo = catalogs.componentes.find(c => c.id.toString() === analisisId.toString());
            if (!componenteCatalogo) {
                return false;
            }

            const descripcion = componenteCatalogo.descripcion || '';
            const codigo = componenteCatalogo.codigo || '';
            const metodoAnalisisId = (componenteCatalogo.metodo_analisis_id || '').toString().trim(); // análisis → cotio_codigometodo_analisis
            const metodoCodigo = (componenteCatalogo.metodo_codigo || '').toString().trim();          // muestreo → cotio_codigometodo
            const metodoDescripcion = componenteCatalogo.metodo_descripcion || '';
            const unidadMedida = componenteCatalogo.unidad_medida || '';
            const limiteDeteccion = componenteCatalogo.limites_establecidos || '';
            const cantidad = 1;
            const precioCatalogo = precioReferenciaDesdeCatalogo(componenteCatalogo);
            const precio = (precioForzado !== null) ? toPositiveNumber(precioForzado, 0) : precioCatalogo;
            const precioMinimoVenta = precioMinimoVentaDesdeCatalogo(componenteCatalogo);

            const notasComp = notasParaNuevoComponenteDesdeModalYCatalogo(componenteCatalogo, false);
            const np = notasAObjetoPersistencia(notasComp);

            const esSugerido = ensayoRegistro && 
                               Array.isArray(ensayoRegistro.componentes_sugeridos) && 
                               ensayoRegistro.componentes_sugeridos.map(id => id.toString()).includes(analisisId.toString());
            const deAgrupadorFinal = resolverDeAgrupadorComponente(ensayoRegistro, analisisId, !!deAgrupador || esSugerido);

            state.contador += 1;
            const nuevoComponente = normalizarComponente({
                item: state.contador,
                analisis_id: analisisId,
                descripcion: descripcion,
                codigo: codigo,
                cantidad: cantidad,
                precio: precio,
                precio_minimo_venta: precioMinimoVenta,
                total: precio * cantidad,
                ensayo_asociado: ensayoAsociado,
                metodo_codigo: metodoCodigo,
                metodo_descripcion: metodoDescripcion,
                unidad_medida: unidadMedida,
                limite_deteccion: limiteDeteccion,
                metodo_analisis_id: metodoAnalisisId,
                ley_normativa_id: leyNormativaId,
                nota_tipo: np.nota_tipo,
                nota_contenido: np.nota_contenido,
                de_agrupador: deAgrupadorFinal,
            });

            state.componentes.push(nuevoComponente);
            agregados += 1;
            if (deAgrupador) componentesDeAgrupadoresAgregados += 1;
            return true;
        }

        selectedOptions.forEach(option => {
            const analisisId = option.value;
            const esAgrupador = option.dataset.esAgrupador === '1';
            const agrupadorTienePrecioDefinido = option.dataset.precioDefinido === '1';

            let idsHijosAgrupador = [];
            if (esAgrupador && option.dataset.componentesAsociados) {
                try {
                    idsHijosAgrupador = JSON.parse(option.dataset.componentesAsociados) || [];
                } catch (e) {
                    idsHijosAgrupador = [];
                }
            }
            // Si el agrupador tiene precio de catálogo y trae analitos, no duplicamos el agrupador como línea con importe:
            // los hijos llevan su precio de lista y el "adic. ensayo" se define aparte en el ensayo.
            const omitirPrincipalAgrupador = esAgrupador && agrupadorTienePrecioDefinido && idsHijosAgrupador.length > 0;

            // principal (agrupador o componente) tal como está en el option
            if (!omitirPrincipalAgrupador && !componenteYaExiste(analisisId)) {
                // Usar la descripción del dataset para evitar el ID
                let descripcion = option.dataset.descripcion || option.textContent.replace(/^\[AGRUPADOR\]\s*/, '').replace(/\s*\(ID: \d+\)$/, '');
                const codigo = option.dataset.codigo || '';
                const metodoAnalisisId = (option.dataset.metodoAnalisisId || '').toString().trim(); // análisis → cotio_codigometodo_analisis
                const metodoCodigo = (option.dataset.metodoCodigo || '').toString().trim();         // muestreo → cotio_codigometodo
                const metodoDescripcion = option.dataset.metodoDescripcion || '';
                const unidadMedida = option.dataset.unidadMedida || '';
                const limiteDeteccion = option.dataset.limitesEstablecidos || '';
                const cantidad = 1;

                let precio = precioManual;
                if (!precio) {
                    precio = precioReferenciaDesdeOptionDataset(option.dataset);
                }

                const catPrincipal = catalogs.componentes.find(c => String(c.id) === String(analisisId));
                const notasPrincipal = notasParaNuevoComponenteDesdeModalYCatalogo(catPrincipal, seleccionUnicaAnalisis);
                const npPrincipal = notasAObjetoPersistencia(notasPrincipal);

                const esSugeridoPrincipal = ensayoRegistro && 
                                           Array.isArray(ensayoRegistro.componentes_sugeridos) && 
                                           ensayoRegistro.componentes_sugeridos.map(id => id.toString()).includes(analisisId.toString());
                const deAgrupadorPrincipal = resolverDeAgrupadorComponente(ensayoRegistro, analisisId, esSugeridoPrincipal);

                state.contador += 1;
                const nuevoComponente = normalizarComponente({
                    item: state.contador,
                    analisis_id: analisisId,
                    descripcion: descripcion,
                    codigo: codigo,
                    cantidad: cantidad,
                    precio: precio,
                    total: precio * cantidad,
                    ensayo_asociado: ensayoAsociado,
                    metodo_codigo: metodoCodigo,
                    metodo_descripcion: metodoDescripcion,
                    unidad_medida: unidadMedida,
                    limite_deteccion: limiteDeteccion,
                    metodo_analisis_id: metodoAnalisisId,
                    ley_normativa_id: leyNormativaId,
                    nota_tipo: npPrincipal.nota_tipo,
                    nota_contenido: npPrincipal.nota_contenido,
                    de_agrupador: deAgrupadorPrincipal,
                });

                state.componentes.push(nuevoComponente);
                agregados += 1;
            } else if (!omitirPrincipalAgrupador) {
                omitidos += 1;
            }

            if (esAgrupador) {
                agrupadoresAgregados += 1;
            }

            // asociados del agrupador, en el orden recibido (siempre precio de catálogo por analito)
            if (idsHijosAgrupador.length) {
                idsHijosAgrupador.forEach(id => {
                    const idStr = id.toString();
                    if (asociadosVistos.has(idStr)) return;
                    asociadosVistos.add(idStr);
                    agregarComponenteDesdeCatalogo(idStr, true, null);
                });
            }
        });

        if (!agregados) {
            if (window.Swal && omitidos) {
                Swal.fire({
                    icon: 'info',
                    title: 'Componentes ya agregados',
                    text: 'Los análisis seleccionados ya estaban asociados a este ensayo.',
                    timer: 2000,
                    showConfirmButton: false,
                });
            }
            return;
        }
 
        recalcularPreciosEnsayo(ensayoAsociado);
        renderTabla();
        cerrarModal(elements.modalComponente, 'formComponente');
 
        if (window.Swal) {
            const componentesPrincipales = agregados - componentesDeAgrupadoresAgregados;
            
            let mensaje = `${agregados} elemento${agregados > 1 ? 's' : ''} añadido${agregados > 1 ? 's' : ''} al ensayo.`;
            if (componentesDeAgrupadoresAgregados > 0 && agrupadoresAgregados > 0) {
                mensaje += ` (${componentesPrincipales} principal${componentesPrincipales !== 1 ? 'es' : ''} y ${componentesDeAgrupadoresAgregados} componente${componentesDeAgrupadoresAgregados > 1 ? 's' : ''} de agrupador${agrupadoresAgregados > 1 ? 'es' : ''})`;
            }
            if (omitidos) {
                mensaje += ` ${omitidos} ya estaban asociados.`;
            }

            Swal.fire({
                icon: 'success',
                title: 'Componentes agregados',
                text: mensaje,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
        }
    }

    function cerrarModal(modalElement, formId) {
        if (modalElement && window.bootstrap && window.bootstrap.Modal.getInstance(modalElement)) {
            window.bootstrap.Modal.getInstance(modalElement).hide();
        }

        const formulario = formId ? document.getElementById(formId) : null;
        if (formulario) {
            formulario.reset();
            if (formId === 'formComponente' && window.$ && window.$('#componente_analisis').length) {
                window.$('#componente_analisis').val(null).trigger('change');
            }
        }

        // Limpiar estado de error de cadena de custodia
        const groupAgregar = document.getElementById('custodia_group_agregar');
        const errorAgregar = document.getElementById('custodia_error_agregar');
        if (groupAgregar) groupAgregar.classList.remove('border-danger');
        if (errorAgregar) errorAgregar.classList.add('d-none');

        const groupEditar = document.getElementById('custodia_group_editar');
        const errorEditar = document.getElementById('custodia_error_editar');
        if (groupEditar) groupEditar.classList.remove('border-danger');
        if (errorEditar) errorEditar.classList.add('d-none');

        if (formId === 'formEnsayo') {
            limpiarAdjuntosModalAgregar();
        }
        if (formId === 'formEditarEnsayo') {
            const inputEdit = document.getElementById('edit_ensayo_adjuntos_input');
            if (inputEdit) {
                inputEdit.value = '';
            }
        }
    }

    function eliminarItem(tipo, itemId) {
        if (!state.puedeEditar) {
            return;
        }

        const ejecutarEliminacion = () => {
            if (tipo === 'ensayo') {
                state.ensayos = state.ensayos.filter(ensayo => ensayo.item !== itemId);
                state.componentes = state.componentes.filter(componente => componente.ensayo_asociado !== itemId);
            } else if (tipo === 'componente') {
                const componente = state.componentes.find(c => c.item === itemId);
                state.componentes = state.componentes.filter(c => c.item !== itemId);
                if (componente) {
                    recalcularPreciosEnsayo(componente.ensayo_asociado);
                }
            }

            renderTabla();
        };

        if (window.Swal) {
            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar item?',
                text: 'Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33',
            }).then(result => {
                if (result.isConfirmed) {
                    ejecutarEliminacion();
                    Swal.fire({
                        icon: 'success',
                        title: 'Item eliminado',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    });
                }
            });
        } else {
            if (confirm('¿Está seguro de que desea eliminar este item?')) {
                ejecutarEliminacion();
            }
        }
    }

    function renderTabla() {
         // console.log('[renderTabla] Iniciando renderizado de tabla');
         const tbody = elements.tablaItems;
         if (!tbody) {
             // console.warn('[renderTabla] ❌ No se encontró el elemento tablaItems');
             return;
         }
         
         // console.log('[renderTabla] Estado actual del state:', {
         //     ensayosCount: state.ensayos.length,
         //     componentesCount: state.componentes.length,
         //     totalItems: state.ensayos.length + state.componentes.length
         // });

         if (state.ensayos.length === 0) {
             // console.log('[renderTabla] No hay ensayos, mostrando mensaje vacío');
             tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No hay items agregados. Utilice los botones "Agregar Ensayo" o "Agregar Componente" para comenzar.
                    </td>
                </tr>
             `;
             actualizarTotalGeneral();
             actualizarEnsayosDisponiblesParaComponentes();
             sincronizarCheckboxGlobalPrioridad();
             return;
         }

         // console.log('[renderTabla] Hay', state.ensayos.length, 'ensayos y', state.componentes.length, 'componentes');
         sincronizarTotales();

         let html = '';
         const ensayosOrdenados = state.ensayos.slice().sort((a, b) => a.item - b.item);
         // console.log('[renderTabla] Ensayos ordenados:', ensayosOrdenados.map(e => ({ item: e.item, descripcion: e.descripcion })));

         // Numeración secuencial de ensayos (1, 2, 3...)
         let numeroEnsayoSecuencial = 0;
         
         ensayosOrdenados.forEach(ensayo => {
             numeroEnsayoSecuencial++;
             // console.log(`[renderTabla] Renderizando ensayo ${numeroEnsayoSecuencial}:`, { 
             //     item: ensayo.item, 
             //     descripcion: ensayo.descripcion 
             // });
             html += renderFilaEnsayo(ensayo, numeroEnsayoSecuencial);

             // Filtrar componentes asociados a este ensayo
             const todosLosComponentes = state.componentes;
             // console.log(`[renderTabla] Buscando componentes para ensayo ${ensayo.item}:`, {
             //     totalComponentes: todosLosComponentes.length,
             //     componentesConEnsayoAsociado: todosLosComponentes.map(c => ({
             //         item: c.item,
             //         descripcion: c.descripcion,
             //         ensayo_asociado: c.ensayo_asociado,
             //         coincide: c.ensayo_asociado === ensayo.item
             //     }))
             // });

             // Obtener componentes del ensayo manteniendo el orden del array (no ordenar por item)
             // Esto permite que el drag and drop funcione correctamente
             const componentesDelEnsayo = state.componentes
                 .filter(componente => {
                     const coincide = componente.ensayo_asociado === ensayo.item;
                     if (!coincide) {
                         // console.log(`[renderTabla] Componente ${componente.item} (${componente.descripcion}) NO coincide: ensayo_asociado=${componente.ensayo_asociado}, ensayo.item=${ensayo.item}`);
                     }
                     return coincide;
                 });
             // NO ordenar por item - mantener el orden del array para preservar el orden del drag and drop
             
             // console.log(`[renderTabla] Ensayo ${ensayo.item} tiene ${componentesDelEnsayo.length} componentes asociados`, {
             //     componentes: componentesDelEnsayo.map(c => ({ item: c.item, descripcion: c.descripcion }))
             // });

             componentesDelEnsayo.forEach((componente, index) => {
                 html += renderFilaComponente(componente, ensayo, index + 1, numeroEnsayoSecuencial);
             });
         });

         // console.log('[renderTabla] HTML generado, longitud:', html.length, 'caracteres');
         tbody.innerHTML = html;
         // console.log('[renderTabla] Tabla actualizada, filas en tbody:', tbody.children.length);
         
         // Inicializar botones de toggle después de renderizar
         inicializarTogglesComponentes();
         
         // Restaurar estado de colapso de ensayos
         state.ensayosColapsados.forEach(ensayoItem => {
             const componentesRows = document.querySelectorAll(`.componente-row-${ensayoItem}`);
             const toggleIcon = document.querySelector(`.toggle-icon[data-ensayo="${ensayoItem}"]`);
             const toggleButton = document.querySelector(`.toggle-componentes[data-ensayo="${ensayoItem}"]`);
             
             componentesRows.forEach(row => {
                 row.style.display = 'none';
                 row.classList.add('componente-oculto');
             });
             
             if (toggleIcon && toggleButton) {
                 toggleIcon.style.transform = 'rotate(-90deg)';
                 toggleButton.setAttribute('title', 'Mostrar componentes');
             }
         });
         
         // Inicializar drag and drop después de renderizar
         if (state.puedeEditar && typeof Sortable !== 'undefined') {
             inicializarDragAndDrop();
         }
         
         actualizarTotalGeneral();
         actualizarEnsayosDisponiblesParaComponentes();
         sincronizarCheckboxGlobalPrioridad();

         if (state.soloReferenciasFacturacion) {
             refrescarEdicionLimitadaEnTabla();
         }
     }

    function inicializarTogglesComponentes() {
        // Agregar event listeners a los botones de toggle
        document.querySelectorAll('.toggle-componentes').forEach(btn => {
            btn.addEventListener('click', function() {
                const ensayoItem = this.dataset.ensayo;
                toggleComponentesEnsayo(ensayoItem);
            });
        });
    }

    function toggleComponentesEnsayo(ensayoItem) {
        const componentesRows = document.querySelectorAll(`.componente-row-${ensayoItem}`);
        const toggleIcon = document.querySelector(`.toggle-icon[data-ensayo="${ensayoItem}"]`);
        const toggleButton = document.querySelector(`.toggle-componentes[data-ensayo="${ensayoItem}"]`);
        
        if (!componentesRows.length) {
            return;
        }

        // Verificar si están visibles (por defecto están visibles)
        const primerComponente = componentesRows[0];
        const estaOculto = primerComponente.style.display === 'none' || 
                          primerComponente.classList.contains('componente-oculto');

        componentesRows.forEach(row => {
            if (estaOculto) {
                // Mostrar componentes
                row.style.display = '';
                row.classList.remove('componente-oculto');
            } else {
                // Ocultar componentes
                row.style.display = 'none';
                row.classList.add('componente-oculto');
            }
        });

        // Guardar estado de colapso en el state
        if (estaOculto) {
            state.ensayosColapsados.delete(Number(ensayoItem));
        } else {
            state.ensayosColapsados.add(Number(ensayoItem));
        }

        // Rotar el icono y actualizar título
        if (toggleIcon && toggleButton) {
            if (estaOculto) {
                toggleIcon.style.transform = 'rotate(0deg)';
                toggleButton.setAttribute('title', 'Ocultar componentes');
            } else {
                toggleIcon.style.transform = 'rotate(-90deg)';
                toggleButton.setAttribute('title', 'Mostrar componentes');
            }
        }
    }

    // Variable para almacenar la instancia de Sortable
    let sortableInstance = null;

    function inicializarDragAndDrop() {
        if (!elements.tablaItems || typeof Sortable === 'undefined' || !state.puedeEditar) {
            return;
        }

        // Destruir instancia anterior si existe
        if (sortableInstance) {
            sortableInstance.destroy();
            sortableInstance = null;
        }

        // Crear un solo Sortable que maneje tanto ensayos como componentes
        sortableInstance = new Sortable(elements.tablaItems, {
            animation: 150,
            handle: '.drag-handle, .drag-handle-componente',
            draggable: '.sortable-ensayo, .sortable-componente',
            group: 'items',
            onMove: function(evt, originalEvent) {
                const dragged = evt.dragged;
                const related = evt.related;
                
                // Si se está arrastrando un ensayo
                if (dragged.classList.contains('sortable-ensayo')) {
                    // Solo permitir moverlo antes o después de otro ensayo
                    if (related && related.classList.contains('sortable-componente')) {
                        // Buscar el ensayo padre del componente relacionado
                        const ensayoRelacionado = parseInt(related.dataset.ensayo);
                        const filaEnsayoRelacionado = elements.tablaItems.querySelector(
                            `tr.sortable-ensayo[data-item="${ensayoRelacionado}"]`
                        );
                        if (filaEnsayoRelacionado) {
                            // Permitir insertar antes del ensayo relacionado
                            return evt.willInsertAfter ? false : true;
                        }
                        return false;
                    }
                    return true; // Permitir mover entre ensayos
                }
                
                // Si se está arrastrando un componente
                if (dragged.classList.contains('sortable-componente')) {
                    const draggedEnsayo = parseInt(dragged.dataset.ensayo);
                    
                    // Si el destino es un ensayo, verificar que sea el mismo ensayo
                    if (related && related.classList.contains('sortable-ensayo')) {
                        const relatedEnsayo = parseInt(related.dataset.item);
                        // Permitir mover solo si es el mismo ensayo (componente puede ir antes/después de su ensayo)
                        return draggedEnsayo === relatedEnsayo;
                    }
                    
                    // Si el destino es otro componente, verificar que sea del mismo ensayo
                    if (related && related.classList.contains('sortable-componente')) {
                        const relatedEnsayo = parseInt(related.dataset.ensayo);
                        // Permitir mover solo dentro del mismo ensayo
                        return draggedEnsayo === relatedEnsayo;
                    }
                    
                    // Si no hay elemento relacionado (por ejemplo, al final de la lista), permitir
                    if (!related) {
                        return true;
                    }
                    
                    return false;
                }
                
                return true;
            },
            onEnd: function(evt) {
                if (evt.oldIndex === evt.newIndex) return;
                
                const dragged = evt.item;
                const esEnsayo = dragged.classList.contains('sortable-ensayo');
                const esComponente = dragged.classList.contains('sortable-componente');
                
                if (esEnsayo) {
                    // Se movió un ensayo (y sus componentes se mueven automáticamente en el DOM)
                    const ensayoItem = parseInt(dragged.dataset.item);
                    
                    // Obtener todos los ensayos en el nuevo orden del DOM
                    const filasEnsayos = Array.from(elements.tablaItems.querySelectorAll('.sortable-ensayo'));
                    const nuevosItemsEnsayos = filasEnsayos.map(fila => parseInt(fila.dataset.item));

                    // Reordenar ensayos en el state según el nuevo orden
                    const ensayosOrdenados = [];
                    nuevosItemsEnsayos.forEach(itemId => {
                        const e = state.ensayos.find(ens => ens.item === itemId);
                        if (e) {
                            ensayosOrdenados.push(e);
                        }
                    });

                    // Actualizar el state con el nuevo orden
                    state.ensayos = ensayosOrdenados;
                    
                    // IMPORTANTE: Actualizar el orden de TODOS los componentes según el orden actual del DOM
                    // Esto es necesario porque cuando se arrastra un ensayo, los componentes se mueven en el DOM
                    // pero el state no refleja ese cambio automáticamente
                    const componentesOrdenados = [];
                    
                    // Recorrer los ensayos en el nuevo orden del DOM
                    nuevosItemsEnsayos.forEach(ensayoItemId => {
                        // Obtener todos los componentes de este ensayo en el orden actual del DOM
                        const filasComponentes = Array.from(elements.tablaItems.querySelectorAll(
                            `.componente-row-${ensayoItemId}`
                        ));
                        
                        // Agregar los componentes de este ensayo en el orden del DOM
                        filasComponentes.forEach(fila => {
                            const componenteItem = parseInt(fila.dataset.item);
                            const componente = state.componentes.find(c => c.item === componenteItem && c.ensayo_asociado === ensayoItemId);
                            if (componente) {
                                // Crear una copia del componente para evitar problemas de referencia
                                componentesOrdenados.push({...componente});
                            }
                        });
                    });
                    
                    // Actualizar el state con el nuevo orden de componentes
                    state.componentes = componentesOrdenados;
                    
                    // Re-renderizar para actualizar números secuenciales
                    renderTabla();
                } else if (esComponente) {
                    // Se movió un componente dentro de su ensayo
                    const ensayoItem = parseInt(dragged.dataset.ensayo);
                    
                    // Obtener todos los componentes de este ensayo en el nuevo orden del DOM
                    // IMPORTANTE: Obtener todos, incluso los ocultos, para preservar el orden completo
                    const filasComponentes = Array.from(elements.tablaItems.querySelectorAll(
                        `.componente-row-${ensayoItem}`
                    ));
                    
                    const nuevosItemsComponentes = filasComponentes.map(fila => parseInt(fila.dataset.item));

                    // Reordenar componentes en el state según el nuevo orden del DOM
                    const componentesOrdenados = [];
                    const otrosComponentes = state.componentes.filter(c => c.ensayo_asociado !== ensayoItem);
                    
                    // Mantener el orden de los componentes de otros ensayos según aparecen en el state
                    // (preservar su orden relativo)
                    otrosComponentes.forEach(comp => {
                        componentesOrdenados.push(comp);
                    });
                    
                    // Agregar componentes de este ensayo en el nuevo orden del DOM
                    nuevosItemsComponentes.forEach(itemId => {
                        const componente = state.componentes.find(c => c.item === itemId && c.ensayo_asociado === ensayoItem);
                        if (componente) {
                            // Crear una copia del componente para evitar problemas de referencia
                            componentesOrdenados.push({...componente});
                        }
                    });

                    // Actualizar el state con el nuevo orden
                    state.componentes = componentesOrdenados;
                    
                    // Re-renderizar para actualizar números secuenciales y mantener el nuevo orden
                    renderTabla();
                }
            }
        });
    }

    /** Sin muestreo en campo: etiqueta según canal (consultoría / ASP / Clarke Fire) o lab directo genérico. */
    function badgeFlujoEnsayoSinMuestreo(ensayo) {
        let c = ((ensayo && ensayo.canal_especial) ? String(ensayo.canal_especial) : '').trim().toLowerCase();
        if (!c && typeof canalEspecialDesdeMatrizYDescripcion === 'function') {
            c = canalEspecialDesdeMatrizYDescripcion(ensayo.matriz_descripcion, ensayo.descripcion) || '';
        }
        if (c === 'consultoria') {
            return '<span class="badge text-white ms-1" style="background-color:#0d6efd;" title="Consultoría (sin muestreo en campo)">Consultoría</span>';
        }
        if (c === 'asp') {
            return '<span class="badge bg-dark ms-1" title="ASP (sin muestreo en campo)">ASP</span>';
        }
        if (c === 'clarke_fire') {
            return '<span class="badge ms-1" style="background-color:#6f4e37;color:#fff;" title="Clarke Fire (sin muestreo en campo)">Clarke Fire</span>';
        }
        if (c === 'mediciones') {
            return '<span class="badge ms-1" style="background-color:#6f42c1;color:#fff;" title="Mediciones (sin muestreo en campo)">Mediciones</span>';
        }
        return '<span class="badge bg-secondary ms-1" title="Laboratorio directo (sin muestreo en campo)">Lab directo</span>';
    }

    function renderFilaEnsayo(ensayo, numeroSecuencial) {
        // Aumento global: importe fila ensayo = adic. ensayo × cantidad; en P. unit. solo el adicional u.m.
        const aumentoPercent = clampPercent(getAumentoGlobal());
        const factorAumento = 1 + (aumentoPercent / 100);
        const precioUnitAdicMostrado = precioAdicionalEnsayoPorUm(ensayo) * factorAumento;
        const totalConAumento = (parseFloat(ensayo.total) || 0) * factorAumento;
        const cantidadCampo = state.puedeEditar
            ? `<input type="number" class="form-control form-control-sm input-cantidad-ensayo" data-item="${ensayo.item}" value="${formatInt(ensayo.cantidad)}" min="1" step="1">`
            : `<span>${formatInt(ensayo.cantidad)}</span>`;

        const badgeCadena = ensayo.req_cadena_custodia === true
            ? '<span class="badge bg-info text-dark ms-1" title="Requiere Cadena de Custodia">Cadena</span>'
            : '';

        const badgePriori = ensayoEsPrioritarioEnState(ensayo)
            ? '<span class="badge bg-warning text-dark ms-1" title="Prioridad de muestreo">★ Prioridad</span>'
            : '';

        const existentesActivos = (ensayo.adjuntos_existentes || []).filter(function (adj) {
            return !(ensayo.adjuntos_eliminar || []).includes(adj.id);
        }).length;
        const totalAdjuntos = existentesActivos + (ensayo.adjuntos_pendientes || []).length;
        const badgeAdjuntos = totalAdjuntos > 0
            ? '<span class="badge bg-secondary ms-1" title="Archivos adjuntos"><i class="fas fa-paperclip"></i> ' + totalAdjuntos + '</span>'
            : '';

        const llevaMuestreo = (typeof ensayo.lleva_muestreo !== 'undefined') ? !!ensayo.lleva_muestreo : true;
        const canal = canalEspecialDesdeMatrizYDescripcion(ensayo.matriz_descripcion, ensayo.descripcion);
        
        let badgeMuestreo = '';
        if (llevaMuestreo) {
            if (canal === 'mediciones') {
                badgeMuestreo = '<span class="badge ms-1" style="background-color:#6f42c1;color:#fff;" title="Mediciones (lleva muestreo)">Mediciones</span>';
            } else {
                badgeMuestreo = '<span class="badge bg-success ms-1" title="Lleva muestreo">Muestreo</span>';
            }
        } else {
            badgeMuestreo = badgeFlujoEnsayoSinMuestreo(ensayo);
        }

        const componentesDelEnsayo = state.componentes.filter(c => c.ensayo_asociado === ensayo.item);
        const tieneComponentes = componentesDelEnsayo.length > 0;
        const iconoExpandir = tieneComponentes 
            ? `<button type="button" class="btn btn-sm btn-link p-0 toggle-componentes" data-ensayo="${ensayo.item}" title="Ocultar componentes" style="line-height: 1;">
                    <svg class="toggle-icon" data-ensayo="${ensayo.item}" style="width: 16px; height: 16px; transition: transform 0.3s ease;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>`
            : '<span style="display: inline-block; width: 16px;"></span>';

        const acciones = state.puedeEditar
            ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem('ensayo', ${ensayo.item})" title="Eliminar ensayo">
                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
               </button>`
            : '';

        const botonEditar = (state.puedeEditar || state.soloReferenciasFacturacion)
            ? `<button type="button" class="btn btn-sm btn-outline-info btn-editar-norma-item" onclick="editarEnsayo(${ensayo.item})" title="${state.soloReferenciasFacturacion ? 'Editar norma de comparación' : 'Editar ensayo'}">
                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
            </button>`
            : '';

        const dragHandle = state.puedeEditar 
            ? `<span class="drag-handle" style="cursor: move; display: inline-block; padding: 0 4px; color: #6c757d;" title="Arrastrar para reordenar">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
               </span>`
            : '';

        return `
            <tr data-tipo="ensayo" data-item="${ensayo.item}" class="sortable-ensayo" style="cursor: ${state.puedeEditar ? 'move' : 'default'};">
                <td>${dragHandle} ${iconoExpandir} ${numeroSecuencial}</td>
                <td>${escapeHtml(ensayo.codigo || '-')}</td>
                <td>${escapeHtml(ensayo.descripcion || '')} ${badgeCadena} ${badgeMuestreo} ${badgePriori} ${badgeAdjuntos}</td>
                <td>-</td>
                <td>${botonEditar}</td>
                <td>${cantidadCampo}</td>
                <td data-ensayo-unitario="${ensayo.item}" class="text-end cotizacion-col-precio">
                    <span class="cotizacion-precio-ensayo-valor">${formatCurrency(precioUnitAdicMostrado)}</span>
                    <small class="text-muted d-block cotizacion-leyenda-precio-ensayo">P. Unit. (total)</small>
                </td>
                <td data-ensayo-total="${ensayo.item}" class="text-end fw-semibold cotizacion-col-precio">${formatCurrency(totalConAumento)}</td>
                <td>${acciones}</td>
            </tr>
        `;
    }

    function renderFilaComponente(componente, ensayo, subindice, numeroEnsayoSecuencial) {
        const itemLabel = `${numeroEnsayoSecuencial}-${subindice}`;

        const cantidadEditable = state.puedeEditar && cantidadComponenteEditable(ensayo);
        const cantidadCampo = cantidadEditable
            ? `<input type="number" class="form-control form-control-sm input-cantidad-componente" data-item="${componente.item}" value="${formatInt(componente.cantidad)}" min="1" step="1" title="Cantidad del componente (Clarke Fire)">`
            : `<span>${formatInt(componente.cantidad)}</span>`;

        const aumentoPercent = clampPercent(getAumentoGlobal());
        const factorAumento = 1 + (aumentoPercent / 100);
        const esParametroPack = componenteEsParametroPackAgrupador(ensayo, componente);
        const esDeAgrupador = componente.de_agrupador === true;
        // Mostrar siempre el precio de catálogo/referencia; el importe en $0 solo si no suma al pack
        const precioMostrar = precioComponenteParaMostrar(componente);
        const totalMostrar = esParametroPack ? 0 : (parseFloat(componente.total) || 0);

        const precioConAumento = precioMostrar * factorAumento;
        const totalConAumento = totalMostrar * factorAumento;

        const precioCampo = state.puedeEditar
            ? `<input type="number" class="form-control form-control-sm input-precio-componente" data-item="${componente.item}" value="${formatNumber(precioConAumento)}" min="${formatNumber(0)}" step="0.01" ${esParametroPack ? 'readonly style="background-color: #f8f9fa;"' : ''}>`
            : `<span>${formatCurrency(precioConAumento)}</span>`;

        const acciones = state.puedeEditar
            ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem('componente', ${componente.item})">
                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
               </button>`
            : '';

        const botonVer = (state.puedeEditar || state.soloReferenciasFacturacion)
            ? `<button type="button" class="btn btn-sm btn-outline-info btn-editar-norma-item" onclick="verDetalle(${componente.item})" title="${state.soloReferenciasFacturacion ? 'Editar norma de comparación' : 'Ver detalle'}">
                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
            </button>`
            : '';

        // Indicador sutil si proviene de un agrupador
        const claseFila = esDeAgrupador ? 'componente-de-agrupador' : '';
        const indicadorAgrupador = esDeAgrupador 
            ? '<span class="badge badge-agrupador" title="Componente del agrupador">⊞</span>' 
            : '';

        const dragHandleComponente = state.puedeEditar 
            ? `<span class="drag-handle-componente" style="cursor: move; display: inline-block; padding: 0 4px; color: #6c757d;" title="Arrastrar para reordenar">
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                    </svg>
               </span>`
            : '';

        const badgeCadenaComp = componente.req_cadena_custodia === true
            ? '<span class="badge bg-info text-dark ms-1" title="Requiere Cadena de Custodia">Cadena</span>'
            : '';

        return `
            <tr data-tipo="componente" data-item="${componente.item}" data-ensayo="${ensayo.item}" class="sortable-componente componente-row componente-row-${ensayo.item} ${claseFila}" style="cursor: ${state.puedeEditar ? 'move' : 'default'};">
                <td>${dragHandleComponente} ${itemLabel}</td>
                <td>${escapeHtml(componente.codigo || '-')}</td>
                <td>${indicadorAgrupador} ${escapeHtml(componente.descripcion || '')} ${badgeCadenaComp}</td>
                <td><small>${escapeHtml(componente.metodo_descripcion || '-')}</small></td>
                <td>${botonVer}</td>
                <td>${cantidadCampo}</td>
                <td class="text-end cotizacion-col-precio">${precioCampo}</td>
                <td data-componente-total="${componente.item}" class="text-end cotizacion-col-precio">
                    <span class="cotizacion-total-componente-valor">${formatCurrency(totalConAumento)}</span>

                </td>
                <td>${acciones}</td>
            </tr>
        `;
    }

    function verDetalle(itemId) {
        // Solo funciona para componentes
        const componente = state.componentes.find(c => c.item === itemId);
        if (!componente) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'info',
                    title: 'Información',
                    text: 'El botón "Ver" solo está disponible para componentes (análisis).',
                });
            }
            return;
        }

        // Abrir modal de edición
        abrirModalEditarComponente(componente);
    }

    function textosNotasImprimibleInternaDesdeComponente(componente) {
        let imp = '';
        let intern = '';
        const lista = componente && componente.notas;
        if (Array.isArray(lista)) {
            lista.forEach(n => {
                if (n && n.tipo === 'imprimible' && n.contenido) {
                    imp = String(n.contenido);
                }
                if (n && n.tipo === 'interna' && n.contenido) {
                    intern = String(n.contenido);
                }
            });
        }
        return { imp, intern };
    }

    function abrirModalEditarComponente(componente) {
        const modal = document.getElementById('modalEditarComponente');
        if (!modal) {
            // console.error('Modal de edición no encontrado');
            return;
        }

        // Guardar ID del componente
        document.getElementById('edit_componente_item_id').value = componente.item;

        // Cargar opciones de análisis
        const selectAnalisis = document.getElementById('edit_componente_analisis');
        if (selectAnalisis) {
            selectAnalisis.innerHTML = '<option value="">Seleccionar análisis...</option>';
            let analisisSeleccionado = null;
            
            catalogs.componentes.forEach(comp => {
                const option = document.createElement('option');
                option.value = comp.id;
                option.textContent = `${comp.descripcion} (ID: ${comp.id})`;
                option.dataset.descripcion = comp.descripcion;
                option.dataset.codigo = comp.codigo || '';
                option.dataset.precio = comp.precio || '0';
                option.dataset.unidadMedida = comp.unidad_medida || '';
                option.dataset.metodoAnalisisId = (comp.metodo_analisis_id || '').toString().trim();
                option.dataset.metodoCodigo = (comp.metodo_codigo || '').toString().trim();
                option.dataset.metodoDescripcion = comp.metodo_descripcion || '';
                option.dataset.matrizCodigo = comp.matriz_codigo || '';
                option.dataset.matrizDescripcion = comp.matriz_descripcion || '';
                
                // Comparar usando conversión a string para evitar problemas de tipo
                const compIdStr = String(comp.id).trim();
                const analisisIdStr = String(componente.analisis_id || '').trim();
                
                if (compIdStr === analisisIdStr && analisisIdStr !== '') {
                    option.selected = true;
                    analisisSeleccionado = comp.id;
                }
                
                selectAnalisis.appendChild(option);
            });
            
            // Asegurarse de que el select tenga el valor correcto
            if (analisisSeleccionado !== null) {
                selectAnalisis.value = analisisSeleccionado;
            } else if (componente.analisis_id) {
                // Intentar establecer el valor directamente si no se encontró coincidencia
                selectAnalisis.value = String(componente.analisis_id);
            }
        }

        // Cargar métodos
        const selectMetodo = document.getElementById('edit_componente_metodo');
        if (selectMetodo) {
            selectMetodo.innerHTML = '<option value="">Seleccionar método...</option>';
            catalogs.metodosAnalisis.forEach(metodo => {
                const option = document.createElement('option');
                option.value = metodo.codigo;
                option.textContent = metodo.text;
                if (metodo.codigo == componente.metodo_analisis_id || metodo.codigo == componente.metodo_codigo) {
                    option.selected = true;
                }
                selectMetodo.appendChild(option);
            });
        }

        // Llenar campos
        document.getElementById('edit_componente_precio').value = formatNumber(componente.precio);
        document.getElementById('edit_componente_unidad').value = componente.unidad_medida || '';

        const ensayoPadre = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
        const wrapCantidad = document.getElementById('edit_componente_cantidad_wrap');
        const inputCantidad = document.getElementById('edit_componente_cantidad');
        if (wrapCantidad && inputCantidad) {
            const mostrarCantidad = cantidadComponenteEditable(ensayoPadre);
            wrapCantidad.style.display = mostrarCantidad ? '' : 'none';
            if (mostrarCantidad) {
                inputCantidad.value = formatInt(componente.cantidad);
            }
        }

        // Checkboxes de requerimientos
        const chkReqCadena = document.getElementById('edit_comp_req_cadena_custodia');
        if (chkReqCadena) {
            chkReqCadena.checked = !!componente.req_cadena_custodia;
        }
        const chkReqProt = document.getElementById('edit_comp_req_prot_mapba');
        if (chkReqProt) {
            chkReqProt.checked = !!componente.req_prot_mapba;
        }

        const tn = textosNotasImprimibleInternaDesdeComponente(componente);
        const impNotaEd = document.getElementById('edit_comp_nota_imprimible');
        const intNotaEd = document.getElementById('edit_comp_nota_interna');
        if (impNotaEd) {
            impNotaEd.value = tn.imp;
        }
        if (intNotaEd) {
            intNotaEd.value = tn.intern;
        }

        const selectLeyComp = document.getElementById('edit_componente_ley_normativa');
        if (selectLeyComp) {
            const leyesCatalogo = catalogs.leyesNormativas || catalogs.leyes;
            poblarSelectLeyesNormativas(selectLeyComp, leyesCatalogo);

            const leyGuardada = componente.ley_normativa_id ? String(componente.ley_normativa_id).trim() : '';
            if (leyGuardada) {
                selectLeyComp.value = leyGuardada;
                if (selectLeyComp.value !== leyGuardada) {
                    const optExtra = document.createElement('option');
                    optExtra.value = leyGuardada;
                    optExtra.textContent = leyGuardada;
                    selectLeyComp.appendChild(optExtra);
                    selectLeyComp.value = leyGuardada;
                }
            }

            inicializarSelectLeyNormativa(selectLeyComp, 'modalEditarComponente');
            if (leyGuardada && window.$) {
                window.$(selectLeyComp).val(leyGuardada).trigger('change');
            }
        }

        aplicarModoEdicionLimitadaModalComponente(modal);

        // Abrir modal
        if (window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = new window.bootstrap.Modal(modal);
            modalInstance.show();
        }
    }

    function editarEnsayo(itemId) {
        const ensayo = state.ensayos.find(e => e.item === itemId);
        if (!ensayo) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se encontró el ensayo a editar.',
                });
            }
            return;
        }

        abrirModalEditarEnsayo(ensayo);
    }

    function resolverMuestraIdEnsayo(ensayo) {
        if (!ensayo) {
            return null;
        }

        if (ensayo.muestra_id !== null && ensayo.muestra_id !== undefined && String(ensayo.muestra_id).trim() !== '') {
            return String(ensayo.muestra_id).trim();
        }

        const catalogo = catalogs.ensayos || [];
        const codigoRaw = ensayo.codigo ? String(ensayo.codigo).trim() : '';
        if (codigoRaw !== '') {
            const codNorm = codigoRaw.replace(/^0+/, '') || codigoRaw;
            const porCodigo = catalogo.find(function (ens) {
                return String(ens.id) === codNorm || String(ens.id) === codigoRaw;
            });
            if (porCodigo) {
                return String(porCodigo.id);
            }
        }

        if (ensayo.descripcion) {
            const desc = ensayo.descripcion.trim().toLowerCase();
            const porDesc = catalogo.find(function (ens) {
                return (ens.descripcion || '').trim().toLowerCase() === desc;
            });
            if (porDesc) {
                return String(porDesc.id);
            }
        }

        return null;
    }

    function crearOpcionEnsayoCatalogo(ens) {
        const option = document.createElement('option');
        option.value = ens.id;
        option.textContent = `${ens.descripcion} (ID: ${ens.id})`;
        option.dataset.descripcion = ens.descripcion || '';
        option.dataset.codigo = ens.codigo || '';
        option.dataset.componentes = JSON.stringify(Array.isArray(ens.componentes_default) ? ens.componentes_default : []);
        option.dataset.matrizCodigo = (ens.matriz_codigo || '').toString().trim();
        option.dataset.matrizDescripcion = ens.matriz_descripcion || '';
        const precioNum = parseFloat(ens.precio);
        const precioOk = Number.isFinite(precioNum) && precioNum >= 0;
        option.dataset.precio = precioOk ? precioNum.toFixed(2) : '';
        option.dataset.precioRaw = precioOk ? String(precioNum) : '0';
        return option;
    }

    function inicializarSelectEnsayoEditar(muestraIdSeleccionada) {
        const select = document.getElementById('edit_ensayo_muestra');
        if (!select || !window.$) {
            return;
        }

        const $select = window.$('#edit_ensayo_muestra');
        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: window.$('#modalEditarEnsayo'),
            width: '100%',
        });

        if (muestraIdSeleccionada) {
            $select.val(String(muestraIdSeleccionada)).trigger('change');
        }
    }

    function abrirModalEditarEnsayo(ensayo) {
        const modal = document.getElementById('modalEditarEnsayo');
        if (!modal) {
            // console.error('Modal de edición de ensayo no encontrado');
            return;
        }

        // Limpiar contenedor de notas antes de cargar
        const container = document.getElementById('notasEditEnsayoContainer');
        if (container) {
            container.innerHTML = '';
        }

        // Guardar ID del ensayo
        document.getElementById('edit_ensayo_item_id').value = ensayo.item;

        // Cargar opciones de muestras/ensayos
        const selectMuestra = document.getElementById('edit_ensayo_muestra');
        const muestraIdResuelto = resolverMuestraIdEnsayo(ensayo);
        if (muestraIdResuelto && !ensayo.muestra_id) {
            ensayo.muestra_id = muestraIdResuelto;
        }

        if (selectMuestra) {
            selectMuestra.innerHTML = '<option value="">Seleccionar muestra...</option>';

            if (catalogs.ensayos && Array.isArray(catalogs.ensayos)) {
                catalogs.ensayos.forEach(function (ens) {
                    const option = crearOpcionEnsayoCatalogo(ens);
                    if (muestraIdResuelto && String(ens.id) === String(muestraIdResuelto)) {
                        option.selected = true;
                    }
                    selectMuestra.appendChild(option);
                });
            } else if (elements.selectEnsayo && elements.selectEnsayo.options.length > 1) {
                for (let i = 1; i < elements.selectEnsayo.options.length; i++) {
                    const originalOption = elements.selectEnsayo.options[i];
                    const option = document.createElement('option');
                    option.value = originalOption.value;
                    option.textContent = originalOption.textContent;
                    option.dataset.descripcion = originalOption.dataset.descripcion || '';
                    option.dataset.codigo = originalOption.dataset.codigo || '';
                    option.dataset.componentes = originalOption.dataset.componentes || '[]';
                    option.dataset.matrizCodigo = originalOption.dataset.matrizCodigo || '';
                    option.dataset.matrizDescripcion = originalOption.dataset.matrizDescripcion || '';
                    option.dataset.precio = originalOption.dataset.precio || '';
                    option.dataset.precioRaw = originalOption.dataset.precioRaw || '0';

                    if (muestraIdResuelto && String(originalOption.value) === String(muestraIdResuelto)) {
                        option.selected = true;
                    }

                    selectMuestra.appendChild(option);
                }
            }

            if (muestraIdResuelto && !Array.from(selectMuestra.options).some(function (opt) {
                return String(opt.value) === String(muestraIdResuelto);
            })) {
                const optionFallback = document.createElement('option');
                optionFallback.value = muestraIdResuelto;
                optionFallback.textContent = (ensayo.descripcion || 'Ensayo') + ` (ID: ${muestraIdResuelto})`;
                optionFallback.dataset.descripcion = ensayo.descripcion || '';
                optionFallback.dataset.codigo = ensayo.codigo || '';
                optionFallback.dataset.componentes = JSON.stringify(ensayo.componentes_sugeridos || []);
                optionFallback.dataset.matrizCodigo = ensayo.matriz_codigo || '';
                optionFallback.dataset.matrizDescripcion = ensayo.matriz_descripcion || '';
                optionFallback.dataset.precio = '';
                optionFallback.dataset.precioRaw = String(Math.max(0, parseFloat(ensayo.precio_extra_ensayo) || 0));
                optionFallback.selected = true;
                selectMuestra.appendChild(optionFallback);
            }

            if (muestraIdResuelto) {
                selectMuestra.value = String(muestraIdResuelto);
            }

            inicializarSelectEnsayoEditar(muestraIdResuelto);
        }

        const editSelectMuestra = document.getElementById('edit_ensayo_muestra');
        if (editSelectMuestra && !editSelectMuestra.dataset.ensayoPrecioCatalogListener) {
            editSelectMuestra.dataset.ensayoPrecioCatalogListener = '1';
            editSelectMuestra.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                const inp = document.getElementById('edit_ensayo_precio_extra');
                if (!inp || !opt || !this.value) {
                    return;
                }
                const p = parseFloat(opt.dataset.precio || opt.dataset.precioRaw);
                if (!Number.isFinite(p)) {
                    return;
                }
                inp.value = formatNumber(Math.max(0, p));

                // Aplicar regla de muestreo y otras automáticas
                handleCambioEnsayoModal({ target: this });
            });
        }

        // Cargar leyes/normativas
        const selectLey = document.getElementById('edit_ensayo_ley_normativa');
        if (selectLey) {
            const leyesCatalogo = catalogs.leyesNormativas || catalogs.leyes;
            poblarSelectLeyesNormativas(selectLey, leyesCatalogo);

            const leyGuardada = ensayo.ley_normativa_id ? String(ensayo.ley_normativa_id).trim() : '';
            if (leyGuardada) {
                selectLey.value = leyGuardada;
                if (selectLey.value !== leyGuardada) {
                    const optExtra = document.createElement('option');
                    optExtra.value = leyGuardada;
                    optExtra.textContent = leyGuardada;
                    selectLey.appendChild(optExtra);
                    selectLey.value = leyGuardada;
                }
            }

            inicializarSelectLeyNormativa(selectLey, 'modalEditarEnsayo');
            if (leyGuardada && window.$) {
                window.$(selectLey).val(leyGuardada).trigger('change');
            }
        }

        // Llenar campos
        document.getElementById('edit_ensayo_codigo').value = ensayo.codigo || '';
        document.getElementById('edit_ensayo_cantidad').value = formatInt(ensayo.cantidad);

        const editPrecioExtra = document.getElementById('edit_ensayo_precio_extra');
        if (editPrecioExtra) {
            editPrecioExtra.value = formatNumber(Math.max(0, parseFloat(ensayo.precio_extra_ensayo) || 0));
        }

        // Checkbox "No lleva muestreo" (UI invertida respecto a lleva_muestreo en BD/state)
        const chkLlevaMuestreo = document.getElementById('edit_ensayo_lleva_muestreo');
        if (chkLlevaMuestreo) {
            // Por defecto true si no viene definido
            const valor = (typeof ensayo.lleva_muestreo !== 'undefined') ? !!ensayo.lleva_muestreo : true;
            // checked = "No lleva muestreo" => lleva_muestreo = false
            chkLlevaMuestreo.checked = !valor;
        }

        // Cadena de custodia: checkbox "NO requiere" (checked => req_cadena_custodia = false)
        const chkNoReqCadena = document.getElementById('edit_ensayo_no_requiere_cadena_custodia');
        if (chkNoReqCadena) {
            chkNoReqCadena.checked = !ensayo.req_cadena_custodia;
        }

        // Forzar actualización de reglas automáticas (p. ej. muestreo para Mediciones)
        if (editSelectMuestra) {
            handleCambioEnsayoModal({ target: editSelectMuestra });
        }
        const chkReqProt = document.getElementById('edit_ensayo_req_prot_mapba');
        if (chkReqProt) {
            chkReqProt.checked = !!ensayo.req_prot_mapba;
        }

        const chkPrioriEdit = document.getElementById('edit_ensayo_es_priori');
        if (chkPrioriEdit) {
            chkPrioriEdit.checked = !!ensayo.es_priori;
        }
        
        // Cargar múltiples notas
        let notasParaCargar = [];
        if (ensayo.notas && Array.isArray(ensayo.notas)) {
            // Si ya está como array, usarlo directamente
            notasParaCargar = ensayo.notas;
        } else if (ensayo.nota_contenido) {
            // Intentar parsear como JSON (si es múltiple)
            try {
                const notasParseadas = JSON.parse(ensayo.nota_contenido);
                if (Array.isArray(notasParseadas)) {
                    notasParaCargar = notasParseadas;
                } else if (notasParseadas && typeof notasParseadas === 'object') {
                    notasParaCargar = [notasParseadas];
                } else {
                    notasParaCargar = [{
                        tipo: ensayo.nota_tipo || 'imprimible',
                        contenido: ensayo.nota_contenido
                    }];
                }
            } catch (e) {
                // Si no es JSON válido, es una nota simple (formato antiguo)
                notasParaCargar = [{
                    tipo: ensayo.nota_tipo || 'imprimible',
                    contenido: ensayo.nota_contenido
                }];
            }
        }
        
        // Cargar notas en el contenedor
        cargarNotasEnContenedor('notasEditEnsayoContainer', notasParaCargar);

        ensayo.adjuntos_existentes = ensayo.adjuntos_existentes || ensayo.adjuntos || [];
        ensayo.adjuntos_pendientes = ensayo.adjuntos_pendientes || [];
        ensayo.adjuntos_eliminar = ensayo.adjuntos_eliminar || [];
        renderEnsayoAdjuntosEnModal('editEnsayoAdjuntosLista', ensayo);

        syncCotiReqCadenaRelCheckboxesFromHidden();

        aplicarModoEdicionLimitadaModalEnsayo(modal);

        // Abrir modal
        if (window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = new window.bootstrap.Modal(modal);
            modalInstance.show();
        }
    }

    function habilitarSelect2LeyNormativa(selectId) {
        const selectEl = document.getElementById(selectId);
        if (!selectEl || !window.$) {
            return;
        }

        const $select = window.$(selectEl);
        $select.prop('disabled', false);
        const $container = $select.next('.select2-container');
        if ($container.length) {
            $container.removeClass('select2-container--disabled');
            $container.find('.select2-selection').attr('aria-disabled', 'false');
        }
    }

    function aplicarModoEdicionLimitadaModalEnsayo(modal) {
        if (!modal || !state.soloReferenciasFacturacion) {
            return;
        }

        modal.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (el.id === 'edit_ensayo_ley_normativa') {
                el.disabled = false;
                return;
            }
            if (el.id === 'btnGuardarEnsayoEditado') {
                el.disabled = false;
                return;
            }
            if (el.classList.contains('btn-close') || el.getAttribute('data-bs-dismiss') === 'modal') {
                el.disabled = false;
                return;
            }
            el.disabled = true;
        });

        habilitarSelect2LeyNormativa('edit_ensayo_ley_normativa');

        const titulo = modal.querySelector('.modal-title');
        if (titulo) {
            titulo.textContent = 'Norma de comparación del ítem';
        }
    }

    function aplicarModoEdicionLimitadaModalComponente(modal) {
        if (!modal || !state.soloReferenciasFacturacion) {
            return;
        }

        modal.querySelectorAll('input, select, textarea, button').forEach(function (el) {
            if (el.id === 'edit_componente_ley_normativa') {
                el.disabled = false;
                return;
            }
            if (el.id === 'btnGuardarComponenteEditado') {
                el.disabled = false;
                return;
            }
            if (el.classList.contains('btn-close') || el.getAttribute('data-bs-dismiss') === 'modal') {
                el.disabled = false;
                return;
            }
            el.disabled = true;
        });

        habilitarSelect2LeyNormativa('edit_componente_ley_normativa');

        const titulo = modal.querySelector('.modal-title');
        if (titulo) {
            titulo.textContent = 'Norma de comparación del análisis';
        }
    }

    function guardarEnsayoEditadoHandler() {
        if (!state.puedeEditar && !state.soloReferenciasFacturacion) {
            return;
        }

        const itemId = Number(document.getElementById('edit_ensayo_item_id').value);
        if (!itemId) {
            return;
        }

        const ensayo = state.ensayos.find(e => e.item === itemId);
        if (!ensayo) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se encontró el ensayo a editar.',
                });
            }
            return;
        }

        if (state.soloReferenciasFacturacion) {
            const selectLeyEdit = document.getElementById('edit_ensayo_ley_normativa');
            if (selectLeyEdit) {
                const lc = selectLeyEdit.value ? String(selectLeyEdit.value).trim() : '';
                ensayo.ley_normativa_id = lc || null;
            }
            renderTabla();

            const modalLimitado = document.getElementById('modalEditarEnsayo');
            if (modalLimitado && window.bootstrap && window.bootstrap.Modal) {
                const modalInstance = window.bootstrap.Modal.getInstance(modalLimitado);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
            return;
        }

        // Obtener valores del formulario
        const selectMuestra = document.getElementById('edit_ensayo_muestra');
        const muestraId = selectMuestra ? selectMuestra.value : null;
        const option = selectMuestra && selectMuestra.selectedIndex >= 0 
            ? selectMuestra.options[selectMuestra.selectedIndex] 
            : null;

        if (!muestraId || !option) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validación',
                    text: 'Debe seleccionar una muestra/ensayo.',
                });
            }
            return;
        }

        // Validar que al menos una opción de cadena de custodia esté seleccionada
        const _chkNoCustEdit = document.getElementById('edit_ensayo_no_requiere_cadena_custodia');
        const _chkMapbaEdit = document.getElementById('edit_ensayo_req_prot_mapba');
        const _chkRelEdit2 = document.getElementById('edit_ensayo_chk_req_cadena_relacionada');
        const _algunoSeleccionadoEdit = (_chkNoCustEdit && _chkNoCustEdit.checked)
            || (_chkMapbaEdit && _chkMapbaEdit.checked)
            || (_chkRelEdit2 && _chkRelEdit2.checked);

        const _groupEditar = document.getElementById('custodia_group_editar');
        const _errorEditar = document.getElementById('custodia_error_editar');

        if (!_algunoSeleccionadoEdit) {
            if (_groupEditar) _groupEditar.classList.add('border-danger');
            if (_errorEditar) _errorEditar.classList.remove('d-none');
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campo requerido',
                    text: 'Debe seleccionar al menos una opción de cadena de custodia.',
                });
            }
            return;
        }
        if (_groupEditar) _groupEditar.classList.remove('border-danger');
        if (_errorEditar) _errorEditar.classList.add('d-none');

        const cantidad = toPositiveInt(document.getElementById('edit_ensayo_cantidad').value, 1);
        const editPe = document.getElementById('edit_ensayo_precio_extra');
        if (editPe) {
            ensayo.precio_extra_ensayo = Math.max(0, parseFloat(editPe.value) || 0);
        }
        
        const chkNoReqCadena = document.getElementById('edit_ensayo_no_requiere_cadena_custodia');
        const chkReqProt = document.getElementById('edit_ensayo_req_prot_mapba');
        const chkLlevaMuestreo = document.getElementById('edit_ensayo_lleva_muestreo');

        // Obtener múltiples notas del contenedor
        const notas = obtenerNotasDelContenedor('notasEditEnsayoContainer');
        
        // Para compatibilidad con el backend, guardar como JSON en nota_contenido
        // y el primer tipo en nota_tipo (o null si no hay notas)
        const notaTipo = notas.length > 0 ? notas[0].tipo : null;
        const notaContenido = notas.length > 0 ? JSON.stringify(notas) : null;

        // Actualizar ensayo
        ensayo.muestra_id = muestraId;
        ensayo.descripcion = option.dataset.descripcion || option.textContent.replace(/\s*\(ID: \d+\)$/, '') || ensayo.descripcion;
        ensayo.codigo = option.dataset.codigo || ensayo.codigo;
        ensayo.cantidad = cantidad;
        ensayo.nota_tipo = notaTipo;
        ensayo.nota_contenido = notaContenido;
        ensayo.notas = notas; // Guardar también como array para uso interno

        // Actualizar requerimientos (checkbox "NO requiere cadena de custodia")
        if (chkNoReqCadena) {
            ensayo.req_cadena_custodia = !chkNoReqCadena.checked;
        }
        if (chkReqProt) {
            ensayo.req_prot_mapba = !!chkReqProt.checked;
        }

        const matrizDescEdit = option.dataset.matrizDescripcion || ensayo.matriz_descripcion || '';
        const descripcionEdit = option.dataset.descripcion || ensayo.descripcion || '';
        const canalDetectadoEdit = canalEspecialDesdeMatrizYDescripcion(matrizDescEdit, descripcionEdit)
            || ensayo.canal_especial
            || null;
        ensayo.canal_especial = canalDetectadoEdit;
        ensayo.lleva_muestreo = resolverLlevaMuestreoEnsayo(chkLlevaMuestreo, canalDetectadoEdit);

        const chkPrioriEditSave = document.getElementById('edit_ensayo_es_priori');
        ensayo.es_priori = !!(chkPrioriEditSave && chkPrioriEditSave.checked);
        sincronizarCheckboxGlobalPrioridad();

        const chkRelEdit = document.getElementById('edit_ensayo_chk_req_cadena_relacionada');
        if (chkRelEdit) {
            setCotiReqCadenaRelHidden(!!chkRelEdit.checked);
            syncCotiReqCadenaRelCheckboxesFromHidden();
        }

        const selectLeyEdit = document.getElementById('edit_ensayo_ley_normativa');
        if (selectLeyEdit) {
            const lc = selectLeyEdit.value ? String(selectLeyEdit.value).trim() : '';
            ensayo.ley_normativa_id = lc || null;
        }
        
        // Actualizar matriz_codigo y matriz_descripcion si están disponibles
        if (option.dataset.matrizCodigo) {
            ensayo.matriz_codigo = option.dataset.matrizCodigo.trim();
        }
        if (option.dataset.matrizDescripcion) {
            ensayo.matriz_descripcion = option.dataset.matrizDescripcion;
        }

        // Actualizar componentes sugeridos si cambió la muestra
        if (option.dataset.componentes) {
            try {
                ensayo.componentes_sugeridos = JSON.parse(option.dataset.componentes);
            } catch (e) {
                // console.error('Error parseando componentes sugeridos:', e);
            }
        }

        capturarAdjuntosDesdeInput('edit_ensayo_adjuntos_input', ensayo, 'editEnsayoAdjuntosLista');

        // Recalcular precios
        recalcularPreciosEnsayo(ensayo.item);
        renderTabla();

        // Cerrar modal
        const modal = document.getElementById('modalEditarEnsayo');
        if (modal && window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = window.bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Ensayo actualizado',
                text: 'Los cambios se han guardado correctamente.',
                timer: 1500,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
            });
        }
    }

    function ensayoTienePrecioPackAgrupador(ensayo) {
        if (!ensayo) {
            return false;
        }
        const extra = Math.max(0, parseFloat(ensayo.precio_extra_ensayo) || 0);
        if (extra <= 0) {
            return false;
        }
        return obtenerComponentesSugeridosDeEnsayo(ensayo).length > 0;
    }

    function componenteEsParametroPackAgrupador(ensayo, componente) {
        if (!ensayo || !componente) {
            return false;
        }
        if (componente.de_agrupador === true) {
            return true;
        }
        if (!ensayoTienePrecioPackAgrupador(ensayo)) {
            return false;
        }
        const analisisId = String(componente.analisis_id ?? '').trim();
        if (!analisisId) {
            return false;
        }
        const sugeridos = obtenerComponentesSugeridosDeEnsayo(ensayo).map(id => String(id));
        return sugeridos.includes(analisisId);
    }

    function resolverDeAgrupadorComponente(ensayo, analisisId, deAgrupadorActual) {
        if (deAgrupadorActual === true) {
            return true;
        }
        if (!ensayo || analisisId === null || analisisId === undefined || String(analisisId).trim() === '') {
            return false;
        }
        return componenteEsParametroPackAgrupador(ensayo, {
            analisis_id: analisisId,
            de_agrupador: false,
        });
    }

    function buscarCatalogoComponente(componente) {
        if (!catalogs.componentes || !catalogs.componentes.length || !componente) {
            return null;
        }

        const codigosGenericos = new Set(['000010000100006', '000010000000000']);
        const descripcion = String(componente.descripcion || '').trim().toLowerCase();
        if (descripcion) {
            const porDescripcion = catalogs.componentes.find(c =>
                String(c.descripcion || '').trim().toLowerCase() === descripcion
            );
            if (porDescripcion) {
                return porDescripcion;
            }
        }

        if (componente.analisis_id) {
            const porId = catalogs.componentes.find(c => String(c.id) === String(componente.analisis_id));
            if (porId) {
                return porId;
            }
        }

        const codigo = String(componente.codigo || '').trim();
        if (codigo && !codigosGenericos.has(codigo)) {
            const codSinCeros = codigo.replace(/^0+/, '') || '0';
            const porCodigo = catalogs.componentes.find(c =>
                String(c.id) === codigo
                || String(c.id) === codSinCeros
                || String(c.codigo || '').trim() === codigo
            );
            if (porCodigo) {
                return porCodigo;
            }
        }

        return null;
    }

    function catalogoTienePrecioDefinido(item) {
        if (!item) {
            return false;
        }
        return item.precio_definido === true
            || item.precio_definido === 1
            || item.precio_definido === '1';
    }

    function precioMinimoVentaDesdeCatalogo(item) {
        if (!item) {
            return 5000;
        }
        const min = parseFloat(item.precio_minimo_venta);
        if (Number.isFinite(min) && min > 0) {
            return min;
        }
        if (catalogoTienePrecioDefinido(item)) {
            const p = parseFloat(item.precio);
            return Number.isFinite(p) && p > 0 ? p : 5000;
        }
        return 5000;
    }

    function precioReferenciaDesdeCatalogo(item) {
        if (!catalogoTienePrecioDefinido(item)) {
            return 0;
        }
        const precio = parseFloat(item && item.precio);
        return Number.isFinite(precio) && precio >= 0 ? precio : 0;
    }

    function precioReferenciaDesdeOptionDataset(dataset) {
        if (!dataset || dataset.precioDefinido !== '1') {
            return 0;
        }
        const precio = parseFloat(dataset.precio ?? dataset.precioRaw ?? '0') || 0;
        return precio >= 0 ? precio : 0;
    }

    function precioComponenteParaMostrar(componente) {
        const precioGuardado = parseFloat(componente.precio) || 0;
        if (precioGuardado > 0) {
            return precioGuardado;
        }
        const cat = buscarCatalogoComponente(componente);
        if (cat) {
            return precioReferenciaDesdeCatalogo(cat);
        }
        return 0;
    }

    function asegurarPreciosReferenciaComponentes() {
        state.componentes.forEach(componente => {
            const cat = buscarCatalogoComponente(componente);

            if (cat && !componente.analisis_id) {
                componente.analisis_id = cat.id;
            }

            componente.precio_minimo_venta = precioMinimoVentaDesdeCatalogo(cat);

            const precioActual = parseFloat(componente.precio) || 0;
            const cantidad = parseFloat(componente.cantidad) || 1;

            // Precio persistido en cotio (edición): no sobrescribir con catálogo
            if (precioActual > 0) {
                componente.total = precioActual * cantidad;
                return;
            }

            if (cat && catalogoTienePrecioDefinido(cat)) {
                const precioCat = precioReferenciaDesdeCatalogo(cat);
                if (precioCat > 0) {
                    componente.precio = precioCat;
                    componente.total = precioCat * cantidad;
                }
            }
        });
    }

    function asegurarDeAgrupadorEnComponentes() {
        state.componentes.forEach(componente => {
            if (!componente.ensayo_asociado) {
                return;
            }
            const ensayo = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
            if (!ensayo) {
                return;
            }
            if (resolverDeAgrupadorComponente(ensayo, componente.analisis_id, componente.de_agrupador)) {
                componente.de_agrupador = true;
            }
        });
    }

    function sincronizarTotales() {
        asegurarPreciosReferenciaComponentes();
        asegurarDeAgrupadorEnComponentes();
        state.ensayos.forEach(ensayo => {
            recalcularPreciosEnsayo(ensayo.item);
        });
    }

    function recalcularPreciosEnsayo(ensayoItemId) {
        const ensayo = state.ensayos.find(e => e.item === ensayoItemId);
        if (!ensayo) {
            return;
        }

        const extraEnsayo = Math.max(0, parseFloat(ensayo.precio_extra_ensayo) || 0);
        const cantidadEnsayo = toPositiveInt(ensayo.cantidad, 1);
        
        // Sumar componentes asociados para mostrar el total consolidado en la fila del ensayo
        const componentes = state.componentes.filter(c => c.ensayo_asociado === ensayoItemId);
        const sumaComponentesUnitaria = componentes.reduce((suma, c) => {
            // Parámetros del agrupador con precio pack: no sumar (ya incluidos en precio_extra_ensayo)
            if (componenteEsParametroPackAgrupador(ensayo, c)) {
                return suma;
            }
            const p = parseFloat(c.precio) || 0;
            const cant = parseFloat(c.cantidad) || 1;
            return suma + (p * (cant <= 0 ? 1 : cant));
        }, 0);

        // Fila ensayo: P. unit. = consolidado (extra + componentes); importe = consolidado × cant. muestras.
        ensayo.precio = extraEnsayo + sumaComponentesUnitaria;
        ensayo.total = (extraEnsayo + sumaComponentesUnitaria) * cantidadEnsayo;
    }

    /** Solo el adicional del ensayo por u.m. (no incluye analitos); la UI lo muestra en P. unit. de la fila ensayo. */
    function precioAdicionalEnsayoPorUm(ensayo) {
        if (!ensayo) {
            return 0;
        }
        return parseFloat(ensayo.precio) || 0;
    }

    function actualizarTotalGeneral() {
        // Total base: importe filas ensayo (consolidado) + importe de componentes SUELTOS únicamente
        const totalEnsayos = state.ensayos.reduce((suma, ensayo) => suma + (parseFloat(ensayo.total) || 0), 0);
        const totalComponentesSueltos = state.componentes
            .filter(c => !c.ensayo_asociado)
            .reduce((suma, c) => suma + (parseFloat(c.total) || 0), 0);
        
        const totalBase = totalEnsayos + totalComponentesSueltos;

        // Aumento global: se aplica sobre cada ítem, por lo que el total mostrado
        // ya incluye el aumento
        const aumentoPercent = clampPercent(getAumentoGlobal());
        const aumentoDecimal = aumentoPercent / 100;
        const totalConAumento = totalBase * (1 + aumentoDecimal);

        if (elements.totalGeneral) {
            elements.totalGeneral.textContent = formatNumber(totalConAumento, 2);
        }

        // Descuento global: se aplica sobre el total ya aumentado
        const descuentoPercent = clampPercent(getDescuentoGlobal());
        const descuentoDecimal = descuentoPercent / 100;
        const descuentoGlobalMonto = totalConAumento * descuentoDecimal;

        const totalFinal = totalConAumento - descuentoGlobalMonto;

        // No mostramos visualmente el aumento global, solo el descuento y el total final
        if (elements.descuentoGlobalPorcentaje) {
            elements.descuentoGlobalPorcentaje.textContent = `${formatNumber(descuentoPercent, 2)}%`;
        }
        if (elements.descuentoGlobalMonto) {
            elements.descuentoGlobalMonto.textContent = formatNumber(descuentoGlobalMonto, 2);
        }
        if (elements.totalConAjustes) {
            elements.totalConAjustes.textContent = formatNumber(totalFinal, 2);
        }

        // Auto-sincronizar monto total de cuotas cuando el panel está visible
        const cuotasPanel = document.getElementById('cuotasPanel');
        if (cuotasPanel && !cuotasPanel.classList.contains('d-none')) {
            const montoTotalInput = document.getElementById('coti_cuota_monto_total');
            if (montoTotalInput) {
                montoTotalInput.value = totalFinal.toFixed(2);
                // Disparar recalculo de monto individual
                montoTotalInput.dispatchEvent(new Event('input'));
            }
        }
    }

    function getDescuentoCliente() {
        return getDescuentoGlobal();
    }

    function getDescuentoGlobal() {
        // Primero intentar leer del campo del formulario
        const descuentoInput = document.getElementById('descuento');
        if (descuentoInput) {
            const valor = parseFloat(descuentoInput.value);
            if (!isNaN(valor)) {
                return clampPercent(valor);
            }
        }
        // Si no está disponible, leer del hidden field
        return getHiddenDatasetNumber('descuentoGlobal');
    }

    function getAumentoGlobal() {
        const aumentoInput = document.getElementById('aumento');
        if (aumentoInput) {
            const valor = parseFloat(aumentoInput.value);
            if (!isNaN(valor)) {
                return clampPercent(valor);
            }
        }
        return 0;
    }


    function getSectorEtiqueta() {
        if (!elements.descuentoHidden) {
            return obtenerSectorEtiqueta();
        }
        const etiqueta = elements.descuentoHidden.dataset.sectorEtiqueta;
        if (etiqueta && etiqueta.trim() !== '') {
            return etiqueta.trim();
        }
        return obtenerSectorEtiqueta();
    }

    function getHiddenDatasetNumber(attribute) {
        if (!elements.descuentoHidden) {
            return 0;
        }
        const valor = parseFloat(elements.descuentoHidden.dataset[attribute] ?? elements.descuentoHidden.value ?? '0');
        return isNaN(valor) ? 0 : valor;
    }

    function clampPercent(valor) {
        const numero = parseFloat(valor);
        if (isNaN(numero)) {
            return 0;
        }
        if (numero < 0) {
            return 0;
        }
        if (numero > 100) {
            return 100;
        }
        return numero;
    }

    function toPositiveInt(value, fallback) {
        const numero = Number(value);
        if (isNaN(numero) || numero < 1) {
            return fallback;
        }
        return Math.round(numero);
    }

    function toPositiveNumber(value, fallback) {
        const numero = parseFloat(value);
        if (isNaN(numero) || numero <= 0) {
            return fallback;
        }
        return numero;
    }

    function normalizarEnsayo(raw) {
        const item = Number(raw.item) || 0;
        const cantidad = toPositiveInt(raw.cantidad, 1);
        const precio = parseFloat(raw.precio) || 0;
        const total = parseFloat(raw.total) || precio * cantidad;
        const componentesSugeridos = Array.isArray(raw.componentes_sugeridos)
            ? raw.componentes_sugeridos.map(id => id.toString())
            : [];

        // Manejar notas: si viene como array, usarlo; si viene como JSON string, parsearlo; si viene como string simple, crear array
        let notas = null;
        if (raw.notas && Array.isArray(raw.notas)) {
            notas = raw.notas;
        } else if (raw.nota_contenido) {
            try {
                const parsed = JSON.parse(raw.nota_contenido);
                if (Array.isArray(parsed)) {
                    notas = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    notas = [parsed];
                } else {
                    notas = raw.nota_tipo ? [{ tipo: raw.nota_tipo, contenido: raw.nota_contenido }] : null;
                }
            } catch (e) {
                // No es JSON, es texto plano (legacy)
                notas = raw.nota_contenido
                    ? [{ tipo: raw.nota_tipo || 'imprimible', contenido: raw.nota_contenido }]
                    : null;
            }
        }

        if (notas && notas.length > 0) {
            notas = aplicarLimiteNotasItem(notas);
            if (notas.length === 0) {
                notas = null;
            }
        }

        // req_cadena_custodia:
        //  - si viene explícito, usarlo
        //  - si no, inferir desde no_requiere_custodia (dato histórico)
        //  - si no hay datos históricos, por defecto = false (no marcar)
        let reqCadena = false;
        if (typeof raw.req_cadena_custodia !== 'undefined') {
            reqCadena = !!raw.req_cadena_custodia;
        } else if (typeof raw.no_requiere_custodia !== 'undefined') {
            reqCadena = !(raw.no_requiere_custodia === true || raw.no_requiere_custodia === 1 || raw.no_requiere_custodia === '1');
        }

        const reqProtMapba = !!raw.req_prot_mapba;

        // Precio adicional del ensayo (por unidad), aparte de la suma de componentes
        let precioExtraEnsayo = 0;
        if (typeof raw.precio_extra_ensayo !== 'undefined' && raw.precio_extra_ensayo !== null && raw.precio_extra_ensayo !== '') {
            const pe = parseFloat(raw.precio_extra_ensayo);
            precioExtraEnsayo = !isNaN(pe) && pe > 0 ? pe : 0;
        }

        // Lleva muestreo:
        //  - si viene explícito, usarlo
        //  - si no, por defecto true (se puede recalcular al crear el ensayo nuevo)
        let llevaMuestreo = true;
        if (typeof raw.lleva_muestreo !== 'undefined') {
            llevaMuestreo = !!raw.lleva_muestreo;
        }

        const canalEsp = raw.canal_especial || canalEspecialDesdeMatrizYDescripcion(raw.matriz_descripcion, raw.descripcion);
        if (canalEsp && canalEsp !== 'mediciones') {
            llevaMuestreo = false;
        }

        const leyRaw = raw.ley_normativa_id ?? raw.ley_normativa ?? null;
        const leyNormativaId = (leyRaw !== null && leyRaw !== undefined && String(leyRaw).trim() !== '')
            ? String(leyRaw).trim()
            : null;

        return {
            item: item,
            muestra_id: raw.muestra_id || null,
            descripcion: raw.descripcion || '',
            codigo: raw.codigo || '',
            cantidad: cantidad,
            precio: precio,
            total: total,
            tipo: 'ensayo',
            componentes_sugeridos: componentesSugeridos,
            nota_tipo: notas && notas.length > 0 ? notas[0].tipo : null,
            nota_contenido: notas && notas.length > 0 ? JSON.stringify(notas) : null,
            notas: notas, // Guardar como array para uso interno
            matriz_codigo: raw.matriz_codigo ? raw.matriz_codigo.toString().trim() : null,
            matriz_descripcion: raw.matriz_descripcion || null,
            canal_especial: canalEsp || null,
            req_cadena_custodia: reqCadena,
            req_prot_mapba: reqProtMapba,
            lleva_muestreo: llevaMuestreo,
            precio_extra_ensayo: precioExtraEnsayo,
            ley_normativa_id: leyNormativaId,
            es_priori: !!raw.es_priori,
            adjuntos_pendientes: Array.isArray(raw.adjuntos_pendientes) ? raw.adjuntos_pendientes : [],
            adjuntos_existentes: Array.isArray(raw.adjuntos_existentes)
                ? raw.adjuntos_existentes
                : (Array.isArray(raw.adjuntos) ? raw.adjuntos : []),
            adjuntos_eliminar: Array.isArray(raw.adjuntos_eliminar) ? raw.adjuntos_eliminar : [],
        };
    }

    function normalizarComponente(raw) {
        const item = Number(raw.item) || 0;
        const cantidad = toPositiveInt(raw.cantidad, 1);
        const precio = parseFloat(raw.precio) || 0;
        const total = parseFloat(raw.total) || precio * cantidad;
        const precioMinimoVenta = (raw.precio_minimo_venta !== null && raw.precio_minimo_venta !== undefined)
            ? (parseFloat(raw.precio_minimo_venta) || 0)
            : precio;

        // req_cadena_custodia:
        //  - si viene explícito, usarlo
        //  - si no, inferir desde no_requiere_custodia (dato histórico)
        //  - si no hay datos históricos, por defecto = false (no marcar)
        let reqCadena = false;
        if (typeof raw.req_cadena_custodia !== 'undefined') {
            reqCadena = !!raw.req_cadena_custodia;
        } else if (typeof raw.no_requiere_custodia !== 'undefined') {
            reqCadena = !(raw.no_requiere_custodia === true || raw.no_requiere_custodia === 1 || raw.no_requiere_custodia === '1');
        }

        const reqProtMapba = !!raw.req_prot_mapba;

        let notasComp = null;
        if (raw.notas && Array.isArray(raw.notas)) {
            notasComp = raw.notas;
        } else if (raw.nota_contenido) {
            try {
                const parsed = JSON.parse(raw.nota_contenido);
                if (Array.isArray(parsed)) {
                    notasComp = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    notasComp = [parsed];
                } else {
                    notasComp = raw.nota_tipo ? [{ tipo: raw.nota_tipo, contenido: raw.nota_contenido }] : null;
                }
            } catch (e) {
                notasComp = raw.nota_contenido
                    ? [{ tipo: raw.nota_tipo || 'imprimible', contenido: raw.nota_contenido }]
                    : null;
            }
        }

        if (notasComp && notasComp.length > 0) {
            notasComp = aplicarLimiteNotasItem(notasComp);
            if (notasComp.length === 0) {
                notasComp = null;
            }
        }

        return {
            item: item,
            analisis_id: raw.analisis_id || null,
            descripcion: raw.descripcion || '',
            codigo: raw.codigo || '',
            cantidad: cantidad,
            precio: precio,
            total: total,
            precio_minimo_venta: precioMinimoVenta,
            tipo: 'componente',
            ensayo_asociado: Number(raw.ensayo_asociado) || 0,
            metodo_analisis_id: raw.metodo_analisis_id || null,
            metodo_codigo: raw.metodo_codigo || null,
            metodo_descripcion: raw.metodo_descripcion || '',
            unidad_medida: raw.unidad_medida || '',
            limite_deteccion: raw.limite_deteccion || null,
            ley_normativa_id: raw.ley_normativa_id || null,
            nota_tipo: notasComp && notasComp.length > 0 ? notasComp[0].tipo : (raw.nota_tipo || null),
            nota_contenido: notasComp && notasComp.length > 0 ? JSON.stringify(notasComp) : (raw.nota_contenido || null),
            notas: notasComp,
            de_agrupador: raw.de_agrupador === true || raw.de_agrupador === 1 || raw.de_agrupador === '1',
            req_cadena_custodia: reqCadena,
            req_prot_mapba: reqProtMapba,
        };
    }

    function calcularContadorInicial(ensayosIniciales, componentesIniciales) {
        const maxEnsayo = (ensayosIniciales || []).reduce((max, ensayo) => Math.max(max, Number(ensayo.item) || 0), 0);
        const maxComponente = (componentesIniciales || []).reduce((max, componente) => Math.max(max, Number(componente.item) || 0), 0);
        return Math.max(maxEnsayo, maxComponente);
    }

    function getDivisaCodigoActual() {
        if (state.divisaCodigo) {
            return state.divisaCodigo;
        }
        const select = elements.divisaSelect || document.getElementById('divisa_codigo');
        const codigo = select && select.value ? select.value.toString().trim() : 'PES';
        state.divisaCodigo = codigo || 'PES';
        return state.divisaCodigo;
    }

    function formatCurrency(valor) {
        const numero = parseFloat(valor) || 0;
        const codigo = getDivisaCodigoActual();

        if (codigo === 'PES' || codigo === 'ARS') {
            return `$${numero.toFixed(2)}`;
        }
        if (codigo === 'USD') {
            return `USD ${numero.toFixed(2)}`;
        }
        return `${codigo} ${numero.toFixed(2)}`;
    }

    function formatNumber(valor, decimales = 2) {
        const numero = parseFloat(valor);
        if (isNaN(numero)) {
            return (0).toFixed(decimales);
        }
        return numero.toFixed(decimales);
    }

    function formatInt(valor) {
        const numero = Number(valor);
        if (isNaN(numero) || numero < 1) {
            return '1';
        }
        return Math.round(numero).toString();
    }

    function escapeHtml(valor) {
        if (valor === null || valor === undefined) {
            return '';
        }

        return String(valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function esZonaEditablePresupuestoLimitado(el) {
        if (!el) {
            return false;
        }
        if (el.type === 'submit' || el.closest('.presupuesto-form-acciones')) {
            return false;
        }

        return !!(
            el.closest('#datosAprobacionWrapper')
            || el.closest('.cotizacion-contactos-section')
            || el.closest('#presupuestoCondicionPagoWrapper')
            || el.closest('#cuotasPanel')
        );
    }

    function esControlPermitidoEnEdicionLimitada(el) {
        if (!el) {
            return false;
        }
        if (esZonaEditablePresupuestoLimitado(el)) {
            return true;
        }
        if (el.classList && el.classList.contains('btn-editar-norma-item')) {
            return true;
        }
        if (el.classList && el.classList.contains('toggle-componentes')) {
            return true;
        }
        if (el.closest && el.closest('.toggle-componentes')) {
            return true;
        }
        return false;
    }

    function refrescarEdicionLimitadaEnTabla() {
        if (!state.soloReferenciasFacturacion || !elements.form) {
            return;
        }

        Array.from(elements.form.elements).forEach(function (el) {
            if (el.type === 'hidden' || el.tagName === 'A') {
                return;
            }
            if (el.type === 'submit' || el.closest('.presupuesto-form-acciones')) {
                return;
            }
            if (esControlPermitidoEnEdicionLimitada(el)) {
                el.disabled = false;
                return;
            }
            el.disabled = true;
        });

        document.querySelectorAll('.btn-editar-norma-item').forEach(function (btn) {
            btn.disabled = false;
        });
    }

    function appendCampoFormDataSiExiste(fd, name) {
        const el = elements.form ? elements.form.querySelector('[name="' + name + '"]') : null;
        if (!el) {
            return;
        }
        if (el.type === 'checkbox') {
            if (el.checked) {
                fd.append(name, el.value || '1');
            }
            return;
        }
        fd.append(name, el.value ?? '');
    }

    function enviarSoloReferenciasFacturacion(event) {
        if (!state.soloReferenciasFacturacion || !elements.form) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (typeof window.cotizacionReferenciaFactSyncHidden === 'function') {
            window.cotizacionReferenciaFactSyncHidden();
        }

        const fd = new FormData();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fd.append('_token', csrf);
        fd.append('_method', 'PUT');

        const estadoHidden = elements.form.querySelector('input[name="coti_estado"]');
        if (estadoHidden) {
            fd.append('coti_estado', estadoHidden.value);
        }

        const oc = document.getElementById('coti_oc_referencia');
        if (oc) {
            fd.append('coti_oc_referencia', oc.value);
        }

        const ocReq = document.getElementById('coti_oc_requerido_factura');
        if (ocReq && ocReq.checked) {
            fd.append('coti_oc_requerido_factura', '1');
        }

        const refsHidden = document.getElementById('coti_refs_facturacion_json');
        fd.append('coti_refs_facturacion_json', refsHidden ? (refsHidden.value || '[]') : '[]');

        [
            'coti_contacto', 'coti_telefono', 'coti_mail1', 'coti_contacto_tipo1',
            'coti_contacto2', 'coti_mail2', 'coti_telefono2', 'coti_contacto_tipo2',
            'coti_contacto3', 'coti_mail3', 'coti_telefono3', 'coti_contacto_tipo3',
            'coti_contacto4', 'coti_mail4', 'coti_telefono4', 'coti_contacto_tipo4',
            'coti_cond_pago', 'coti_cuota_desc', 'coti_cuota_cant', 'coti_cuota_monto_total',
            'coti_cuota_monto_indiv', 'coti_cuota_interes', 'coti_cuota_fact_fin_mes', 'coti_cuota_fact_inicio_mes',
        ].forEach(function (name) {
            appendCampoFormDataSiExiste(fd, name);
        });

        const ensayosLey = state.ensayos.map(function (ensayo) {
            return {
                item: ensayo.item,
                ley_normativa_id: ensayo.ley_normativa_id ? String(ensayo.ley_normativa_id).trim() : null,
            };
        });
        const componentesLey = state.componentes.map(function (componente) {
            return {
                item: componente.item,
                ley_normativa_id: componente.ley_normativa_id ? String(componente.ley_normativa_id).trim() : null,
            };
        });
        fd.append('ensayos_data', JSON.stringify(ensayosLey));
        fd.append('componentes_data', JSON.stringify(componentesLey));

        const btn = document.getElementById('btnGuardarPresupuesto');
        if (btn) {
            btn.disabled = true;
        }

        fetch(elements.form.action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'No se pudieron guardar las referencias.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Datos guardados correctamente.',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                    });
                }
                setTimeout(function () {
                    window.location.reload();
                }, 900);
            })
            .catch(function (error) {
                if (btn) {
                    btn.disabled = false;
                }
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudieron guardar los cambios permitidos.',
                    });
                }
            });
    }

    function aplicarRestriccionEdicionSiCorresponde() {
        if (state.puedeEditar || !elements.form) {
            return;
        }

        if (state.soloReferenciasFacturacion) {
            Array.from(elements.form.elements).forEach(function (el) {
                if (el.type === 'hidden' || el.tagName === 'A') {
                    return;
                }
                if (el.type === 'submit' || el.closest('.presupuesto-form-acciones')) {
                    return;
                }
                if (esControlPermitidoEnEdicionLimitada(el)) {
                    return;
                }
                el.disabled = true;
            });

            refrescarEdicionLimitadaEnTabla();

            ['btnAbrirModalEnsayo', 'btnAbrirModalComponente'].forEach(function (id) {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = true;
                    btn.classList.add('d-none');
                }
            });

            const tabla = elements.tablaItems ? elements.tablaItems.closest('table') : null;
            if (tabla) {
                tabla.classList.remove('tabla-items-bloqueada');
                tabla.classList.add('tabla-items-edicion-limitada');
            }

            if (elements.form) {
                elements.form.setAttribute('novalidate', 'novalidate');
                elements.form.addEventListener('submit', enviarSoloReferenciasFacturacion, true);
            }

            return;
        }

        const aviso = document.createElement('div');
        aviso.className = 'alert alert-info mb-3';
        aviso.textContent = 'Esta cotización está en modo solo lectura y no puede modificarse.';

        const contenedor = elements.form.closest('.card');
        if (contenedor && !contenedor.querySelector('.alert-info')) {
            contenedor.prepend(aviso);
        }

        Array.from(elements.form.elements).forEach(el => {
            if (el.type === 'hidden' || el.tagName === 'A') {
                return;
            }
            el.disabled = true;
        });

        const tabla = elements.tablaItems ? elements.tablaItems.closest('table') : null;
        if (tabla) {
            tabla.classList.add('tabla-items-bloqueada');
        }
    }

    function sincronizarDestinatarioAntesDeEnviar() {
        const sucursalInput = document.getElementById('sucursal');
        const sucursalSelect = document.getElementById('sucursal_select');
        if (sucursalInput && sucursalSelect && !sucursalSelect.classList.contains('d-none')) {
            sucursalInput.value = (sucursalSelect.value || '').trim();
        }

        const inputPara = document.getElementById('coti_para');
        const selectPara = document.getElementById('coti_para_select');
        const hiddenEmpresaId = document.getElementById('coti_empresa_rel');
        if (selectPara && !selectPara.classList.contains('d-none') && (selectPara.value || '').trim() !== '') {
            const valor = String(selectPara.value).trim();
            const selectedOption = Array.from(selectPara.options).find(function (o) {
                return String(o.value || '').trim() === valor;
            });
            if (hiddenEmpresaId) {
                hiddenEmpresaId.value = valor;
            }
            if (inputPara && selectedOption) {
                inputPara.value = (
                    selectedOption.dataset.razonSocial
                    || selectedOption.getAttribute('data-razon-social')
                    || selectedOption.textContent
                    || ''
                ).trim();
            }
        }
    }

    if (elements.form) {
        // Usar fase de captura (true) para rellenar hidden antes de cualquier otro listener (p. ej. confirmación en edit)
        elements.form.addEventListener('submit', function (event) {
            if (state.soloReferenciasFacturacion) {
                return;
            }
            sincronizarDestinatarioAntesDeEnviar();
            const ensayosJson = JSON.stringify(state.ensayos.map(serializarEnsayo));
            const componentesJson = JSON.stringify(state.componentes.map(serializarComponente));
            if (elements.ensayosHidden) {
                elements.ensayosHidden.value = ensayosJson;
            }
            if (elements.componentesHidden) {
                elements.componentesHidden.value = componentesJson;
            }
            if (typeof window.cotizacionReferenciaFactSyncHidden === 'function') {
                window.cotizacionReferenciaFactSyncHidden();
            }
            if (typeof window.cotizacionNotasGeneralesSyncHidden === 'function') {
                window.cotizacionNotasGeneralesSyncHidden();
            }
            appendAdjuntosAlFormulario(elements.form);
            console.log('[Cotización submit] Ensayos:', state.ensayos.length, 'Componentes:', state.componentes.length, 'componentes_data length:', componentesJson.length, 'preview:', componentesJson.substring(0, 300));
        }, true);
    }

    function serializarEnsayo(ensayo) {
        const canalSer = ensayo.canal_especial || canalEspecialDesdeMatrizYDescripcion(ensayo.matriz_descripcion, ensayo.descripcion);
        return {
            item: ensayo.item,
            muestra_id: ensayo.muestra_id,
            descripcion: ensayo.descripcion,
            codigo: ensayo.codigo,
            cantidad: ensayo.cantidad,
            precio: ensayo.precio,
            total: ensayo.total,
            tipo: ensayo.tipo,
            componentes_sugeridos: ensayo.componentes_sugeridos || [],
            nota_tipo: ensayo.nota_tipo || null,
            nota_contenido: ensayo.nota_contenido || null,
            matriz_codigo: ensayo.matriz_codigo || null,
            matriz_descripcion: ensayo.matriz_descripcion || null,
            canal_especial: canalSer,
            req_cadena_custodia: ensayo.req_cadena_custodia === true,
            req_prot_mapba: ensayo.req_prot_mapba === true,
            lleva_muestreo: (canalSer && canalSer !== 'mediciones') ? false : (ensayo.lleva_muestreo !== false),
            precio_extra_ensayo: Math.max(0, parseFloat(ensayo.precio_extra_ensayo) || 0),
            ley_normativa_id: ensayo.ley_normativa_id
                ? String(ensayo.ley_normativa_id).trim()
                : null,
            es_priori: !!ensayo.es_priori,
        };
    }

    function serializarComponente(componente) {
        const ensayoAsociado = state.ensayos.find(e => e.item === Number(componente.ensayo_asociado));
        const deAgrupador = resolverDeAgrupadorComponente(ensayoAsociado, componente.analisis_id, componente.de_agrupador);
        if (deAgrupador) {
            componente.de_agrupador = true;
        }

        return {
            item: Number(componente.item) || 0,
            analisis_id: componente.analisis_id,
            descripcion: (componente.descripcion || '').toString().trim(),
            codigo: (componente.codigo || '').toString().trim(),
            cantidad: toPositiveInt(componente.cantidad, 1),
            precio: parseFloat(componente.precio) || 0,
            total: parseFloat(componente.total) || 0,
            tipo: componente.tipo || 'componente',
            ensayo_asociado: Number(componente.ensayo_asociado) || 0,
            metodo_analisis_id: componente.metodo_analisis_id,
            metodo_codigo: componente.metodo_codigo,
            metodo_descripcion: componente.metodo_descripcion,
            unidad_medida: componente.unidad_medida,
            limite_deteccion: componente.limite_deteccion,
            ley_normativa_id: componente.ley_normativa_id,
            nota_tipo: componente.nota_tipo || null,
            nota_contenido: componente.nota_contenido || null,
            de_agrupador: deAgrupador,
            req_cadena_custodia: componente.req_cadena_custodia === true,
            req_prot_mapba: componente.req_prot_mapba === true,
        };
    }

    function obtenerComponentesSugeridosDeEnsayo(ensayo) {
        if (!ensayo) {
            return [];
        }

        if (Array.isArray(ensayo.componentes_sugeridos) && ensayo.componentes_sugeridos.length) {
            const normalizados = ensayo.componentes_sugeridos.map(id => id.toString());
            ensayo.componentes_sugeridos = Array.from(new Set(normalizados));
            return ensayo.componentes_sugeridos;
        }

        let defaults = [];

        if (ensayo.muestra_id) {
            defaults = catalogs.ensayosDefaultsById[ensayo.muestra_id.toString()] || [];
        }

        if ((!defaults || defaults.length === 0) && ensayo.codigo) {
            defaults = catalogs.ensayosDefaultsByCodigo[String(ensayo.codigo).trim()] || [];
        }

        const normalizados = defaults.map(id => id.toString());
        ensayo.componentes_sugeridos = Array.from(new Set(normalizados));
        return ensayo.componentes_sugeridos;
    }

    function preseleccionarComponentesDeEnsayo(ensayoItemId, recargarOpciones = true) {
        if (!elements.selectComponente) {
            return;
        }

        function reordenarOpcionesSelectPorIds(selectEl, idsEnOrden) {
            if (!selectEl || !Array.isArray(idsEnOrden) || idsEnOrden.length === 0) return;

            const idsSet = new Set(idsEnOrden.map(v => v.toString()));
            const optionById = new Map(
                Array.from(selectEl.options).map(opt => [opt.value.toString(), opt])
            );

            // Mantener el resto en el orden actual (alfabético) y poner sugeridos arriba en orden.
            const fragment = document.createDocumentFragment();

            idsEnOrden.forEach(id => {
                const opt = optionById.get(id.toString());
                if (opt) fragment.appendChild(opt);
            });

            Array.from(optionById.entries()).forEach(([id, opt]) => {
                if (!idsSet.has(id)) fragment.appendChild(opt);
            });

            selectEl.appendChild(fragment);
        }

        if (!ensayoItemId) {
            if (window.$ && window.$('#componente_analisis').length) {
                window.$('#componente_analisis').val(null).trigger('change');
            }
            return;
        }

        const ensayo = state.ensayos.find(e => e.item === Number(ensayoItemId));
        if (!ensayo) {
            return;
        }

        // Obtener componentes sugeridos del ensayo (mantener el orden)
        const componentesIds = (obtenerComponentesSugeridosDeEnsayo(ensayo) || []).map(id => id.toString());
        
        // Filtrar solo los que existen en las opciones disponibles
        const opcionesDisponibles = Array.from(elements.selectComponente.options)
            .map(opt => opt.value.toString());
        const componentesIdsFiltrados = componentesIds.filter(id => opcionesDisponibles.includes(id));

        // Preseleccionar usando Select2 de forma estándar (respetar orden: sugeridos primero)
        if (window.$ && window.$('#componente_analisis').length && window.$('#componente_analisis').data('select2')) {
            const valoresActuales = window.$('#componente_analisis').val() || [];
            const sugeridosSet = new Set(componentesIdsFiltrados.map(v => v.toString()));
            const restantes = valoresActuales.map(v => v.toString()).filter(v => !sugeridosSet.has(v));
            const valoresCombinados = [...componentesIdsFiltrados, ...restantes];
            // Reordenar el DOM del <select> para que Select2 respete el orden de selección mostrado
            reordenarOpcionesSelectPorIds(elements.selectComponente, valoresCombinados);
            window.$('#componente_analisis').val(valoresCombinados).trigger('change');
        } else {
            // Para selects nativos
            reordenarOpcionesSelectPorIds(elements.selectComponente, componentesIdsFiltrados);
            Array.from(elements.selectComponente.options).forEach(opt => {
                if (componentesIdsFiltrados.includes(opt.value.toString())) {
                    opt.selected = true;
                }
            });
            handleCambioComponenteModal();
        }
        
        // Actualizar chips
        actualizarChipsComponentesPreseleccionados();
    }
    
    function actualizarChipsComponentesPreseleccionados() {
        // Verificar que el modal esté visible antes de buscar elementos
        const modal = document.getElementById('modalAgregarComponente');
        if (!modal || !modal.classList.contains('show')) {
            // El modal no está visible, no hacer nada
            return;
        }
        
        const container = document.getElementById('componentes_preseleccionados_container');
        const countSpan = document.getElementById('componentes_preseleccionados_count');
        const listaDiv = document.getElementById('componentes_preseleccionados_lista');
        
        if (!container || !countSpan || !listaDiv) {
            // console.log('actualizarChipsComponentesPreseleccionados: Elementos no encontrados, reintentando...', {
            //     container: !!container,
            //     countSpan: !!countSpan,
            //     listaDiv: !!listaDiv,
            //     modalVisible: modal && modal.classList.contains('show')
            // });
            // Reintentar después de un breve delay si el modal está visible
            if (modal && modal.classList.contains('show')) {
                setTimeout(() => actualizarChipsComponentesPreseleccionados(), 200);
            }
            return;
        }
        
        const ensayoItemId = elements.selectEnsayoAsociado ? elements.selectEnsayoAsociado.value : null;
        if (!ensayoItemId) {
            // console.log('actualizarChipsComponentesPreseleccionados: No hay ensayo seleccionado');
            container.classList.add('d-none');
            return;
        }
        
        const ensayo = state.ensayos.find(e => e.item === Number(ensayoItemId));
        if (!ensayo) {
            // console.log('actualizarChipsComponentesPreseleccionados: Ensayo no encontrado en state', ensayoItemId);
            container.classList.add('d-none');
            return;
        }
        
        const componentesPreseleccionados = obtenerComponentesSugeridosDeEnsayo(ensayo).map(id => id.toString());
        // console.log('actualizarChipsComponentesPreseleccionados: Componentes preseleccionados', {
        //     ensayo: ensayo.descripcion,
        //     componentesPreseleccionados: componentesPreseleccionados
        // });
        
        if (componentesPreseleccionados.length === 0) {
            // console.log('actualizarChipsComponentesPreseleccionados: No hay componentes preseleccionados');
            container.classList.add('d-none');
            return;
        }
        
        // Filtrar solo los componentes preseleccionados que existen en las opciones disponibles
        const opcionesDisponibles = elements.selectComponente ? 
            Array.from(elements.selectComponente.options).map(opt => opt.value.toString()) : [];
        const componentesPreseleccionadosDisponibles = componentesPreseleccionados.filter(id => 
            opcionesDisponibles.includes(id)
        );
        
        // console.log('actualizarChipsComponentesPreseleccionados: Componentes disponibles', {
        //     opcionesDisponibles: opcionesDisponibles.length,
        //     componentesPreseleccionadosDisponibles: componentesPreseleccionadosDisponibles
        // });
        
        if (componentesPreseleccionadosDisponibles.length === 0) {
            // console.log('actualizarChipsComponentesPreseleccionados: No hay componentes preseleccionados disponibles en las opciones');
            container.classList.add('d-none');
            return;
        }
        
        // Obtener componentes seleccionados actualmente para marcar cuáles están seleccionados
        const $select = window.$('#componente_analisis');
        const componentesSeleccionados = $select ? ($select.val() || []) : [];
        
        // Limpiar lista anterior
        listaDiv.innerHTML = '';
        
        // Crear items para cada componente preseleccionado disponible
        componentesPreseleccionadosDisponibles.forEach(id => {
            const estaSeleccionado = componentesSeleccionados.includes(id);
            const option = elements.selectComponente ? 
                Array.from(elements.selectComponente.options).find(opt => opt.value === id) : null;
            if (!option) return;
            
            const nombre = option.textContent.trim();
            const metodoCodigo = option.dataset.metodoCodigo || '';
            const metodoDescripcion = option.dataset.metodoDescripcion || '';
            const precio = parseFloat(option.dataset.precioRaw || 0);
            
            // Crear item del componente
            const item = document.createElement('div');
            item.className = 'componente-preseleccionado-item';
            if (estaSeleccionado) {
                item.classList.add('componente-seleccionado');
            }
            item.dataset.componenteId = id;
            
            const infoDiv = document.createElement('div');
            infoDiv.className = 'componente-preseleccionado-info';
            
            const nombreP = document.createElement('p');
            nombreP.className = 'componente-preseleccionado-nombre';
            nombreP.textContent = nombre;
            if (estaSeleccionado) {
                const checkIcon = document.createElement('span');
                checkIcon.className = 'me-2';
                checkIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px; color: #198754;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
                nombreP.insertBefore(checkIcon, nombreP.firstChild);
            }
            
            const detallesDiv = document.createElement('div');
            detallesDiv.className = 'componente-preseleccionado-detalles';
            const detalles = [];
            if (metodoCodigo) {
                detalles.push(`Método: ${metodoCodigo}${metodoDescripcion ? ' - ' + metodoDescripcion : ''}`);
            }
            if (precio > 0) {
                detalles.push(`Precio: ${formatCurrency(precio)}`);
            }
            detallesDiv.textContent = detalles.join(' • ');
            
            infoDiv.appendChild(nombreP);
            if (detalles.length > 0) {
                infoDiv.appendChild(detallesDiv);
            }
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'componente-preseleccionado-remove';
            removeBtn.dataset.componenteId = id;
            removeBtn.title = 'Eliminar componente';
            removeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 16px; height: 16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
            
            // Agregar evento para remover componente individual
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const componenteId = this.dataset.componenteId;
                const valoresActuales = $select ? ($select.val() || []) : [];
                const valoresFinales = valoresActuales.filter(v => v !== componenteId);
                if ($select) {
                    $select.val(valoresFinales).trigger('change');
                }
                // Actualizar la lista después de remover
                setTimeout(() => actualizarChipsComponentesPreseleccionados(), 100);
            });
            
            // Si no está seleccionado, agregar botón para agregarlo
            if (!estaSeleccionado) {
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn btn-sm btn-outline-info ms-2';
                addBtn.textContent = 'Agregar';
                addBtn.title = 'Agregar este componente';
                addBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const componenteId = id;
                    const valoresActuales = $select ? ($select.val() || []) : [];
                    if (!valoresActuales.includes(componenteId)) {
                        const valoresFinales = [...valoresActuales, componenteId];
                        if ($select) {
                            $select.val(valoresFinales).trigger('change');
                        }
                        setTimeout(() => actualizarChipsComponentesPreseleccionados(), 100);
                    }
                });
                item.appendChild(addBtn);
            }
            
            item.appendChild(infoDiv);
            item.appendChild(removeBtn);
            listaDiv.appendChild(item);
        });
        
        countSpan.textContent = componentesPreseleccionadosDisponibles.length;
        container.classList.remove('d-none');
        
        // console.log('actualizarChipsComponentesPreseleccionados: Contenedor mostrado con', componentesPreseleccionadosDisponibles.length, 'componentes');
        
        // Ocultar los componentes preseleccionados del select para que no aparezcan como tags grandes
        if ($select && $select.data('select2')) {
            setTimeout(() => {
                const $selectContainer = $select.next('.select2-container');
                if ($selectContainer.length) {
                    const $choices = $selectContainer.find('.select2-selection__choice');
                    $choices.each(function() {
                        const $choice = $(this);
                        const choiceText = $choice.text().trim();
                        // Buscar el componente por su texto
                        const option = Array.from(elements.selectComponente.options).find(opt => {
                            const optText = opt.textContent.trim();
                            return optText === choiceText && componentesPreseleccionadosDisponibles.includes(opt.value.toString());
                        });
                        if (option) {
                            $choice.addClass('componente-preseleccionado-hidden');
                        }
                    });
                }
            }, 100);
        }
    }

    window.agregarEnsayo = agregarEnsayo;
    window.agregarComponente = agregarComponente;
    window.eliminarItem = eliminarItem;
    window.verDetalle = verDetalle;
    window.editarEnsayo = editarEnsayo;
    
    // Exponer state y funciones para carga de versiones
    // Exponer funciones y state para uso externo
    window.cotizacionScripts = {
        state: state,
        renderTabla: renderTabla,
        sincronizarTotales: sincronizarTotales,
        actualizarEnsayosDisponiblesParaComponentes: actualizarEnsayosDisponiblesParaComponentes,
        cargarItemsDesdeVersion: function(ensayos, componentes) {
            // console.log('[cotizacion] ========== cargarItemsDesdeVersion INICIADO ==========');
            // console.log('[cotizacion] Parámetros recibidos:', {
            //     ensayosTipo: typeof ensayos,
            //     ensayosEsArray: Array.isArray(ensayos),
            //     ensayosCount: ensayos ? ensayos.length : 0,
            //     componentesTipo: typeof componentes,
            //     componentesEsArray: Array.isArray(componentes),
            //     componentesCount: componentes ? componentes.length : 0,
            //     ensayosSample: Array.isArray(ensayos) && ensayos.length > 0 ? ensayos[0] : null,
            //     componentesSample: Array.isArray(componentes) && componentes.length > 0 ? componentes[0] : null
            // });
            
            // Estado ANTES de la actualización
            // console.log('[cotizacion] Estado ANTES de actualizar:', {
            //     ensayosEnState: state.ensayos.length,
            //     componentesEnState: state.componentes.length,
            //     contador: state.contador
            // });
            
            // Asegurar que sean arrays
            const ensayosArray = Array.isArray(ensayos) ? ensayos : [];
            const componentesArray = Array.isArray(componentes) ? componentes : [];
            
            // console.log('[cotizacion] Arrays normalizados:', {
            //     ensayosArrayLength: ensayosArray.length,
            //     componentesArrayLength: componentesArray.length
            // });
            
            // ENFOQUE NUEVO: Limpiar completamente ANTES de cargar
            // console.log('[cotizacion] LIMPIANDO state completamente...');
            
            // 1. Limpiar arrays del state
            state.ensayos.length = 0;
            state.componentes.length = 0;
            
            // 2. Limpiar la tabla visualmente
            if (elements.tablaItems) {
                // console.log('[cotizacion] Limpiando tabla visualmente...');
                elements.tablaItems.innerHTML = '';
            }
            
            // 3. Limpiar estado de colapso
            state.ensayosColapsados.clear();
            
            // 4. Forzar un pequeño delay para asegurar que el DOM se actualice
            setTimeout(() => {
                // console.log('[cotizacion] Cargando nuevos items después de limpieza...');
                
                // Limpiar y normalizar items - SIEMPRE reemplazar completamente
                // console.log('[cotizacion] Normalizando ensayos...');
                state.ensayos = ensayosArray.map(e => normalizarEnsayo(e));
                
                // console.log('[cotizacion] Normalizando componentes...');
                state.componentes = componentesArray.map(c => normalizarComponente(c));
                
                // Recalcular contador
                state.contador = calcularContadorInicial(ensayosArray, componentesArray);
            
                // console.log('[cotizacion] Estado DESPUÉS de actualizar:', {
                //     ensayosEnState: state.ensayos.length,
                //     componentesEnState: state.componentes.length,
                //     contador: state.contador,
                //     ensayosEnStateSample: state.ensayos.slice(0, 2),
                //     componentesEnStateSample: state.componentes.slice(0, 2)
                // });
                
                // Renderizar y actualizar
                // console.log('[cotizacion] Renderizando tabla...');
                // console.log('[cotizacion] Estado antes de renderTabla:', {
                //     ensayosEnState: state.ensayos.length,
                //     componentesEnState: state.componentes.length
                // });
                
                try {
                    renderTabla();
                    // console.log('[cotizacion] ✅ renderTabla ejecutado correctamente');
                } catch (error) {
                    // console.error('[cotizacion] ❌ Error en renderTabla:', error);
                }
                
                // console.log('[cotizacion] Actualizando ensayos disponibles...');
                try {
                    actualizarEnsayosDisponiblesParaComponentes();
                    // console.log('[cotizacion] ✅ actualizarEnsayosDisponiblesParaComponentes ejecutado');
                } catch (error) {
                    // console.error('[cotizacion] ❌ Error en actualizarEnsayosDisponiblesParaComponentes:', error);
                }
                
                // console.log('[cotizacion] Sincronizando totales...');
                try {
                    sincronizarTotales();
                    // console.log('[cotizacion] ✅ sincronizarTotales ejecutado');
                } catch (error) {
                    // console.error('[cotizacion] ❌ Error en sincronizarTotales:', error);
                }
                
                // Verificar estado final
                const filasEnTabla = elements.tablaItems ? elements.tablaItems.querySelectorAll('tr').length : 0;
                const totalItemsEsperados = state.ensayos.length + state.componentes.length;
                
                // console.log('[cotizacion] Estado FINAL después de todas las operaciones:', {
                //     ensayosEnState: state.ensayos.length,
                //     componentesEnState: state.componentes.length,
                //     contador: state.contador,
                //     tablaItemsExiste: !!elements.tablaItems,
                //     filasEnTabla: filasEnTabla,
                //     totalItemsEsperados: totalItemsEsperados,
                //     coincide: filasEnTabla === totalItemsEsperados || (totalItemsEsperados === 0 && filasEnTabla === 1) // 1 fila es el mensaje "no hay items"
                // });
                
                // Verificación adicional: si no coincide, forzar otro render
                if (filasEnTabla !== totalItemsEsperados && !(totalItemsEsperados === 0 && filasEnTabla === 1)) {
                    // console.warn('[cotizacion] ⚠️ La tabla no coincide con el state, forzando re-render...');
                    setTimeout(() => {
                        renderTabla();
                        sincronizarTotales();
                    }, 100);
                }
                
                // console.log('[cotizacion] ========== cargarItemsDesdeVersion COMPLETADO ==========');
            }, 50); // Pequeño delay para asegurar que el DOM se actualice
        },
        syncCotiReqCadenaRelCheckboxesFromHidden: syncCotiReqCadenaRelCheckboxesFromHidden,
        sincronizarCheckboxGlobalPrioridad: sincronizarCheckboxGlobalPrioridad,
        aplicarPrioridadATodosLosEnsayos: aplicarPrioridadATodosLosEnsayos,
        sincronizarResumenEmpresaRelacionadaDesdeCotiData: function (cotiData) {
            if (!cotiData) {
                limpiarPanelEmpresaRelacionada();
                return;
            }
            const id = cotiData.coti_empresa_rel || cotiData.coti_cli_empresa;
            if (!id) {
                limpiarPanelEmpresaRelacionada();
                return;
            }
            fetch('/api/empresa-relacionada/' + encodeURIComponent(String(id)))
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('not ok');
                    }
                    return r.json();
                })
                .then(function (emp) {
                    rellenarPanelEmpresaRelacionada({
                        razonSocial: emp.razon_social || '',
                        direccion: emp.direcciones || '',
                        localidad: emp.localidad || '',
                        partido: emp.partido || '',
                        cuit: emp.cuit || '',
                        contacto: emp.contacto || '',
                    });
                })
                .catch(function () {
                    rellenarPanelEmpresaRelacionada({
                        razonSocial: (cotiData.coti_para || '').toString(),
                        direccion: '',
                        localidad: '',
                        partido: '',
                        cuit: '',
                        contacto: '',
                    });
                });
        },
    };
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCotizacionScripts);
    } else {
        initCotizacionScripts();
    }
    
    // Log cuando el script se expone
    // console.log('[cotizacion] Script de cotización cargado', {
    //     windowCotizacionScriptsDisponible: !!window.cotizacionScripts,
    //     tieneCargarItemsDesdeVersion: window.cotizacionScripts && typeof window.cotizacionScripts.cargarItemsDesdeVersion === 'function',
    //     stateInicial: window.cotizacionScripts ? {
    //         ensayos: window.cotizacionScripts.state.ensayos.length,
    //         componentes: window.cotizacionScripts.state.componentes.length
    //     } : null
    // });
})();
</script>

