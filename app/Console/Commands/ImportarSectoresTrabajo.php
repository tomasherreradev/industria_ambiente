<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SectoresTrabajoImport;

class ImportarSectoresTrabajo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usuarios:importar-sectores {archivo?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar sectores de trabajo desde un archivo Excel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $archivo = $this->argument('archivo') ?? public_path('usuarios_sectores.xlsx');

        if (!file_exists($archivo)) {
            $this->error("El archivo no existe: {$archivo}");
            return 1;
        }

        $this->info("Importando sectores de trabajo desde: {$archivo}");
        $this->newLine();

        try {
            $import = new SectoresTrabajoImport();
            Excel::import($import, $archivo);

            $stats = $import->getStats();

            $this->info("✓ Usuarios actualizados: {$stats['actualizados']}");
            
            if (!empty($stats['no_encontrados'])) {
                $this->warn("⚠ Usuarios no encontrados: " . count($stats['no_encontrados']));
                foreach ($stats['no_encontrados'] as $codigo) {
                    $this->line("  - {$codigo}");
                }
            }

            if (!empty($stats['errores'])) {
                $this->error("✗ Errores: " . count($stats['errores']));
                foreach ($stats['errores'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            $this->newLine();
            $this->info("Importación completada.");

            return 0;

        } catch (\Exception $e) {
            $this->error("Error durante la importación: " . $e->getMessage());
            return 1;
        }
    }
}
