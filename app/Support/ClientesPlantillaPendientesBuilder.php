<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ClientesPlantillaPendientesBuilder
{
    /**
     * @return array{clientes: array<int, array<int, string>>, contactos: array<int, array<int, string>>}
     */
    public static function desdeCsv(string $rutaCsv): array
    {
        $handle = fopen($rutaCsv, 'r');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo leer el archivo: {$rutaCsv}");
        }

        $primeraLinea = fgets($handle);
        if ($primeraLinea === false) {
            fclose($handle);
            throw new \RuntimeException('El archivo CSV está vacío.');
        }

        $primeraLinea = self::normalizarBom($primeraLinea);
        $encabezados = str_getcsv($primeraLinea);
        $mapa = self::mapearEncabezados($encabezados);

        $clientes = [];
        $contactos = [];
        $hoy = now()->format('Y-m-d');

        while (($fila = fgetcsv($handle)) !== false) {
            if (count(array_filter($fila, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $filaExcel = trim((string) self::valor($fila, $mapa, ['fila excel', 'fila_excel'], ''));
            $razonSocial = trim((string) self::valor($fila, $mapa, ['razon social', 'razón social'], ''));
            $cuit = trim((string) self::valor($fila, $mapa, ['cuit'], ''));
            $motivo = trim((string) self::valor($fila, $mapa, ['motivo'], ''));
            $mailFacturas = trim((string) self::valor($fila, $mapa, ['mail facturas', 'mail_facturas'], ''));
            $mailCobranzas = trim((string) self::valor($fila, $mapa, ['mail cobranzas', 'mail_cobranzas'], ''));

            if ($razonSocial === '') {
                continue;
            }

            $referencia = $filaExcel !== '' ? 'REF-'.$filaExcel : 'REF-'.(count($clientes) + 1);

            $clientes[] = [
                $referencia,
                $razonSocial,
                '',
                '',
                '',
                '',
                'ARG',
                '',
                $cuit,
                '',
                '',
                'Activo',
                'UNO',
                $hoy,
                '',
                'No',
                $filaExcel,
                $motivo,
            ];

            foreach (self::emailsDesdeTexto($mailFacturas) as $email) {
                $contactos[] = [
                    $referencia,
                    self::nombreDesdeEmail($email, 'Facturación'),
                    '',
                    $email,
                    'Envío de factura',
                ];
            }

            foreach (self::emailsDesdeTexto($mailCobranzas) as $email) {
                $contactos[] = [
                    $referencia,
                    self::nombreDesdeEmail($email, 'Cobranza'),
                    '',
                    $email,
                    'Cobranza',
                ];
            }
        }

        fclose($handle);

        return [
            'clientes' => $clientes,
            'contactos' => $contactos,
        ];
    }

    private static function normalizarBom(string $linea): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $linea) ?? $linea;
    }

    /**
     * @param  array<int, string>  $encabezados
     * @return array<string, int>
     */
    private static function mapearEncabezados(array $encabezados): array
    {
        $mapa = [];
        foreach ($encabezados as $i => $titulo) {
            $clave = self::normalizarClave($titulo);
            if ($clave !== '') {
                $mapa[$clave] = $i;
            }
        }

        return $mapa;
    }

    private static function normalizarClave(string $texto): string
    {
        $texto = mb_strtolower(trim(self::normalizarBom($texto)));
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    /**
     * @param  array<int, string>  $fila
     * @param  array<string, int>  $mapa
     * @param  array<int, string>  $claves
     */
    private static function valor(array $fila, array $mapa, array $claves, string $default = ''): string
    {
        foreach ($claves as $clave) {
            if (isset($mapa[$clave])) {
                return $fila[$mapa[$clave]] ?? $default;
            }
        }

        return $default;
    }

    /**
     * @return array<int, string>
     */
    private static function emailsDesdeTexto(string $texto): array
    {
        if ($texto === '') {
            return [];
        }

        $partes = preg_split('/[,;]+/', $texto) ?: [];
        $emails = [];

        foreach ($partes as $parte) {
            $email = trim($parte);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private static function nombreDesdeEmail(string $email, string $fallback): string
    {
        $local = trim((string) Str::before($email, '@'));

        return $local !== '' ? $local : $fallback;
    }
}
