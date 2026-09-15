@extends('layouts.app')

@section('title', 'Nuevo usuario')

@php
    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
    $opcionesRol = [
        'laboratorio' => 'Analista',
        'muestreador' => 'Muestreador',
        'coordinador_lab' => 'Coordinador Laboratorio',
        'coordinador_muestreo' => 'Coordinador Muestreo',
        'facturador' => 'Facturador',
        'ventas' => 'Vendedor',
        'firmador' => 'Firmador',
        'coordinador_consul' => 'Coordinador Consultoría',
        'coordinador_mediciones' => 'Coordinador Mediciones',
        'asp' => 'ASP',
        'clarke_fire' => 'Clarke Fire',
        'cliente' => 'Usuario Cliente',
    ];
@endphp

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Nuevo usuario</h1>
    <div class="row">
        <div class="col-lg-8">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex align-items-center gap-2">
                    <strong>Alta de usuario</strong>
                    @if($esAdmin)
                        <span class="badge bg-warning text-dark">Administración</span>
                    @endif
                </div>
                <div class="card-body">
                    <form id="form-crear-usuario" action="{{ route('users.storeUser') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="usu_codigo" class="form-label">Código (usuario de ingreso)</label>
                            <input type="text" name="usu_codigo" id="usu_codigo" class="form-control" value="{{ old('usu_codigo') }}" required autocomplete="username" maxlength="50">
                        </div>

                        <div class="mb-3">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" value="{{ old('usu_descripcion') }}" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="dni" class="form-label">DNI / documento</label>
                                <input type="text" name="dni" id="dni" class="form-control" value="{{ old('dni') }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Correo</label>
                                <input type="text" name="email" id="email" class="form-control" value="{{ old('email') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="departamento" class="form-label">Departamento / área</label>
                            <input type="text" name="departamento" id="departamento" class="form-control" value="{{ old('departamento') }}">
                        </div>

                        <div class="mb-3">
                            <label for="sector_trabajo" class="form-label">Sector de trabajo (texto libre)</label>
                            <input type="text" name="sector_trabajo" id="sector_trabajo" class="form-control" value="{{ old('sector_trabajo') }}" placeholder="p. ej. química, microbiología">
                        </div>

                        @php
                            $estadoNuevo = (string) old('usu_estado', '1');
                        @endphp
                        <div class="mb-3">
                            <label for="usu_estado" class="form-label">Estado</label>
                            <select name="usu_estado" id="usu_estado" class="form-select" required>
                                <option value="1" @selected($estadoNuevo === '1')>Activo</option>
                                <option value="0" @selected($estadoNuevo === '0')>Inactivo</option>
                            </select>
                        </div>

                        @if($esAdmin)
                            <div class="mb-3">
                                <label for="usu_nivel" class="form-label">Nivel de acceso (≥900 = administrador)</label>
                                <input type="number" name="usu_nivel" id="usu_nivel" class="form-control" min="0" max="9999" value="{{ old('usu_nivel', 500) }}">
                                <div class="form-text">Por defecto 500. Dejar vacío al guardar mantiene 500.</div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol principal</label>
                            <select name="rol" id="rol" class="form-select" required>
                                <option value="" hidden disabled @selected((string) old('rol', '') === '')>Selecciona un rol</option>
                                @foreach($opcionesRol as $val => $etq)
                                    <option value="{{ $val }}" @selected((string) old('rol') === (string) $val)>{{ $etq }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($esAdmin)
                            <div class="mb-3">
                                <label for="roles_adicionales" class="form-label">Roles adicionales (varios; Ctrl+clic)</label>
                                <select name="roles_adicionales[]" id="roles_adicionales" class="form-select" multiple size="8">
                                    @foreach($opcionesRol as $val => $etq)
                                        <option value="{{ $val }}" @selected(in_array($val, old('roles_adicionales', []), true))>{{ $etq }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Se ignoran si coinciden con el rol principal.</div>
                            </div>
                        @endif

                        @php
                            $mostrarAdminLab = (string) old('rol') === 'coordinador_lab'
                                || in_array('coordinador_lab', (array) old('roles_adicionales', []), true);
                        @endphp
                        <div class="mb-3" id="wrapper_admin_lab" style="display: {{ $mostrarAdminLab ? 'block' : 'none' }};">
                            <div class="form-check">
                                <input type="checkbox"
                                       name="admin_lab"
                                       id="admin_lab"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('admin_lab', false))>
                                <label for="admin_lab" class="form-check-label">Administrador de laboratorio</label>
                                <div class="form-text">Puede ver quién aprobó los informes en órdenes de trabajo.</div>
                            </div>
                            <div class="form-check mt-2">
                                <input type="checkbox"
                                       name="puede_gestionar_ordenes"
                                       id="puede_gestionar_ordenes"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('puede_gestionar_ordenes', false))>
                                <label for="puede_gestionar_ordenes" class="form-check-label">Puede gestionar órdenes</label>
                                <div class="form-text">Permite asignar y modificar órdenes de trabajo (analistas, sectores, fechas).</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox"
                                       name="puede_cargar_items"
                                       id="puede_cargar_items"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('puede_cargar_items', false))>
                                <label for="puede_cargar_items" class="form-check-label">Puede cargar determinaciones (items)</label>
                                <div class="form-text">Habilita el acceso al módulo de determinaciones: alta, edición, importación y exportación.</div>
                            </div>
                        </div>

                        <div class="mb-3" id="wrapper_sector_unico">
                            <label for="sector_codigo" class="form-label">Laboratorio (sector)</label>
                            <select name="sector_codigo" id="sector_codigo" class="form-select">
                                <option value="">Sin sector</option>
                                @foreach($sectores as $sector)
                                    <option value="{{ $sector->usu_codigo }}" @selected((string) old('sector_codigo', '') === (string) $sector->usu_codigo)>
                                        {{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="wrapper_sectores_multiples">
                            <label for="sectores_codigos" class="form-label">Laboratorios / Sectores (múltiples; Ctrl+clic)</label>
                            <select name="sectores_codigos[]" id="sectores_codigos" class="form-select" multiple size="6">
                                @foreach($sectores as $sector)
                                    <option value="{{ $sector->usu_codigo }}"
                                        {{ in_array($sector->usu_codigo, (array) old('sectores_codigos', []), true) ? 'selected' : '' }}>
                                        {{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Mantené presionado Ctrl (o Cmd en Mac) para seleccionar más de un sector.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password" minlength="4">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password" minlength="4">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ url('/users') }}" class="btn btn-secondary">← Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('form-crear-usuario');
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');
        const sectoresMultiSelect = document.getElementById('sectores_codigos');
        const rolesMultiSelect = document.getElementById('roles_adicionales');
        const wrapperSectorUnico = document.getElementById('wrapper_sector_unico');
        const wrapperSectoresMultiples = document.getElementById('wrapper_sectores_multiples');
        const wrapperAdminLab = document.getElementById('wrapper_admin_lab');
        const adminLabCheckbox = document.getElementById('admin_lab');
        const puedeGestionarOrdenesCheckbox = document.getElementById('puede_gestionar_ordenes');

        if (!form || !rolSelect) return;

        function tieneCoordinadorLab() {
            if (rolSelect.value === 'coordinador_lab') {
                return true;
            }
            if (rolesMultiSelect) {
                return Array.from(rolesMultiSelect.selectedOptions).some(option => option.value === 'coordinador_lab');
            }
            return false;
        }

        function toggleAdminLab() {
            if (!wrapperAdminLab) return;
            if (tieneCoordinadorLab()) {
                wrapperAdminLab.style.display = 'block';
            } else {
                wrapperAdminLab.style.display = 'none';
                if (adminLabCheckbox) adminLabCheckbox.checked = false;
                if (puedeGestionarOrdenesCheckbox) puedeGestionarOrdenesCheckbox.checked = false;
            }
        }

        function rolPermiteSector() {
            const r = rolSelect.value;
            return r === 'laboratorio' || r === 'coordinador_lab';
        }

        function toggleSectores() {
            const permite = rolPermiteSector();
            if (permite) {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'none';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'block';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
            } else {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'block';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'none';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = true;
            }
        }

        rolSelect.addEventListener('change', () => {
            toggleSectores();
            toggleAdminLab();
        });
        if (rolesMultiSelect) {
            rolesMultiSelect.addEventListener('change', toggleAdminLab);
        }
        toggleSectores();
        toggleAdminLab();

        form.addEventListener('submit', function () {
            if (sectorSelect) sectorSelect.disabled = false;
            if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
        });
    });
</script>
@endsection
