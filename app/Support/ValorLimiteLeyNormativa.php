<?php

namespace App\Support;

use App\Models\Cotio;
use App\Models\CotioInstancia;
use App\Models\CotioItems;
use App\Models\Variable;
use Illuminate\Support\Collection;

/**
 * Valor límite de referencia según la ley/normativa de la muestra (cotio_subitem = 0),
 * cruzado con cada análisis por cotio_codigoprod o descripción del ítem.
 */
class ValorLimiteLeyNormativa
{
    /**
     * @return array{variablesLey: Collection, mapVariablePorDescripcion: Collection}
     */
    public static function contextoDesdeLineaMuestra(?Cotio $lineaMuestra): array
    {
        $variablesLey = collect();
        $mapVariablePorDescripcion = collect();

        if ($lineaMuestra === null) {
            return [
                'variablesLey' => $variablesLey,
                'mapVariablePorDescripcion' => $mapVariablePorDescripcion,
            ];
        }

        $ley = $lineaMuestra->leyNormativa;
        if ($ley === null) {
            return [
                'variablesLey' => $variablesLey,
                'mapVariablePorDescripcion' => $mapVariablePorDescripcion,
            ];
        }

        $variablesLey = $ley->relationLoaded('variables')
            ? $ley->variables
            : $ley->variables()->get();

        if ($variablesLey->isNotEmpty()) {
            $catalogIdsEnLey = $variablesLey->pluck('cotio_item_id')->filter()->unique()->toArray();
            $itemsCatalogo = CotioItems::whereIn('id', $catalogIdsEnLey)->get();

            foreach ($itemsCatalogo as $itemCat) {
                $variable = $variablesLey->firstWhere('cotio_item_id', $itemCat->id);
                if ($variable) {
                    $mapVariablePorDescripcion[trim(strtolower((string) $itemCat->cotio_descripcion))] = $variable;
                }
            }
        }

        return [
            'variablesLey' => $variablesLey,
            'mapVariablePorDescripcion' => $mapVariablePorDescripcion,
        ];
    }

    public static function variableParaAnalisis(
        CotioInstancia $analisis,
        Collection $variablesLey,
        Collection $mapVariablePorDescripcion
    ): ?Variable {
        $variableLey = $variablesLey->first(function ($v) use ($analisis) {
            $tarea = $analisis->relationLoaded('tarea') ? $analisis->tarea : $analisis->tarea()->first();
            if ($tarea === null) {
                return false;
            }
            $itemProdCode = trim((string) ($tarea->cotio_codigoprod ?? ''));
            $varCatalogId = trim((string) ($v->cotio_item_id ?? ''));

            return $itemProdCode !== '' && (int) $itemProdCode === (int) $varCatalogId;
        });

        if (! $variableLey) {
            $desc = trim(strtolower((string) ($analisis->cotio_descripcion ?? '')));
            $variableLey = $mapVariablePorDescripcion->get($desc);
        }

        return $variableLey;
    }

    public static function textoDesdeVariable(?Variable $variable): string
    {
        if ($variable === null) {
            return '—';
        }

        $valor = trim((string) ($variable->pivot->valor_limite ?? ''));
        $unidad = trim((string) ($variable->pivot->unidad_medida ?? ''));

        if ($valor === '') {
            return '—';
        }

        return $unidad !== '' ? $valor . ' ' . $unidad : $valor;
    }

    public static function textoParaAnalisis(?Cotio $lineaMuestra, CotioInstancia $analisis): string
    {
        $ctx = static::contextoDesdeLineaMuestra($lineaMuestra);
        $variable = static::variableParaAnalisis(
            $analisis,
            $ctx['variablesLey'],
            $ctx['mapVariablePorDescripcion']
        );

        return static::textoDesdeVariable($variable);
    }
}
