<?php

// app/Models/SimpleNotification.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SimpleNotification extends Model
{
    protected $fillable = [
        'coordinador_codigo', // receptor
        'sender_codigo',       // emisor (nuevo campo)
        'instancia_id',
        'mensaje',
        'url',
        'leida'
    ];

    public function coordinador()
    {
        return $this->belongsTo(User::class, 'coordinador_codigo', 'usu_codigo');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_codigo', 'usu_codigo');
    }

    public function instancia()
    {
        return $this->belongsTo(CotioInstancia::class, 'instancia_id');
    }

    /**
     * Genera la URL correspondiente según el rol del destinatario
     * Usa config('app.url') para asegurar que use el dominio correcto según el entorno
     */
    public static function generarUrlPorRol($coordinadorCodigo, $instanciaId = null)
    {
        $coordinador = User::where('usu_codigo', $coordinadorCodigo)->first();
        
        if (!$coordinador || !$instanciaId) {
            return null;
        }
    
        $instancia = CotioInstancia::find($instanciaId);
        if (!$instancia) {
            return null;
        }
    
        // Generar la ruta relativa
        $routeName = null;
        $routeParams = [];
        
        switch ($coordinador->rol) {
            case 'coordinador_lab':
                $routeName = 'categoria.verOrden';
                $routeParams = [
                    'cotizacion' => $instancia->cotio_numcoti,
                    'item' => $instancia->cotio_item,
                    'instance' => $instancia->instance_number
                ];
                break;
                
            case 'laboratorio':
                $routeName = 'ordenes.all.show';
                $routeParams = [
                    $instancia->cotio_numcoti,
                    $instancia->cotio_item,
                    $instancia->cotio_subitem,
                    $instancia->instance_number
                ];
                break;
                
            case 'coordinador_muestreo':
                $routeName = 'muestras.ver';
                $routeParams = [
                    'cotizacion' => $instancia->cotio_numcoti,
                    'item' => $instancia->cotio_item,
                    'instance' => $instancia->instance_number
                ];
                break;
                
            case 'muestreador':
                $routeName = 'tareas.all.show';
                $routeParams = [
                    $instancia->cotio_numcoti,
                    $instancia->cotio_item,
                    $instancia->cotio_subitem,
                    $instancia->instance_number
                ];
                break;
                
            default:
                return null;
        }
        
        if ($routeName) {
            try {
                // Generar la URL absoluta directamente usando route()
                // Esto asegura que use la configuración correcta de APP_URL del .env
                $url = route($routeName, $routeParams, true);
                
                // Si la URL generada es localhost sin puerto y estamos en desarrollo,
                // intentar detectar y agregar el puerto si es necesario
                if (app()->environment('local') && strpos($url, 'localhost') !== false && strpos($url, ':') === false) {
                    // Intentar obtener el puerto de la request si está disponible (contexto web)
                    // Si no está disponible (contexto consola), usar el puerto por defecto de Laravel (8000)
                    $port = null;
                    try {
                        if (app()->runningInConsole()) {
                            // Si estamos en consola, usar el puerto por defecto de desarrollo
                            $port = 8000;
                        } else {
                            // Si estamos en contexto web, obtener el puerto de la request
                            $port = request()->getPort();
                        }
                    } catch (\Exception $e) {
                        // Si hay algún error, usar puerto por defecto
                        $port = 8000;
                    }
                    
                    if ($port && $port != 80 && $port != 443) {
                        $url = str_replace('http://localhost', "http://localhost:{$port}", $url);
                        $url = str_replace('https://localhost', "https://localhost:{$port}", $url);
                    }
                }
                
                return $url;
            } catch (\Exception $e) {
                // Si hay error generando la ruta, retornar null
                Log::error("Error generando URL para notificación: " . $e->getMessage());
                return null;
            }
        }
        
        return null;
    }
}