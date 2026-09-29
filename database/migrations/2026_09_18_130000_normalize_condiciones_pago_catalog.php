<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Códigos legacy (pag / cotizaciones / clientes) → catálogo nuevo.
     *
     * @return array<string, string>
     */
    private function legacyToNewCode(): array
    {
        return [
            'ANTI' => 'SALDOANT',
            'CONTA' => 'CONTADO',
            'CONAD' => 'CONTADO',
            'CONEU' => 'CONTADO',
            'CCOCH' => 'CONTADO',
            'CCHD7' => 'CONTADO',
            'PCONT' => 'CONTADO',
            'PTRAN' => 'CONTADO',
            'PTRAP' => 'CONTADO',
            '1000' => 'CUOTAS',
            '12 CT' => 'CUOTAS',
            '3 CT' => 'CUOTAS',
            '6 CT' => 'CUOTAS',
            '4C' => 'CUOTAS',
            'CUOTAS' => 'CUOTAS',
            'CTE7' => 'FF7',
            'P7' => 'FF7',
            'CTE15' => 'FF15',
            'P15' => 'FF15',
            'CTE' => 'FF30',
            'P30' => 'FF30',
            'CTE1' => 'FF45',
            'P45' => 'FF45',
            'CTE60' => 'FF60',
            'P60' => 'FF60',
            'P75' => 'FF75',
            'CTE90' => 'FF90',
            'P90' => 'FF90',
            'P180' => 'FF90',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogo(): array
    {
        $base = [
            'pag_descuento1' => null,
            'pag_descuento2' => null,
            'pag_interes' => null,
            'pag_clienteproveedor' => 'C',
            'pag_estado' => true,
        ];

        return [
            array_merge($base, [
                'pag_codigo' => 'ANTICIPO',
                'pag_descripcion' => 'ANTICIPO',
                'pag_cuotas' => 1,
                'pag_dias' => 0,
                'pag_vencimiento' => false,
                'pag_anticipo' => true,
            ]),
            array_merge($base, [
                'pag_codigo' => 'SALDOANT',
                'pag_descripcion' => 'SALDO FINAL x ANTICIPO',
                'pag_cuotas' => 1,
                'pag_dias' => 0,
                'pag_vencimiento' => false,
                'pag_anticipo' => true,
            ]),
            array_merge($base, [
                'pag_codigo' => 'CONTADO',
                'pag_descripcion' => 'CONTADO',
                'pag_cuotas' => 1,
                'pag_dias' => 0,
                'pag_vencimiento' => false,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'CONTRAENTREGA',
                'pag_descripcion' => 'CONTRAENTREGA',
                'pag_cuotas' => 1,
                'pag_dias' => 0,
                'pag_vencimiento' => false,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'CUOTAS',
                'pag_descripcion' => 'CUOTAS',
                'pag_cuotas' => 1,
                'pag_dias' => 0,
                'pag_vencimiento' => false,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF7',
                'pag_descripcion' => '7 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 7,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF15',
                'pag_descripcion' => '15 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 15,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF30',
                'pag_descripcion' => '30 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 30,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF45',
                'pag_descripcion' => '45 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 45,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF60',
                'pag_descripcion' => '60 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 60,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF75',
                'pag_descripcion' => '75 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 75,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
            array_merge($base, [
                'pag_codigo' => 'FF90',
                'pag_descripcion' => '90 DIAS FECHA FACTURA',
                'pag_cuotas' => 1,
                'pag_dias' => 90,
                'pag_vencimiento' => true,
                'pag_anticipo' => false,
            ]),
        ];
    }

    private function dropForeignKeysToPag(): void
    {
        if (Schema::hasTable('cli')) {
            DB::statement('ALTER TABLE cli DROP CONSTRAINT IF EXISTS fk_cli_pag');
        }
        if (Schema::hasTable('comproc')) {
            DB::statement('ALTER TABLE comproc DROP CONSTRAINT IF EXISTS fk_comproc_pag');
        }
    }

    private function restoreForeignKeysToPag(): void
    {
        if (Schema::hasTable('cli')) {
            DB::statement('
                ALTER TABLE cli
                ADD CONSTRAINT fk_cli_pag
                FOREIGN KEY (cli_codigopag) REFERENCES pag(pag_codigo)
                ON UPDATE CASCADE ON DELETE SET NULL
            ');
        }
        if (Schema::hasTable('comproc')) {
            DB::statement('
                ALTER TABLE comproc
                ADD CONSTRAINT fk_comproc_pag
                FOREIGN KEY (comproc_codigopag) REFERENCES pag(pag_codigo)
                ON UPDATE CASCADE ON DELETE SET NULL
            ');
        }
    }

    private function widenPaymentCodeColumns(): void
    {
        DB::statement('ALTER TABLE pag ALTER COLUMN pag_codigo TYPE varchar(20)');
        DB::statement('ALTER TABLE pag ALTER COLUMN pag_descripcion TYPE varchar(40)');

        if (Schema::hasColumn('cli', 'cli_codigopag')) {
            DB::statement('ALTER TABLE cli ALTER COLUMN cli_codigopag TYPE varchar(20)');
        }

        if (Schema::hasColumn('coti', 'coti_cond_pago')) {
            DB::statement('ALTER TABLE coti ALTER COLUMN coti_cond_pago TYPE varchar(20)');
        }

        if (Schema::hasTable('cliente_razones_sociales_facturacion')
            && Schema::hasColumn('cliente_razones_sociales_facturacion', 'condicion_pago')) {
            DB::statement('ALTER TABLE cliente_razones_sociales_facturacion ALTER COLUMN condicion_pago TYPE varchar(20)');
        }

        if (Schema::hasColumn('comproc', 'comproc_codigopag')) {
            DB::statement('ALTER TABLE comproc ALTER COLUMN comproc_codigopag TYPE varchar(20)');
        }
    }

    /**
     * @param  iterable<string, string>  $map
     */
    private function remapColumn(string $table, string $column, iterable $map): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach ($map as $old => $new) {
            if ($old === $new) {
                continue;
            }
            DB::table($table)
                ->whereRaw("LTRIM(RTRIM({$column})) = ?", [$old])
                ->update([$column => $new]);
        }
    }

    /**
     * @param  list<object>  $pagRows
     * @return array<string, string>
     */
    private function buildRemapMap(array $pagRows): array
    {
        $map = $this->legacyToNewCode();

        foreach ($pagRows as $row) {
            $old = trim((string) ($row->pag_codigo ?? ''));
            if ($old === '') {
                continue;
            }

            if (isset($map[$old]) || in_array($old, $this->allowedCodes(), true)) {
                continue;
            }

            $guessed = $this->guessNewCodeFromPagRow($row);
            if ($guessed !== null) {
                $map[$old] = $guessed;
            }
        }

        return $map;
    }

    private function guessNewCodeFromPagRow(object $row): ?string
    {
        $code = strtoupper(trim((string) ($row->pag_codigo ?? '')));
        $desc = strtoupper(trim((string) ($row->pag_descripcion ?? '')));
        $blob = $code . ' ' . $desc;

        if (str_contains($blob, 'CONTRAENTREGA') || str_contains($blob, 'CONTRA ENTREGA')) {
            return 'CONTRAENTREGA';
        }

        if (str_contains($blob, 'CUOTA') || preg_match('/\b\d+\s*C(T|UOTAS?)\b/', $blob)) {
            return 'CUOTAS';
        }

        if (str_contains($blob, 'ANTICIPO') && (str_contains($blob, 'SALDO') || str_contains($blob, '50%'))) {
            return 'SALDOANT';
        }

        if (str_contains($blob, 'ANTICIPO')) {
            return 'ANTICIPO';
        }

        if (str_contains($blob, 'CONTADO') || str_contains($blob, 'TRANSFER') || str_contains($blob, 'CHEQUE') || str_contains($blob, 'EFECTIVO')) {
            return 'CONTADO';
        }

        if (preg_match('/\b180\b/', $blob)) {
            return 'FF90';
        }

        foreach ([90 => 'FF90', 75 => 'FF75', 60 => 'FF60', 45 => 'FF45', 30 => 'FF30', 15 => 'FF15', 7 => 'FF7'] as $days => $target) {
            if (preg_match('/\b' . $days . '\b/', $blob)) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @param  list<object>  $pagRows
     */
    private function remapOrphansUsingPagSnapshot(string $table, string $column, array $pagRows): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $allowed = $this->allowedCodes();
        $byCode = [];
        foreach ($pagRows as $row) {
            $byCode[trim((string) ($row->pag_codigo ?? ''))] = $row;
        }

        $orphans = DB::table($table)
            ->whereNotNull($column)
            ->whereRaw("LTRIM(RTRIM({$column})) <> ''")
            ->selectRaw("DISTINCT LTRIM(RTRIM({$column})) as code")
            ->pluck('code');

        foreach ($orphans as $code) {
            $code = trim((string) $code);
            if ($code === '' || in_array($code, $allowed, true)) {
                continue;
            }

            $row = $byCode[$code] ?? null;
            $new = $row ? $this->guessNewCodeFromPagRow($row) : null;
            if ($new !== null) {
                DB::table($table)
                    ->whereRaw("LTRIM(RTRIM({$column})) = ?", [$code])
                    ->update([$column => $new]);
            }
        }
    }

    /** @return list<string> */
    private function allowedCodes(): array
    {
        return array_map(static fn (array $row) => $row['pag_codigo'], $this->catalogo());
    }

    private function nullifyOrphanReferences(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $allowed = $this->allowedCodes();
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));

        DB::update("
            UPDATE {$table}
            SET {$column} = NULL
            WHERE {$column} IS NOT NULL
              AND LTRIM(RTRIM({$column})) <> ''
              AND LTRIM(RTRIM({$column})) NOT IN ({$placeholders})
        ", $allowed);
    }

    public function up(): void
    {
        $pagSnapshot = DB::table('pag')->get()->all();
        $remapMap = $this->buildRemapMap($pagSnapshot);

        $this->dropForeignKeysToPag();
        $this->widenPaymentCodeColumns();

        foreach (['cli' => 'cli_codigopag', 'coti' => 'coti_cond_pago', 'cliente_razones_sociales_facturacion' => 'condicion_pago', 'comproc' => 'comproc_codigopag'] as $table => $column) {
            $this->remapColumn($table, $column, $remapMap);
            $this->remapOrphansUsingPagSnapshot($table, $column, $pagSnapshot);
        }

        $this->nullifyOrphanReferences('cli', 'cli_codigopag');
        $this->nullifyOrphanReferences('cliente_razones_sociales_facturacion', 'condicion_pago');
        $this->nullifyOrphanReferences('comproc', 'comproc_codigopag');
        // coti: sin FK; no anular condiciones para no perder historial en cotizaciones.

        DB::table('pag')->delete();

        foreach ($this->catalogo() as $row) {
            DB::table('pag')->insert($row);
        }

        $this->restoreForeignKeysToPag();
    }

    public function down(): void
    {
        // Catálogo anterior heterogéneo; no se restaura automáticamente.
    }
};
