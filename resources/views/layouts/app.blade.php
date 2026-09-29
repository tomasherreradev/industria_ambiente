<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Laboratorio')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ filemtime(public_path('css/sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/topbar.css') }}?v={{ filemtime(public_path('css/topbar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/notificaciones.css') }}?v={{ filemtime(public_path('css/notificaciones.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-nav.css') }}?v={{ filemtime(public_path('css/mobile-nav.css')) }}">

    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --gray-600: #6c757d;
            --gray-400: #ced4da;
            --sidebar-width: 260px;
            --navbar-height: 64px;
            --app-topbar-height: 64px;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: padding 0.3s ease;
        }

        /* Navbar */
        .navbar {
            background-color: #ffffff;
        }

        /* Layout principal */
        .main-content {
            min-height: calc(100vh - var(--navbar-height));
        }

        @media (min-width: 768px) {
            .main-content {
                margin-left: var(--sidebar-width);
                padding: 2rem;
            }
        }

        /* Elementos comunes */
        .navbar-toggler {
            border: none;
            outline: none;
        }

        .navbar-brand img {
            height: 40px;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .mobile-logo {
            height: 40px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }

        /* Navbar móvil mejorado */
        .navbar-mobile-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1060;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        #mobileNavbar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #fff;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        #mobileNavbar.show {
            transform: translateX(0);
        }

        @media (max-width: 767.98px) {
            :root {
                --navbar-height: 4.125rem;
            }

            .navbar-mobile-container .navbar {
                --bs-navbar-padding-y: 0.375rem;
                min-height: var(--navbar-height);
            }

            .navbar-mobile-container .navbar-brand {
                padding-top: 0;
                padding-bottom: 0;
                margin-right: 0.5rem;
                flex-shrink: 0;
            }

            .navbar-mobile-container .container-fluid {
                flex-wrap: nowrap;
                gap: 0.5rem;
            }

            .navbar-mobile-container .navbar-actions {
                flex-shrink: 0;
                margin-left: auto;
            }

            .navbar-mobile-container .navbar-toggler {
                padding: 0.25rem 0.375rem;
                font-size: 1rem;
                line-height: 1;
                border: 1px solid var(--gray-400);
                border-radius: 0.375rem;
            }

            .navbar-mobile-container .navbar-toggler:focus {
                box-shadow: none;
            }

            .main-content {
                margin-top: var(--navbar-height);
                padding-top: 0.75rem;
            }
        }

        body.navbar-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* Botón de cierre del menú */
        .close-menu-btn {
            position: absolute;
            top: 0.5rem;
            right: 1rem;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: none;
            font-size: 1.5rem;
            color: var(--gray-600);
            z-index: 1;
        }

        /* Efecto overlay */
        .navbar-overlay {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .navbar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        @media (min-width: 768px) {
            .navbar-mobile-container,
            .navbar-overlay {
                display: none;
            }
            .main-content {
                margin-top: 0;
            }
        }

        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
        }
        
        .tooltip {
            pointer-events: none;
        }
        
        .dropdown-menu:not(.app-topbar__dropdown) {
            max-height: 400px;
            overflow-y: auto;
        }

        @media (max-width: 767.98px) {
            .dropdown-menu:not(.app-topbar__dropdown) {
                position: fixed !important;
                top: auto !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                max-height: 60vh;
                overflow-y: auto;
                transform: none !important;
                margin: 0 !important;
                border-radius: 0 !important;
                border-top-left-radius: 0.5rem !important;
                border-top-right-radius: 0.5rem !important;
                box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.1);
                animation: slideUp 0.3s ease-out;
            }

            /* Asegurar que el dropdown no se corte en la parte inferior */
            .dropdown-menu:not(.app-topbar__dropdown).show {
                display: block;
                transform: translateY(0) !important;
            }

            /* Animación para el dropdown */
            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                }
                to {
                    transform: translateY(0);
                }
            }

            /* Ajustar el contenedor del dropdown para móviles */
            .dropdown {
                position: static !important;
            }

            /* Estilo para el backdrop del dropdown */
            .dropdown-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
            }
        }
    </style>
</head>
<body>
@php
    $usuarioNivelMaximo = Auth::check() && (int) (Auth::user()->usu_nivel ?? 0) === 999;
    $usuarioVeSeccionConfiguracion = Auth::check() && (
        (Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'ventas']))
        && (! userTieneBandejaSoloInformes() || Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'ventas']))
    );
    $usuarioVeMenuMobileConfig = Auth::check() && (
        Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'ventas'])
    );
@endphp

    
<div class="navbar-mobile-container">
    <nav class="navbar d-md-none shadow-sm">
        <div class="container-fluid d-flex justify-content-between align-items-center px-3">
            <a class="navbar-brand" href="
                @if(Auth::user()->usu_nivel >= 900)
                    {{ url('/dashboard') }}
                @elseif(userHasRole('laboratorio'))
                    {{ url('/mis-ordenes') }}
                @elseif(userHasRole('muestreador'))
                    {{ url('/mis-tareas') }}
                @elseif(userHasRole('coordinador_lab'))
                    {{ userTieneBandejaSoloInformes() ? url('/informes') : url('/dashboard/analisis') }}
                @elseif(userHasRole('coordinador_muestreo'))
                    {{ url('/dashboard/muestreo') }}
                @elseif(userHasRole('ventas'))
                    {{ url('/ventas') }}
                @elseif(userHasRole('coordinador_consul'))
                    {{ route('consultoria.index') }}
                @elseif(userHasRole('coordinador_mediciones'))
                    {{ route('mediciones.index') }}
                @elseif(userHasRole('asp'))
                    {{ route('asp.index') }}
                @elseif(userHasRole('clarke_fire'))
                    {{ route('clarke-fire.index') }}
                @elseif(userHasRole('firmador'))
                    {{ url('/informes') }}
                @elseif(userHasRole('facturador'))
                    {{ url('/facturacion') }}
                @endif
            ">
                <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="mobile-logo">
            </a>

            @if(Auth::user())
                <div class="d-flex align-items-center gap-2 navbar-actions">
                    <div class="dropdown notif-dropdown-wrap">
                        <a href="#" class="app-topbar__icon-btn position-relative" id="notificationsDropdownDesktop" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
                            <x-heroicon-o-bell id="notificationsBell" />
                            @php
                                $notificacionesNoLeidas = App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                                    ->where('leida', false)
                                    ->count();
                            @endphp
                            @if($notificacionesNoLeidas > 0)
                            <span class="position-absolute badge rounded-pill bg-danger app-topbar__icon-btn__badge">
                                {{ $notificacionesNoLeidas }}
                                <span class="visually-hidden">notificaciones no leídas</span>
                            </span>
                            @endif
                        </a>
                        @include('layouts.partials.notificaciones-dropdown')
                    </div>
        
                    
                    <div class="dropdown">
                        <a href="#" class="app-topbar__icon-btn" id="settingsDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Configuración">
                            <x-heroicon-o-cog-6-tooth />
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown" aria-labelledby="settingsDropdownMobile">
                            <li class="dropdown-header">
                                <span class="fw-semibold">Configuración</span>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                                    <x-heroicon-o-user-circle style="width: 16px; height: 16px;" />
                                    Perfil de usuario
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('auth.security', ['id' => trim(Auth::user()->usu_codigo)]) }}">
                                    <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                                    Seguridad y contraseña
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('auth.help', ['id' => trim(Auth::user()->usu_codigo)]) }}">
                                    <x-heroicon-o-question-mark-circle style="width: 16px; height: 16px;" />
                                    Ayuda y soporte
                                </a>
                            </li>
                        </ul>
                    </div>
                    @include('layouts.partials.hamburger-toggle')
                </div>
            @else
                @include('layouts.partials.hamburger-toggle', ['class' => 'ms-auto'])
            @endif

        </div>
    </nav>

    
    <div class="navbar-overlay" id="navbarOverlay"></div>

    <div class="mobile-navbar-menu" id="mobileNavbar">
        <nav class="nav flex-column app-mobile-nav">
            @if((userHasRole('muestreador') || userHasRole('laboratorio')) && Auth::user()->usu_nivel < 900)
                <p class="app-mobile-nav__label">Bandeja de trabajo</p>
                @if(userHasRole('muestreador'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/mis-tareas') }}">
                        <x-heroicon-o-beaker style="width: 18px; height: 18px;" />
                        Mis muestras
                    </a>
                @endif
                @if(userHasRole('laboratorio'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/mis-ordenes') }}">
                        <x-heroicon-o-clipboard-document-list style="width: 18px; height: 18px;" />
                        Mis análisis
                    </a>
                @endif
            @endif

            @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'ventas']))
                @if((userHasRole('muestreador') || userHasRole('laboratorio')) && Auth::user()->usu_nivel < 900)
                    <div class="app-mobile-nav__section"></div>
                @else
                    <p class="app-mobile-nav__label">Bandeja de trabajo</p>
                @endif

                @if(Auth::user()->usu_nivel >= 900)
                    <a class="nav-link mobile-nav-link" href="{{ url('/dashboard') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Dashboard
                    </a>
                @endif

                @if(userHasRole('coordinador_lab') && !userTieneBandejaSoloInformes())
                    <a class="nav-link mobile-nav-link" href="{{ url('/dashboard/analisis') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Dashboard Lab
                    </a>
                @endif

                @if(userHasRole('coordinador_muestreo'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/dashboard/muestreo') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Dashboard Muestreo
                    </a>
                @endif

                @if((Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab'])) && !userTieneBandejaSoloInformes())
                    <a class="nav-link mobile-nav-link" href="{{ url('/') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Cotizaciones
                    </a>
                @endif

                @if(userHasRole('ventas'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/ventas') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Cotizaciones
                    </a>
                @endif

                @if(userHasRole('ventas') || userHasRole('facturador'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/clientes') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Clientes
                    </a>
                @endif

                @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo') || userHasRole('cadena_custodia'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/muestras') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Muestras
                    </a>
                @endif

                @if((Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab')) && !userTieneBandejaSoloInformes())
                    <a class="nav-link mobile-nav-link" href="{{ url('/inventarios') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Inventario Lab
                    </a>
                @endif
                
                @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/inventarios-muestreo') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Inventario Muestreo
                    </a>
                @endif

                
                @if((Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab'])) && !userTieneBandejaSoloInformes())
                    <a class="nav-link mobile-nav-link" href="{{ url('/variables-requeridas') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Mediciones de Campo
                    </a>
                @endif

                @if($usuarioNivelMaximo)
                    <a class="nav-link mobile-nav-link" href="{{ url('/leyes-normativas') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Leyes y Normativas
                    </a>
                @endif
{{-- 
                @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/metodos') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Métodos
                    </a>
                @endif --}}

                @if(userPuedeCargarItems() && $usuarioVeMenuMobileConfig)
                    <a class="nav-link mobile-nav-link" href="{{ url('/items') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Determinaciones
                    </a>
                @endif

                @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas'))
                    <a class="nav-link mobile-nav-link" href="{{ route('condiciones-pago.index') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Condiciones de pago
                    </a>
                @endif


                @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                <a class="nav-link mobile-nav-link" href="{{ url('/vehiculos') }}">
                    <x-heroicon-o-truck style="width: 18px; height: 18px;" />
                    Vehiculos
                </a>
                @endif
            @endif

            @include('layouts.partials.nav-portales-canal', ['linkClass' => 'mobile-nav-link'])
            
            @if((Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab')) && !userTieneBandejaSoloInformes())
                <a class="nav-link mobile-nav-link" href="{{ url('/ordenes') }}">
                    <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                    Ordenes de Trabajo
                </a>
            @endif

            @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'firmador']) || userTieneBandejaSoloInformes())
                <a class="nav-link mobile-nav-link" href="{{ url('/informes') }}">
                    <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                    Informes
                </a>
            @endif

            @if(userPuedeCargarItems() && ! $usuarioVeMenuMobileConfig)
                <a class="nav-link mobile-nav-link" href="{{ url('/items') }}">
                    <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                    Determinaciones
                </a>
            @endif

            
            @if(Auth::user()->usu_nivel >= 900)
                <a class="nav-link mobile-nav-link" href="{{ url('/users') }}">
                    <x-heroicon-o-user style="width: 18px; height: 18px;" />
                    Usuarios
                </a>
            @endif

            <div class="app-mobile-nav__section">
                <p class="app-mobile-nav__label">Cuenta</p>
                <a class="nav-link mobile-nav-link" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                    <x-heroicon-o-user style="width: 18px; height: 18px;" />
                    Perfil
                </a>
            </div>

            @if (Auth::check())
                <div class="app-mobile-nav__logout">
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="app-mobile-nav__logout-btn">
                            <x-heroicon-o-arrow-left-on-rectangle style="width: 16px; height: 16px;" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            @endif
        </nav>
    </div>
</div>


<aside class="sidebar app-sidebar d-none d-md-flex flex-column">
    <div class="app-sidebar__brand">
        <a class="navbar-brand" href="
            @if(Auth::user()->usu_nivel >= 900)
                {{ url('/dashboard') }}
            @elseif(userHasRole('laboratorio'))
                {{ url('/mis-ordenes') }}
            @elseif(userHasRole('muestreador'))
                {{ url('/mis-tareas') }}
            @elseif(userHasRole('coordinador_lab'))
                {{ userTieneBandejaSoloInformes() ? url('/informes') : url('/dashboard/analisis') }}
            @elseif(userHasRole('coordinador_muestreo'))
                {{ url('/dashboard/muestreo') }}
            @elseif(userHasRole('ventas'))
                {{ url('/ventas') }}
            @elseif(userHasRole('coordinador_consul'))
                {{ route('consultoria.index') }}
            @elseif(userHasRole('coordinador_mediciones'))
                {{ route('mediciones.index') }}
            @elseif(userHasRole('asp'))
                {{ route('asp.index') }}
            @elseif(userHasRole('clarke_fire'))
                {{ route('clarke-fire.index') }}
            @endif
        ">
            <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="sidebar-logo">
        </a>
    </div>

    <div class="app-sidebar__scroll">
    <nav class="nav flex-column app-sidebar__nav w-100">

        @if(Auth::user())
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#bandejaTrabajo" aria-expanded="true">
                    <x-heroicon-o-ticket style="width: 14px; height: 14px;" />
                    <span>Bandeja de Trabajo</span>
                </button>
                
                <div id="bandejaTrabajo" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        @if((userHasRole('muestreador') || userHasRole('laboratorio')) && Auth::user()->usu_nivel < 900)
                            @if(userHasRole('muestreador'))
                                <a class="nav-link" href="{{ url('/mis-tareas') }}">
                                    Mis muestras
                                </a>
                            @endif
                            @if(userHasRole('laboratorio'))
                                <a class="nav-link" href="{{ url('/mis-ordenes') }}">
                                    Mis análisis
                                </a>
                            @endif
                        @endif

                        @if(Auth::user()->usu_nivel >= 900)
                            <a class="nav-link" href="{{ url('/dashboard') }}">
                                Dashboard
                            </a>
                        @endif

                        @if(userHasRole('coordinador_lab') && !userTieneBandejaSoloInformes())
                            <a class="nav-link" href="{{ url('/dashboard/analisis') }}">
                                Dashboard Lab
                            </a>
                        @endif

                        @if(userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/dashboard/muestreo') }}">
                                Dashboard Muestreo
                            </a>
                        @endif

                        @include('layouts.partials.nav-portales-canal')

                        @if(userHasRole('ventas'))
                            <a class="nav-link" href="{{ url('/ventas') }}">
                                Cotizaciones
                            </a>
                        @endif

                        @if(userHasRole('ventas') || userHasRole('facturador'))
                            <a class="nav-link" href="{{ url('/clientes') }}">
                                Clientes
                            </a>
                        @endif
                    
                        
                        
                        @if((Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab'])) && !userTieneBandejaSoloInformes())
                            <a class="nav-link" href="{{ url('/') }}">
                                Cotizaciones
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo') || userHasRole('cadena_custodia'))
                            <a class="nav-link" href="{{ url('/muestras') }}">
                                Muestras
                            </a>
                        @endif
                        
                        @if((Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab')) && !userTieneBandejaSoloInformes())
                            <a class="nav-link" href="{{ url('/ordenes') }}">
                                Ordenes de Trabajo
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'firmador']) || userTieneBandejaSoloInformes())
                            <a class="nav-link mobile-nav-link" href="{{ url('/informes') }}">
                                Informes
                            </a>
                        @endif

                        @if(userPuedeCargarItems() && ! $usuarioVeSeccionConfiguracion)
                            <a class="nav-link" href="{{ url('/items') }}">
                                Determinaciones
                            </a>
                        @endif

                        @if(userPuedeAutorizarFacturacion())
                            <a class="nav-link" href="{{ route('facturacion-revision.index') }}">
                                Revisión facturación
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('facturador'))
                            <a class="nav-link" href="{{ url('/facturacion') }}">
                                Facturación
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($usuarioVeSeccionConfiguracion)
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#configuracion" aria-expanded="true">
                    <x-heroicon-o-cog style="width: 14px; height: 14px;" />
                    <span>Configuración</span>
                </button>
                
                <div id="configuracion" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        @if((Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab')) && !userTieneBandejaSoloInformes())
                            <a class="nav-link" href="{{ url('/inventarios') }}">
                                Inventario Lab
                            </a>
                        @endif
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/inventarios-muestreo') }}">
                                Inventario Muestreo
                            </a>
                        @endif

                        @if((Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab'])) && !userTieneBandejaSoloInformes())
                            <a class="nav-link" href="{{ url('/variables-requeridas') }}">
                                Mediciones de Campo
                            </a>
                        @endif
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/vehiculos') }}">
                                Vehiculos
                            </a>
                        @endif

                        @if($usuarioNivelMaximo)
                            <a class="nav-link" href="{{ url('/leyes-normativas') }}">
                                Leyes y Normativas
                            </a>
                        @endif

                        {{-- @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas'))
                            <a class="nav-link" href="{{ url('/metodos') }}">
                                Métodos 
                            </a>
                        @endif --}}

                        @if(userPuedeCargarItems() && $usuarioVeSeccionConfiguracion)
                            <a class="nav-link" href="{{ url('/items') }}">
                                Determinaciones
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas'))
                            <a class="nav-link" href="{{ route('condiciones-pago.index') }}">
                                Condiciones de pago
                            </a>
                        @endif

                    </div>
                </div>
            </div>
        @endif

        @if(userHasRole('admin'))
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#administracion" aria-expanded="true">
                    <x-heroicon-o-cog-6-tooth style="width: 14px; height: 14px;" />
                    <span>Administración</span>
                </button>
                
                <div id="administracion" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        <a class="nav-link" href="{{ url('/dashboard') }}">
                            <x-heroicon-o-chart-bar style="width: 16px; height: 16px;" class="me-2" />
                            Dashboard
                        </a>
                        {{-- <a class="nav-link" href="{{ url('/metodos') }}">
                            <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="me-2" />
                            Métodos
                        </a> --}}
                        @if($usuarioNivelMaximo)
                            <a class="nav-link" href="{{ url('/leyes-normativas') }}">
                                <x-heroicon-o-scale style="width: 16px; height: 16px;" class="me-2" />
                                Leyes y Normativas
                            </a>
                        @endif
                        {{-- <a class="nav-link" href="{{ url('/variables') }}">
                            <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="me-2" />
                            Variables
                        </a> --}}
                    </div>
                </div>
            </div>
        @endif

        
        <div class="accordion-item">
            <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#seguridad" aria-expanded="true">
                <x-heroicon-o-lock-closed style="width: 14px; height: 14px;" />
                <span>Seguridad</span>
            </button>
            
            <div id="seguridad" class="accordion-collapse collapse show">
                <div class="accordion-body p-0">
                    @if(Auth::user()->usu_nivel >= 900)
                        <a class="nav-link" href="{{ url('/users') }}">
                            Usuarios
                        </a>
                    @endif
                    
                    <a class="nav-link" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                        Perfil
                    </a>
                </div>
            </div>
        </div>
    </nav>
    </div>
</aside>

@if (Auth::check())
    <nav class="navbar navbar-expand-md navbar-light d-none d-md-flex app-desktop-topbar">
        <div class="app-topbar__actions w-100 justify-content-end">
            <div class="dropdown notif-dropdown-wrap">
                <a href="#" class="app-topbar__icon-btn position-relative" id="notificationsDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
                    <x-heroicon-o-bell />
                        @php
                            $notificacionesNoLeidas = App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                            ->where('leida', false)
                            ->count();
                        @endphp
                        @if($notificacionesNoLeidas > 0)
                        <span class="position-absolute badge rounded-pill bg-danger app-topbar__icon-btn__badge">
                            {{ $notificacionesNoLeidas }}
                            <span class="visually-hidden">notificaciones no leídas</span>
                        </span>
                        @endif
                </a>
                @include('layouts.partials.notificaciones-dropdown')
            </div>

            
            <div class="dropdown">
                <a href="#" class="app-topbar__icon-btn" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Configuración">
                    <x-heroicon-o-cog-6-tooth />
                </a>
                <ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown" aria-labelledby="settingsDropdown">
                    <li class="dropdown-header">
                        <span class="fw-semibold">Configuración</span>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                            <x-heroicon-o-user-circle style="width: 16px; height: 16px;" />
                            Perfil de usuario
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('auth.security', ['id' => trim(Auth::user()->usu_codigo)]) }}">
                            <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                            Seguridad y contraseña
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('auth.help', ['id' => trim(Auth::user()->usu_codigo)]) }}">
                            <x-heroicon-o-question-mark-circle style="width: 16px; height: 16px;" />
                            Ayuda y soporte
                        </a>
                    </li>
                </ul>
            </div>

            
            <div class="dropdown">
                <a href="#" class="app-topbar__user-btn dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <x-heroicon-o-user />
                    <span>{{ Auth::user()->usu_descripcion }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end app-topbar__dropdown" aria-labelledby="userDropdown">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                            <x-heroicon-o-user style="width: 16px; height: 16px;" />
                            Ver Perfil
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                                <x-heroicon-o-arrow-left-on-rectangle style="width: 16px; height: 16px;" />
                                Cerrar Sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
@endif


<div class="main-content">
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    /**
     * Evita que queden contenedores/backdrops de SweetAlert2 o estilos en body
     * que bloqueen clics (p. ej. enlace "Volver") tras cerrar un alert con timer o encadenar Swal + Bootstrap modal.
     */
    window.limpiarResiduosSweetAlert2 = function () {
        try {
            if (window.Swal) {
                Swal.close();
            }
        } catch (e) {}
        document.querySelectorAll('.swal2-container').forEach(function (el) {
            el.remove();
        });
        document.body.classList.remove('swal2-shown', 'swal2-height-auto');
        document.documentElement.classList.remove('swal2-shown', 'swal2-height-auto');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('overflow');
        if (!document.querySelector('.modal.show')) {
            document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                b.remove();
            });
            document.body.classList.remove('modal-open');
        }
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@stack('scripts')
<script defer src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_API_KEY') }}&libraries=places"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileNavbar = document.getElementById('mobileNavbar');
        const navbarOverlay = document.getElementById('navbarOverlay');
        const toggler = document.getElementById('mobileNavbarToggler');
        const body = document.body;

        if (!mobileNavbar || !navbarOverlay || !toggler) {
            return;
        }

        
        function toggleMenu(show) {
            if (show) {
                mobileNavbar.classList.add('show');
                navbarOverlay.classList.add('show');
                body.classList.add('navbar-open');
                toggler.classList.add('is-active');
                toggler.setAttribute('aria-expanded', 'true');
                toggler.setAttribute('aria-label', 'Cerrar menú');
            } else {
                mobileNavbar.classList.remove('show');
                navbarOverlay.classList.remove('show');
                body.classList.remove('navbar-open');
                toggler.classList.remove('is-active');
                toggler.setAttribute('aria-expanded', 'false');
                toggler.setAttribute('aria-label', 'Abrir menú');
            }
        }

        
        toggler.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu(!mobileNavbar.classList.contains('show'));
        });

        
        navbarOverlay.addEventListener('click', function() {
            toggleMenu(false);
        });

        
        document.addEventListener('click', function(e) {
            if (mobileNavbar.classList.contains('show') && 
                !mobileNavbar.contains(e.target) && 
                e.target !== toggler && 
                !toggler.contains(e.target)) {
                toggleMenu(false);
            }
        });

        
        document.querySelectorAll('#mobileNavbar .nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!e.target.classList.contains('dropdown-toggle')) {
                    setTimeout(() => {
                        toggleMenu(false);
                    }, 300);
                }
            });
        });

        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileNavbar.classList.contains('show')) {
                toggleMenu(false);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Marcar ítem activo del sidebar
        const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
        document.querySelectorAll('.app-sidebar .nav-link[href], #mobileNavbar .nav-link[href]').forEach(function (link) {
            try {
                const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
                if (linkPath === currentPath || (linkPath !== '/' && currentPath.startsWith(linkPath + '/'))) {
                    link.classList.add('active');
                }
            } catch (e) { /* ignore malformed href */ }
        });

        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: tooltipTriggerEl.getAttribute('data-bs-placement') || 'bottom',
                delay: {show: 300, hide: 100}
            });
        });
        
        
        document.querySelectorAll('.text-truncate').forEach(el => {
            el.addEventListener('mouseenter', function() {
                const tooltip = bootstrap.Tooltip.getInstance(this);
                if (tooltip) {
                    tooltip.show();
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        ['notificationsDropdownMobile', 'notificationsDropdownDesktop'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('shown.bs.dropdown', function() {
                    // console.log('Dropdown ABIERTO en', id);
                    fetch('{{ route("notificaciones.marcar-leidas") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const badges = el.querySelectorAll('.badge');
                            badges.forEach(badge => badge.remove());
                            document.querySelectorAll('.notif-dropdown__item--unread').forEach(function (notif) {
                                notif.classList.remove('notif-dropdown__item--unread');
                                const dot = notif.querySelector('.notif-dropdown__dot');
                                if (dot) dot.remove();
                            });
                        }
                    })
                    .catch(error => console.error('Error:', error));
                });
            }
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
    // Inicializar acordeones
    const accordions = document.querySelectorAll('.accordion-button');
    
    // Cargar estado guardado
    accordions.forEach(button => {
        const target = button.getAttribute('data-bs-target');
        const storedState = localStorage.getItem(target);
        
        if (storedState === 'collapsed') {
            const collapse = bootstrap.Collapse.getInstance(target) || 
                            new bootstrap.Collapse(target, { toggle: false });
            collapse.hide();
        }
    });
    
    // Guardar estado al cambiar
    document.querySelectorAll('.accordion-collapse').forEach(collapse => {
        collapse.addEventListener('hidden.bs.collapse', function() {
            localStorage.setItem('#' + this.id, 'collapsed');
        });
        
        collapse.addEventListener('shown.bs.collapse', function() {
            localStorage.removeItem('#' + this.id);
        });
    });
});
</script>

    <!-- Global Scanner Listener for Zebra DS2208 -->
    <script>
        (function() {
            let barcodeBuffer = "";
            let lastKeyTime = Date.now();

            document.addEventListener('keydown', function(e) {
                const currentTime = Date.now();
                const diff = currentTime - lastKeyTime;

                // Los escáneres envían teclas muy rápido (usualmente < 20ms entre ellas)
                // Si pasa más de 50ms, asumimos que es escritura manual y reseteamos.
                if (diff > 50) {
                    barcodeBuffer = "";
                }

                if (e.key === 'Enter') {
                    if (barcodeBuffer.length > 10) {
                        // Detectar si el contenido es una URL de nuestro sistema
                        if (barcodeBuffer.includes('/muestras/show/') || 
                            barcodeBuffer.includes('/ordenes/show/') || 
                            barcodeBuffer.includes('/muestras/show-qr/') ||
                            barcodeBuffer.includes('/qr-universal/') ||
                            barcodeBuffer.includes('/qr-selector/') ||
                            barcodeBuffer.includes('/tareas-all/') ||
                            barcodeBuffer.includes('/ordenes-all/')) {
                            
                            // Mostrar indicador de carga
                            const loader = document.createElement('div');
                            loader.id = 'global-scan-loader';
                            loader.innerHTML = `
                                <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.9);z-index:99999;display:flex;flex-direction:column;justify-content:center;align-items:center;font-family:sans-serif;">
                                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                                    <h2 style="margin-top:20px; color:#333;">Procesando Escaneo...</h2>
                                    <p style="color:#666;">Redirigiendo a la muestra detectada</p>
                                </div>
                            `;
                            document.body.appendChild(loader);
                            
                            // Redirigir
                            window.location.href = barcodeBuffer;
                        }
                    }
                    barcodeBuffer = "";
                } else if (e.key.length === 1) {
                    barcodeBuffer += e.key;
                }

                lastKeyTime = currentTime;
            });
        })();
    </script>

</body>
</html>