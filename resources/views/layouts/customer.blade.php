<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Panel de Cliente')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}?v={{ filemtime(public_path('css/sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/topbar.css') }}?v={{ filemtime(public_path('css/topbar.css')) }}">
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
    </style>
</head>
<body>

    
<div class="navbar-mobile-container">
    <nav class="navbar d-md-none shadow-sm">
        <div class="container-fluid d-flex justify-content-between align-items-center px-3">
            <a class="navbar-brand" href="{{ url('/customers') }}">
                <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="mobile-logo">
            </a>

            @if(Auth::user())
                <div class="d-flex align-items-center">
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
                        </ul>
                    </div>
                @endif
                    @include('layouts.partials.hamburger-toggle')
            </div>

        </div>
    </nav>

    
    <div class="navbar-overlay" id="navbarOverlay"></div>

    <div class="mobile-navbar-menu" id="mobileNavbar">
        <nav class="nav flex-column px-3 py-3">
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#seguridad">
                    Seguridad
                    <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" class="ms-2" />
                </button>
                
                <div id="seguridad" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        <a class="nav-link mobile-nav-link" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                            <x-heroicon-o-user style="width: 18px; height: 18px;" />
                            Perfil
                        </a>
                    </div>
                </div>
            </div>
            
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


<aside class="sidebar app-sidebar d-none d-md-flex flex-column">
    <div class="app-sidebar__brand">
        <a class="navbar-brand" href="{{ url('/customers') }}">
            <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="sidebar-logo">
        </a>
    </div>

    <div class="app-sidebar__scroll">
        <nav class="nav flex-column app-sidebar__nav w-100">
            @if(Auth::user())
                <div class="accordion-item">
                    <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#seguridadDesktop" aria-expanded="true">
                        <x-heroicon-o-lock-closed style="width: 14px; height: 14px;" />
                        <span>Seguridad</span>
                    </button>

                    <div id="seguridadDesktop" class="accordion-collapse collapse show">
                        <div class="accordion-body p-0">
                            <a class="nav-link" href="{{ url('/auth/' . Auth::user()->usu_codigo) }}">
                                Perfil
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </nav>
    </div>
</aside>

@if (Auth::check())
    <nav class="navbar navbar-expand-md navbar-light d-none d-md-flex app-desktop-topbar">
        <div class="app-topbar__actions w-100 justify-content-end">
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
        const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
        document.querySelectorAll('.app-sidebar .nav-link[href]').forEach(function (link) {
            try {
                const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '') || '/';
                if (linkPath === currentPath || (linkPath !== '/' && currentPath.startsWith(linkPath + '/'))) {
                    link.classList.add('active');
                }
            } catch (e) { /* ignore malformed href */ }
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

