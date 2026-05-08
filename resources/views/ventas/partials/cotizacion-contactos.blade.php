{{-- Contactos de la cotización: 1 visible por defecto, hasta 4 con botón "+ Agregar otro". --}}
@php
    $cotizacion = $cotizacion ?? null;
    $contacto1 = [
        'nombre' => old('coti_contacto', $cotizacion ? $cotizacion->coti_contacto : ''),
        'correo' => old('coti_mail1', $cotizacion ? $cotizacion->coti_mail1 : ''),
        'telefono' => old('coti_telefono', $cotizacion ? $cotizacion->coti_telefono : ''),
        'tipo' => old('coti_contacto_tipo1', $cotizacion ? $cotizacion->coti_contacto_tipo1 : ''),
    ];
    $contacto2 = [
        'nombre' => old('coti_contacto2', $cotizacion ? $cotizacion->coti_contacto2 : ''),
        'correo' => old('coti_mail2', $cotizacion ? $cotizacion->coti_mail2 : ''),
        'telefono' => old('coti_telefono2', $cotizacion ? $cotizacion->coti_telefono2 : ''),
        'tipo' => old('coti_contacto_tipo2', $cotizacion ? $cotizacion->coti_contacto_tipo2 : ''),
    ];
    $contacto3 = [
        'nombre' => old('coti_contacto3', $cotizacion ? $cotizacion->coti_contacto3 : ''),
        'correo' => old('coti_mail3', $cotizacion ? $cotizacion->coti_mail3 : ''),
        'telefono' => old('coti_telefono3', $cotizacion ? $cotizacion->coti_telefono3 : ''),
        'tipo' => old('coti_contacto_tipo3', $cotizacion ? $cotizacion->coti_contacto_tipo3 : ''),
    ];
    $contacto4 = [
        'nombre' => old('coti_contacto4', $cotizacion ? $cotizacion->coti_contacto4 : ''),
        'correo' => old('coti_mail4', $cotizacion ? $cotizacion->coti_mail4 : ''),
        'telefono' => old('coti_telefono4', $cotizacion ? $cotizacion->coti_telefono4 : ''),
        'tipo' => old('coti_contacto_tipo4', $cotizacion ? $cotizacion->coti_contacto_tipo4 : ''),
    ];
    $tipos = ['Pedido', 'Coordinacion', 'Envio de factura', 'Cobranza'];
@endphp

<div class="cotizacion-contactos-section mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h6 class="mb-1 text-dark">Contactos</h6>
            <p class="text-muted small mb-0">Elegí un contacto registrado del cliente o cargá los datos manualmente. Podés guardar un contacto nuevo en el cliente para usarlo después.</p>
        </div>
    </div>

    <div class="contactos-list">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-light text-dark border">Principal</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnAgregarContacto">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                Agregar otro contacto
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%;">Nombre y Apellido</th>
                        <th style="width: 20%;">Teléfono</th>
                        <th style="width: 25%;">Email</th>
                        <th style="width: 15%;">Tipo</th>
                        <th style="width: 10%;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Fila contacto 1 (siempre visible) --}}
                    <tr class="contacto-card contacto-card-principal" data-contacto-index="1" id="contacto-block-1">
                        <td>
                            <input type="text" class="form-control form-control-sm" id="contacto" name="coti_contacto" value="{{ $contacto1['nombre'] }}" placeholder="Nombre del contacto">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" id="telefono" name="coti_telefono" value="{{ $contacto1['telefono'] }}" placeholder="+54 9 11 1234-5678">
                        </td>
                        <td>
                            <input type="email" class="form-control form-control-sm" id="correo" name="coti_mail1" value="{{ $contacto1['correo'] }}" placeholder="correo@ejemplo.com">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" id="coti_contacto_tipo1" name="coti_contacto_tipo1">
                                <option value="">Seleccionar...</option>
                                @foreach($tipos as $t)
                                    <option value="{{ $t }}" {{ $contacto1['tipo'] === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm contacto-select" id="contacto_selector" data-contacto-index="1">
                                <option value="">Seleccionar contacto registrado...</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-outline-success btn-sm btn-guardar-contacto" data-contacto-index="1" title="Guardar este contacto en el cliente">
                                <x-heroicon-o-plus-circle style="width: 14px; height: 14px;" />
                            </button>
                        </td>
                    </tr>

                    {{-- Fila contacto 2 --}}
                    <tr class="contacto-card contacto-card-extra d-none" data-contacto-index="2" id="contacto-block-2">
                        <td>
                            <input type="text" class="form-control form-control-sm" id="contacto2" name="coti_contacto2" value="{{ $contacto2['nombre'] }}" placeholder="Nombre del contacto">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" id="telefono2" name="coti_telefono2" value="{{ $contacto2['telefono'] }}" placeholder="+54 9 11 1234-5678">
                        </td>
                        <td>
                            <input type="email" class="form-control form-control-sm" id="correo2" name="coti_mail2" value="{{ $contacto2['correo'] }}" placeholder="correo@ejemplo.com">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" id="coti_contacto_tipo2" name="coti_contacto_tipo2">
                                <option value="">Seleccionar...</option>
                                @foreach($tipos as $t)
                                    <option value="{{ $t }}" {{ $contacto2['tipo'] === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm contacto-select" id="contacto2_selector" data-contacto-index="2">
                                <option value="">Seleccionar contacto registrado...</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button type="button" class="btn btn-outline-success btn-sm btn-guardar-contacto" data-contacto-index="2" title="Guardar en cliente">
                                    <x-heroicon-o-plus-circle style="width: 14px; height: 14px;" />
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-contacto" data-contacto-index="2" title="Quitar este contacto">
                                    <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- Fila contacto 3 --}}
                    <tr class="contacto-card contacto-card-extra d-none" data-contacto-index="3" id="contacto-block-3">
                        <td>
                            <input type="text" class="form-control form-control-sm" id="contacto3" name="coti_contacto3" value="{{ $contacto3['nombre'] }}" placeholder="Nombre del contacto">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" id="telefono3" name="coti_telefono3" value="{{ $contacto3['telefono'] }}" placeholder="+54 9 11 1234-5678">
                        </td>
                        <td>
                            <input type="email" class="form-control form-control-sm" id="correo3" name="coti_mail3" value="{{ $contacto3['correo'] }}" placeholder="correo@ejemplo.com">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" id="coti_contacto_tipo3" name="coti_contacto_tipo3">
                                <option value="">Seleccionar...</option>
                                @foreach($tipos as $t)
                                    <option value="{{ $t }}" {{ $contacto3['tipo'] === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm contacto-select" id="contacto3_selector" data-contacto-index="3">
                                <option value="">Seleccionar contacto registrado...</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button type="button" class="btn btn-outline-success btn-sm btn-guardar-contacto" data-contacto-index="3" title="Guardar en cliente">
                                    <x-heroicon-o-plus-circle style="width: 14px; height: 14px;" />
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-contacto" data-contacto-index="3" title="Quitar este contacto">
                                    <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- Fila contacto 4 --}}
                    <tr class="contacto-card contacto-card-extra d-none" data-contacto-index="4" id="contacto-block-4">
                        <td>
                            <input type="text" class="form-control form-control-sm" id="contacto4" name="coti_contacto4" value="{{ $contacto4['nombre'] }}" placeholder="Nombre del contacto">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" id="telefono4" name="coti_telefono4" value="{{ $contacto4['telefono'] }}" placeholder="+54 9 11 1234-5678">
                        </td>
                        <td>
                            <input type="email" class="form-control form-control-sm" id="correo4" name="coti_mail4" value="{{ $contacto4['correo'] }}" placeholder="correo@ejemplo.com">
                        </td>
                        <td>
                            <select class="form-select form-select-sm" id="coti_contacto_tipo4" name="coti_contacto_tipo4">
                                <option value="">Seleccionar...</option>
                                @foreach($tipos as $t)
                                    <option value="{{ $t }}" {{ $contacto4['tipo'] === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm contacto-select" id="contacto4_selector" data-contacto-index="4">
                                <option value="">Seleccionar contacto registrado...</option>
                            </select>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button type="button" class="btn btn-outline-success btn-sm btn-guardar-contacto" data-contacto-index="4" title="Guardar en cliente">
                                    <x-heroicon-o-plus-circle style="width: 14px; height: 14px;" />
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-contacto" data-contacto-index="4" title="Quitar este contacto">
                                    <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
