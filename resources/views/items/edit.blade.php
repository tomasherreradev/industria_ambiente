@extends('layouts.app')

@section('title', 'Editar Parámetro')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Editar Parámetro</h1>
        <a href="{{ route('items.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('items.update', $item) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">ID</label>
                    <input type="text" class="form-control" value="{{ $item->id }}" readonly>
                </div>

                <div class="mb-3">
                    <label for="cotio_descripcion" class="form-label">Determinación</label>
                    <input type="text" name="cotio_descripcion" id="cotio_descripcion" value="{{ old('cotio_descripcion', $item->cotio_descripcion) }}" class="form-control @error('cotio_descripcion') is-invalid @enderror" required>
                    @error('cotio_descripcion')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="limites_establecidos" class="form-label">Límites establecidos</label>
                    <input type="text" name="limites_establecidos" id="limites_establecidos" value="{{ old('limites_establecidos', $item->limites_establecidos) }}" class="form-control @error('limites_establecidos') is-invalid @enderror" placeholder="Ej: No especifica, 6,5 <3, etc">
                    @error('limites_establecidos')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="metodo" class="form-label">Método</label>
                    <select name="metodo" id="metodo" class="form-select select2">
                        <option value="">Sin método</option>
                        @foreach($metodos as $met)
                            <option value="{{ trim($met->metodo_codigo) }}" {{ old('metodo', $item->metodo) == trim($met->metodo_codigo) ? 'selected' : '' }}>
                                {{ trim($met->metodo_codigo) }} - {{ $met->metodo_descripcion }}
                            </option>
                        @endforeach
                    </select>
                    @error('metodo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="matrices" class="form-label">Matrices</label>
                    <select name="matrices[]" id="matrices" class="form-select select2-multiple" multiple data-placeholder="Selecciona las matrices">
                        @php
                            $matricesSeleccionadas = old('matrices', $item->matrices->pluck('matriz_codigo')->toArray());
                        @endphp
                        @foreach($matrices as $matriz)
                            <option value="{{ $matriz->matriz_codigo }}" {{ in_array($matriz->matriz_codigo, $matricesSeleccionadas) ? 'selected' : '' }}>
                                {{ $matriz->matriz_codigo }} - {{ $matriz->matriz_descripcion }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Puedes seleccionar múltiples matrices para este ítem.</small>
                    @error('matrices')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="unidad_medida" class="form-label">Unidad de medida</label>
                    <input type="text" name="unidad_medida" id="unidad_medima" value="{{ old('unidad_medida', $item->unidad_medida) }}" class="form-control @error('unidad_medida') is-invalid @enderror" placeholder="Ej: mg/L, µg/L, etc">
                    @error('unidad_medida')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="precio" class="form-label">Precio</label>
                    <input type="number" name="precio" id="precio" value="{{ old('precio', $item->precio ? number_format($item->precio, 2, '.', '') : '') }}" step="0.01" min="0" class="form-control @error('precio') is-invalid @enderror" placeholder="Ej: 5000 o 5000.50">
                    <small class="text-muted">Puedes ingresar valores con o sin decimales. Se guardará con 2 decimales.</small>
                    @error('precio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="es_muestra" name="es_muestra" value="1" {{ old('es_muestra', $item->es_muestra) ? 'checked' : '' }}>
                    <label class="form-check-label" for="es_muestra">Es agrupador</label>
                </div>

                <div class="form-check form-switch mb-3 {{ old('es_muestra', $item->es_muestra) ? '' : 'd-none' }}" id="agregable_a_comps_wrapper">
                    <input class="form-check-input" type="checkbox" role="switch" id="agregable_a_comps" name="agregable_a_comps" value="1" {{ old('agregable_a_comps', $item->agregable_a_comps) ? 'checked' : '' }}>
                    <label class="form-check-label" for="agregable_a_comps">Agregable como componente</label>
                    <small class="text-muted d-block">Si está marcado, este agrupador podrá ser agregado como componente en las cotizaciones, trayendo consigo sus componentes asociados.</small>
                </div>

                <div class="mb-3 {{ old('es_muestra', $item->es_muestra) ? '' : 'd-none' }}" id="componentes_wrapper">
                    <label for="componentes" class="form-label">Componentes asociados</label>
                    <select name="componentes[]" id="componentes" class="form-select select2-multiple" multiple data-placeholder="Selecciona los componentes">
                        @foreach($componentes as $componente)
                            @php
                                // Obtener nombre del método
                                $metodoCodigo = trim($componente->metodo ?? '');
                                $metodoNombre = '';
                                
                                if ($metodoCodigo) {
                                    // Intentar desde MetodoAnalitico (relación con Metodo legacy)
                                    if ($componente->metodoAnalitico) {
                                        $metodoNombre = trim($componente->metodoAnalitico->metodo_descripcion ?? '');
                                    }
                                    // Si no, intentar desde MetodoMuestreo (relación con Metodo legacy)
                                    if (!$metodoNombre && $componente->metodoMuestreo) {
                                        $metodoNombre = trim($componente->metodoMuestreo->metodo_descripcion ?? '');
                                    }
                                    // Si no, buscar en MetodoAnalisis
                                    if (!$metodoNombre) {
                                        $metodoAnalisis = \App\Models\MetodoAnalisis::where('codigo', $metodoCodigo)->first();
                                        if ($metodoAnalisis) {
                                            $metodoNombre = trim($metodoAnalisis->nombre ?? '');
                                        }
                                    }
                                    // Si no, buscar en MetodoMuestreo
                                    if (!$metodoNombre) {
                                        $metodoMuestreo = \App\Models\MetodoMuestreo::where('codigo', $metodoCodigo)->first();
                                        if ($metodoMuestreo) {
                                            $metodoNombre = trim($metodoMuestreo->nombre ?? '');
                                        }
                                    }
                                }
                                
                                $metodoDisplay = $metodoNombre ? ($metodoCodigo . ' - ' . $metodoNombre) : ($metodoCodigo ?: 'Sin método');
                                
                                // Obtener matriz con código y descripción
                                $matrizDisplay = 'Sin matriz';
                                if ($componente->matriz) {
                                    $matrizCodigo = trim($componente->matriz->matriz_codigo ?? '');
                                    $matrizDescripcion = trim($componente->matriz->matriz_descripcion ?? '');
                                    if ($matrizCodigo && $matrizDescripcion) {
                                        $matrizDisplay = $matrizCodigo . ' - ' . $matrizDescripcion;
                                    } elseif ($matrizDescripcion) {
                                        $matrizDisplay = $matrizDescripcion;
                                    } elseif ($matrizCodigo) {
                                        $matrizDisplay = $matrizCodigo;
                                    }
                                }
                            @endphp
                            <option value="{{ $componente->id }}"
                                data-precio="{{ number_format($componente->precio ?? 0, 2, '.', '') }}"
                                data-matriz="{{ $matrizDisplay }}"
                                data-metodo="{{ $metodoDisplay }}"
                                data-limites_establecidos="{{ $componente->limites_establecidos ?? 'Sin límites' }}"
                                data-unidad="{{ $componente->unidad_medida ?? 's/u' }}"
                                {{ in_array($componente->id, old('componentes', $item->componentesAsociados->pluck('id')->toArray())) ? 'selected' : '' }}>
                                {{ $componente->cotio_descripcion }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted">Estos componentes se sugerirán al usar este agrupador en una cotización.</small>
                    @error('componentes')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="{{ route('items.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>

            <div class="mt-3 d-flex">
                <form action="{{ route('items.delete', $item) }}" method="POST" class="js-delete-form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->any())
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Corrige los errores',
            html: `{!! implode('<br>', $errors->all()) !!}`
        });
    });
    </script>
    @endif

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.js-delete-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar este ítem?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        }
    });
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const agrupadorCheck = document.getElementById('es_muestra');
        const wrapper = document.getElementById('componentes_wrapper');
        const select = $('#componentes');

        function formatComponenteOption(option) {
            if (!option.id) {
                return option.text;
            }

            const $option = $(option.element);
            const precio = $option.data('precio') || '0.00';
            const matriz = $option.data('matriz') || 'Sin matriz';
            const metodo = $option.data('metodo') || 'Sin método';
            const unidad = $option.data('unidad') || 's/u';
            const limites = $option.data('limites_establecidos') || 'Sin límites';
            const descripcion = option.text;

            return $(
                '<div class="componente-option-item">' +
                    '<div class="fw-semibold mb-1">' + descripcion + '</div>' +
                    '<div class="d-flex flex-wrap gap-3 small text-muted">' +
                        '<span><strong>Límites:</strong> ' + limites + '</span>' +
                        '<span><strong>U. Med:</strong> ' + unidad + '</span>' +
                        '<span><strong>Método:</strong> ' + metodo + '</span>' +
                    '</div>' +
                '</div>'
            );
        }

        function formatComponenteSelection(option) {
            if (!option.id) {
                return option.text;
            }

            const $option = $(option.element);
            const precio = $option.data('precio') || '0.00';
            const matriz = $option.data('matriz') || 'Sin matriz';
            const descripcion = option.text;

            return descripcion + ' | $' + parseFloat(precio).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' | ' + matriz;
        }

        if (select.length) {
            select.select2({
                width: '100%',
                placeholder: select.data('placeholder') || 'Selecciona los componentes',
                templateResult: formatComponenteOption,
                templateSelection: formatComponenteSelection,
                escapeMarkup: function(markup) {
                    return markup;
                }
            });
        }

        // Inicializar select2 para matrices
        const selectMatrices = $('#matrices');
        if (selectMatrices.length) {
            selectMatrices.select2({
                width: '100%',
                placeholder: selectMatrices.data('placeholder') || 'Selecciona las matrices'
            });
        }

        const agregableWrapper = document.getElementById('agregable_a_comps_wrapper');

        function toggleComponentes() {
            if (!wrapper) return;
            const isAgrupador = agrupadorCheck && agrupadorCheck.checked;
            
            if (isAgrupador) {
                wrapper.classList.remove('d-none');
                if (agregableWrapper) {
                    agregableWrapper.classList.remove('d-none');
                }
            } else {
                wrapper.classList.add('d-none');
                if (agregableWrapper) {
                    agregableWrapper.classList.add('d-none');
                }
                if (select.length) {
                    select.val(null).trigger('change');
                }
            }
        }

        if (agrupadorCheck) {
            agrupadorCheck.addEventListener('change', toggleComponentes);
        }

        toggleComponentes();
    });
    </script>

    <style>
    .componente-option-item {
        padding: 0.5rem 0;
    }
    .componente-option-item .fw-semibold {
        color: #495057;
        font-size: 0.9rem;
    }
    .componente-option-item .text-muted {
        font-size: 0.8rem;
        line-height: 1.6;
    }
    .componente-option-item .text-muted span {
        display: inline-block;
        margin-right: 1rem;
    }
    .select2-results__option--highlighted .componente-option-item .fw-semibold {
        color: #ffffff;
    }
    .select2-results__option--highlighted .componente-option-item .text-muted {
        color: rgba(255, 255, 255, 0.8);
    }
    </style>
</div>
@endsection


