<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePuedeAutorizarFacturacion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->puedeAutorizarFacturacion()) {
            return $next($request);
        }

        abort(403, 'No tiene permiso para acceder a la revisión de facturación.');
    }
}
