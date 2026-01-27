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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Crear Nueva Cotización</h2>
                <div>
                    <a href="{{ route('ventas.index') }}" class="btn btn-secondary me-2">
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
                    <form method="POST" action="{{ route('ventas.store') }}" id="cotizacionForm">
                        @csrf
                        
                        <!-- Header con información básica -->
                        <div class="border-bottom px-4 py-3 bg-info">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <label for="cliente_codigo" class="form-label fw-semibold mb-1 text-dark">Cliente:</label>
                                    <div class="position-relative" id="clienteBuscadorWrapper">
                                        <div class="input-group">
                                        <input type="text" class="form-control form-control-sm" id="cliente_codigo" name="coti_codigocli" 
                                                   value="{{ old('coti_codigocli') }}" placeholder="Escribe nombre o código..." autocomplete="off" required>
                                            <button class="btn btn-outline-secondary btn-sm" style="border-color: #fff;" type="button" id="btnBuscarCliente">
                                                <x-heroicon-o-magnifying-glass style="width: 14px; height: 14px; color: #fff;" />
                                            </button>
                                        </div>
                                        <div class="dropdown-menu w-100 shadow-sm p-0" id="clienteResultados"></div>
                                    </div>
                                    <!-- Campos hidden para datos del cliente -->
                                    <input type="hidden" id="cliente_razon_social_hidden" name="cliente_razon_social">
                                    <input type="hidden" id="cliente_direccion_hidden" name="cliente_direccion">
                                    <input type="hidden" id="cliente_localidad_hidden" name="cliente_localidad">
                                    <input type="hidden" id="cliente_cuit_hidden" name="cliente_cuit">
                                    <input type="hidden" id="cliente_codigo_postal_hidden" name="cliente_codigo_postal">
                                    <input type="hidden" id="cliente_telefono_hidden" name="cliente_telefono">
                                    <input type="hidden" id="cliente_correo_hidden">
                                    <input type="hidden" id="cliente_sector_hidden">
                                    <input type="hidden" id="cliente_descuento_hidden" value="{{ old('cliente_descuento_hidden', '0.00') }}" data-descuento-global="{{ old('cliente_descuento_global', '0.00') }}">
                                    
                                    <!-- Campos hidden para ensayos y componentes -->
                                    <input type="hidden" id="ensayos_data" name="ensayos_data">
                                    <input type="hidden" id="componentes_data" name="componentes_data">
                                </div>
                                <div class="col-md-4">
                                    <label for="cliente_nombre" class="form-label fw-semibold mb-1">&nbsp;</label>
                                    <input type="text" class="form-control form-control-sm" id="cliente_nombre" 
                                           placeholder="Seleccione un cliente" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label for="sucursal" class="form-label fw-semibold mb-1 text-dark">Sucursal:</label>
                                    <input type="text" class="form-control form-control-sm" id="sucursal" name="coti_codigosuc"
                                           value="{{ old('coti_codigosuc') }}">
                                </div>
                                <div class="col-md-2">
                                    <label for="numero" class="form-label fw-semibold mb-1 text-dark">Nro:</label>
                                    <input type="text" class="form-control form-control-sm" id="numero" name="coti_num" 
                                           value="NUEVO" readonly>
                                </div>

                                <div class="col-md-2">
                                    <label for="Para" class="form-label fw-semibold mb-1 text-dark">Para:</label>
                                    <div id="coti_para_wrapper">
                                        <input type="text" class="form-control form-control-sm" id="coti_para" name="coti_para" 
                                               value="{{ old('coti_para') }}" placeholder="Empresa relacionada...">
                                        <select class="form-control form-control-sm d-none" id="coti_para_select" name="coti_para">
                                            <option value="">Seleccionar empresa relacionada...</option>
                                        </select>
                                        <input type="hidden" id="coti_cli_empresa" name="coti_cli_empresa" value="{{ old('coti_cli_empresa') }}">
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
                            <li class="nav-item" role="presentation">
                                <button class="nav-link disabled" type="button">Documentos</button>
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
                                                   value="{{ old('coti_descripcion') }}" placeholder="Descripción de la cotización...">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#modalClonarCotizacion">
                                                <x-heroicon-o-document-duplicate style="width: 16px; height: 16px;" class="me-1" />
                                                Clonar
                                            </button>
                                        </div>
                           
                                        <div class="col-md-2">
                                            <label for="fecha_alta" class="form-label">Alta:</label>
                                            <input type="date" class="form-control" id="fecha_alta" name="coti_fechaalta" 
                                                   value="{{ old('coti_fechaalta', date('Y-m-d')) }}">
                                        </div>
                                        <div class="col-md-2">
                                            <label for="fecha_venc" class="form-label">Venc:</label>
                                            <input type="date" class="form-control" id="fecha_venc" name="coti_fechafin"
                                                   value="{{ old('coti_fechafin') }}">
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
                                                <option value="En Espera" selected>En Espera</option>
                                                <option value="Aprobado">Aprobado</option>
                                                <option value="Rechazado">Rechazado</option>
                                                <option value="En Proceso">En Proceso</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="cadena_custodia" name="coti_cadena_custodia" value="1" {{ old('coti_cadena_custodia') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="cadena_custodia">
                                                    Cadena de Custodia
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="muestreo" name="coti_muestreo" value="1" {{ old('coti_muestreo') ? 'checked' : '' }}>
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
                                                   value="{{ old('coti_contacto') }}" placeholder="Nombre del contacto principal">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="correo" class="form-label">Correo:</label>
                                            <input type="email" class="form-control" id="correo" name="coti_mail1"
                                                   value="{{ old('coti_mail1') }}" placeholder="correo@cliente.com">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="telefono" class="form-label">Teléfono:</label>
                                            <input type="text" class="form-control" id="telefono" name="coti_telefono"
                                                   value="{{ old('coti_telefono') }}" placeholder="+54 9 11 1234-5678">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="sector" class="form-label">Sector:</label>
                                            <select class="form-select" id="sector" name="coti_sector">
                                                <option value="">Seleccionar sector...</option>
                                                    @foreach($sectoresCliente as $sector)
                                                        @php
                                                            $codigoSector = trim($sector->divis_codigo);
                                                        @endphp
                                                        <option value="{{ $codigoSector }}" 
                                                                {{ trim((string) old('coti_sector')) === $codigoSector ? 'selected' : '' }}>
                                                            {{ trim($sector->divis_descripcion) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <label for="comentario" class="form-label">Comentario:</label>
                                            <textarea class="form-control" id="comentario" name="coti_notas" rows="3"></textarea>
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
                                                           value="{{ old('descuento', '0.00') }}" placeholder="0.00">
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
                                
                                @include('ventas.partials.cotizacion-approval-fields')
                                </div>
                            </div>

                            <!-- Solapa Gestión -->
                            <div class="tab-pane fade" id="gestion" role="tabpanel">
                                <div class="p-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="responsable" class="form-label">Responsable:</label>
                                                <input type="text" class="form-control" id="responsable" name="coti_responsable">
                                            </div>
                                            <div class="mb-3">
                                                <label for="fecha_aprobado" class="form-label">Fecha Aprobado:</label>
                                                <input type="date" class="form-control" id="fecha_aprobado" name="coti_fechaaprobado">
                                            </div>
                                            <div class="mb-3">
                                                <label for="aprobo" class="form-label">Aprobó:</label>
                                                <input type="text" class="form-control" id="aprobo" name="coti_aprobo">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="fecha_en_curso" class="form-label">Fecha En Curso:</label>
                                                <input type="date" class="form-control" id="fecha_en_curso" name="coti_fechaencurso">
                                            </div>
                                            <div class="mb-3">
                                                <label for="fecha_alta_tecnica" class="form-label">Fecha Alta Técnica:</label>
                                                <input type="date" class="form-control" id="fecha_alta_tecnica" name="coti_fechaaltatecnica">
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
                                                       value="{{ old('coti_empresa') }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="establecimiento" class="form-label">Establecimiento:</label>
                                                <input type="text" class="form-control" id="establecimiento" name="coti_establecimiento"
                                                       value="{{ old('coti_establecimiento') }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="direccion_cliente" class="form-label">Dirección Cliente:</label>
                                                <input type="text" class="form-control" id="direccion_cliente" name="coti_direccioncli"
                                                       value="{{ old('coti_direccioncli') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="localidad_cliente" class="form-label">Localidad:</label>
                                                <input type="text" class="form-control" id="localidad_cliente" name="coti_localidad"
                                                       value="{{ old('coti_localidad') }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="partido" class="form-label">Partido:</label>
                                                <input type="text" class="form-control" id="partido" name="coti_partido"
                                                       value="{{ old('coti_partido') }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="cuit_cliente" class="form-label">CUIT:</label>
                                                <input type="text" class="form-control" id="cuit_cliente" name="coti_cuit"
                                                       value="{{ old('coti_cuit') }}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="codigo_postal_cliente" class="form-label">Código Postal:</label>
                                                <input type="text" class="form-control" id="codigo_postal_cliente" name="coti_codigopostal"
                                                       value="{{ old('coti_codigopostal') }}">
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
                                    <button type="button" class="btn btn-info">
                                        <x-heroicon-o-arrow-path style="width: 16px; height: 16px;" class="me-1" />
                                        Salir
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <x-heroicon-o-check style="width: 16px; height: 16px;" class="me-1" />
                                        Guardar
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

<!-- Modal Agregar Ensayo -->
<div class="modal fade" id="modalAgregarEnsayo" tabindex="-1" aria-labelledby="modalAgregarEnsayoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalAgregarEnsayoLabel">Ensayo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEnsayo">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="ensayo_muestra" class="form-label">Seleccionar Muestra/Ensayo <span class="text-danger">*</span></label>
                            <select class="form-select" id="ensayo_muestra" name="ensayo_muestra" required>
                                <option value="">Seleccionar muestra...</option>
                            </select>
                            <small class="text-muted">Seleccione el tipo de muestra que desea analizar</small>
                            <div id="ensayo_metodo_info" class="form-text mt-1"></div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ensayo_codigo" class="form-label">Código:</label>
                            <input type="text" class="form-control" id="ensayo_codigo" name="ensayo_codigo" 
                                   placeholder="Se generará automáticamente" readonly>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="no_requiere_custodia">
                                <label class="form-check-label" for="no_requiere_custodia">
                                    No Requiere Cadena de Custodia
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="cantidad_ensayo" class="form-label">Cantidad:</label>
                            <input type="number" class="form-control" id="cantidad_ensayo" name="cantidad" value="3" min="1">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="flexible">
                                <label class="form-check-label" for="flexible">Flexible</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="bonificado">
                                <label class="form-check-label" for="bonificado">Bonificado</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ensayo_ley_normativa" class="form-label">Ley/Normativa:</label>
                            <select class="form-select" id="ensayo_ley_normativa" name="ensayo_ley_normativa">
                                <option value="">Seleccionar normativa...</option>
                            </select>
                        </div>
                    </div>


                    <!-- Sección de Notas Múltiples -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0 fw-semibold">Notas:</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarNotaEnsayo">
                                    <x-heroicon-o-plus style="width: 14px; height: 14px;" class="me-1" />
                                    Agregar Nota
                                </button>
                            </div>
                            <div id="notasEnsayoContainer">
                                <!-- Las notas se agregarán dinámicamente aquí -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarEnsayo">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Agregar Componente -->
<div class="modal fade" id="modalAgregarComponente" tabindex="-1" aria-labelledby="modalAgregarComponenteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalAgregarComponenteLabel">Componente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formComponente">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="componente_ensayo_asociado" class="form-label">Ensayo Asociado <span class="text-danger">*</span></label>
                            <select class="form-select" id="componente_ensayo_asociado" name="componente_ensayo_asociado" required>
                                <option value="">Seleccionar ensayo...</option>
                            </select>
                            <small class="text-muted">
                                <x-heroicon-o-information-circle style="width: 14px; height: 14px;" class="me-1" />
                                Seleccione el ensayo al que pertenece este análisis. Debe agregar al menos un ensayo primero.
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label for="componente_analisis" class="form-label">Seleccionar Análisis <span class="text-danger">*</span></label>
                            <select class="form-select" id="componente_analisis" name="componente_analisis[]" multiple required>
                                <option disabled value="">Seleccionar análisis...</option>
                            </select>
                            <small class="text-muted">
                                <x-heroicon-o-beaker style="width: 14px; height: 14px;" class="me-1" />
                                Seleccione uno o varios análisis a realizar en la muestra
                            </small>
                            <div id="componente_metodo_info" class="form-text mt-1"></div>
                        </div>
                    </div>


                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="componente_codigo" class="form-label">Código:</label>
                            <input type="text" class="form-control" id="componente_codigo" name="componente_codigo" 
                                   placeholder="Se generará automáticamente" readonly>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="comp_no_requiere_custodia">
                                <label class="form-check-label" for="comp_no_requiere_custodia">
                                    No Requiere Cadena de Custodia
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="comp_flexible">
                                <label class="form-check-label" for="comp_flexible">Flexible</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="comp_bonificado">
                                <label class="form-check-label" for="comp_bonificado">Bonificado</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="number" step="0.01" class="form-control" placeholder="0.00" readonly>
                            <small class="text-muted">Última Cotización</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="comp_precio_final" class="form-label">Precio:</label>
                            <input type="number" step="0.01" class="form-control" id="comp_precio_final" 
                                   name="comp_precio_final" value="237055.00">
                        </div>
                        {{-- <div class="col-md-8">
                            <label class="form-label">Precio de lista</label>
                        </div> --}}
                    </div>

                    {{-- Notas para componentes - COMENTADO
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comp_nota_tipo" id="comp_nota_imprimible" value="imprimible" checked>
                                <label class="form-check-label" for="comp_nota_imprimible">Nota Imprimible</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comp_nota_tipo" id="comp_nota_interna" value="interna">
                                <label class="form-check-label" for="comp_nota_interna">Nota Interna</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="comp_nota_tipo" id="comp_nota_fact" value="fact">
                                <label class="form-check-label" for="comp_nota_fact">Nota Fact.</label>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-2">Insertar Nota Predefinida</button>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="comp_predeterminar">
                                <label class="form-check-label" for="comp_predeterminar">Predeterminar</label>
                            </div>
                            <textarea class="form-control" id="componente_nota_contenido" name="componente_nota_contenido" rows="4" placeholder="Descripción del componente..."></textarea>
                        </div>
                    </div>
                    --}}
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarComponente">Aceptar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Componente -->
<div class="modal fade" id="modalEditarComponente" tabindex="-1" aria-labelledby="modalEditarComponenteLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarComponenteLabel">Editar Componente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarComponente">
                    <input type="hidden" id="edit_componente_item_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_componente_analisis" class="form-label">Análisis <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_componente_analisis" name="edit_componente_analisis" required>
                                <option value="">Seleccionar análisis...</option>
                            </select>
                            <small class="text-muted">Seleccione el análisis que desea asignar a este componente</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_componente_precio" class="form-label">Precio:</label>
                            <input type="number" step="0.01" class="form-control" id="edit_componente_precio" name="edit_componente_precio" value="0.00" min="0">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_componente_unidad" class="form-label">Unidad de Medida:</label>
                            <input type="text" class="form-control" id="edit_componente_unidad" name="edit_componente_unidad" placeholder="U.M.">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_componente_metodo" class="form-label">Método de Análisis:</label>
                            <select class="form-select" id="edit_componente_metodo" name="edit_componente_metodo">
                                <option value="">Seleccionar método...</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarComponenteEditado">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

@include('ventas.partials.cotizacion-styles')

<!-- Modal Clonar Cotización -->
<div class="modal fade" id="modalClonarCotizacion" tabindex="-1" aria-labelledby="modalClonarCotizacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalClonarCotizacionLabel">
                    <x-heroicon-o-document-duplicate style="width: 20px; height: 20px;" class="me-2" />
                    Clonar Cotización
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Filtros de búsqueda -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Filtros de Búsqueda</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="filtro_numero" class="form-label">Número de Cotización</label>
                                <input type="text" class="form-control form-control-sm" id="filtro_numero" placeholder="Ej: 123">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_descripcion" class="form-label">Descripción</label>
                                <input type="text" class="form-control form-control-sm" id="filtro_descripcion" placeholder="Buscar por descripción...">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_cliente" class="form-label">Cliente</label>
                                <input type="text" class="form-control form-control-sm" id="filtro_cliente" placeholder="Nombre o código...">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_estado" class="form-label">Estado</label>
                                <select class="form-select form-select-sm" id="filtro_estado">
                                    <option value="">Todos</option>
                                    <option value="En Espera">En Espera</option>
                                    <option value="Aprobado">Aprobado</option>
                                    <option value="Rechazado">Rechazado</option>
                                    <option value="En Proceso">En Proceso</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_fecha_desde" class="form-label">Fecha Desde</label>
                                <input type="date" class="form-control form-control-sm" id="filtro_fecha_desde">
                            </div>
                            <div class="col-md-3">
                                <label for="filtro_fecha_hasta" class="form-label">Fecha Hasta</label>
                                <input type="date" class="form-control form-control-sm" id="filtro_fecha_hasta">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-primary btn-sm me-2" id="btnBuscarCotizaciones">
                                    Buscar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltros">
                                    <x-heroicon-o-arrow-path style="width: 16px; height: 16px;" class="me-1" />
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de resultados -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 100px;">Número</th>
                                <th>Descripción</th>
                                <th>Cliente</th>
                                <th style="width: 120px;">Estado</th>
                                <th style="width: 120px;">Fecha Alta</th>
                                <th style="width: 100px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tablaCotizaciones">
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <em>Ingrese criterios de búsqueda y haga clic en "Buscar"</em>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script>
    @php
        $configuracionCotizacion = $cotizacionConfig ?? [
            'modo' => 'create',
            'puedeEditar' => true,
            'ensayosIniciales' => [],
            'componentesIniciales' => [],
        ];
    @endphp
    window.cotizacionConfig = @json($configuracionCotizacion);
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

@include('ventas.partials.cotizacion-scripts')

<script>
// Funcionalidad de clonación de cotizaciones
(function() {
    'use strict';
    
    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClonacion);
    } else {
        initClonacion();
    }
    
    function initClonacion() {
        function agregarEventListeners() {
            // Agregar event listeners directamente a los botones
            const btnBuscar = document.getElementById('btnBuscarCotizaciones');
            const btnLimpiar = document.getElementById('btnLimpiarFiltros');
            
            if (btnBuscar && !btnBuscar.dataset.listenerAdded) {
                btnBuscar.addEventListener('click', function(e) {
                    e.preventDefault();
                    buscarCotizaciones();
                });
                btnBuscar.dataset.listenerAdded = 'true';
            }
            
            if (btnLimpiar && !btnLimpiar.dataset.listenerAdded) {
                btnLimpiar.addEventListener('click', function(e) {
                    e.preventDefault();
                    limpiarFiltros();
                });
                btnLimpiar.dataset.listenerAdded = 'true';
            }

            // Permitir búsqueda con Enter en los campos de filtro
            const filtroNumero = document.getElementById('filtro_numero');
            const filtroDescripcion = document.getElementById('filtro_descripcion');
            const filtroCliente = document.getElementById('filtro_cliente');
            
            [filtroNumero, filtroDescripcion, filtroCliente].forEach(input => {
                if (input && !input.dataset.listenerAdded) {
                    input.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            buscarCotizaciones();
                        }
                    });
                    input.dataset.listenerAdded = 'true';
                }
            });
        }
        
        // Agregar listeners inmediatamente si los elementos existen
        agregarEventListeners();
        
        // También agregar cuando el modal se muestre
        const modalClonar = document.getElementById('modalClonarCotizacion');
        if (modalClonar) {
            modalClonar.addEventListener('shown.bs.modal', function() {
                agregarEventListeners();
            });
        }

    async function buscarCotizaciones() {
        const btnBuscar = document.getElementById('btnBuscarCotizaciones');
        const tablaCotizaciones = document.getElementById('tablaCotizaciones');
        
        if (!btnBuscar || !tablaCotizaciones) {
            console.error('Elementos no encontrados');
            return;
        }

        const filtros = {
            numero: document.getElementById('filtro_numero')?.value || '',
            descripcion: document.getElementById('filtro_descripcion')?.value || '',
            cliente: document.getElementById('filtro_cliente')?.value || '',
            estado: document.getElementById('filtro_estado')?.value || '',
            fecha_desde: document.getElementById('filtro_fecha_desde')?.value || '',
            fecha_hasta: document.getElementById('filtro_fecha_hasta')?.value || '',
        };

        // Validar que haya al menos un filtro
        const tieneFiltros = Object.values(filtros).some(v => v.trim() !== '');
        if (!tieneFiltros) {
            Swal.fire({
                icon: 'warning',
                title: 'Filtros requeridos',
                text: 'Por favor ingrese al menos un criterio de búsqueda'
            });
            return;
        }

        const originalHtml = btnBuscar.innerHTML;
        try {
            btnBuscar.disabled = true;
            btnBuscar.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Buscando...';

            const params = new URLSearchParams();
            Object.entries(filtros).forEach(([key, value]) => {
                if (value) params.append(key, value);
            });

            const response = await fetch(`{{ route('ventas.buscar-para-clonar') }}?${params.toString()}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error al buscar cotizaciones');
            }

            mostrarResultados(data.cotizaciones || []);
        } catch (error) {
            console.error('Error buscando cotizaciones:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al buscar cotizaciones'
            });
        } finally {
            if (btnBuscar) {
                btnBuscar.disabled = false;
                btnBuscar.innerHTML = originalHtml;
            }
        }
    }

    function mostrarResultados(cotizaciones) {
        const tablaCotizaciones = document.getElementById('tablaCotizaciones');
        if (!tablaCotizaciones) return;

        if (cotizaciones.length === 0) {
            tablaCotizaciones.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        <em>No se encontraron cotizaciones con los criterios especificados</em>
                    </td>
                </tr>
            `;
            return;
        }

        tablaCotizaciones.innerHTML = cotizaciones.map(cot => {
            const estadoBadge = {
                'En Espera': 'warning',
                'Aprobado': 'success',
                'Rechazado': 'danger',
                'En Proceso': 'info'
            }[cot.coti_estado] || 'secondary';

            return `
                <tr>
                    <td><strong>${cot.coti_num}</strong></td>
                    <td>${cot.coti_descripcion || '-'}</td>
                    <td>${cot.cliente_nombre || cot.coti_codigocli || '-'}</td>
                    <td><span class="badge bg-${estadoBadge}">${cot.coti_estado || '-'}</span></td>
                    <td>${cot.coti_fechaalta || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="clonarCotizacion(${cot.coti_num})">
                            Clonar
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function limpiarFiltros() {
        const filtroNumero = document.getElementById('filtro_numero');
        const filtroDescripcion = document.getElementById('filtro_descripcion');
        const filtroCliente = document.getElementById('filtro_cliente');
        const filtroEstado = document.getElementById('filtro_estado');
        const filtroFechaDesde = document.getElementById('filtro_fecha_desde');
        const filtroFechaHasta = document.getElementById('filtro_fecha_hasta');
        const tablaCotizaciones = document.getElementById('tablaCotizaciones');

        if (filtroNumero) filtroNumero.value = '';
        if (filtroDescripcion) filtroDescripcion.value = '';
        if (filtroCliente) filtroCliente.value = '';
        if (filtroEstado) filtroEstado.value = '';
        if (filtroFechaDesde) filtroFechaDesde.value = '';
        if (filtroFechaHasta) filtroFechaHasta.value = '';
        
        if (tablaCotizaciones) {
            tablaCotizaciones.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        <em>Ingrese criterios de búsqueda y haga clic en "Buscar"</em>
                    </td>
                </tr>
            `;
        }
    }

    // Función global para clonar
    window.clonarCotizacion = async function(cotiNum) {
        try {
            Swal.fire({
                title: 'Cargando cotización...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const url = '{{ route("ventas.obtener-para-clonar", ["cotiNum" => "__COTI_NUM__"]) }}'.replace('__COTI_NUM__', cotiNum);
            const response = await fetch(url);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error al obtener cotización');
            }

            // Cerrar modal
            const modalClonar = document.getElementById('modalClonarCotizacion');
            if (modalClonar) {
                const modal = bootstrap.Modal.getInstance(modalClonar);
                if (modal) modal.hide();
            }

            // Rellenar formulario
            rellenarFormulario(data);

            Swal.fire({
                icon: 'success',
                title: '¡Cotización clonada!',
                text: 'Los datos de la cotización han sido cargados. Revise y ajuste según sea necesario.',
                timer: 2000,
                showConfirmButton: false
            });
        } catch (error) {
            console.error('Error clonando cotización:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Error al clonar la cotización'
            });
        }
    };

    function rellenarFormulario(data) {
        const { cotizacion, ensayos, componentes } = data;

        // Rellenar campos básicos
        if (cotizacion.coti_codigocli) {
            document.getElementById('cliente_codigo').value = cotizacion.coti_codigocli;
            // Disparar evento para cargar datos del cliente
            const evento = new Event('input', { bubbles: true });
            document.getElementById('cliente_codigo').dispatchEvent(evento);
        }

        if (cotizacion.coti_descripcion) {
            document.getElementById('descripcion').value = cotizacion.coti_descripcion;
        }

        if (cotizacion.coti_fechaalta) {
            document.getElementById('fecha_alta').value = cotizacion.coti_fechaalta;
        }

        if (cotizacion.coti_fechafin) {
            document.getElementById('fecha_venc').value = cotizacion.coti_fechafin;
        }

        if (cotizacion.coti_estado) {
            const estadoSelect = document.getElementById('estado');
            if (estadoSelect) {
                estadoSelect.value = cotizacion.coti_estado;
            }
        }

        if (cotizacion.coti_codigosuc) {
            document.getElementById('sucursal').value = cotizacion.coti_codigosuc;
        }

        if (cotizacion.coti_para) {
            document.getElementById('coti_para').value = cotizacion.coti_para;
        }

        if (cotizacion.coti_contacto) {
            document.getElementById('contacto').value = cotizacion.coti_contacto;
        }

        if (cotizacion.coti_mail1) {
            document.getElementById('correo').value = cotizacion.coti_mail1;
        }

        if (cotizacion.coti_telefono) {
            document.getElementById('telefono').value = cotizacion.coti_telefono;
        }

        if (cotizacion.coti_sector) {
            const sectorSelect = document.getElementById('sector');
            if (sectorSelect) {
                sectorSelect.value = cotizacion.coti_sector;
            }
        }

        if (cotizacion.coti_notas) {
            document.getElementById('comentario').value = cotizacion.coti_notas;
        }

        if (cotizacion.descuento) {
            document.getElementById('descuento').value = cotizacion.descuento;
        }

        if (cotizacion.coti_cadena_custodia) {
            document.getElementById('cadena_custodia').checked = true;
        }

        if (cotizacion.coti_muestreo) {
            document.getElementById('muestreo').checked = true;
        }

        // Campos de gestión
        if (cotizacion.coti_responsable) {
            document.getElementById('responsable').value = cotizacion.coti_responsable;
        }

        if (cotizacion.coti_fechaaprobado) {
            document.getElementById('fecha_aprobado').value = cotizacion.coti_fechaaprobado;
        }

        if (cotizacion.coti_aprobo) {
            document.getElementById('aprobo').value = cotizacion.coti_aprobo;
        }

        if (cotizacion.coti_fechaencurso) {
            document.getElementById('fecha_en_curso').value = cotizacion.coti_fechaencurso;
        }

        if (cotizacion.coti_fechaaltatecnica) {
            document.getElementById('fecha_alta_tecnica').value = cotizacion.coti_fechaaltatecnica;
        }

        // Campos de empresa
        if (cotizacion.coti_empresa) {
            document.getElementById('empresa_nombre').value = cotizacion.coti_empresa;
        }

        if (cotizacion.coti_establecimiento) {
            document.getElementById('establecimiento').value = cotizacion.coti_establecimiento;
        }

        if (cotizacion.coti_direccioncli) {
            document.getElementById('direccion_cliente').value = cotizacion.coti_direccioncli;
        }

        if (cotizacion.coti_localidad) {
            document.getElementById('localidad_cliente').value = cotizacion.coti_localidad;
        }

        if (cotizacion.coti_partido) {
            document.getElementById('partido').value = cotizacion.coti_partido;
        }

        if (cotizacion.coti_cuit) {
            document.getElementById('cuit_cliente').value = cotizacion.coti_cuit;
        }

        if (cotizacion.coti_codigopostal) {
            document.getElementById('codigo_postal_cliente').value = cotizacion.coti_codigopostal;
        }

        // Guardar datos de ensayos y componentes en sessionStorage para que se carguen después
        if (ensayos && ensayos.length > 0) {
            sessionStorage.setItem('ensayosParaClonar', JSON.stringify(ensayos));
        }
        if (componentes && componentes.length > 0) {
            sessionStorage.setItem('componentesParaClonar', JSON.stringify(componentes));
        }

        // Esperar un momento para que el script de cotización esté listo
        setTimeout(() => {
            cargarEnsayosYComponentesDesdeClonacion();
        }, 500);
    }

    function cargarEnsayosYComponentesDesdeClonacion() {
        const ensayosData = sessionStorage.getItem('ensayosParaClonar');
        const componentesData = sessionStorage.getItem('componentesParaClonar');

        if (!ensayosData && !componentesData) return;

        // Limpiar sessionStorage
        if (ensayosData) sessionStorage.removeItem('ensayosParaClonar');
        if (componentesData) sessionStorage.removeItem('componentesParaClonar');

        try {
            const ensayos = ensayosData ? JSON.parse(ensayosData) : [];
            const componentes = componentesData ? JSON.parse(componentesData) : [];

            // Intentar acceder al state del script de cotización
            if (window.cotizacionScripts && window.cotizacionScripts.state) {
                const state = window.cotizacionScripts.state;
                
                // Calcular el contador inicial basado en los ensayos existentes
                const maxItemEnsayo = Math.max(
                    ...state.ensayos.map(e => e.item || 0),
                    ...ensayos.map(e => e.item || 0),
                    0
                );
                
                // Agregar ensayos al state
                ensayos.forEach(ensayo => {
                    const ensayoNormalizado = {
                        item: ensayo.item,
                        muestra_id: ensayo.muestra_id || null,
                        descripcion: ensayo.descripcion,
                        codigo: ensayo.codigo || '',
                        cantidad: ensayo.cantidad || 1,
                        precio: null,
                        total: null,
                        tipo: 'ensayo',
                        componentes_sugeridos: [],
                        nota_tipo: ensayo.notas && ensayo.notas.length > 0 ? ensayo.notas[0].tipo : null,
                        nota_contenido: ensayo.notas && ensayo.notas.length > 0 ? ensayo.notas[0].contenido : null,
                    };
                    state.ensayos.push(ensayoNormalizado);
                });

                // Actualizar el contador para que los componentes tengan items únicos
                // Los componentes deben tener items mayores que el máximo item de ensayo
                let contadorComponente = maxItemEnsayo;

                // Agregar componentes al state con items únicos
                componentes.forEach(componente => {
                    contadorComponente++;
                    const componenteNormalizado = {
                        item: contadorComponente, // Item único para el componente
                        subitem: componente.subitem,
                        ensayo_asociado: componente.ensayo_asociado, // El item del ensayo al que pertenece
                        descripcion: componente.analisis && componente.analisis.length > 0 ? componente.analisis[0] : '',
                        codigo: componente.codigo || '',
                        precio: componente.precio || 0.00,
                        cantidad: 1,
                        total: componente.precio || 0.00,
                        tipo: 'componente',
                    };
                    state.componentes.push(componenteNormalizado);
                });
                
                // Actualizar el contador global para futuros items
                state.contador = Math.max(state.contador || 0, contadorComponente);

                // Renderizar tabla y actualizar totales
                if (window.cotizacionScripts.renderTabla) {
                    window.cotizacionScripts.renderTabla();
                }
                if (window.cotizacionScripts.actualizarTotalGeneral) {
                    window.cotizacionScripts.actualizarTotalGeneral();
                }
            } else {
                // Si no está disponible, recargar la página con los datos en los campos hidden
                const ensayosHidden = document.getElementById('ensayos_data');
                const componentesHidden = document.getElementById('componentes_data');
                
                if (ensayosHidden && ensayos.length > 0) {
                    ensayosHidden.value = JSON.stringify(ensayos);
                }
                if (componentesHidden && componentes.length > 0) {
                    componentesHidden.value = JSON.stringify(componentes);
                }

                Swal.fire({
                    icon: 'info',
                    title: 'Recargando...',
                    text: 'La página se recargará para cargar los ensayos y componentes',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            }
        } catch (error) {
            console.error('Error cargando ensayos y componentes:', error);
        }
    }
    
    } // Cierre de initClonacion

})();
</script>
@endsection

