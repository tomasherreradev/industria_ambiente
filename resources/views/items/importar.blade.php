@extends('layouts.app')

@section('title', 'Importar Determinaciones')

@section('content')
@include('partials.ucrud-styles')

<div class="container-fluid py-4 ucrud ucrud-layout--fluid">
    @include('partials.ucrud-form-header', [
        'title' => 'Importar determinaciones',
        'subtitle' => 'Carga masiva desde archivo Excel o CSV.',
        'backUrl' => route('items.index'),
    ])

    @if(session('success'))
        <div id="flash-success" data-message="{{ session('success') }}" style="display:none"></div>
    @endif

    @if(session('warning'))
        <div id="flash-warning" data-message="{{ session('warning') }}" style="display:none"></div>
    @endif

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger">
            <h5 class="alert-heading">Errores encontrados:</h5>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('import_errors'))
        <div class="ucrud-alert ucrud-alert--warning">
            <h5 class="alert-heading">Errores durante la importación:</h5>
            <ul class="mb-0">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ucrud-panel">
                <div class="ucrud-form">
                    <p class="ucrud-form__section-title" style="margin-top:0;">Subir archivo Excel</p>
                    <form method="POST" action="{{ route('items.importar-procesar') }}" enctype="multipart/form-data" id="formImportar">
                        @csrf

                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo Excel <span class="text-danger">*</span></label>
                            <input type="file" 
                                   name="archivo" 
                                   id="archivo" 
                                   class="form-control @error('archivo') is-invalid @enderror" 
                                   accept=".xlsx,.xls,.csv" 
                                   required>
                            @error('archivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Formatos soportados: .xlsx, .xls, .csv (Máximo 10MB)
                            </small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="actualizar_existentes" id="actualizar_existentes" value="1" checked>
                                <label class="form-check-label" for="actualizar_existentes">
                                    Actualizar determinaciones existentes (basado en descripción)
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Marcado (recomendado): reutiliza parámetros existentes con el mismo nombre, matriz (Tipo) y metodologías, y actualiza unidad, límites y precio sin duplicar.
                                Desmarcado: crea ítems nuevos aunque ya exista uno parecido (solo formato antiguo por descripción; en plantilla Tipo/Agrupador/Parámetro siempre crea filas nuevas).
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <h6 class="alert-heading">📋 Instrucciones:</h6>
                            <ul class="mb-0 small">
                                <li>Descarga la plantilla Excel para ver el formato correcto</li>
                                <li>La plantilla incluye <strong>múltiples hojas</strong>: una para los datos y otras con listas de referencia (Métodos, Matrices, Componentes)</li>
                                <li>La primera fila debe contener los encabezados</li>
                                <li><strong>Formato nuevo:</strong> Usa texto descriptivo en lugar de códigos</li>
                                <li><strong>Tipo:</strong> Nombre de la matriz (ej: "LÍQUIDO"). Si no existe, se creará automáticamente</li>
                                <li><strong>Agrupador:</strong> Nombre del agrupador (ej: "EFLUENTE LÍQUIDO"). Puedes indicar varios en la misma celda separados por <strong>punto y coma o coma</strong> (ej: "AGUA; AGUA SUPERFICIAL; EFLUENTE LÍQUIDO" o "AGUA, AGUA SUPERFICIAL"). Si no existen, se crearán automáticamente</li>
                                <li><strong>Parámetro:</strong> Nombre del componente/parámetro (ej: "pH"). Si no existe, se creará automáticamente</li>
                                <li><strong>Metodología muestreo/análisis:</strong> Nombre completo del método (ej: "SM 4500 H+ B"). Si no existe, se creará automáticamente</li>
                                <li><strong>Nota:</strong> Solo se procesará la primera hoja. Las otras hojas son solo de referencia.</li>
                                <li><strong>Ventaja:</strong> No necesitas conocer los códigos, solo los nombres. El sistema los detecta o crea automáticamente.</li>
                                <li><strong>Datos de ejemplo:</strong> La plantilla incluye una fila de ejemplo. Si no quieres importarla, elimínala antes de importar. Si la dejas, se procesará normalmente.</li>
                            </ul>
                        </div>

                        <div class="ucrud-form__actions">
                            <a href="{{ route('items.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                            <button type="submit" class="ucrud-btn ucrud-btn--primary" id="btnImportar">
                                <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" />
                                Importar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ucrud-panel">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Plantilla Excel</h2>
                    <p class="text-muted small">
                        Descarga la plantilla Excel con el formato correcto y ejemplos de datos.
                    </p>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="incluir_componentes" id="incluir_componentes" value="1">
                            <label class="form-check-label" for="incluir_componentes">
                                Incluir hoja de componentes
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            Si está marcado, se incluirá una hoja adicional con los componentes actuales (ID, nombre, método, matriz y precio).
                        </small>
                    </div>
                    <a href="{{ route('items.descargar-plantilla') }}" class="ucrud-btn ucrud-btn--outline-primary w-100" id="btnDescargarPlantilla">
                        <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                        Descargar plantilla
                    </a>
                </div>
            </div>

            <div class="ucrud-panel mt-3">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Formato de columnas</h2>
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Columna</th>
                                <th>Requerido</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>Tipo</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Agrupador</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Parámetro</code></td>
                                <td><span class="badge bg-danger">Sí</span></td>
                            </tr>
                            <tr>
                                <td><code>Metodología muestreo</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Metodología análisis</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Unidades de medición</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Límite de detección</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Límite de cuantificación</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                            <tr>
                                <td><code>Precio de venta</code></td>
                                <td><span class="badge bg-secondary">No</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <small class="text-muted d-block mt-2">
                        <strong>Tipo:</strong> Nombre de la matriz (se crea automáticamente si no existe)<br>
                        <strong>Agrupador:</strong> Uno o más nombres separados por punto y coma (se crean automáticamente si no existen)<br>
                        <strong>Parámetro:</strong> Nombre del componente (se crea automáticamente si no existe)<br>
                        <strong>Metodologías:</strong> Nombre completo del método (se crea automáticamente si no existe)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const flash = document.getElementById('flash-success');
        if (flash && flash.dataset.message) {
            Swal.fire({
                icon: 'success',
                title: 'Éxito',
                text: flash.dataset.message,
                timer: 3000,
                showConfirmButton: false
            });
        }

        const flashWarning = document.getElementById('flash-warning');
        if (flashWarning && flashWarning.dataset.message) {
            Swal.fire({
                icon: 'warning',
                title: 'Advertencia',
                text: flashWarning.dataset.message,
                timer: 4000,
                showConfirmButton: true
            });
        }

        document.getElementById('formImportar').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;
            const archivo = document.getElementById('archivo').files[0];

            if (!archivo) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Por favor selecciona un archivo.'
                });
                return;
            }

            if (archivo.size > 10 * 1024 * 1024) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El archivo es demasiado grande. El tamaño máximo es 10MB.'
                });
                return;
            }

            const actualizarExistentes = document.getElementById('actualizar_existentes').checked;
            const confirmHtml = actualizarExistentes
                ? '<p class="mb-2 text-start">Vas a <strong>actualizar el catálogo</strong> reutilizando parámetros que ya existan cuando coincidan nombre, matriz (Tipo) y metodologías.</p>'
                    + '<ul class="text-start small mb-0 ps-3">'
                    + '<li>Se pueden modificar <strong>unidades</strong>, <strong>límites</strong>, <strong>precios</strong> y métodos en los ítems encontrados.</li>'
                    + '<li>Se crearán agrupadores o parámetros <strong>solo si no hay coincidencia</strong>.</li>'
                    + '<li>No se eliminan ítems viejos del catálogo.</li>'
                    + '<li>Solo se procesa la <strong>primera hoja</strong> del Excel.</li>'
                    + '</ul>'
                : '<p class="mb-2 text-start">Vas a importar en modo <strong>solo altas</strong>: no se actualizarán determinaciones existentes.</p>'
                    + '<ul class="text-start small mb-0 ps-3">'
                    + '<li>Cada fila puede generar <strong>nuevos ítems</strong>, aunque ya exista uno similar (riesgo de duplicados).</li>'
                    + '<li>Los vínculos agrupador ↔ parámetro se crearán según el archivo.</li>'
                    + '<li>No se eliminan ítems del catálogo.</li>'
                    + '<li>Solo se procesa la <strong>primera hoja</strong> del Excel.</li>'
                    + '</ul>';

            const confirmacion = await Swal.fire({
                icon: 'warning',
                title: '¿Confirmar importación?',
                html: confirmHtml,
                showCancelButton: true,
                confirmButtonText: 'Sí, importar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: true,
            });

            if (!confirmacion.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Importando...',
                text: 'Por favor espera mientras se procesa el archivo.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            form.submit();
        });

        // Manejar descarga de plantilla con checkbox
        document.getElementById('btnDescargarPlantilla').addEventListener('click', function(e) {
            e.preventDefault();
            const incluirComponentes = document.getElementById('incluir_componentes').checked;
            const url = '{{ route("items.descargar-plantilla") }}' + (incluirComponentes ? '?incluir_componentes=1' : '');
            window.location.href = url;
        });
    });
    </script>
</div>
@endsection

