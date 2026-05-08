@extends('layouts.app')

@section('title', 'Editar usuario')

@php
    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
    $opcionesRol = [
        '' => 'Sin rol',
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
    <h1 class="mb-4">Editando: {{ $usuario->usu_descripcion }}</h1>
    <div class="row">
        <div class="col-lg-8">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

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
                <div class="card-header bg-dark text-white">
                    <strong>Formulario de edición</strong>
                    {{-- @if($esAdmin)
                        <span class="badge bg-warning text-dark ms-2">Administración</span>
                    @endif --}}
                </div>
                <div class="card-body">
                    <form id="form-editar-usuario" action="{{ url('/users/' . $usuario->usu_codigo) }}" method="POST" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="usu_codigo_display" class="form-label">Código (usuario de ingreso)</label>
                            <input type="text" id="usu_codigo_display" class="form-control" value="{{ $usuario->usu_codigo }}" readonly disabled>
                            <div class="form-text">La clave primaria no se modifica desde aquí.</div>
                        </div>

                        <div class="mb-3">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" value="{{ old('usu_descripcion', $usuario->usu_descripcion) }}" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="dni" class="form-label">DNI / documento</label>
                                <input type="text" name="dni" id="dni" class="form-control" value="{{ old('dni', $usuario->dni) }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Correo</label>
                                <input type="text" name="email" id="email" class="form-control" value="{{ old('email', $usuario->email) }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="departamento" class="form-label">Departamento / área</label>
                            <input type="text" name="departamento" id="departamento" class="form-control" value="{{ old('departamento', $usuario->departamento) }}">
                        </div>

                        <div class="mb-3">
                            <label for="sector_trabajo" class="form-label">Sector de trabajo (texto libre)</label>
                            <input type="text" name="sector_trabajo" id="sector_trabajo" class="form-control" value="{{ old('sector_trabajo', $usuario->sector_trabajo) }}" placeholder="p. ej. química, microbiología">
                        </div>

                        @php
                            $estadoVal = (string) old('usu_estado', $usuario->usu_estado ? '1' : '0');
                        @endphp
                        <div class="mb-3">
                            <label for="usu_estado" class="form-label">Estado</label>
                            <select name="usu_estado" id="usu_estado" class="form-select" required>
                                <option value="1" @selected($estadoVal === '1')>Activo</option>
                                <option value="0" @selected($estadoVal === '0')>Inactivo</option>
                            </select>
                        </div>

                        @if($esAdmin)
                            <div class="mb-3">
                                <label for="usu_nivel" class="form-label">Nivel de acceso (≥900 = administrador)</label>
                                <input type="number" name="usu_nivel" id="usu_nivel" class="form-control" min="0" max="9999" value="{{ old('usu_nivel', $usuario->usu_nivel) }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Nueva contraseña</label>
                                    <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" placeholder="Dejar vacío para no cambiar">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                                </div>
                            </div>

                            {{-- <div class="mb-3 form-check">
                                <input type="checkbox" name="limpiar_sesion" id="limpiar_sesion" class="form-check-input" value="1" {{ old('limpiar_sesion') ? 'checked' : '' }}>
                                <label for="limpiar_sesion" class="form-check-label">Cerrar otras sesiones (invalidar inicio de sesión en otros dispositivos)</label>
                            </div> --}}
                        @endif

                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol principal</label>
                            <select name="rol" id="rol" class="form-select">
                                @foreach($opcionesRol as $val => $etq)
                                    <option value="{{ $val }}" @selected((string) old('rol', $usuario->rol ?? '') === (string) $val)>{{ $etq }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($esAdmin)
                            <div class="mb-3">
                                <label for="roles_adicionales" class="form-label">Roles adicionales (varios; Ctrl+clic)</label>
                                <select name="roles_adicionales[]" id="roles_adicionales" class="form-select" multiple size="8">
                                    @foreach($opcionesRol as $val => $etq)
                                        @if($val === '') @continue @endif
                                        <option value="{{ $val }}"
                                            {{ in_array($val, old('roles_adicionales', $rolesAdicionales), true) ? 'selected' : '' }}>{{ $etq }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">No hace falta repetir el rol principal; se ignorará si lo marcás.</div>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="sector_codigo" class="form-label">Laboratorio (sector)</label>
                            <select name="sector_codigo" id="sector_codigo" class="form-select">
                                <option value="">Sin sector</option>
                                @foreach($sectores as $sector)
                                    <option value="{{ $sector->usu_codigo }}" {{ trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '') || old('sector_codigo') == $sector->usu_codigo ? 'selected' : '' }}>
                                        {{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ url('/users') }}" class="btn btn-secondary">← Volver al listado</a>
                            <button type="submit" class="btn btn-primary">Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('form-editar-usuario');
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');
        if (!form || !rolSelect || !sectorSelect) return;

        function rolPermiteSector() {
            const rol = rolSelect.value;
            return rol === 'laboratorio' || rol === 'coordinador_lab';
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
