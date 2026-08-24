<?php

namespace App\Support;

use App\Models\Cotio;
use App\Models\CotioInstancia;
use Illuminate\Support\Facades\Auth;

/**
 * Ensayos / ítems «Trabajo técnico en campo» (descripción variable en catálogo).
 * Tras muestreo finalizado (estado muestreado) van directo a facturación, sin laboratorio ni informe.
 */
final class TrabajoTecnicoCampo
{
    /**
     * Reconoce variantes: "TRABAJO TECNICO EN CAMPO", "Trabajo tecnico en campo", "Trabajo Tecnico En Campo_", etc.
     */
    public static function esDescripcion(?string $raw): bool
    {
        $s = self::normalizarDescripcion($raw);
        if ($s === '') {
            return false;
        }

        if (str_contains($s, 'TRABAJO TECNICO') && str_contains($s, 'CAMPO')) {
            return true;
        }

        return in_array($s, [
            'TRABAJO TECNICO EN CAMPO',
            'TRABAJO TECNICO DE CAMPO',
        ], true);
    }

    public static function normalizarDescripcion(?string $raw): string
    {
        $s = strtoupper(trim((string) $raw));
        $s = rtrim($s, '_');
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return $s;
    }

    public static function instanciaEsTrabajoTecnicoCampo(CotioInstancia $instancia): bool
    {
        if (self::esDescripcion($instancia->cotio_descripcion ?? null)) {
            return true;
        }

        $cotio = Cotio::query()
            ->where('cotio_numcoti', $instancia->cotio_numcoti)
            ->where('cotio_item', $instancia->cotio_item)
            ->where('cotio_subitem', $instancia->cotio_subitem)
            ->first();

        return $cotio && self::esDescripcion($cotio->cotio_descripcion ?? null);
    }

    /**
     * Ítem independiente (ensayo subitem 0) de trabajo técnico en campo.
     */
    public static function instanciaEsEnsayoIndependienteTrabajoTecnico(CotioInstancia $instancia): bool
    {
        return (int) ($instancia->cotio_subitem ?? -1) === 0
            && self::instanciaEsTrabajoTecnicoCampo($instancia);
    }

    public static function estadoEsMuestreado(?string $estado): bool
    {
        return strtolower(trim((string) $estado)) === 'muestreado';
    }

    /**
     * Marca la instancia lista para facturación. Retorna true si aplicó cambios.
     */
    public static function marcarListoParaFacturar(CotioInstancia $instancia): bool
    {
        if (! self::instanciaEsEnsayoIndependienteTrabajoTecnico($instancia)) {
            return false;
        }

        if (! self::estadoEsMuestreado($instancia->cotio_estado ?? null)) {
            return false;
        }

        if ((bool) ($instancia->enable_inform ?? false) && (bool) ($instancia->aprobado_informe ?? false)) {
            return false;
        }

        $usuario = Auth::user();

        $instancia->updateQuietly([
            'enable_inform' => true,
            'aprobado_informe' => true,
            'fecha_aprobacion_informe' => $instancia->fecha_aprobacion_informe ?? now(),
            'aprobado_informe_usuario' => $instancia->aprobado_informe_usuario
                ?? ($usuario->usu_codigo ?? null),
            'complete_muestreo' => true,
        ]);

        return true;
    }

    /**
     * Aplicar al pasar a estado muestreado (desde observer o servicios).
     */
    public static function aplicarSiCorresponde(CotioInstancia $instancia): void
    {
        if (! self::estadoEsMuestreado($instancia->cotio_estado ?? null)) {
            return;
        }

        self::marcarListoParaFacturar($instancia);
    }

    /**
     * Backfill para instancias ya muestreadas antes del fix.
     */
    public static function sincronizarInstanciasMuestreadasPendientes(?int $cotiNum = null): int
    {
        $query = CotioInstancia::query()
            ->where('cotio_subitem', 0)
            ->whereRaw("LOWER(TRIM(COALESCE(cotio_estado, ''))) = 'muestreado'")
            ->where(function ($q) {
                $q->where('enable_inform', false)
                    ->orWhere('aprobado_informe', false);
            });

        if ($cotiNum !== null) {
            $query->where('cotio_numcoti', $cotiNum);
        }

        $actualizadas = 0;
        foreach ($query->cursor() as $instancia) {
            if (self::marcarListoParaFacturar($instancia)) {
                $actualizadas++;
            }
        }

        return $actualizadas;
    }
}
