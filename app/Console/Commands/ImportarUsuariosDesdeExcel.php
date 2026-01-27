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
    protected $description = 'Importa usuarios desde un archivo Excel. Por defecto usa public/usuarios.xlsx';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $archivo = $this->argument('archivo') ?? public_path('usuarios.xlsx');

        $this->info("---------------------------------------------------------");
        $this->info("IMPORTADOR DE USUARIOS");
        $this->info("---------------------------------------------------------");
        $this->info("Archivo: {$archivo}");

        if (!file_exists($archivo)) {
            $this->error("El archivo no existe: {$archivo}");
            Log::error("Archivo no encontrado: {$archivo}");
            return Command::FAILURE;
        }

        $this->info("Iniciando importación...");
        Log::info("=== Iniciando importación de usuarios ===");
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
            $this->info("RESULTADOS DE LA IMPORTACIÓN");
            $this->info("---------------------------------------------------------");
            $this->info("✓ Usuarios procesados correctamente: {$successCount}");
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
                $this->warn("\nLa importación se completó con algunos errores. Revisa los logs para más detalles.");
                return Command::FAILURE;
            }

            $this->info("\n✓ Importación completada exitosamente!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error al importar: " . $e->getMessage());
            Log::error("Error en importación de usuarios", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
