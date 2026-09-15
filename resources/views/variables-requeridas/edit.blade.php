@extends('layouts.app')

@section('title', 'Editar variable requerida')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Editar variable requerida',
        'subtitle' => $variableRequerida->nombre,
        'backUrl' => route('variables-requeridas.index'),
    ])

    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="ucrud-panel">
        <div class="ucrud-form">
    <form action="{{ route('variables-requeridas.update', $variableRequerida->id) }}" method="POST" id="edit-variable-form" novalidate>
        @csrf
        @method('PUT')

        <div class="ucrud-subpanel mb-0">
            <div class="ucrud-subpanel__body">
                <!-- Cotio Descripción -->
                <div class="mb-3">
                    <label for="cotio_descripcion" class="form-label">Cotio Descripción</label>
                    <select name="cotio_descripcion" id="cotio_descripcion" class="form-select select2" required>
                        <option value="">Seleccione una descripción</option>
                        @foreach($cotioDescripciones as $descripcion)
                            <option value="{{ $descripcion }}" {{ old('cotio_descripcion', $variableRequerida->cotio_descripcion) == $descripcion ? 'selected' : '' }}>
                                {{ $descripcion }}
                            </option>
                        @endforeach
                    </select>
                    @error('cotio_descripcion')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Nombre -->
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre de la Variable</label>
                    <input type="text" name="nombre" id="nombre" 
                           class="form-control" 
                           value="{{ old('nombre', $variableRequerida->nombre) }}" 
                           required 
                           minlength="3" 
                           maxlength="255">
                    @error('nombre')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Obligatorio -->
                <div class="mb-3">
                    <label for="obligatorio" class="form-label">Obligatorio</label>
                    <select name="obligatorio" id="obligatorio" class="form-select" required>
                        <option value="1" {{ old('obligatorio', $variableRequerida->obligatorio) == 1 ? 'selected' : '' }}>Sí</option>
                        <option value="0" {{ old('obligatorio', $variableRequerida->obligatorio) == 0 ? 'selected' : '' }}>No</option>
                    </select>
                    @error('obligatorio')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                {{-- <!-- Unidad de Medición -->
                <div class="mb-3">
                    <label for="unidad_medicion" class="form-label">Unidad de Medición</label>
                    <input type="text" name="unidad_medicion" id="unidad_medicion" 
                           class="form-control" 
                           value="{{ old('unidad_medicion', $variableRequerida->unidad_medicion) }}" 
                           maxlength="255">
                    @error('unidad_medicion')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div> --}}
            </div>
        </div>

        <div class="ucrud-form__actions">
            <button type="submit" class="ucrud-btn ucrud-btn--primary">Actualizar</button>
            <a href="{{ route('variables-requeridas.index') }}" class="ucrud-btn ucrud-btn--ghost" id="cancel-button">Cancelar</a>
            <button type="button" class="ucrud-btn ucrud-btn--ghost" id="reset-form">Resetear</button>
        </div>
    </form>
        </div>
    </div>

    <!-- Modal de confirmación para cancelar -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Confirmar Cancelación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de que deseas cancelar? Los cambios no guardados se perderán.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <a href="{{ route('variables-requeridas.index') }}" class="btn btn-danger">Confirmar Cancelar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Select2
    $('#cotio_descripcion').select2({
        theme: 'bootstrap-5',
        placeholder: 'Seleccione una descripción',
        allowClear: true,
        width: '100%'
    });

    const form = document.getElementById('edit-variable-form');
    const cancelButton = document.getElementById('cancel-button');
    const resetButton = document.getElementById('reset-form');
    const originalValues = {
        cotio_descripcion: '{{ $variableRequerida->cotio_descripcion }}',
        nombre: '{{ $variableRequerida->nombre }}',
        obligatorio: '{{ $variableRequerida->obligatorio }}'
    };

    // Validación en tiempo real
    form.addEventListener('input', function(e) {
        const input = e.target;
        if (input.id === 'nombre') {
            if (input.value.length < 3 && input.value.length > 0) {
                input.classList.add('is-invalid');
                input.nextElementSibling.textContent = 'El nombre debe tener al menos 3 caracteres.';
                input.nextElementSibling.classList.add('d-block');
            } else {
                input.classList.remove('is-invalid');
                input.nextElementSibling?.classList.remove('d-block');
            }
        }
    });

    // Confirmación al cancelar
    cancelButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (formHasChanges()) {
            const modal = new bootstrap.Modal(document.getElementById('cancelModal'));
            modal.show();
        } else {
            window.location.href = this.href;
        }
    });

    // Detectar cambios en el formulario
    function formHasChanges() {
        const currentValues = {
            cotio_descripcion: form.querySelector('#cotio_descripcion').value,
            nombre: form.querySelector('#nombre').value,
            obligatorio: form.querySelector('#obligatorio').value
        };
        return JSON.stringify(currentValues) !== JSON.stringify(originalValues);
    }

    // Resetear formulario
    resetButton.addEventListener('click', function() {
        form.querySelector('#cotio_descripcion').value = originalValues.cotio_descripcion;
        $('#cotio_descripcion').trigger('change');
        form.querySelector('#nombre').value = originalValues.nombre;
        form.querySelector('#obligatorio').value = originalValues.obligatorio;
        // form.querySelector('#unidad_medicion').value = originalValues.unidad_medicion;
        form.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.classList.remove('d-block'));
    });
});
</script>
@endpush
@endsection