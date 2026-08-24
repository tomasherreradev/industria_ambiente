<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Carga acotada de instancias para listados de portales (/consultoria, /ventas, etc.).
 */
final class PortalListadoInstancias
{
    public const MAX_COPIAS_MUESTRA = 200;

    public static function cantidadSegura(mixed $raw): int
    {
        if (is_numeric($raw)) {
            $n = (int) round((float) $raw);
        } else {
            $s = str_replace(',', '.', trim((string) $raw));
            $n = is_numeric($s) ? (int) round((float) $s) : 1;
        }

        if ($n <= 0) {
            return 1;
        }

        if ($n > self::MAX_COPIAS_MUESTRA) {
            Log::warning('portal listado: cotio_cantidad acotada', [
                'original' => $raw,
                'usado' => self::MAX_COPIAS_MUESTRA,
            ]);

            return self::MAX_COPIAS_MUESTRA;
        }

        return $n;
    }

    /**
     * @param  array<int, array{num:int, item:int, instance:int}>  $claves
     * @return array<string, object>
     */
    public static function fetchPorClaves(array $claves, array $select): array
    {
        if ($claves === []) {
            return [];
        }

        $instMap = [];
        foreach (array_chunk($claves, 80) as $chunk) {
            $rows = DB::table('cotio_instancias')
                ->where('cotio_subitem', 0)
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $c) {
                        $q->orWhere(function ($q2) use ($c) {
                            $q2->where('cotio_numcoti', $c['num'])
                                ->where('cotio_item', $c['item'])
                                ->where('instance_number', $c['instance']);
                        });
                    }
                })
                ->select($select)
                ->get();

            foreach ($rows as $inst) {
                $k = sprintf(
                    '%d|%d|0|%d',
                    (int) $inst->cotio_numcoti,
                    (int) $inst->cotio_item,
                    (int) $inst->instance_number
                );
                $instMap[$k] = $inst;
            }
        }

        return $instMap;
    }

    /**
     * @return array<int, array{num:int, item:int, instance:int}>
     */
    public static function clavesDesdeEnsayos(int $cotiNum, iterable $ensayos): array
    {
        $claves = [];
        foreach ($ensayos as $m) {
            $cant = self::cantidadSegura($m->cotio_cantidad ?? 1);
            for ($i = 1; $i <= $cant; $i++) {
                $claves[] = [
                    'num' => $cotiNum,
                    'item' => (int) $m->cotio_item,
                    'instance' => $i,
                ];
            }
        }

        return $claves;
    }

    /**
     * @param  array<string, object>  $instMap
     */
    public static function instanciasEsperadasDesdeMap(int $cotiNum, iterable $ensayos, array $instMap): Collection
    {
        $col = collect();
        foreach ($ensayos as $m) {
            $cant = self::cantidadSegura($m->cotio_cantidad ?? 1);
            for ($i = 1; $i <= $cant; $i++) {
                $k = sprintf('%d|%d|0|%d', $cotiNum, (int) $m->cotio_item, $i);
                if (isset($instMap[$k])) {
                    $col->push($instMap[$k]);
                }
            }
        }

        return $col;
    }

    public static function totalCopiasEsperadas(iterable $ensayos): int
    {
        $total = 0;
        foreach ($ensayos as $m) {
            $total += self::cantidadSegura($m->cotio_cantidad ?? 1);
        }

        return $total;
    }
}
