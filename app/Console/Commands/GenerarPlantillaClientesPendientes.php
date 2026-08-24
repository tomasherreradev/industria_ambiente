<?php

namespace App\Console\Commands;

use App\Exports\ClientesTemplateExport;
use App\Support\ClientesPlantillaPendientesBuilder;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class GenerarPlantillaClientesPendientes extends Command
{
    protected $signature = 'clientes:generar-plantilla-pendientes
                            {--origen= : Ruta al CSV de clientes faltantes}
                            {--salida= : Ruta del Excel de salida}';

    protected $description = 'Genera la plantilla de clientes precargada con el listado de pendientes';

    public function handle(): int
    {
        $origen = $this->option('origen')
            ?: storage_path('app/plantillas/clientes_faltantes_source.csv');
        $salida = $this->option('salida')
            ?: public_path('plantillas/plantilla_clientes_pendientes.xlsx');

        if (! is_file($origen)) {
            $this->error("No se encontró el CSV de origen: {$origen}");

            return self::FAILURE;
        }

        $datos = ClientesPlantillaPendientesBuilder::desdeCsv($origen);
        $directorio = dirname($salida);
        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $export = new ClientesTemplateExport(
            clientesRows: $datos['clientes'],
            contactosRows: $datos['contactos'],
            incluirEjemplosSecundarios: false,
        );

        $contenido = Excel::raw($export, ExcelFormat::XLSX);
        if ($contenido === '' || $contenido === false) {
            $this->error('No se pudo generar el archivo Excel.');

            return self::FAILURE;
        }

        file_put_contents($salida, $contenido);

        $this->info('Plantilla generada correctamente.');
        $this->line("Clientes: ".count($datos['clientes']));
        $this->line("Contactos: ".count($datos['contactos']));
        $this->line("Archivo: {$salida}");

        return self::SUCCESS;
    }
}
