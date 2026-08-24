<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ampliar columnas legacy de identificación/domicilio en cli (antes character(60)).
     * Alineado con validación del formulario (max:255).
     */
    public function up(): void
    {
        $columns = [
            'cli_razonsocial' => 255,
            'cli_fantasia' => 255,
            'cli_direccion' => 255,
        ];

        foreach ($columns as $column => $length) {
            if (! $this->columnExists($column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE cli ALTER COLUMN %s TYPE varchar(%d) USING trim(%s)::varchar(%d)',
                $column,
                $length,
                $column,
                $length
            ));
        }
    }

    public function down(): void
    {
        $revert = [
            'cli_razonsocial' => 60,
            'cli_fantasia' => 60,
            'cli_direccion' => 60,
        ];

        foreach ($revert as $column => $length) {
            if (! $this->columnExists($column)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE cli ALTER COLUMN %s TYPE char(%d) USING (left(trim(%s), %d)::char(%d))',
                $column,
                $length,
                $column,
                $length,
                $length
            ));
        }
    }

    private function columnExists(string $column): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ? LIMIT 1',
            ['cli', $column]
        );

        return $row !== null;
    }
};
