@extends('layouts.app')
<head>
    <title>Editar Laboratorio</title>
</head>

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Editando: {{ $sector->usu_descripcion }}</h1>

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
                    <form action="{{ url('/sectores/' . $sector->usu_codigo) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" value="{{ old('usu_descripcion', $sector->usu_descripcion) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="usu_codigo" class="form-label">Codigo</label>
                            <input type="text" name="usu_codigo" id="usu_codigo" class="form-control" value="{{ old('usu_codigo', $sector->usu_codigo) }}" required>
                        </div>

                        {{-- <div class="mb-3">
                            <label for="usu_estado" class="form-label">Estado</label>
                            <select name="usu_estado" id="usu_estado" class="form-select" required>
                                <option value="1" {{ $sector->usu_estado ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ !$sector->usu_estado ? 'selected' : '' }}>Inactivo</option>
                            </select>
                        </div> --}}

                        {{-- <div class="mb-3">
                            <label for="rol" class="form-label">Rol</label>
                            <select name="rol" id="rol" class="form-select">
                                <option value="">Sin rol</option>
                                <option value="analista" {{ $sector->rol === 'laboratorio' ? 'selected' : '' }}>Analista</option>
                                <option value="muestreador" {{ $sector->rol === 'muestreador' ? 'selected' : '' }}>Muestreador</option>
                                <option value="coordinador_lab" {{ $sector->rol === 'coordinador_lab' ? 'selected' : '' }}>Coordinador Laboratorio</option>
                                <option value="coordinador_muestreo" {{ $sector->rol === 'coordinador_muestreo' ? 'selected' : '' }}>Coordinador Muestreo</option>
                            </select>
                        </div> --}}

                        <div class="d-flex justify-content-between">
                            <a href="{{ url('/sectores') }}" class="btn btn-secondary">← Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
