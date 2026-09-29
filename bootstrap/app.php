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
        $middleware->alias([
            'puede.cargar.items' => \App\Http\Middleware\EnsurePuedeCargarItems::class,
            'puede.autorizar.facturacion' => \App\Http\Middleware\EnsurePuedeAutorizarFacturacion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if (! config('app.debug') && ! $request->expectsJson()) {
                \Illuminate\Support\Facades\Log::error('HTTP 500: ' . $e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);

                return response()->view('errors.simple', [], 500);
            }

            return null;
        });
    })->create();
