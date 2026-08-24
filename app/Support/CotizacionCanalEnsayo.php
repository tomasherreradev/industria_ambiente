<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canal operativo por ensayo (cotio_subitem = 0), según matriz y/o descripción.
 * Valores: consultoria | asp | clarke_fire | mediciones
 */
class CotizacionCanalEnsayo
{
    /** Canales que no deben aparecer en /ordenes (laboratorio). */
    public const CANALES_EXCLUIDOS_ORDENES = ['consultoria', 'asp', 'clarke_fire', 'mediciones'];

    /** Canales con flujo directo a facturación (sin muestreo en campo). */
    public const CANALES_FACTURACION_DIRECTA = ['consultoria', 'asp', 'clarke_fire'];

    /** Portal consultoría: solo ensayos de consultoría (sin mediciones). */
    public const CANALES_PORTAL_CONSULTORIA = ['consultoria'];

    /** Canales que se gestionan en portales y no deben listarse en /muestras. */
    public const CANALES_EXCLUIDOS_MUESTRAS = [];

    /** Roles que coordinan esos canales (rol principal o adicional). */
    public const ROLES_COORDINADOR_CANAL = ['coordinador_consul', 'asp', 'clarke_fire'];

    public static function esCanalFacturacionDirecta(?string $canal): bool
    {
        return in_array(trim((string) ($canal ?? '')), self::CANALES_FACTURACION_DIRECTA, true);
    }

    /**
     * Canales de ensayo visibles en un portal (p. ej. consultoría incluye mediciones).
     *
     * @return array<int, string>
     */
    public static function canalesIncluidosEnPortal(string $portalCanal): array
    {
        $portalCanal = strtolower(trim($portalCanal));

        if ($portalCanal === 'consultoria') {
            return self::CANALES_PORTAL_CONSULTORIA;
        }

        return [$portalCanal];
    }

    /**
     * Canal operativo resuelto de un ensayo (persistido o inferido por matriz/descripción).
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function resolverCanalEnsayo($tarea, ?string $matrizDescripcionCoti = null): ?string
    {
        return self::resolverDesdeEnsayoPayload([
            'canal_especial' => $tarea->cotio_canal_especial ?? null,
            'matriz_descripcion' => $matrizDescripcionCoti,
            'descripcion' => $tarea->cotio_descripcion ?? '',
        ]);
    }

    /**
     * Muestra de ensayo mediciones ya enviada al módulo de documentación.
     */
    public static function instanciaMedicionesEnDocumentacion($instancia): bool
    {
        if (! $instancia || (int) ($instancia->cotio_subitem ?? -1) !== 0) {
            return false;
        }
        if (! (bool) ($instancia->enable_modulo_mediciones ?? false)) {
            return false;
        }

        $cotio = \App\Models\Cotio::query()
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('cotio_subitem', 0)
            ->first();

        if (! $cotio) {
            return false;
        }

        $matrizDescripcion = \App\Models\Coti::query()
            ->with('matriz')
            ->where('coti_num', $instancia->cotio_numcoti)
            ->first()
            ?->matriz
            ?->matriz_descripcion;

        return self::resolverCanalEnsayo($cotio, $matrizDescripcion) === 'mediciones';
    }

    /**
     * Muestra ya derivada a laboratorio (OT).
     */
    public static function instanciaPasadaALaboratorio($instancia): bool
    {
        return (bool) ($instancia->enable_ot ?? false);
    }

    /**
     * Muestra ya derivada a laboratorio (OT) o a documentación (mediciones).
     */
    public static function instanciaPasadaALaboratorioODocumentacion($instancia): bool
    {
        if (self::instanciaPasadaALaboratorio($instancia)) {
            return true;
        }

        return self::instanciaMedicionesEnDocumentacion($instancia);
    }

    /**
     * Canal del ensayo inferido desde la instancia de muestreo (subitem 0).
     */
    public static function resolverCanalDesdeInstanciaMuestreo($instancia, ?string $matrizDescripcionCoti = null): ?string
    {
        if (! $instancia) {
            return null;
        }

        $canalEspecial = $instancia->cotio_canal_especial ?? null;
        if ($canalEspecial === null && $instancia->relationLoaded('tarea') && $instancia->tarea) {
            $canalEspecial = $instancia->tarea->cotio_canal_especial ?? null;
        }

        return self::resolverDesdeEnsayoPayload([
            'canal_especial' => $canalEspecial,
            'matriz_descripcion' => $matrizDescripcionCoti,
            'descripcion' => $instancia->cotio_descripcion ?? '',
        ]);
    }

    /**
     * Muestreo finalizado, ensayo de laboratorio, aún sin pasar a OT.
     */
    public static function instanciaPendientePasarALaboratorio($instancia, ?string $matrizDescripcionCoti = null): bool
    {
        $estado = strtolower(trim((string) ($instancia->cotio_estado ?? '')));
        if (! in_array($estado, ['muestreado', 'finalizado'], true)) {
            return false;
        }

        if (self::instanciaPasadaALaboratorio($instancia)) {
            return false;
        }

        return self::resolverCanalDesdeInstanciaMuestreo($instancia, $matrizDescripcionCoti) !== 'mediciones';
    }

    /**
     * Muestreo finalizado pero aún sin pasar a laboratorio/documentación.
     */
    public static function instanciaPendientePasarDesdeMuestreo($instancia): bool
    {
        $estado = strtolower(trim((string) ($instancia->cotio_estado ?? '')));
        if (! in_array($estado, ['muestreado', 'finalizado'], true)) {
            return false;
        }

        return ! self::instanciaPasadaALaboratorioODocumentacion($instancia);
    }

    /**
     * Ensayo con flujo directo a informes (consultoría / ASP / Clarke Fire), sin muestreo previo.
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function esEnsayoFacturacionDirecta($tarea, ?string $matrizDescripcionCoti = null): bool
    {
        return self::esCanalFacturacionDirecta(self::resolverCanalEnsayo($tarea, $matrizDescripcionCoti));
    }

    public static function usuarioEsCoordinadorCanal($user): bool
    {
        if (!$user) {
            return false;
        }
        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return true;
        }

        return method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(self::ROLES_COORDINADOR_CANAL);
    }

    /**
     * Ensayo (cotio subitem 0) que debe omitirse en órdenes de laboratorio.
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function ensayoExcluidoDeOrdenes($tarea, ?string $matrizDescripcionCoti = null): bool
    {
        return self::resolverDesdeEnsayoPayload([
            'canal_especial' => $tarea->cotio_canal_especial ?? null,
            'matriz_descripcion' => $matrizDescripcionCoti,
            'descripcion' => $tarea->cotio_descripcion ?? '',
        ]) !== null;
    }

    /**
     * Ensayo (cotio subitem 0) que debe omitirse en /muestras (p. ej. mediciones → consultoría).
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function ensayoExcluidoDeMuestras($tarea, ?string $matrizDescripcionCoti = null): bool
    {
        foreach (self::CANALES_EXCLUIDOS_MUESTRAS as $canal) {
            if (self::cotioEnsayoCoincideCanalUnico($tarea, $canal, $matrizDescripcionCoti)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filtra filas cotio: quita ensayos excluidos de /muestras y sus componentes (mismo cotio_item).
     *
     * @param  Collection|array<int,\App\Models\Cotio>  $tareas
     * @return Collection<int,\App\Models\Cotio>
     */
    public static function filtrarTareasCotioExcluidasDeMuestras($tareas, ?string $matrizDescripcionCoti = null): Collection
    {
        $col = $tareas instanceof Collection ? $tareas : collect($tareas);
        $itemsPermitidos = $col->where('cotio_subitem', 0)
            ->reject(fn ($t) => self::ensayoExcluidoDeMuestras($t, $matrizDescripcionCoti))
            ->pluck('cotio_item')
            ->unique()
            ->flip();

        if ($itemsPermitidos->isEmpty()) {
            return $col->filter(static fn () => false)->values();
        }

        return $col->filter(fn ($row) => $itemsPermitidos->has($row->cotio_item))->values();
    }

    public static function contextoPortalConsultoria(?string $canalParaFiltrar): bool
    {
        return strtolower(trim((string) ($canalParaFiltrar ?? ''))) === 'consultoria';
    }

    public static function contextoPortalMediciones(?string $canalParaFiltrar): bool
    {
        return strtolower(trim((string) ($canalParaFiltrar ?? ''))) === 'mediciones';
    }

    public static function esRequestPortalMediciones(): bool
    {
        if (request()->routeIs('mediciones.*')) {
            return true;
        }

        return in_array(request('portal'), ['mediciones'], true);
    }

    /**
     * Canal de filtrado explícito (query ?canal= o ruta /mediciones/*).
     * En /muestras y /show/{coti} sin parámetro no infiere canal por rol del usuario.
     */
    public static function canalParaFiltrarDesdeRequest(): ?string
    {
        if (request()->filled('canal')) {
            return strtolower(trim((string) request('canal')));
        }

        if (request()->routeIs('mediciones.*')) {
            return 'mediciones';
        }

        return null;
    }

    /**
     * Scope sobre filas cotio (ensayo): sin exclusiones adicionales (mediciones van a /muestras).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotioEnsayoIncluidoEnMuestras($query): void
    {
        // Sin filtros: todas las filas cotio aplicables al listado de muestreo pasan por otros scopes.
    }

    /**
     * Scope sobre filas cotio (ensayo): solo las que sí van a /ordenes.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotioEnsayoIncluidoEnOrdenes($query, ?string $matrizDescripcionCoti = null): void
    {
        $matriz = mb_strtolower(trim((string) ($matrizDescripcionCoti ?? '')));
        $descExpr = "LOWER(LTRIM(RTRIM(COALESCE(cotio_descripcion,''))))";

        $query->where(function ($w) use ($matriz, $descExpr) {
            $w->where(function ($w2) {
                $w2->whereNull('cotio_canal_especial')
                    ->orWhereRaw("LOWER(LTRIM(RTRIM(cotio_canal_especial))) NOT IN ('consultoria','asp','clarke_fire','mediciones')");
            })
            ->where(function ($w3) use ($descExpr, $matriz) {
                $w3->whereRaw("{$descExpr} NOT LIKE ?", ['%consultoria%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%clarke fire%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%clarke-fire%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%clarke_fire%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%asp%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%mediciones%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%determinacion de potabilidad%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%analisis de agua potable%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%analisis fisicoquimico y bacteriologico%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%analisis de agua recreacional%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%ensayo de agua de red de incendio%'])
                    ->whereRaw("{$descExpr} NOT LIKE ?", ['%control bacteriologico de aire%']);

                if ($matriz !== '') {
                    $w3->whereRaw('? NOT LIKE ?', [$matriz, '%consultoria%'])
                        ->whereRaw('? NOT LIKE ?', [$matriz, '%clarke fire%'])
                        ->whereRaw('? NOT LIKE ?', [$matriz, '%clarke-fire%'])
                        ->whereRaw('? NOT LIKE ?', [$matriz, '%clarke_fire%'])
                        ->whereRaw('? NOT LIKE ?', [$matriz, '%asp%'])
                        ->whereRaw('? NOT LIKE ?', [$matriz, '%mediciones%']);
                }
            });
        });
    }

    /**
     * Cotización (tabla coti) con al menos un ensayo del canal (columna persistida o heurística por descripción).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotiTieneEnsayoDelCanal($query, string $canal): void
    {
        $canales = self::canalesIncluidosEnPortal($canal);

        $query->where(function ($outer) use ($canales) {
            foreach ($canales as $index => $canalUnico) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $outer->{$method}(function ($subQuery) use ($canalUnico) {
                    self::aplicarWhereCotiTieneEnsayoDeCanalUnico($subQuery, $canalUnico);
                });
            }
        });
    }

    /**
     * Cotización con al menos un ensayo (subitem 0) de un canal concreto.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotiTieneEnsayoDeCanalUnico($query, string $canal): void
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
                                $w2->where(function ($w3) {
                                    $w3->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%mediciones%'])
                                        ->orWhereExists(function ($matSub) {
                                            $matSub->select(DB::raw(1))
                                                ->from('coti as c_mat')
                                                ->join('matriz as m_mat', 'c_mat.coti_codigomatriz', '=', 'm_mat.matriz_codigo')
                                                ->whereColumn('c_mat.coti_num', 'cotio.cotio_numcoti')
                                                ->whereRaw('LOWER(LTRIM(RTRIM(m_mat.matriz_descripcion))) LIKE ?', ['%mediciones%']);
                                        });
                                });
                            }
                        });
                });
        });
    }

    /**
     * Fila cotio (ensayo, subitem 0) del canal mediciones.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarWhereCotioRowEsCanalMediciones($query): void
    {
        $query->where(function ($w) {
            $w->where('cotio.cotio_canal_especial', 'mediciones')
                ->orWhere(function ($w2) {
                    $w2->whereNull('cotio.cotio_canal_especial')
                        ->where(function ($w3) {
                            $w3->whereRaw('LOWER(LTRIM(RTRIM(cotio.cotio_descripcion))) LIKE ?', ['%mediciones%'])
                                ->orWhereExists(function ($matSub) {
                                    $matSub->select(DB::raw(1))
                                        ->from('coti as c_mat')
                                        ->join('matriz as m_mat', 'c_mat.coti_codigomatriz', '=', 'm_mat.matriz_codigo')
                                        ->whereColumn('c_mat.coti_num', 'cotio.cotio_numcoti')
                                        ->whereRaw('LOWER(LTRIM(RTRIM(m_mat.matriz_descripcion))) LIKE ?', ['%mediciones%']);
                                });
                        });
                });
        });
    }

    /**
     * Instancias visibles en /informes: ensayos de mediciones solo si tienen PDF subido.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public static function aplicarWhereInstanciaVisibleEnInformes($query): void
    {
        $table = $query->getModel()->getTable();

        // Trabajos enviados directo a facturación (sin informe ni firma digital).
        $query->where(function ($sinInforme) use ($table) {
            $sinInforme->where('firmado', false)
                ->orWhereNotNull("{$table}.identificador_documento_firma")
                ->orWhere(function ($conArchivo) use ($table) {
                    $conArchivo->whereNotNull("{$table}.archivo_informe")
                        ->whereRaw("LTRIM(RTRIM(COALESCE({$table}.archivo_informe,''))) <> ''");
                });
        });

        $query->where(function ($outer) use ($table) {
            $outer->whereNotExists(function ($sub) use ($table) {
                $sub->select(DB::raw(1))
                    ->from('cotio')
                    ->whereColumn('cotio.cotio_numcoti', "{$table}.cotio_numcoti")
                    ->whereColumn('cotio.cotio_item', "{$table}.cotio_item")
                    ->where('cotio.cotio_subitem', 0);
                self::aplicarWhereCotioRowEsCanalMediciones($sub);
            })->orWhere(function ($med) use ($table) {
                $med->whereNotNull("{$table}.archivo_informe")
                    ->whereRaw("LTRIM(RTRIM(COALESCE({$table}.archivo_informe,''))) <> ''")
                    ->whereExists(function ($sub) use ($table) {
                        $sub->select(DB::raw(1))
                            ->from('cotio')
                            ->whereColumn('cotio.cotio_numcoti', "{$table}.cotio_numcoti")
                            ->whereColumn('cotio.cotio_item', "{$table}.cotio_item")
                            ->where('cotio.cotio_subitem', 0);
                        self::aplicarWhereCotioRowEsCanalMediciones($sub);
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
            if ($user->hasRole('coordinador_mediciones')) {
                return 'mediciones';
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
        foreach (self::canalesIncluidosEnPortal($canal) as $canalUnico) {
            if (self::cotioEnsayoCoincideCanalUnico($tarea, $canalUnico, $matrizDescripcionCoti)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensayo pertenece a un único canal (sin expansión de portal).
     *
     * @param  \App\Models\Cotio|object  $tarea
     */
    public static function cotioEnsayoCoincideCanalUnico($tarea, string $canal, ?string $matrizDescripcionCoti = null): bool
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
        $mat = mb_strtolower(trim((string) ($matrizDescripcionCoti ?? '')));
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
            return str_contains($desc, 'mediciones') || str_contains($mat, 'mediciones');
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
