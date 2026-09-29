@extends('layouts.app')

@section('title', 'Seguridad y contraseña')

@php
    $codigoUsuario = trim((string) $user->usu_codigo);
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

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
            <x-heroicon-o-exclamation-circle style="width: 18px; height: 18px;" />
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">Seguridad y contraseña</h1>
            <p class="ucrud-subtitle">
                {{ $user->usu_descripcion }}
                <span class="text-muted">·</span>
                <span class="ucrud-role-chip ucrud-role-chip--extra">{{ $codigoUsuario }}</span>
            </p>
        </div>
        <div class="ucrud-header__actions d-flex flex-wrap gap-2">
            <a href="{{ route('auth.show', $codigoUsuario) }}" class="ucrud-btn ucrud-btn--ghost">
                <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
                Volver al perfil
            </a>
            @if($esPropioPerfil)
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="ucrud-btn ucrud-btn--ghost">
                        <x-heroicon-o-arrow-right-on-rectangle style="width: 16px; height: 16px;" />
                        Cerrar sesión
                    </button>
                </form>
            @endif
        </div>
    </header>

    <div class="row g-3">
        @if($esPropioPerfil)
            <div class="col-lg-7">
                <div class="ucrud-panel h-100">
                    <div class="ucrud-form">
                        <p class="ucrud-form__section-title d-flex align-items-center gap-2 mb-3">
                            <x-heroicon-o-lock-closed style="width: 18px; height: 18px;" />
                            Cambiar contraseña
                        </p>

                        <form method="POST" action="{{ route('auth.security.password', ['id' => $codigoUsuario]) }}">
                            @csrf
                            @method('PUT')

                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="usu_clave_actual" class="form-label">Contraseña actual</label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control @error('usu_clave_actual') is-invalid @enderror"
                                               id="usu_clave_actual"
                                               name="usu_clave_actual"
                                               placeholder="Ingresá tu contraseña actual"
                                               required
                                               autocomplete="current-password">
                                        <button type="button" class="btn btn-outline-secondary js-toggle-password" aria-label="Mostrar contraseña">
                                            <x-heroicon-o-eye class="js-icon-show" style="width: 18px; height: 18px;" />
                                            <x-heroicon-o-eye-slash class="js-icon-hide d-none" style="width: 18px; height: 18px;" />
                                        </button>
                                    </div>
                                    @error('usu_clave_actual')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="usu_clave" class="form-label">Nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control @error('usu_clave') is-invalid @enderror"
                                               id="usu_clave"
                                               name="usu_clave"
                                               placeholder="Mínimo 4 caracteres"
                                               required
                                               minlength="4"
                                               autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary js-toggle-password" aria-label="Mostrar contraseña">
                                            <x-heroicon-o-eye class="js-icon-show" style="width: 18px; height: 18px;" />
                                            <x-heroicon-o-eye-slash class="js-icon-hide d-none" style="width: 18px; height: 18px;" />
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar" id="passwordStrengthBar" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="passwordStrengthText"></small>
                                    </div>
                                    @error('usu_clave')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="usu_clave_confirmation" class="form-label">Confirmar contraseña</label>
                                    <div class="input-group">
                                        <input type="password"
                                               class="form-control"
                                               id="usu_clave_confirmation"
                                               name="usu_clave_confirmation"
                                               placeholder="Repetí la nueva contraseña"
                                               required
                                               minlength="4"
                                               autocomplete="new-password">
                                        <button type="button" class="btn btn-outline-secondary js-toggle-password" aria-label="Mostrar contraseña">
                                            <x-heroicon-o-eye class="js-icon-show" style="width: 18px; height: 18px;" />
                                            <x-heroicon-o-eye-slash class="js-icon-hide d-none" style="width: 18px; height: 18px;" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <button type="submit" class="ucrud-btn ucrud-btn--primary">
                                    <x-heroicon-o-check style="width: 16px; height: 16px;" />
                                    Actualizar contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @else
            <div class="col-12">
                <div class="ucrud-alert ucrud-alert--warning">
                    <x-heroicon-o-information-circle style="width: 18px; height: 18px;" />
                    <span>
                        Estás viendo la seguridad de otro usuario. El cambio de contraseña solo puede hacerlo el titular o un administrador desde
                        <a href="{{ route('auth.edit', $codigoUsuario) }}" class="alert-link">editar perfil</a>.
                    </span>
                </div>
            </div>
        @endif

        <div class="{{ $esPropioPerfil ? 'col-lg-5' : 'col-12' }}">
            <div class="ucrud-panel h-100">
                <div class="ucrud-detail-block mb-0">
                    <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                        <x-heroicon-o-shield-check style="width: 18px; height: 18px;" />
                        Política de sesión
                    </h2>
                    <p class="text-muted mb-0 mt-2" style="font-size: 0.9375rem; line-height: 1.5;">
                        Solo puede haber <strong>una sesión activa</strong> por usuario. Si iniciás sesión en otro dispositivo o navegador,
                        la sesión anterior se cierra automáticamente.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="ucrud-panel mt-3">
        <div class="ucrud-detail-block mb-3">
            <h2 class="ucrud-detail-block__title d-flex align-items-center gap-2">
                <x-heroicon-o-clock style="width: 18px; height: 18px;" />
                Sesión en este dispositivo
            </h2>
        </div>

        <div class="ucrud-tablewrap">
            <table class="ucrud-table">
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>IP</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $dispositivo['label'] }}</div>
                            <small class="text-muted">Navegador detectado en esta visita</small>
                        </td>
                        <td>
                            <span class="ucrud-detail-item__value">{{ $ipActual ?? '—' }}</span>
                        </td>
                        <td>
                            @if($sesionEsActual)
                                <span class="ucrud-role-chip">Sesión activa</span>
                            @else
                                <span class="ucrud-role-chip ucrud-role-chip--extra">No registrada</span>
                                <small class="d-block text-muted mt-1">Volvé a iniciar sesión si tenés problemas.</small>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-toggle-password').forEach(function (button) {
            button.addEventListener('click', function () {
                const group = this.closest('.input-group');
                const input = group ? group.querySelector('input') : null;
                if (!input) return;

                const showIcon = this.querySelector('.js-icon-show');
                const hideIcon = this.querySelector('.js-icon-hide');
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';

                if (showIcon) showIcon.classList.toggle('d-none', isPassword);
                if (hideIcon) hideIcon.classList.toggle('d-none', !isPassword);
            });
        });

        const passwordInput = document.getElementById('usu_clave');
        const progressBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        if (!passwordInput || !progressBar || !strengthText) return;

        passwordInput.addEventListener('input', function () {
            const value = this.value;
            if (value.length === 0) {
                progressBar.style.width = '0%';
                progressBar.className = 'progress-bar';
                strengthText.textContent = '';
                return;
            }
            if (value.length < 6) {
                progressBar.style.width = '25%';
                progressBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Seguridad: débil';
            } else if (value.length < 10) {
                progressBar.style.width = '50%';
                progressBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Seguridad: moderada';
            } else {
                progressBar.style.width = '100%';
                progressBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Seguridad: fuerte';
            }
        });
    });
</script>
@endpush
