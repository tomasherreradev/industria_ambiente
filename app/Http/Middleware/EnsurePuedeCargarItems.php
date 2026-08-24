<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePuedeCargarItems
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return $next($request);
        }

        if ((bool) ($user->puede_cargar_items ?? false)) {
            return $next($request);
        }

        abort(403, 'No tiene permiso para gestionar determinaciones (items).');
    }
}
