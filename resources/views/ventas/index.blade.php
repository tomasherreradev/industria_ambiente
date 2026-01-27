@extends('layouts.app')

@section('content')

<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<style>
.dashboard-header {
    background-color: #0d6efd;
    color: white;
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.stats-card {
    border: none;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.stats-card.active {
    border: 2px solid #0d6efd;
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(13, 110, 253, 0.1) 100%);
}

.stats-card-monto {
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: default;
}

.stats-card-monto:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
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

.estado-E { background: #ffc107; color: #000; }
.estado-A { background: #28a745; color: #fff; }
.estado-R { background: #dc3545; color: #fff; }
.estado-P { background: #17a2b8; color: #fff; }

.badge-counter {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    min-width: 2rem;
    text-align: center;
}

.search-filter-bar {
    padding: 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.action-buttons {
    white-space: nowrap;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.filter-active {
    background: #e7f3ff;
    padding: 0.5rem;
    border-radius: 5px;
}

@media (max-width: 768px) {
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .row.g-2 > div {
        margin-bottom: 0.5rem;
    }
}
</style>

@php
// Estadísticas (se calcularán en el controlador)
$totalCotizaciones = \App\Models\Ventas::count();
$enEspera = \App\Models\Ventas::where('coti_estado', 'LIKE', 'E%')->count();
$aprobadas = \App\Models\Ventas::where('coti_estado', 'LIKE', 'A%')->count();
$enProceso = \App\Models\Ventas::where('coti_estado', 'LIKE', 'P%')->count();
$rechazadas = \App\Models\Ventas::where('coti_estado', 'LIKE', 'R%')->count();
@endphp

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1 class="mb-1"><x-heroicon-o-chart-bar class="me-2" style="width: 16px; height: 16px;" />Dashboard de Cotizaciones</h1>
                <p class="mb-0 opacity-75">Gestión y análisis de cotizaciones</p>
            </div>
            <a href="{{ route('ventas.create') }}" class="btn btn-light btn-lg" style="font-size: 14px;">
                <x-heroicon-o-plus class="me-2" style="width: 16px; height: 16px;" /> Nueva Cotización
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card {{ !request('estado') ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('')" 
                 data-estado="">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total</h6>
                            <h3 class="mb-0 text-primary">{{ number_format($totalCotizaciones) }}</h3>
                        </div>
                        <div class="stats-icon text-primary">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card {{ request('estado') == 'E' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('E')" 
                 data-estado="E">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">En Espera</h6>
                            <h3 class="mb-0 text-warning">{{ number_format($enEspera) }}</h3>
                        </div>
                        <div class="stats-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card {{ request('estado') == 'A' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('A')" 
                 data-estado="A">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Aprobadas</h6>
                            <h3 class="mb-0 text-success">{{ number_format($aprobadas) }}</h3>
                        </div>
                        <div class="stats-icon text-success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card stats-card {{ request('estado') == 'R' ? 'active' : '' }}" 
                 onclick="filtrarPorEstado('R')" 
                 data-estado="R">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Rechazadas</h6>
                            <h3 class="mb-0 text-danger">{{ number_format($rechazadas) }}</h3>
                        </div>
                        <div class="stats-icon text-danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Monto (no clickeable) -->
        <div class="col-lg-4 col-md-8 col-sm-12 mb-3">
            <div class="card stats-card-monto bg-white text-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-2 opacity-75">Monto Total</h6>
                            <h2 class="mb-0 fw-bold" style="font-size: 26px;">${{ number_format($montoMostrar, 2, ',', '.') }}</h2>
                        </div>
                        <div class="stats-icon" style="opacity: 0.3;">
                            <i class="fas fa-dollar-sign" style="font-size: 3rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Cotizaciones -->
    <div class="table-container">
        <div class="table-header">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Cotizaciones</h5>
                    @if(request()->hasAny(['cliente', 'estado', 'fecha_desde', 'fecha_hasta']))
                        <small class="text-muted">
                            <i class="fas fa-filter me-1"></i>Filtros activos
                        </small>
                    @endif
                </div>
                
                <!-- Filtros -->
                <form method="GET" action="{{ route('ventas.index') }}" id="filterForm">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Cliente</label>
                            <select name="cliente" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos los clientes</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->cli_codigo }}" {{ request('cliente') == $cliente->cli_codigo ? 'selected' : '' }}>
                                        {{ trim($cliente->cli_codigo) }} - {{ trim($cliente->cli_razonsocial) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Estado</label>
                            <select name="estado" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">Todos los estados</option>
                                <option value="E" {{ request('estado') == 'E' ? 'selected' : '' }}>En Espera</option>
                                <option value="A" {{ request('estado') == 'A' ? 'selected' : '' }}>Aprobado</option>
                                <option value="P" {{ request('estado') == 'P' ? 'selected' : '' }}>En Proceso</option>
                                <option value="R" {{ request('estado') == 'R' ? 'selected' : '' }}>Rechazado</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Fecha Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ request('fecha_desde') }}" onchange="this.form.submit()">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ request('fecha_hasta') }}" onchange="this.form.submit()">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" onclick="limpiarFiltros()" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-times me-1"></i>Limpiar
                            </button>
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="searchInput" class="form-control" placeholder="Buscar..." value="{{ request('search') }}">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Empresa</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cotizaciones as $cotizacion)
                    <tr>
                        <td><strong>#{{ $cotizacion->coti_num }}@if($cotizacion->coti_version != 1).{{ $cotizacion->coti_version ?? 1 }} @endif</strong></td>
                        <td>{{ Str::limit($cotizacion->coti_descripcion, 50) ?: 'Sin descripción' }}</td>
                        <td>{{ Str::limit($cotizacion->coti_empresa, 30) ?: '-' }}</td>
                        <td>
                            @php
                                $estado = trim($cotizacion->coti_estado);
                                $estadoClass = 'estado-E';
                                $estadoTexto = 'En Espera';
                                
                                if($estado && $estado[0] == 'A') {
                                    $estadoClass = 'estado-A';
                                    $estadoTexto = 'Aprobado';
                                } elseif($estado && $estado[0] == 'R') {
                                    $estadoClass = 'estado-R';
                                    $estadoTexto = 'Rechazado';
                                } elseif($estado && $estado[0] == 'P') {
                                    $estadoClass = 'estado-P';
                                    $estadoTexto = 'En Proceso';
                                }
                            @endphp
                            <span class="badge {{ $estadoClass }} badge-estado">{{ $estadoTexto }}</span>
                        </td>
                        <td>{{ $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('d/m/Y') : '-' }}</td>
                        <td class="text-end action-buttons">
                            <a href="{{ route('cotizaciones.ver-detalle', $cotizacion->coti_num) }}" class="btn btn-sm btn-outline-info" title="Ver">
                                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                            </a>
                            <a href="{{ route('ventas.edit', $cotizacion->coti_num) }}" class="btn btn-sm btn-outline-primary" title="Ver/Editar">
                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                            </a>
                            <button type="button" 
                               class="btn btn-sm btn-outline-danger" 
                               onclick="confirmarEliminacion({{ $cotizacion->coti_num }})"
                               title="Eliminar">
                                <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    
                    @if($cotizaciones->isEmpty())
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <x-heroicon-o-document-text class="me-2" style="width: 32px; height: 32px; color: #6c757d;" />
                            <p class="text-muted">No hay cotizaciones registradas</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($cotizaciones->hasPages())
        <div class="p-3 border-top">
            {{ $cotizaciones->links() }}
        </div>
        @endif
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Función para filtrar por estado desde las tarjetas de estadísticas
function filtrarPorEstado(estado) {
    const url = new URL(window.location.href);
    const baseUrl = url.origin + url.pathname;
    
    // Construir parámetros de consulta manteniendo otros filtros
    const params = new URLSearchParams();
    
    // Mantener filtros existentes (excepto estado)
    if (url.searchParams.get('cliente')) {
        params.set('cliente', url.searchParams.get('cliente'));
    }
    if (url.searchParams.get('fecha_desde')) {
        params.set('fecha_desde', url.searchParams.get('fecha_desde'));
    }
    if (url.searchParams.get('fecha_hasta')) {
        params.set('fecha_hasta', url.searchParams.get('fecha_hasta'));
    }
    if (url.searchParams.get('search')) {
        params.set('search', url.searchParams.get('search'));
    }
    
    // Agregar o quitar el filtro de estado
    if (estado) {
        params.set('estado', estado);
    }
    
    // Construir URL final
    const finalUrl = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    
    // Redirigir
    window.location.href = finalUrl;
}

// Función para limpiar filtros
function limpiarFiltros() {
    window.location.href = '{{ route("ventas.index") }}';
}

// Búsqueda simple en tabla (filtra resultados visibles)
document.getElementById('searchInput').addEventListener('keyup', function() {
    const search = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
});

// Mostrar contador de resultados
function actualizarContador() {
    const filasVisibles = document.querySelectorAll('tbody tr:not([style*="display: none"])').length;
    const total = {{ $cotizaciones->count() }};
    // Puedes agregar un contador visual aquí si lo necesitas
}

// Llamar después de la búsqueda
document.getElementById('searchInput').addEventListener('keyup', actualizarContador);

// Función para confirmar eliminación con SweetAlert
function confirmarEliminacion(cotiNum) {
    Swal.fire({
        title: '¿Está seguro?',
        text: 'Esta acción eliminará la cotización. No se puede deshacer.',
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
            form.action = `/ventas/${cotiNum}`;
            
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

// Notificaciones de sesión (para crear/editar)
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