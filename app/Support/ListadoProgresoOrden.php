<?php

namespace App\Support;

use App\Models\Coti;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Orden por columna Progreso en listados.
 *
 * Estados mixtos: se ordena por % de ítems finalizados (analizado / muestreado / con informe).
 * Desempate: % de avance en curso (incluye coordinadas, en revisión, etc.).
 * Sin ítems: siempre al final.
 */
final class ListadoProgresoOrden
{
    public const COLUMNA = 'progreso';

    /** @var list<string> */
    public const TIPOS_FINALIZACION = ['muestreo', 'ordenes', 'mediciones'];

    /**
     * @param  array<string, mixed>  $orden  Entrada de OrdenesLaboratorioListado::construirOrdenesDesdeCotizaciones
     * @return array{total: int, finalizadas: float, avance: float, coti_num: int}
     */
    public static function metricasDesdeOrdenLaboratorio(array $orden): array
    {
        $muestras = collect($orden['muestras_relevantes'] ?? []);
        $total = $muestras->count();
        $cotiNum = (int) ($orden['cotizacion']->coti_num ?? 0);

        if ($total === 0) {
            return self::metricasVacias($cotiNum);
        }

        $analizadas = $muestras->where('cotio_estado_analisis', 'analizado')->count();
        $enProceso = $muestras->where('cotio_estado_analisis', 'en revision analisis')->count();
        $coordinadas = $muestras->where('cotio_estado_analisis', 'coordinado analisis')->count();

        return [
            'total' => $total,
            'finalizadas' => ($analizadas / $total) * 100,
            'avance' => (($analizadas + $enProceso + $coordinadas) / $total) * 100,
            'coti_num' => $cotiNum,
        ];
    }

    /**
     * @return array{total: int, finalizadas: float, avance: float, coti_num: int}
     */
    public static function metricasDesdeCoti(Coti $coti, string $tipoFinalizacion = 'muestreo'): array
    {
        $total = (int) ($coti->total_instancias ?? 0);
        $cotiNum = (int) $coti->coti_num;
        $porcentajes = is_array($coti->porcentaje_progreso ?? null) ? $coti->porcentaje_progreso : [];

        if ($total === 0) {
            return self::metricasVacias($cotiNum);
        }

        return match ($tipoFinalizacion) {
            'mediciones' => [
                'total' => $total,
                'finalizadas' => (float) ($porcentajes['con_informe'] ?? 0),
                'avance' => (float) ($porcentajes['con_informe'] ?? 0),
                'coti_num' => $cotiNum,
            ],
            default => [
                'total' => $total,
                'finalizadas' => (float) ($porcentajes['muestreadas'] ?? 0),
                'avance' => (float) ($porcentajes['total'] ?? 0),
                'coti_num' => $cotiNum,
            ],
        };
    }

    /**
     * @param  Collection<int|string, array<string, mixed>>  $ordenes
     */
    public static function ordenarOrdenesLaboratorio(Collection $ordenes, string $dir): Collection
    {
        $metricas = $ordenes->mapWithKeys(function (array $orden, $key) {
            $cotiNum = (int) ($orden['cotizacion']->coti_num ?? $key);

            return [$cotiNum => self::metricasDesdeOrdenLaboratorio($orden)];
        });

        return self::ordenarPorMetricas($ordenes, $metricas, $dir, fn (array $orden) => (int) $orden['cotizacion']->coti_num);
    }

    /**
     * @param  Collection<int, Coti>  $cotizaciones
     */
    public static function ordenarCotizaciones(Collection $cotizaciones, string $dir, string $tipoFinalizacion = 'muestreo'): Collection
    {
        $metricas = $cotizaciones->mapWithKeys(function (Coti $coti) use ($tipoFinalizacion) {
            return [(int) $coti->coti_num => self::metricasDesdeCoti($coti, $tipoFinalizacion)];
        });

        return self::ordenarPorMetricas($cotizaciones, $metricas, $dir, fn (Coti $coti) => (int) $coti->coti_num);
    }

    public static function esOrdenPorProgreso(?string $sort): bool
    {
        return strtolower(trim((string) $sort)) === self::COLUMNA;
    }

    /**
     * @template T
     *
     * @param  Collection<int|string, T>  $items
     * @param  Collection<int, array{total: int, finalizadas: float, avance: float, coti_num: int}>  $metricas
     * @param  callable(T): int  $resolverClave
     * @return Collection<int|string, T>
     */
    private static function ordenarPorMetricas(Collection $items, Collection $metricas, string $dir, callable $resolverClave): Collection
    {
        $mult = strtolower($dir) === 'desc' ? -1 : 1;

        return $items->sort(function ($a, $b) use ($metricas, $mult, $resolverClave) {
            $ma = $metricas->get($resolverClave($a)) ?? self::metricasVacias($resolverClave($a));
            $mb = $metricas->get($resolverClave($b)) ?? self::metricasVacias($resolverClave($b));

            return self::compararMetricas($ma, $mb, $mult);
        })->values();
    }

    /**
     * @param  array{total: int, finalizadas: float, avance: float, coti_num: int}  $a
     * @param  array{total: int, finalizadas: float, avance: float, coti_num: int}  $b
     */
    private static function compararMetricas(array $a, array $b, int $mult): int
    {
        $sinDatos = fn (array $m): bool => ($m['total'] ?? 0) === 0;

        if ($sinDatos($a) && $sinDatos($b)) {
            return $a['coti_num'] <=> $b['coti_num'];
        }
        if ($sinDatos($a)) {
            return 1;
        }
        if ($sinDatos($b)) {
            return -1;
        }

        $cmp = $mult * ($a['finalizadas'] <=> $b['finalizadas']);
        if ($cmp !== 0) {
            return $cmp;
        }

        $cmp = $mult * ($a['avance'] <=> $b['avance']);
        if ($cmp !== 0) {
            return $cmp;
        }

        return $a['coti_num'] <=> $b['coti_num'];
    }

    /**
     * @return array{total: int, finalizadas: float, avance: float, coti_num: int}
     */
    private static function metricasVacias(int $cotiNum): array
    {
        return [
            'total' => 0,
            'finalizadas' => 0.0,
            'avance' => 0.0,
            'coti_num' => $cotiNum,
        ];
    }

    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    public static function paginar(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $items->count();
        $offset = ($page - 1) * $perPage;
        $results = $items->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }
}
