<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ImportarUsuariosDesdeExcel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usuarios:importar {archivo?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza roles de usuarios desde un archivo Excel. Por defecto usa public/usuarios.xlsx o public/usuarios_roles.xlsx';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Intentar usar usuarios_roles.xlsx primero, luego usuarios.xlsx como fallback
        $archivoDefault = file_exists(public_path('usuarios_roles.xlsx')) 
            ? public_path('usuarios_roles.xlsx')
            : public_path('usuarios.xlsx');
        
        $archivo = $this->argument('archivo') ?? $archivoDefault;

        $this->info("---------------------------------------------------------");
        $this->info("ACTUALIZADOR DE ROLES DE USUARIOS");
        $this->info("---------------------------------------------------------");
        $this->info("Archivo: {$archivo}");

        if (!file_exists($archivo)) {
            $this->error("El archivo no existe: {$archivo}");
            Log::error("Archivo no encontrado: {$archivo}");
            return Command::FAILURE;
        }

        $this->info("Iniciando actualización de roles...");
        $this->warn("NOTA: Solo se actualizarán usuarios existentes usando el código de usuario.");
        Log::info("=== Iniciando actualización de roles de usuarios ===");
        Log::info("Archivo: {$archivo}");

        try {
            $import = new UsersImport();
            
            // Importar usando toCollection para tener más control
            $collection = Excel::toCollection($import, $archivo)->first();
            
            if ($collection) {
                $import->collection($collection);
            }

            $successCount = $import->getSuccessCount();
            $errorCount = $import->getErrorCount();
            $errors = $import->getErrors();

            $this->info("---------------------------------------------------------");
            $this->info("RESULTADOS DE LA ACTUALIZACIÓN");
            $this->info("---------------------------------------------------------");
            $this->info("✓ Usuarios actualizados correctamente: {$successCount}");
            $this->info("✗ Errores: {$errorCount}");

            if (!empty($errors)) {
                $this->warn("\nErrores encontrados:");
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }
            }

            Log::info("Importación completada", [
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'errors' => $errors
            ]);

            if ($errorCount > 0) {
                $this->warn("\nLa actualización se completó con algunos errores. Revisa los logs para más detalles.");
                return Command::FAILURE;
            }

            $this->info("\n✓ Actualización de roles completada exitosamente!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al actualizar roles: " . $e->getMessage());
            Log::error("Error en actualización de roles de usuarios", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
