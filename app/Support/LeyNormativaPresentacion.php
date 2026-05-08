<?php

namespace App\Support;

use App\Models\Cotio;

/**
 * Texto de legislación / normativa asociada a una fila Cotio (ley_aplicacion → leyes_normativas).
 */
class LeyNormativaPresentacion
{
    /**
     * Fragmento HTML seguro (puede incluir &lt;span class="text-muted"&gt;…&lt;/span&gt;).
     */
    public static function fragmentoHtml(?Cotio $lineaCotio): string
    {
        if ($lineaCotio === null) {
            return '—';
        }
        $codigo = trim((string) ($lineaCotio->ley_aplicacion ?? ''));
        if ($codigo === '') {
            return '—';
        }
        $ley = $lineaCotio->leyNormativa;
        if ($ley) {
            $nombre = trim((string) ($ley->nombre ?? ''));
            $articulo = trim((string) ($ley->articulo ?? ''));
            $parts = array_filter([
                $nombre !== '' ? $nombre : null,
                $articulo !== '' ? $articulo : null,
            ]);
            $main = implode(' — ', $parts);
            if ($main === '') {
                $main = trim((string) ($ley->descripcion ?? ''));
            }
            if ($main === '') {
                return htmlspecialchars((string) ($ley->codigo ?? $codigo));
            }
            $codigoRef = htmlspecialchars((string) ($ley->codigo ?? $codigo));

            return htmlspecialchars($main) . ' <span class="text-muted">(' . $codigoRef . ')</span>';
        }

        return htmlspecialchars($codigo);
    }

    /**
     * Texto plano (sin HTML), para PDF con {{ }} o listados.
     */
    public static function textoPlano(?Cotio $lineaCotio): string
    {
        if ($lineaCotio === null) {
            return '—';
        }
        $codigo = trim((string) ($lineaCotio->ley_aplicacion ?? ''));
        if ($codigo === '') {
            return '—';
        }
        $ley = $lineaCotio->leyNormativa;
        if ($ley) {
            $nombre = trim((string) ($ley->nombre ?? ''));
            $articulo = trim((string) ($ley->articulo ?? ''));
            $parts = array_filter([
                $nombre !== '' ? $nombre : null,
                $articulo !== '' ? $articulo : null,
            ]);
            $main = implode(' — ', $parts);
            if ($main === '') {
                $main = trim((string) ($ley->descripcion ?? ''));
            }
            if ($main === '') {
                return (string) ($ley->codigo ?? $codigo);
            }

            return $main . ' (' . ($ley->codigo ?? $codigo) . ')';
        }

        return $codigo;
    }
}
