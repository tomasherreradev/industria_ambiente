@extends('layouts.app')

@section('title', 'Crear variables requeridas')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Crear variables requeridas',
        'subtitle' => 'Asigná variables a una o más determinaciones.',
        'backUrl' => route('variables-requeridas.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
    <form action="{{ route('variables-requeridas.store') }}" method="POST" id="variables-form" novalidate>
        @csrf

        <div class="ucrud-subpanel mb-4">
            <div class="ucrud-subpanel__header">Determinaciones</div>
            <div class="ucrud-subpanel__body">
                <div class="mb-3">
                    <label for="cotio_descripciones" class="form-label">Agrupadores</label>
                    <select name="cotio_descripciones[]" id="cotio_descripciones" class="form-select select2" multiple required>
                        @foreach($cotioDescripciones as $descripcion)
                            <option value="{{ $descripcion }}" {{ in_array($descripcion, old('cotio_descripciones', [])) ? 'selected' : '' }}>
                                {{ $descripcion }}
                            </option>
                        @endforeach
                    </select>
                    @error('cotio_descripciones')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <small class="form-text text-muted">Selecciona una o más descripciones.</small>
                </div>
            </div>
        </div>

        <p class="ucrud-form__section-title">Variables a crear</p>
        <div id="variables-container">
            <div class="variable-group card mb-3 shadow-sm" data-index="0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre_0" class="form-label">Nombre de la Variable</label>
                            <input type="text" name="variables[0][nombre]" id="nombre_0" 
                                   class="form-control" 
                                   value="{{ old('variables.0.nombre') }}" 
                                   required 
                                   minlength="3" 
                                   maxlength="255">
                            @error('variables.0.nombre')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- <div class="col-md-4">
                            <label for="unidad_medicion_0" class="form-label">Unidad de Medición</label>
                            <input type="text" name="variables[0][unidad_medicion]" id="unidad_medicion_0" 
                                   class="form-control" 
                                   value="{{ old('variables.0.unidad_medicion') }}" 
                                   maxlength="255">
                        </div> --}}

                        <div class="col-md-2">
                            <label for="obligatorio_0" class="form-label">Obligatorio</label>
                            <select name="variables[0][obligatorio]" id="obligatorio_0" class="form-select" required>
                                <option value="1" {{ old('variables.0.obligatorio') == '1' ? 'selected' : '' }}>Sí</option>
                                <option value="0" {{ old('variables.0.obligatorio') == '0' ? 'selected' : '' }}>No</option>
                            </select>
                            @error('variables.0.obligatorio')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger btn-sm remove-variable" style="display: none;">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ucrud-form__actions">
            <button type="button" id="add-variable" class="ucrud-btn ucrud-btn--ghost">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" /> Añadir variable
            </button>
            <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar variables</button>
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
    $('#cotio_descripciones').select2({
        theme: 'bootstrap-5',
        placeholder: 'Selecciona descripciones',
        allowClear: true,
        width: '100%'
    });

    const container = document.getElementById('variables-container');
    const addButton = document.getElementById('add-variable');
    const form = document.getElementById('variables-form');
    const cancelButton = document.getElementById('cancel-button');
    const resetButton = document.getElementById('reset-form');
    let index = 1;

    // Añadir nueva variable
    addButton.addEventListener('click', function() {
        const template = document.querySelector('.variable-group').cloneNode(true);
        template.setAttribute('data-index', index);

        // Actualizar nombres e IDs de los campos
        template.querySelectorAll('input, select').forEach(el => {
            const name = el.getAttribute('name').replace('[0]', `[${index}]`);
            const id = el.getAttribute('id').replace('_0', `_${index}`);
            const unidadMedicion = el.getAttribute('name').replace('unidad_medicion', 'unidad_medicion');
            el.setAttribute('name', unidadMedicion);
            el.setAttribute('name', name);
            el.setAttribute('id', id);
            el.value = '';
            el.classList.remove('is-invalid');
            el.nextElementSibling?.classList.remove('d-block');
        });

        // Mostrar botón de eliminar
        const removeButton = template.querySelector('.remove-variable');
        removeButton.style.display = 'block';
        removeButton.setAttribute('aria-label', `Eliminar variable ${index + 1}`);

        container.appendChild(template);
        updateRemoveButtons();
        index++;
    });

    // Eliminar variable
    container.addEventListener('click', function(e) {
        const removeButton = e.target.closest('.remove-variable');
        if (removeButton) {
            const group = removeButton.closest('.variable-group');
            if (document.querySelectorAll('.variable-group').length > 1) {
                group.remove();
            } else {
                // Resetear el primer grupo
                group.querySelectorAll('input').forEach(el => {
                    el.value = '';
                    el.classList.remove('is-invalid');
                });
                group.querySelectorAll('select').forEach(el => {
                    el.selectedIndex = 0;
                    el.classList.remove('is-invalid');
                });
                group.querySelectorAll('.invalid-feedback').forEach(el => el.classList.remove('d-block'));
            }
            updateRemoveButtons();
        }
    });

    // Actualizar visibilidad de botones de eliminación
    function updateRemoveButtons() {
        const groups = document.querySelectorAll('.variable-group');
        groups.forEach(group => {
            const removeButton = group.querySelector('.remove-variable');
            removeButton.style.display = groups.length > 1 ? 'block' : 'none';
        });
    }

    // Validación en tiempo real
    form.addEventListener('input', function(e) {
        const input = e.target;
        if (input.matches('input[name*="nombre"]')) {
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
        const inputs = form.querySelectorAll('input, select');
        for (let input of inputs) {
            if (input.type === 'text' && input.value !== '') return true;
            if (input.type === 'select-multiple' && input.selectedOptions.length > 0) return true;
            if (input.type === 'select-one' && input.value !== '1') return true;
        }
        return false;
    }

    // Resetear formulario
    resetButton.addEventListener('click', function() {
        form.reset();
        $('#cotio_descripciones').val(null).trigger('change');
        while (container.children.length > 1) {
            container.lastChild.remove();
        }
        const firstGroup = container.querySelector('.variable-group');
        firstGroup.querySelectorAll('input').forEach(el => el.value = '');
        firstGroup.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        firstGroup.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove('is-invalid'));
        firstGroup.querySelectorAll('.invalid-feedback').forEach(el => el.classList.remove('d-block'));
        updateRemoveButtons();
    });
});
</script>
@endpush
@endsection