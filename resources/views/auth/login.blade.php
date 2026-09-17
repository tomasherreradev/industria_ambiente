@extends('layouts.login')

@section('title', 'Iniciar sesión')

@section('content')
<div class="login-shell">
    <div class="login-shell__bg" aria-hidden="true"></div>

    <div class="login-shell__layout">
        <section class="login-brand" aria-label="Presentación">
            <div class="login-brand__inner">
                <div class="login-hero__logo-wrap">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="Industria y Ambiente" class="login-hero__logo" width="160" height="160">
                </div>
                <h1 class="login-hero__title">Gestión de muestreo y laboratorio</h1>
                <p class="login-hero__subtitle">
                    Cotizaciones, órdenes de trabajo, muestras, análisis e informes en un solo lugar.
                </p>
                <ul class="login-hero__features">
                    <li class="login-hero__feature">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Trazabilidad completa
                    </li>
                    <li class="login-hero__feature">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Informes y facturación
                    </li>
                    <li class="login-hero__feature">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Operación en campo y lab
                    </li>
                </ul>
            </div>
        </section>

        <main class="login-main">
            <div class="login-card">
                <header class="login-card__header">
                    <span class="login-card__eyebrow">Acceso seguro</span>
                    <h2 class="login-card__title">Iniciar sesión</h2>
                    <p class="login-card__desc">Ingresá con tu usuario y contraseña del sistema.</p>
                </header>

                @if($errors->any())
                    <div class="login-alert" role="alert">
                        <div class="login-alert__head">
                            <span>No se pudo iniciar sesión</span>
                        </div>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" novalidate>
                    @csrf

                    <div class="login-field">
                        <label for="usu_codigo">Usuario</label>
                        <div class="login-input-wrap">
                            <svg class="login-input-wrap__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                            </svg>
                            <input type="text" name="usu_codigo" id="usu_codigo" value="{{ old('usu_codigo') }}"
                                   autocomplete="username" required autofocus placeholder="Tu código de usuario">
                        </div>
                    </div>

                    <div class="login-field">
                        <label for="usu_clave">Contraseña</label>
                        <div class="login-input-wrap login-input-wrap--password">
                            <svg class="login-input-wrap__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                            </svg>
                            <input type="password" name="usu_clave" id="usu_clave" autocomplete="current-password"
                                   required placeholder="••••••••">
                            <button type="button" class="login-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <svg data-icon-show xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                </svg>
                                <svg data-icon-hide class="d-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="login-submit">Entrar al sistema</button>
                </form>
            </div>

            <footer class="login-footer">
                &copy; {{ date('Y') }} — InitSoluciones S.R.L.
            </footer>
        </main>
    </div>
</div>
@endsection
