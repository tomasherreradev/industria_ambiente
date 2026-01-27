@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Editar Ley/Normativa</h2>
        <a href="{{ route('leyes-normativas.index') }}" class="btn btn-outline-secondary">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" /> Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('leyes-normativas.update', $leyNormativa) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('codigo') is-invalid @enderror" 
                                           id="codigo" name="codigo" value="{{ old('codigo', $leyNormativa->codigo) }}" required>
                                    @error('codigo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="grupo" class="form-label">Grupo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('grupo') is-invalid @enderror" 
                                           id="grupo" name="grupo" value="{{ old('grupo', $leyNormativa->grupo) }}"
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
                                           id="articulo" name="articulo" value="{{ old('articulo', $leyNormativa->articulo) }}"
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
                                   id="nombre" name="nombre" value="{{ old('nombre', $leyNormativa->nombre) }}" required>
                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control @error('descripcion') is-invalid @enderror" 
                                      id="descripcion" name="descripcion" rows="3">{{ old('descripcion', $leyNormativa->descripcion) }}</textarea>
                            @error('descripcion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Variables Asociadas -->
                        <div class="mb-4">
                            <label class="form-label">Variables Asociadas</label>
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span>Gestión de Variables</span>
                                    <button type="button" class="btn btn-sm btn-success" id="addVariableBtn">
                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" /> Agregar Variable
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div id="variablesContainer">
                                        <!-- Las variables se cargarán dinámicamente con JavaScript -->
                                    </div>
                                    <div class="text-muted text-center py-3" id="noVariablesMessage" style="display: {{ count($leyNormativa->variables) > 0 ? 'none' : 'block' }};">
                                        <x-heroicon-o-information-circle style="width: 16px; height: 16px;" class="me-1" />
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
                                           id="organismo_emisor" name="organismo_emisor" value="{{ old('organismo_emisor', $leyNormativa->organismo_emisor) }}"
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
                                           id="fecha_vigencia" name="fecha_vigencia" value="{{ old('fecha_vigencia', $leyNormativa->fecha_vigencia?->format('Y-m-d')) }}">
                                    @error('fecha_vigencia')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="fecha_actualizacion" class="form-label">Última Actualización</label>
                                    <input type="date" class="form-control @error('fecha_actualizacion') is-invalid @enderror" 
                                           id="fecha_actualizacion" name="fecha_actualizacion" value="{{ old('fecha_actualizacion', $leyNormativa->fecha_actualizacion?->format('Y-m-d')) }}">
                                    @error('fecha_actualizacion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control @error('observaciones') is-invalid @enderror" 
                                      id="observaciones" name="observaciones" rows="3">{{ old('observaciones', $leyNormativa->observaciones) }}</textarea>
                            @error('observaciones')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" 
                                       value="1" {{ old('activo', $leyNormativa->activo) ? 'checked' : '' }}>
                                <label class="form-check-label" for="activo">
                                    Normativa activa
                                </label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="{{ route('leyes-normativas.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Normativa</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
let variableCounter = 0;
let availableCotioItems = [];
const existingVariables = @json($leyNormativa->variables);

// Función para cargar cotio_items
async function loadAvailableCotioItems(search = '') {
    try {
        const url = search ? `/cotio-items-api?search=${encodeURIComponent(search)}` : '/cotio-items-api';
        const response = await fetch(url);
        if (response.ok) {
            availableCotioItems = await response.json();
            console.log('Cotio items cargados (edit):', availableCotioItems.length);
        }
    } catch (error) {
        console.error('Error cargando cotio items:', error);
        availableCotioItems = [];
    }
}

// Cargar variables existentes
function loadExistingVariables() {
    if (existingVariables && existingVariables.length > 0) {
        existingVariables.forEach(variable => {
            addVariableRow(variable);
        });
        updateNoVariablesMessage();
    } else {
        updateNoVariablesMessage();
    }
}

// Función para actualizar el mensaje de "no variables"
function updateNoVariablesMessage() {
    const container = document.getElementById('variablesContainer');
    const noMessage = document.getElementById('noVariablesMessage');
    
    if (container && noMessage) {
        const hasVariables = container.children.length > 0;
        noMessage.style.display = hasVariables ? 'none' : 'block';
    }
}

// Usar JavaScript vanilla para mayor compatibilidad
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded (edit) - inicializando variables');
    
    // Cargar cotio items
    loadAvailableCotioItems().then(() => {
        loadExistingVariables();
    });
    
    // Buscar el botón
    const addBtn = document.getElementById('addVariableBtn');
    console.log('Botón encontrado (edit):', addBtn !== null);
    
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('¡Botón agregar variable clickeado (edit)!');
            addVariableRow();
            return false;
        });
        console.log('Event listener agregado al botón (edit)');
    } else {
        console.error('No se encontró el botón addVariableBtn (edit)');
    }
    
    // Verificar elementos
    const container = document.getElementById('variablesContainer');
    const noMessage = document.getElementById('noVariablesMessage');
    console.log('Container encontrado (edit):', container !== null);
    console.log('NoMessage encontrado (edit):', noMessage !== null);
});

function addVariableRow(variable = null) {
    console.log('addVariableRow llamada (edit)');
    
    const container = document.getElementById('variablesContainer');
    
    if (!container) {
        console.error('Container no encontrado (edit)');
        return;
    }
    
    // Crear el elemento div
    const variableDiv = document.createElement('div');
    variableDiv.className = 'variable-row border rounded p-3 mb-3';
    variableDiv.setAttribute('data-index', variableCounter);
    
    // Obtener el cotio_item_id de la variable existente
    const cotioItemId = variable && variable.cotio_item_id ? variable.cotio_item_id : 
                       (variable && variable.cotioItem ? variable.cotioItem.id : null);
    
    variableDiv.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <label class="form-label">Determinación <span class="text-danger">*</span></label>
                <select class="form-select variable-select" name="variables[${variableCounter}][cotio_item_id]" required>
                    <option value="">Seleccionar variable...</option>
                    ${availableCotioItems.map(item => {
                        // Escapar caracteres especiales para HTML
                        const matriz = (item.matriz || 'Sin matriz').replace(/"/g, '&quot;');
                        const metodos = (item.metodos || 'Sin método').replace(/"/g, '&quot;');
                        const displayText = item.display_text || `${item.id} - ${item.descripcion}`;
                        const selected = cotioItemId == item.id ? 'selected' : '';
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
                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(variableDiv);
    
    // Agregar event listeners
    const removeBtn = variableDiv.querySelector('.remove-variable-btn');
    const select = variableDiv.querySelector('.variable-select');
    const unidadInput = variableDiv.querySelector('.unidad-medida-input');
    
    if (removeBtn) {
        removeBtn.addEventListener('click', function(e) {
            e.preventDefault();
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
    
    updateNoVariablesMessage();
    variableCounter++;
    console.log('Variable row agregada (edit), contador:', variableCounter);
}

function removeVariableRow(button) {
    const row = button.closest('.variable-row');
    if (!row) return;
    
    const select = row.querySelector('.variable-select');
    const cotioItemId = select ? select.value : null;
    
    // Si la variable ya está guardada en la base de datos, hacer petición AJAX
    // Buscar si existe una variable con este cotio_item_id
    const existingVariable = existingVariables.find(v => 
        (v.cotio_item_id && v.cotio_item_id == cotioItemId) || 
        (v.cotioItem && v.cotioItem.id == cotioItemId)
    );
    
    if (cotioItemId && existingVariable) {
        // Confirmar eliminación
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción eliminará la variable de la normativa.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                removeVariableFromDatabase(existingVariable.id, row);
            }
        });
    } else {
        // Si es una variable nueva (no guardada), simplemente eliminar del DOM
        row.remove();
        updateNoVariablesMessage();
        console.log('Variable nueva eliminada (edit)');
    }
}

async function removeVariableFromDatabase(variableId, row) {
    try {
        const response = await fetch(`/leyes-normativas/{{ $leyNormativa->id }}/remove-variable`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                variable_id: variableId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            row.remove();
            updateNoVariablesMessage();
            
            // Eliminar de la lista de variables existentes
            const index = existingVariables.findIndex(v => v.id == variableId);
            if (index > -1) {
                existingVariables.splice(index, 1);
            }
            
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
            
            console.log('Variable eliminada de la base de datos (edit)');
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al eliminar la variable'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al eliminar la variable'
        });
    }
}

</script>
@endsection

