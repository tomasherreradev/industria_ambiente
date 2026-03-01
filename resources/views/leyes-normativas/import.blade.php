@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Importar Leyes y Normativas</h2>
        <a href="{{ route('leyes-normativas.index') }}" class="btn btn-outline-secondary">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" /> Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-upload me-2"></i>Importación Masiva de Leyes y Normativas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                        <ol class="mb-0">
                            <li>Descarga la plantilla Excel haciendo clic en el botón "Descargar Plantilla"</li>
                            <li>La plantilla incluye 4 hojas: "Datos" (para completar), "Leyes Existentes", "Métodos" y "Matrices" (referencias)</li>
                            <li>Completa la hoja <strong>"Datos"</strong> con la información de las leyes y sus variables (no cambies el nombre de esa hoja; el import solo lee la hoja llamada "Datos")</li>
                            <li>Para Matriz y Método puedes usar el código o el nombre (consulta las hojas de referencia)</li>
                            <li>Si no especificas Matriz ni Método, la ley se aplicará a todos los items con ese nombre de analito</li>
                            <li>Sube el archivo completado usando el formulario a continuación</li>
                        </ol>
                    </div>

                    <form method="POST" action="{{ route('leyes-normativas.import.process') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="archivo" class="form-label">
                                Archivo Excel <span class="text-danger">*</span>
                            </label>
                            <input type="file" 
                                   class="form-control @error('archivo') is-invalid @enderror" 
                                   id="archivo" 
                                   name="archivo" 
                                   accept=".xlsx,.xls"
                                   required>
                            @error('archivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Formatos aceptados: .xlsx, .xls (máximo 10MB)
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('leyes-normativas.export.template') }}" class="btn btn-outline-success">
                                <i class="fas fa-download me-2"></i>Descargar Plantilla
                            </a>
                            <div>
                                <a href="{{ route('leyes-normativas.index') }}" class="btn btn-outline-secondary me-2">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i>Importar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Estructura de la Plantilla</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Columna</th>
                                    <th>Descripción</th>
                                    <th>Requerido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Analito (cotio_descripcion)</strong></td>
                                    <td>Nombre del analito/parámetro (ej: pH, DBO, etc.)</td>
                                    <td><span class="badge bg-danger">Sí</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Matriz (opcional)</strong></td>
                                    <td>Código o nombre de matriz para filtrar items específicos. Si se deja vacío, aplica a todos los items con ese nombre. Puede consultar la hoja "Matrices" en la plantilla.</td>
                                    <td><span class="badge bg-secondary">No</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Método (opcional)</strong></td>
                                    <td>Código o nombre de método para filtrar items específicos. Puede consultar la hoja "Métodos" en la plantilla.</td>
                                    <td><span class="badge bg-secondary">No</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre de la Ley</strong></td>
                                    <td>Nombre de la ley/normativa a crear o actualizar</td>
                                    <td><span class="badge bg-danger">Sí</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Unidad de medida</strong></td>
                                    <td>Unidad de medida para el valor límite (ej: UpH, mg/L, etc.)</td>
                                    <td><span class="badge bg-warning">Recomendado</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Valor límite</strong></td>
                                    <td>Valor límite para la variable en esta ley (ej: 6.5-8.5, <10, etc.)</td>
                                    <td><span class="badge bg-secondary">No</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

