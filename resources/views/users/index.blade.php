@extends('layouts.app')
<head>
    <title>Usuarios</title>
</head>

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2 align-items-center ">
            <h1 class="fs-4 md-fs-5">Usuarios</h1>
            <a href="{{ url('/sectores') }}" class="btn btn-primary">Sectores</a>
        </div>


        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('users.createUser') }}" class="btn btn-primary">Nuevo Usuario</a>
            {{-- <a href="{{ route('users.exportar', request()->only('rol')) }}" class="btn btn-success">
                <i class="bi bi-download"></i> Exportar Excel
            </a> --}}

            <!-- Formulario para filtros -->
            <form action="{{ url('/users') }}" method="GET" class="d-flex gap-2" style="margin-bottom: 0px;">
                <div class="form-group mr-2">
                    <select name="rol" class="form-control">
                        <option value="">Selecciona Rol</option>
                        <option value="laboratorio" {{ request('rol') == 'laboratorio' ? 'selected' : '' }}>Analista</option>
                        <option value="muestreador" {{ request('rol') == 'muestreador' ? 'selected' : '' }}>Muestreador</option>
                        <option value="coordinador_lab" {{ request('rol') == 'coordinador_lab' ? 'selected' : '' }}>Coordinador Laboratorio</option>
                        <option value="coordinador_muestreo" {{ request('rol') == 'coordinador_muestreo' ? 'selected' : '' }}>Coordinador Muestreo</option>
                        <!-- Agregar más roles según tu base de datos -->
                    </select>
                </div>
                {{-- <div class="form-group mr-2">
                    <select name="estado" class="form-control">
                        <option value="">Selecciona Estado</option>
                        <option value="1" {{ request('estado') == '1' ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ request('estado') == '0' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                </div> --}}
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </form>
        </div>

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
                        <td>{{ $usu->sector_codigo ?? 'Sin sector' }}</td>
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

    <!-- Vista en pantallas pequeñas (cards) -->
    <div class="d-block d-lg-none">
        <div class="row">
            @foreach($usuarios as $usu)
                <div class="col-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">{{ $usu->usu_descripcion }}</h5>
                            <p><strong>Código:</strong> {{ $usu->usu_codigo }}</p>
                            {{-- <p 
                            <?php 
                                $estado = trim($usu->usu_estado);
                            
                                if ($estado) {
                                    echo 'class="text-success"';
                                } else {
                                    echo 'class="text-danger"';
                                }
                            ?>
                            ><strong>Estado:</strong> {{ $usu->usu_estado ? 'Activo' : 'Inactivo' }}</p> --}}
                            <p><strong>Rol:</strong> {{ $usu->rol ?? 'Sin rol' }}</p>
                            <p><strong>Sector:</strong> {{ $usu->sector_codigo ?? 'Sin sector' }}</p>
                            <a class="btn btn-sm btn-primary" href="{{ url('/users/' . $usu->usu_codigo) }}">
                                Editar
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    {{ $usuarios->links() }}

    @endif

</div>
@endsection
