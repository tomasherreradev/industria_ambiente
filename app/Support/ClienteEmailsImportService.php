<?php

namespace App\Support;

use App\Models\ClienteContacto;
use App\Models\Clientes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClienteEmailsImportService
{
    public function __construct(
        private readonly ClienteEmailsExcelParser $parser = new ClienteEmailsExcelParser(),
        private readonly ClienteEmailsImportResolver $resolver = new ClienteEmailsImportResolver(),
    ) {
    }

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     * @return array{
     *   insertados: int,
     *   omitidos_duplicado: int,
     *   omitidos_sin_email: int,
     *   omitidos_portal: int,
     *   clientes_no_encontrados: list<array<string, mixed>>,
     *   sucursales_no_encontradas: list<array<string, mixed>>
     * }
     */
    public function procesarFilas(Collection $rows, bool $dryRun = false): array
    {
        $stats = [
            'insertados' => 0,
            'omitidos_duplicado' => 0,
            'omitidos_sin_email' => 0,
            'omitidos_portal' => 0,
            'clientes_no_encontrados' => [],
            'sucursales_no_encontradas' => [],
        ];

        $filaNumero = 0;
        foreach ($rows as $row) {
            $filaNumero++;
            $datos = $this->mapearFila($row);
            if ($datos === null) {
                continue;
            }

            $principal = $this->resolver->resolverPrincipal(
                $datos['codigo'],
                $datos['cliente'],
                $datos['cuit']
            );

            if (!$principal) {
                $stats['clientes_no_encontrados'][] = [
                    'fila' => $filaNumero,
                    'codigo_excel' => $datos['codigo'],
                    'cliente' => $datos['cliente'],
                    'cuit' => $datos['cuit'],
                    'mail_facturas' => $datos['mail_facturas'],
                    'mail_cobranzas' => $datos['mail_cobranzas'],
                    'motivo' => 'No existe en tabla cli un cliente principal con ese código, CUIT y/o razón social',
                ];
                continue;
            }

            foreach (['Envío de factura' => $datos['mail_facturas'], 'Cobranza' => $datos['mail_cobranzas']] as $tipo => $celda) {
                $this->procesarCelda(
                    $stats,
                    $filaNumero,
                    $principal,
                    $datos,
                    $tipo,
                    $celda,
                    $dryRun
                );
            }
        }

        return $stats;
    }

    private function procesarCelda(
        array &$stats,
        int $filaNumero,
        Clientes $principal,
        array $datos,
        string $tipo,
        ?string $celda,
        bool $dryRun,
    ): void {
        $texto = trim((string) $celda);
        if ($texto === '') {
            return;
        }

        if (preg_match('/^\s*PORTAL\s*$/i', $texto) && !$this->parser->extraerEmails($texto)) {
            $stats['omitidos_portal']++;

            return;
        }

        $bloques = $this->parser->parseCell($texto);
        if ($bloques === []) {
            if (stripos($texto, 'PORTAL') !== false) {
                $stats['omitidos_portal']++;
            } else {
                $stats['omitidos_sin_email']++;
            }

            return;
        }

        foreach ($bloques as $bloque) {
            $destino = $principal;
            $etiqueta = $bloque['etiqueta_suc'] ?? null;

            if ($etiqueta !== null) {
                $resolucion = $this->resolver->resolverSucursal($principal, $etiqueta);
                if (!$resolucion['cliente']) {
                    $stats['sucursales_no_encontradas'][] = [
                        'fila' => $filaNumero,
                        'codigo_excel' => $datos['codigo'],
                        'cliente' => $datos['cliente'],
                        'cuit' => $datos['cuit'],
                        'codigo_principal_bd' => trim($principal->cli_codigo),
                        'tipo' => $tipo,
                        'etiqueta_suc' => $etiqueta,
                        'emails' => implode(', ', $bloque['emails']),
                        'motivo' => $resolucion['ambiguo']
                            ? 'Varias sucursales en BD coinciden con la etiqueta SUC (revisar manualmente)'
                            : 'Ninguna sucursal en BD coincide con la etiqueta SUC del Excel',
                        'sucursales_en_bd' => $this->resolver->listarSucursalesReferencia($principal),
                    ];
                    continue;
                }
                $destino = $resolucion['cliente'];
            }

            foreach ($bloque['emails'] as $email) {
                $this->guardarContacto($stats, $destino, $email, $tipo, $dryRun);
            }
        }
    }

    private function guardarContacto(array &$stats, Clientes $destino, string $email, string $tipo, bool $dryRun): void
    {
        $cliCodigo = $destino->cli_codigo;

        $existe = ClienteContacto::query()
            ->where('cli_codigo', $cliCodigo)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->where('tipo', $tipo)
            ->exists();

        if ($existe) {
            $stats['omitidos_duplicado']++;

            return;
        }

        if ($dryRun) {
            $stats['insertados']++;

            return;
        }

        $nombre = Str::before($email, '@') ?: 'Contacto importado';

        ClienteContacto::create([
            'cli_codigo' => $cliCodigo,
            'nombre' => $nombre,
            'telefono' => null,
            'email' => $email,
            'tipo' => $tipo,
        ]);

        $stats['insertados']++;
    }

    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $row
     * @return ?array{codigo: string, cliente: string, cuit: string, mail_facturas: ?string, mail_cobranzas: ?string}
     */
    private function mapearFila($row): ?array
    {
        if ($row instanceof Collection) {
            $row = $row->values()->all();
        }

        if (!is_array($row)) {
            return null;
        }

        $valores = array_values($row);
        if (count($valores) < 4) {
            return null;
        }

        $codigo = trim((string) ($valores[0] ?? ''));
        $cliente = trim((string) ($valores[1] ?? ''));

        if ($codigo === '' && $cliente === '') {
            return null;
        }

        $primerCampo = mb_strtolower($codigo);
        if (in_array($primerCampo, ['cod cliente', 'codigo cliente', 'código cliente'], true)) {
            return null;
        }

        return [
            'codigo' => $codigo,
            'cliente' => $cliente,
            'cuit' => trim((string) ($valores[2] ?? '')),
            'mail_facturas' => isset($valores[3]) ? (string) $valores[3] : null,
            'mail_cobranzas' => isset($valores[4]) ? (string) $valores[4] : null,
        ];
    }
}
