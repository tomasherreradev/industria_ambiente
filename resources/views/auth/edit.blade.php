@extends('layouts.app')

@section('title', 'Editar perfil')

@php
    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
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
    $rolesAdicionales = $user->rolesAdicionales();
    $rolPrincipal = trim((string) ($user->rol ?? ''));
    $rolesSoloLectura = array_values(array_filter(
        $rolesAdicionales,
        fn ($r) => trim((string) $r) !== '' && trim((string) $r) !== $rolPrincipal
    ));
    $etiquetaRol = fn ($rol) => $opcionesRol[$rol] ?? ucfirst(str_replace('_', ' ', $rol));
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    <div class="ucrud-perfil__main">
        @include('partials.ucrud-form-header', [
            'title' => 'Editar perfil',
            'subtitle' => $user->usu_descripcion,
            'backUrl' => route('auth.show', $user->usu_codigo),
        ])

        @if($errors->any())
            <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="ucrud-panel">
            <div class="ucrud-form">
                <form action="{{ route('auth.update', $user->usu_codigo) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <p class="ucrud-form__section-title mb-0">Datos personales</p>
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="usu_descripcion" name="usu_descripcion"
                                   value="{{ old('usu_descripcion', $user->usu_descripcion) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label for="usu_codigo" class="form-label">Código</label>
                            <input type="text" class="form-control" id="usu_codigo" value="{{ $user->usu_codigo }}" readonly disabled>
                        </div>
                    </div>

                    <p class="ucrud-form__section-title">Seguridad</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="usu_clave" class="form-label">Contraseña nueva (opcional)</label>
                            <input type="password" class="form-control" id="usu_clave" name="usu_clave" autocomplete="new-password">
                            <small class="text-muted">Dejá en blanco si no querés cambiarla.</small>
                        </div>
                    </div>

                    @if($esAdmin)
                        <p class="ucrud-form__section-title">Administración</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="usu_nivel" class="form-label">Nivel</label>
                                <input type="number" class="form-control" id="usu_nivel" name="usu_nivel"
                                       value="{{ old('usu_nivel', $user->usu_nivel) }}">
                            </div>
                            <div class="col-md-4">
                                <label for="usu_estado" class="form-label">Estado</label>
                                <select class="form-select" id="usu_estado" name="usu_estado">
                                    <option value="1" @selected(old('usu_estado', $user->usu_estado ? '1' : '0') == '1')>Activo</option>
                                    <option value="0" @selected(old('usu_estado', $user->usu_estado ? '1' : '0') == '0')>Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="rol" class="form-label">Rol principal</label>
                                <select class="form-select" id="rol" name="rol">
                                    @foreach($opcionesRol as $val => $etq)
                                        <option value="{{ $val }}" @selected(old('rol', $user->rol) === $val)>{{ $etq }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="roles_adicionales" class="form-label">Roles adicionales</label>
                                <select name="roles_adicionales[]" id="roles_adicionales" class="form-select" multiple size="6">
                                    @foreach($opcionesRol as $val => $etq)
                                        <option value="{{ $val }}"
                                            @selected(in_array($val, old('roles_adicionales', $rolesAdicionales), true))>{{ $etq }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Varios roles: Ctrl+clic. Se ignoran si coinciden con el rol principal.</small>
                            </div>
                        </div>
                    @else
                        <p class="ucrud-form__section-title">Roles</p>
                        <div class="ucrud-role-list mb-2">
                            @if($rolPrincipal !== '')
                                <span class="ucrud-role-chip">
                                    {{ $etiquetaRol($rolPrincipal) }}
                                    <span class="ucrud-role-chip__tag">Principal</span>
                                </span>
                            @endif
                            @foreach($rolesSoloLectura as $rol)
                                <span class="ucrud-role-chip ucrud-role-chip--extra">{{ $etiquetaRol($rol) }}</span>
                            @endforeach
                            @if($rolPrincipal === '' && $rolesSoloLectura === [])
                                <span class="ucrud-role-chip ucrud-role-chip--extra">Sin rol asignado</span>
                            @endif
                        </div>
                        <small class="text-muted">Los roles solo pueden modificarlos administradores del sistema.</small>
                    @endif

                    <div class="ucrud-form__actions">
                        <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar cambios</button>
                        <a href="{{ route('auth.show', $user->usu_codigo) }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
