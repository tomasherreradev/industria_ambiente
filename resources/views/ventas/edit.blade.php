@extends('layouts.app')

@section('content')
<div id="cotizacionLoadingOverlay" class="cotizacion-loading-overlay">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h4 mb-0">
                        Editar Cotización #{{ $cotizacion->coti_num }}
                        @if($cotizacion->coti_version != 1)
                            <small class="text-muted ms-2">.{{ $cotizacion->coti_version ?? 1 }}</small>
                        @endif
                    </h2>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select id="selectorVersion" class="form-select form-select-sm" style="width: auto; min-width: 200px;">
                        <option value="">Cargando versiones...</option>
                    </select>
                    <a href="{{ route('ventas.index') }}" class="btn btn-secondary">
                        <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" />
                        Volver
                    </a>
                </div>
            </div>

            <!-- Mensajes de éxito y error -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error:</strong>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <form method="POST" action="{{ route('ventas.update', $cotizacion->coti_num) }}" id="cotizacionForm">
                        @csrf
                        @method('PUT')
                        
                        <!-- Header con información básica -->
                        <div class="border-bottom px-4 py-3 bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <label for="cliente_codigo" class="form-label fw-semibold mb-1">Cliente:</label>
                                    <div class="position-relative" id="clienteBuscadorWrapper">
                                        <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" id="cliente_codigo" name="coti_codigocli" 
                                           value="{{ trim($cotizacion->coti_codigocli) }}" placeholder="Escribe nombre o código..." autocomplete="off" required>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" id="btnBuscarCliente">
                                                <x-heroicon-o-magnifying-glass style="width: 14px; height: 14px;" />
                                            </button>
                                        </div>
                                        <div class="dropdown-menu w-100 shadow-sm p-0" id="clienteResultados"></div>
                                    </div>
                                    <!-- Campos hidden para datos del cliente -->
                                    <input type="hidden" id="cliente_razon_social_hidden" name="cliente_razon_social" value="{{ $cotizacion->coti_empresa }}">
                                    <input type="hidden" id="cliente_direccion_hidden" name="cliente_direccion" value="{{ $cotizacion->coti_direccioncli }}">
                                    <input type="hidden" id="cliente_localidad_hidden" name="cliente_localidad" value="{{ $cotizacion->coti_localidad }}">
                                    <input type="hidden" id="cliente_cuit_hidden" name="cliente_cuit" value="{{ $cotizacion->coti_cuit }}">
                                    <input type="hidden" id="cliente_codigo_postal_hidden" name="cliente_codigo_postal" value="{{ $cotizacion->coti_codigopostal }}">
                                    <input type="hidden" id="cliente_telefono_hidden" name="cliente_telefono" value="{{ $cotizacion->coti_telefono }}">
                                    <input type="hidden" id="cliente_correo_hidden" value="{{ $cotizacion->coti_mail1 }}">
                                    <input type="hidden" id="cliente_sector_hidden" value="{{ trim($cotizacion->coti_sector ?? '') }}">
                                    <input type="hidden" id="cliente_descuento_hidden" value="{{ number_format($descuentoCliente ?? 0, 2, '.', '') }}" data-descuento-global="{{ number_format($descuentoGlobalCliente ?? 0, 2, '.', '') }}">
                                    <input type="hidden" id="ensayos_data" name="ensayos_data">
                                    <input type="hidden" id="componentes_data" name="componentes_data">
                                </div>
                                <div class="col-md-4">
                                    <label for="cliente_nombre" class="form-label fw-semibold mb-1">&nbsp;</label>
                                    <input type="text" class="form-control form-control-sm" id="cliente_nombre" 
                                           value="{{ $cotizacion->coti_empresa }}" placeholder="Seleccione un cliente" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label for="sucursal" class="form-label fw-semibold mb-1">Sucursal:</label>
                                    <input type="text" class="form-control form-control-sm" id="sucursal" name="coti_codigosuc"
                                           value="{{ $cotizacion->coti_codigosuc }}">
                                </div>
                                <div class="col-md-2">
                                    <label for="numero" class="form-label fw-semibold mb-1">Nro:</label>
                                    <input type="text" class="form-control form-control-sm" id="numero" name="coti_num" 
                                           value="{{ $cotizacion->coti_num }}" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label for="Para" class="form-label fw-semibold mb-1">Para:</label>
                                    <div id="coti_para_wrapper">
                                        <input type="text" class="form-control form-control-sm" id="coti_para" name="coti_para" 
                                               value="{{ old('coti_para', $cotizacion->coti_para) }}" placeholder="Empresa relacionada...">
                                        <select class="form-control form-control-sm d-none" id="coti_para_select" name="coti_para">
                                            <option value="">Seleccionar empresa relacionada...</option>
                                        </select>
                                        <input type="hidden" id="coti_cli_empresa" name="coti_cli_empresa" value="{{ old('coti_cli_empresa', $cotizacion->coti_cli_empresa) }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navegación de solapas -->
                        <ul class="nav nav-tabs nav-tabs-custom" id="cotizacionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" 
                                        data-bs-target="#general" type="button" role="tab">
                                    General
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="gestion-tab" data-bs-toggle="tab" 
                                        data-bs-target="#gestion" type="button" role="tab">
                                    Gestión
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="empresa-tab" data-bs-toggle="tab" 
                                        data-bs-target="#empresa" type="button" role="tab">
                                    Empresa
                                </button>
                            </li>
                        </ul>

                        <!-- Contenido de las solapas -->
                        <div class="tab-content" id="cotizacionTabsContent">
                            <!-- Solapa General -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <div class="p-4">
                                    <!-- Información superior -->
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label for="descripcion" class="form-label">Descripción:</label>
                                            <input type="text" class="form-control" id="descripcion" name="coti_descripcion" 
                                                   value="{{ old('coti_descripcion', $cotizacion->coti_descripcion) }}" placeholder="Descripción de la cotización...">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-primary w-100">Clonar</button>
                                        </div>
                           
                                        <div class="col-md-2">
                                            <label for="fecha_alta" class="form-label">Alta:</label>
                                            <input type="date" class="form-control" id="fecha_alta" name="coti_fechaalta" 
                                                   value="{{ old('coti_fechaalta', $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('Y-m-d') : date('Y-m-d')) }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="fecha_venc" class="form-label">Venc:</label>
                                            <input type="date" class="form-control" id="fecha_venc" name="coti_fechafin"
                                                   value="{{ old('coti_fechafin', $cotizacion->coti_fechafin ? $cotizacion->coti_fechafin->format('Y-m-d') : '') }}">
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <label for="usuario" class="form-label">Usuario:</label>
                                            @php
                                                $usuarioActual = auth()->user();
                                                $usuarioTexto = $usuarioActual
                                                    ? trim(($usuarioActual->usu_codigo ?? '') . ' ' . ($usuarioActual->usu_descripcion ?? ''))
                                                    : 'Usuario no identificado';
                                                $usuarioTexto = trim($usuarioTexto) ?: ($usuarioActual->name ?? 'Usuario no identificado');
                                            @endphp
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="usuario" value="{{ $usuarioTexto }}" readonly>
                                                <button class="btn btn-outline-secondary" type="button" disabled>
                                                    <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Segunda fila -->
                                    <div class="row mb-4">
                                        <div class="col-md-2">
                                            <label for="estado" class="form-label">Estado:</label>
                                            <select class="form-select" id="estado" name="coti_estado">
                                                @php
                                                    $estado = trim($cotizacion->coti_estado);
                                                    $estadoActual = 'E';
                                                    if(str_starts_with($estado, 'A')) {
                                                        $estadoActual = 'A';
                                                    } elseif(str_starts_with($estado, 'R')) {
                                                        $estadoActual = 'R';
                                                    } elseif(str_starts_with($estado, 'P')) {
                                                        $estadoActual = 'P';
                                                    }
                                                @endphp
                                                <option value="E" {{ $estadoActual == 'E' ? 'selected' : '' }}>En Espera</option>
                                                <option value="A" {{ $estadoActual == 'A' ? 'selected' : '' }}>Aprobado</option>
                                                <option value="R" {{ $estadoActual == 'R' ? 'selected' : '' }}>Rechazado</option>
                                                <option value="P" {{ $estadoActual == 'P' ? 'selected' : '' }}>En Proceso</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="cadena_custodia" name="coti_cadena_custodia" value="1" {{ old('coti_cadena_custodia', $cotizacion->coti_cadena_custodia ?? false) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="cadena_custodia">
                                                    Cadena de Custodia
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="muestreo" name="coti_muestreo" value="1" {{ old('coti_muestreo', $cotizacion->coti_muestreo ?? false) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="muestreo">
                                                    Muestreo
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Comentarios -->
                                    <div class="row mb-4">
                                        <div class="col-md-3">
                                            <label for="contacto" class="form-label">Contacto:</label>
                                            <input type="text" class="form-control" id="contacto" name="coti_contacto"
                                                   value="{{ old('coti_contacto', $cotizacion->coti_contacto) }}" placeholder="Nombre del contacto principal">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="correo" class="form-label">Correo:</label>
                                            <input type="email" class="form-control" id="correo" name="coti_mail1"
                                                   value="{{ old('coti_mail1', $cotizacion->coti_mail1) }}" placeholder="correo@cliente.com">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="telefono" class="form-label">Teléfono:</label>
                                            <input type="text" class="form-control" id="telefono" name="coti_telefono" 
                                                   value="{{ old('coti_telefono', $cotizacion->coti_telefono) }}" placeholder="+54 9 11 1234-5678">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="sector" class="form-label">Sector:</label>
                                            <select class="form-select" id="sector" name="coti_sector">
                                                <option value="">Seleccionar sector...</option>
                                                    @foreach($sectoresCliente as $sector)
                                                        @php
                                                            $codigoSector = trim($sector->divis_codigo);
                                                            $sectorActual = trim($cotizacion->coti_sector ?? '');
                                                        @endphp
                                                        <option value="{{ $codigoSector }}" 
                                                                {{ trim((string) old('coti_sector', $sectorActual)) === $codigoSector ? 'selected' : '' }}>
                                                            {{ trim($sector->divis_descripcion) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <label for="comentario" class="form-label">Comentario:</label>
                                            <textarea class="form-control" id="comentario" name="coti_notas" rows="3">{{ $cotizacion->coti_notas }}</textarea>
                                        </div>
                                    </div>

                                    <!-- Sección de Descuentos -->
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h5 class="mb-3">Descuentos</h5>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <label for="descuento" class="form-label">Descuento Global %</label>
                                                    <input type="number" step="0.01" class="form-control" id="descuento" name="descuento" 
                                                           value="{{ old('descuento', $cotizacion->coti_descuentoglobal ?? '0.00') }}" placeholder="0.00">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tabla de Items/Ensayos -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5>Items de la Cotización</h5>
                                                <div>
                                                    <button type="button" id="btnAbrirModalEnsayo" class="btn btn-success btn-sm me-2" data-bs-toggle="modal" data-bs-target="#modalAgregarEnsayo">
                                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                                        Agregar Ensayo
                                                    </button>
                                                    <button type="button" id="btnAbrirModalComponente" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalAgregarComponente">
                                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                                        Agregar Componente
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 80px;">Item</th>
                                                            <th style="width: 120px;">Ensayo</th>
                                                            <th>Título</th>
                                                            <th style="width: 150px;">Método</th>
                                                            <th style="width: 120px;">Detalle</th>
                                                            <th style="width: 80px;">Cantidad</th>
                                                            <th style="width: 100px;">Prec. Unit</th>
                                                            <th style="width: 100px;">Total</th>
                                                            <th style="width: 60px;">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tablaItems"></tbody>
                                                    <tfoot class="table-light">
                                                        <tr>
                                                            <td colspan="7" class="text-end fw-bold">Total:</td>
                                                            <td class="fw-bold">
                                                                <span id="totalGeneral">0.00</span>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td colspan="7" class="text-end text-muted">Descuento global cliente (<span id="descuentoGlobalPorcentaje">0.00%</span>):</td>
                                                            <td class="text-danger fw-semibold">
                                                                -<span id="descuentoGlobalMonto">0.00</span>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td colspan="7" class="text-end fw-bold">Total con descuento:</td>
                                                            <td class="fw-bold">
                                                                <span id="totalConDescuento">0.00</span>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                @include('ventas.partials.cotizacion-approval-fields', ['cotizacion' => $cotizacion])
                            </div>

                            <!-- Solapa Gestión -->
                            <div class="tab-pane fade" id="gestion" role="tabpanel">
                                <div class="p-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="responsable" class="form-label">Responsable:</label>
                                                <input type="text" class="form-control" id="responsable" name="coti_responsable" 
                                                       value="{{ $cotizacion->coti_responsable }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="fecha_aprobado" class="form-label">Fecha Aprobado:</label>
                                                <input type="date" class="form-control" id="fecha_aprobado" name="coti_fechaaprobado" 
                                                       value="{{ $cotizacion->coti_fechaaprobado ? $cotizacion->coti_fechaaprobado->format('Y-m-d') : '' }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="aprobo" class="form-label">Aprobó:</label>
                                                <input type="text" class="form-control" id="aprobo" name="coti_aprobo" 
                                                       value="{{ $cotizacion->coti_aprobo }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fecha_en_curso" class="form-label">Fecha En Curso:</label>
                                                <input type="date" class="form-control" id="fecha_en_curso" name="coti_fechaencurso" 
                                                       value="{{ $cotizacion->coti_fechaencurso ? $cotizacion->coti_fechaencurso->format('Y-m-d') : '' }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="fecha_alta_tecnica" class="form-label">Fecha Alta Técnica:</label>
                                                <input type="date" class="form-control" id="fecha_alta_tecnica" name="coti_fechaaltatecnica" 
                                                       value="{{ $cotizacion->coti_fechaaltatecnica ? $cotizacion->coti_fechaaltatecnica->format('Y-m-d') : '' }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa Empresa -->
                            <div class="tab-pane fade" id="empresa" role="tabpanel">
                                <div class="p-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                            <label for="empresa_nombre" class="form-label">Empresa:</label>
                                            <input type="text" class="form-control" id="empresa_nombre" name="coti_empresa" 
                                                       value="{{ $cotizacion->coti_empresa }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="establecimiento" class="form-label">Establecimiento:</label>
                                                <input type="text" class="form-control" id="establecimiento" name="coti_establecimiento"
                                                       value="{{ $cotizacion->coti_establecimiento }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="direccion_cliente" class="form-label">Dirección Cliente:</label>
                                                <input type="text" class="form-control" id="direccion_cliente" name="coti_direccioncli"
                                                       value="{{ $cotizacion->coti_direccioncli }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="localidad_cliente" class="form-label">Localidad:</label>
                                                <input type="text" class="form-control" id="localidad_cliente" name="coti_localidad"
                                                       value="{{ $cotizacion->coti_localidad }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="partido" class="form-label">Partido:</label>
                                                <input type="text" class="form-control" id="partido" name="coti_partido"
                                                       value="{{ $cotizacion->coti_partido }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="cuit_cliente" class="form-label">CUIT:</label>
                                                <input type="text" class="form-control" id="cuit_cliente" name="coti_cuit"
                                                       value="{{ $cotizacion->coti_cuit }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="codigo_postal_cliente" class="form-label">Código Postal:</label>
                                                <input type="text" class="form-control" id="codigo_postal_cliente" name="coti_codigopostal"
                                                       value="{{ $cotizacion->coti_codigopostal }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="card-footer bg-light border-top">
                            <div class="d-flex justify-content-between">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('ventas.index') }}" class="btn btn-secondary">
                                        <x-heroicon-o-x-mark style="width: 16px; height: 16px;" class="me-1" />
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <x-heroicon-o-check style="width: 16px; height: 16px;" class="me-1" />
                                        Actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

{{--
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Búsqueda de clientes
    const clienteInput = document.getElementById('cliente_codigo');
    const clienteNombre = document.getElementById('cliente_nombre');
    const btnBuscarCliente = document.getElementById('btnBuscarCliente');
    
    let searchTimeout;
    
    // Búsqueda automática mientras se escribe
    clienteInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const termino = this.value.trim();
        
        if (termino.length < 2) {
            return;
        }
        
        searchTimeout = setTimeout(() => {
            buscarClientes(termino);
        }, 300);
    });

    // Búsqueda al hacer clic en el botón
    btnBuscarCliente.addEventListener('click', function() {
        const termino = clienteInput.value.trim();
        if (termino.length >= 2) {
            buscarClientes(termino);
        }
    });

    // Función para buscar clientes
    function buscarClientes(termino) {
        fetch(`/api/clientes/buscar?q=${encodeURIComponent(termino)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    const coincidenciaExacta = data.find(cliente => 
                        cliente.codigo.trim().toLowerCase() === termino.toLowerCase()
                    );
                    
                    if (coincidenciaExacta) {
                        seleccionarCliente(coincidenciaExacta.codigo);
                    } else if (data.length === 1) {
                        seleccionarCliente(data[0].codigo);
                    } else {
                        mostrarOpcionesClientes(data);
                    }
                }
            })
            .catch(error => {
                console.error('Error buscando clientes:', error);
            });
    }

    // Función para seleccionar un cliente
    function seleccionarCliente(codigoCliente) {
        fetch(`/api/clientes/${encodeURIComponent(codigoCliente)}`)
            .then(response => response.json())
            .then(cliente => {
                if (cliente.error) {
                    return;
                }
                
                clienteInput.value = cliente.codigo;
                clienteNombre.value = cliente.razon_social;
                
                // Actualizar campos
                const empresaField = document.getElementById('empresa');
                const direccionField = document.getElementById('direccion_cliente');
                const localidadField = document.getElementById('localidad_cliente');
                const cuitField = document.getElementById('cuit_cliente');
                const codigoPostalField = document.getElementById('codigo_postal_cliente');
                const telefonoField = document.getElementById('telefono');
                
                if (empresaField) empresaField.value = cliente.razon_social || '';
                if (direccionField) direccionField.value = cliente.direccion || '';
                if (localidadField) localidadField.value = cliente.localidad || '';
                if (cuitField) cuitField.value = cliente.cuit || '';
                if (codigoPostalField) codigoPostalField.value = cliente.codigo_postal || '';
                if (telefonoField) telefonoField.value = cliente.telefono || '';
                
                Swal.fire({
                    icon: 'success',
                    title: 'Cliente Seleccionado',
                    text: `Se han actualizado los datos de ${cliente.razon_social}`,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            })
            .catch(error => {
                console.error('Error obteniendo datos del cliente:', error);
            });
    }

    // Función para mostrar opciones de clientes
    function mostrarOpcionesClientes(clientes) {
        const opciones = {};
        clientes.forEach((cliente) => {
            opciones[cliente.codigo] = cliente.text;
        });

        Swal.fire({
            title: 'Seleccionar Cliente',
            text: `Se encontraron ${clientes.length} clientes. Seleccione uno:`,
            input: 'select',
            inputOptions: opciones,
            inputPlaceholder: 'Seleccione un cliente...',
            showCancelButton: true,
            confirmButtonText: 'Seleccionar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Debe seleccionar un cliente';
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                seleccionarCliente(result.value);
            }
        });
    }

    // Validación del formulario
    const form = document.getElementById('cotizacionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const clienteCodigo = document.getElementById('cliente_codigo');
            if (!clienteCodigo.value.trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Validación',
                    text: 'El código de cliente es obligatorio'
                });
                clienteCodigo.focus();
                return;
            }
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: 'Se actualizarán los datos de la cotización',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) {
                    e.preventDefault();
                }
            });
        });
    }

    // Notificaciones de sesión
    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '{{ session("success") }}',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ session("error") }}',
            confirmButtonColor: '#dc3545'
        });
    @endif
});
</script>
--}}

@include('ventas.partials.cotizacion-modals')
@include('ventas.partials.cotizacion-styles')

<script>
    @php
        $configuracionCotizacion = $cotizacionConfig ?? [
            'modo' => 'edit',
            'puedeEditar' => true,
            'ensayosIniciales' => [],
            'componentesIniciales' => [],
        ];
    @endphp
    window.cotizacionConfig = @json($configuracionCotizacion);
    
    // Pasar versión actual desde PHP a JavaScript
    window.versionActual = {{ $cotizacion->coti_version ?? 1 }};
    window.versionSolicitada = {{ request()->get('version', $cotizacion->coti_version ?? 1) }};
</script>

@include('ventas.partials.cotizacion-scripts')

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectorVersion = document.getElementById('selectorVersion');
    const cotiNum = {{ $cotizacion->coti_num }};
    
    if (!selectorVersion) return;
    
    // Obtener versión actual desde la URL o desde la variable global
    const urlParams = new URLSearchParams(window.location.search);
    const versionParam = urlParams.get('version') || (window.versionSolicitada ? String(window.versionSolicitada) : null);
    const versionActual = window.versionActual ? String(window.versionActual) : null;
    
    // Cargar versiones disponibles
    fetch(`/api/cotizaciones/${cotiNum}/versiones`)
        .then(response => response.json())
        .then(versiones => {
            selectorVersion.innerHTML = '';
            let versionSeleccionada = null;
            
            versiones.forEach(version => {
                const option = document.createElement('option');
                option.value = version.version;
                option.textContent = `Versión ${version.version} - ${version.fecha_version}`;
                
                // Determinar qué versión seleccionar
                const versionNum = String(version.version);
                const esVersionSolicitada = versionParam && versionNum === String(versionParam);
                const esVersionActual = !versionParam && version.es_actual;
                
                if (esVersionSolicitada || esVersionActual) {
                    option.selected = true;
                    versionSeleccionada = versionNum;
                    if (version.es_actual && !versionParam) {
                        option.textContent += ' (Actual)';
                    }
                }
                
                selectorVersion.appendChild(option);
            });
            
            // Los items ya fueron cargados correctamente desde el backend
            // El backend (VentasController::edit) ya procesó la versión y cargó los items en $ensayosIniciales y $componentesIniciales
            // No necesitamos hacer nada más, el script de cotización los cargará automáticamente desde window.cotizacionConfig
            console.log('[Versión] Versión detectada en URL:', {
                versionParam: versionParam,
                versionActual: versionActual,
                versionSeleccionada: versionSeleccionada,
                mensaje: 'Los items ya fueron cargados desde el backend en la carga inicial de la página'
            });
        })
        .catch(error => {
            console.error('Error cargando versiones:', error);
            selectorVersion.innerHTML = '<option value="">Error cargando versiones</option>';
        });
    
    // Función para cargar versión desde API
    function cargarVersionDesdeAPI(version) {
        if (!version) {
            console.warn('[Versión] No se proporcionó versión');
            return;
        }
        
        console.log('[Versión] Iniciando carga de versión:', version);
        
        // Mostrar overlay de carga
        const overlay = document.getElementById('cotizacionLoadingOverlay');
        if (overlay) overlay.style.display = 'flex';
        
        // Cargar datos de la versión
        fetch(`/api/cotizaciones/${cotiNum}/versiones/${version}`)
            .then(response => {
                console.log('[Versión] Respuesta recibida, status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('[Versión] Datos recibidos de la API:', {
                    tieneCotiData: !!data.coti_data,
                    tieneCotioData: !!data.cotio_data,
                    cotioDataEsArray: Array.isArray(data.cotio_data),
                    cotioDataLength: Array.isArray(data.cotio_data) ? data.cotio_data.length : 'N/A',
                    version: data.version,
                    cotioDataSample: Array.isArray(data.cotio_data) && data.cotio_data.length > 0 
                        ? data.cotio_data.slice(0, 2) 
                        : 'vacío o no es array'
                });
                
                // Cargar datos de la cotización en los campos del formulario
                cargarDatosVersion(data.coti_data, data.cotio_data);
            })
            .catch(error => {
                console.error('[Versión] Error cargando versión:', error);
                alert('Error al cargar la versión seleccionada: ' + error.message);
            })
            .finally(() => {
                if (overlay) overlay.style.display = 'none';
            });
    }
    
    // Manejar cambio de versión
    // ENFOQUE NUEVO: Recargar la página completamente para evitar problemas de sincronización
    // Esto garantiza que todos los datos (incluyendo items) se carguen correctamente desde el backend
    selectorVersion.addEventListener('change', function() {
        const version = this.value;
        if (!version) return;
        
        console.log('[Versión] Cambiando a versión:', version);
        
        // Recargar la página con el parámetro de versión
        // El backend (VentasController::edit) ya maneja la carga de versiones históricas
        const url = new URL(window.location.href);
        if (version === String(versionActual)) {
            // Si es la versión actual, quitar el parámetro de versión
            url.searchParams.delete('version');
        } else {
            url.searchParams.set('version', version);
        }
        console.log('[Versión] Recargando página con URL:', url.toString());
        window.location.href = url.toString();
    });
    
    function cargarDatosVersion(cotiData, cotioData) {
        console.log('[Versión] cargarDatosVersion llamado', {
            tieneCotiData: !!cotiData,
            tieneCotioData: !!cotioData,
            cotioDataTipo: typeof cotioData,
            cotioDataEsArray: Array.isArray(cotioData),
            cotioDataLength: Array.isArray(cotioData) ? cotioData.length : 'N/A'
        });
        
        // Cargar campos del formulario
        // Usar valores explícitos o cadena vacía para limpiar campos
        document.getElementById('descripcion').value = cotiData.coti_descripcion || '';
        if (cotiData.coti_para !== undefined) {
            const cotiParaInput = document.getElementById('coti_para');
            const cotiCliEmpresaHidden = document.getElementById('coti_cli_empresa');
            if (cotiParaInput) cotiParaInput.value = cotiData.coti_para || '';
            if (cotiCliEmpresaHidden) {
                cotiCliEmpresaHidden.value = (cotiData.coti_cli_empresa !== null && cotiData.coti_cli_empresa !== undefined) ? cotiData.coti_cli_empresa : '';
            }
        }
        document.getElementById('fecha_alta').value = cotiData.coti_fechaalta || '';
        document.getElementById('fecha_venc').value = cotiData.coti_fechafin || '';
        document.getElementById('matriz').value = (cotiData.coti_codigomatriz && cotiData.coti_codigomatriz.trim()) ? cotiData.coti_codigomatriz.trim() : '';
        if (cotiData.coti_estado) {
            const estado = String(cotiData.coti_estado).trim();
            const estadoMap = {
                'E': 'E',
                'A': 'A',
                'R': 'R',
                'P': 'P'
            };
            const estadoValue = estadoMap[estado.charAt(0)] || 'E';
            document.getElementById('estado').value = estadoValue;
        }
        document.getElementById('contacto').value = cotiData.coti_contacto || '';
        document.getElementById('correo').value = cotiData.coti_mail1 || '';
        document.getElementById('telefono').value = cotiData.coti_telefono || '';
        
        // Sector: manejar explícitamente null, undefined y valores vacíos
        const sectorSelect = document.getElementById('sector');
        if (sectorSelect) {
            // Si el sector es null, undefined, o string vacío, limpiar el selector
            if (cotiData.coti_sector === null || cotiData.coti_sector === undefined || cotiData.coti_sector === '') {
                sectorSelect.value = '';
            } else {
                const sectorValue = String(cotiData.coti_sector).trim();
                if (sectorValue) {
                    // Buscar la opción que coincida con el sector (comparar con trim)
                    const options = Array.from(sectorSelect.options);
                    const optionEncontrada = options.find(opt => {
                        const optValue = String(opt.value).trim();
                        // Comparar valores completos o primeros 4 caracteres
                        return optValue === sectorValue || 
                               optValue === sectorValue.substring(0, 4) ||
                               sectorValue === optValue.substring(0, 4);
                    });
                    if (optionEncontrada) {
                        sectorSelect.value = optionEncontrada.value;
                    } else {
                        // Si no se encuentra, intentar establecer directamente
                        sectorSelect.value = sectorValue;
                    }
                } else {
                    sectorSelect.value = '';
                }
            }
        }
        
        document.getElementById('comentario').value = cotiData.coti_notas || '';
        document.getElementById('descuento').value = (cotiData.coti_descuentoglobal !== null && cotiData.coti_descuentoglobal !== undefined) ? cotiData.coti_descuentoglobal : '0.00';
        document.getElementById('responsable').value = (cotiData.coti_responsable && cotiData.coti_responsable.trim()) ? cotiData.coti_responsable.trim() : '';
        document.getElementById('fecha_aprobado').value = cotiData.coti_fechaaprobado || '';
        document.getElementById('aprobo').value = (cotiData.coti_aprobo && cotiData.coti_aprobo.trim()) ? cotiData.coti_aprobo.trim() : '';
        document.getElementById('fecha_en_curso').value = cotiData.coti_fechaencurso || '';
        document.getElementById('fecha_alta_tecnica').value = cotiData.coti_fechaaltatecnica || '';
        document.getElementById('empresa_nombre').value = cotiData.coti_empresa || '';
        document.getElementById('establecimiento').value = cotiData.coti_establecimiento || '';
        document.getElementById('direccion_cliente').value = cotiData.coti_direccioncli || '';
        document.getElementById('localidad_cliente').value = cotiData.coti_localidad || '';
        document.getElementById('partido').value = cotiData.coti_partido || '';
        document.getElementById('cuit_cliente').value = cotiData.coti_cuit || '';
        document.getElementById('codigo_postal_cliente').value = cotiData.coti_codigopostal || '';
        
        // Cargar items (ensayos y componentes)
        // Procesar cotioData para separar ensayos y componentes
        // IMPORTANTE: Mantener el orden y estructura original de la versión
        const ensayos = [];
        const componentes = [];
        
        // Asegurar que cotioData sea un array
        const itemsData = Array.isArray(cotioData) ? cotioData : [];
        
        console.log('[Versión] Procesando itemsData:', {
            itemsDataLength: itemsData.length,
            itemsDataSample: itemsData.slice(0, 3)
        });
        
        // Primero, obtener el máximo item de ensayo para asignar items únicos a componentes
        // Si no hay items, maxItemEnsayo será 0
        const itemsEnsayo = itemsData.filter(item => parseInt(item.cotio_subitem) === 0);
        const maxItemEnsayo = itemsEnsayo.length > 0 
            ? Math.max(...itemsEnsayo.map(item => parseInt(item.cotio_item) || 0))
            : 0;
        let contadorComponente = 0;
        
        console.log('[Versión] Análisis de items:', {
            totalItems: itemsData.length,
            itemsEnsayo: itemsEnsayo.length,
            maxItemEnsayo: maxItemEnsayo
        });
        
        // Procesar todos los items manteniendo su estructura original
        itemsData.forEach((item, index) => {
            console.log(`[Versión] Procesando item ${index}:`, {
                cotio_item: item.cotio_item,
                cotio_subitem: item.cotio_subitem,
                descripcion: item.cotio_descripcion
            });
            if (parseInt(item.cotio_subitem) === 0) {
                // Es un ensayo
                const cantidad = parseFloat(item.cotio_cantidad) || 1;
                const itemEnsayo = parseInt(item.cotio_item) || 0;
                console.log(`[Versión] Procesando ensayo:`, {
                    cotio_item: item.cotio_item,
                    itemEnsayo: itemEnsayo,
                    descripcion: item.cotio_descripcion
                });
                ensayos.push({
                    item: itemEnsayo,
                    descripcion: item.cotio_descripcion || '',
                    codigo: item.cotio_codigoprod || '',
                    cantidad: cantidad,
                    precio: 0, // Se calculará desde los componentes
                    total: 0,
                    tipo: 'ensayo',
                    componentes_sugeridos: [],
                    nota_tipo: item.cotio_nota_tipo || null,
                    nota_contenido: item.cotio_nota_contenido || null,
                });
            } else {
                // Es un componente
                // Asignar un item único (mayor que el máximo item de ensayo)
                contadorComponente++;
                const itemComponente = maxItemEnsayo + contadorComponente;
                
                const ensayoAsociadoValue = parseInt(item.cotio_item) || 0;
                console.log(`[Versión] Procesando componente:`, {
                    cotio_item: item.cotio_item,
                    cotio_subitem: item.cotio_subitem,
                    descripcion: item.cotio_descripcion,
                    ensayo_asociado_calculado: ensayoAsociadoValue,
                    itemComponente: itemComponente
                });
                
                componentes.push({
                    item: itemComponente, // Item único para el componente
                    descripcion: item.cotio_descripcion || '',
                    codigo: item.cotio_codigoprod || '',
                    cantidad: parseFloat(item.cotio_cantidad) || 1,
                    precio: parseFloat(item.cotio_precio) || 0,
                    total: (parseFloat(item.cotio_precio) || 0) * (parseFloat(item.cotio_cantidad) || 1),
                    tipo: 'componente',
                    ensayo_asociado: ensayoAsociadoValue, // El item del ensayo al que pertenece
                    metodo_analisis_id: item.cotio_codigometodo_analisis ? item.cotio_codigometodo_analisis.trim() : null,
                    metodo_codigo: item.cotio_codigometodo ? item.cotio_codigometodo.trim() : null,
                    metodo_descripcion: '-',
                    unidad_medida: item.cotio_codigoum ? item.cotio_codigoum.trim() : null,
                    limite_deteccion: item.limite_deteccion !== null && item.limite_deteccion !== undefined ? parseFloat(item.limite_deteccion) : null,
                    limite_cuantificacion: item.limite_cuantificacion !== null && item.limite_cuantificacion !== undefined ? parseFloat(item.limite_cuantificacion) : null,
                    ley_normativa_id: item.ley_aplicacion ? item.ley_aplicacion.trim() : null,
                    nota_tipo: item.cotio_nota_tipo || null,
                    nota_contenido: item.cotio_nota_contenido || null,
                });
            }
        });
        
        // Calcular precios de ensayos desde componentes
        console.log('[Versión] Calculando precios de ensayos desde componentes');
        ensayos.forEach(ensayo => {
            const componentesEnsayo = componentes.filter(c => {
                const coincide = c.ensayo_asociado === ensayo.item;
                if (!coincide) {
                    console.log(`[Versión] Componente ${c.item} (${c.descripcion}) NO asociado a ensayo ${ensayo.item}: ensayo_asociado=${c.ensayo_asociado}`);
                }
                return coincide;
            });
            console.log(`[Versión] Ensayo ${ensayo.item} (${ensayo.descripcion}) tiene ${componentesEnsayo.length} componentes asociados`);
            ensayo.precio = componentesEnsayo.reduce((sum, comp) => sum + (comp.precio * comp.cantidad), 0);
            ensayo.total = ensayo.precio * ensayo.cantidad;
        });
        
        // Cargar items en el state del script de cotización
        // IMPORTANTE: Siempre pasar arrays, incluso si están vacíos, para limpiar los items anteriores
        console.log('[Versión] Resumen de items procesados:', { 
            ensayosCount: ensayos.length,
            componentesCount: componentes.length,
            cotioDataLength: itemsData.length,
            ensayosSample: ensayos.slice(0, 2),
            componentesSample: componentes.slice(0, 2)
        });
        
        // ENFOQUE NUEVO: Actualizar también window.cotizacionConfig para mantener sincronización
        if (window.cotizacionConfig) {
            console.log('[Versión] Actualizando window.cotizacionConfig con nuevos items');
            window.cotizacionConfig.ensayosIniciales = JSON.parse(JSON.stringify(ensayos));
            window.cotizacionConfig.componentesIniciales = JSON.parse(JSON.stringify(componentes));
        }
        
        // Esperar a que el script de cotización esté inicializado
        let intentos = 0;
        const maxIntentos = 20; // Máximo 4 segundos (20 * 200ms)
        
        const intentarCargarItems = () => {
            intentos++;
            console.log(`[Versión] Intento ${intentos}/${maxIntentos} de cargar items`, {
                existeCotizacionScripts: !!window.cotizacionScripts,
                existeFuncion: window.cotizacionScripts && typeof window.cotizacionScripts.cargarItemsDesdeVersion === 'function'
            });
            
            // Buscar el objeto cotizacionScripts
            if (window.cotizacionScripts && typeof window.cotizacionScripts.cargarItemsDesdeVersion === 'function') {
                console.log('[Versión] ✅ Script disponible, cargando items en el state', {
                    ensayosParaCargar: ensayos.length,
                    componentesParaCargar: componentes.length
                });
                
                // Usar la función expuesta para cargar items (siempre pasar arrays, incluso vacíos)
                // Crear copias profundas para evitar referencias compartidas
                const ensayosCopia = JSON.parse(JSON.stringify(ensayos || []));
                const componentesCopia = JSON.parse(JSON.stringify(componentes || []));
                
                try {
                    window.cotizacionScripts.cargarItemsDesdeVersion(ensayosCopia, componentesCopia);
                    console.log('[Versión] ✅ Items cargados exitosamente en el state');
                } catch (error) {
                    console.error('[Versión] ❌ Error al cargar items:', error);
                }
            } else if (intentos < maxIntentos) {
                // Si no está disponible, intentar de nuevo después de un breve delay
                console.log(`[Versión] Script no disponible aún, reintentando en 200ms...`);
                setTimeout(intentarCargarItems, 200);
            } else {
                console.error('[Versión] ❌ No se pudo cargar items: script no disponible después de', maxIntentos, 'intentos');
                console.error('[Versión] Estado actual:', {
                    windowCotizacionScripts: window.cotizacionScripts,
                    tipo: typeof window.cotizacionScripts
                });
            }
        };
        
        // Intentar cargar después de que el script se inicialice
        console.log('[Versión] Iniciando intentos de carga de items...');
        setTimeout(intentarCargarItems, 500);
    }
});
</script>
@endsection

