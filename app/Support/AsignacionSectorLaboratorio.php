<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve sectores (usu con rol=sector) a usuarios reales vía sector_codigo y user_sectors.
 */
final class AsignacionSectorLaboratorio
{
    /**
     * @param  array<int, string>  $codigosSeleccionados  Códigos de sector y/o usuario
     * @return Collection<int, User>  Usuarios a asignar como responsables (nunca rol=sector)
     */
    public static function expandirSeleccionAResponsables(array $codigosSeleccionados): Collection
    {
        $usuarios = collect();

        foreach (array_filter(array_map('trim', $codigosSeleccionados)) as $codigo) {
            if ($codigo === '') {
                continue;
            }

            $entidad = self::buscarPorCodigo($codigo);
            if (! $entidad) {
                continue;
            }

            if (trim((string) ($entidad->rol ?? '')) === 'sector') {
                $usuarios = $usuarios->merge(self::usuariosDelSector($codigo));
                continue;
            }

            if ($entidad->miembros()->exists()) {
                $usuarios = $usuarios->merge($entidad->miembros);
            }

            $usuarios->push($entidad);
        }

        return $usuarios
            ->filter(fn (User $u) => trim((string) ($u->rol ?? '')) !== 'sector')
            ->unique(fn (User $u) => trim($u->usu_codigo));
    }

    /**
     * @param  array<int, string>  $codigosSeleccionados
     * @return array<int, string>  usu_codigo exactos de la BD
     */
    public static function codigosUsuariosParaAsignar(array $codigosSeleccionados): array
    {
        return self::expandirSeleccionAResponsables($codigosSeleccionados)
            ->pluck('usu_codigo')
            ->values()
            ->all();
    }

    public static function buscarPorCodigo(string $codigo): ?User
    {
        $codigo = trim($codigo);

        return User::whereRaw('TRIM(usu_codigo) = ?', [$codigo])->first();
    }

    /**
     * Sectores directos del usuario (sector_codigo + user_sectors), sin expandir pools de otros sectores.
     *
     * @return Collection<int, string>
     */
    public static function codigosSectorDirectosDelUsuario(User $usuario): Collection
    {
        $codigos = collect();

        $primario = trim((string) ($usuario->sector_codigo ?? ''));
        if ($primario !== '') {
            $codigos->push($primario);
        }

        $desdePivot = DB::table('user_sectors')
            ->whereRaw('TRIM(usu_codigo) = ?', [trim((string) $usuario->usu_codigo)])
            ->pluck('sector_codigo')
            ->map(fn ($c) => trim((string) $c))
            ->filter();

        return $codigos->merge($desdePivot)->unique()->values();
    }

    /**
     * Sector único para mostrar un responsable asignado (evita duplicar coordinadores multi-sector).
     */
    public static function resolverSectorDisplayDeUsuario(User $usuario, ?Collection $sectoresPorCodigo = null): string
    {
        $sectoresPorCodigo ??= User::where('rol', 'sector')
            ->get()
            ->keyBy(fn (User $s) => trim((string) $s->usu_codigo));

        $primario = trim((string) ($usuario->sector_codigo ?? ''));
        if ($primario !== '' && $sectoresPorCodigo->has($primario)) {
            return $primario;
        }

        $pivotSectores = self::codigosSectorDirectosDelUsuario($usuario)
            ->filter(fn (string $c) => $sectoresPorCodigo->has($c))
            ->values();

        if ($pivotSectores->count() === 1) {
            return $pivotSectores->first();
        }

        return '';
    }

    /**
     * Usuario analista/perteneciente real al sector (excluye coordinadores de lab en sectores secundarios).
     */
    public static function usuarioPerteneceAlSectorParaAsignacion(User $usuario, string $sectorCodigo): bool
    {
        $sectorCodigo = trim($sectorCodigo);
        $rol = trim((string) ($usuario->rol ?? ''));
        $sectorPrimario = trim((string) ($usuario->sector_codigo ?? ''));

        if ($rol === 'coordinador_lab') {
            return $sectorPrimario !== '' && $sectorPrimario === $sectorCodigo;
        }

        if ($sectorPrimario === $sectorCodigo) {
            return true;
        }

        return DB::table('user_sectors')
            ->whereRaw('TRIM(usu_codigo) = ?', [trim((string) $usuario->usu_codigo)])
            ->whereRaw('TRIM(sector_codigo) = ?', [$sectorCodigo])
            ->exists();
    }

    /**
     * Usuarios pertenecientes a un sector (sector_codigo + user_sectors).
     *
     * @return Collection<int, User>
     */
    public static function usuariosDelSector(string $sectorCodigo): Collection
    {
        $sectorCodigo = trim($sectorCodigo);
        if ($sectorCodigo === '') {
            return collect();
        }

        $porSectorCodigo = User::where('rol', '!=', 'sector')
            ->whereRaw('TRIM(sector_codigo) = ?', [$sectorCodigo])
            ->get();

        $codigosPivot = DB::table('user_sectors')
            ->whereRaw('TRIM(sector_codigo) = ?', [$sectorCodigo])
            ->pluck('usu_codigo')
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $porPivot = collect();
        if ($codigosPivot !== []) {
            $porPivot = User::where('rol', '!=', 'sector')
                ->where(function ($q) use ($codigosPivot) {
                    foreach ($codigosPivot as $usuCodigo) {
                        $q->orWhereRaw('TRIM(usu_codigo) = ?', [$usuCodigo]);
                    }
                })
                ->get();
        }

        return $porSectorCodigo
            ->merge($porPivot)
            ->filter(fn (User $u) => self::usuarioPerteneceAlSectorParaAsignacion($u, $sectorCodigo))
            ->unique(fn (User $u) => trim($u->usu_codigo));
    }

    /**
     * Agrupa responsables asignados por laboratorio/sector para mostrar en UI.
     *
     * @return Collection<int, array{sector: ?User, sector_codigo: string, sector_nombre: string, usuarios: Collection<int, User>}>
     */
    public static function sectoresConResponsablesAsignados(Collection $responsables): Collection
    {
        if ($responsables->isEmpty()) {
            return collect();
        }

        $sectoresPorCodigo = User::where('rol', 'sector')
            ->get()
            ->keyBy(fn (User $s) => trim((string) $s->usu_codigo));

        $grupos = [];

        foreach ($responsables as $usuario) {
            $sectorCodigo = self::resolverSectorDisplayDeUsuario($usuario, $sectoresPorCodigo);
            $key = $sectorCodigo !== '' ? $sectorCodigo : '__sin_sector__';

            if (! isset($grupos[$key])) {
                $sectorEntidad = $sectorCodigo !== '' ? $sectoresPorCodigo->get($sectorCodigo) : null;
                $grupos[$key] = [
                    'sector' => $sectorEntidad,
                    'sector_codigo' => $sectorCodigo,
                    'sector_nombre' => $sectorEntidad
                        ? trim((string) $sectorEntidad->usu_descripcion)
                        : 'Sin sector',
                    'usuarios' => collect(),
                ];
            }

            $grupos[$key]['usuarios']->push($usuario);
        }

        return collect($grupos)
            ->sortBy(fn (array $g) => $g['sector_nombre'] === 'Sin sector' ? 'zzz' : $g['sector_nombre'])
            ->values()
            ->map(function (array $grupo) {
                $grupo['usuarios'] = $grupo['usuarios']
                    ->unique(fn (User $u) => trim((string) $u->usu_codigo))
                    ->values();

                return $grupo;
            });
    }

    /**
     * Sectores cuyo pool tiene al menos un responsable asignado (para preselección en modales).
     *
     * @return array<int, string>
     */
    public static function codigosSectoresDesdeResponsables(Collection $responsables): array
    {
        return self::sectoresConResponsablesAsignados($responsables)
            ->pluck('sector_codigo')
            ->filter(fn ($c) => $c !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{sector_codigo: string, sector_nombre: string, usuarios: array<int, array{usu_codigo: string, usu_descripcion: string}>}>
     */
    public static function sectoresConResponsablesAsignadosParaApi(Collection $responsables): array
    {
        return self::sectoresConResponsablesAsignados($responsables)
            ->map(function (array $grupo) {
                return [
                    'sector_codigo' => $grupo['sector_codigo'],
                    'sector_nombre' => $grupo['sector_nombre'],
                    'usuarios' => $grupo['usuarios']->map(fn (User $u) => [
                        'usu_codigo' => trim((string) $u->usu_codigo),
                        'usu_descripcion' => trim((string) $u->usu_descripcion),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
