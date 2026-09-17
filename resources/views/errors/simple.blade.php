<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error — {{ config('app.name') }}</title>
    <style>
        :root {
            --brand: #4e73df;
            --brand-dark: #2d5bd7;
            --ink: #1e293b;
            --muted: #64748b;
            --surface: #ffffff;
            --border: #e2e8f0;
            --danger-soft: #fef2f2;
            --danger: #ef4444;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(78, 115, 223, 0.12), transparent),
                radial-gradient(ellipse 60% 50% at 100% 100%, rgba(15, 118, 110, 0.08), transparent),
                #f8fafc;
        }

        .error-card {
            width: 100%;
            max-width: 26rem;
            padding: 2.5rem 2rem;
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 12px 40px rgba(15, 23, 42, 0.06);
        }

        .error-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 4rem;
            height: 4rem;
            margin-bottom: 1.25rem;
            border-radius: 50%;
            background: var(--danger-soft);
            color: var(--danger);
        }

        .error-icon svg {
            width: 1.75rem;
            height: 1.75rem;
        }

        .error-title {
            margin: 0 0 0.5rem;
            font-size: 1.375rem;
            font-weight: 600;
            letter-spacing: -0.02em;
        }

        .error-message {
            margin: 0 0 1.75rem;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--muted);
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6875rem 1.375rem;
            font-size: 0.9375rem;
            font-weight: 500;
            color: #fff;
            text-decoration: none;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
            border-radius: 0.625rem;
            box-shadow: 0 2px 8px rgba(78, 115, 223, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-home:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(78, 115, 223, 0.35);
            color: #fff;
        }

        .btn-home svg {
            width: 1.125rem;
            height: 1.125rem;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <main class="error-card">
        <div class="error-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
        </div>

        <h1 class="error-title">Algo salió mal</h1>

        <p class="error-message">
            Ocurrió un error inesperado. Intentá de nuevo en unos minutos.
        </p>

        <a href="{{ url('/') }}" class="btn-home">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
            </svg>
            Volver al inicio
        </a>
    </main>
</body>
</html>
