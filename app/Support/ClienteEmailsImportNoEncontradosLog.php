<?php

namespace App\Support;

class ClienteEmailsImportNoEncontradosLog
{
    /** Directorio fijo (mismo que los .log) para evitar confusiones con el disco "local" de Laravel. */
    private function directorioLogs(): string
    {
        $dir = storage_path('logs' . DIRECTORY_SEPARATOR . 'importacion_emails_clientes');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }
    /**
     * @param  array{
     *   insertados: int,
     *   omitidos_duplicado: int,
     *   omitidos_sin_email: int,
     *   omitidos_portal: int,
     *   clientes_no_encontrados: list<array<string, mixed>>,
     *   sucursales_no_encontradas: list<array<string, mixed>>
     * }  $stats
     * @return array{log_path: string, csv_path: string, total: int}
     */
    public function escribir(
        array $stats,
        string $archivoOrigen,
        bool $dryRun,
    ): array {
        $timestamp = now()->format('Y-m-d_His');
        $logFilename = "importacion_emails_clientes_{$timestamp}.log";
        $csvFilename = "import_emails_no_encontrados_{$timestamp}.csv";

        $clientes = $stats['clientes_no_encontrados'] ?? [];
        $sucursales = $stats['sucursales_no_encontradas'] ?? [];
        $total = count($clientes) + count($sucursales);

        $logLines = $this->construirLogTexto($stats, $archivoOrigen, $dryRun, $clientes, $sucursales, $total);
        $csvContent = $this->construirCsv($clientes, $sucursales);

        $dir = $this->directorioLogs();
        $logPath = $dir . DIRECTORY_SEPARATOR . $logFilename;
        $csvPath = $dir . DIRECTORY_SEPARATOR . $csvFilename;

        $logOk = file_put_contents($logPath, implode("\n", $logLines) . "\n") !== false;
        // BOM UTF-8 para que Excel en Windows abra bien acentos y ñ
        $csvOk = file_put_contents($csvPath, "\xEF\xBB\xBF" . $csvContent) !== false;

        if (!$csvOk) {
            throw new \RuntimeException("No se pudo escribir el CSV en: {$csvPath}");
        }

        return [
            'log_path' => $logPath,
            'csv_path' => $csvPath,
            'log_escrito' => $logOk,
            'csv_escrito' => $csvOk,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $clientes
     * @param  list<array<string, mixed>>  $sucursales
     * @return list<string>
     */
    private function construirLogTexto(
        array $stats,
        string $archivoOrigen,
        bool $dryRun,
        array $clientes,
        array $sucursales,
        int $total,
    ): array {
        $lines = [
            '================================================================================',
            'LOG DE IMPORTACIÓN DE EMAILS - NO ENCONTRADOS EN BASE DE DATOS',
            '================================================================================',
            'Fecha: ' . now()->format('Y-m-d H:i:s'),
            'Archivo Excel: ' . $archivoOrigen,
            'Modo: ' . ($dryRun ? 'SIMULACIÓN (dry-run)' : 'IMPORTACIÓN REAL'),
            '',
            '--- RESUMEN DE LA CORRIDA ---',
            'Contactos ' . ($dryRun ? 'simulados' : 'insertados') . ': ' . ($stats['insertados'] ?? 0),
            'Omitidos (duplicado): ' . ($stats['omitidos_duplicado'] ?? 0),
            'Omitidos (PORTAL / sin email): ' . (($stats['omitidos_sin_email'] ?? 0) + ($stats['omitidos_portal'] ?? 0)),
            'Clientes principales NO encontrados: ' . count($clientes),
            'Sucursales NO encontradas (etiqueta SUC): ' . count($sucursales),
            'Total registros pendientes: ' . $total,
            '',
        ];

        $lines[] = '--- CLIENTES PRINCIPALES NO ENCONTRADOS ---';
        if ($clientes === []) {
            $lines[] = '(ninguno)';
        } else {
            foreach ($clientes as $i => $c) {
                $n = $i + 1;
                $lines[] = "{$n}. Fila Excel: {$c['fila']}";
                $lines[] = "   Código Excel: {$c['codigo_excel']}";
                $lines[] = "   Razón social: {$c['cliente']}";
                $lines[] = "   CUIT Excel: {$c['cuit']}";
                $lines[] = '   Motivo: ' . ($c['motivo'] ?? 'No existe en tabla cli con ese código/CUIT/razón social');
                if (!empty($c['mail_facturas'])) {
                    $lines[] = '   Mail facturas (no importado): ' . $this->resumirCelda($c['mail_facturas']);
                }
                if (!empty($c['mail_cobranzas'])) {
                    $lines[] = '   Mail cobranzas (no importado): ' . $this->resumirCelda($c['mail_cobranzas']);
                }
                $lines[] = '';
            }
        }

        $lines[] = '--- SUCURSALES NO ENCONTRADAS (etiqueta SUC del Excel) ---';
        if ($sucursales === []) {
            $lines[] = '(ninguna)';
        } else {
            foreach ($sucursales as $i => $s) {
                $n = $i + 1;
                $lines[] = "{$n}. Fila Excel: {$s['fila']}";
                $lines[] = "   Cliente principal (sí existe): {$s['cliente']} (cód. Excel {$s['codigo_excel']}, BD {$s['codigo_principal_bd']})";
                $lines[] = "   Etiqueta SUC en Excel: {$s['etiqueta_suc']}";
                $lines[] = "   Tipo contacto: {$s['tipo']}";
                $lines[] = '   Motivo: ' . $s['motivo'];
                $lines[] = "   Emails no importados: {$s['emails']}";
                if (!empty($s['sucursales_en_bd'])) {
                    $lines[] = '   Sucursales existentes en BD (para comparar nombre/localidad):';
                    foreach ($s['sucursales_en_bd'] as $ref) {
                        $lines[] = "      - [{$ref['codigo']}] {$ref['fantasia']} | {$ref['localidad']} | {$ref['direccion']}";
                    }
                } else {
                    $lines[] = '   Sucursales en BD: (ninguna registrada para este cliente)';
                }
                $lines[] = '';
            }
        }

        $lines[] = '================================================================================';
        $lines[] = 'Fin del log. Corrija los datos en cli o ajuste las etiquetas SUC y vuelva a importar.';
        $lines[] = '================================================================================';

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $clientes
     * @param  list<array<string, mixed>>  $sucursales
     */
    private function construirCsv(array $clientes, array $sucursales): string
    {
        $headers = [
            'tipo',
            'fila_excel',
            'codigo_excel',
            'razon_social_excel',
            'cuit_excel',
            'codigo_principal_bd',
            'etiqueta_suc_excel',
            'tipo_contacto',
            'emails_no_importados',
            'motivo',
            'sucursales_en_bd',
        ];

        $rows = [$this->filaCsv($headers)];

        foreach ($clientes as $c) {
            $rows[] = $this->filaCsv([
                'cliente_principal',
                $c['fila'],
                $c['codigo_excel'],
                $c['cliente'],
                $c['cuit'],
                '',
                '',
                '',
                $this->emailsResumenCliente($c),
                $c['motivo'] ?? 'Cliente principal no encontrado en cli',
                '',
            ]);
        }

        foreach ($sucursales as $s) {
            $refs = [];
            foreach ($s['sucursales_en_bd'] ?? [] as $ref) {
                $refs[] = sprintf(
                    '%s: %s / %s',
                    $ref['codigo'],
                    $ref['fantasia'],
                    $ref['localidad']
                );
            }

            $rows[] = $this->filaCsv([
                'sucursal',
                $s['fila'],
                $s['codigo_excel'],
                $s['cliente'],
                $s['cuit'] ?? '',
                $s['codigo_principal_bd'] ?? '',
                $s['etiqueta_suc'],
                $s['tipo'],
                $s['emails'],
                $s['motivo'],
                implode(' | ', $refs),
            ]);
        }

        return implode("\n", $rows) . "\n";
    }

    /**
     * @param  array<string, mixed>  $cliente
     */
    private function emailsResumenCliente(array $cliente): string
    {
        $partes = array_filter([
            !empty($cliente['mail_facturas']) ? 'Facturas: ' . $this->resumirCelda((string) $cliente['mail_facturas']) : null,
            !empty($cliente['mail_cobranzas']) ? 'Cobranzas: ' . $this->resumirCelda((string) $cliente['mail_cobranzas']) : null,
        ]);

        return implode(' // ', $partes);
    }

    private function resumirCelda(string $texto, int $max = 200): string
    {
        $texto = preg_replace('/\s+/u', ' ', trim($texto)) ?? '';

        return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max) . '…' : $texto;
    }

    /**
     * @param  list<string|int>  $valores
     */
    private function filaCsv(array $valores): string
    {
        return implode(',', array_map(
            fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
            $valores
        ));
    }
}
