<?php

namespace App\Console\Commands;

use App\Mail\MuestraSinResponsableMail;
use App\Models\CotioInstancia;
use App\Models\SimpleNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class VerificarMuestrasSinResponsables extends Command
{
    protected $signature = 'app:verificar-muestras-sin-responsables';
    protected $description = 'Verifica muestras en muestreo sin responsables asignados y genera notificaciones a coordinadores';

    public function handle()
    {
        $this->info('Iniciando verificación de muestras sin responsables asignados...');
        
        try {
            $resultado = $this->verificarMuestrasSinResponsables();
            
            $this->info("Encontradas {$resultado['muestras_encontradas']} muestras sin responsables asignados");
            $this->info("Se crearon {$resultado['notificaciones_creadas']} notificaciones para {$resultado['coordinadores_notificados']} coordinadores");
            $this->info("Se enviaron {$resultado['emails_enviados']} emails a coordinadores con email configurado");
            
            if (!empty($resultado['errores'])) {
                foreach ($resultado['errores'] as $error) {
                    $this->warn("Error: {$error}");
                }
            }

            if ($resultado['muestras_encontradas'] > 0) {
                $this->newLine();
                $this->warn("⚠️  Muestras sin responsables asignados:");
                foreach ($resultado['muestras'] as $muestra) {
                    $this->warn("- {$muestra['cotio_descripcion']} (COTI {$muestra['cotio_numcoti']}, Muestra {$muestra['instance_number']})");
                }
            }

            Log::info("Comando de verificación de muestras sin responsables ejecutado. Notificaciones creadas: {$resultado['notificaciones_creadas']}, Emails enviados: {$resultado['emails_enviados']}");

            return 0;

        } catch (\Exception $e) {
            $this->error("Error al verificar muestras: " . $e->getMessage());
            Log::error("Error en comando de verificación de muestras sin responsables: " . $e->getMessage());
            return 1;
        }
    }

    private function verificarMuestrasSinResponsables()
    {
        $resultado = [
            'muestras_encontradas' => 0,
            'muestras' => [],
            'coordinadores_notificados' => 0,
            'notificaciones_creadas' => 0,
            'emails_enviados' => 0,
            'errores' => []
        ];

        try {
            // Obtener muestras que están en muestreo pero no tienen responsables asignados
            // Una muestra está "en muestreo" si:
            // 1. Tiene estado 'coordinado muestreo' O tiene fecha_muestreo asignada
            // 2. Es una muestra (cotio_subitem = 0)
            // 3. No tiene responsables asignados
            $muestrasSinResponsables = CotioInstancia::where('cotio_subitem', 0)
                ->where(function($query) {
                    $query->where('cotio_estado', 'coordinado muestreo')
                          ->orWhereNotNull('fecha_muestreo');
                })
                ->whereDoesntHave('responsablesMuestreo')
                ->with(['cotizacion'])
                ->get();

            $resultado['muestras_encontradas'] = $muestrasSinResponsables->count();

            if ($muestrasSinResponsables->isEmpty()) {
                $this->info('No se encontraron muestras sin responsables asignados.');
                return $resultado;
            }

            // Obtener todos los coordinadores de muestreo
            $coordinadores = User::where('rol', 'coordinador_muestreo')->get();

            if ($coordinadores->isEmpty()) {
                $resultado['errores'][] = 'No se encontraron coordinadores de muestreo en el sistema';
                return $resultado;
            }

            $resultado['coordinadores_notificados'] = $coordinadores->count();

            // Crear notificaciones para cada muestra sin responsables
            foreach ($muestrasSinResponsables as $muestra) {
                $muestraInfo = [
                    'cotio_descripcion' => $muestra->cotio_descripcion ?? 'Sin descripción',
                    'cotio_numcoti' => $muestra->cotio_numcoti,
                    'cotio_item' => $muestra->cotio_item,
                    'instance_number' => $muestra->instance_number,
                ];
                $resultado['muestras'][] = $muestraInfo;

                // Generar mensaje de notificación
                $mensaje = $this->generarMensajeNotificacion($muestra);

                // Crear notificación y enviar email para cada coordinador
                foreach ($coordinadores as $coordinador) {
                    try {
                        // Verificar si ya existe una notificación similar en las últimas 24 horas
                        $existeNotificacion = SimpleNotification::where('coordinador_codigo', $coordinador->usu_codigo)
                            ->where('instancia_id', $muestra->id)
                            ->where('mensaje', 'like', '%sin responsables asignados%')
                            ->where('created_at', '>', Carbon::now()->subDay())
                            ->exists();

                        if (!$existeNotificacion) {
                            // Generar URL para la notificación
                            $url = SimpleNotification::generarUrlPorRol($coordinador->usu_codigo, $muestra->id);
                            
                            // Crear notificación en el sistema
                            SimpleNotification::create([
                                'coordinador_codigo' => $coordinador->usu_codigo,
                                'sender_codigo' => 'SISTEMA',
                                'instancia_id' => $muestra->id,
                                'mensaje' => $mensaje,
                                'url' => $url,
                                'leida' => false,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            
                            $resultado['notificaciones_creadas']++;
                            Log::debug("Notificación creada para {$coordinador->usu_codigo} sobre muestra sin responsables: {$muestra->id}");
                            
                            // Enviar email si el coordinador tiene email configurado
                            if (!empty($coordinador->email) && filter_var($coordinador->email, FILTER_VALIDATE_EMAIL)) {
                                try {
                                    Mail::to($coordinador->email)->send(
                                        new MuestraSinResponsableMail($coordinador, $muestra, $url)
                                    );
                                    $resultado['emails_enviados']++;
                                    Log::debug("Email enviado a {$coordinador->email} sobre muestra sin responsables: {$muestra->id}");
                                } catch (\Exception $e) {
                                    $resultado['errores'][] = "Error al enviar email a {$coordinador->email}: " . $e->getMessage();
                                    Log::error("Error al enviar email a {$coordinador->email}: " . $e->getMessage());
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $resultado['errores'][] = "Error al crear notificación para {$coordinador->usu_codigo}: " . $e->getMessage();
                        Log::error("Error al crear notificación: " . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $resultado['errores'][] = "Error al verificar muestras: " . $e->getMessage();
            Log::error("Error en verificación de muestras sin responsables: " . $e->getMessage());
        }

        return $resultado;
    }

    private function generarMensajeNotificacion(CotioInstancia $muestra): string
    {
        $descripcion = $muestra->cotio_descripcion ?? 'Sin descripción';
        $cotiNum = $muestra->cotio_numcoti;
        $item = $muestra->cotio_item;
        $instance = $muestra->instance_number;
        $estado = $muestra->cotio_estado ?? 'Sin estado';
        
        $fechaInfo = '';
        if ($muestra->fecha_muestreo) {
            $fechaInfo = " - Coordinada el: " . Carbon::parse($muestra->fecha_muestreo)->format('d/m/Y H:i');
        }

        return "⚠️ MUESTRA SIN RESPONSABLES ASIGNADOS: {$descripcion} (COTI {$cotiNum}, Ítem {$item}, Muestra {$instance}) - Estado: {$estado}{$fechaInfo}";
    }
}
