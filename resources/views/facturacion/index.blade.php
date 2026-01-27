@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Facturación</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary stats-card-facturacion {{ !request('tipo_filtro') || request('tipo_filtro') == 'total' ? 'active' : '' }}" 
                 onclick="filtrarPorTipo('total')" 
                 style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" 
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)'"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.1)'">
                <div class="card-body">
                    <h5 class="card-title">Total Facturas</h5>
                    <h3 class="card-text">{{ $estadisticas['total_facturas'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-warning stats-card-facturacion {{ request('tipo_filtro') == 'pendientes' ? 'active' : '' }}" 
                 onclick="filtrarPorTipo('pendientes')" 
                 style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" 
                 onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)'"
                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.1)'">
                <div class="card-body">
                    <h5 class="card-title">Pendientes</h5>
                    <h3 class="card-text">{{ $estadisticas['facturas_pendientes'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title">Monto Total</h5>
                    <h3 class="card-text">${{ number_format($estadisticas['monto_total'], 2, ',', '.') }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filtros</h5>
        </div>
        <div class="card-body">
                <form method="GET" action="{{ route('facturacion.index') }}" id="filterFormFacturacion">
                    @if(request('tipo_filtro'))
                        <input type="hidden" name="tipo_filtro" value="{{ request('tipo_filtro') }}">
                    @endif
                    <div class="row">
                        <div class="col-md-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select name="estado" id="estado" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendiente" {{ $request->estado == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                                <option value="aprobada" {{ $request->estado == 'aprobada' ? 'selected' : '' }}>Aprobada</option>
                                <option value="rechazada" {{ $request->estado == 'rechazada' ? 'selected' : '' }}>Rechazada</option>
                                <option value="anulada" {{ $request->estado == 'anulada' ? 'selected' : '' }}>Anulada</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="cotizacion" class="form-label">Cotización</label>
                            <input type="number" name="cotizacion" id="cotizacion" class="form-control" 
                                   value="{{ $request->cotizacion }}" placeholder="Núm. Cotización">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" 
                                   value="{{ $request->fecha_desde }}">
                        </div>
                        <div class="col-md-3">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" 
                                   value="{{ $request->fecha_hasta }}">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">Filtrar</button>
                        </div>
                    </div>
                </form>
        </div>
    </div>


    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Muestras a Facturar</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Cotización</th>
                            <th>Cliente</th>
                            <th width="150" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($informesPorCotizacion as $numCoti => $informeData)
                            @php
                                $coti = $informeData['cotizacion'];
                            @endphp
                            <tr>
                                <td colspan="4">
                                    <div class="accordion" id="accordion-{{ $numCoti }}">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading-{{ $numCoti }}">
                                                <div class="d-flex w-100 align-items-center justify-content-between">
                                                    <button class="accordion-button collapsed flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $numCoti }}" aria-expanded="false" aria-controls="collapse-{{ $numCoti }}">
                                                        Cotización #{{ $numCoti }} - {{ $coti->coti_empresa ?? 'N/A' }} ({{ $informeData['muestras']->count() }} muestras)
                                                    </button>
                                                    <a href="{{ route('facturacion.facturar', ['cotizacion' => $numCoti]) }}" class="btn btn-sm btn-outline-primary" style="display: flex; align-items: center; gap: 5px; margin-left: 10px;">
                                                        <x-heroicon-o-currency-dollar style="width: 15px; height: 15px;" />
                                                        Facturar
                                                    </a>
                                                </div>
                                            </h2>
                                            <div id="collapse-{{ $numCoti }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $numCoti }}" data-bs-parent="#accordion-{{ $numCoti }}">
                                                <div class="accordion-body p-0">
                                                    <table class="table mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Identificación</th>
                                                                <th>Descripción</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($informeData['muestras'] as $muestra)
                                                                <tr>
                                                                    <td>{{ $muestra->cotio_identificacion }}</td>
                                                                    <td>{{ $muestra->cotio_descripcion }} (#{{ $muestra->instance_number }})
                                                                        @if($muestra->facturado)
                                                                            <x-heroicon-o-check-circle style="width: 18px; height: 18px; color: green;" />
                                                                            <span class="badge bg-success">Facturada</span>
                                                                        @else
                                                                            <x-heroicon-o-x-circle style="width: 18px; height: 18px; color: red;" />
                                                                            <span class="badge bg-danger">No Facturada</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Tabla de Facturas -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <h5 class="mb-0">Facturas Generadas</h5>
        </div>
        <div class="card-body">
            @if($facturas->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Número Factura</th>
                                <th>Cotización</th>
                                <th>Cliente</th>
                                <th>Muestra</th>
                                <th>CUIT</th>
                                <th>CAE</th>
                                <th>Fecha Emisión</th>
                                <th>Fecha Venc. CAE</th>
                                <th>Monto Total</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($facturas as $factura)
                                <tr>
                                    <td>{{ $factura->id }}</td>
                                    <td><strong>{{ $factura->numero_factura }}</strong></td>
                                    <td>
                                        @if($factura->cotizacion)
                                            <a href="{{ route('facturacion.show', $factura->cotizacion_id) }}" 
                                               class="btn btn-sm btn-outline-primary">
                                                #{{ $factura->cotizacion_id }}
                                            </a>
                                        @else
                                            {{ $factura->cotizacion_id }}
                                        @endif
                                    </td>
                                    <td>{{ $factura->cliente_razon_social ?? 'N/A' }}</td>
                                    <td>{{ $factura->cotio_descripcion ?? 'N/A' }} {{ $factura->instance_number ?? '' }}</td>
                                    <td>{{ $factura->cliente_cuit ?? 'N/A' }}</td>
                                    <td><small>{{ $factura->cae }}</small></td>
                                    <td>{{ $factura->fecha_emision->format('d/m/Y H:i') }}</td>
                                    <td>{{ $factura->fecha_vencimiento_cae ? \Carbon\Carbon::parse($factura->fecha_vencimiento_cae)->format('d/m/Y') : 'N/A' }}</td>
                                    <td><strong>${{ number_format($factura->monto_total, 2, ',', '.') }}</strong></td>
                                    <td>
                                        <div class="d-flex justify-content-center align-items-center" style="width: 100%; height: 30px;">
                                            <a href="{{ route('facturacion.ver', $factura->id) }}" 
                                            class="d-flex justify-content-center align-items-center" style="width: 100%; height: 30px;" title="Ver Detalle">
                                                <x-heroicon-o-eye style="width: 18px; height: 18px; margin: 0 auto;" class="text-center" />
                                            </a>

                                            <a href="{{ route('facturacion.descargar', $factura->id) }}" 
                                                class="d-flex justify-content-center align-items-center descargar-pdf" 
                                                style="width: 100%; height: 30px;" 
                                                title="Descargar PDF"
                                                data-factura-id="{{ $factura->id }}"
                                                target="_blank">
                                                
                                                @if($factura->pdf_url)
                                                 <x-heroicon-o-arrow-down-tray style="width: 18px; height: 18px; margin: 0 auto;" 
                                                     class="text-center" />
                                                @else
                                                    <x-heroicon-o-arrow-down-tray style="width: 18px; height: 18px; margin: 0 auto;" 
                                                    class="text-center text-muted" />
                                                @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $facturas->appends(request()->query())->links() }}
                </div>
            @else
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    No hay facturas aún.
                </div>
            @endif
        </div>
    </div>
</div>




<style>
    #informePreviewModal .modal-xl {
        max-width: 95%;
    }
    #variablesTable input {
        min-width: 100px;
    }
    .card-header h6 {
        font-size: 1.1rem;
        font-weight: 500;
    }
    .form-label {
        font-weight: 500;
    }
    #mapa {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }
    .nav-tabs .nav-link {
        color: #495057;
    }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        font-weight: 500;
    }
    .table th {
        font-weight: 500;
    }
    .badge {
        font-size: 0.9rem;
        padding: 0.5em 1em;
    }
    .stats-card-facturacion.active {
        border: 2px solid #fff;
        box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
    }
</style>


<script>
// Función para filtrar por tipo (total o pendientes)
function filtrarPorTipo(tipo) {
    const url = new URL(window.location.href);
    const baseUrl = url.origin + url.pathname;
    
    // Construir parámetros de consulta manteniendo otros filtros
    const params = new URLSearchParams();
    
    // Mantener filtros existentes
    if (url.searchParams.get('estado')) {
        params.set('estado', url.searchParams.get('estado'));
    }
    if (url.searchParams.get('cotizacion')) {
        params.set('cotizacion', url.searchParams.get('cotizacion'));
    }
    if (url.searchParams.get('fecha_desde')) {
        params.set('fecha_desde', url.searchParams.get('fecha_desde'));
    }
    if (url.searchParams.get('fecha_hasta')) {
        params.set('fecha_hasta', url.searchParams.get('fecha_hasta'));
    }
    
    // Agregar o quitar el filtro de tipo
    if (tipo && tipo !== 'total') {
        params.set('tipo_filtro', tipo);
    }
    // Si es 'total', no agregamos el parámetro (o lo quitamos si existe)
    
    // Construir URL final
    const finalUrl = params.toString() ? `${baseUrl}?${params.toString()}` : baseUrl;
    
    // Redirigir
    window.location.href = finalUrl;
}

// Manejar descarga de PDFs con loading
document.querySelectorAll('.descargar-pdf').forEach(link => {
    link.addEventListener('click', function(e) {
        const icon = this.querySelector('svg');
        
        // Mostrar loading
        if (icon) {
            icon.style.opacity = '0.5';
        }
        
        // Restaurar después de un tiempo
        setTimeout(() => {
            if (icon) {
                icon.style.opacity = '1';
            }
        }, 3000);
    });
});
</script>

@endsection