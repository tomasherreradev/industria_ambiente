<?php

namespace App\Console\Commands;

use App\Models\CotioInstancia;
use Illuminate\Console\Command;

class ActualizarEstadoAnalisisPorNombre extends Command
{
    protected $signature = 'analisis:actualizar-estado-por-nombre
                            {cotizacion : Número de cotización (cotio_numcoti)}
                            {item : Ítem de cotización (cotio_item)}
                            {instance : Número de instancia (instance_number)}
                            {--nombre=* : Fragmento del nombre del análisis (repetible)}
                            {--estado=coordinado analisis : Estado de análisis a asignar}
                            {--dry-run : Listar coincidencias sin guardar}';

    protected $description = 'Actualiza cotio_estado_analisis de análisis (subitem > 0) que coincidan por nombre en una OT';

    public function handle(): int
    {
        $cotizacion = (int) $this->argument('cotizacion');
        $item = (int) $this->argument('item');
        $instance = (int) $this->argument('instance');
        $nombres = array_filter(array_map(
            fn ($n) => mb_strtolower(trim((string) $n)),
            $this->option('nombre')
        ));
        $estado = trim((string) $this->option('estado'));
        $dryRun = (bool) $this->option('dry-run');

        if ($nombres === []) {
            $this->error('Indique al menos un --nombre=');

            return self::FAILURE;
        }

        $analisis = CotioInstancia::query()
            ->where('cotio_numcoti', $cotizacion)
            ->where('cotio_item', $item)
            ->where('instance_number', $instance)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_subitem')
            ->get();

        if ($analisis->isEmpty()) {
            $this->warn("No hay análisis para OT {$cotizacion}/{$item}/{$instance}.");

            return self::SUCCESS;
        }

        $this->info("Análisis en OT {$cotizacion}/{$item}/{$instance}:");
        foreach ($analisis as $a) {
            $this->line("  [{$a->id}] subitem={$a->cotio_subitem} | {$a->cotio_estado_analisis} | {$a->cotio_descripcion}");
        }

        $coincidencias = $analisis->filter(function (CotioInstancia $a) use ($nombres) {
            $desc = mb_strtolower(trim((string) $a->cotio_descripcion));

            foreach ($nombres as $nombre) {
                if ($nombre !== '' && str_contains($desc, $nombre)) {
                    return true;
                }
            }

            return false;
        })->values();

        if ($coincidencias->isEmpty()) {
            $this->warn('No se encontraron análisis que coincidan con los nombres indicados.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($dryRun ? 'Coincidencias (dry-run, sin guardar):' : 'Actualizando:');

        foreach ($coincidencias as $a) {
            $antes = $a->cotio_estado_analisis ?? '(vacío)';
            if ($dryRun) {
                $this->line("  [{$a->id}] {$a->cotio_descripcion}: {$antes} → {$estado}");
                continue;
            }

            $a->cotio_estado_analisis = $estado;
            $a->save();
            $this->line("  [{$a->id}] {$a->cotio_descripcion}: {$antes} → {$estado}");
        }

        $this->newLine();
        $this->info($dryRun
            ? "Total coincidencias: {$coincidencias->count()}"
            : "Total actualizados: {$coincidencias->count()}");

        return self::SUCCESS;
    }
}
