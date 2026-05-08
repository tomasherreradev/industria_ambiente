<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminVolumenCosteosController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user || (int) $user->usu_nivel < 900) {
            abort(403);
        }

        $anio = (int) $request->get('anio', (int) now()->year);
        if ($anio < 2000 || $anio > (int) now()->year + 1) {
            $anio = (int) now()->year;
        }

        $soloAnalizados = $request->boolean('solo_analizados', true);

        $presets = config('admin_volumen_costeos.presets', []);
        $resultados = [];
        foreach ($presets as $clave => $def) {
            $resultados[$clave] = [
                'label' => $def['label'] ?? $clave,
                'help' => $def['help'] ?? null,
                'cantidad' => $this->contarInstanciasAnalisis($anio, $soloAnalizados, $def),
            ];
        }

        $aniosDisponibles = $this->aniosConActividad();

        return view('admin.volumen-costeos', [
            'anio' => $anio,
            'soloAnalizados' => $soloAnalizados,
            'resultados' => $resultados,
            'aniosDisponibles' => $aniosDisponibles,
        ]);
    }

    /**
     * @param  array<string, mixed>  $preset
     */
    private function contarInstanciasAnalisis(int $anio, bool $soloAnalizados, array $preset): int
    {
        $q = DB::table('cotio_instancias as ci')
            ->join('cotio as ca', function ($join) {
                $join->on('ca.cotio_numcoti', '=', 'ci.cotio_numcoti')
                    ->on('ca.cotio_item', '=', 'ci.cotio_item')
                    ->on('ca.cotio_subitem', '=', 'ci.cotio_subitem');
            })
            ->join('cotio as cm', function ($join) {
                $join->on('cm.cotio_numcoti', '=', 'ca.cotio_numcoti')
                    ->on('cm.cotio_item', '=', 'ca.cotio_item')
                    ->where('cm.cotio_subitem', '=', 0);
            })
            ->leftJoin('coti as co', 'co.coti_num', '=', 'ci.cotio_numcoti')
            ->leftJoin('matriz as mz', function ($join) {
                $join->on(DB::raw('TRIM(co.coti_codigomatriz)'), '=', DB::raw('TRIM(mz.matriz_codigo)'));
            })
            ->where('ci.cotio_subitem', '>', 0)
            ->where('ci.active_ot', true)
            ->where(function ($w) {
                $w->whereNull('ci.time_annulled')
                    ->orWhere('ci.time_annulled', '=', 0);
            })
            ->whereRaw('EXTRACT(YEAR FROM COALESCE(ci.fecha_carga_ot, ci.fecha_fin_ot, ci.updated_at)) = ?', [$anio]);

        if ($soloAnalizados) {
            $q->where('ci.cotio_estado_analisis', '=', 'analizado');
        }

        $q->where(function ($outer) use ($preset) {
            $tiene = false;
            foreach (['muestra_descripcion_like', 'analisis_descripcion_like', 'matriz_descripcion_like'] as $campo) {
                $patterns = $preset[$campo] ?? [];
                if (! is_array($patterns) || $patterns === []) {
                    continue;
                }
                $col = match ($campo) {
                    'muestra_descripcion_like' => 'cm.cotio_descripcion',
                    'analisis_descripcion_like' => 'ca.cotio_descripcion',
                    'matriz_descripcion_like' => 'mz.matriz_descripcion',
                    default => null,
                };
                if ($col === null) {
                    continue;
                }
                foreach ($patterns as $pat) {
                    $pat = (string) $pat;
                    if (trim($pat) === '') {
                        continue;
                    }
                    $tiene = true;
                    $outer->orWhereRaw(
                        'LOWER(TRIM('.$col.')) LIKE LOWER(?)',
                        [trim($pat)]
                    );
                }
            }
            if (! $tiene) {
                $outer->whereRaw('1 = 0');
            }
        });

        return (int) $q->count(DB::raw('DISTINCT ci.id'));
    }

    /**
     * @return int[]
     */
    private function aniosConActividad(): array
    {
        $rows = DB::table('cotio_instancias')
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->where(function ($w) {
                $w->whereNull('time_annulled')->orWhere('time_annulled', '=', 0);
            })
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM COALESCE(fecha_carga_ot, fecha_fin_ot, updated_at))::int as y')
            ->orderByDesc('y')
            ->pluck('y')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        if ($rows === []) {
            return [(int) now()->year];
        }

        return $rows;
    }
}
