<!-- Modal para vista previa editable -->
<div class="modal fade" id="informePreviewModal" tabindex="-1" aria-labelledby="informePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="informePreviewModalLabel">
                    <i class="fas fa-file-alt me-2"></i>Editar Informe Completo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="informeForm">
                    <!-- Encabezado del Informe -->
                    <div class="row mb-4 bg-light p-3 rounded">
                        <div class="col-md-6">
                            <h4 class="text-primary mb-3" id="modalCotizacionNumero"></h4>
                            <div class="mb-2" id="modalClienteInfo"></div>
                            <div class="text-muted" id="modalDescripcion"></div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="mb-2" id="modalFechaMuestreo"></div>
                            <div class="mb-2" id="modalMatrizInfo"></div>
                            <div class="badge bg-success" id="modalEstado"></div>
                        </div>
                    </div>
                    
                    <!-- Pesta├▒as para Muestra y An├ílisis -->
                    <ul class="nav nav-tabs mb-4" id="informeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="muestra-tab" data-bs-toggle="tab" data-bs-target="#muestra" type="button" role="tab">
                                <i class="fas fa-flask me-2"></i>Muestra
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="analisis-tab" data-bs-toggle="tab" data-bs-target="#analisis" type="button" role="tab">
                                <i class="fas fa-microscope me-2"></i>An├ílisis
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="informeTabsContent">
                        <!-- Pesta├▒a de Muestra -->
                        <div class="tab-pane fade show active" id="muestra" role="tabpanel">
                            <!-- Informaci├│n de Identificaci├│n -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informaci├│n de Identificaci├│n</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Identificaci├│n de Muestra</label>
                                                <input type="text" class="form-control" id="cotio_identificacion" name="cotio_identificacion" placeholder="Identificaci├│n de la muestra">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Veh├¡culo Asignado</label>
                                                <select class="form-select" id="vehiculo_asignado" name="vehiculo_asignado">
                                                    <option value="">Seleccione un veh├¡culo</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Ubicaci├│n</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Latitud</label>
                                                    <input type="text" class="form-control" id="latitud" name="latitud" placeholder="Latitud">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Longitud</label>
                                                    <input type="text" class="form-control" id="longitud" name="longitud" placeholder="Longitud">
                                                </div>
                                            </div>
                                            <div id="mapa" style="height: 300px;" class="rounded border"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Variables de Campo -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Variables de Campo</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="variablesTable">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Variable</th>
                                                            <th>Valor</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Se llenar├í din├ímicamente -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pesta├▒a de An├ílisis -->
                        <div class="tab-pane fade" id="analisis" role="tabpanel">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-microscope me-2"></i>An├ílisis</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="analisisTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>An├ílisis</th>
                                                    <th>Resultado 1</th>
                                                    <th>Obs. 1</th>
                                                    <th>Resultado 2</th>
                                                    <th>Obs. 2</th>
                                                    <th>Resultado 3</th>
                                                    <th>Obs. 3</th>
                                                    <th>Resultado Final</th>
                                                    <th>Obs. Final</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Se llenar├í din├ímicamente -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cerrar
                </button>
                <button type="button" class="btn btn-primary" id="guardarCambiosBtn">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
                <button type="button" class="btn btn-success" id="generarPdfBtn">
                    <i class="fas fa-file-pdf me-2"></i>Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #informePreviewModal .modal-xl {
        max-width: 95%;
    }
    #variablesTable input {
        min-width: 100px;
    }
    .card-header h6 {
        font-size: 1.1rem;
        font-weight: 500;
    }
    .form-label {
        font-weight: 500;
    }
    #mapa {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }
    .nav-tabs .nav-link {
        color: #495057;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        font-weight: 500;
    }
    .table th {
        font-weight: 500;
    }
    .badge {
        font-size: 0.9rem;
        padding: 0.5em 1em;
    }
</style>


<script>
// Hacer que initMap est├® disponible globalmente
window.initMap = function() {
    const defaultLocation = { lat: -33.4489, lng: -70.6693 }; // Santiago, Chile
    const mapElement = document.getElementById('mapa');
    
    if (!mapElement) return;

    const map = new google.maps.Map(mapElement, {
        zoom: 13,
        center: defaultLocation,
    });

    const marker = new google.maps.Marker({
        map: map,
        draggable: true,
    });

    // Evento cuando se arrastra el marcador
    marker.addListener('dragend', function() {
        const position = marker.getPosition();
        document.getElementById('latitud').value = position.lat();
        document.getElementById('longitud').value = position.lng();
    });

    // Evento de clic en el mapa
    map.addListener('click', function(event) {
        const position = event.latLng;
        marker.setPosition(position);
        document.getElementById('latitud').value = position.lat();
        document.getElementById('longitud').value = position.lng();
    });

    // Guardar referencias en el objeto window para uso posterior
    window.mapInstance = map;
    window.mapMarker = marker;
};

document.addEventListener('DOMContentLoaded', function() {
    // Funci├│n para actualizar el mapa
    function actualizarMapa(lat, lng) {
        if (lat && lng && window.mapInstance && window.mapMarker) {
            const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
            window.mapMarker.setPosition(position);
            window.mapInstance.setCenter(position);
        }
    }

    // Cargar el script de Google Maps
    function loadGoogleMaps() {
    if (!document.querySelector('script[src*="maps.googleapis.com"]')) {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key={{ config('app.GOOGLE_API_KEY') }}&callback=initMap&zoom=13`;
        script.async = true;
        script.defer = true;
        script.onerror = function() {
            console.error('Error al cargar Google Maps API');
            alert('No se pudo cargar el mapa. Verifica la conexi├│n o la clave de la API.');
        };
        document.head.appendChild(script);
    } else {
        // Si el script ya est├í cargado, inicializa el mapa directamente
        if (typeof google !== 'undefined') {
            initMap();
        }
    }
}

    // Manejar cambios en latitud y longitud
    document.getElementById('latitud').addEventListener('change', function() {
        const lat = this.value;
        const lng = document.getElementById('longitud').value;
        actualizarMapa(lat, lng);
    });

    document.getElementById('longitud').addEventListener('change', function() {
        const lng = this.value;
        const lat = document.getElementById('latitud').value;
        actualizarMapa(lat, lng);
    });

    

    // Funci├│n para cargar veh├¡culos
    function cargarVehiculos() {
        fetch('/api/vehiculos')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('vehiculo_asignado');
                data.forEach(vehiculo => {
                    const option = document.createElement('option');
                    option.value = vehiculo.id;
                    option.textContent = `${vehiculo.marca} ${vehiculo.modelo} (${vehiculo.patente})`;
                    select.appendChild(option);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    // Cargar veh├¡culos al iniciar
    cargarVehiculos();

    // Funci├│n para cargar datos del informe en el modal
    function cargarDatosInforme(cotizacion, item, instance) {
        fetch(`/informes-api/${cotizacion}/${item}/${instance}`)
            .then(response => response.json())
            .then(data => {
                // Llenar informaci├│n general
                document.getElementById('modalCotizacionNumero').textContent = `Cotizaci├│n #${data.cotizacion.coti_num}`;
                document.getElementById('modalClienteInfo').innerHTML = `
                    <strong>${data.cotizacion.cliente_etiqueta || data.cotizacion.coti_empresa || 'N/A'}</strong><br>
                    ${data.cotizacion.cliente_establecimiento ? data.cotizacion.cliente_establecimiento : (data.cotizacion.coti_establecimiento || '')}
                `;
                document.getElementById('modalDescripcion').textContent = data.muestra.cotio_descripcion + ' ' + data.muestra.instance_number || 'Sin descripci├│n';
                document.getElementById('modalFechaMuestreo').textContent = `Muestreo: ${data.muestra.fecha_muestreo ? new Date(data.muestra.fecha_muestreo).toLocaleDateString() : 'No especificada'}`;
                document.getElementById('modalMatrizInfo').textContent = `Matriz: ${data.cotizacion.matriz.matriz_descripcion}`;
                document.getElementById('modalEstado').textContent = `Estado: ${data.muestra.cotio_estado_analisis || 'No especificado'}`;
                
                // Llenar campos de identificaci├│n
                document.getElementById('cotio_identificacion').value = data.muestra.cotio_identificacion || '';
                document.getElementById('vehiculo_asignado').value = data.muestra.vehiculo_asignado || '';
                
                // Llenar ubicaci├│n y cargar mapa
                document.getElementById('latitud').value = data.muestra.latitud || '';
                document.getElementById('longitud').value = data.muestra.longitud || '';
                loadGoogleMaps();
                if (data.muestra.latitud && data.muestra.longitud) {
                    actualizarMapa(data.muestra.latitud, data.muestra.longitud);
                }
                
                // Mostrar imagen si existe
                if (data.muestra.image) {
                    const preview = document.getElementById('imagePreview');
                    // preview.innerHTML = `<img src="${data.muestra.image}" class="img-fluid rounded">`;
                }
                
                // Llenar variables de campo
                const variablesTbody = document.querySelector('#variablesTable tbody');
                variablesTbody.innerHTML = '';
                
                console.log('Datos de la muestra:', data.muestra);
                console.log('Variables de campo:', data.muestra.valores_variables);
                
                if (data.muestra.valores_variables && data.muestra.valores_variables.length > 0) {
                    console.log('Procesando variables...');
                    data.muestra.valores_variables.forEach(variable => {
                        console.log('Variable actual:', variable);
                        variablesTbody.innerHTML += `
                        <tr data-variable-id="${variable.id}">
                            <td>${variable.variable || 'Variable'}</td>
                            <td>
                                <input type="text" class="form-control form-control-sm" 
                                       value="${variable.valor || ''}" 
                                       name="variable_valor">
                            </td>
                            <td>${variable.unidad || ''}</td>
                        </tr>
                        `;
                    });
                } else {
                    console.log('No se encontraron variables');
                    variablesTbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay variables de campo registradas</td></tr>';
                }
                
                // Llenar an├ílisis
                const analisisTbody = document.querySelector('#analisisTable tbody');
                analisisTbody.innerHTML = '';
                
                if (data.analisis && data.analisis.length > 0) {
                    data.analisis.forEach(analisis => {
                        analisisTbody.innerHTML += `
                        <tr data-analisis-id="${analisis.id}">
                            <td>${analisis.cotio_descripcion || 'An├ílisis'}</td>
                            <td><input type="text" class="form-control form-control-sm" value="${analisis.resultado || ''}" name="analisis_resultado"></td>
                            <td><textarea class="form-control form-control-sm" name="analisis_observacion_resultado">${analisis.observacion_resultado || ''}</textarea></td>
                            <td><input type="text" class="form-control form-control-sm" value="${analisis.resultado_2 || ''}" name="analisis_resultado_2"></td>
                            <td><textarea class="form-control form-control-sm" name="analisis_observacion_resultado_2">${analisis.observacion_resultado_2 || ''}</textarea></td>
                            <td><input type="text" class="form-control form-control-sm" value="${analisis.resultado_3 || ''}" name="analisis_resultado_3"></td>
                            <td><textarea class="form-control form-control-sm" name="analisis_observacion_resultado_3">${analisis.observacion_resultado_3 || ''}</textarea></td>
                            <td><input type="text" class="form-control form-control-sm" value="${analisis.resultado_final || ''}" name="analisis_resultado_final"></td>
                            <td><textarea class="form-control form-control-sm" name="analisis_observacion_resultado_final">${analisis.observacion_resultado_final || ''}</textarea></td>
                        </tr>
                        `;
                    });
                } else {
                    analisisTbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay an├ílisis registrados</td></tr>';
                }
                
                // Configurar botones
                document.getElementById('guardarCambiosBtn').onclick = function() {
                    guardarCambios(cotizacion, item, instance);
                };
                
                document.getElementById('generarPdfBtn').onclick = function() {
                    window.open(`/informes/${cotizacion}/${item}/${instance}/pdf`, '_blank');
                };
                
                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('informePreviewModal'));
                modal.show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cargar los datos del informe');
            });
    }
    
    // Funci├│n para guardar cambios
    function guardarCambios(cotizacion, item, instance) {
        const formData = {
            muestra: {
                resultado: document.getElementById('resultado')?.value || '',
                resultado_2: document.getElementById('resultado_2')?.value || '',
                resultado_3: document.getElementById('resultado_3')?.value || '',
                resultado_final: document.getElementById('resultado_final')?.value || '',
                observaciones_generales: document.getElementById('observaciones_generales')?.value || '',
                observacion_resultado: document.getElementById('observacion_resultado')?.value || '',
                observacion_resultado_2: document.getElementById('observacion_resultado_2')?.value || '',
                observacion_resultado_3: document.getElementById('observacion_resultado_3')?.value || '',
                observacion_resultado_final: document.getElementById('observacion_resultado_final')?.value || '',
                cotio_identificacion: document.getElementById('cotio_identificacion')?.value || '',
                vehiculo_asignado: document.getElementById('vehiculo_asignado')?.value || '',
                latitud: document.getElementById('latitud')?.value || '',
                longitud: document.getElementById('longitud')?.value || ''
            },
            variables: Array.from(document.querySelectorAll('#variablesTable tbody tr')).map(row => ({
                id: row.dataset.variableId,
                valor: row.querySelector('input[name="variable_valor"]')?.value || '',
                observaciones: row.querySelector('textarea[name="variable_observaciones"]')?.value || ''
            })),
            analisis: Array.from(document.querySelectorAll('#analisisTable tbody tr')).map(row => ({
                id: row.dataset.analisisId,
                resultado: row.querySelector('input[name="analisis_resultado"]')?.value || '',
                observacion_resultado: row.querySelector('textarea[name="analisis_observacion_resultado"]')?.value || '',
                resultado_2: row.querySelector('input[name="analisis_resultado_2"]')?.value || '',
                observacion_resultado_2: row.querySelector('textarea[name="analisis_observacion_resultado_2"]')?.value || '',
                resultado_3: row.querySelector('input[name="analisis_resultado_3"]')?.value || '',
                observacion_resultado_3: row.querySelector('textarea[name="analisis_observacion_resultado_3"]')?.value || '',
                resultado_final: row.querySelector('input[name="analisis_resultado_final"]')?.value || '',
                observacion_resultado_final: row.querySelector('textarea[name="analisis_observacion_resultado_final"]')?.value || ''
            }))
        };

        console.log('Datos a enviar:', formData); // Para depuraci├│n

        fetch(`/informes-api/${cotizacion}/${item}/${instance}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cambios guardados exitosamente');
                // Recargar los datos
                // cargarDatosInforme(cotizacion, item, instance);
            } else {
                alert('Error al guardar los cambios: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar los cambios');
        });
    }

    // Configurar botones de vista previa
    document.querySelectorAll('.preview-informe-btn').forEach(button => {
        button.addEventListener('click', function() {
            const cotizacion = this.getAttribute('data-cotizacion');
            const item = this.getAttribute('data-item');
            const instance = this.getAttribute('data-instance');
            cargarDatosInforme(cotizacion, item, instance);
        });
    });
});
</script>
