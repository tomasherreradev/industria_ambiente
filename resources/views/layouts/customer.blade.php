<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Panel de Cliente')</title>
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


<div class="sidebar d-none d-md-flex flex-column p-3 shadow-sm">
    <a class="navbar-brand mb-3" href="{{ url('/customers') }}">
        <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" class="sidebar-logo">
    </a>
    
    <nav class="nav flex-column w-100 px-2">
        
        @if(Auth::user())
            <div class="accordion-item">
                <button class="accordion-button nav-group-title" type="button" data-bs-toggle="collapse" data-bs-target="#seguridad">
                    Seguridad
                    <x-heroicon-o-lock-closed style="width: 16px; height: 16px;" class="ms-2" />
                </button>
                
                <div id="seguridad" class="accordion-collapse collapse show">
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

@if (Auth::check())
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm d-none d-md-flex justify-content-end px-4" style="margin-left: var(--sidebar-width); padding: 1rem 0;">
        
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

