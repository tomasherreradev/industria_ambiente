@php
    $user = Auth::user();
    $role = $user->rol;
    $isInformes = $role == 'informes';
@endphp

<div class="d-none d-lg-block">
    {{-- @dd($informesPorCotizacion) --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Cotización</th>
                            <th>Cliente</th>
                            {{-- <th width="120" class="text-center">Fecha Muestreo</th> --}}
                            <th width="150" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($informesPorCotizacion as $numCoti => $informeData)
                            @php
                                $coti = $informeData['cotizacion'];
                            @endphp
                            <tr>
                                <td colspan="4">
                                    <div class="accordion" id="accordion-{{ $numCoti }}">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading-{{ $numCoti }}">
                                                <div class="d-flex w-100 align-items-center justify-content-between">
                                                    <button class="accordion-button collapsed flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $numCoti }}" aria-expanded="false" aria-controls="collapse-{{ $numCoti }}">
                                                        Cotización #{{ $numCoti }} - {{ $coti->coti_empresa ?? 'N/A' }}
                                                    </button>
                                                    
                                                    <a href="{{ route('informes.pdf-masivo', ['cotizacion' => $numCoti]) }}" class="btn btn-sm btn-outline-secondary ms-2" target="_blank" data-bs-toggle="tooltip" title="Descargar PDF masivo">
                                                        <x-heroicon-o-document-arrow-down style="width: 18px; height: 18px;" />
                                                        PDF masivo
                                                    </a>
                                                </div>
                                            </h2>
                                            <div id="collapse-{{ $numCoti }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $numCoti }}" data-bs-parent="#accordion-{{ $numCoti }}">
                                                <div class="accordion-body p-0">
                                                    <table class="table mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Identificación</th>
                                                                <th>Descripción</th>
                                                                <th>Firma ID</th>
                                                                <th class="text-center">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($informeData['muestras'] as $muestra)
                                                                <tr>
                                                                    <td>{{ $muestra->cotio_identificacion }}</td>
                                                                    <td>{{ $muestra->cotio_descripcion }} (#{{ $muestra->instance_number }})</td>
                                                                    <td>{{ $muestra->identificador_documento_firma ? $muestra->identificador_documento_firma : 'N/A' }}</td>
                                                                    <td class="text-center">
                                                                        <div class="btn-group" role="group">
                                                                            @if($isInformes)
                                                                                <button type="button" class="btn btn-sm btn-outline-primary preview-informe-btn"
                                                                                        data-cotizacion="{{ $numCoti }}"
                                                                                        data-item="{{ $muestra->cotio_item }}"
                                                                                        data-instance="{{ $muestra->instance_number }}"
                                                                                        data-bs-toggle="tooltip" 
                                                                                        title="Vista previa y editar">
                                                                                    <x-heroicon-o-eye style="width: 15px; height: 15px;" />
                                                                                </button>
                                                                                                                                                @endif
                                                            <a href="{{ route('informes.pdf', [
                                                                'cotio_numcoti' => $numCoti,
                                                                'cotio_item' => $muestra->cotio_item,
                                                                'instance_number' => $muestra->instance_number
                                                            ]) }}"
                                                            class="btn btn-sm btn-outline-secondary" 
                                                            target="_blank"
                                                            data-bs-toggle="tooltip" 
                                                            title="{{ $muestra->firmado ? 'Descargar PDF Firmado' : 'Descargar PDF' }}">
                                                            <x-heroicon-o-document-arrow-down style="width: 15px; height: 15px;" />
                                                            @if($muestra->firmado)
                                                                <span class="badge bg-success ms-1" style="font-size: 0.6rem;">✓</span>
                                                            @endif
                                                            </a>
                                                            
                                                                        @if(!$muestra->firmado && Auth::user()->rol == 'firmador')
                                                                            <a href="{{ route('informes.firmar', [
                                                                                'cotio_numcoti' => $numCoti,
                                                                                'cotio_item' => $muestra->cotio_item,
                                                                                'instance_number' => $muestra->instance_number
                                                                            ]) }}"
                                                                            class="btn btn-sm btn-outline-primary" 
                                                                            data-bs-toggle="tooltip" 
                                                                            title="Firmar Informe">
                                                                            <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                                                            </a>
                                                                        @endif
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Vista móvil -->
<div class="d-block d-lg-none">
    <div class="row g-3">
        @foreach($informesPorCotizacion as $numCoti => $informeData)
            @php
                $coti = $informeData['cotizacion'];
                $finales = $informeData['informes_finales'];
                $total = $informeData['total_muestras'];
                $porcentaje = $total > 0 ? round(($finales / $total) * 100) : 0;
            @endphp
            <div class="col-12">
                <div class="card shadow-sm border-start border-4 
                    @if($coti->coti_estado == 'A') border-success
                    @elseif($coti->coti_estado == 'E') border-warning
                    @elseif($coti->coti_estado == 'S') border-danger
                    @else border-secondary @endif">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="card-title mb-1">#{{ $numCoti }}</h5>
                                <span class="badge 
                                    @if($coti->coti_estado == 'A') bg-success
                                    @elseif($coti->coti_estado == 'E') bg-warning
                                    @elseif($coti->coti_estado == 'S') bg-danger
                                    @else bg-secondary @endif">
                                    {{ trim($coti->coti_estado) }}
                                </span>
                            </div>
                            <div>
                                <!-- Botón PDF Masivo en móvil -->
                                <a href="{{ route('informes.pdf-masivo', ['cotizacion' => $numCoti]) }}" 
                                   class="btn btn-sm btn-outline-secondary ms-2" 
                                   target="_blank"
                                   data-bs-toggle="tooltip" 
                                   title="Descargar PDF masivo">
                                    <x-heroicon-o-document-arrow-down style="width: 15px; height: 15px;" />
                                </a>
                            </div>
                        </div>
                        
                        <h6 class="card-subtitle mb-2 text-muted">{{ $coti->coti_empresa }}</h6>
                        
                        @if($coti->coti_establecimiento)
                            <p class="small mb-1"><i class="fas fa-building me-1"></i> {{ $coti->coti_establecimiento }}</p>
                        @endif
                        
                        
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="small text-muted me-2">
                                    <i class="fas fa-flask me-1"></i> {{ $coti->matriz->matriz_descripcion ?? 'N/A' }}
                                </span>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" 
                                        data-bs-target="#collapseMobile-{{ $numCoti }}" 
                                        aria-expanded="false" 
                                        aria-controls="collapseMobile-{{ $numCoti }}">
                                    <x-heroicon-o-chevron-down style="width: 15px; height: 15px;" />
                                </button>
                            </div>
                        </div>
                        
                        <!-- Contenido desplegable para móvil -->
                        <div class="collapse mt-3" id="collapseMobile-{{ $numCoti }}">
                            <div class="card card-body bg-light">
                                <h6 class="mb-3">Muestras</h6>
                                <ul class="list-group list-group-flush">
                                    @foreach($informeData['muestras'] as $muestra)
                                        <li class="list-group-item bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="fw-bold">{{ $muestra->cotio_identificacion }}</span><br>
                                                    <small>{{ $muestra->cotio_descripcion }} - {{ $muestra->instance_number }}</small>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary preview-informe-btn"
                                                            data-cotizacion="{{ $numCoti }}"
                                                            data-item="{{ $muestra->cotio_item }}"
                                                            data-instance="{{ $muestra->instance_number }}"
                                                            data-bs-toggle="tooltip" 
                                                            title="Vista previa y editar">
                                                        <x-heroicon-o-eye style="width: 15px; height: 15px;" />
                                                    </button>
                                                    <a href="{{ route('informes.pdf', [
                                                        'cotio_numcoti' => $numCoti,
                                                        'cotio_item' => $muestra->cotio_item,
                                                        'instance_number' => $muestra->instance_number
                                                    ]) }}"
                                                    class="btn btn-sm btn-outline-secondary" 
                                                    target="_blank"
                                                    data-bs-toggle="tooltip" 
                                                    title="{{ $muestra->firmado ? 'Descargar PDF Firmado' : 'Descargar PDF' }}">
                                                    <x-heroicon-o-document-arrow-down style="width: 15px; height: 15px;" />
                                                    @if($muestra->firmado)
                                                        <span class="badge bg-success ms-1" style="font-size: 0.6rem;">✓</span>
                                                    @endif
                                                    </a>
                                                    
                                                    @if(!$muestra->firmado)
                                                    <a href="{{ route('informes.firmar', [
                                                        'cotio_numcoti' => $numCoti,
                                                        'cotio_item' => $muestra->cotio_item,
                                                        'instance_number' => $muestra->instance_number
                                                    ]) }}"
                                                    class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="tooltip" 
                                                    title="Firmar Informe">
                                                    <x-heroicon-o-pencil style="width: 15px; height: 15px;" />
                                                    </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="d-flex justify-content-center mt-4">
    {{ $pagination->links() }}
</div>


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
                    
                    <!-- Pestañas para Muestra y Análisis -->
                    <ul class="nav nav-tabs mb-4" id="informeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="muestra-tab" data-bs-toggle="tab" data-bs-target="#muestra" type="button" role="tab">
                                <i class="fas fa-flask me-2"></i>Muestra
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="analisis-tab" data-bs-toggle="tab" data-bs-target="#analisis" type="button" role="tab">
                                <i class="fas fa-microscope me-2"></i>Análisis
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="informeTabsContent">
                        <!-- Pestaña de Muestra -->
                        <div class="tab-pane fade show active" id="muestra" role="tabpanel">
                            <!-- Información de Identificación -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de Identificación</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Identificación de Muestra</label>
                                                <input type="text" class="form-control" id="cotio_identificacion" name="cotio_identificacion" placeholder="Identificación de la muestra">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Vehículo Asignado</label>
                                                <select class="form-select" id="vehiculo_asignado" name="vehiculo_asignado">
                                                    <option value="">Seleccione un vehículo</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Ubicación</h6>
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

                            <!-- Imagen -->
                            @if($muestra->image)
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-image me-2"></i>Imagen de la Muestra</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="imagePreview" class="mt-2">
                                                <img src="{{ Storage::url('images/' . $muestra->image) }}" alt="Imagen de la muestra" class="img-fluid rounded shadow-sm" style="max-height: 300px;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

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
                                                        <!-- Se llenará dinámicamente -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pestaña de Análisis -->
                        <div class="tab-pane fade" id="analisis" role="tabpanel">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-microscope me-2"></i>Análisis</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="analisisTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Análisis</th>
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
                                                <!-- Se llenará dinámicamente -->
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
// Hacer que initMap esté disponible globalmente
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
    // Función para actualizar el mapa
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
            alert('No se pudo cargar el mapa. Verifica la conexión o la clave de la API.');
        };
        document.head.appendChild(script);
    } else {
        // Si el script ya está cargado, inicializa el mapa directamente
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

    

    // Función para cargar vehículos
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

    // Cargar vehículos al iniciar
    cargarVehiculos();

    // Función para cargar datos del informe en el modal
    function cargarDatosInforme(cotizacion, item, instance) {
        fetch(`/informes-api/${cotizacion}/${item}/${instance}`)
            .then(response => response.json())
            .then(data => {
                // Llenar información general
                document.getElementById('modalCotizacionNumero').textContent = `Cotización #${data.cotizacion.coti_num}`;
                document.getElementById('modalClienteInfo').innerHTML = `
                    <strong>${data.cotizacion.coti_empresa}</strong><br>
                    ${data.cotizacion.coti_establecimiento ? data.cotizacion.coti_establecimiento : ''}
                `;
                document.getElementById('modalDescripcion').textContent = data.muestra.cotio_descripcion + ' ' + data.muestra.instance_number || 'Sin descripción';
                document.getElementById('modalFechaMuestreo').textContent = `Muestreo: ${data.muestra.fecha_muestreo ? new Date(data.muestra.fecha_muestreo).toLocaleDateString() : 'No especificada'}`;
                document.getElementById('modalMatrizInfo').textContent = `Matriz: ${data.cotizacion.matriz.matriz_descripcion}`;
                document.getElementById('modalEstado').textContent = `Estado: ${data.muestra.cotio_estado_analisis || 'No especificado'}`;
                
                // Llenar campos de identificación
                document.getElementById('cotio_identificacion').value = data.muestra.cotio_identificacion || '';
                document.getElementById('vehiculo_asignado').value = data.muestra.vehiculo_asignado || '';
                
                // Llenar ubicación y cargar mapa
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
                
                // Llenar análisis
                const analisisTbody = document.querySelector('#analisisTable tbody');
                analisisTbody.innerHTML = '';
                
                if (data.analisis && data.analisis.length > 0) {
                    data.analisis.forEach(analisis => {
                        analisisTbody.innerHTML += `
                        <tr data-analisis-id="${analisis.id}">
                            <td>${analisis.cotio_descripcion || 'Análisis'}</td>
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
                    analisisTbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay análisis registrados</td></tr>';
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
    
    // Función para guardar cambios
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

        console.log('Datos a enviar:', formData); // Para depuración

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
