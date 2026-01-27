@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Leyes y Normativas</h2>
            @if($ultimaImportacion)
                <small class="text-muted">
                    <i class="fas fa-clock"></i> Última importación: {{ $ultimaImportacion->format('d/m/Y H:i') }}
                </small>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('leyes-normativas.export.template') }}" class="btn btn-outline-success">
                <i class="fas fa-download"></i> Descargar Plantilla
            </a>
            <a href="{{ route('leyes-normativas.import') }}" class="btn btn-outline-primary">
                <i class="fas fa-upload"></i> Importar 
            </a>
            <a href="{{ route('leyes-normativas.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nueva Normativa
            </a>
        </div>
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

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('import_errors'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>Errores durante la importación:</strong>
            <ul class="mb-0 mt-2">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('leyes-normativas.index') }}">
                <div class="row">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="{{ request('search') }}" placeholder="Código, nombre o grupo...">
                    </div>
                    <div class="col-md-3">
                        <label for="grupo" class="form-label">Grupo</label>
                        <select class="form-select" id="grupo" name="grupo">
                            <option value="">Todos los grupos</option>
                            @foreach($grupos as $grupo)
                                <option value="{{ $grupo }}" {{ request('grupo') == $grupo ? 'selected' : '' }}>
                                    {{ $grupo }}
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
                        <a href="{{ route('leyes-normativas.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-body">
            @if($normativas->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Grupo</th>
                                <th>Artículo</th>
                                <th>Organismo</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($normativas as $normativa)
                                <tr>
                                    <td><code>{{ $normativa->codigo }}</code></td>
                                    <td>{{ Str::limit($normativa->nombre, 40) }}</td>
                                    <td><span class="badge bg-info">{{ $normativa->grupo }}</span></td>
                                    <td>{{ $normativa->articulo ?? '-' }}</td>
                                    <td>{{ Str::limit($normativa->organismo_emisor ?? '-', 20) }}</td>
                                    <td>
                                        @if($normativa->fecha_vigencia)
                                            {{ $normativa->fecha_vigencia->format('d/m/Y') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($normativa->activo)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-secondary">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('leyes-normativas.show', $normativa) }}" 
                                               class="btn btn-sm btn-outline-info" title="Ver">
                                                <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                            </a>
                                            <a href="{{ route('leyes-normativas.edit', $normativa) }}" 
                                               class="btn btn-sm btn-outline-primary" title="Editar">
                                                <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                            </a>
                                            <a href="{{ route('leyes-normativas.delete', $normativa) }}" 
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
                    {{ $normativas->appends(request()->query())->links() }}
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-balance-scale fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No se encontraron leyes o normativas.</p>
                    <a href="{{ route('leyes-normativas.create') }}" class="btn btn-primary">
                        Crear la primera normativa
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
