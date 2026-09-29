<?php

namespace App\Support;

use App\Models\CotioInstancia;
use App\Models\VariableRequerida;
use Illuminate\Support\Collection;

class VariablesMedicionInstancia
{
    /**
     * Valores de medición de campo de una instancia de muestra, enriquecidos con metadatos del catálogo.
     */
    public static function paraVista(?CotioInstancia $instancia): Collection
    {
        if (! $instancia) {
            return collect();
        }

        if (! $instancia->relationLoaded('valoresVariables')) {
            $instancia->load(['valoresVariables' => fn ($query) => $query->orderBy('variable')]);
        }

        $valores = $instancia->valoresVariables;
        if ($valores->isEmpty()) {
            return collect();
        }

        $catalogo = VariableRequerida::query()
            ->where('cotio_descripcion', $instancia->cotio_descripcion)
            ->get()
            ->keyBy(fn (VariableRequerida $variable) => self::normalizarNombre($variable->nombre));

        return $valores
            ->sortBy(fn ($row) => mb_strtolower((string) $row->variable))
            ->values()
            ->map(function ($row) use ($catalogo) {
                $meta = $catalogo->get(self::normalizarNombre($row->variable));
                $row->obligatorio = (bool) ($meta?->obligatorio ?? false);
                $row->unidad_medicion = $meta?->unidad_medicion;

                return $row;
            });
    }

    public static function normalizarNombre(?string $str): string
    {
        if (empty($str)) {
            return '';
        }

        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('/\s*\(.*\)\s*/u', '', $str);
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $str
        );

        return trim(preg_replace('/[^a-z0-9]/u', '', $str));
    }
}
