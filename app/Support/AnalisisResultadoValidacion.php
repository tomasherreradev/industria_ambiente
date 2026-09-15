<?php

namespace App\Support;

use App\Models\CotioInstancia;
use Illuminate\Support\Collection;

/**
 * Reglas para marcar análisis / muestras como «analizado»: requiere resultados cargados.
 */
final class AnalisisResultadoValidacion
{
    /** @var array<int, string> */
    private const CAMPOS_RESULTADO = [
        'resultado',
        'resultado_2',
        'resultado_3',
        'resultado_final',
    ];

    public static function valorResultadoPresente(mixed $valor): bool
    {
        if ($valor === null) {
            return false;
        }

        return trim((string) $valor) !== '';
    }

    public static function instanciaTieneResultado(CotioInstancia $instancia): bool
    {
        foreach (self::CAMPOS_RESULTADO as $campo) {
            if (self::valorResultadoPresente($instancia->{$campo} ?? null)) {
                return true;
            }
        }

        return false;
    }

    public static function etiquetaInstancia(CotioInstancia $instancia): string
    {
        $desc = trim((string) ($instancia->cotio_descripcion ?? ''));
        if ($desc !== '') {
            return $desc;
        }

        if ((int) $instancia->cotio_subitem === 0) {
            return 'Muestra';
        }

        return 'Análisis #' . $instancia->cotio_subitem;
    }

    public static function mensajeInstanciaSinResultado(CotioInstancia $instancia): string
    {
        return self::etiquetaInstancia($instancia) . ' no tiene resultados cargados.';
    }

    /**
     * Análisis en OT activa de la misma muestra/instancia sin resultados.
     *
     * @return Collection<int, CotioInstancia>
     */
    public static function analisisEnOtSinResultado(
        string|int $cotioNumcoti,
        string|int $cotioItem,
        string|int $instanceNumber
    ): Collection {
        return CotioInstancia::query()
            ->where('cotio_numcoti', $cotioNumcoti)
            ->where('cotio_item', $cotioItem)
            ->where('instance_number', $instanceNumber)
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->get()
            ->filter(fn (CotioInstancia $instancia) => ! self::instanciaTieneResultado($instancia))
            ->values();
    }

    public static function instanciaEstaAnalizada(CotioInstancia $instancia): bool
    {
        return strtolower(trim((string) ($instancia->cotio_estado_analisis ?? ''))) === 'analizado';
    }

    /**
     * Análisis en OT activa de la misma muestra/instancia aún no finalizados (≠ analizado).
     *
     * @return Collection<int, CotioInstancia>
     */
    public static function analisisEnOtNoAnalizados(
        string|int $cotioNumcoti,
        string|int $cotioItem,
        string|int $instanceNumber
    ): Collection {
        return CotioInstancia::query()
            ->where('cotio_numcoti', $cotioNumcoti)
            ->where('cotio_item', $cotioItem)
            ->where('instance_number', $instanceNumber)
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->get()
            ->filter(fn (CotioInstancia $instancia) => ! self::instanciaEstaAnalizada($instancia))
            ->values();
    }

    /**
     * Todos los análisis activos en OT de la muestra están en estado «analizado».
     */
    public static function muestraTodosAnalisisOtAnalizados(
        string|int $cotioNumcoti,
        string|int $cotioItem,
        string|int $instanceNumber
    ): bool {
        return self::analisisEnOtNoAnalizados($cotioNumcoti, $cotioItem, $instanceNumber)->isEmpty();
    }

    /**
     * Análisis en OT de la muestra asignados a los sectores indicados.
     *
     * @return Collection<int, CotioInstancia>
     */
    public static function analisisEnOtDelSector(CotioInstancia $muestra, Collection $sectores): Collection
    {
        if ($sectores->isEmpty()) {
            return collect();
        }

        return CotioInstancia::query()
            ->where('cotio_numcoti', $muestra->cotio_numcoti)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('instance_number', $muestra->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->with('responsablesAnalisis')
            ->get()
            ->filter(fn (CotioInstancia $analisis) => OrdenesAccesoPorSector::instanciaTieneResponsablesEnSectores($analisis, $sectores))
            ->values();
    }

    public static function sectoresTodosAnalisisOtAnalizados(CotioInstancia $muestra, Collection $sectores): bool
    {
        $analisis = self::analisisEnOtDelSector($muestra, $sectores);

        if ($analisis->isEmpty()) {
            return false;
        }

        return $analisis->every(fn (CotioInstancia $analisis) => self::instanciaEstaAnalizada($analisis));
    }

    /**
     * Si todos los análisis en OT están «analizado», actualiza la muestra al mismo estado.
     */
    public static function sincronizarEstadoMuestraDesdeAnalisis(
        string|int $cotioNumcoti,
        string|int $cotioItem,
        string|int $instanceNumber
    ): bool {
        $muestra = CotioInstancia::query()
            ->where('cotio_numcoti', $cotioNumcoti)
            ->where('cotio_item', $cotioItem)
            ->where('cotio_subitem', 0)
            ->where('instance_number', $instanceNumber)
            ->first();

        if (! $muestra || ! self::muestraTodosAnalisisOtAnalizados($cotioNumcoti, $cotioItem, $instanceNumber)) {
            return false;
        }

        if (self::instanciaEstaAnalizada($muestra)) {
            return false;
        }

        $muestra->cotio_estado_analisis = 'analizado';
        if (! $muestra->fecha_carga_ot) {
            $muestra->fecha_carga_ot = now();
        }
        $muestra->save();

        return true;
    }

    public static function muestraPuedeMarcarseAnalizada(CotioInstancia $muestra): bool
    {
        return self::muestraTodosAnalisisOtAnalizados(
            $muestra->cotio_numcoti,
            $muestra->cotio_item,
            $muestra->instance_number
        );
    }

    /**
     * Todos los análisis activos en OT de la muestra tienen al menos un resultado cargado.
     * Usado para pasar la muestra a «en revision analisis» solo cuando todos los laboratorios
     * completaron la carga de resultados de sus análisis asignados.
     */
    public static function muestraTodosAnalisisOtConResultado(
        string|int $cotioNumcoti,
        string|int $cotioItem,
        string|int $instanceNumber
    ): bool {
        return self::analisisEnOtSinResultado($cotioNumcoti, $cotioItem, $instanceNumber)->isEmpty();
    }

    public static function mensajeMuestraNoAnalizable(CotioInstancia $muestra): string
    {
        $pendientes = self::analisisEnOtNoAnalizados(
            $muestra->cotio_numcoti,
            $muestra->cotio_item,
            $muestra->instance_number
        );

        if ($pendientes->isEmpty()) {
            return 'La muestra no puede marcarse como analizada.';
        }

        $nombres = $pendientes
            ->map(fn (CotioInstancia $instancia) => self::etiquetaInstancia($instancia))
            ->take(5)
            ->implode(', ');

        $extra = $pendientes->count() > 5 ? '…' : '';

        return 'La muestra solo puede marcarse como analizada cuando todos los análisis fueron finalizados explícitamente. Pendientes: '
            . $nombres
            . $extra
            . '.';
    }

    public static function analisisPuedeMarcarseAnalizado(CotioInstancia $instancia): bool
    {
        if ((int) $instancia->cotio_subitem === 0) {
            return self::muestraPuedeMarcarseAnalizada($instancia);
        }

        return self::instanciaTieneResultado($instancia);
    }

    public static function mensajeNoAnalizable(CotioInstancia $instancia): string
    {
        if ((int) $instancia->cotio_subitem === 0) {
            return self::mensajeMuestraNoAnalizable($instancia);
        }

        return self::mensajeInstanciaSinResultado($instancia);
    }

    /**
     * @param  Collection<int, CotioInstancia>  $instancias
     */
    public static function mensajeVariasSinResultado(Collection $instancias): string
    {
        $nombres = $instancias
            ->map(fn (CotioInstancia $instancia) => self::etiquetaInstancia($instancia))
            ->take(5)
            ->implode(', ');

        $extra = $instancias->count() > 5 ? '…' : '';

        return 'No se puede finalizar: faltan resultados en ' . $nombres . $extra . '.';
    }
}
