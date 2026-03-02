@extends('layouts.app')

@section('content')

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">Crear Nuevo Cliente</h2>
                <div>
                    <a href="{{ route('clientes.index') }}" class="btn btn-secondary me-2">
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
                    <form method="POST" action="{{ route('clientes.store') }}" id="clienteForm">
                        @csrf
                        
                        <!-- Header con código y estado -->
                        <div class="border-bottom px-4 py-3 bg-light">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <label for="codigo" class="form-label fw-semibold mb-1">Código:</label>
                                    <input type="text" class="form-control form-control-sm" id="codigo" name="codigo" 
                                           value="{{ old('codigo') }}" placeholder="Autogenerado">
                                </div>
                                <div class="col-md-6"></div>
                            </div>
                        </div>

                        <!-- Navegación de solapas -->
                        <ul class="nav nav-tabs nav-tabs-custom" id="clienteTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" 
                                        data-bs-target="#general" type="button" role="tab">
                                    General
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="facturacion-tab" data-bs-toggle="tab" 
                                        data-bs-target="#facturacion" type="button" role="tab">
                                    Facturación
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="contactos-tab" data-bs-toggle="tab" 
                                        data-bs-target="#contactos" type="button" role="tab">
                                    Contactos
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link disabled" type="button">Cobranza</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link disabled" type="button">Observaciones</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link disabled" type="button">Actividades</button>
                            </li>
                            <li class="nav-item" role="presentation" id="empresas-relacionadas-tab-item" style="display: none;">
                                <button class="nav-link" id="empresas-relacionadas-tab" data-bs-toggle="tab" 
                                        data-bs-target="#empresas-relacionadas" type="button" role="tab">
                                    Empresas Relacionadas
                                </button>
                            </li>
                        </ul>

                        <!-- Contenido de las solapas -->
                        <div class="tab-content" id="clienteTabsContent">
                            <!-- Solapa General -->
                            <div class="tab-pane fade show active" id="general" role="tabpanel">
                                <div class="p-4">
                                    <div class="row">
                                        <!-- Columna izquierda -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="razon_social" class="form-label">Razón Social <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control @error('razon_social') is-invalid @enderror" 
                                                       id="razon_social" name="razon_social" 
                                                       value="{{ old('razon_social') }}" required>
                                                @error('razon_social')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label d-block">Estado</label>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="activo" id="estado_activo_si" value="1"
                                                            {{ old('activo', '1') == '1' ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="estado_activo_si">Activo</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="radio" name="activo" id="estado_activo_no" value="0"
                                                            {{ old('activo') == '0' ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="estado_activo_no">Inactivo</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="es_consultor" name="es_consultor" value="1"
                                                        {{ old('es_consultor') ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="es_consultor">
                                                        Es Consultor
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="fantasia" class="form-label">Fantasía</label>
                                                <input type="text" class="form-control" id="fantasia" name="fantasia" 
                                                       value="{{ old('fantasia') }}">
                                            </div>

                                            <div class="mb-3">
                                                <label for="direccion" class="form-label">Dirección</label>
                                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                                       value="{{ old('direccion') }}">
                                            </div>

                                            <div class="mb-3">
                                                <label for="partido" class="form-label">Partido</label>
                                                <input type="text" class="form-control" id="partido" name="partido" 
                                                       value="{{ old('partido') }}">
                                            </div>

                                            <div class="mb-3">
                                                <label for="localidad" class="form-label">Localidad</label>
                                                <input type="text" class="form-control" id="localidad" name="localidad" 
                                                       value="{{ old('localidad') }}">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="provincia" class="form-label">Provincia</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" id="provincia_codigo" name="provincia_codigo" 
                                                                   value="{{ old('provincia_codigo') }}" placeholder="Código (máx. 5 caracteres)" maxlength="5">
                                                            <input type="text" class="form-control" id="provincia_nombre" name="provincia_nombre" 
                                                                   value="{{ old('provincia_nombre') }}" placeholder="Nombre">
                                                            <button class="btn btn-outline-secondary" type="button">
                                                                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="zona" class="form-label">Zona:</label>
                                                        <select class="form-select" id="zona_codigo" name="zona_codigo">
                                                            <option value="">Seleccionar zona...</option>
                                                            @foreach($zonas as $zona)
                                                                <option value="{{ $zona->zon_codigo }}" 
                                                                        {{ old('zona_codigo') == $zona->zon_codigo ? 'selected' : '' }}>
                                                                    {{ trim($zona->zon_codigo) }} - {{ trim($zona->zon_descripcion) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>


                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="rubro" class="form-label">Rubro</label>
                                                        <select class="form-select" id="rubro_codigo" name="rubro_codigo">
                                                            <option value="">Seleccionar rubro...</option>
                                                            <option value="001" data-descripcion="INDUSTRIA ALIMENTARIA" {{ old('rubro_codigo') == '001' ? 'selected' : '' }}>001 - INDUSTRIA ALIMENTARIA</option>
                                                            <option value="002" data-descripcion="INDUSTRIA FARMACÉUTICA" {{ old('rubro_codigo') == '002' ? 'selected' : '' }}>002 - INDUSTRIA FARMACÉUTICA</option>
                                                            <option value="003" data-descripcion="INDUSTRIA QUÍMICA" {{ old('rubro_codigo') == '003' ? 'selected' : '' }}>003 - INDUSTRIA QUÍMICA</option>
                                                            <option value="004" data-descripcion="INDUSTRIA TEXTIL" {{ old('rubro_codigo') == '004' ? 'selected' : '' }}>004 - INDUSTRIA TEXTIL</option>
                                                            <option value="005" data-descripcion="HIGIENE Y SEGURIDAD" {{ old('rubro_codigo') == '005' ? 'selected' : '' }}>005 - HIGIENE Y SEGURIDAD</option>
                                                            <option value="006" data-descripcion="CONSTRUCCIÓN" {{ old('rubro_codigo') == '006' ? 'selected' : '' }}>006 - CONSTRUCCIÓN</option>
                                                            <option value="007" data-descripcion="MINERÍA" {{ old('rubro_codigo') == '007' ? 'selected' : '' }}>007 - MINERÍA</option>
                                                            <option value="008" data-descripcion="PETRÓLEO Y GAS" {{ old('rubro_codigo') == '008' ? 'selected' : '' }}>008 - PETRÓLEO Y GAS</option>
                                                            <option value="009" data-descripcion="AGRICULTURA" {{ old('rubro_codigo') == '009' ? 'selected' : '' }}>009 - AGRICULTURA</option>
                                                            <option value="010" data-descripcion="SERVICIOS" {{ old('rubro_codigo') == '010' ? 'selected' : '' }}>010 - SERVICIOS</option>
                                                            <option value="026" data-descripcion="CONSULTORÍA" {{ old('rubro_codigo') == '026' ? 'selected' : '' }}>026 - CONSULTORÍA</option>
                                                        </select>
                                                        <input type="hidden" id="rubro_nombre" name="rubro_nombre" value="{{ old('rubro_nombre') }}">
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- Columna derecha -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="codigo_postal" class="form-label">Código Postal</label>
                                                <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" 
                                                       value="{{ old('codigo_postal') }}">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="pais_codigo" class="form-label">País (máx. 3 caracteres)</label>
                                                            <input type="text" class="form-control" id="pais_codigo" name="pais_codigo" 
                                                                   value="{{ old('pais_codigo', 'ARG') }}" placeholder="ARG" maxlength="3">
                                                    </div>
                                                </div>
                                            </div>


                                            {{-- <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="promotor" class="form-label">Promotor</label>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control" id="promotor_codigo" name="promotor_codigo" 
                                                                   value="{{ old('promotor_codigo') }}">
                                                            <button class="btn btn-outline-secondary" type="button">
                                                                <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div> --}}

                                            <div class="mb-3">
                                                <label for="fecha_alta" class="form-label">Fecha alta</label>
                                                <input type="date" class="form-control" id="fecha_alta" name="fecha_alta" 
                                                       value="{{ old('fecha_alta', date('Y-m-d')) }}">
                                            </div>

                                            <div class="mb-3">
                                                <label for="fecha_modif" class="form-label">Fecha modif.</label>
                                                <input type="date" class="form-control" id="fecha_modif" name="fecha_modif" 
                                                       value="{{ old('fecha_modif') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa Contactos -->
                            <div class="tab-pane fade" id="contactos" role="tabpanel">
                                <div class="p-4">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Contactos</h6>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="agregarFilaContacto()">
                                                <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                                Agregar contacto
                                            </button>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 30%;">Nombre y Apellido</th>
                                                        <th style="width: 20%;">Teléfono</th>
                                                        <th style="width: 25%;">Email</th>
                                                        <th style="width: 15%;">Tipo</th>
                                                        <th style="width: 10%;">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyContactos">
                                                    @php
                                                        $oldContactos = old('contactos', []);
                                                    @endphp
                                                    @if(is_array($oldContactos) && count($oldContactos) > 0)
                                                        @foreach($oldContactos as $index => $contacto)
                                                            <tr>
                                                                <td>
                                                                    <input type="text" class="form-control form-control-sm"
                                                                           name="contactos[{{ $index }}][nombre]"
                                                                           value="{{ $contacto['nombre'] ?? '' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control form-control-sm"
                                                                           name="contactos[{{ $index }}][telefono]"
                                                                           value="{{ $contacto['telefono'] ?? '' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="email" class="form-control form-control-sm"
                                                                           name="contactos[{{ $index }}][email]"
                                                                           value="{{ $contacto['email'] ?? '' }}">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select form-select-sm"
                                                                            name="contactos[{{ $index }}][tipo]">
                                                                        @php
                                                                            $tipo = $contacto['tipo'] ?? '';
                                                                        @endphp
                                                                        <option value="">Seleccionar...</option>
                                                                        <option value="Compras" {{ $tipo === 'Compras' ? 'selected' : '' }}>Compras</option>
                                                                        <option value="Envío de factura" {{ $tipo === 'Envío de factura' ? 'selected' : '' }}>Envío de factura</option>
                                                                        <option value="Cobranza" {{ $tipo === 'Cobranza' ? 'selected' : '' }}>Cobranza</option>
                                                                        <option value="SHyMA" {{ $tipo === 'SHyMA' ? 'selected' : '' }}>SHyMA</option>
                                                                    </select>
                                                                </td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaContacto(this)">
                                                                        <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm"
                                                                       name="contactos[0][nombre]" placeholder="Nombre y Apellido">
                                                            </td>
                                                            <td>
                                                                <input type="text" class="form-control form-control-sm"
                                                                       name="contactos[0][telefono]" placeholder="Teléfono">
                                                            </td>
                                                            <td>
                                                                <input type="email" class="form-control form-control-sm"
                                                                       name="contactos[0][email]" placeholder="correo@ejemplo.com">
                                                            </td>
                                                            <td>
                                                                <select class="form-select form-select-sm"
                                                                        name="contactos[0][tipo]">
                                                                    <option value="">Seleccionar...</option>
                                                                    <option value="Compras">Compras</option>
                                                                    <option value="Envío de factura">Envío de factura</option>
                                                                    <option value="Cobranza">Cobranza</option>
                                                                    <option value="SHyMA">SHyMA</option>
                                                                </select>
                                                            </td>
                                                            <td class="text-center">
                                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaContacto(this)">
                                                                    <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa Facturación -->
                            <div class="tab-pane fade" id="facturacion" role="tabpanel">
                                <div class="p-4">
                                    <div class="row">
                                        <!-- Columna izquierda -->
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="condicion_iva" class="form-label">Condición de I.V.A.</label>
                                                        <select class="form-select" id="condicion_iva_codigo" name="condicion_iva_codigo">
                                                            <option value="">Seleccionar condición de IVA...</option>
                                                            @foreach($condicionesIva as $condicion)
                                                                <option value="{{ $condicion->civa_codigo }}" 
                                                                        data-descripcion="{{ trim($condicion->civa_descripcion) }}"
                                                                        {{ old('condicion_iva_codigo') == $condicion->civa_codigo ? 'selected' : '' }}>
                                                                    {{ trim($condicion->civa_codigo) }} - {{ trim($condicion->civa_descripcion) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <input type="hidden" id="condicion_iva_desc" name="condicion_iva_desc" value="{{ old('condicion_iva_desc') }}">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="condicion_pago" class="form-label">Condición de pago</label>
                                                <select class="form-select" id="condicion_pago" name="condicion_pago">
                                                    <option value="">Seleccionar condición de pago...</option>
                                                    @foreach($condicionesPago as $condicion)
                                                        <option value="{{ $condicion->pag_codigo }}" 
                                                                data-descripcion="{{ trim($condicion->pag_descripcion) }}"
                                                                {{ old('condicion_pago') == $condicion->pag_codigo ? 'selected' : '' }}>
                                                            {{ trim($condicion->pag_codigo) }} - {{ trim($condicion->pag_descripcion) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>


                                            {{-- <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="nro_lp" class="form-label">Nro LP</label>
                                                        <select class="form-select" id="nro_lp" name="nro_lp">
                                                            <option value="">Seleccionar...</option>
                                                            <option value="1" {{ old('nro_lp') == '1' ? 'selected' : '' }}>1</option>
                                                            <option value="2" {{ old('nro_lp') == '2' ? 'selected' : '' }}>2</option>
                                                            <option value="3" {{ old('nro_lp') == '3' ? 'selected' : '' }}>3</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div> --}}

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <div class="mb-3">
                                                        <label for="cuit_doc" class="form-label">C.U.I.T./Doc</label>
                                                        <div class="input-group">
                                                            <select class="form-select" id="cuit_tipo" name="cuit_tipo" style="max-width: 100px;">
                                                                <option value="CUIT" {{ old('cuit_tipo') == 'CUIT' ? 'selected' : '' }}>CUIT</option>
                                                                <option value="CUIL" {{ old('cuit_tipo') == 'CUIL' ? 'selected' : '' }}>CUIL</option>
                                                                <option value="DNI" {{ old('cuit_tipo') == 'DNI' ? 'selected' : '' }}>DNI</option>
                                                            </select>
                                                            <input type="text" class="form-control" id="cuit_numero" name="cuit_numero" 
                                                                   value="{{ old('cuit_numero') }}" placeholder="Número">
                                                            <button class="btn btn-outline-secondary" type="button">Padrón AFIP</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label for="tipo_factura" class="form-label">Tipo de Factura:</label>
                                                <select class="form-select" id="tipo_factura" name="tipo_factura">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="A" {{ old('tipo_factura') == 'A' ? 'selected' : '' }}>A</option>
                                                    <option value="B" {{ old('tipo_factura') == 'B' ? 'selected' : '' }}>B</option>
                                                    <option value="C" {{ old('tipo_factura') == 'C' ? 'selected' : '' }}>C</option>
                                                    <option value="Contra Informe" {{ old('tipo_factura') == 'Contra Informe' ? 'selected' : '' }}>Contra Informe</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Columna derecha -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="observaciones_facturacion" class="form-label">Observaciones</label>
                                                <textarea class="form-control" id="observaciones_facturacion" name="observaciones_facturacion" 
                                                          rows="10">{{ old('observaciones_facturacion') }}</textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sección de Razones Sociales de Facturación -->
                                    <div class="row mt-4">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0">Razones Sociales de Facturación</h6>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="agregarRazonSocialFacturacion()">
                                                    <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                                    Agregar Razón Social
                                                </button>
                                            </div>

                                            <!-- Tabla de razones sociales de facturación -->
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm" id="tablaRazonesSocialesFacturacion">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 20%;">Razón Social</th>
                                                            <th style="width: 12%;">CUIT</th>
                                                            <th style="width: 15%;">Dirección</th>
                                                            <th style="width: 12%;">Cond. IVA</th>
                                                            <th style="width: 12%;">Cond. Pago</th>
                                                            <th style="width: 10%;">Tipo Fact.</th>
                                                            <th style="width: 7%;">Predet.</th>
                                                            <th style="width: 10%;">Acciones</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="tbodyRazonesSocialesFacturacion">
                                                        <!-- Las razones sociales se agregarán dinámicamente aquí -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa Empresas Relacionadas -->
                            <div class="tab-pane fade" id="empresas-relacionadas" role="tabpanel">
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Empresas Relacionadas</h6>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="agregarEmpresaRelacionada()">
                                            <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                            Agregar Empresa
                                        </button>
                                    </div>

                                    <!-- Tabla de empresas relacionadas -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm" id="tablaEmpresasRelacionadas">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 25%;">Razón Social</th>
                                                    <th style="width: 12%;">CUIT</th>
                                                    <th style="width: 20%;">Direcciones</th>
                                                    <th style="width: 12%;">Localidad</th>
                                                    <th style="width: 12%;">Partido</th>
                                                    <th style="width: 12%;">Contacto</th>
                                                    <th style="width: 7%;">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tbodyEmpresasRelacionadas">
                                                <!-- Las empresas se agregarán dinámicamente aquí -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="card-footer bg-light border-top">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                    <x-heroicon-o-x-mark style="width: 16px; height: 16px;" class="me-1" />
                                    Cancelar
                                </button>
                                {{-- <button type="button" class="btn btn-success">
                                    <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                                    Agregar
                                </button>
                                <button type="button" class="btn btn-warning">
                                    <x-heroicon-o-pencil style="width: 16px; height: 16px;" class="me-1" />
                                    Modificar
                                </button> --}}
                                <button type="submit" class="btn btn-primary">
                                    <x-heroicon-o-check style="width: 16px; height: 16px;" class="me-1" />
                                    Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar/editar empresa relacionada (fuera del formulario y card para que siempre esté disponible) -->
    <div class="modal fade" id="modalEmpresaRelacionada" tabindex="-1" aria-labelledby="modalEmpresaRelacionadaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEmpresaRelacionadaLabel">Agregar Empresa Relacionada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formEmpresaRelacionada">
                        <input type="hidden" id="empresa_relacionada_index" value="">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="empresa_rel_razon_social" class="form-label">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="empresa_rel_razon_social" maxlength="255" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="empresa_rel_cuit" class="form-label">CUIT</label>
                                <input type="text" class="form-control" id="empresa_rel_cuit" maxlength="13">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="empresa_rel_contacto" class="form-label">Contacto</label>
                                <input type="text" class="form-control" id="empresa_rel_contacto" maxlength="100">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="empresa_rel_direcciones" class="form-label">Direcciones</label>
                                <textarea class="form-control" id="empresa_rel_direcciones" rows="3"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="empresa_rel_localidad" class="form-label">Localidad</label>
                                <input type="text" class="form-control" id="empresa_rel_localidad" maxlength="50">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="empresa_rel_partido" class="form-label">Partido</label>
                                <input type="text" class="form-control" id="empresa_rel_partido" maxlength="50">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEmpresaRelacionada()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para agregar/editar razón social de facturación -->
    <div class="modal fade" id="modalRazonSocialFacturacion" tabindex="-1" aria-labelledby="modalRazonSocialFacturacionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRazonSocialFacturacionLabel">Agregar Razón Social de Facturación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formRazonSocialFacturacion">
                        <input type="hidden" id="razon_social_facturacion_index" value="">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="rsf_razon_social" class="form-label">Razón Social <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="rsf_razon_social" maxlength="255" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rsf_cuit" class="form-label">CUIT</label>
                                <input type="text" class="form-control" id="rsf_cuit" maxlength="13">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rsf_tipo_factura" class="form-label">Tipo de Factura</label>
                                <select class="form-select" id="rsf_tipo_factura">
                                    <option value="">Seleccionar...</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="Contra Informe">Contra Informe</option>
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label for="rsf_direccion" class="form-label">Dirección</label>
                                <textarea class="form-control" id="rsf_direccion" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rsf_condicion_iva" class="form-label">Condición de I.V.A.</label>
                                <select class="form-select" id="rsf_condicion_iva">
                                    <option value="">Seleccionar condición de IVA...</option>
                                    @foreach($condicionesIva as $condicion)
                                        <option value="{{ $condicion->civa_codigo }}" 
                                                data-descripcion="{{ trim($condicion->civa_descripcion) }}">
                                            {{ trim($condicion->civa_codigo) }} - {{ trim($condicion->civa_descripcion) }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" id="rsf_condicion_iva_desc" value="">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="rsf_condicion_pago" class="form-label">Condición de Pago</label>
                                <select class="form-select" id="rsf_condicion_pago">
                                    <option value="">Seleccionar condición de pago...</option>
                                    @foreach($condicionesPago as $condicion)
                                        <option value="{{ $condicion->pag_codigo }}" 
                                                data-descripcion="{{ trim($condicion->pag_descripcion) }}">
                                            {{ trim($condicion->pag_codigo) }} - {{ trim($condicion->pag_descripcion) }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" id="rsf_condicion_pago_desc" value="">
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rsf_es_predeterminada" value="1">
                                    <label class="form-check-label" for="rsf_es_predeterminada">
                                        Marcar como predeterminada
                                    </label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarRazonSocialFacturacion()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos personalizados para las solapas */
    .nav-tabs-custom {
        border-bottom: 1px solid #dee2e6;
        background-color: #f8f9fa;
        padding: 0;
        margin: 0;
    }

    .nav-tabs-custom .nav-link {
        border: none;
        border-radius: 0;
        padding: 12px 20px;
        color: #495057;
        background-color: transparent;
        font-weight: 500;
        position: relative;
    }

    .nav-tabs-custom .nav-link:hover {
        background-color: #e9ecef;
        border: none;
    }

    .nav-tabs-custom .nav-link.active {
        background-color: #fff;
        color: #0d6efd;
        border: none;
        border-bottom: 2px solid #0d6efd;
    }

    .nav-tabs-custom .nav-link.disabled {
        color: #6c757d;
        background-color: transparent;
        cursor: not-allowed;
    }

    /* Estilo para los campos de formulario */
    .form-label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.25rem;
    }

    .form-control, .form-select {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Tabla de sectores */
    .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    .table th {
        background-color: #f8f9fa;
        border-color: #dee2e6;
        font-weight: 600;
        font-size: 0.8rem;
        padding: 0.5rem;
    }

    .table td {
        padding: 0.25rem 0.5rem;
        vertical-align: middle;
    }

    /* Botones de búsqueda */
    .btn-outline-secondary {
        border-color: #ced4da;
        color: #6c757d;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    /* Header del formulario */
    .bg-light {
        background-color: #f8f9fa !important;
    }

    /* Radio buttons en línea */
    .form-check-inline .form-check-input {
        margin-right: 0.25rem;
    }

    .form-check-inline .form-check-label {
        margin-right: 1rem;
    }

    /* Espaciado de contenido */
    .tab-content {
        min-height: 500px;
    }

    /* Input groups */
    .input-group .form-control {
        border-right: 0;
    }

    .input-group .form-control:not(:last-child) {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .input-group .form-control:not(:first-child) {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        border-left: 0;
    }

    .input-group .btn {
        border-left: 0;
    }
</style>

<script>
    function agregarFilaContacto() {
        const tbody = document.getElementById('tbodyContactos');
        if (!tbody) return;

        const index = tbody.children.length;
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="contactos[${index}][nombre]" placeholder="Nombre y Apellido">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm"
                       name="contactos[${index}][telefono]" placeholder="Teléfono">
            </td>
            <td>
                <input type="email" class="form-control form-control-sm"
                       name="contactos[${index}][email]" placeholder="correo@ejemplo.com">
            </td>
            <td>
                <select class="form-select form-select-sm"
                        name="contactos[${index}][tipo]">
                    <option value="">Seleccionar...</option>
                    <option value="Compras">Compras</option>
                    <option value="Envío de factura">Envío de factura</option>
                    <option value="Cobranza">Cobranza</option>
                    <option value="SHyMA">SHyMA</option>
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarFilaContacto(this)">
                    <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                </button>
            </td>
        `;

        tbody.appendChild(row);
    }

    function eliminarFilaContacto(button) {
        const row = button.closest('tr');
        if (row) {
            row.remove();
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips si están disponibles
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (tooltipTriggerList.length > 0) {
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Manejar cambios en las solapas
        const tabs = document.querySelectorAll('#clienteTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                // Aquí se puede agregar lógica adicional cuando se cambie de solapa
                console.log('Solapa activa:', e.target.textContent.trim());
            });
        });

        // Manejar cambios en los selectores
        const condicionIvaSelect = document.getElementById('condicion_iva_codigo');
        const condicionIvaDesc = document.getElementById('condicion_iva_desc');
        
        if (condicionIvaSelect && condicionIvaDesc) {
            condicionIvaSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    condicionIvaDesc.value = selectedOption.getAttribute('data-descripcion') || '';
                } else {
                    condicionIvaDesc.value = '';
                }
            });
        }

        // Manejar cambios en el selector de rubros
        const rubroSelect = document.getElementById('rubro_codigo');
        const rubroNombre = document.getElementById('rubro_nombre');
        
        if (rubroSelect && rubroNombre) {
            rubroSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    rubroNombre.value = selectedOption.getAttribute('data-descripcion') || '';
                } else {
                    rubroNombre.value = '';
                }
            });
        }

    // Validación básica del formulario con SweetAlert
    const form = document.getElementById('clienteForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('=== VALIDANDO FORMULARIO DE CLIENTE ===');
            console.log('Datos del formulario:');
            
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            const razonSocial = document.getElementById('razon_social');
            if (!razonSocial.value.trim()) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Validación',
                    text: 'La Razón Social es obligatoria',
                    confirmButtonColor: '#dc3545'
                });
                razonSocial.focus();
                return;
            }
            
            console.log('Formulario válido, enviando...');
        });
    }

        // Mejorar la experiencia con los selectores
        const selectores = document.querySelectorAll('.form-select');
        selectores.forEach(select => {
            select.addEventListener('focus', function() {
                this.style.borderColor = '#86b7fe';
            });
            
            select.addEventListener('blur', function() {
                this.style.borderColor = '#ced4da';
            });
        });

        // Manejar checkbox de consultor
        const esConsultorCheck = document.getElementById('es_consultor');
        const empresasRelacionadasTabItem = document.getElementById('empresas-relacionadas-tab-item');
        
        function toggleEmpresasRelacionadasTab() {
            if (esConsultorCheck && empresasRelacionadasTabItem) {
                if (esConsultorCheck.checked) {
                    empresasRelacionadasTabItem.style.display = '';
                } else {
                    empresasRelacionadasTabItem.style.display = 'none';
                    // Si el tab está activo, cambiar a General
                    const empresasTab = document.getElementById('empresas-relacionadas-tab');
                    if (empresasTab && empresasTab.classList.contains('active')) {
                        const generalTab = document.getElementById('general-tab');
                        if (generalTab) {
                            generalTab.click();
                        }
                    }
                }
            }
        }
        
        if (esConsultorCheck) {
            esConsultorCheck.addEventListener('change', toggleEmpresasRelacionadasTab);
            // Ejecutar al cargar para establecer el estado inicial
            toggleEmpresasRelacionadasTab();
        }
    });

    // Gestión de empresas relacionadas
    let empresasRelacionadas = [];
    let empresaEditandoIndex = -1;

    // Gestión de razones sociales de facturación
    let razonesSocialesFacturacion = [];
    let razonSocialEditandoIndex = -1;

    // Hacer las funciones globales para que puedan ser llamadas desde onclick
    window.agregarEmpresaRelacionada = function() {
        empresaEditandoIndex = -1;
        
        // Esperar un momento para asegurar que el DOM esté listo
        setTimeout(function() {
            const modalLabel = document.getElementById('modalEmpresaRelacionadaLabel');
            const form = document.getElementById('formEmpresaRelacionada');
            const indexInput = document.getElementById('empresa_relacionada_index');
            const modalElement = document.getElementById('modalEmpresaRelacionada');
            
            if (!modalLabel || !form || !indexInput || !modalElement) {
                console.error('Elementos del modal no encontrados:', {
                    modalLabel: !!modalLabel,
                    form: !!form,
                    indexInput: !!indexInput,
                    modalElement: !!modalElement
                });
                console.log('Buscando modal en el DOM...');
                console.log('Modal encontrado:', document.querySelector('#modalEmpresaRelacionada'));
                return;
            }
            
            modalLabel.textContent = 'Agregar Empresa Relacionada';
            form.reset();
            indexInput.value = '';
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }, 100);
    }

    window.editarEmpresaRelacionada = function(index) {
        empresaEditandoIndex = index;
        const empresa = empresasRelacionadas[index];
        
        const modalLabel = document.getElementById('modalEmpresaRelacionadaLabel');
        const razonSocial = document.getElementById('empresa_rel_razon_social');
        const cuit = document.getElementById('empresa_rel_cuit');
        const direcciones = document.getElementById('empresa_rel_direcciones');
        const localidad = document.getElementById('empresa_rel_localidad');
        const partido = document.getElementById('empresa_rel_partido');
        const contacto = document.getElementById('empresa_rel_contacto');
        const indexInput = document.getElementById('empresa_relacionada_index');
        const modalElement = document.getElementById('modalEmpresaRelacionada');
        
        if (!modalLabel || !razonSocial || !cuit || !direcciones || !localidad || !partido || !contacto || !indexInput || !modalElement) {
            console.error('Elementos del modal no encontrados');
            return;
        }
        
        modalLabel.textContent = 'Editar Empresa Relacionada';
        razonSocial.value = empresa.razon_social || '';
        cuit.value = empresa.cuit || '';
        direcciones.value = empresa.direcciones || '';
        localidad.value = empresa.localidad || '';
        partido.value = empresa.partido || '';
        contacto.value = empresa.contacto || '';
        indexInput.value = index;
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }

    window.eliminarEmpresaRelacionada = function(index) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                empresasRelacionadas.splice(index, 1);
                renderizarTablaEmpresas();
            }
        });
    }

    window.guardarEmpresaRelacionada = function() {
        const form = document.getElementById('formEmpresaRelacionada');
        const razonSocial = document.getElementById('empresa_rel_razon_social');
        const cuit = document.getElementById('empresa_rel_cuit');
        const direcciones = document.getElementById('empresa_rel_direcciones');
        const localidad = document.getElementById('empresa_rel_localidad');
        const partido = document.getElementById('empresa_rel_partido');
        const contacto = document.getElementById('empresa_rel_contacto');
        const modalElement = document.getElementById('modalEmpresaRelacionada');
        
        if (!form || !razonSocial || !cuit || !direcciones || !localidad || !partido || !contacto || !modalElement) {
            console.error('Elementos del formulario no encontrados');
            return;
        }
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const empresa = {
            razon_social: razonSocial.value.trim(),
            cuit: cuit.value.trim(),
            direcciones: direcciones.value.trim(),
            localidad: localidad.value.trim(),
            partido: partido.value.trim(),
            contacto: contacto.value.trim()
        };

        if (empresaEditandoIndex >= 0) {
            empresasRelacionadas[empresaEditandoIndex] = empresa;
        } else {
            empresasRelacionadas.push(empresa);
        }

        renderizarTablaEmpresas();
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }

    window.renderizarTablaEmpresas = function() {
        const tbody = document.getElementById('tbodyEmpresasRelacionadas');
        tbody.innerHTML = '';

        if (empresasRelacionadas.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="7" class="text-center text-muted">No hay empresas relacionadas agregadas</td>';
            tbody.appendChild(row);
        } else {
            empresasRelacionadas.forEach((empresa, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(empresa.razon_social || '-')}</td>
                    <td>${escapeHtml(empresa.cuit || '-')}</td>
                    <td><small>${escapeHtml(empresa.direcciones || '-')}</small></td>
                    <td>${escapeHtml(empresa.localidad || '-')}</td>
                    <td>${escapeHtml(empresa.partido || '-')}</td>
                    <td>${escapeHtml(empresa.contacto || '-')}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarEmpresaRelacionada(${index})" title="Editar">
                            <x-heroicon-o-pencil style="width: 14px; height: 14px;" />
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarEmpresaRelacionada(${index})" title="Eliminar">
                            <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Actualizar campos hidden para el envío del formulario
        actualizarCamposHiddenEmpresas();
    }

    window.actualizarCamposHiddenEmpresas = function() {
        // Eliminar campos hidden anteriores
        document.querySelectorAll('input[name^="empresas_relacionadas"]').forEach(input => input.remove());

        // Crear campos hidden para cada empresa
        empresasRelacionadas.forEach((empresa, index) => {
            Object.keys(empresa).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `empresas_relacionadas[${index}][${key}]`;
                input.value = empresa[key] || '';
                document.getElementById('clienteForm').appendChild(input);
            });
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Actualizar campos hidden antes de enviar el formulario
    document.getElementById('clienteForm').addEventListener('submit', function() {
        actualizarCamposHiddenEmpresas();
        actualizarCamposHiddenRazonesSociales();
    });

    // ========== FUNCIONES PARA RAZONES SOCIALES DE FACTURACIÓN ==========
    window.agregarRazonSocialFacturacion = function() {
        razonSocialEditandoIndex = -1;
        
        setTimeout(function() {
            const modalLabel = document.getElementById('modalRazonSocialFacturacionLabel');
            const form = document.getElementById('formRazonSocialFacturacion');
            const indexInput = document.getElementById('razon_social_facturacion_index');
            const modalElement = document.getElementById('modalRazonSocialFacturacion');
            
            if (!modalLabel || !form || !indexInput || !modalElement) {
                console.error('Elementos del modal de razón social no encontrados');
                return;
            }
            
            modalLabel.textContent = 'Agregar Razón Social de Facturación';
            form.reset();
            indexInput.value = '';
            document.getElementById('rsf_condicion_iva_desc').value = '';
            document.getElementById('rsf_condicion_pago_desc').value = '';
            
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }, 100);
    }

    window.editarRazonSocialFacturacion = function(index) {
        razonSocialEditandoIndex = index;
        const razonSocial = razonesSocialesFacturacion[index];
        
        const modalLabel = document.getElementById('modalRazonSocialFacturacionLabel');
        const razonSocialInput = document.getElementById('rsf_razon_social');
        const cuit = document.getElementById('rsf_cuit');
        const direccion = document.getElementById('rsf_direccion');
        const condicionIva = document.getElementById('rsf_condicion_iva');
        const condicionIvaDesc = document.getElementById('rsf_condicion_iva_desc');
        const condicionPago = document.getElementById('rsf_condicion_pago');
        const condicionPagoDesc = document.getElementById('rsf_condicion_pago_desc');
        const tipoFactura = document.getElementById('rsf_tipo_factura');
        const esPredeterminada = document.getElementById('rsf_es_predeterminada');
        const indexInput = document.getElementById('razon_social_facturacion_index');
        const modalElement = document.getElementById('modalRazonSocialFacturacion');
        
        if (!modalLabel || !razonSocialInput || !cuit || !direccion || !condicionIva || !condicionPago || !tipoFactura || !esPredeterminada || !indexInput || !modalElement) {
            console.error('Elementos del modal de razón social no encontrados');
            return;
        }
        
        modalLabel.textContent = 'Editar Razón Social de Facturación';
        razonSocialInput.value = razonSocial.razon_social || '';
        cuit.value = razonSocial.cuit || '';
        direccion.value = razonSocial.direccion || '';
        condicionIva.value = razonSocial.condicion_iva || '';
        condicionIvaDesc.value = razonSocial.condicion_iva_desc || '';
        condicionPago.value = razonSocial.condicion_pago || '';
        condicionPagoDesc.value = razonSocial.condicion_pago_desc || '';
        tipoFactura.value = razonSocial.tipo_factura || '';
        // Convertir explícitamente a booleano para el checkbox
        esPredeterminada.checked = razonSocial.es_predeterminada === true || razonSocial.es_predeterminada === 1 || razonSocial.es_predeterminada === '1';
        indexInput.value = index;
        
        // Actualizar descripciones de los selectores
        if (condicionIva.value) {
            const ivaOption = condicionIva.options[condicionIva.selectedIndex];
            if (ivaOption) {
                condicionIvaDesc.value = ivaOption.getAttribute('data-descripcion') || '';
            }
        }
        if (condicionPago.value) {
            const pagoOption = condicionPago.options[condicionPago.selectedIndex];
            if (pagoOption) {
                condicionPagoDesc.value = pagoOption.getAttribute('data-descripcion') || '';
            }
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }

    window.eliminarRazonSocialFacturacion = function(index) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                razonesSocialesFacturacion.splice(index, 1);
                renderizarTablaRazonesSocialesFacturacion();
            }
        });
    }

    window.guardarRazonSocialFacturacion = function() {
        const form = document.getElementById('formRazonSocialFacturacion');
        const razonSocialInput = document.getElementById('rsf_razon_social');
        const cuit = document.getElementById('rsf_cuit');
        const direccion = document.getElementById('rsf_direccion');
        const condicionIva = document.getElementById('rsf_condicion_iva');
        const condicionIvaDesc = document.getElementById('rsf_condicion_iva_desc');
        const condicionPago = document.getElementById('rsf_condicion_pago');
        const condicionPagoDesc = document.getElementById('rsf_condicion_pago_desc');
        const tipoFactura = document.getElementById('rsf_tipo_factura');
        const esPredeterminada = document.getElementById('rsf_es_predeterminada');
        const modalElement = document.getElementById('modalRazonSocialFacturacion');
        
        if (!form || !razonSocialInput || !cuit || !direccion || !condicionIva || !condicionPago || !tipoFactura || !esPredeterminada || !modalElement) {
            console.error('Elementos del formulario de razón social no encontrados');
            return;
        }
        
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Si se marca como predeterminada, desmarcar las demás
        if (esPredeterminada.checked) {
            razonesSocialesFacturacion.forEach(rs => {
                rs.es_predeterminada = false;
            });
        }

        const razonSocial = {
            razon_social: razonSocialInput.value.trim(),
            cuit: cuit.value.trim(),
            direccion: direccion.value.trim(),
            condicion_iva: condicionIva.value.trim(),
            condicion_iva_desc: condicionIvaDesc.value.trim(),
            condicion_pago: condicionPago.value.trim(),
            condicion_pago_desc: condicionPagoDesc.value.trim(),
            tipo_factura: tipoFactura.value.trim(),
            es_predeterminada: esPredeterminada.checked
        };

        if (razonSocialEditandoIndex >= 0) {
            razonesSocialesFacturacion[razonSocialEditandoIndex] = razonSocial;
        } else {
            razonesSocialesFacturacion.push(razonSocial);
        }

        renderizarTablaRazonesSocialesFacturacion();
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }

    window.renderizarTablaRazonesSocialesFacturacion = function() {
        const tbody = document.getElementById('tbodyRazonesSocialesFacturacion');
        tbody.innerHTML = '';

        if (razonesSocialesFacturacion.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="8" class="text-center text-muted">No hay razones sociales de facturación agregadas</td>';
            tbody.appendChild(row);
        } else {
            razonesSocialesFacturacion.forEach((razonSocial, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(razonSocial.razon_social || '-')}</td>
                    <td>${escapeHtml(razonSocial.cuit || '-')}</td>
                    <td><small>${escapeHtml(razonSocial.direccion || '-')}</small></td>
                    <td><small>${escapeHtml(razonSocial.condicion_iva || '-')}${razonSocial.condicion_iva_desc ? '<br>' + escapeHtml(razonSocial.condicion_iva_desc) : ''}</small></td>
                    <td><small>${escapeHtml(razonSocial.condicion_pago || '-')}${razonSocial.condicion_pago_desc ? '<br>' + escapeHtml(razonSocial.condicion_pago_desc) : ''}</small></td>
                    <td>${escapeHtml(razonSocial.tipo_factura || '-')}</td>
                    <td class="text-center">${razonSocial.es_predeterminada ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarRazonSocialFacturacion(${index})" title="Editar">
                            <x-heroicon-o-pencil style="width: 14px; height: 14px;" />
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarRazonSocialFacturacion(${index})" title="Eliminar">
                            <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Actualizar campos hidden para el envío del formulario
        actualizarCamposHiddenRazonesSociales();
    }

    window.actualizarCamposHiddenRazonesSociales = function() {
        // Eliminar campos hidden anteriores
        document.querySelectorAll('input[name^="razones_sociales"]').forEach(input => input.remove());

        // Crear campos hidden para cada razón social
        razonesSocialesFacturacion.forEach((razonSocial, index) => {
            Object.keys(razonSocial).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `razones_sociales[${index}][${key}]`;
                // Convertir booleanos a '1' o '0' para es_predeterminada
                if (key === 'es_predeterminada') {
                    input.value = razonSocial[key] === true || razonSocial[key] === 1 || razonSocial[key] === '1' ? '1' : '0';
                } else {
                    input.value = razonSocial[key] || '';
                }
                document.getElementById('clienteForm').appendChild(input);
            });
        });
    }

    // Manejar cambios en los selectores del modal de razón social
    document.addEventListener('DOMContentLoaded', function() {
        const rsfCondicionIva = document.getElementById('rsf_condicion_iva');
        const rsfCondicionIvaDesc = document.getElementById('rsf_condicion_iva_desc');
        
        if (rsfCondicionIva && rsfCondicionIvaDesc) {
            rsfCondicionIva.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    rsfCondicionIvaDesc.value = selectedOption.getAttribute('data-descripcion') || '';
                } else {
                    rsfCondicionIvaDesc.value = '';
                }
            });
        }

        const rsfCondicionPago = document.getElementById('rsf_condicion_pago');
        const rsfCondicionPagoDesc = document.getElementById('rsf_condicion_pago_desc');
        
        if (rsfCondicionPago && rsfCondicionPagoDesc) {
            rsfCondicionPago.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    rsfCondicionPagoDesc.value = selectedOption.getAttribute('data-descripcion') || '';
                } else {
                    rsfCondicionPagoDesc.value = '';
                }
            });
        }

        // Inicializar tabla de razones sociales
        renderizarTablaRazonesSocialesFacturacion();
    });
</script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
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

@if($errors->any())
    Swal.fire({
        icon: 'error',
        title: 'Error de Validación',
        html: '<ul style="text-align: left;"><li>' + {{ Js::from($errors->all()) }}.join('</li><li>') + '</li></ul>',
        confirmButtonColor: '#dc3545'
    });
@endif

// Función de debugging (disponible en consola del navegador)
window.debugFormulario = function() {
    console.log('=== DEBUG FORMULARIO CLIENTE ===');
    const form = document.getElementById('clienteForm');
    
    if (!form) {
        console.error('Formulario no encontrado');
        return;
    }
    
    const formData = new FormData(form);
    console.log('Todos los campos del formulario:');
    
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    // Validar campos críticos
    const razonSocial = document.getElementById('razon_social');
    const codigo = document.getElementById('codigo');
    const activo = document.querySelector('input[name="activo"]:checked');
    
    console.log('\nCampos críticos:');
    console.log('  - Razón Social:', razonSocial ? razonSocial.value : 'NO ENCONTRADO');
    console.log('  - Código:', codigo ? codigo.value : 'NO ENCONTRADO');
    console.log('  - Estado:', activo ? activo.value : 'NO SELECCIONADO');
    
    console.log('\nFormulario válido:', razonSocial && razonSocial.value.trim() ? 'SÍ' : 'NO');
};
</script>
@endsection