@extends('layouts.app')
<head>
    <title>Usuarios</title>
</head>

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2 align-items-center ">
            <a href="{{ url('/users') }}" class="btn btn-outline-secondary">Volver</a>
            <a href="{{ route('sectores.create') }}" class="btn btn-primary">Nuevo Laboratorio</a>
        </div>
    </div>
    
    @if($sectores->isEmpty())
        <div class="alert alert-warning">
            No hay Laboratorios disponibles.
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
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sectores as $sector)
                    <tr>
                        <td>{{ $sector->usu_descripcion }}</td>
                        <td>{{ $sector->usu_codigo }}</td>
                        <td>{{ $sector->rol ?? 'Sin rol' }}</td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="{{ url('/sectores/' . $sector->usu_codigo) }}">
                                Editar
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $sectores->links() }}
    </div>

    <!-- Vista en pantallas pequeñas (cards) -->
    <div class="d-block d-lg-none">
        <div class="row">
            @foreach($sectores as $sector)
                <div class="col-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">{{ $sector->usu_descripcion }}</h5>
                            <p><strong>Código:</strong> {{ $sector->usu_codigo }}</p>
                            <p 
                            <?php 
                                $estado = trim($sector->usu_estado);
                            
                                if ($estado) {
                                    echo 'class="text-success"';
                                } else {
                                    echo 'class="text-danger"';
                                }
                            ?>
                            ><strong>Estado:</strong> {{ $sector->usu_estado ? 'Activo' : 'Inactivo' }}</p>
                            <p><strong>Rol:</strong> {{ $sector->rol ?? 'Sin rol' }}</p>
                            <a class="btn btn-sm btn-primary" href="{{ url('/sectores/' . $sector->usu_codigo) }}">
                                Editar
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    {{ $sectores->links() }}

    @endif

</div>
@endsection
