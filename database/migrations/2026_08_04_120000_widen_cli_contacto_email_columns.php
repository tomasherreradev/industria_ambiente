<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ampliar columnas legacy de contacto/email en cli (antes character(30)).
     * Alineado con cliente_contactos: email 120, nombre 120.
     */
    public function up(): void
    {
        $columns = [
            'cli_email' => 120,
            'cli_email2' => 120,
            'cli_email3' => 120,
            'cli_contacto' => 120,
            'cli_contacto1' => 120,
            'cli_contacto2' => 120,
            'cli_contacto3' => 120,
            'cli_telefono' => 50,
        ];

        foreach ($columns as $column => $length) {
            if (!$this->columnExists($column)) {
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
            'cli_email' => 30,
            'cli_email2' => 30,
            'cli_email3' => 30,
            'cli_contacto' => 30,
            'cli_contacto1' => 30,
            'cli_contacto2' => 30,
            'cli_contacto3' => 30,
            'cli_telefono' => 30,
        ];

        foreach ($revert as $column => $length) {
            if (!$this->columnExists($column)) {
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
