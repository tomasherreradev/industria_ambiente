<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fuerza una única sesión activa por usuario.
 *
 * Estrategia compatible con passwords legacy (MD5) y SESSION_DRIVER=file:
 * - En login se guarda el session_id vigente en `usu.current_session_id`.
 * - En cada request web, si el usuario autenticado tiene otro session_id guardado,
 *   se cierra la sesión actual (significa que hubo un login posterior en otro dispositivo).
 */
class EnsureSingleDeviceSession
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $current = $request->session()->getId();
            $expected = (string) ($user->current_session_id ?? '');

            if ($expected !== '' && $current !== '' && !hash_equals($expected, $current)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login')
                    ->withErrors(['usu_codigo' => 'Tu sesión se cerró porque iniciaste sesión en otro dispositivo.']);
            }
        }

        return $next($request);
    }
}

