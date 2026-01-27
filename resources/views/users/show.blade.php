@extends('layouts.app')
<head>
    <title>Editar Usuario</title>
</head>

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Editando: {{ $usuario->usu_descripcion }}</h1>
    <div class="row">
        <div class="col-md-8">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <strong>Formulario de Edición</strong>
                </div>
                <div class="card-body">
                    <form action="{{ url('/users/' . $usuario->usu_codigo) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" value="{{ old('usu_descripcion', $usuario->usu_descripcion) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="usu_estado" class="form-label">Estado</label>
                            <select name="usu_estado" id="usu_estado" class="form-select" required>
                                <option value="1" {{ $usuario->usu_estado ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ !$usuario->usu_estado ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="sector_codigo" class="form-label">Sector</label>
                            <select name="sector_codigo" id="sector_codigo" class="form-select">
                                <option value="">Sin sector</option>
                                @foreach($sectores as $sector)
                                    <option value="{{ $sector->usu_codigo }}" {{ trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '') ? 'selected' : '' }}>
                                        {{ $sector->usu_descripcion }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            <select name="rol" id="rol" class="form-select">
                                <option value="">Sin rol</option>
                                <option value="laboratorio" {{ $usuario->rol === 'laboratorio' ? 'selected' : '' }}>Analista</option>
                                <option value="muestreador" {{ $usuario->rol === 'muestreador' ? 'selected' : '' }}>Muestreador</option>
                                <option value="coordinador_lab" {{ $usuario->rol === 'coordinador_lab' ? 'selected' : '' }}>Coordinador Laboratorio</option>
                                <option value="coordinador_muestreo" {{ $usuario->rol === 'coordinador_muestreo' ? 'selected' : '' }}>Coordinador Muestreo</option>
                                <option value="facturador" {{ $usuario->rol === 'facturador' ? 'selected' : '' }}>Facturador</option>
                                <option value="ventas" {{ $usuario->rol === 'ventas' ? 'selected' : '' }}>Vendedor</option>
                                <option value="firmador" {{ $usuario->rol === 'firmador' ? 'selected' : '' }}>Firmador</option>
                                <option value="cliente" {{ $usuario->rol === 'cliente' ? 'selected' : '' }}>Usuario Cliente</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ url('/users') }}" class="btn btn-secondary">← Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');

        function toggleSector() {
            const rol = rolSelect.value;
            const habilitar = rol === 'laboratorio' || rol === 'coordinador_lab';

            sectorSelect.disabled = !habilitar;
            if (!habilitar) {
                sectorSelect.value = '';
            }
        }

        rolSelect.addEventListener('change', toggleSector);
        toggleSector(); // ejecutar al cargar por si ya hay rol seleccionado
    });
</script>


@endsection
