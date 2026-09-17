@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Variables</h2>
        <a href="{{ route('variables.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Variable
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('variables.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="{{ request('search') }}" placeholder="Código, nombre o descripción...">
                    </div>
                    <div class="col-md-3">
                        <label for="tipo_variable" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo_variable" name="tipo_variable">
                            <option value="">Todos los tipos</option>
                            @foreach($tipos as $tipo)
                                <option value="{{ $tipo }}" {{ request('tipo_variable') == $tipo ? 'selected' : '' }}>
                                    {{ $tipo }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="activo" class="form-label">Estado</label>
                        <select class="form-select" id="activo" name="activo">
                            <option value="">Todas</option>
                            <option value="1" {{ request('activo') == '1' ? 'selected' : '' }}>Activas</option>
                            <option value="0" {{ request('activo') == '0' ? 'selected' : '' }}>Inactivas</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">Filtrar</button>
                        <a href="{{ route('variables.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body">
            @if($variables->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Unidad</th>
                                <th>Límites</th>
                                <th>Leyes</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($variables as $variable)
                                <tr>
                                    <td><code>{{ $variable->codigo }}</code></td>
                                    <td>{{ $variable->nombre }}</td>
                                    <td>
                                        @if($variable->tipo_variable)
                                            <span class="badge bg-secondary">{{ $variable->tipo_variable }}</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $variable->unidad_medicion ?? '-' }}</td>
                                    <td>
                                        @if($variable->limite_minimo || $variable->limite_maximo)
                                            <small>
                                                @if($variable->limite_minimo)
                                                    Min: {{ $variable->limite_minimo }}
                                                @endif
                                                @if($variable->limite_maximo)
                                                    Max: {{ $variable->limite_maximo }}
                                                @endif
                                            </small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $variable->leyes_normativas_count ?? 0 }}</span>
                                    </td>
                                    <td>
                                        @if($variable->activo)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('variables.show', $variable) }}" 
                                               class="btn btn-sm btn-outline-info" title="Ver">
                                                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                            </a>
                                            <a href="{{ route('variables.edit', $variable) }}" 
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                            </a>
                                            <a href="{{ route('variables.delete', $variable) }}" 
                                               class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-center">
                    {{ $variables->appends(request()->query())->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-vial fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No se encontraron variables.</p>
                    <a href="{{ route('variables.create') }}" class="btn btn-primary">
                        Crear la primera variable
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
