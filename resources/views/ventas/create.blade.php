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
                    <form method="POST" action="{{ route('ventas.store') }}" id="cotizacionForm" enctype="multipart/form-data">
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
                                    @php
                                        $__cotiReqRelCreate = filter_var(old('coti_req_cadena_custodia_relacionada', false), FILTER_VALIDATE_BOOLEAN);
                                    @endphp
                                    <input type="hidden" name="coti_req_cadena_custodia_relacionada" id="input_coti_req_cadena_custodia_relacionada" value="{{ $__cotiReqRelCreate ? '1' : '0' }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="cliente_nombre" class="form-label fw-semibold mb-1">&nbsp;</label>
                                    <input type="text" class="form-control form-control-sm" id="cliente_nombre" 
                                           placeholder="Seleccione un cliente" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label for="sucursal" class="form-label fw-semibold mb-1 text-dark">Sucursal:</label>
                                    <div id="sucursalWrapper">
                                        <input type="text" class="form-control form-control-sm" id="sucursal" name="coti_codigosuc"
                                               value="{{ old('coti_codigosuc') }}" placeholder="Código sucursal">
                                        <select class="form-select form-select-sm d-none mt-1" id="sucursal_select">
                                            <option value="">Seleccionar sucursal...</option>
                                        </select>
                                    </div>
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
                                        <select class="form-control form-control-sm d-none" id="coti_para_select">
                                            <option value="">Seleccionar empresa relacionada...</option>
                                        </select>
                                        <input type="hidden" id="coti_empresa_rel" name="coti_empresa_rel" value="{{ old('coti_empresa_rel', old('coti_cli_empresa')) }}">
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
                            @include('ventas.partials.cotizacion-empresa-relacionada-nav')
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
                                                <option value="Suspendida">Suspendida</option>
                                            </select>
                                        </div>
                         
                                        <!-- Muestreo ahora se configura por ensayo, no a nivel general -->
                                    </div>

                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="coti_prioridad_global_chk" value="1">
                                                <label class="form-check-label fw-semibold" for="coti_prioridad_global_chk">
                                                    ★ Marcar todos los ensayos con prioridad de muestreo
                                                </label>
                                            </div>
                                            <small class="text-muted">Activa o desactiva la prioridad en todos los ensayos ya cargados. Para uno solo, usá el checkbox dentro del modal al agregar o editar un ensayo.</small>
                                        </div>
                                    </div>

                                    <!-- Contactos del cliente -->
                                    @include('ventas.partials.cotizacion-contactos')

                                    @include('ventas.partials.cotizacion-notas-generales', [
                                        'notasGeneralesRaw' => old('coti_notas'),
                                    ])

                                    <!-- Sección de Descuentos / Aumentos -->
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <h5 class="mb-3">Descuentos / Aumentos</h5>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <label for="descuento" class="form-label">Descuento Global %</label>
                                                    <input type="number" step="0.01" class="form-control" id="descuento" name="descuento" 
                                                           value="{{ old('descuento', '0.00') }}" placeholder="0.00">
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" id="coti_mostrar_descuento" name="coti_mostrar_descuento" value="1" checked>
                                                        <label class="form-check-label" for="coti_mostrar_descuento">
                                                            Mostrar en el presupuesto
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="aumento" class="form-label">Aumento Global %</label>
                                                    <input type="number" step="0.01" class="form-control" id="aumento" name="aumento" 
                                                           value="{{ old('aumento', '0.00') }}" placeholder="0.00">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="divisa_codigo" class="form-label">Divisa</label>
                                                    <select class="form-select" id="divisa_codigo" name="divisa_codigo">
                                                        @forelse(($divisas ?? []) as $divisa)
                                                            <option value="{{ $divisa->divisa_codigo }}" {{ old('divisa_codigo', 'PES') === $divisa->divisa_codigo ? 'selected' : '' }}>
                                                                {{ $divisa->divisa_desc }} ({{ $divisa->divisa_codigo }})
                                                            </option>
                                                        @empty
                                                            <option value="PES" selected>Pesos (PES)</option>
                                                            <option value="USD">Dólares (USD)</option>
                                                        @endforelse
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="coti_cond_pago" class="form-label">Condición de pago</label>
                                                    <select class="form-select" id="coti_cond_pago" name="coti_cond_pago">
                                                        <option value="">Seleccionar...</option>
                                                        @foreach($condicionesPago ?? [] as $cond)
                                                            <option value="{{ trim($cond->pag_codigo) }}" {{ old('coti_cond_pago') === trim($cond->pag_codigo) ? 'selected' : '' }}>
                                                                {{ trim($cond->pag_codigo) }} - {{ trim($cond->pag_descripcion) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <small class="text-muted">Se completa con la del cliente al seleccionarlo; puede cambiarse solo para esta cotización.</small>
                                                </div>
                                            </div>

                                            <div id="cuotasPanel" class="row mb-3 border rounded p-3 bg-light d-none">
                                                <h6 class="mb-2">Detalle cuotas</h6>
                                                <div class="col-md-3">
                                                    <label for="coti_cuota_desc" class="form-label">Descripción</label>
                                                    <input type="text" class="form-control form-control-sm" id="coti_cuota_desc" name="coti_cuota_desc" value="{{ old('coti_cuota_desc') }}" placeholder="Ej: 6 cuotas sin interés">
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="coti_cuota_cant" class="form-label">Cantidad</label>
                                                    <input type="number" class="form-control form-control-sm" id="coti_cuota_cant" name="coti_cuota_cant" value="{{ old('coti_cuota_cant', 1) }}" min="1">
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="coti_cuota_interes" class="form-label">
                                                        Interés (%)
                                                        <span class="text-muted" title="Porcentaje de interés total aplicado sobre el monto de la cotización antes de dividir en cuotas. Ej: 10 = 10%" style="cursor:help;">&#9432;</span>
                                                    </label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="coti_cuota_interes" name="coti_cuota_interes" value="{{ old('coti_cuota_interes', 0) }}" placeholder="0.00">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="coti_cuota_monto_total" class="form-label">
                                                        Monto total
                                                        <span class="text-muted" title="Se completa automáticamente con el total de la cotización" style="cursor:help;">&#9432;</span>
                                                    </label>
                                                    <input type="number" step="0.01" class="form-control form-control-sm bg-light" id="coti_cuota_monto_total" name="coti_cuota_monto_total" value="{{ old('coti_cuota_monto_total') }}" placeholder="0.00" readonly title="Se sincroniza automáticamente con el total de la cotización">
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="coti_cuota_monto_indiv" class="form-label">
                                                        Monto individual
                                                        <span class="text-muted" title="(Total × (1 + Interés%)) ÷ cantidad de cuotas" style="cursor:help;">&#9432;</span>
                                                    </label>
                                                    <input type="number" step="0.01" class="form-control form-control-sm bg-light" id="coti_cuota_monto_indiv" name="coti_cuota_monto_indiv" value="{{ old('coti_cuota_monto_indiv') }}" placeholder="0.00" readonly title="Se calcula: (monto total × (1 + interés%)) ÷ cantidad de cuotas">
                                                </div>
                                                <div class="col-md-3 d-flex align-items-end gap-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="coti_cuota_fact_fin_mes" name="coti_cuota_fact_fin_mes" value="1" {{ old('coti_cuota_fact_fin_mes') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="coti_cuota_fact_fin_mes">Fact. fin de mes</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="coti_cuota_fact_inicio_mes" name="coti_cuota_fact_inicio_mes" value="1" {{ old('coti_cuota_fact_inicio_mes') ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="coti_cuota_fact_inicio_mes">Fact. inicio de mes</label>
                                                    </div>
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
                                                            <th style="width: 118px;" class="text-end" title="En analitos: precio unitario. En el ensayo: solo cargo adicional por u.m.">P. unit.</th>
                                                            <th style="width: 118px;" class="text-end" title="En analitos: precio × cantidad. En el ensayo: adicional × cantidad del ensayo (el total general suma también los analitos).">Importe</th>
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
                                                        <tr class="d-none" id="filaAumentoGlobal">
                                                            <td colspan="7" class="text-end text-muted">Aumento global cliente (<span id="aumentoGlobalPorcentaje">0.00%</span>):</td>
                                                            <td class="text-success fw-semibold">
                                                                +<span id="aumentoGlobalMonto">0.00</span>
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
                                                            <td colspan="7" class="text-end fw-bold">
                                                                Total final (<span id="divisaLabel"></span>):
                                                            </td>
                                                            <td class="fw-bold">
                                                                <span id="totalConAjustes">0.00</span>
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
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="razon_social_facturacion_select" class="form-label">Razón social de facturación</label>
                                            <select class="form-select form-select-sm d-none" id="razon_social_facturacion_select">
                                                <option value="">Seleccionar razón social de facturación...</option>
                                            </select>
                                            <small class="form-text text-muted d-none" id="razon_social_facturacion_help">
                                                Al seleccionar una razón social se actualizarán la empresa, CUIT y dirección.
                                            </small>
                                        </div>
                                    </div>
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

                            @include('ventas.partials.cotizacion-empresa-relacionada-pane')
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
                                    NO requiere cadena de custodia
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="req_prot_mapba">
                                <label class="form-check-label" for="req_prot_mapba">
                                    Requiere Protocolo Oficial MAPBA Res 41/14
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="ensayo_chk_req_cadena_relacionada">
                                <label class="form-check-label" for="ensayo_chk_req_cadena_relacionada">
                                    Requiere cadena de custodia relada
                                </label>
                            </div>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="ensayo_no_lleva_muestreo">
                                <label class="form-check-label" for="ensayo_no_lleva_muestreo">
                                    No lleva muestreo
                                </label>
                            </div>
                            <small class="text-muted d-block">Consultoría, ASP y Clarke Fire siempre van sin muestreo (no se puede desmarcar). Mediciones siempre llevan muestreo. En otros ensayos puede tildar esta opción manualmente.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check border border-warning rounded p-2 bg-warning bg-opacity-10" id="ensayo_prioridad_wrap_agregar">
                                <input class="form-check-input" type="checkbox" id="ensayo_es_priori" value="1">
                                <label class="form-check-label fw-semibold" for="ensayo_es_priori">
                                    ★ Ensayo con prioridad de muestreo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-2">
                            <label for="cantidad_ensayo" class="form-label">Cantidad:</label>
                            <input type="number" class="form-control" id="cantidad_ensayo" name="cantidad" value="3" min="1">
                        </div>
                        <div class="col-md-3">
                            <label for="ensayo_precio_extra" class="form-label">Precio adic. ensayo (u.m.)</label>
                            <input type="number" class="form-control" id="ensayo_precio_extra" name="ensayo_precio_extra" value="0" min="0" step="0.01" placeholder="0.00">
                            <small class="text-muted">Suma por unidad de ensayo, aparte de los analitos.</small>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="flexible">
                                <label class="form-check-label" for="flexible">Flexible</label>
                            </div>
                        </div>
                        <div class="col-md-5">
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

                    @include('ventas.partials.ensayo-adjuntos-campo', [
                        'inputId' => 'ensayo_adjuntos_input',
                        'listaId' => 'ensayoAdjuntosLista',
                    ])

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
                            <small class="text-muted">Cada nota admite un máximo de 150 caracteres.</small>
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
                            <label for="componente_analisis" class="form-label">Análisis del ensayo <span class="text-danger">*</span></label>
                            <p id="componente_analisis_ayuda" class="text-muted small mb-2">
                                <strong>1.</strong> Busque abajo para agregar análisis.
                                <strong>2.</strong> Revise la lista y use «Quitar» en los que no correspondan.
                            </p>
                            <span class="d-block form-label form-label-sm text-muted mb-1">Agregar análisis</span>
                            <select class="form-select" id="componente_analisis" name="componente_analisis[]" multiple required>
                                <option disabled value="">Buscar análisis...</option>
                            </select>
                            <div id="componentes_seleccionados_panel" class="componentes-seleccionados-panel d-none mt-2">
                                <div class="componentes-seleccionados-header">
                                    <span class="componentes-seleccionados-titulo">
                                        Análisis seleccionados (<span id="componentes_seleccionados_count">0</span>)
                                    </span>
                                    <button type="button"
                                            id="btnComponentesQuitarTodos"
                                            class="btn btn-link btn-sm text-danger p-0 componentes-seleccionados-vaciar">
                                        Vaciar lista
                                    </button>
                                </div>
                                <input type="search"
                                       id="componentes_seleccionados_buscar"
                                       class="form-control form-control-sm componentes-seleccionados-buscar d-none mt-2"
                                       placeholder="Filtrar en la lista..."
                                       autocomplete="off">
                                <div id="componentes_seleccionados_lista" class="componentes-seleccionados-lista mt-2"></div>
                            </div>
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
                                    NO requiere cadena de custodia
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="comp_req_prot_mapba">
                                <label class="form-check-label" for="comp_req_prot_mapba">
                                    Requiere Protocolo Oficial MAPBA Res 41/14
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

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="comp_nota_imprimible_texto" class="form-label">Nota imprimible</label>
                            <textarea class="form-control" id="comp_nota_imprimible_texto" name="comp_nota_imprimible_texto" rows="3" maxlength="150" placeholder="Texto que verá el cliente (se sugiere desde el ítem de catálogo)"></textarea>
                            <small class="text-muted">Máximo 150 caracteres.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="comp_nota_interna_texto" class="form-label">Nota interna</label>
                            <textarea class="form-control" id="comp_nota_interna_texto" name="comp_nota_interna_texto" rows="3" maxlength="150" placeholder="Uso interno (se sugiere desde el ítem de catálogo)"></textarea>
                            <small class="text-muted">Máximo 150 caracteres.</small>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Con un solo análisis seleccionado puede editar aquí; con varios, cada ítem usa las notas por defecto de su determinación.</small>
                        </div>
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

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_comp_req_cadena_custodia">
                                <label class="form-check-label" for="edit_comp_req_cadena_custodia">
                                    Requiere Cadena de Custodia
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_comp_req_prot_mapba">
                                <label class="form-check-label" for="edit_comp_req_prot_mapba">
                                    Requiere Protocolo Oficial MAPBA Res 41/14
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_comp_nota_imprimible" class="form-label">Nota imprimible</label>
                            <textarea class="form-control" id="edit_comp_nota_imprimible" name="edit_comp_nota_imprimible" rows="3" maxlength="150" placeholder="Texto para el cliente en cotización / PDF"></textarea>
                            <small class="text-muted">Máximo 150 caracteres.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_comp_nota_interna" class="form-label">Nota interna</label>
                            <textarea class="form-control" id="edit_comp_nota_interna" name="edit_comp_nota_interna" rows="3" maxlength="150" placeholder="Uso interno"></textarea>
                            <small class="text-muted">Máximo 150 caracteres.</small>
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

<!-- Modal Editar Ensayo -->
<div class="modal fade" id="modalEditarEnsayo" tabindex="-1" aria-labelledby="modalEditarEnsayoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalEditarEnsayoLabel">Editar Ensayo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarEnsayo">
                    <input type="hidden" id="edit_ensayo_item_id">
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_ensayo_muestra" class="form-label">Seleccionar Muestra/Ensayo <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_ensayo_muestra" name="edit_ensayo_muestra" required>
                                <option value="">Seleccionar muestra...</option>
                            </select>
                            <small class="text-muted">Seleccione el tipo de muestra que desea analizar</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="edit_ensayo_codigo" class="form-label">Código:</label>
                            <input type="text" class="form-control" id="edit_ensayo_codigo" name="edit_ensayo_codigo" 
                                   placeholder="Se generará automáticamente" readonly>
                        </div>
                        <div class="col-md-2">
                            <label for="edit_ensayo_cantidad" class="form-label">Cantidad:</label>
                            <input type="number" class="form-control" id="edit_ensayo_cantidad" name="edit_ensayo_cantidad" value="1" min="1" step="1">
                        </div>
                        <div class="col-md-3">
                            <label for="edit_ensayo_precio_extra" class="form-label">Precio adic. ensayo (u.m.)</label>
                            <input type="number" class="form-control" id="edit_ensayo_precio_extra" name="edit_ensayo_precio_extra" value="0" min="0" step="0.01" placeholder="0.00">
                            <small class="text-muted">Aparte del total de analitos.</small>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="edit_ensayo_lleva_muestreo">
                                <label class="form-check-label" for="edit_ensayo_lleva_muestreo">
                                    No lleva muestreo
                                </label>
                            </div>
                            <small class="text-muted d-block">Consultoría, ASP y Clarke Fire siempre van sin muestreo (no se puede desmarcar). Mediciones siempre llevan muestreo.</small>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="form-check border border-warning rounded p-2 bg-warning bg-opacity-10" id="ensayo_prioridad_wrap_editar">
                                <input class="form-check-input" type="checkbox" id="edit_ensayo_es_priori" value="1">
                                <label class="form-check-label fw-semibold" for="edit_ensayo_es_priori">
                                    ★ Ensayo con prioridad de muestreo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_ensayo_no_requiere_cadena_custodia">
                                <label class="form-check-label" for="edit_ensayo_no_requiere_cadena_custodia">
                                    NO requiere cadena de custodia
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_ensayo_req_prot_mapba">
                                <label class="form-check-label" for="edit_ensayo_req_prot_mapba">
                                    Requiere Protocolo Oficial MAPBA Res 41/14
                                </label>
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="edit_ensayo_chk_req_cadena_relacionada">
                                <label class="form-check-label" for="edit_ensayo_chk_req_cadena_relacionada">
                                    Requiere cadena de custodia relada
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_ensayo_ley_normativa" class="form-label">Ley/Normativa:</label>
                            <select class="form-select" id="edit_ensayo_ley_normativa" name="edit_ensayo_ley_normativa">
                                <option value="">Seleccionar normativa...</option>
                            </select>
                        </div>
                    </div>

                    @include('ventas.partials.ensayo-adjuntos-campo', [
                        'inputId' => 'edit_ensayo_adjuntos_input',
                        'listaId' => 'editEnsayoAdjuntosLista',
                    ])

                    <!-- Sección de Notas Múltiples -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0 fw-semibold">Notas:</label>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarNotaEditEnsayo">
                                    <x-heroicon-o-plus style="width: 14px; height: 14px;" class="me-1" />
                                    Agregar Nota
                                </button>
                            </div>
                            <div id="notasEditEnsayoContainer">
                                <!-- Las notas se agregarán dinámicamente aquí -->
                            </div>
                            <small class="text-muted">Cada nota admite un máximo de 150 caracteres.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarEnsayoEditado">Guardar Cambios</button>
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
                                    <option value="Suspendida">Suspendida</option>
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
        $configuracionCotizacion['puedeBajarPrecio'] = auth()->user() && ((int) (auth()->user()->usu_nivel ?? 0) >= 900);
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
                'En Proceso': 'info',
                'Suspendida': 'secondary'
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
        const fechaHoy = new Date().toISOString().slice(0, 10);

        // Algunos datos (sucursal/contactos) pueden ser sobreescritos por la carga asíncrona
        // del cliente luego de setear el código. Guardamos una copia para re-aplicarlos.
        const clonSuc = (cotizacion.coti_codigosuc || '').toString();
        const clonContactos = {
            c1: { nombre: cotizacion.coti_contacto || '', correo: cotizacion.coti_mail1 || '', tel: cotizacion.coti_telefono || '', tipo: cotizacion.coti_contacto_tipo1 || '' },
            c2: { nombre: cotizacion.coti_contacto2 || '', correo: cotizacion.coti_mail2 || '', tel: cotizacion.coti_telefono2 || '', tipo: cotizacion.coti_contacto_tipo2 || '' },
            c3: { nombre: cotizacion.coti_contacto3 || '', correo: cotizacion.coti_mail3 || '', tel: cotizacion.coti_telefono3 || '', tipo: cotizacion.coti_contacto_tipo3 || '' },
            c4: { nombre: cotizacion.coti_contacto4 || '', correo: cotizacion.coti_mail4 || '', tel: cotizacion.coti_telefono4 || '', tipo: cotizacion.coti_contacto_tipo4 || '' },
        };

        const aplicarSucursalYContactos = () => {
            try {
                const sucEl = document.getElementById('sucursal');
                if (sucEl) sucEl.value = clonSuc;
                const sucSel = document.getElementById('sucursal_select');
                if (sucSel) {
                    const valor = clonSuc;
                    if (valor) {
                        // Si aún no están cargadas las opciones, agregamos una opción temporal
                        // para que el valor quede seleccionado igual.
                        if (!Array.from(sucSel.options || []).some(o => String(o.value).trim() === String(valor).trim())) {
                            const opt = document.createElement('option');
                            opt.value = valor;
                            opt.textContent = valor;
                            sucSel.appendChild(opt);
                        }
                        sucSel.value = valor;
                    }
                    // Mostrar el select si el flujo de UI lo usa
                    sucSel.classList.remove('d-none');
                    // Disparar change para que cualquier listener actualice UI/estado
                    sucSel.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const c1 = document.getElementById('contacto');
                const m1 = document.getElementById('correo');
                const t1 = document.getElementById('telefono');
                const tipo1 = document.getElementById('coti_contacto_tipo1');
                if (c1) c1.value = clonContactos.c1.nombre || '';
                if (m1) m1.value = clonContactos.c1.correo || '';
                if (t1) t1.value = clonContactos.c1.tel || '';
                if (tipo1) tipo1.value = clonContactos.c1.tipo || '';

                const ensureBlockVisible = (idx) => {
                    const block = document.getElementById(`contacto-block-${idx}`);
                    if (block) block.classList.remove('d-none');
                };
                const setExtra = (idx, data) => {
                    const c = document.getElementById(`contacto${idx}`);
                    const m = document.getElementById(`correo${idx}`);
                    const t = document.getElementById(`telefono${idx}`);
                    const tipo = document.getElementById(`coti_contacto_tipo${idx}`);
                    if (c) c.value = data.nombre || '';
                    if (m) m.value = data.correo || '';
                    if (t) t.value = data.tel || '';
                    if (tipo) tipo.value = data.tipo || '';
                };

                if (clonContactos.c2.nombre || clonContactos.c2.correo || clonContactos.c2.tel || clonContactos.c2.tipo) {
                    ensureBlockVisible(2);
                    setExtra(2, clonContactos.c2);
                }
                if (clonContactos.c3.nombre || clonContactos.c3.correo || clonContactos.c3.tel || clonContactos.c3.tipo) {
                    ensureBlockVisible(3);
                    setExtra(3, clonContactos.c3);
                }
                if (clonContactos.c4.nombre || clonContactos.c4.correo || clonContactos.c4.tel || clonContactos.c4.tipo) {
                    ensureBlockVisible(4);
                    setExtra(4, clonContactos.c4);
                }
            } catch (e) {
                console.warn('No se pudo aplicar sucursal/contactos del clon:', e);
            }
        };

        // Rellenar campos básicos
        if (cotizacion.coti_codigocli) {
            // Poner sucursal/contactos ANTES de disparar la carga del cliente para que el script
            // que preselecciona sucursal tenga el valor ya disponible.
            aplicarSucursalYContactos();

            // Señal global para que el script del cliente no pise el clon
            window.__clonCotizacionOverride = { sucursal: clonSuc, contactos: clonContactos };
            window.__clonCotizacionOverrideActive = true;

            document.getElementById('cliente_codigo').value = cotizacion.coti_codigocli;
            // Disparar evento para cargar datos del cliente
            const evento = new Event('input', { bubbles: true });
            document.getElementById('cliente_codigo').dispatchEvent(evento);
        }

        if (cotizacion.coti_descripcion) {
            document.getElementById('descripcion').value = cotizacion.coti_descripcion;
        }

        document.getElementById('fecha_alta').value = fechaHoy;

        if (cotizacion.coti_fechafin) {
            document.getElementById('fecha_venc').value = cotizacion.coti_fechafin;
        }

        if (cotizacion.coti_estado) {
            const estadoSelect = document.getElementById('estado');
            if (estadoSelect) {
                estadoSelect.value = cotizacion.coti_estado;
            }
        }

        const hRelClon = document.getElementById('input_coti_req_cadena_custodia_relacionada');
        if (hRelClon) {
            const rel = cotizacion.coti_req_cadena_custodia_relacionada === true
                || cotizacion.coti_req_cadena_custodia_relacionada === 1
                || cotizacion.coti_req_cadena_custodia_relacionada === '1';
            hRelClon.value = rel ? '1' : '0';
        }
        if (window.cotizacionScripts && typeof window.cotizacionScripts.syncCotiReqCadenaRelCheckboxesFromHidden === 'function') {
            window.cotizacionScripts.syncCotiReqCadenaRelCheckboxesFromHidden();
        }

        if (cotizacion.coti_codigosuc) {
            document.getElementById('sucursal').value = cotizacion.coti_codigosuc;
        }

        if (cotizacion.coti_para) {
            document.getElementById('coti_para').value = cotizacion.coti_para;
        }

        const hidEmpRelClon = document.getElementById('coti_empresa_rel');
        if (hidEmpRelClon) {
            const vRel = cotizacion.coti_empresa_rel ?? cotizacion.coti_cli_empresa;
            hidEmpRelClon.value = (vRel !== null && vRel !== undefined && vRel !== '') ? String(vRel) : '';
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

        // Copiar contactos adicionales (2 a 4) de la cotización original
        if (cotizacion.coti_contacto2 || cotizacion.coti_mail2 || cotizacion.coti_telefono2 || cotizacion.coti_contacto_tipo2) {
            const block2 = document.getElementById('contacto-block-2');
            if (block2) {
                block2.classList.remove('d-none');
            }
            const c2 = document.getElementById('contacto2');
            const m2 = document.getElementById('correo2');
            const t2 = document.getElementById('telefono2');
            const tipo2 = document.getElementById('coti_contacto_tipo2');
            if (c2) c2.value = cotizacion.coti_contacto2 || '';
            if (m2) m2.value = cotizacion.coti_mail2 || '';
            if (t2) t2.value = cotizacion.coti_telefono2 || '';
            if (tipo2) tipo2.value = cotizacion.coti_contacto_tipo2 || '';
        }

        if (cotizacion.coti_contacto3 || cotizacion.coti_mail3 || cotizacion.coti_telefono3 || cotizacion.coti_contacto_tipo3) {
            const block3 = document.getElementById('contacto-block-3');
            if (block3) {
                block3.classList.remove('d-none');
            }
            const c3 = document.getElementById('contacto3');
            const m3 = document.getElementById('correo3');
            const t3 = document.getElementById('telefono3');
            const tipo3 = document.getElementById('coti_contacto_tipo3');
            if (c3) c3.value = cotizacion.coti_contacto3 || '';
            if (m3) m3.value = cotizacion.coti_mail3 || '';
            if (t3) t3.value = cotizacion.coti_telefono3 || '';
            if (tipo3) tipo3.value = cotizacion.coti_contacto_tipo3 || '';
        }

        if (cotizacion.coti_contacto4 || cotizacion.coti_mail4 || cotizacion.coti_telefono4 || cotizacion.coti_contacto_tipo4) {
            const block4 = document.getElementById('contacto-block-4');
            if (block4) {
                block4.classList.remove('d-none');
            }
            const c4 = document.getElementById('contacto4');
            const m4 = document.getElementById('correo4');
            const t4 = document.getElementById('telefono4');
            const tipo4 = document.getElementById('coti_contacto_tipo4');
            if (c4) c4.value = cotizacion.coti_contacto4 || '';
            if (m4) m4.value = cotizacion.coti_mail4 || '';
            if (t4) t4.value = cotizacion.coti_telefono4 || '';
            if (tipo4) tipo4.value = cotizacion.coti_contacto_tipo4 || '';
        }

        // Re-aplicar varias veces hasta que termine la carga asíncrona del cliente
        // (carga de sucursales/contactos registrados suele sobreescribir campos).
        aplicarSucursalYContactos();
        let intentosClon = 0;
        const maxIntentosClon = 30; // ~6s
        const t = setInterval(() => {
            intentosClon++;
            aplicarSucursalYContactos();
            const sucSel = document.getElementById('sucursal_select');
            const yaSeleccionoSucursal = !clonSuc || (sucSel && String(sucSel.value || '').trim() === String(clonSuc).trim());
            if (yaSeleccionoSucursal || intentosClon >= maxIntentosClon) {
                clearInterval(t);
            }
        }, 200);

        if (cotizacion.coti_sector) {
            const sectorSelect = document.getElementById('sector');
            if (sectorSelect) {
                sectorSelect.value = cotizacion.coti_sector;
            }
        }

        if (typeof window.cotizacionNotasGeneralesCargarDesdeAlmacenamiento === 'function') {
            window.cotizacionNotasGeneralesCargarDesdeAlmacenamiento(cotizacion.coti_notas || '');
        }

        if (cotizacion.descuento) {
            document.getElementById('descuento').value = cotizacion.descuento;
        }

        // Al clonar, nunca copiar aumento global: siempre iniciar en 0
        if (document.getElementById('aumento')) {
            document.getElementById('aumento').value = '0.00';
        }

        if (cotizacion.divisa_codigo) {
            const divisaSelect = document.getElementById('divisa_codigo');
            if (divisaSelect) divisaSelect.value = cotizacion.divisa_codigo;
        }

        if (cotizacion.coti_cond_pago) {
            const condPagoSelect = document.getElementById('coti_cond_pago');
            if (condPagoSelect) {
                condPagoSelect.value = cotizacion.coti_cond_pago;
                condPagoSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        const cuotasPanel = document.getElementById('cuotasPanel');
        if (cuotasPanel && cotizacion.coti_cuotas) {
            const desc = document.getElementById('coti_cuota_desc');
            const cant = document.getElementById('coti_cuota_cant');
            const total = document.getElementById('coti_cuota_monto_total');
            const indiv = document.getElementById('coti_cuota_monto_indiv');
            const finMes = document.getElementById('coti_cuota_fact_fin_mes');
            const inicioMes = document.getElementById('coti_cuota_fact_inicio_mes');
            const interes = document.getElementById('coti_cuota_interes');
            if (desc) desc.value = cotizacion.coti_cuota_desc || '';
            if (cant) cant.value = cotizacion.coti_cuota_cant || 1;
            if (interes) interes.value = cotizacion.coti_cuota_interes ?? 0;
            if (total) total.value = cotizacion.coti_cuota_monto_total ?? '';
            if (indiv) indiv.value = cotizacion.coti_cuota_monto_indiv ?? '';
            if (finMes) finMes.checked = !!cotizacion.coti_cuota_fact_fin_mes;
            if (inicioMes) inicioMes.checked = !!cotizacion.coti_cuota_fact_inicio_mes;
        }

        if (cotizacion.coti_cadena_custodia) {
            const el = document.getElementById('cadena_custodia');
            if (el) el.checked = true;
        }

        if (cotizacion.coti_muestreo) {
            document.getElementById('muestreo').checked = true;
        }

        // Campos de gestión
        if (cotizacion.coti_responsable) {
            document.getElementById('responsable').value = cotizacion.coti_responsable;
        }

        const fechaAprobadoEl = document.getElementById('fecha_aprobado');
        if (fechaAprobadoEl) {
            const esAprobada = String(cotizacion.coti_estado || '').toLowerCase().includes('aprobad');
            fechaAprobadoEl.value = esAprobada ? fechaHoy : '';
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

        if (typeof window.cotizacionRefsFacturacionCargarDesdeDatos === 'function') {
            window.cotizacionRefsFacturacionCargarDesdeDatos({
                coti_oc_referencia: cotizacion.coti_oc_referencia || '',
                coti_oc_requerido_factura: !!cotizacion.coti_oc_requerido_factura,
                coti_refs_facturacion_json: cotizacion.coti_refs_facturacion_json || '',
            });
        }

        // Guardar datos de ensayos y componentes en sessionStorage para que se carguen después
        if (ensayos && ensayos.length > 0) {
            sessionStorage.setItem('ensayosParaClonar', JSON.stringify(ensayos));
        }
        if (componentes && componentes.length > 0) {
            sessionStorage.setItem('componentesParaClonar', JSON.stringify(componentes));
        }
        sessionStorage.setItem('clonPrioridadGlobal', cotizacion.coti_prioridad_global ? '1' : '0');

        // Esperar a que el script de cotización esté listo y renderice la tabla
        cargarEnsayosYComponentesDesdeClonacion();
    }

    function cargarEnsayosYComponentesDesdeClonacion() {
        const ensayosData = sessionStorage.getItem('ensayosParaClonar');
        const componentesData = sessionStorage.getItem('componentesParaClonar');

        if (!ensayosData && !componentesData) return;

        let ensayos = [];
        let componentes = [];
        try {
            ensayos = ensayosData ? JSON.parse(ensayosData) : [];
            componentes = componentesData ? JSON.parse(componentesData) : [];
        } catch (error) {
            console.error('Error parseando datos de clonación:', error);
            sessionStorage.removeItem('ensayosParaClonar');
            sessionStorage.removeItem('componentesParaClonar');
            return;
        }

        sessionStorage.removeItem('ensayosParaClonar');
        sessionStorage.removeItem('componentesParaClonar');
        const clonPrioridadGlobal = sessionStorage.getItem('clonPrioridadGlobal') === '1';
        sessionStorage.removeItem('clonPrioridadGlobal');

        const aplicarClonacion = () => {
            if (!window.cotizacionScripts || typeof window.cotizacionScripts.cargarItemsDesdeVersion !== 'function') {
                return false;
            }

            window.cotizacionScripts.cargarItemsDesdeVersion(ensayos, componentes);

            if (clonPrioridadGlobal && window.cotizacionScripts.aplicarPrioridadATodosLosEnsayos) {
                window.cotizacionScripts.aplicarPrioridadATodosLosEnsayos(true);
            }
            if (window.cotizacionScripts.sincronizarCheckboxGlobalPrioridad) {
                window.cotizacionScripts.sincronizarCheckboxGlobalPrioridad();
            }

            return true;
        };

        let intentos = 0;
        const maxIntentos = 30;
        const intervaloMs = 200;

        const intentarCargar = () => {
            intentos += 1;
            if (aplicarClonacion()) {
                return;
            }
            if (intentos >= maxIntentos) {
                console.warn('No se pudo cargar la clonación: cotizacionScripts no disponible a tiempo.');
                return;
            }
            setTimeout(intentarCargar, intervaloMs);
        };

        intentarCargar();
    }
    
    } // Cierre de initClonacion

})();
</script>
@endsection

