<script>
(function () {
    const initCotizacionScripts = function () {
        // console.log('[cotizacion] script inicializado');

        const config = window.cotizacionConfig || {
        modo: 'create',
        puedeEditar: true,
        ensayosIniciales: [],
        componentesIniciales: [],
    };

        // console.log('[cotizacion] Inicializando state con config:', {
        //     modo: config.modo,
        //     puedeEditar: config.puedeEditar,
        //     ensayosInicialesCount: (config.ensayosIniciales || []).length,
        //     componentesInicialesCount: (config.componentesIniciales || []).length,
        //     ensayosInicialesSample: (config.ensayosIniciales || []).slice(0, 2),
        //     componentesInicialesSample: (config.componentesIniciales || []).slice(0, 2)
        // });
        
        const state = {
        ensayos: (config.ensayosIniciales || []).map(normalizarEnsayo),
        componentes: (config.componentesIniciales || []).map(normalizarComponente),
        contador: calcularContadorInicial(
            config.ensayosIniciales || [],
            config.componentesIniciales || []
        ),
        puedeEditar: config.puedeEditar !== false,
        modo: config.modo || 'create',
        clienteSeleccionado: null,
        ensayosColapsados: new Set(), // Guardar estado de colapso de ensayos
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
        totalConDescuento: document.getElementById('totalConDescuento'),
        descuentoGlobalMonto: document.getElementById('descuentoGlobalMonto'),
        descuentoGlobalPorcentaje: document.getElementById('descuentoGlobalPorcentaje'),
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
    };

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
            inicializarEventosTabla();
            inicializarBotonesAccion();
            inicializarBloqueAprobacion();
            await cargarCatalogos();
            inicializarEventosModales();
            inicializarEventosSector();
            inicializarEventosDescuentos();
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
                new window.bootstrap.Tooltip(tooltipTriggerEl);
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
            
            // Campos de la solapa General (usar siempre datos del cliente)
            asignarValorSiExiste('telefono', cliente.telefono);
            asignarValorSiExiste('correo', cliente.email);
            asignarValorSiExiste('sector', cliente.sector);
            asignarValorSiExiste('contacto', cliente.contacto);

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
            if (state.clienteSeleccionado.es_consultor) {
                cargarEmpresasRelacionadas(cliente.codigo);
            } else {
                // Si no es consultor, asegurar que el campo "Para" sea un input de texto
                resetearCampoPara();
            }
        }

        actualizarDescuentoCliente();
        actualizarTotalGeneral();
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
                const hiddenEmpresaId = document.getElementById('coti_cli_empresa');
                
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
                        
                        // Preseleccionar si el ID coincide
                        if (empresaIdPreseleccionado && empresa.id == empresaIdPreseleccionado) {
                            option.selected = true;
                            hiddenEmpresaId.value = empresa.id;
                            inputPara.value = empresa.razon_social; // Mantener el texto en el input para referencia
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
                                return $option.data('razonSocial') || empresa.text;
                            },
                            escapeMarkup: function(markup) {
                                return markup;
                            }
                        });
                    }
                    
                    // Agregar event listener para actualizar el campo hidden cuando cambie la selección
                    selectPara.removeEventListener('change', actualizarEmpresaSeleccionada);
                    selectPara.addEventListener('change', actualizarEmpresaSeleccionada);
                    
                    // Si hay un ID preseleccionado y no se encontró en las opciones, mantenerlo en el input
                    if (empresaIdPreseleccionado && !selectPara.value) {
                        inputPara.value = '';
                        inputPara.classList.remove('d-none');
                        selectPara.classList.add('d-none');
                        hiddenEmpresaId.value = empresaIdPreseleccionado;
                    }
                } else {
                    // No hay empresas relacionadas, mantener como input
                    resetearCampoPara();
                }
            })
            .catch(error => {
                // console.error('Error cargando empresas relacionadas:', error);
                resetearCampoPara();
            });
    }

    function actualizarEmpresaSeleccionada() {
        const selectPara = document.getElementById('coti_para_select');
        const hiddenEmpresaId = document.getElementById('coti_cli_empresa');
        const inputPara = document.getElementById('coti_para');
        
        if (!selectPara || !hiddenEmpresaId) {
            return;
        }
        
        const selectedOption = selectPara.options[selectPara.selectedIndex];
        if (selectedOption && selectedOption.value) {
            hiddenEmpresaId.value = selectedOption.value; // Guardar el ID
            if (inputPara) {
                inputPara.value = selectedOption.dataset.razonSocial || selectedOption.textContent; // Mostrar razón social en input si es necesario
            }
        } else {
            hiddenEmpresaId.value = '';
            if (inputPara) {
                inputPara.value = '';
            }
        }
    }

    function resetearCampoPara() {
        const inputPara = document.getElementById('coti_para');
        const selectPara = document.getElementById('coti_para_select');
        const hiddenEmpresaId = document.getElementById('coti_cli_empresa');
        
        if (inputPara && selectPara) {
            inputPara.classList.remove('d-none');
            selectPara.classList.add('d-none');
            selectPara.innerHTML = '<option value="">Seleccionar empresa relacionada...</option>';
        }
        
        if (hiddenEmpresaId) {
            hiddenEmpresaId.value = '';
        }
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
                
                // Si es consultor y hay un ID guardado, cargar empresas relacionadas
                const cotiCliEmpresa = document.getElementById('coti_cli_empresa');
                if (cotiCliEmpresa && cotiCliEmpresa.value && state.clienteSeleccionado && state.clienteSeleccionado.es_consultor) {
                    cargarEmpresasRelacionadas(codigoActual, cotiCliEmpresa.value);
                } else if (!state.clienteSeleccionado || !state.clienteSeleccionado.es_consultor) {
                    // Si no es consultor, asegurar que el campo "Para" sea un input de texto
                    resetearCampoPara();
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
            elements.modalEnsayo.addEventListener('shown.bs.modal', function () {
                cargarOpcionesEnsayos();
                cargarLeyesNormativas();
                // Limpiar contenedor de notas al abrir el modal
                const container = document.getElementById('notasEnsayoContainer');
                if (container) {
                    container.innerHTML = '';
                }
                if (window.$ && window.$('#ensayo_muestra').length) {
                    window.$('#ensayo_muestra').select2({
                        dropdownParent: window.$('#modalAgregarEnsayo'),
                    });
                }
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
                        placeholder: 'Seleccionar análisis...',
                        closeOnSelect: false,
                        templateResult: renderComponenteOptionTemplate,
                        templateSelection: function(data, container) {
                            if (!data.id || !data.element) {
                                return data.text;
                            }
                            const dataset = data.element.dataset || {};
                            let descripcion = data.text || dataset.descripcion || '';
                            // Limpiar el prefijo [AGRUPADOR] si existe
                            descripcion = descripcion.replace(/^\[AGRUPADOR\]\s*/, '');
                            const esAgrupador = dataset.esAgrupador === '1';
                            
                            // Para el template de selección, mostrar descripción con indicador si es agrupador
                            if (esAgrupador) {
                                return escapeHtml(descripcion) + ' [AGRUPADOR]';
                            }
                            return escapeHtml(descripcion);
                        },
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

            });
        }

        if (elements.selectEnsayo) {
            elements.selectEnsayo.addEventListener('change', handleCambioEnsayoModal);
        }

        if (elements.selectComponente) {
            elements.selectComponente.addEventListener('change', handleCambioComponenteModal);
        }

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
                    if (option.dataset.metodoCodigo) {
                        const selectMetodo = document.getElementById('edit_componente_metodo');
                        if (selectMetodo) {
                            selectMetodo.value = option.dataset.metodoCodigo;
                        }
                    }
                }
            });
        }
    }

    function guardarComponenteEditadoHandler() {
        if (!state.puedeEditar) {
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

        // Actualizar componente
        componente.analisis_id = analisisId;
        componente.descripcion = option.textContent || componente.descripcion;
        componente.codigo = option.dataset.codigo || componente.codigo;
        componente.precio = precio;
        // Mantener la cantidad actual del componente (no se edita)
        componente.total = precio * componente.cantidad;
        componente.unidad_medida = unidadMedida || option.dataset.unidadMedida || componente.unidad_medida;
        componente.metodo_analisis_id = metodoId;
        componente.metodo_codigo = option.dataset.metodoCodigo || componente.metodo_codigo;
        
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
            } else if (event.target.classList.contains('input-precio-componente')) {
                const itemId = Number(event.target.dataset.item);
                const valor = toPositiveNumber(event.target.value, 0);
                event.target.value = valor;
                actualizarPrecioComponente(itemId, valor);
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

        if (unitario) {
            unitario.textContent = formatCurrency(ensayo.precio);
        }

        if (total) {
            total.textContent = formatCurrency(ensayo.total);
        }
    }

    function actualizarTotalComponenteEnDOM(itemId) {
        const celda = document.querySelector(`[data-componente-total="${itemId}"]`);
        const componente = state.componentes.find(c => c.item === itemId);
        if (celda && componente) {
            celda.textContent = formatCurrency(componente.total);
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
            option.textContent = ensayo.descripcion;
            option.dataset.codigo = ensayo.codigo;
            option.dataset.metodoCodigo = (ensayo.metodo_codigo || ensayo.metodo || '').toString().trim();
            option.dataset.metodoDescripcion = ensayo.metodo_descripcion || '';
            option.dataset.componentes = JSON.stringify(Array.isArray(ensayo.componentes_default) ? ensayo.componentes_default : []);
            // Guardar matriz_codigo y matriz_descripcion en el option
            option.dataset.matrizCodigo = (ensayo.matriz_codigo || '').toString().trim();
            option.dataset.matrizDescripcion = ensayo.matriz_descripcion || '';
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
            const metodoCodigo = (componente.metodo_codigo || componente.metodo || '').toString().trim();
            option.value = componente.id;
            // Si es agrupador, agregar indicador visual
            const esAgrupador = componente.es_muestra === true || componente.es_muestra === 1;
            option.textContent = esAgrupador ? `[AGRUPADOR] ${componente.descripcion}` : componente.descripcion;
            option.dataset.descripcion = componente.descripcion || '';
            option.dataset.codigo = componente.codigo || '';
            option.dataset.unidadMedida = componente.unidad_medida || '';
            option.dataset.precio = precio.toFixed(2);
            option.dataset.precioRaw = precio;
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

    function cargarLeyesNormativas() {
        if (!elements.selectEnsayoLeyNormativa) {
            return;
        }

        elements.selectEnsayoLeyNormativa.innerHTML = '<option value="">Seleccionar normativa...</option>';

        catalogs.leyes.forEach(ley => {
            const option = document.createElement('option');
            option.value = ley.id;
            option.textContent = ley.text;
            option.dataset.codigo = ley.codigo;
            option.dataset.grupo = ley.grupo;
            elements.selectEnsayoLeyNormativa.appendChild(option);
        });
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
                if (ensayo && ensayo.matriz_codigo) {
                    const matrizCodigo = ensayo.matriz_codigo.toString().trim();
                    await cargarOpcionesComponentes(matrizCodigo);
                }
            }
            preseleccionarComponentesDeEnsayo(ensayoItemId, false);
        };
    }

    function handleCambioEnsayoModal(event) {
        const select = event.target;
        const option = select.options[select.selectedIndex];

        if (!option) {
            if (elements.campoCodigoEnsayo) {
                elements.campoCodigoEnsayo.value = '';
            }
            if (elements.infoMetodoEnsayo) {
                elements.infoMetodoEnsayo.textContent = '';
            }
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
    }

    function handleCambioComponenteModal() {
        const selectedOptions = obtenerOpcionesSeleccionadasComponente();

        actualizarResumenComponentesSeleccionados(selectedOptions);
        aplicarEstadoCamposComponente(selectedOptions);

        if (selectedOptions.length !== 1) {
            limpiarCamposComponente();
            return;
        }

        aplicarDatosComponenteDesdeOption(selectedOptions[0]);
    }

    function limpiarCamposComponente() {
        if (elements.campoCodigoComponente) {
            elements.campoCodigoComponente.value = '';
        }
        if (elements.campoPrecioComponente) {
            elements.campoPrecioComponente.value = '0.00';
        }
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
            const precio = parseFloat(dataset.precio ?? dataset.precioRaw ?? '0') || 0;
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

    function construirMetaComponente(dataset = {}) {
        const partes = [];
        const matrizEtiqueta = construirEtiquetaMatriz(dataset);
        if (matrizEtiqueta) {
            partes.push(`Matriz: ${matrizEtiqueta}`);
        }
        if (dataset.metodoCodigo) {
            partes.push(`Método: ${dataset.metodoCodigo}${dataset.metodoDescripcion ? ` - ${dataset.metodoDescripcion}` : ''}`);
        }
        if (dataset.unidadMedida) {
            partes.push(`U.M.: ${dataset.unidadMedida}`);
        }
        const precio = parseFloat(dataset.precio ?? dataset.precioRaw);
        if (!isNaN(precio) && precio > 0) {
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
                    ${escapeHtml(descripcion)}
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
        // Limpiar el prefijo [AGRUPADOR] si existe
        descripcion = descripcion.replace(/^\[AGRUPADOR\]\s*/, '');
        const esAgrupador = dataset.esAgrupador === '1';
        
        // Para el template de selección, mostrar descripción con indicador si es agrupador
        if (esAgrupador) {
            return escapeHtml(descripcion) + ' [AGRUPADOR]';
        }
        return escapeHtml(descripcion);
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
        let metodoCodigo = selectedOption && selectedOption.dataset ? selectedOption.dataset.metodoCodigo : '';
        let metodoDesc = selectedOption && selectedOption.dataset ? selectedOption.dataset.metodoDescripcion : '';
        let unidadMedida = selectedOption && selectedOption.dataset ? selectedOption.dataset.unidadMedida : '';
        let limiteEstablecido = selectedOption && selectedOption.dataset ? selectedOption.dataset.limitesEstablecidos : '';
        let precio = selectedOption && selectedOption.dataset ? (selectedOption.dataset.precio || '') : '';

        if (info) {
            info.textContent = '';
            if (metodoCodigo) {
                info.textContent += `Método: ${metodoCodigo}${metodoDesc ? ` - ${metodoDesc}` : ''}`;
            }
            if (unidadMedida) {
                info.textContent += `U.M.: ${unidadMedida}`;
            }
            if (limiteEstablecido) {
                info.textContent += `Límite: ${limiteEstablecido}`;
            }
        }

        // Autocompletar precio del componente
        const precioField = document.getElementById('comp_precio_final');
        if (precioField && precio) {
            const precioNum = parseFloat(precio) || 5000.00;
            precioField.value = precioNum.toFixed(2);
            // console.log('[componente change] precio autocompletado:', precioNum);
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
                <textarea class="form-control nota-contenido" id="${notaId}_contenido" rows="3" placeholder="Escriba el contenido de la nota...">${notaContenido}</textarea>
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
                        contenido: contenido
                    });
                }
            }
        });
        
        return notas;
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
        const descripcion = option ? option.textContent : '';
        const codigo = option ? option.dataset.codigo : '';
        const componentesSugeridos = option && option.dataset && option.dataset.componentes
            ? JSON.parse(option.dataset.componentes)
            : (catalogs.ensayosDefaultsById[muestraId] || []);
        
        // Capturar matriz_codigo y matriz_descripcion del option
        const matrizCodigo = option && option.dataset.matrizCodigo ? option.dataset.matrizCodigo.trim() : null;
        const matrizDescripcion = option && option.dataset.matrizDescripcion ? option.dataset.matrizDescripcion : null;

        const cantidad = toPositiveInt(elements.campoCantidadEnsayo ? elements.campoCantidadEnsayo.value : 1, 1);

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
        });

        state.ensayos.push(nuevoEnsayo);
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

        // Capturar datos de nota del modal de componente
        const notaTipo = document.querySelector('input[name="comp_nota_tipo"]:checked')?.value || 'imprimible';
        const notaContenido = document.getElementById('componente_nota_contenido')?.value || '';

        let agregados = 0;
        let omitidos = 0;

        // Primero procesar todos los items seleccionados (agrupadores y componentes)
        const itemsParaAgregar = [];
        const componentesAsociadosAgregar = new Set(); // Para evitar duplicados
        let agrupadoresAgregados = 0; // Contador de agrupadores agregados
        
        selectedOptions.forEach(option => {
            const analisisId = option.value;
            const esAgrupador = option.dataset.esAgrupador === '1';
            
            // Verificar si el item principal ya existe
            const yaExiste = state.componentes.some(comp => comp.analisis_id?.toString() === analisisId.toString() && comp.ensayo_asociado === ensayoAsociado);
            if (yaExiste) {
                omitidos += 1;
                return;
            }

            // Limpiar el prefijo [AGRUPADOR] de la descripción si existe
            let descripcion = option.textContent.replace(/^\[AGRUPADOR\]\s*/, '');
            const codigo = option.dataset.codigo || '';
            const metodoCodigo = option.dataset.metodoCodigo || '';
            const metodoDescripcion = option.dataset.metodoDescripcion || '';
            const unidadMedida = option.dataset.unidadMedida || '';
            const limiteDeteccion = option.dataset.limitesEstablecidos || '';
            const cantidad = 1;

            let precio = precioManual;
            if (!precio) {
                precio = toPositiveNumber(option.dataset.precio || 0, 0);
            }

            // Agregar el item principal (agrupador o componente)
            itemsParaAgregar.push({
                analisis_id: analisisId,
                descripcion: descripcion,
                codigo: codigo,
                cantidad: cantidad,
                precio: precio,
                metodo_codigo: metodoCodigo,
                metodo_descripcion: metodoDescripcion,
                unidad_medida: unidadMedida,
                limite_deteccion: limiteDeteccion,
            });

            // Si es agrupador, obtener componentes asociados
            if (esAgrupador && option.dataset.componentesAsociados) {
                try {
                    const componentesAsociadosIds = JSON.parse(option.dataset.componentesAsociados);
                    componentesAsociadosIds.forEach(compId => {
                        componentesAsociadosAgregar.add(compId.toString());
                    });
                } catch (e) {
                    // console.error('Error parseando componentes asociados:', e);
                }
            }
        });

        // Agregar el agrupador y sus componentes asociados
        itemsParaAgregar.forEach((item, index) => {
            // Verificar si es agrupador antes de agregar
            const optionOriginal = selectedOptions.find(opt => opt.value === item.analisis_id.toString());
            const esAgrupadorItem = optionOriginal && optionOriginal.dataset.esAgrupador === '1';
            
            state.contador += 1;

            const nuevoComponente = normalizarComponente({
                item: state.contador,
                analisis_id: item.analisis_id,
                descripcion: item.descripcion,
                codigo: item.codigo,
                cantidad: item.cantidad,
                precio: item.precio,
                total: item.precio * item.cantidad,
                ensayo_asociado: ensayoAsociado,
                metodo_codigo: item.metodo_codigo,
                metodo_descripcion: item.metodo_descripcion,
                unidad_medida: item.unidad_medida,
                limite_deteccion: item.limite_deteccion,
                metodo_analisis_id: metodoAnalisisId,
                ley_normativa_id: leyNormativaId,
                nota_tipo: notaTipo,
                nota_contenido: notaContenido,
            });
 
            state.componentes.push(nuevoComponente);
            agregados += 1;
            
            // Si es agrupador, incrementar contador
            if (esAgrupadorItem) {
                agrupadoresAgregados += 1;
            }
        });

        // Agregar componentes asociados de los agrupadores
        let componentesDeAgrupadoresAgregados = 0;
        if (componentesAsociadosAgregar.size > 0) {
            componentesAsociadosAgregar.forEach(compId => {
                // Verificar si el componente ya existe
                const yaExiste = state.componentes.some(comp => 
                    comp.analisis_id?.toString() === compId.toString() && 
                    comp.ensayo_asociado === ensayoAsociado
                );
                
                if (yaExiste) {
                    omitidos += 1;
                    return;
                }

                // Buscar el componente en el catálogo
                const componenteCatalogo = catalogs.componentes.find(c => c.id.toString() === compId.toString());
                if (!componenteCatalogo) {
                    // console.warn('Componente asociado no encontrado en catálogo:', compId);
                    return;
                }

                const descripcion = componenteCatalogo.descripcion || '';
                const codigo = componenteCatalogo.codigo || '';
                const metodoCodigo = componenteCatalogo.metodo_codigo || '';
                const metodoDescripcion = componenteCatalogo.metodo_descripcion || '';
                const unidadMedida = componenteCatalogo.unidad_medida || '';
                const limiteDeteccion = componenteCatalogo.limites_establecidos || '';
                const cantidad = 1;
                const precio = toPositiveNumber(componenteCatalogo.precio || 0, 0);

                state.contador += 1;

                const nuevoComponente = normalizarComponente({
                    item: state.contador,
                    analisis_id: compId,
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
                    nota_tipo: notaTipo,
                    nota_contenido: notaContenido,
                    de_agrupador: true, // Marcar que proviene de un agrupador
                });
 
                state.componentes.push(nuevoComponente);
                agregados += 1;
                componentesDeAgrupadoresAgregados += 1;
            });
        }

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

    function renderFilaEnsayo(ensayo, numeroSecuencial) {
        const cantidadCampo = state.puedeEditar
            ? `<input type="number" class="form-control form-control-sm input-cantidad-ensayo" data-item="${ensayo.item}" value="${formatInt(ensayo.cantidad)}" min="1" step="1">`
            : `<span>${formatInt(ensayo.cantidad)}</span>`;

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

        const botonEditar = state.puedeEditar
            ? `<button type="button" class="btn btn-sm btn-outline-info" onclick="editarEnsayo(${ensayo.item})" title="Editar ensayo">
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
                <td>ENSAYO - ${escapeHtml(ensayo.descripcion || '')}</td>
                <td>-</td>
                <td>${botonEditar}</td>
                <td>${cantidadCampo}</td>
                <td data-ensayo-unitario="${ensayo.item}">${formatCurrency(ensayo.precio)}</td>
                <td data-ensayo-total="${ensayo.item}">${formatCurrency(ensayo.total)}</td>
                <td>${acciones}</td>
            </tr>
        `;
    }

    function renderFilaComponente(componente, ensayo, subindice, numeroEnsayoSecuencial) {
        const itemLabel = `${numeroEnsayoSecuencial}-${subindice}`;

        const cantidadCampo = `<span>${formatInt(componente.cantidad)}</span>`;

        const precioCampo = state.puedeEditar
            ? `<input type="number" class="form-control form-control-sm input-precio-componente" data-item="${componente.item}" value="${formatNumber(componente.precio)}" min="0" step="0.01">`
            : `<span>${formatCurrency(componente.precio)}</span>`;

        const acciones = state.puedeEditar
            ? `<button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem('componente', ${componente.item})">
                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
               </button>`
            : '';

        const botonVer = `<button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalle(${componente.item})">
            <x-heroicon-o-eye style="width: 16px; height: 16px;" />
        </button>`;

        // Indicador sutil si proviene de un agrupador
        const esDeAgrupador = componente.de_agrupador === true;
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

        return `
            <tr data-tipo="componente" data-item="${componente.item}" data-ensayo="${ensayo.item}" class="sortable-componente componente-row componente-row-${ensayo.item} ${claseFila}" style="cursor: ${state.puedeEditar ? 'move' : 'default'};">
                <td>${dragHandleComponente} ${itemLabel}</td>
                <td>${escapeHtml(componente.codigo || '-')}</td>
                <td>ANÁLISIS - ${indicadorAgrupador} ${escapeHtml(componente.descripcion || '')}</td>
                <td><small>${escapeHtml(componente.metodo_descripcion || '-')}</small></td>
                <td>${botonVer}</td>
                <td>${cantidadCampo}</td>
                <td>${precioCampo}</td>
                <td data-componente-total="${componente.item}">${formatCurrency(componente.total)}</td>
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
                option.textContent = comp.descripcion;
                option.dataset.codigo = comp.codigo || '';
                option.dataset.precio = comp.precio || '0';
                option.dataset.unidadMedida = comp.unidad_medida || '';
                option.dataset.metodoCodigo = comp.metodo_codigo || '';
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
        if (selectMuestra) {
            selectMuestra.innerHTML = '<option value="">Seleccionar muestra...</option>';
            
            // Verificar que el catálogo esté cargado
            if (catalogs.ensayos && Array.isArray(catalogs.ensayos)) {
                catalogs.ensayos.forEach(ens => {
                const option = document.createElement('option');
                option.value = ens.id;
                option.textContent = ens.descripcion;
                option.dataset.codigo = ens.codigo || '';
                option.dataset.componentes = JSON.stringify(Array.isArray(ens.componentes_default) ? ens.componentes_default : []);
                option.dataset.matrizCodigo = (ens.matriz_codigo || '').toString().trim();
                option.dataset.matrizDescripcion = ens.matriz_descripcion || '';
                
                if (String(ens.id) === String(ensayo.muestra_id)) {
                    option.selected = true;
                }
                
                selectMuestra.appendChild(option);
                });
                
                // Asegurarse de que el select tenga el valor correcto
                if (ensayo.muestra_id) {
                    selectMuestra.value = String(ensayo.muestra_id);
                }
            } else {
                // console.warn('Catálogo de ensayos no está cargado aún');
                // Intentar cargar las opciones desde el select original si no están disponibles
                if (elements.selectEnsayo && elements.selectEnsayo.options.length > 1) {
                    for (let i = 1; i < elements.selectEnsayo.options.length; i++) {
                        const originalOption = elements.selectEnsayo.options[i];
                        const option = document.createElement('option');
                        option.value = originalOption.value;
                        option.textContent = originalOption.textContent;
                        option.dataset.codigo = originalOption.dataset.codigo || '';
                        option.dataset.componentes = originalOption.dataset.componentes || '[]';
                        option.dataset.matrizCodigo = originalOption.dataset.matrizCodigo || '';
                        option.dataset.matrizDescripcion = originalOption.dataset.matrizDescripcion || '';
                        
                        if (String(originalOption.value) === String(ensayo.muestra_id)) {
                            option.selected = true;
                        }
                        
                        selectMuestra.appendChild(option);
                    }
                    
                    if (ensayo.muestra_id) {
                        selectMuestra.value = String(ensayo.muestra_id);
                    }
                }
            }
        }

        // Cargar leyes/normativas
        const selectLey = document.getElementById('edit_ensayo_ley_normativa');
        if (selectLey) {
            selectLey.innerHTML = '<option value="">Seleccionar normativa...</option>';
            
            // Verificar que el catálogo esté cargado
            const leyesCatalogo = catalogs.leyesNormativas || catalogs.leyes;
            if (leyesCatalogo && Array.isArray(leyesCatalogo)) {
                leyesCatalogo.forEach(ley => {
                    const option = document.createElement('option');
                    // Usar codigo o id dependiendo de qué propiedad tenga
                    option.value = ley.codigo || ley.id || '';
                    // Usar nombre o text dependiendo de qué propiedad tenga
                    option.textContent = ley.nombre || ley.text || '';
                    selectLey.appendChild(option);
                });
            }
        }

        // Llenar campos
        document.getElementById('edit_ensayo_codigo').value = ensayo.codigo || '';
        document.getElementById('edit_ensayo_cantidad').value = formatInt(ensayo.cantidad);
        
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
                } else {
                    // Si no es array, crear una nota con los datos antiguos
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

        // Abrir modal
        if (window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = new window.bootstrap.Modal(modal);
            modalInstance.show();
        }
    }

    function guardarEnsayoEditadoHandler() {
        if (!state.puedeEditar) {
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

        const cantidad = toPositiveInt(document.getElementById('edit_ensayo_cantidad').value, 1);
        
        // Obtener múltiples notas del contenedor
        const notas = obtenerNotasDelContenedor('notasEditEnsayoContainer');
        
        // Para compatibilidad con el backend, guardar como JSON en nota_contenido
        // y el primer tipo en nota_tipo (o null si no hay notas)
        const notaTipo = notas.length > 0 ? notas[0].tipo : null;
        const notaContenido = notas.length > 0 ? JSON.stringify(notas) : null;

        // Actualizar ensayo
        ensayo.muestra_id = muestraId;
        ensayo.descripcion = option.textContent || ensayo.descripcion;
        ensayo.codigo = option.dataset.codigo || ensayo.codigo;
        ensayo.cantidad = cantidad;
        ensayo.nota_tipo = notaTipo;
        ensayo.nota_contenido = notaContenido;
        ensayo.notas = notas; // Guardar también como array para uso interno
        
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
        if (selectMuestra) {
            selectMuestra.innerHTML = '<option value="">Seleccionar muestra...</option>';
            
            // Verificar que el catálogo esté cargado
            if (catalogs.ensayos && Array.isArray(catalogs.ensayos)) {
                catalogs.ensayos.forEach(ens => {
                const option = document.createElement('option');
                option.value = ens.id;
                option.textContent = ens.descripcion;
                option.dataset.codigo = ens.codigo || '';
                option.dataset.componentes = JSON.stringify(Array.isArray(ens.componentes_default) ? ens.componentes_default : []);
                option.dataset.matrizCodigo = (ens.matriz_codigo || '').toString().trim();
                option.dataset.matrizDescripcion = ens.matriz_descripcion || '';
                
                if (String(ens.id) === String(ensayo.muestra_id)) {
                    option.selected = true;
                }
                
                selectMuestra.appendChild(option);
                });
                
                // Asegurarse de que el select tenga el valor correcto
                if (ensayo.muestra_id) {
                    selectMuestra.value = String(ensayo.muestra_id);
                }
            } else {
                // console.warn('Catálogo de ensayos no está cargado aún');
                // Intentar cargar las opciones desde el select original si no están disponibles
                if (elements.selectEnsayo && elements.selectEnsayo.options.length > 1) {
                    for (let i = 1; i < elements.selectEnsayo.options.length; i++) {
                        const originalOption = elements.selectEnsayo.options[i];
                        const option = document.createElement('option');
                        option.value = originalOption.value;
                        option.textContent = originalOption.textContent;
                        option.dataset.codigo = originalOption.dataset.codigo || '';
                        option.dataset.componentes = originalOption.dataset.componentes || '[]';
                        option.dataset.matrizCodigo = originalOption.dataset.matrizCodigo || '';
                        option.dataset.matrizDescripcion = originalOption.dataset.matrizDescripcion || '';
                        
                        if (String(originalOption.value) === String(ensayo.muestra_id)) {
                            option.selected = true;
                        }
                        
                        selectMuestra.appendChild(option);
                    }
                    
                    if (ensayo.muestra_id) {
                        selectMuestra.value = String(ensayo.muestra_id);
                    }
                }
            }
        }

        // Cargar leyes/normativas
        const selectLey = document.getElementById('edit_ensayo_ley_normativa');
        if (selectLey) {
            selectLey.innerHTML = '<option value="">Seleccionar normativa...</option>';
            
            // Verificar que el catálogo esté cargado
            const leyesCatalogo = catalogs.leyesNormativas || catalogs.leyes;
            if (leyesCatalogo && Array.isArray(leyesCatalogo)) {
                leyesCatalogo.forEach(ley => {
                    const option = document.createElement('option');
                    // Usar codigo o id dependiendo de qué propiedad tenga
                    option.value = ley.codigo || ley.id || '';
                    // Usar nombre o text dependiendo de qué propiedad tenga
                    option.textContent = ley.nombre || ley.text || '';
                    selectLey.appendChild(option);
                });
            }
        }

        // Llenar campos
        document.getElementById('edit_ensayo_codigo').value = ensayo.codigo || '';
        document.getElementById('edit_ensayo_cantidad').value = formatInt(ensayo.cantidad);
        
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
                } else {
                    // Si no es array, crear una nota con los datos antiguos
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

        // Abrir modal
        if (window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = new window.bootstrap.Modal(modal);
            modalInstance.show();
        }
    }

    function guardarEnsayoEditadoHandler() {
        if (!state.puedeEditar) {
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

        const cantidad = toPositiveInt(document.getElementById('edit_ensayo_cantidad').value, 1);
        
        // Obtener múltiples notas del contenedor
        const notas = obtenerNotasDelContenedor('notasEditEnsayoContainer');
        
        // Para compatibilidad con el backend, guardar como JSON en nota_contenido
        // y el primer tipo en nota_tipo (o null si no hay notas)
        const notaTipo = notas.length > 0 ? notas[0].tipo : null;
        const notaContenido = notas.length > 0 ? JSON.stringify(notas) : null;

        // Actualizar ensayo
        ensayo.muestra_id = muestraId;
        ensayo.descripcion = option.textContent || ensayo.descripcion;
        ensayo.codigo = option.dataset.codigo || ensayo.codigo;
        ensayo.cantidad = cantidad;
        ensayo.nota_tipo = notaTipo;
        ensayo.nota_contenido = notaContenido;
        ensayo.notas = notas; // Guardar también como array para uso interno
        
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

    function sincronizarTotales() {
        state.ensayos.forEach(ensayo => {
            recalcularPreciosEnsayo(ensayo.item);
        });
    }

    function recalcularPreciosEnsayo(ensayoItemId) {
        const ensayo = state.ensayos.find(e => e.item === ensayoItemId);
        if (!ensayo) {
            return;
        }

        const componentes = state.componentes.filter(c => c.ensayo_asociado === ensayoItemId);

        const sumaComponentes = componentes.reduce((total, componente) => {
            const precio = parseFloat(componente.precio) || 0;
            const cantidad = toPositiveInt(componente.cantidad, 1);
            return total + precio * cantidad;
        }, 0);

        const cantidadEnsayo = toPositiveInt(ensayo.cantidad, 1);
        ensayo.precio = sumaComponentes;
        ensayo.total = sumaComponentes * cantidadEnsayo;
    }

    function actualizarTotalGeneral() {
        const total = state.ensayos.reduce((suma, ensayo) => suma + (parseFloat(ensayo.total) || 0), 0);

        if (elements.totalGeneral) {
            elements.totalGeneral.textContent = formatNumber(total, 2);
        }

        const globalPercent = clampPercent(getDescuentoGlobal());
        const globalDecimal = globalPercent / 100;

        const descuentoGlobalMonto = total * globalDecimal;
        const totalConDescuento = total - descuentoGlobalMonto;

        if (elements.descuentoGlobalPorcentaje) {
            elements.descuentoGlobalPorcentaje.textContent = `${formatNumber(globalPercent, 2)}%`;
        }
        if (elements.descuentoGlobalMonto) {
            elements.descuentoGlobalMonto.textContent = formatNumber(descuentoGlobalMonto, 2);
        }
        if (elements.totalConDescuento) {
            elements.totalConDescuento.textContent = formatNumber(totalConDescuento, 2);
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
                } else {
                    // Formato antiguo: nota simple
                    notas = raw.nota_tipo ? [{ tipo: raw.nota_tipo, contenido: raw.nota_contenido }] : null;
                }
            } catch (e) {
                // No es JSON, es formato antiguo
                notas = raw.nota_tipo ? [{ tipo: raw.nota_tipo, contenido: raw.nota_contenido }] : null;
            }
        }

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
        };
    }

    function normalizarComponente(raw) {
        const item = Number(raw.item) || 0;
        const cantidad = toPositiveInt(raw.cantidad, 1);
        const precio = parseFloat(raw.precio) || 0;
        const total = parseFloat(raw.total) || precio * cantidad;

        return {
            item: item,
            analisis_id: raw.analisis_id || null,
            descripcion: raw.descripcion || '',
            codigo: raw.codigo || '',
            cantidad: cantidad,
            precio: precio,
            total: total,
            tipo: 'componente',
            ensayo_asociado: Number(raw.ensayo_asociado) || 0,
            metodo_analisis_id: raw.metodo_analisis_id || null,
            metodo_codigo: raw.metodo_codigo || null,
            metodo_descripcion: raw.metodo_descripcion || '',
            unidad_medida: raw.unidad_medida || '',
            limite_deteccion: raw.limite_deteccion || null,
            ley_normativa_id: raw.ley_normativa_id || null,
            nota_tipo: raw.nota_tipo || null,
            nota_contenido: raw.nota_contenido || null,
            de_agrupador: raw.de_agrupador === true || raw.de_agrupador === 1 || raw.de_agrupador === '1',
        };
    }

    function calcularContadorInicial(ensayosIniciales, componentesIniciales) {
        const maxEnsayo = (ensayosIniciales || []).reduce((max, ensayo) => Math.max(max, Number(ensayo.item) || 0), 0);
        const maxComponente = (componentesIniciales || []).reduce((max, componente) => Math.max(max, Number(componente.item) || 0), 0);
        return Math.max(maxEnsayo, maxComponente);
    }

    function formatCurrency(valor) {
        const numero = parseFloat(valor) || 0;
        return `$${numero.toFixed(2)}`;
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

    function aplicarRestriccionEdicionSiCorresponde() {
        if (state.puedeEditar || !elements.form) {
            return;
        }

        const aviso = document.createElement('div');
        aviso.className = 'alert alert-info mb-3';
        aviso.textContent = 'La cotización está aprobada y no puede modificarse.';

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

    if (elements.form) {
        elements.form.addEventListener('submit', function () {
            if (elements.ensayosHidden) {
                elements.ensayosHidden.value = JSON.stringify(state.ensayos.map(serializarEnsayo));
            }
            if (elements.componentesHidden) {
                elements.componentesHidden.value = JSON.stringify(state.componentes.map(serializarComponente));
            }
        });
    }

    function serializarEnsayo(ensayo) {
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
        };
    }

    function serializarComponente(componente) {
        return {
            item: componente.item,
            analisis_id: componente.analisis_id,
            descripcion: componente.descripcion,
            codigo: componente.codigo,
            cantidad: componente.cantidad,
            precio: componente.precio,
            total: componente.total,
            tipo: componente.tipo,
            ensayo_asociado: componente.ensayo_asociado,
            metodo_analisis_id: componente.metodo_analisis_id,
            metodo_codigo: componente.metodo_codigo,
            metodo_descripcion: componente.metodo_descripcion,
            unidad_medida: componente.unidad_medida,
            limite_deteccion: componente.limite_deteccion,
            ley_normativa_id: componente.ley_normativa_id,
            nota_tipo: componente.nota_tipo || null,
            nota_contenido: componente.nota_contenido || null,
            de_agrupador: componente.de_agrupador || false,
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

        // Obtener componentes sugeridos del ensayo
        const componentesIds = Array.from(new Set(obtenerComponentesSugeridosDeEnsayo(ensayo).map(id => id.toString())));
        
        // Filtrar solo los que existen en las opciones disponibles
        const opcionesDisponibles = Array.from(elements.selectComponente.options)
            .map(opt => opt.value.toString());
        const componentesIdsFiltrados = componentesIds.filter(id => opcionesDisponibles.includes(id));

        // Preseleccionar usando Select2 de forma estándar
        if (window.$ && window.$('#componente_analisis').length && window.$('#componente_analisis').data('select2')) {
            const valoresActuales = window.$('#componente_analisis').val() || [];
            const valoresCombinados = Array.from(new Set([...valoresActuales, ...componentesIdsFiltrados]));
            window.$('#componente_analisis').val(valoresCombinados).trigger('change');
        } else {
            // Para selects nativos
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
        }
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

