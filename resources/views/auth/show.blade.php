@extends('layouts.app')

@section('title', 'Perfil de ' . $user->usu_descripcion)

@php
    use App\Support\PerfilUsuarioResumen;

    $rolPrincipal = trim((string) ($user->rol ?? ''));
    $rolesAdicionales = array_values(array_filter(
        $user->rolesAdicionales(),
        fn ($r) => trim((string) $r) !== '' && trim((string) $r) !== $rolPrincipal
    ));
    $etiquetaRol = fn ($rol) => PerfilUsuarioResumen::etiquetaRol(trim((string) $rol));
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success mb-3" role="status">
            <x-heroicon-o-check-circle style="width: 18px; height: 18px;" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="ucrud-panel mb-3">
        <div class="ucrud-perfil__hero">
            <div class="ucrud-perfil__avatar" aria-hidden="true">{{ $perfil['iniciales'] }}</div>
            <div class="ucrud-perfil__identity">
                <h1 class="ucrud-perfil__name">{{ $user->usu_descripcion }}</h1>
                <div class="ucrud-perfil__meta">
                    @if($perfil['cargo'] !== '')
                        <span>{{ $perfil['cargo'] }}</span>
                        <span class="text-muted">·</span>
                    @endif
                    <span class="ucrud-role-chip ucrud-role-chip--extra">{{ $codigoCanonico }}</span>
                    <span class="ucrud-role-chip {{ $perfil['seguridad']['estado_es_activo'] ? '' : 'ucrud-role-chip--extra' }}">
                        {{ $perfil['seguridad']['estado_cuenta'] }}
                    </span>
                </div>
            </div>
            <div class="ucrud-perfil__actions">
                @if($esPropioPerfil)
                    <a href="{{ route('auth.help', ['id' => $codigoCanonico]) }}" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-question-mark-circle style="width: 16px; height: 16px;" />
                        Ayuda
                    </a>
                    <a href="{{ route('auth.security', ['id' => $codigoCanonico]) }}" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                        Seguridad
                    </a>
                    <a href="{{ route('auth.edit', $codigoCanonico) }}" class="ucrud-btn ucrud-btn--primary">
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
                @else
                    <a href="{{ route('auth.edit', $codigoCanonico) }}" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-pencil-square style="width: 16px; height: 16px;" />
                        Editar (admin)
                    </a>
                @endif
            </div>
        </div>

        @if(count($perfil['metricas']) > 0)
            <div class="ucrud-stat-grid">
                @foreach($perfil['metricas'] as $stat)
                    <div class="ucrud-stat-card">
                        <div class="ucrud-stat-card__value">{{ number_format($stat['valor'], 0, ',', '.') }}</div>
                        <div class="ucrud-stat-card__label">{{ $stat['etiqueta'] }}</div>
                        @if(!empty($stat['detalle']))
                            <div class="ucrud-stat-card__hint">{{ $stat['detalle'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="ucrud-panel h-100">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-user-circle style="width: 18px; height: 18px;" />
                        Datos de la cuenta
                    </h2>
                    <div class="ucrud-detail-grid mt-2">
                        @foreach($perfil['cuenta'] as $label => $value)
                            <div @class(['ucrud-detail-item--wide' => in_array($label, ['Email', 'Nombre', 'Departamento', 'Sector de trabajo'], true)])>
                                <div class="ucrud-detail-item__label">{{ $label }}</div>
                                <div class="ucrud-detail-item__value">
                                    @if($label === 'Email')
                                        <a href="mailto:{{ $value }}" class="text-decoration-none">{{ $value }}</a>
                                    @else
                                        {{ $value }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="ucrud-panel h-100">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-user-group style="width: 18px; height: 18px;" />
                        Roles asignados
                    </h2>
                    <div class="ucrud-role-list mt-2">
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
                    @if($perfil['permisos']['total'] > 0)
                        <p class="small text-muted mt-3 mb-2">{{ $perfil['permisos']['total'] }} permisos / capacidades efectivas</p>
                        <button class="ucrud-btn ucrud-btn--ghost btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#perfilPermisos" aria-expanded="false">
                            Ver permisos
                        </button>
                        <div class="collapse ucrud-perfil__permisos" id="perfilPermisos">
                            <ul class="mt-2 mb-0">
                                @foreach($perfil['permisos']['items'] as $perm)
                                    <li>{{ $perm['etiqueta'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-shield-check style="width: 18px; height: 18px;" />
                        Seguridad
                    </h2>
                    <div class="ucrud-detail-grid mt-2">
                        <div>
                            <div class="ucrud-detail-item__label">Estado</div>
                            <div class="ucrud-detail-item__value">{{ $perfil['seguridad']['estado_cuenta'] }}</div>
                        </div>
                        @if($perfil['seguridad']['ultima_actualizacion'])
                            <div>
                                <div class="ucrud-detail-item__label">Última actualización</div>
                                <div class="ucrud-detail-item__value">{{ $perfil['seguridad']['ultima_actualizacion']->format('d/m/Y H:i') }}</div>
                            </div>
                        @endif
                        @if($esPropioPerfil)
                            <div>
                                <div class="ucrud-detail-item__label">Sesión actual</div>
                                <div class="ucrud-detail-item__value">
                                    @if($sesionEsActual)
                                        <span class="ucrud-role-chip">Activa en este dispositivo</span>
                                    @elseif($perfil['seguridad']['tiene_sesion_registrada'])
                                        <span class="ucrud-role-chip ucrud-role-chip--extra">Otra sesión registrada</span>
                                    @else
                                        <span class="text-muted">Sin registro de sesión</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                    @if($esPropioPerfil)
                        <div class="mt-3">
                            <a href="{{ route('auth.security', ['id' => $codigoCanonico]) }}" class="ucrud-btn ucrud-btn--ghost btn-sm">
                                <x-heroicon-o-lock-closed style="width: 14px; height: 14px;" />
                                Cambiar contraseña y sesión
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @foreach($perfil['operativo'] as $bloque)
            <div class="col-md-6 col-xl-4">
                <div class="ucrud-panel h-100">
                    <div class="ucrud-detail-block mb-0">
                        <h2 class="ucrud-detail-block__title">{{ $bloque['titulo'] }}</h2>
                        <div class="ucrud-detail-grid mt-2">
                            @foreach($bloque['filas'] as $fila)
                                <div>
                                    <div class="ucrud-detail-item__label">{{ $fila['label'] }}</div>
                                    <div class="ucrud-detail-item__value">{{ is_int($fila['value']) ? number_format($fila['value'], 0, ',', '.') : $fila['value'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if(count($perfil['actividad']) > 0)
            <div class="col-12">
                <div class="ucrud-panel">
                    <div class="ucrud-detail-block mb-0 pb-2">
                        <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                            <x-heroicon-o-clock style="width: 18px; height: 18px;" />
                            Actividad reciente
                        </h2>
                        <p class="small text-muted mb-0">Cambios registrados en el historial del sistema.</p>
                    </div>
                    <ul class="ucrud-actividad-list">
                        @foreach($perfil['actividad'] as $item)
                            <li>
                                <span>{{ $item['descripcion'] }}</span>
                                <span class="text-muted small">
                                    @if($item['fecha'])
                                        {{ $item['fecha']->format('d/m/Y H:i') }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
