<?php

namespace App\Console\Commands;

use App\Imports\ClienteEmailsRawImport;
use App\Support\ClienteEmailsImportNoEncontradosLog;
use App\Support\ClienteEmailsImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ImportarClienteEmailsDesdeExcel extends Command
{
    protected $signature = 'clientes:importar-emails
                            {archivo? : Ruta al .xlsx (por defecto public/mails_clientes.xlsx)}
                            {--dry-run : Simula sin insertar en cliente_contactos}
                            {--sin-log : No genera archivo de log ni CSV de no encontrados}';

    protected $description = 'Importa emails de factura y cobranza desde el Excel "Mails Clientes" hacia cliente_contactos';

    public function handle(
        ClienteEmailsImportService $service,
        ClienteEmailsImportNoEncontradosLog $noEncontradosLog,
    ): int {
        $archivo = $this->argument('archivo') ?? public_path('mails_clientes.xlsx');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('---------------------------------------------------------');
        $this->info('IMPORTACIÓN DE EMAILS DE CLIENTES');
        $this->info('---------------------------------------------------------');
        $this->info("Archivo: {$archivo}");
        $this->info($dryRun ? 'Modo: simulación (dry-run)' : 'Modo: inserción en base de datos');

        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");
            $this->line('');
            $this->line('Descargá el Google Sheet como Excel:');
            $this->line('  Archivo → Descargar → Microsoft Excel (.xlsx)');
            $this->line('Guardalo como: public/mails_clientes.xlsx');
            $this->line('O pasá la ruta: php artisan clientes:importar-emails "ruta/al/archivo.xlsx"');

            return self::FAILURE;
        }

        try {
            $collection = Excel::toCollection(new ClienteEmailsRawImport(), $archivo)->first();
            if (!$collection || $collection->isEmpty()) {
                $this->error('El archivo no tiene filas de datos.');

                return self::FAILURE;
            }

            $stats = $service->procesarFilas($collection, $dryRun);

            $clientesNo = $stats['clientes_no_encontrados'];
            $sucursalesNo = $stats['sucursales_no_encontradas'];

            $this->info('---------------------------------------------------------');
            $this->info('RESULTADOS');
            $this->info('---------------------------------------------------------');
            $this->info('Contactos ' . ($dryRun ? 'a crear' : 'creados') . ": {$stats['insertados']}");
            $this->info("Omitidos (ya existían): {$stats['omitidos_duplicado']}");
            $this->info("Omitidos (sin email / PORTAL): " . ($stats['omitidos_sin_email'] + $stats['omitidos_portal']));
            $this->info('Clientes principales NO encontrados: ' . count($clientesNo));
            $this->info('Sucursales NO encontradas: ' . count($sucursalesNo));

            if (!$this->option('sin-log')) {
                $archivos = $noEncontradosLog->escribir($stats, $archivo, $dryRun);
                $this->info('---------------------------------------------------------');
                $this->info('LOG DE NO ENCONTRADOS');
                $this->info('---------------------------------------------------------');
                $this->line("  Log (texto): {$archivos['log_path']}");
                $this->line("  CSV (Excel): {$archivos['csv_path']}");
                if (!is_file($archivos['csv_path'])) {
                    $this->error('  El CSV no se generó en disco. Revisá permisos en storage/logs/importacion_emails_clientes/');
                }

                if ($archivos['total'] === 0) {
                    $this->info('  Todos los clientes y sucursales del Excel fueron identificados en la base.');
                } else {
                    $this->warn("  Hay {$archivos['total']} registro(s) pendiente(s). Abrí el log o el CSV para ver el detalle.");
                }
            }

            if (count($clientesNo) > 0) {
                $this->warn('Ejemplos — clientes no encontrados:');
                foreach (array_slice($clientesNo, 0, 5) as $c) {
                    $this->line("  Fila {$c['fila']} | {$c['codigo_excel']} | {$c['cliente']}");
                }
            }

            if (count($sucursalesNo) > 0) {
                $this->warn('Ejemplos — sucursales no encontradas:');
                foreach (array_slice($sucursalesNo, 0, 5) as $s) {
                    $this->line(sprintf(
                        '  Fila %s | %s | SUC "%s" | %s',
                        $s['fila'],
                        $s['cliente'],
                        $s['etiqueta_suc'],
                        $s['motivo']
                    ));
                }
            }

            Log::info('Importación emails clientes', [
                'insertados' => $stats['insertados'],
                'clientes_no_encontrados' => count($clientesNo),
                'sucursales_no_encontradas' => count($sucursalesNo),
            ]);

            if ($clientesNo !== [] || $sucursalesNo !== []) {
                $this->warn('Finalizado con pendientes. Revisá el log antes de cerrar la importación.');

                return self::FAILURE;
            }

            $this->info($dryRun ? 'Simulación OK. Ejecutá sin --dry-run para guardar.' : 'Importación completada.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('clientes:importar-emails', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return self::FAILURE;
        }
    }
}
