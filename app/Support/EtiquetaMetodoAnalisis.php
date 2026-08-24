<?php

namespace App\Support;

use App\Models\Cotio;
use App\Models\CotioInstancia;
use App\Models\Metodo;
use App\Models\MetodoAnalisis;

/**
 * Texto y código de método para líneas de análisis en OT.
 * En cotizaciones legacy el código suele estar en cotio_codigometodo, no solo en cotio_codigometodo_analisis.
 */
final class EtiquetaMetodoAnalisis
{
    /**
     * @param  object  $tarea  Línea cotio (subitem > 0) o clon con métodos
     * @param  object|null  $instancia  CotioInstancia opcional (real o virtual)
     * @return array{codigo: string, etiqueta: string|null}
     */
    public static function resolver(object $tarea, ?object $instancia = null): array
    {
        if ($instancia === null && $tarea instanceof CotioInstancia && (int) $tarea->cotio_subitem > 0) {
            $instancia = $tarea;
            $cotioLinea = self::lineaCotioParaInstancia($instancia);
            $tarea = $cotioLinea ?? $instancia;
        } else {
            $instancia = $instancia ?? (isset($tarea->instancia) ? $tarea->instancia : null);
        }

        $codigo = self::codigo($tarea, $instancia);

        if ($codigo === '') {
            return ['codigo' => '', 'etiqueta' => null];
        }

        $etiqueta = self::etiquetaDesdeCodigo($codigo, $tarea, $instancia);

        return [
            'codigo' => $codigo,
            'etiqueta' => $etiqueta ?: $codigo,
        ];
    }

    public static function codigo(object $tarea, ?object $instancia = null): string
    {
        $instancia = $instancia ?? (isset($tarea->instancia) ? $tarea->instancia : null);

        foreach (self::candidatosCodigo($tarea, $instancia) as $valor) {
            $t = trim((string) $valor);
            if ($t !== '') {
                return $t;
            }
        }

        return '';
    }

    /**
     * @return array<int, mixed>
     */
    private static function candidatosCodigo(object $tarea, ?object $instancia): array
    {
        $lista = [];

        if ($instancia !== null) {
            $lista[] = $instancia->cotio_codigometodo_analisis ?? null;
            $lista[] = $instancia->cotio_codigometodo ?? null;
        }

        $lista[] = $tarea->cotio_codigometodo_analisis ?? null;
        $lista[] = $tarea->cotio_codigometodo ?? null;

        return $lista;
    }

    private static function etiquetaDesdeCodigo(string $codigo, object $tarea, ?object $instancia): ?string
    {
        if ($instancia !== null) {
            if (method_exists($instancia, 'getMetodoAnalisisConTrim')) {
                $legacy = $instancia->getMetodoAnalisisConTrim();
                if ($legacy?->metodo_descripcion) {
                    return $legacy->metodo_descripcion;
                }
            }
            if (method_exists($instancia, 'getMetodoMuestreoConTrim')) {
                $legacy = $instancia->getMetodoMuestreoConTrim();
                if ($legacy?->metodo_descripcion) {
                    return $legacy->metodo_descripcion;
                }
            }
        }

        if (isset($tarea->metodoAnalisis) && $tarea->metodoAnalisis) {
            $nombre = $tarea->metodoAnalisis->nombre ?: $tarea->metodoAnalisis->descripcion;
            if ($nombre) {
                return $nombre;
            }
        }

        if (isset($tarea->metodoLegacy) && $tarea->metodoLegacy) {
            if ($tarea->metodoLegacy->metodo_descripcion) {
                return $tarea->metodoLegacy->metodo_descripcion;
            }
        }

        if (isset($tarea->metodoMuestreo) && $tarea->metodoMuestreo) {
            $nombre = $tarea->metodoMuestreo->nombre ?? $tarea->metodoMuestreo->descripcion ?? null;
            if ($nombre) {
                return $nombre;
            }
        }

        $legacy = Metodo::whereRaw('TRIM(metodo_codigo) = ?', [$codigo])->first();
        if ($legacy?->metodo_descripcion) {
            return $legacy->metodo_descripcion;
        }

        $ma = MetodoAnalisis::whereRaw('TRIM(codigo) = ?', [$codigo])->first();
        if ($ma) {
            return $ma->nombre ?: $ma->descripcion;
        }

        return null;
    }

    private static function lineaCotioParaInstancia(CotioInstancia $instancia): ?Cotio
    {
        if ($instancia->relationLoaded('tarea') && $instancia->tarea instanceof Cotio) {
            return $instancia->tarea;
        }

        return Cotio::with(['metodoLegacy', 'metodoMuestreo', 'metodoAnalisis'])
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('cotio_subitem', $instancia->cotio_subitem)
            ->first();
    }
}
