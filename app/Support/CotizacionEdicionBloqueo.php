<?php

namespace App\Support;

use App\Models\User;
use App\Models\Ventas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bloqueo exclusivo mientras un presupuesto está abierto en /ventas/{id}/edit (versión actual).
 */
final class CotizacionEdicionBloqueo
{
    public const TTL_MINUTOS = 5;

    public static function requiereBloqueoConcurrente(Ventas $cotizacion): bool
    {
        return empty($cotizacion->cancelada);
    }

    /**
     * @return array{ok: bool, titular?: array{usu_codigo: string, nombre: string, desde: ?string}}
     */
    public static function adquirir(int $cotiNum, User $usuario): array
    {
        $usuCodigo = trim((string) $usuario->usu_codigo);

        return DB::transaction(function () use ($cotiNum, $usuCodigo) {
            $cotizacion = Ventas::where('coti_num', $cotiNum)->lockForUpdate()->first();

            if (! $cotizacion) {
                return ['ok' => false, 'titular' => self::titularDesconocido()];
            }

            $titularActual = trim((string) ($cotizacion->coti_edit_lock_usu ?? ''));
            $vencido = self::bloqueoVencido($cotizacion->coti_edit_lock_at);

            if ($titularActual !== '' && ! $vencido && $titularActual !== $usuCodigo) {
                return [
                    'ok' => false,
                    'titular' => self::resolverTitular($titularActual, $cotizacion->coti_edit_lock_at),
                ];
            }

            $cotizacion->coti_edit_lock_usu = $usuCodigo;
            $cotizacion->coti_edit_lock_at = now();
            $cotizacion->save();

            return ['ok' => true];
        });
    }

    public static function renovar(int $cotiNum, User $usuario): bool
    {
        $usuCodigo = trim((string) $usuario->usu_codigo);

        return DB::transaction(function () use ($cotiNum, $usuCodigo) {
            $cotizacion = Ventas::where('coti_num', $cotiNum)->lockForUpdate()->first();

            if (! $cotizacion) {
                return false;
            }

            if (trim((string) ($cotizacion->coti_edit_lock_usu ?? '')) !== $usuCodigo) {
                return false;
            }

            $cotizacion->coti_edit_lock_at = now();
            $cotizacion->save();

            return true;
        });
    }

    public static function liberar(int $cotiNum, User $usuario): void
    {
        $usuCodigo = trim((string) $usuario->usu_codigo);

        DB::transaction(function () use ($cotiNum, $usuCodigo) {
            $cotizacion = Ventas::where('coti_num', $cotiNum)->lockForUpdate()->first();

            if (! $cotizacion) {
                return;
            }

            if (trim((string) ($cotizacion->coti_edit_lock_usu ?? '')) !== $usuCodigo) {
                return;
            }

            $cotizacion->coti_edit_lock_usu = null;
            $cotizacion->coti_edit_lock_at = null;
            $cotizacion->save();
        });
    }

    public static function usuarioTieneBloqueo(Ventas $cotizacion, User $usuario): bool
    {
        $usuCodigo = trim((string) $usuario->usu_codigo);
        $titular = trim((string) ($cotizacion->coti_edit_lock_usu ?? ''));

        if ($titular === '' || $titular !== $usuCodigo) {
            return false;
        }

        return ! self::bloqueoVencido($cotizacion->coti_edit_lock_at);
    }

    private static function bloqueoVencido($lockAt): bool
    {
        if ($lockAt === null || $lockAt === '') {
            return true;
        }

        return Carbon::parse($lockAt)->addMinutes(self::TTL_MINUTOS)->isPast();
    }

    /**
     * @return array{usu_codigo: string, nombre: string, desde: ?string}
     */
    private static function resolverTitular(string $usuCodigo, $lockAt): array
    {
        $user = User::where('usu_codigo', $usuCodigo)->first();

        return [
            'usu_codigo' => $usuCodigo,
            'nombre' => trim((string) ($user->usu_descripcion ?? $usuCodigo)),
            'desde' => $lockAt ? Carbon::parse($lockAt)->format('d/m/Y H:i') : null,
        ];
    }

    /**
     * @return array{usu_codigo: string, nombre: string, desde: ?string}
     */
    private static function titularDesconocido(): array
    {
        return [
            'usu_codigo' => '',
            'nombre' => 'Otro usuario',
            'desde' => null,
        ];
    }
}
