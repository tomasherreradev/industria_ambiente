<?php

namespace App\Console\Commands;

use App\Mail\MuestraSinResponsableMail;
use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProbarEnvioEmail extends Command
{
    protected $signature = 'app:probar-envio-email {email?}';
    protected $description = 'Prueba el envío de email de muestra sin responsable';

    public function handle()
    {
        $email = $this->argument('email');
        
        if (!$email) {
            // Buscar un coordinador con email para hacer la prueba
            $coordinador = User::where('rol', 'coordinador_muestreo')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->first();
            
            if (!$coordinador) {
                $this->error('No se encontró ningún coordinador con email configurado.');
                $this->info('Usa: php artisan app:probar-envio-email tu@email.com');
                return 1;
            }
            
            $email = $coordinador->email;
            $this->info("Usando email del coordinador: {$email}");
        }
        
        // Buscar una muestra de ejemplo
        $muestra = CotioInstancia::where('cotio_subitem', 0)->first();
        
        if (!$muestra) {
            $this->error('No se encontró ninguna muestra en el sistema.');
            return 1;
        }
        
        // Crear un usuario temporal para la prueba
        $coordinadorPrueba = new User();
        $coordinadorPrueba->usu_codigo = 'PRUEBA';
        $coordinadorPrueba->usu_descripcion = 'Usuario de Prueba';
        $coordinadorPrueba->email = $email;
        
        // Generar URL
        $url = route('muestras.ver', [
            'cotizacion' => $muestra->cotio_numcoti,
            'item' => $muestra->cotio_item,
            'instance' => $muestra->instance_number
        ], true);
        
        // Agregar puerto si es necesario (misma lógica que en SimpleNotification)
        if (app()->environment('local') && strpos($url, 'localhost') !== false && strpos($url, ':') === false) {
            $port = app()->runningInConsole() ? 8000 : (request()->getPort() ?? 8000);
            if ($port && $port != 80 && $port != 443) {
                $url = str_replace('http://localhost', "http://localhost:{$port}", $url);
                $url = str_replace('https://localhost', "https://localhost:{$port}", $url);
            }
        }
        
        $this->info("Enviando email de prueba a: {$email}");
        $this->info("Muestra: {$muestra->cotio_descripcion} (COTI {$muestra->cotio_numcoti})");
        $this->info("URL: {$url}");
        
        try {
            Mail::to($email)->send(
                new MuestraSinResponsableMail($coordinadorPrueba, $muestra, $url)
            );
            
            $this->info('✅ Email enviado exitosamente!');
            $this->info('Revisa tu bandeja de entrada (y spam) en: ' . $email);
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error al enviar email: ' . $e->getMessage());
            Log::error("Error al enviar email de prueba: " . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
