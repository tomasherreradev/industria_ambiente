<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Iniciar sesión')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
    @stack('head')
</head>
<body class="login-page">
    @yield('content')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('togglePassword');
            const input = document.getElementById('usu_clave');
            if (!toggle || !input) return;

            toggle.addEventListener('click', function () {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                toggle.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                toggle.querySelector('[data-icon-show]').classList.toggle('d-none', isPassword);
                toggle.querySelector('[data-icon-hide]').classList.toggle('d-none', !isPassword);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
