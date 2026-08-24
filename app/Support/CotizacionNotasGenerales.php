<?php

namespace App\Support;

class CotizacionNotasGenerales
{
    /**
     * Lista normalizada para vistas/PDF: solo textos no vacíos.
     *
     * @return list<string>
     */
    public static function listadoParaVista(?string $raw): array
    {
        return collect(self::listadoDesdeAlmacenamiento($raw))
            ->map(fn (array $n) => trim((string) ($n['contenido'] ?? '')))
            ->filter(fn (string $t) => $t !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{contenido: string}>
     */
    public static function listadoDesdeAlmacenamiento($raw): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $str = trim((string) $raw);
        $decoded = json_decode($str, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (array_is_list($decoded)) {
                return collect($decoded)
                    ->map(function ($row) {
                        if (is_string($row)) {
                            $cont = trim($row);

                            return $cont !== '' ? ['contenido' => $cont] : null;
                        }
                        if (!is_array($row)) {
                            return null;
                        }
                        $cont = trim((string) ($row['contenido'] ?? $row['text'] ?? ''));

                        return $cont !== '' ? ['contenido' => $cont] : null;
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            if (isset($decoded['contenido']) || isset($decoded['text'])) {
                $cont = trim((string) ($decoded['contenido'] ?? $decoded['text'] ?? ''));

                return $cont !== '' ? [['contenido' => $cont]] : [];
            }
        }

        return [['contenido' => $str]];
    }

    /**
     * Persiste notas generales como JSON [{contenido: ...}, ...] o null.
     */
    public static function persistirDesdeRequest($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $list = null;
        if (is_array($valor)) {
            $list = $valor;
        } else {
            $str = trim((string) $valor);
            if ($str === '') {
                return null;
            }

            $decoded = json_decode($str, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $list = $decoded;
            } else {
                return $str;
            }
        }

        $out = [];
        foreach ($list as $row) {
            if (is_string($row)) {
                $cont = trim($row);
            } elseif (is_array($row)) {
                $cont = trim((string) ($row['contenido'] ?? ''));
            } else {
                continue;
            }

            if ($cont !== '') {
                $out[] = ['contenido' => $cont];
            }
        }

        if ($out === []) {
            return null;
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
