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

                        <div class="mb-3">
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
        if (!form || !rolSelect || !sectorSelect) return;

        function rolPermiteSector() {
            const r = rolSelect.value;
            return r === 'laboratorio' || r === 'coordinador_lab';
        }

        function toggleSector() {
            sectorSelect.disabled = !rolPermiteSector();
        }

        rolSelect.addEventListener('change', toggleSector);
        toggleSector();

        form.addEventListener('submit', function () {
            sectorSelect.disabled = false;
        });
    });
</script>
@endsection
