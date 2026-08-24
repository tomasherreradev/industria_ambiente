@extends('layouts.app')

@section('content')

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.dashboard-header {
    background-color: #28a745;
    color: white;
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.stats-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.table-container {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table-header {
    background: #f8f9fa;
    padding: 1rem;
    border-bottom: 2px solid #dee2e6;
}

.badge-estado {
    padding: 0.35rem 0.65rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.estado-activo {
    background: #28a745;
    color: #fff;
}

.estado-inactivo {
    background: #dc3545;
    color: #fff;
}

.action-buttons {
    white-space: nowrap;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 1rem;
    }
}
</style>

@php
// Estadísticas
$totalClientes = \App\Models\Clientes::count();
$activos = \App\Models\Clientes::where('cli_estado', true)->count();
$inactivos = \App\Models\Clientes::where('cli_estado', false)->count();
$readOnly = function_exists('userHasRole') ? userHasRole('facturador') : (strtolower(trim((string) (optional(Auth::user())->rol ?? ''))) === 'facturador');
@endphp

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="mb-1"><x-heroicon-o-users class="me-2" style="width: 16px; height: 16px;" />Dashboard de Clientes</h1>
                <p class="mb-0 opacity-75">Gestión y análisis de clientes</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @if(!$readOnly)
                    <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#importModal" style="font-size: 14px;">
                        <x-heroicon-o-arrow-up-tray class="me-2" style="width: 16px; height: 16px;" />Importar
                    </button>
                    <a href="{{ route('clientes.plantilla') }}" class="btn btn-outline-light" style="font-size: 14px;">
                        <x-heroicon-o-arrow-down-tray class="me-2" style="width: 16px; height: 16px;" />Plantilla vacía
                    </a>
                    <a href="{{ route('clientes.plantilla-pendientes') }}" class="btn btn-outline-light" style="font-size: 14px;">
                        <x-heroicon-o-arrow-down-tray class="me-2" style="width: 16px; height: 16px;" />Plantilla pendientes
                    </a>
                    <a href="{{ route('clientes.create') }}" class="btn btn-light" style="font-size: 14px;">
                        <x-heroicon-o-plus class="me-2" style="width: 16px; height: 16px;" />Nuevo Cliente
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de Importación -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('clientes.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="importModalLabel">Importar Clientes</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Seleccione el archivo Excel (.xlsx, .xls) o CSV con los datos de los clientes.</p>
                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo</label>
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".xlsx, .xls, .csv" required>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <x-heroicon-o-information-circle style="width: 16px; height: 16px;" class="me-1" />
                            Use la plantilla vacía o la de pendientes (con clientes del listado precargados) para asegurar el formato correcto.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Procesar Importación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total</h6>
                            <h3 class="mb-0 text-success">{{ number_format($totalClientes) }}</h3>
                        </div>
                        <div class="stats-icon text-success">
                            <x-heroicon-o-users class="me-2" style="width: 16px; height: 16px;" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Activos</h6>
                            <h3 class="mb-0 text-success">{{ number_format($activos) }}</h3>
                        </div>
                        <div class="stats-icon text-success">
                            <x-heroicon-o-check-circle class="me-2" style="width: 16px; height: 16px;" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Inactivos</h6>
                            <h3 class="mb-0 text-danger">{{ number_format($inactivos) }}</h3>
                        </div>
                        <div class="stats-icon text-danger">
                            <x-heroicon-o-x-mark class="me-2" style="width: 16px; height: 16px;" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Clientes -->
    <div class="table-container">
        <div class="table-header">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><x-heroicon-o-list-bullet class="me-2" style="width: 16px; height: 16px;" />Clientes</h5>
                    @if(request()->hasAny(['search', 'estado']))
                        <small class="text-muted">
                            <x-heroicon-o-funnel class="me-1" style="width: 16px; height: 16px;" />Filtros activos
                        </small>
                    @endif
                </div>
                
                <!-- Filtros -->
                <form method="GET" action="{{ route('clientes.index') }}" id="filterForm">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Buscar</label>
                            <div class="input-group">
                                <span class="input-group-text"><x-heroicon-o-magnifying-glass class="me-2" style="width: 16px; height: 16px;" /></span>
                                <input type="text" name="search" class="form-control form-control-sm" 
                                       value="{{ request('search') }}" 
                                       placeholder="Código, razón social, CUIT...">
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Estado</label>
                            <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                <option value="1" {{ request('estado') == '1' ? 'selected' : '' }}>Activos</option>
                                <option value="0" {{ request('estado') == '0' ? 'selected' : '' }}>Inactivos</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" onclick="limpiarFiltros()" class="btn btn-sm btn-outline-secondary w-100">
                                <x-heroicon-o-x-mark class="me-1" style="width: 16px; height: 16px;" />Limpiar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Razón Social</th>
                        <th>Localidad</th>
                        <th>CUIT</th>
                        <th>Cotizaciones</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clientes as $cliente)
                    <tr>
                        <td><strong>{{ trim($cliente->cli_codigo) }}</strong></td>
                        <td>{{ Str::limit($cliente->cli_razonsocial, 40) ?: '-' }}</td>
                        <td>{{ Str::limit($cliente->cli_localidad, 30) ?: '-' }}</td>
                        <td>{{ $cliente->cli_cuit ?: '-' }}</td>
                        <td>
                            @php
                                $totalCotizaciones = \App\Models\Ventas::where('coti_codigocli', 'LIKE', trim($cliente->cli_codigo) . '%')->count();
                            @endphp
                            <a href="{{ route('ventas.index', ['cliente' => trim($cliente->cli_codigo)]) }}" 
                               class="badge bg-info text-decoration-none">
                                {{ $totalCotizaciones }}
                            </a>
                        </td>
                        <td>
                            @if($cliente->cli_estado)
                                <span class="badge estado-activo badge-estado">Activo</span>
                            @else
                                <span class="badge estado-inactivo badge-estado">Inactivo</span>
                            @endif
                        </td>
                        <td class="text-end action-buttons">
                            <a href="{{ route('clientes.edit', trim($cliente->cli_codigo)) }}" class="btn btn-sm btn-outline-primary" title="{{ $readOnly ? 'Ver' : 'Ver/Editar' }}">
                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                            </a>
                            <a href="{{ route('ventas.index', ['cliente' => trim($cliente->cli_codigo)]) }}" class="btn btn-sm btn-outline-info" title="Ver Cotizaciones">
                                <x-heroicon-o-document-text style="width: 16px; height: 16px;" />
                            </a>
                            @if(!$readOnly)
                                <button type="button" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="confirmarEliminacion('{{ trim($cliente->cli_codigo) }}')"
                                   title="Eliminar">
                                    <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    
                    @if($clientes->isEmpty())
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <x-heroicon-o-users class="me-2" style="width: 32px; height: 32px; color: #6c757d;" />
                            <p class="text-muted">No hay clientes registrados</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($clientes->hasPages())
        <div class="p-3 border-top">
            {{ $clientes->links() }}
        </div>
        @endif
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Función para limpiar filtros
function limpiarFiltros() {
    window.location.href = '{{ route("clientes.index") }}';
}

// Función para confirmar eliminación con SweetAlert
function confirmarEliminacion(codigo) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción eliminará el cliente. No se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            confirmButton: 'btn btn-danger mx-2',
            cancelButton: 'btn btn-secondary mx-2'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Crear formulario para enviar DELETE
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/clientes/${codigo}`;
            
            // Agregar token CSRF
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            // Agregar método DELETE
            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            form.appendChild(method);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Notificaciones de sesión
@if(session('success'))
    Swal.fire({
        icon: 'success',
        title: '¡Éxito!',
        text: '{{ session("success") }}',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
@endif

@if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '{{ session("error") }}',
        confirmButtonColor: '#dc3545'
    });
@endif

@if(session('warning'))
    Swal.fire({
        icon: 'warning',
        title: 'Atención',
        text: '{{ session("warning") }}',
        confirmButtonColor: '#ffc107'
    });
@endif
</script>

@endsection
