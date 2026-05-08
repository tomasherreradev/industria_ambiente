@extends('layouts.app')

@section('title', 'Vehículos')

@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0 fs-4">Gestión de Vehículos</h1>

            <div class="btn-group">
                <a href="{{ route('vehiculos.create') }}" class="btn btn-primary btn-sm d-lg-btn-md">
                    <x-heroicon-o-plus class="me-1" style="width: 16px; height: 16px;" />
                    Nuevo Vehículo
                </a>

                <button class="btn btn-outline-primary btn-sm d-lg-btn-md" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filtrosCollapse">
                    <x-heroicon-o-magnifying-glass class="me-1" style="width: 16px; height: 16px;" />
                    Buscar
                </button>

            </div>



        </div>

        <!-- Filtros desplegables -->
        <div class="collapse mb-4 @if(request()->hasAny(['marca', 'modelo', 'anio', 'patente', 'tipo', 'estado'])) show @endif"
            id="filtrosCollapse">
            <div class="card card-body">
                <form action="{{ url('/vehiculos') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="marca" class="form-label">Marca</label>
                        <input type="text" name="marca" id="marca" class="form-control" placeholder="Ej: Toyota"
                            value="{{ request('marca') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="modelo" class="form-label">Modelo</label>
                        <input type="text" name="modelo" id="modelo" class="form-control" placeholder="Ej: Hilux"
                            value="{{ request('modelo') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="anio" class="form-label">Año</label>
                        <input type="number" name="anio" id="anio" class="form-control" placeholder="Ej: 2020"
                            value="{{ request('anio') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="patente" class="form-label">Patente</label>
                        <input type="text" name="patente" id="patente" class="form-control" placeholder="Ej: AB1234"
                            value="{{ request('patente') }}">
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select name="estado" id="estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="libre" {{ request('estado') == 'libre' ? 'selected' : '' }}>Libre</option>
                            <option value="ocupado" {{ request('estado') == 'ocupado' ? 'selected' : '' }}>Ocupado</option>
                            <option value="mantenimiento" {{ request('estado') == 'mantenimiento' ? 'selected' : '' }}>
                                Mantenimiento</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Aplicar Filtros
                            </button>
                            <a href="{{ url('/vehiculos') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Limpiar
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        @if($vehiculos->isEmpty())
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No se encontraron vehículos con los criterios seleccionados.
            </div>
        @else
            <!-- Vista para pantallas grandes -->
            <div class="d-none d-lg-block">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Año</th>
                                <th>Patente</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Ultimo mantenimiento</th>
                                <th>Estado general</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vehiculos as $vehiculo)
                                <tr>
                                    <td>{{ $vehiculo->marca ?? 'N/A' }}</td>
                                    <td>{{ $vehiculo->modelo ?? 'N/A' }}</td>
                                    <td>{{ $vehiculo->anio ?? 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $vehiculo->patente }}</span>
                                    </td>
                                    <td>{{ $vehiculo->tipo ?? 'N/A' }}</td>
                                    <td>
                                        @if($vehiculo->estado === 'libre')
                                            <span class="badge bg-success">Libre</span>
                                        @elseif($vehiculo->estado === 'mantenimiento')
                                            <span class="badge bg-warning">Mantenimiento</span>
                                        @else
                                            @php
                                                $tareaActiva = $vehiculo->tareas->sortByDesc('created_at')->first();
                                            @endphp

                                            @if($tareaActiva)
                                                <span class="badge bg-danger" data-bs-toggle="tooltip" data-bs-placement="bottom"
                                                    title="Cotización: {{ $tareaActiva->cotio_numcoti }} - {{ $tareaActiva->cotio_descripcion }}">
                                                    Ocupado
                                                </span>
                                            @else
                                                <span class="badge bg-danger">Ocupado</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ $vehiculo->ultimo_mantenimiento ?? 'N/A' }}</td>
                                    <td>{{ $vehiculo->estado_gral ?? 'N/A' }}</td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('vehiculos.show', $vehiculo->id) }}"
                                                class="btn btn-sm btn-outline-primary" title="Editar">
                                                Editar
                                            </a>
                                            <form action="{{ route('vehiculos.destroy', $vehiculo->id) }}" method="POST"
                                                class="d-inline" onsubmit="return confirm('¿Eliminar este vehículo?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vista para móviles -->
            <div class="d-block d-lg-none">
                <div class="row g-3">
                    @foreach($vehiculos as $vehiculo)
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="card-title mb-1">
                                            {{ $vehiculo->marca }} {{ $vehiculo->modelo }} ({{ $vehiculo->anio }})
                                        </h5>
                                        <span class="badge bg-secondary">{{ $vehiculo->patente }}</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted">{{ $vehiculo->tipo }}</span>
                                        @if($vehiculo->estado === 'libre')
                                            <span class="badge bg-success">Libre</span>
                                        @elseif($vehiculo->estado === 'mantenimiento')
                                            <span class="badge bg-warning">Mantenimiento</span>
                                        @else
                                            <span class="badge bg-danger">Ocupado</span>
                                        @endif
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted">{{ $vehiculo->ultimo_mantenimiento }}</span>
                                        <span class="text-muted">{{ $vehiculo->estado_gral }}</span>
                                    </div>
                                    @if($vehiculo->estado === 'ocupado')
                                        @php
                                            $tareaActiva = $vehiculo->tareas->sortByDesc('created_at')->first();
                                        @endphp

                                        @if($tareaActiva)
                                            <div class="alert alert-warning py-2 mb-2">
                                                <small>
                                                    <strong>En uso:</strong> Cotización {{ $tareaActiva->cotio_numcoti }}<br>
                                                    {{ Str::limit($tareaActiva->cotio_descripcion, 50) }}
                                                </small>
                                            </div>
                                        @endif
                                    @endif

                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('vehiculos.show', $vehiculo->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>
                                        <form action="{{ route('vehiculos.destroy', $vehiculo->id) }}" method="POST"
                                            class="d-inline" onsubmit="return confirm('¿Eliminar este vehículo?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Paginación -->
            <div class="d-flex justify-content-center mt-4">
                {{ $vehiculos->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        // Inicializar tooltips
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Mantener abierto el buscador si hay filtros aplicados
            @if(request()->hasAny(['marca', 'modelo', 'anio', 'patente', 'tipo', 'estado']))
                document.getElementById('filtrosCollapse').classList.add('show');
            @endif
            });
    </script>
@endsection