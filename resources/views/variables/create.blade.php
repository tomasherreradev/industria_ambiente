@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Crear Variable</h2>
        <a href="{{ route('variables.index') }}" class="btn btn-outline-secondary">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" /> Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('variables.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('codigo') is-invalid @enderror" 
                                           id="codigo" name="codigo" value="{{ old('codigo') }}" required>
                                    @error('codigo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="tipo_variable" class="form-label">Tipo <span class="text-danger">*</span></label>
                                    <select class="form-select @error('tipo_variable') is-invalid @enderror" 
                                            id="tipo_variable" name="tipo_variable" required>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="Físico-Química" {{ old('tipo_variable') == 'Físico-Química' ? 'selected' : '' }}>Físico-Química</option>
                                        <option value="Microbiológica" {{ old('tipo_variable') == 'Microbiológica' ? 'selected' : '' }}>Microbiológica</option>
                                        <option value="Organoléptica" {{ old('tipo_variable') == 'Organoléptica' ? 'selected' : '' }}>Organoléptica</option>
                                        <option value="Química" {{ old('tipo_variable') == 'Química' ? 'selected' : '' }}>Química</option>
                                        <option value="Metales Pesados" {{ old('tipo_variable') == 'Metales Pesados' ? 'selected' : '' }}>Metales Pesados</option>
                                        <option value="Plaguicidas" {{ old('tipo_variable') == 'Plaguicidas' ? 'selected' : '' }}>Plaguicidas</option>
                                        @foreach($tipos as $tipo)
                                            @if(!in_array($tipo, ['Físico-Química', 'Microbiológica', 'Organoléptica', 'Química', 'Metales Pesados', 'Plaguicidas']))
                                                <option value="{{ $tipo }}" {{ old('tipo_variable') == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    @error('tipo_variable')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="unidad_medicion" class="form-label">Unidad de Medición</label>
                                    <input type="text" class="form-control @error('unidad_medicion') is-invalid @enderror" 
                                           id="unidad_medicion" name="unidad_medicion" value="{{ old('unidad_medicion') }}"
                                           placeholder="ej: mg/L, UFC/100ml, pH">
                                    @error('unidad_medicion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nombre') is-invalid @enderror" 
                                   id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control @error('descripcion') is-invalid @enderror" 
                                      id="descripcion" name="descripcion" rows="3">{{ old('descripcion') }}</textarea>
                            @error('descripcion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="limite_minimo" class="form-label">Límite Mínimo</label>
                                    <input type="number" class="form-control @error('limite_minimo') is-invalid @enderror" 
                                           id="limite_minimo" name="limite_minimo" value="{{ old('limite_minimo') }}" 
                                           step="0.000001">
                                    @error('limite_minimo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="limite_maximo" class="form-label">Límite Máximo</label>
                                    <input type="number" class="form-control @error('limite_maximo') is-invalid @enderror" 
                                           id="limite_maximo" name="limite_maximo" value="{{ old('limite_maximo') }}" 
                                           step="0.000001">
                                    @error('limite_maximo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="metodo_determinacion" class="form-label">Método de Determinación</label>
                            <textarea class="form-control @error('metodo_determinacion') is-invalid @enderror" 
                                      id="metodo_determinacion" name="metodo_determinacion" rows="3">{{ old('metodo_determinacion') }}</textarea>
                            @error('metodo_determinacion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                                       value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="activo">
                                    Variable activa
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="{{ route('variables.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Crear Variable</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
