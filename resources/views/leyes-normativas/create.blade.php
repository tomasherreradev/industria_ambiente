@extends('layouts.app')

@section('title', 'Crear ley o normativa')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Crear ley o normativa',
        'subtitle' => 'Alta manual con variables asociadas.',
        'backUrl' => route('leyes-normativas.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form method="POST" action="{{ route('leyes-normativas.store') }}">
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
                                    <label for="grupo" class="form-label">Grupo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('grupo') is-invalid @enderror" 
                                           id="grupo" name="grupo" value="{{ old('grupo') }}"
                                           placeholder="ej: Código Alimentario Argentino">
                                    @error('grupo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="articulo" class="form-label">Artículo</label>
                                    <input type="text" class="form-control @error('articulo') is-invalid @enderror" 
                                           id="articulo" name="articulo" value="{{ old('articulo') }}"
                                           placeholder="ej: Art. 982">
                                    @error('articulo')
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

                        <!-- Variables Asociadas (Cotio Items) -->
                        <div class="mb-4">
                            <label class="form-label">Variables Asociadas</label>
                            <div class="ucrud-subpanel">
                                <div class="ucrud-subpanel__header">
                                    <span>Gestión de variables</span>
                                    <button type="button" class="ucrud-btn ucrud-btn--success" style="padding:.4rem .75rem;font-size:.8rem;" id="addVariableBtn">
                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" /> Agregar variable
                                    </button>
                                </div>
                                <div class="ucrud-subpanel__body">
                                    <div id="variablesContainer">
                                        <!-- Las variables se agregarán aquí dinámicamente -->
                                    </div>
                                    <div class="text-muted text-center py-3" id="noVariablesMessage">
                                        <i class="fas fa-info-circle"></i>
                                        No hay variables asociadas. Haz clic en "Agregar Variable" para comenzar.
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="organismo_emisor" class="form-label">Organismo Emisor</label>
                                    <input type="text" class="form-control @error('organismo_emisor') is-invalid @enderror" 
                                           id="organismo_emisor" name="organismo_emisor" value="{{ old('organismo_emisor') }}"
                                           placeholder="ej: ANMAT, Congreso Nacional">
                                    @error('organismo_emisor')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="fecha_vigencia" class="form-label">Fecha de Vigencia</label>
                                    <input type="date" class="form-control @error('fecha_vigencia') is-invalid @enderror" 
                                           id="fecha_vigencia" name="fecha_vigencia" value="{{ old('fecha_vigencia') }}">
                                    @error('fecha_vigencia')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="fecha_actualizacion" class="form-label">Última Actualización</label>
                                    <input type="date" class="form-control @error('fecha_actualizacion') is-invalid @enderror" 
                                           id="fecha_actualizacion" name="fecha_actualizacion" value="{{ old('fecha_actualizacion') }}">
                                    @error('fecha_actualizacion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control @error('observaciones') is-invalid @enderror" 
                                      id="observaciones" name="observaciones" rows="3">{{ old('observaciones') }}</textarea>
                            @error('observaciones')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                                       value="1" {{ old('activo', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="activo">
                                    Normativa activa
                                </label>
                            </div>
                        </div>

                        <div class="ucrud-form__actions">
                            <a href="{{ route('leyes-normativas.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                            <button type="submit" class="ucrud-btn ucrud-btn--primary">Crear normativa</button>
                        </div>
                    </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let variableCounter = 0;
let availableCotioItems = [];

// Función para cargar cotio_items
async function loadAvailableCotioItems(search = '') {
    try {
        const url = search ? `/cotio-items-api?search=${encodeURIComponent(search)}` : '/cotio-items-api';
        const response = await fetch(url);
        if (response.ok) {
            availableCotioItems = await response.json();
            console.log('Cotio items cargados:', availableCotioItems.length);
        }
    } catch (error) {
        console.error('Error cargando cotio items:', error);
        availableCotioItems = [];
    }
}

// Usar JavaScript vanilla para mayor compatibilidad
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - inicializando variables');
    
    // Cargar cotio items
    loadAvailableCotioItems();
    
    // Buscar el botón
    const addBtn = document.getElementById('addVariableBtn');
    console.log('Botón encontrado:', addBtn !== null);
    
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('¡Botón agregar variable clickeado!');
            addVariableRow();
            return false;
        });
        console.log('Event listener agregado al botón');
    } else {
        console.error('No se encontró el botón addVariableBtn');
    }
    
    // Verificar elementos
    const container = document.getElementById('variablesContainer');
    const noMessage = document.getElementById('noVariablesMessage');
    console.log('Container encontrado:', container !== null);
    console.log('NoMessage encontrado:', noMessage !== null);
});

function addVariableRow(variable = null) {
    console.log('addVariableRow llamada');
    
    const container = document.getElementById('variablesContainer');
    const noMessage = document.getElementById('noVariablesMessage');
    
    if (!container) {
        console.error('Container no encontrado');
        return;
    }
    
    const variableHtml = `
        <div class="variable-row border rounded p-3 mb-3" data-index="${variableCounter}">
            <div class="row">
                <div class="col-md-5">
                    <label class="form-label">Variable (Cotio Item) <span class="text-danger">*</span></label>
                    <select class="form-select variable-select" name="variables[${variableCounter}][cotio_item_id]" required>
                        <option value="">Seleccionar variable...</option>
                        ${availableCotioItems.map(item => {
                            // Escapar caracteres especiales para HTML
                            const matriz = (item.matriz || 'Sin matriz').replace(/"/g, '&quot;');
                            const metodos = (item.metodos || 'Sin método').replace(/"/g, '&quot;');
                            const displayText = item.display_text || `${item.id} - ${item.descripcion}`;
                            const selected = variable && variable.cotio_item_id == item.id ? 'selected' : '';
                            return `
                            <option value="${item.id}" 
                                    data-matriz="${matriz}" 
                                    data-metodos="${metodos}"
                                    data-unidad="${(item.unidad_medida || '').replace(/"/g, '&quot;')}"
                                    ${selected}>
                                ${displayText}
                            </option>
                        `;
                        }).join('')}
                    </select>
                    <small class="text-muted">Busca por ID, descripción, matriz o métodos</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Valor Límite</label>
                    <input type="text" class="form-control valor-limite-input" 
                           name="variables[${variableCounter}][valor_limite]" 
                           value="${variable ? variable.pivot?.valor_limite || '' : ''}"
                           placeholder="ej: 5, 10.5, < 5 mg/L">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unidad de Medida</label>
                    <input type="text" class="form-control unidad-medida-input" 
                           name="variables[${variableCounter}][unidad_medida]" 
                           value="${variable ? variable.pivot?.unidad_medida || '' : ''}"
                           placeholder="ej: mg/L, ppm">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-variable-btn">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', variableHtml);
    if (noMessage) {
        noMessage.style.display = 'none';
    }
    
    // Agregar event listeners usando JavaScript vanilla
    const newRow = container.lastElementChild;
    const removeBtn = newRow.querySelector('.remove-variable-btn');
    const select = newRow.querySelector('.variable-select');
    const unidadInput = newRow.querySelector('.unidad-medida-input');
    
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            removeVariableRow(this);
        });
    }
    
    // Cuando se selecciona un item, actualizar la unidad de medida si está disponible
    if (select && unidadInput) {
        select.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.unidad) {
                unidadInput.value = selectedOption.dataset.unidad;
            }
        });
    }
    
    // Agregar funcionalidad de búsqueda al select usando Select2 si está disponible
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $(select).select2({
            placeholder: 'Buscar variable...',
            allowClear: true,
            language: {
                noResults: function() {
                    return "No se encontraron resultados";
                },
                searching: function() {
                    return "Buscando...";
                }
            }
        });
    }
    
    variableCounter++;
    console.log('Variable row agregada, contador:', variableCounter);
}

function removeVariableRow(button) {
    const row = button.closest('.variable-row');
    row.remove();
    
    const container = document.getElementById('variablesContainer');
    const noMessage = document.getElementById('noVariablesMessage');
    
    if (container && container.children.length === 0 && noMessage) {
        noMessage.style.display = 'block';
    }
}
</script>
@endpush
@endsection
