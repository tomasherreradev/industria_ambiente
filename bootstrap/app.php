<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureSingleDeviceSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Solo mostrar vista personalizada cuando APP_DEBUG=false
        $exceptions->render(function (Throwable $e, $request) {
            // Solo para requests web (no API/JSON)
            if (!config('app.debug') && !$request->expectsJson()) {
                return response()->view('errors.simple', [], 500);
            }
            
            // Si APP_DEBUG=true, dejar que Laravel maneje normalmente
            return null;
        });
    })->create();
