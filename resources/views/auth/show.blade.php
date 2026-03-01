@extends('layouts.app')
<head>
    <title>Perfil de {{ $user->usu_descripcion }}</title>
</head>

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow rounded-4 border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center rounded-top-4">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle me-2"></i>
                        Perfil de {{ $user->usu_descripcion }}
                    </h5>
                    <div>
                        <span class="badge bg-primary">{{ $user->rol ?? 'Sin rol' }}</span>
                        @if($user->all_roles && count($user->all_roles) > 1)
                            @foreach(array_diff($user->all_roles, [$user->rol]) as $rolAdicional)
                                <span class="badge bg-secondary ms-1">{{ $rolAdicional }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush mb-4">
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="bi bi-upc-scan me-2 text-primary"></i>Código:</strong> <span>{{ $user->usu_codigo }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="bi bi-bar-chart me-2 text-primary"></i>Nivel:</strong> <span>{{ $user->usu_nivel }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="bi bi-check-circle me-2 text-primary"></i>Estado:</strong> 
                            <span class="badge {{ $user->usu_estado ? 'bg-success' : 'bg-danger' }}">
                                {{ $user->usu_estado ? 'Activo' : 'Inactivo' }}
                            </span>
                        </li>
                        @if($user->created_at)
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="bi bi-calendar-plus me-2 text-primary"></i>Registrado:</strong> 
                            <span>{{ $user->created_at->format('d/m/Y H:i') }}</span>
                        </li>
                        @endif
                        @if($user->updated_at)
                        <li class="list-group-item d-flex justify-content-between">
                            <strong><i class="bi bi-calendar-check me-2 text-primary"></i>Última actualización:</strong> 
                            <span>{{ $user->updated_at->format('d/m/Y H:i') }}</span>
                        </li>
                        @endif
                    </ul>
                    <div class="d-flex flex-column flex-md-row gap-2">
                        <a href="{{ route('auth.edit', $user->usu_codigo) }}" class="btn btn-outline-primary w-100 w-md-50 align-self-start">
                            <i class="bi bi-pencil-square me-1"></i>Editar Perfil
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="w-100 w-md-50">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                            </button>
                        </form>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
