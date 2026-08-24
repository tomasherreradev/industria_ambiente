@extends('layouts.app')

@section('title', 'Editar nota predeterminada')

@section('content')
<div class="container py-3 py-md-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Editar nota predeterminada</h1>
        <a href="{{ route('ventas.notas.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('ventas.notas.update', $nota) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label for="titulo" class="form-label">Título (opcional)</label>
                    <input type="text" class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo"
                           value="{{ old('titulo', $nota->titulo) }}" maxlength="255">
                    @error('titulo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" min="0" class="form-control @error('orden') is-invalid @enderror" id="orden" name="orden"
                           value="{{ old('orden', $nota->orden) }}">
                    @error('orden')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label for="contenido" class="form-label">Texto de la nota <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('contenido') is-invalid @enderror" id="contenido" name="contenido"
                              rows="8" required>{{ old('contenido', $nota->contenido) }}</textarea>
                    @error('contenido')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activa" id="activa" value="1"
                               @checked(old('activa', $nota->activa))>
                        <label class="form-check-label" for="activa">Activa</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('ventas.notas.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
