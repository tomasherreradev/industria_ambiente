@extends('layouts.app')
<head>
    <title>Usuarios</title>
</head>

@section('content')
<div class="container py-4">

    <!-- Encabezado Desktop -->
    <div class="d-none d-lg-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2 align-items-center">
            <h1 class="fs-4">Usuarios</h1>
            <a href="{{ url('/sectores') }}" class="btn btn-sm btn-outline-primary">Laboratorios</a>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('users.createUser') }}" class="btn btn-primary">Nuevo Usuario</a>

            <form action="{{ url('/users') }}" method="GET" class="d-flex gap-2 align-items-center" style="margin-bottom: 0;">
                <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}" style="width: 180px;">
                <select name="rol" class="form-control" style="width: 160px;">
                    <option value="">Todos los roles</option>
                    <option value="laboratorio" {{ request('rol') == 'laboratorio' ? 'selected' : '' }}>Analista</option>
                    <option value="muestreador" {{ request('rol') == 'muestreador' ? 'selected' : '' }}>Muestreador</option>
                    <option value="coordinador_lab" {{ request('rol') == 'coordinador_lab' ? 'selected' : '' }}>Coordinador Lab</option>
                    <option value="coordinador_muestreo" {{ request('rol') == 'coordinador_muestreo' ? 'selected' : '' }}>Coord. Muestreo</option>
                    <option value="ventas" {{ request('rol') == 'ventas' ? 'selected' : '' }}>Ventas</option>
                    <option value="firmador" {{ request('rol') == 'firmador' ? 'selected' : '' }}>Firmador</option>
                    <option value="facturador" {{ request('rol') == 'facturador' ? 'selected' : '' }}>Facturador</option>
                </select>
                <button type="submit" class="btn btn-primary">Buscar</button>
                @if(request('search') || request('rol'))
                    <a href="{{ url('/users') }}" class="btn btn-outline-secondary">Limpiar</a>
                @endif
            </form>
        </div>
    </div>

    <!-- Encabezado Mobile -->
    <div class="d-lg-none mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fs-5 mb-0">Usuarios</h1>
            <div class="d-flex gap-2">
                <a href="{{ url('/sectores') }}" class="btn btn-sm btn-outline-primary">Laboratorios</a>
                <a href="{{ route('users.createUser') }}" class="btn btn-sm btn-primary">+ Nuevo</a>
            </div>
        </div>

        <form action="{{ url('/users') }}" method="GET" class="mb-0">
            <div class="input-group mb-2">
                <input type="text" name="search" class="form-control" placeholder="Buscar por nombre o código..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            <div class="d-flex gap-2">
                <select name="rol" class="form-select form-select-sm flex-grow-1">
                    <option value="">Todos los roles</option>
                    <option value="laboratorio" {{ request('rol') == 'laboratorio' ? 'selected' : '' }}>Analista</option>
                    <option value="muestreador" {{ request('rol') == 'muestreador' ? 'selected' : '' }}>Muestreador</option>
                    <option value="coordinador_lab" {{ request('rol') == 'coordinador_lab' ? 'selected' : '' }}>Coord. Lab</option>
                    <option value="coordinador_muestreo" {{ request('rol') == 'coordinador_muestreo' ? 'selected' : '' }}>Coord. Muestreo</option>
                    <option value="ventas" {{ request('rol') == 'ventas' ? 'selected' : '' }}>Ventas</option>
                    <option value="firmador" {{ request('rol') == 'firmador' ? 'selected' : '' }}>Firmador</option>
                    <option value="facturador" {{ request('rol') == 'facturador' ? 'selected' : '' }}>Facturador</option>
                </select>
                @if(request('search') || request('rol'))
                    <a href="{{ url('/users') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
                @endif
            </div>
        </form>
    </div>
    
    @if($usuarios->isEmpty())
        <div class="alert alert-warning">
            No hay Usuarios disponibles.
        </div>
    @else

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Vista en pantallas grandes (tables-like) -->
    <div class="d-none d-lg-block">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Código</th>
                    {{-- <th>Estado</th> --}}
                    <th>Rol</th>
                    <th>Laboratorio</th>
                    <th>Sector</th> 
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($usuarios as $usu)
                    <tr>
                        <td>{{ $usu->usu_descripcion }}</td>
                        <td>{{ $usu->usu_codigo }}</td>
                        {{-- <td
                        <?php 
                            $estado = trim($usu->usu_estado);
                        
                            if ($estado) {
                                echo 'class="bg-success text-white"';
                            } else {
                                echo 'class="bg-warning text-white"';
                            }
                        ?>
                        >{{ $usu->usu_estado ? 'Activo' : 'Inactivo' }}</td> --}}
                        <td>{{ $usu->rol ?? 'Sin rol' }}</td>
                        <td>{{ $usu->sector_codigo ?? 'N/A' }}</td>
                        <td>{{ $usu->sector_trabajo ?? 'N/A' }}</td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="{{ url('/users/' . $usu->usu_codigo) }}">
                                Editar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $usuarios->links() }}
    </div>

    <!-- Vista en pantallas pequeñas (lista compacta) -->
    <div class="d-block d-lg-none">
        <div class="list-group">
            @foreach($usuarios as $usu)
                <a href="{{ url('/users/' . $usu->usu_codigo) }}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-semibold">{{ $usu->usu_descripcion }}</h6>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <small class="text-muted">
                                    <i class="bi bi-person-badge"></i> {{ trim($usu->usu_codigo) }}
                                </small>
                                @if($usu->sector_codigo)
                                    <small class="text-muted">
                                        <i class="bi bi-diagram-3"></i> {{ $usu->sector_codigo }}
                                    </small>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge {{ $usu->rol ? 'bg-primary' : 'bg-secondary' }} rounded-pill">
                                {{ $usu->rol ?? 'Sin rol' }}
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-3">
            {{ $usuarios->links() }}
        </div>
    </div>
    @endif

</div>
@endsection
