<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProbarHelperRoles extends Command
{
    protected $signature = 'app:probar-helper-roles';
    protected $description = 'Probar que los helpers de roles funcionan correctamente';

    public function handle()
    {
        $this->info('Verificando helpers de roles...');
        
        // Verificar que las funciones existen
        if (function_exists('userHasRole')) {
            $this->info('✓ userHasRole() está disponible');
        } else {
            $this->error('✗ userHasRole() NO está disponible');
        }
        
        if (function_exists('userHasAnyRole')) {
            $this->info('✓ userHasAnyRole() está disponible');
        } else {
            $this->error('✗ userHasAnyRole() NO está disponible');
        }
        
        // Intentar cargar manualmente si no están disponibles
        if (!function_exists('userHasRole')) {
            $this->warn('Intentando cargar helpers manualmente...');
            if (file_exists(app_path('helpers.php'))) {
                require_once app_path('helpers.php');
                $this->info('Helpers cargados manualmente');
                
                if (function_exists('userHasRole')) {
                    $this->info('✓ userHasRole() ahora está disponible');
                } else {
                    $this->error('✗ userHasRole() aún NO está disponible después de cargar manualmente');
                }
            } else {
                $this->error('✗ No se encontró el archivo app/helpers.php');
            }
        }
        
        return 0;
    }
}
