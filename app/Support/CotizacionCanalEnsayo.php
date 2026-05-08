<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canal operativo por ensayo (cotio_subitem = 0), según matriz y/o descripción.
 * Valores: consultoria | asp | clarke_fire
 */
class CotizacionCanalEnsayo
{
    /**
     * Cotización (tabla coti) con al menos un ensayo del canal (columna persistida o heurística por descripción).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotiTieneEnsayoDelCanal($query, string $canal): void
    {
        $canal = strtolower(trim($canal));
        $query->whereExists(function ($sub) use ($canal) {
            $sub->select(DB::raw(1))
                ->from('cotio')
                ->whereColumn('cotio.cotio_numcoti', 'coti.coti_num')
                ->where('cotio.cotio_subitem', 0)
                ->where(function ($w) use ($canal) {
                    $w->where('cotio.cotio_canal_especial', $canal)
                        ->orWhere(function ($w2) use ($canal) {
                            $w2->whereNull('cotio.cotio_canal_especial');
                            if ($canal === 'consultoria') {
                                $w2->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%consultoria%']);
                            } elseif ($canal === 'clarke_fire') {
                                $w2->where(function ($w3) {
                                    $w3->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%clarke%fire%'])
                                        ->orWhereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%clarke fire%']);
                                });
                            } elseif ($canal === 'asp') {
                                $w2->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%asp%']);
                            } elseif ($canal === 'mediciones') {
                                $w2->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%mediciones%']);
                            }
                        });
                });
        });
    }

    /**
     * Usuario coordinador de un solo canal (muestreo / detalle de cotización), o null si no aplica restricción.
     *
     * @param  \App\Models\User|object|null  $user
     */
    public static function soloCanalUsuario($user): ?string
    {
        if (!$user || (int) ($user->usu_nivel ?? 0) >= 900) {
            return null;
        }
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('coordinador_consul')) {
                return 'consultoria';
            }
            if ($user->hasRole('asp')) {
                return 'asp';
            }
            if ($user->hasRole('clarke_fire')) {
                return 'clarke_fire';
            }
        }

        return null;
    }

    /**
     * Filtra filas cotio: solo ensayos del canal y sus componentes (mismo cotio_item).
     *
     * @param  Collection|array<int,\App\Models\Cotio>  $tareas
     * @return Collection<int,\App\Models\Cotio>
     */
    public static function filtrarTareasCotioPorCanal($tareas, ?string $canal, ?string $matrizDescripcionCoti = null): Collection
    {
        if ($canal === null || $canal === '') {
            return $tareas instanceof Collection ? $tareas : collect($tareas);
        }
        $col = $tareas instanceof Collection ? $tareas : collect($tareas);
        $itemsPermitidos = $col->where('cotio_subitem', 0)
            ->filter(function ($row) use ($canal, $matrizDescripcionCoti) {
                return self::cotioEnsayoCoincideCanal($row, $canal, $matrizDescripcionCoti);
            })
            ->pluck('cotio_item')
            ->unique();
        if ($itemsPermitidos->isEmpty()) {
            return $col->filter(static fn () => false)->values();
        }
        $perm = $itemsPermitidos->flip();

        return $col->filter(static function ($row) use ($perm) {
            return $perm->has($row->cotio_item);
        })->values();
    }

    /**
     * Fila cotio (ensayo, subitem 0) pertenece al canal indicado (persistido, matriz+descripción o heurística en descripción).
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function cotioEnsayoCoincideCanal($tarea, string $canal, ?string $matrizDescripcionCoti = null): bool
    {
        $canal = strtolower(trim($canal));
        $inferido = self::resolverDesdeEnsayoPayload([
            'canal_especial' => $tarea->cotio_canal_especial ?? null,
            'matriz_descripcion' => $matrizDescripcionCoti,
            'descripcion' => $tarea->cotio_descripcion ?? '',
        ]);
        if ($inferido !== null) {
            return $inferido === $canal;
        }
        $r = strtolower(trim((string) ($tarea->cotio_canal_especial ?? '')));
        if ($r === $canal) {
            return true;
        }
        if (in_array($r, ['consultoria', 'asp', 'clarke_fire', 'mediciones'], true)) {
            return false;
        }
        $desc = mb_strtolower(trim((string) ($tarea->cotio_descripcion ?? '')));
        if ($canal === 'consultoria') {
            return str_contains($desc, 'consultoria');
        }
        if ($canal === 'clarke_fire') {
            return str_contains($desc, 'clarke fire')
                || (str_contains($desc, 'clarke') && str_contains($desc, 'fire'))
                || str_contains($desc, 'clarke-fire');
        }
        if ($canal === 'asp') {
            return str_contains($desc, 'asp');
        }
        if ($canal === 'mediciones') {
            return str_contains($desc, 'mediciones');
        }

        return false;
    }

    public static function resolverDesdeEnsayoPayload(array $ensayo): ?string
    {
        if (!empty($ensayo['canal_especial'])) {
            $c = strtolower(trim((string) $ensayo['canal_especial']));
            if (in_array($c, ['consultoria', 'asp', 'clarke_fire', 'mediciones'], true)) {
                return $c;
            }
        }

        $mat = mb_strtolower(trim((string) ($ensayo['matriz_descripcion'] ?? '')));
        $desc = mb_strtolower(trim((string) ($ensayo['descripcion'] ?? '')));

        $hay = static function (string $needle) use ($mat, $desc): bool {
            return str_contains($mat, $needle) || str_contains($desc, $needle);
        };

        if ($hay('consultoria') 
            || $hay('determinacion de potabilidad') 
            || $hay('analisis de agua potable') 
            || $hay('analisis fisicoquimico y bacteriologico')
            || $hay('analisis de agua recreacional')
        ) {
            return 'consultoria';
        }

        if ($hay('clarke fire') 
            || $hay('clarke-fire') 
            || $hay('clarke_fire')
            || $hay('ensayo de agua de red de incendio')
        ) {
            return 'clarke_fire';
        }

        if ($hay('asp') 
            || $hay('control bacteriologico de aire')
        ) {
            return 'asp';
        }

        if ($hay('mediciones')) {
            return 'mediciones';
        }

        return null;
    }
}
