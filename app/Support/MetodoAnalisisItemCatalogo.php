<?php

namespace App\Support;

use App\Models\Cotio;
use App\Models\CotioItems;
use App\Models\Metodo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Método de análisis desde el catálogo cotio_items.metodo (no confundir con metodo_muestreo).
 */
final class MetodoAnalisisItemCatalogo
{
    /** @var Collection<string, CotioItems>|null */
    private static ?Collection $itemsPorDescripcion = null;

    public static function reiniciarCache(): void
    {
        self::$itemsPorDescripcion = null;
    }

    public static function preload(): void
    {
        if (self::$itemsPorDescripcion !== null) {
            return;
        }

        self::$itemsPorDescripcion = CotioItems::query()
            ->where('es_muestra', false)
            ->with('metodoAnalitico')
            ->get()
            ->keyBy(fn (CotioItems $item) => self::claveDescripcion($item->cotio_descripcion));
    }

    public static function claveDescripcion(?string $descripcion): string
    {
        return Str::lower(trim((string) $descripcion));
    }

    public static function itemPorDescripcion(?string $descripcion): ?CotioItems
    {
        self::preload();

        $clave = self::claveDescripcion($descripcion);

        return $clave === '' ? null : self::$itemsPorDescripcion->get($clave);
    }

    public static function etiquetaDesdeItemCatalogo(CotioItems $item): string
    {
        $codigo = trim((string) ($item->metodo ?? ''));
        if ($codigo === '') {
            return '';
        }

        $metodo = $item->relationLoaded('metodoAnalitico')
            ? $item->metodoAnalitico
            : $item->metodoAnalitico()->first();

        if ($metodo) {
            $descripcion = trim((string) ($metodo->metodo_descripcion ?? ''));
            if ($descripcion !== '') {
                return $descripcion;
            }
        }

        $legacy = Metodo::whereRaw('TRIM(metodo_codigo) = ?', [$codigo])->first();

        return $legacy ? trim((string) ($legacy->metodo_descripcion ?? '')) : $codigo;
    }

    public static function etiquetaParaDescripcion(?string $descripcion): string
    {
        $item = self::itemPorDescripcion($descripcion);

        return $item ? self::etiquetaDesdeItemCatalogo($item) : '';
    }

    /**
     * Etiqueta de método de análisis para una línea cotio (componente).
     * Prioridad: cotio_items.metodo por descripción, luego códigos en la línea cotio.
     */
    public static function etiquetaParaLineaCotio(Cotio $linea): string
    {
        $desdeCatalogo = self::etiquetaParaDescripcion($linea->cotio_descripcion ?? '');
        if ($desdeCatalogo !== '') {
            return $desdeCatalogo;
        }

        $resuelto = EtiquetaMetodoAnalisis::resolver($linea);

        return trim((string) ($resuelto['etiqueta'] ?? ''));
    }
}
