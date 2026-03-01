<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use App\Observers\CotioInstanciaObserver;
use App\Models\CotioInstancia;  
use App\Models\CotioValorVariable;
use App\Observers\CotioValorVariableObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cargar helpers lo antes posible
        if (file_exists(app_path('helpers.php'))) {
            require_once app_path('helpers.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') || str_contains(config('app.url'), 'ngrok-free.app')) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', true);
        }
    
        Paginator::useBootstrapFive();

        CotioInstancia::observe(CotioInstanciaObserver::class);
        CotioValorVariable::observe(CotioValorVariableObserver::class);
        
        // Cargar helpers manualmente si no se cargaron automáticamente
        if (!function_exists('userHasRole')) {
            require_once app_path('helpers.php');
        }
    }
}
