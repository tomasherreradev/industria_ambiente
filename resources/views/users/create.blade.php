@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Nuevo Usuario</h1>
    <form action="{{ route('users.storeUser') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="usu_descripcion" class="form-label">Nombre</label>
            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="usu_codigo" class="form-label">Código</label>
            <input type="text" name="usu_codigo" id="usu_codigo" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-control" required>
                <option value="">Selecciona un rol</option>
                <option value="laboratorio">Analista</option>
                <option value="muestreador">Muestreador</option>
                <option value="coordinador_lab">Coordinador Laboratorio</option>
                <option value="coordinador_muestreo">Coordinador Muestreo</option>
                <option value="facturador">Facturador</option>
                <option value="ventas">Vendedor</option>
                <option value="firmador">Firmador</option>
                <option value="cliente">Usuario Cliente</option>
            </select>
        </div>

        {{-- <div class="mb-3">
            <label for="cli_listado" class="form-label">Perteneciente a:</label>
            
            <checkbox name="cli_listado" id="cli_listado" class="form-control">
                <checkbox value="">Selecciona uno o varios clientes</checkbox>
                @foreach($clientes as $cliente)
                    <checkbox value="{{ $cliente->cli_codigo }}">{{ $cliente->cli_razonsocial }}</checkbox>
                @endforeach
            </checkbox>
        </div> --}}

        <div class="mb-3">
            <label for="sector_codigo" class="form-label">Sector</label>
            <select name="sector_codigo" id="sector_codigo" class="form-control">
                <option value="">Selecciona un sector</option>
                @foreach($sectores as $sector)
                    <option value="{{ $sector->usu_codigo }}">{{ $sector->usu_descripcion }}</option>
                @endforeach
            </select>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>



<script>
    document.addEventListener('DOMContentLoaded', function () {
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');

        function toggleSector() {
            const selectedRol = rolSelect.value;
            const habilitado = selectedRol === 'coordinador_lab' || selectedRol === 'laboratorio';

            sectorSelect.disabled = !habilitado;
            if (!habilitado) {
                sectorSelect.value = '';
            }
        }

        rolSelect.addEventListener('change', toggleSector);

        toggleSector();
    });
</script>



@endsection
