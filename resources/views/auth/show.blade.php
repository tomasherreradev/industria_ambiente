@extends('layouts.app')

@section('title', 'Perfil de ' . $user->usu_descripcion)

@php
    $opcionesRol = [
        'laboratorio' => 'Analista',
        'muestreador' => 'Muestreador',
        'coordinador_lab' => 'Coordinador Laboratorio',
        'coordinador_muestreo' => 'Coordinador Muestreo',
        'facturador' => 'Facturador',
        'ventas' => 'Vendedor',
        'firmador' => 'Firmador',
        'coordinador_consul' => 'Coordinador Consultoría',
        'coordinador_mediciones' => 'Coordinador Mediciones',
        'asp' => 'ASP',
        'clarke_fire' => 'Clarke Fire',
        'cliente' => 'Usuario Cliente',
        'cadena_custodia' => 'Cadena de custodia',
    ];
    $rolPrincipal = trim((string) ($user->rol ?? ''));
    $rolesAdicionales = array_values(array_filter(
        $user->rolesAdicionales(),
        fn ($r) => trim((string) $r) !== '' && trim((string) $r) !== $rolPrincipal
    ));
    $etiquetaRol = fn ($rol) => $opcionesRol[$rol] ?? ucfirst(str_replace('_', ' ', $rol));
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    <div class="ucrud-perfil__main">
        @if(session('success'))
            <div class="ucrud-alert ucrud-alert--success mb-3" role="status">
                <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <header class="ucrud-header">
            <div class="ucrud-header__titles">
                <h1 class="ucrud-title">{{ $user->usu_descripcion }}</h1>
                <p class="ucrud-subtitle">Perfil de usuario</p>
            </div>
            <div class="ucrud-header__actions d-flex flex-wrap gap-2">
                <a href="{{ route('auth.edit', $user->usu_codigo) }}" class="ucrud-btn ucrud-btn--primary">
                    <x-heroicon-o-pencil-square style="width: 16px; height: 16px;" />
                    Editar perfil
                </a>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-arrow-right-on-rectangle style="width: 16px; height: 16px;" />
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </header>

        <div class="ucrud-panel">
            <div class="ucrud-detail-block">
                <h2 class="ucrud-detail-block__title">Datos de la cuenta</h2>
                <div class="ucrud-detail-grid">
                    <div>
                        <div class="ucrud-detail-item__label">Código</div>
                        <div class="ucrud-detail-item__value">{{ $user->usu_codigo }}</div>
                    </div>
                    <div>
                        <div class="ucrud-detail-item__label">Nivel</div>
                        <div class="ucrud-detail-item__value">{{ $user->usu_nivel }}</div>
                    </div>
                    <div>
                        <div class="ucrud-detail-item__label">Estado</div>
                        <div class="ucrud-detail-item__value">
                            <span class="ucrud-role-chip {{ $user->usu_estado ? '' : 'ucrud-role-chip--extra' }}">
                                {{ $user->usu_estado ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                    </div>
                    @if($user->updated_at)
                    <div>
                        <div class="ucrud-detail-item__label">Última actualización</div>
                        <div class="ucrud-detail-item__value">{{ $user->updated_at->format('d/m/Y H:i') }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="ucrud-detail-block">
                <h2 class="ucrud-detail-block__title">Roles asignados</h2>
                <div class="ucrud-role-list">
                    @if($rolPrincipal !== '')
                        <span class="ucrud-role-chip">
                            {{ $etiquetaRol($rolPrincipal) }}
                            <span class="ucrud-role-chip__tag">Principal</span>
                        </span>
                    @endif
                    @foreach($rolesAdicionales as $rol)
                        <span class="ucrud-role-chip ucrud-role-chip--extra">
                            {{ $etiquetaRol($rol) }}
                        </span>
                    @endforeach
                    @if($rolPrincipal === '' && $rolesAdicionales === [])
                        <span class="ucrud-role-chip ucrud-role-chip--extra">Sin rol asignado</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
