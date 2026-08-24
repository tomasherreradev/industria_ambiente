@extends('layouts.app')

<head>
    <title>Ver Tarea</title>
</head>

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Detalles de la Tarea</h1>
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información de la Tarea</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Descripción</h6>
                        <p class="fs-5 fw-bold">{{ $tarea->cotio_descripcion ?? 'N/A' }}</p>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Fecha Límite</h6>
                        <p class="fs-5">{{ $tarea->cotio_fecha_fin ?? 'N/A' }}</p>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Vehiculo</h6>
                        @php
                            $vehiculo = $tarea->instancias->first() ? $tarea->instancias->first()->vehiculo : null;
                        @endphp
                        <p class="fs-5">{{ $vehiculo->marca ?? 'N/A' }} {{ $vehiculo->modelo ?? 'N/A' }} ({{ $vehiculo->patente ?? 'N/A' }})</p>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Herramientas Asignadas</h6>
                        @if($tarea->herramientas->count() > 0)
                            <ul class="list-group">
                                @foreach($tarea->herramientas as $herramienta)
                                    <li class="list-group-item">
                                        {{ $herramienta->equipamiento }} ({{ $herramienta->marca_modelo }})
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted">No hay herramientas asignadas</p>
                        @endif
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Estado Actual</h6>
                        <span class="badge bg-{{ 
                            $tarea->cotio_estado === 'finalizado' ? 'success' : 
                            ($tarea->cotio_estado === 'en proceso' ? 'info' : 'warning') 
                        }} fs-6">
                            {{ ucfirst($tarea->cotio_estado) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Actualizar Tarea</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('tareas.updateResultado', [$tarea->cotio_numcoti, $tarea->cotio_item, $tarea->cotio_subitem]) }}" id="resultadoForm">
                        @csrf
                        @method('PUT')
                        
                        <input type="hidden" name="nuevo_estado" id="nuevoEstado">
                        
                        <div class="mb-4">
                            <label for="resultado" class="form-label fw-bold">Subir resultado</label>
                            <input type="text" class="form-control" id="resultado" value="{{ $tarea->resultado ?? '' }}" name="resultado" placeholder="Describe el resultado o actualización de la tarea" required>
                            <div class="form-text">Proporciona los resultados obtenidos.</div>
                        </div>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-undo me-1"></i> Limpiar
                            </button>
                            <button type="button" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-1"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>



<script>
   document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('resultadoForm');
        const submitBtn = document.getElementById('submitBtn');
        const estadoActual = "{{ strtolower($tarea->cotio_estado) }}";
        
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const resultado = document.getElementById('resultado').value.trim();
            if (!resultado) {
                Swal.fire({
                    icon: 'error',
                    title: 'Campo requerido',
                    text: 'Por favor ingresa un resultado antes de enviar',
                    confirmButtonColor: '#3085d6',
                });
                return;
            }
            
            Swal.fire({
                title: '¿Deseas marcar la tarea como "En Proceso"?',
                text: 'Estado actual: ' + estadoActual.toUpperCase(),
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, marcar como "En Proceso"',
                cancelButtonText: 'No, solo guardar resultado',
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('nuevoEstado').value = 'en proceso';
                } else {
                    document.getElementById('nuevoEstado').value = '';
                }
                form.submit();
            });
        });
    });
    </script>



@endsection