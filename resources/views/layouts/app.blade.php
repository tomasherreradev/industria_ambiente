<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Laboratorio')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    
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
            --sidebar-width: 250px;
            --navbar-height: 56px;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: padding 0.3s ease;
        }

        /* Navbar y Sidebar */
        .navbar, .sidebar {
            background-color: #ffffff;
        }

        .sidebar {
            border-right: 1px solid var(--gray-400);
        }

        .sidebar .nav-link {
            color: var(--dark-color);
            font-weight: 500;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease-in-out;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }

        .sidebar .nav-group-title {
            color: var(--gray-600);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            padding-left: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }


        /* Estilos para el acordeón del sidebar */
        .accordion-item {
            border: none;
            background: transparent;
            margin-bottom: 0.5rem;
        }

        .accordion-button {
            background: transparent !important;
            padding: 0.5rem 0;
            color: #495057;
            font-weight: 600;
            box-shadow: none !important;
        }

        .accordion-button:not(.collapsed) {
            color: #0d6efd;
        }

        .accordion-button::after {
            margin-left: auto;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23212529'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-button:not(.collapsed)::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230d6efd'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-body {
            padding: 0 !important;
        }

        /* Layout principal */
        .main-content {
            min-height: calc(100vh - var(--navbar-height));
        }

        @media (min-width: 768px) {
            .sidebar {
                width: var(--sidebar-width);
                height: 100vh;
                position: fixed;
                top: 0;
                left: 0;
                padding-top: 2rem;
                overflow-y: auto;
                overflow-x: hidden;
            }

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

        .sidebar-logo {
            width: 120px;
            margin-bottom: 2rem;
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
            z-index: 1030;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        #mobileNavbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            padding-top: var(--navbar-height);
            z-index: 1020;
            background-color: rgba(255, 255, 255, 0.98);
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        #mobileNavbar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-top: var(--navbar-height);
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
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1010;
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

        /* Mejoras visuales para móvil */
        .mobile-nav-link {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .mobile-nav-link svg {
            flex-shrink: 0;
        }

        .mobile-nav-link:hover {
            background-color: rgba(0, 0, 0, 0.03);
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
        
        .dropdown-menu {
            max-height: 400px;
            overflow-y: auto;
        }

        @media (max-width: 767.98px) {
            .dropdown-menu {
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
            .dropdown-menu.show {
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
                    {{ url('/dashboard/analisis') }}
                @elseif(userHasRole('coordinador_muestreo'))
                    {{ url('/dashboard/muestreo') }}
                @elseif(userHasRole('ventas'))
                    {{ url('/ventas') }}
                @elseif(userHasRole('firmador'))
                    {{ url('/informes') }}
                @elseif(userHasRole('facturador'))
                    {{ url('/facturacion') }}
                @endif
            ">
                <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="mobile-logo">
            </a>

            @if(Auth::user())
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a href="#" class="d-flex align-items-center text-decoration-none" id="notificationsDropdownDesktop" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="position-relative">
                                <x-heroicon-o-bell style="width: 18px; height: 18px;" class="text-gray-500" id="notificationsBell"/>
                                @php
                                    $notificacionesNoLeidas = App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                                        ->where('leida', false)
                                        ->count();
                                @endphp
                                @if($notificacionesNoLeidas > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                                    {{ $notificacionesNoLeidas }}
                                    <span class="visually-hidden">notificaciones no leídas</span>
                                </span>
                                @endif
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2" aria-labelledby="notificationsDropdown" style="width: 300px;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold">Notificaciones</span>
                                <small>
                                    <form action="{{ route('notificaciones.leer-todas') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link text-primary p-0">Marcar todas</button>
                                    </form>
                                </small>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            
                            @forelse(App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                                ->orderBy('created_at', 'desc')
                                ->take(5)
                                ->get() as $notificacion)
                            <li>


                                @if($notificacion->url)
                                    <a href="{{ $notificacion->url }}" class="dropdown-item py-2 {{ $notificacion->leida ? '' : 'bg-light' }}">
                                @else
                                    <span class="dropdown-item py-2 {{ $notificacion->leida ? '' : 'bg-light' }}">
                                @endif
                                        <div class="d-flex gap-2">
                                            <div class="flex-shrink-0 text-primary">
                                                <x-heroicon-o-bell style="width: 16px; height: 16px;" />
                                            </div>
                                            <div style="min-width: 0;">
                                                <p class="mb-0 small text-truncate" style="max-width: 220px;"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="bottom"
                                                    title="{{ $notificacion->mensaje }}">
                                                    {{ $notificacion->mensaje }}
                                                </p>
                                                <small class="text-muted">{{ $notificacion->created_at->diffForHumans() }}</small>
                                            </div>
                                        </div>
                                @if($notificacion->url)
                                    </a>
                                @else
                                    </span>
                                @endif
                            </li>
                            @empty
                            <li class="text-center py-2 text-muted">
                                <small>No hay notificaciones</small>
                            </li>
                            @endforelse
                        
                            
                            <li><hr class="dropdown-divider my-1"></li>
                            <li class="text-center">
                                <a href="{{ route('notificaciones.index') }}" class="dropdown-item small text-primary">Ver todas</a>
                            </li>
                        </ul>
                    </div>
        
                    
                    <div class="dropdown me-3">
                        <a href="#" class="d-flex align-items-center text-decoration-none" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <x-heroicon-o-cog-6-tooth style="width: 18px; height: 18px;" class="text-gray-500" />
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2" aria-labelledby="settingsDropdown">
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
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) . '/seguridad' }}">
                                    <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                                    Seguridad y contraseña
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) . '/ayuda' }}">
                                    <x-heroicon-o-question-mark-circle style="width: 16px; height: 16px;" />
                                    Ayuda y soporte
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif
                    <button class="navbar-toggler" type="button" id="mobileNavbarToggler" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
            </div>

        </div>
    </nav>

    
    <div class="navbar-overlay" id="navbarOverlay"></div>

    <div class="mobile-navbar-menu" id="mobileNavbar">
        <button class="close-menu-btn" id="closeMenuBtn" aria-label="Cerrar menú">
            &times;
        </button>
        
        <nav class="nav flex-column px-3 py-3">
            @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'ventas']))

                @if(Auth::user()->usu_nivel >= 900)
                    <a class="nav-link mobile-nav-link" href="{{ url('/dashboard') }}">
                        <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                        Dashboard
                    </a>
                @endif

                @if(userHasRole('coordinador_lab'))
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

                @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab']))
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

                @if(userHasRole('ventas'))
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

                @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab'))
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

                
                @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab']))
                    <a class="nav-link mobile-nav-link" href="{{ url('/variables-requeridas') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Mediciones de Campo
                    </a>
                @endif

                @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas') || userHasRole('coordinador_lab')) 
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

                @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas') || userHasRole('coordinador_lab'))
                    <a class="nav-link mobile-nav-link" href="{{ url('/items') }}">
                        <x-heroicon-o-cog style="width: 18px; height: 18px;" />
                        Determinaciones
                    </a>
                @endif


                @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                <a class="nav-link mobile-nav-link" href="{{ url('/vehiculos') }}">
                    <x-heroicon-o-truck style="width: 18px; height: 18px;" />
                    Vehiculos
                </a>
                @endif
            @endif
            
            @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab'))
                <a class="nav-link mobile-nav-link" href="{{ url('/ordenes') }}">
                    <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                    Ordenes de Trabajo
                </a>
            @endif

            @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'firmador']))
                <a class="nav-link mobile-nav-link" href="{{ url('/informes') }}">
                    <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                    Informes
                </a>
            @endif

            
            @if(Auth::user()->usu_nivel >= 900 || userHasRole('facturador'))
                <a class="nav-link mobile-nav-link" href="{{ url('/facturacion') }}">
                    <x-heroicon-o-ticket style="width: 18px; height: 18px;" />
                    Facturación
                </a>
            @endif

            @if(Auth::user()->usu_nivel >= 900)
                <a class="nav-link mobile-nav-link" href="{{ url('/users') }}">
                    <x-heroicon-o-user style="width: 18px; height: 18px;" />
                    Usuarios
                </a>
            @endif
            
            <a class="nav-link mobile-nav-link" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                <x-heroicon-o-user style="width: 18px; height: 18px;" />
                Perfil
            </a>
            
            @if (Auth::check())
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100">
                        Cerrar Sesión
                    </button>
                </form>
            @endif
        </nav>
    </div>
</div>


<div class="sidebar d-none d-md-flex flex-column p-3 shadow-sm">
    <a class="navbar-brand mb-3" href="
        @if(Auth::user()->usu_nivel >= 900)
            {{ url('/dashboard') }}
        @elseif(userHasRole('laboratorio'))
            {{ url('/mis-ordenes') }}
        @elseif(userHasRole('muestreador'))
            {{ url('/mis-tareas') }}
        @elseif(userHasRole('coordinador_lab'))
            {{ url('/dashboard/analisis') }}
        @elseif(userHasRole('coordinador_muestreo'))
            {{ url('/dashboard/muestreo') }}
        @elseif(userHasRole('ventas'))
            {{ url('/ventas') }}
        @endif
    ">
        <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="sidebar-logo">
    </a>
    
    <nav class="nav flex-column w-100 px-2">
        
        @if(Auth::user())
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#bandejaTrabajo">
                    Bandeja de Trabajo
                    <x-heroicon-o-ticket style="width: 16px; height: 16px;" class="ms-2" />
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

                        @if(userHasRole('coordinador_lab'))
                            <a class="nav-link" href="{{ url('/dashboard/analisis') }}">
                                Dashboard Lab
                            </a>
                        @endif

                        @if(userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/dashboard/muestreo') }}">
                                Dashboard Muestreo
                            </a>
                        @endif

                        @if(userHasRole('ventas'))
                            <a class="nav-link" href="{{ url('/ventas') }}">
                                Cotizaciones
                            </a>
                        @endif

                        @if(userHasRole('ventas'))
                            <a class="nav-link" href="{{ url('/clientes') }}">
                                Clientes
                            </a>
                        @endif
                    
                        
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab']))
                            <a class="nav-link" href="{{ url('/') }}">
                                Cotizaciones
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo') || userHasRole('cadena_custodia'))
                            <a class="nav-link" href="{{ url('/muestras') }}">
                                Muestras
                            </a>
                        @endif
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab'))
                            <a class="nav-link" href="{{ url('/ordenes') }}">
                                Ordenes de Trabajo
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'firmador']))
                            <a class="nav-link mobile-nav-link" href="{{ url('/informes') }}">
                                Informes
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('facturador'))
                            <a class="nav-link mobile-nav-link" href="{{ url('/facturacion') }}">
                                Facturación
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_lab', 'coordinador_muestreo', 'ventas']))
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#configuracion">
                    Configuración
                    <x-heroicon-o-cog style="width: 16px; height: 16px;" class="ms-2" />
                </button>
                
                <div id="configuracion" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_lab'))
                            <a class="nav-link" href="{{ url('/inventarios') }}">
                                Inventario Lab
                            </a>
                        @endif
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/inventarios-muestreo') }}">
                                Inventario Muestreo
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasAnyRole(['coordinador_muestreo', 'coordinador_lab']))
                            <a class="nav-link" href="{{ url('/variables-requeridas') }}">
                                Mediciones de Campo
                            </a>
                        @endif
                        
                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('coordinador_muestreo'))
                            <a class="nav-link" href="{{ url('/vehiculos') }}">
                                Vehiculos
                            </a>
                        @endif

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas') || userHasRole('coordinador_lab'))
                            <a class="nav-link" href="{{ url('/leyes-normativas') }}">
                                Leyes y Normativas
                            </a>
                        @endif

                        {{-- @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas'))
                            <a class="nav-link" href="{{ url('/metodos') }}">
                                Métodos 
                            </a>
                        @endif --}}

                        @if(Auth::user()->usu_nivel >= 900 || userHasRole('ventas') || userHasRole('coordinador_lab'))
                            <a class="nav-link" href="{{ url('/items') }}">
                                Determinaciones
                            </a>
                        @endif

                    </div>
                </div>
            </div>
        @endif

        @if(userHasRole('admin'))
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#administracion">
                    Administración
                    <x-heroicon-o-cog-6-tooth style="width: 16px; height: 16px;" class="ms-2" />
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
                        <a class="nav-link" href="{{ url('/leyes-normativas') }}">
                            <x-heroicon-o-scale style="width: 16px; height: 16px;" class="me-2" />
                            Leyes y Normativas
                        </a>
                        {{-- <a class="nav-link" href="{{ url('/variables') }}">
                            <x-heroicon-o-beaker style="width: 16px; height: 16px;" class="me-2" />
                            Variables
                        </a> --}}
                    </div>
                </div>
            </div>
        @endif

        
        <div class="accordion-item">
            <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#seguridad">
                Seguridad
                <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" class="ms-2" />
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

@if (Auth::check())
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm d-none d-md-flex justify-content-end px-4" style="margin-left: var(--sidebar-width); padding: 1rem 0;">
        <div class="dropdown me-3">
            <a href="#" class="d-flex align-items-center text-decoration-none" id="notificationsDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="position-relative">
                    <x-heroicon-o-bell style="width: 18px; height: 18px;" class="text-gray-500" />
                    @php
                        $notificacionesNoLeidas = App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                        ->where('leida', false)
                        ->count();
                    @endphp
                    @if($notificacionesNoLeidas > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">
                        {{ $notificacionesNoLeidas }}
                        <span class="visually-hidden">notificaciones no leídas</span>
                    </span>
                    @endif
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2" aria-labelledby="notificationsDropdown" style="width: 300px;">
                <li class="dropdown-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notificaciones</span>
                    <small>
                        <form action="{{ route('notificaciones.leer-todas') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link text-primary p-0">Marcar todas</button>
                        </form>
                    </small>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                
                @forelse(App\Models\SimpleNotification::where('coordinador_codigo', auth()->user()->usu_codigo)
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get() as $notificacion)
                <li>


                    @if($notificacion->url)
                        <a href="{{ $notificacion->url }}" class="dropdown-item py-2 {{ $notificacion->leida ? '' : 'bg-light' }}">
                    @else
                        <span class="dropdown-item py-2 {{ $notificacion->leida ? '' : 'bg-light' }}">
                    @endif

                        <div class="d-flex gap-2">
                            <div class="flex-shrink-0 text-primary">
                                <x-heroicon-o-bell style="width: 16px; height: 16px;" />
                            </div>
                            <div style="min-width: 0;">
                                <p class="mb-0 small text-truncate" 
                                style="max-width: 220px;"
                                data-bs-toggle="tooltip" 
                                data-bs-placement="bottom" 
                                title="{{ $notificacion->mensaje }}">
                                    {{ $notificacion->mensaje }}
                                </p>
                                <small class="text-muted">{{ $notificacion->created_at->diffForHumans() }}</small>
                            </div>
                        </div>

                    @if($notificacion->url)
                        </a>
                    @else
                        </span>
                    @endif
                </li>

                @empty
                <li class="text-center py-2 text-muted">
                    <small>No hay notificaciones</small>
                </li>
                @endforelse
                
                <li><hr class="dropdown-divider my-1"></li>
                <li class="text-center">
                    <a href="{{ route('notificaciones.index') }}" class="dropdown-item small text-primary">Ver todas</a>
                </li>
            </ul>
        </div>

        
        <div class="dropdown me-3">
            <a href="#" class="d-flex align-items-center text-decoration-none" id="settingsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <x-heroicon-o-cog-6-tooth style="width: 18px; height: 18px;" class="text-gray-500" />
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2" aria-labelledby="settingsDropdown">
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
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) . '/seguridad' }}">
                        <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" />
                        Seguridad y contraseña
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/auth/' . Auth::user()->usu_codigo) . '/ayuda' }}">
                        <x-heroicon-o-question-mark-circle style="width: 16px; height: 16px;" />
                        Ayuda y soporte
                    </a>
                </li>
            </ul>
        </div>

        
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <x-heroicon-o-user style="width: 18px; height: 18px;" class="text-gray-500" />
                <span class="ms-2 fw-semibold">{{ Auth::user()->usu_descripcion }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2" aria-labelledby="userDropdown">
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
    </nav>
@endif


<div class="main-content">
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script defer src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_API_KEY') }}&libraries=places"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileNavbar = document.getElementById('mobileNavbar');
        const navbarOverlay = document.getElementById('navbarOverlay');
        const toggler = document.getElementById('mobileNavbarToggler');
        const closeBtn = document.getElementById('closeMenuBtn');
        const body = document.body;

        
        function toggleMenu(show) {
            if (show) {
                mobileNavbar.classList.add('show');
                navbarOverlay.classList.add('show');
                body.classList.add('navbar-open');
            } else {
                mobileNavbar.classList.remove('show');
                navbarOverlay.classList.remove('show');
                body.classList.remove('navbar-open');
            }
        }

        
        toggler.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu(!mobileNavbar.classList.contains('show'));
        });

        
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu(false);
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
        
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
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
                            const notificacionesNoLeidas = document.querySelectorAll('.dropdown-item.bg-light');
                            notificacionesNoLeidas.forEach(notif => notif.classList.remove('bg-light'));
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

</body>
</html>