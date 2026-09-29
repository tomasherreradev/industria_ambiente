@extends('layouts.app')

@section('title', 'Editar usuario')

@php
    use App\Support\PerfilUsuarioResumen;

    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
    $puedeEditarUsuarios = $puedeEditarUsuarios ?? false;
    $puedeEliminarUsuarios = $puedeEliminarUsuarios ?? false;
    $iniciales = PerfilUsuarioResumen::inicialesDesdeNombre((string) $usuario->usu_descripcion);
    $rolEtq = PerfilUsuarioResumen::etiquetaRol(trim((string) ($usuario->rol ?? '')));
    $activo = (bool) ($usuario->usu_estado ?? false);
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    <div class="mb-3">
        <a href="{{ route('users.showUsers') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
            Volver al listado
        </a>
    </div>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success mb-3" role="status">
            <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ucrud-panel mb-3">
        <div class="ucrud-perfil__hero">
            <div class="ucrud-perfil__avatar" aria-hidden="true">{{ $iniciales }}</div>
            <div class="ucrud-perfil__identity">
                <h1 class="ucrud-perfil__name">{{ $usuario->usu_descripcion }}</h1>
                <div class="ucrud-perfil__meta">
                    <span class="ucrud-role-chip ucrud-role-chip--extra">{{ trim($usuario->usu_codigo) }}</span>
                    @if($rolEtq !== '')
                        <span class="ucrud-role-chip">{{ $rolEtq }}</span>
                    @endif
                    <span class="ucrud-role-chip {{ $activo ? '' : 'ucrud-role-chip--extra' }}">
                        {{ $activo ? 'Activo' : 'Inactivo' }}
                    </span>
                    @unless($puedeEditarUsuarios)
                        <span class="ucrud-role-chip ucrud-role-chip--extra">Solo lectura</span>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="ucrud-panel">
                @include('users.partials.formulario-usuario', [
                    'modo' => 'edit',
                    'usuario' => $usuario,
                    'esAdmin' => $esAdmin,
                    'sectores' => $sectores,
                    'rolesAdicionales' => $rolesAdicionales,
                    'sectoresAsignados' => $sectoresAsignados,
                    'puedeEditarUsuarios' => $puedeEditarUsuarios,
                    'puedeEliminarUsuarios' => $puedeEliminarUsuarios,
                ])
            </div>
        </div>
        <div class="col-lg-4">
            @include('users.partials.sidebar-usuario-edit', ['usuario' => $usuario])
        </div>
    </div>
</div>

@include('users.partials.formulario-usuario-scripts', ['formId' => 'form-editar-usuario'])
@endsection
