<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HelpersServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Cargar helpers lo antes posible, antes de que cualquier otra cosa se ejecute
        $helpersPath = app_path('helpers.php');
        if (file_exists($helpersPath)) {
            require_once $helpersPath;
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Asegurar que los helpers estén disponibles
        if (!function_exists('userHasRole')) {
            $helpersPath = app_path('helpers.php');
            if (file_exists($helpersPath)) {
                require_once $helpersPath;
            }
        }
    }
}
