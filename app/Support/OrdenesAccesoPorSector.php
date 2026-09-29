<?php

namespace App\Support;

use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alcance de órdenes para coordinadores de lab sin privilegio "Puede gestionar órdenes":
 * ven cotizaciones/muestras/análisis asignados a su laboratorio (sector).
 */
final class OrdenesAccesoPorSector
{
    public static function debeFiltrarPorSector(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return false;
        }

        if (! $user->hasRole('coordinador_lab')) {
            return false;
        }

        return ! $user->puedeGestionarOrdenes();
    }

    /**
     * @return Collection<int, string>
     */
    public static function sectoresUsuario(User $user): Collection
    {
        return AsignacionSectorLaboratorio::codigosSectorDirectosDelUsuario($user);
    }

    public static function responsablePerteneceASectores(User $responsable, Collection $sectores): bool
    {
        if ($sectores->isEmpty()) {
            return false;
        }

        foreach ($sectores as $sectorCodigo) {
            if (AsignacionSectorLaboratorio::usuarioPerteneceAlSectorParaAsignacion($responsable, (string) $sectorCodigo)) {
                return true;
            }
        }

        return false;
    }

    public static function instanciaTieneResponsablesEnSectores(CotioInstancia $instancia, Collection $sectores): bool
    {
        if ($sectores->isEmpty()) {
            return false;
        }

        $instancia->loadMissing('responsablesAnalisis');

        foreach ($instancia->responsablesAnalisis as $responsable) {
            if (self::responsablePerteneceASectores($responsable, $sectores)) {
                return true;
            }
        }

        return false;
    }

    public static function muestraTieneAnalisisEnSectores(CotioInstancia $muestra, Collection $sectores): bool
    {
        if ($sectores->isEmpty()) {
            return false;
        }

        return CotioInstancia::query()
            ->where('cotio_numcoti', $muestra->cotio_numcoti)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('instance_number', $muestra->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->where(function (Builder $q) use ($sectores) {
                self::aplicarWhereInstanciaConResponsablesEnSectores($q, $sectores);
            })
            ->exists();
    }

    public static function cotizacionVisibleParaUsuario(int $cotioNumcoti, User $user): bool
    {
        if (! self::debeFiltrarPorSector($user)) {
            return true;
        }

        $sectores = self::sectoresUsuario($user);
        if ($sectores->isEmpty()) {
            return false;
        }

        return CotioInstancia::query()
            ->where('cotio_numcoti', $cotioNumcoti)
            ->where('active_ot', true)
            ->where('cotio_subitem', '>', 0)
            ->where(function (Builder $q) use ($sectores) {
                self::aplicarWhereInstanciaConResponsablesEnSectores($q, $sectores);
            })
            ->exists();
    }

    public static function aplicarFiltroCotiPorSectoresUsuario(Builder $query, User $user): void
    {
        $sectores = self::sectoresUsuario($user);
        if ($sectores->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('instancias', function (Builder $instQ) use ($sectores) {
            $instQ->where('active_ot', true)
                ->where('cotio_subitem', '>', 0)
                ->where(function (Builder $q) use ($sectores) {
                    self::aplicarWhereInstanciaConResponsablesEnSectores($q, $sectores);
                });
        });
    }

    public static function aplicarFiltroInstanciaPorSectoresUsuario(Builder $query, User $user): void
    {
        $sectores = self::sectoresUsuario($user);
        if ($sectores->isEmpty()) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($sectores) {
            self::aplicarWhereInstanciaConResponsablesEnSectores($q, $sectores);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ordenes
     * @return Collection<int, array<string, mixed>>
     */
    public static function filtrarOrdenesPorSector(Collection $ordenes, User $user): Collection
    {
        if (! self::debeFiltrarPorSector($user)) {
            return $ordenes;
        }

        $sectores = self::sectoresUsuario($user);
        if ($sectores->isEmpty()) {
            return collect();
        }

        return $ordenes->map(function (array $orden, $cotiNum) use ($sectores) {
            $muestras = collect($orden['muestras_relevantes'] ?? [])
                ->filter(fn ($muestra) => self::muestraTieneAnalisisEnSectores($muestra, $sectores))
                ->values();

            if ($muestras->isEmpty()) {
                return null;
            }

            $orden['muestras_relevantes'] = $muestras;
            $total = $muestras->count();
            $orden['total'] = $total;
            $orden['completadas'] = $muestras->where('cotio_estado_analisis', 'analizado')->count();
            $orden['en_proceso'] = $muestras->where('cotio_estado_analisis', 'en revision analisis')->count();
            $orden['coordinadas'] = $muestras->where('cotio_estado_analisis', 'coordinado analisis')->count();
            $orden['porcentaje'] = $total > 0 ? round(($orden['completadas'] / $total) * 100) : 0;

            return $orden;
        })->filter(fn ($orden) => $orden !== null);
    }

    /**
     * @return Collection<int, User>
     */
    public static function usuariosGestoresOrdenes(): Collection
    {
        return User::query()
            ->where('usu_estado', true)
            ->where(function (Builder $q) {
                $q->where('puede_gestionar_ordenes', true)
                    ->orWhere('usu_nivel', '>=', 900);
            })
            ->get()
            ->filter(fn (User $u) => $u->puedeGestionarOrdenes())
            ->unique(fn (User $u) => trim((string) $u->usu_codigo))
            ->values();
    }

    private static function aplicarWhereInstanciaConResponsablesEnSectores(Builder $query, Collection $sectores): void
    {
        $query->whereExists(function ($sub) use ($sectores) {
            $sub->select(DB::raw(1))
                ->from('instancia_responsable_analisis as ira')
                ->join('usu as u', function ($join) {
                    $join->whereRaw('TRIM(u.usu_codigo) = TRIM(ira.usu_codigo)');
                })
                ->whereColumn('ira.cotio_instancia_id', 'cotio_instancias.id')
                ->where(function ($sectorQ) use ($sectores) {
                    foreach ($sectores as $sectorCodigo) {
                        $sectorCodigo = trim((string) $sectorCodigo);
                        if ($sectorCodigo === '') {
                            continue;
                        }

                        $sectorQ->orWhere(function ($match) use ($sectorCodigo) {
                            $match->whereRaw('TRIM(u.sector_codigo) = ?', [$sectorCodigo])
                                ->orWhere(function ($q) use ($sectorCodigo) {
                                    // Coordinadores de lab: solo cuenta su sector primario (sector_codigo).
                                    $q->where(function ($rolQ) {
                                        $rolQ->whereNull('u.rol')
                                            ->orWhereRaw("TRIM(u.rol) <> 'coordinador_lab'");
                                    })->whereExists(function ($pivot) use ($sectorCodigo) {
                                        $pivot->select(DB::raw(1))
                                            ->from('user_sectors as us')
                                            ->whereRaw('TRIM(us.usu_codigo) = TRIM(u.usu_codigo)')
                                            ->whereRaw('TRIM(us.sector_codigo) = ?', [$sectorCodigo]);
                                    });
                                })
                                ->orWhere(function ($sectorEntity) use ($sectorCodigo) {
                                    $sectorEntity->whereRaw("TRIM(u.rol) = 'sector'")
                                        ->whereRaw('TRIM(u.usu_codigo) = ?', [$sectorCodigo]);
                                });
                        });
                    }
                });
        });
    }
}
