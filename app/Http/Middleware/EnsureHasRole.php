<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        if ((int) ($user->usu_nivel ?? 0) < 900 && ! $user->hasRole($role)) {
            abort(403);
        }

        return $next($request);
    }
}
