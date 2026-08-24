<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza matriz_codigo en cotio_items_matriz (legacy con espacios de relleno).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM cotio_items_matriz padded
            WHERE padded.matriz_codigo != TRIM(padded.matriz_codigo)
              AND EXISTS (
                  SELECT 1
                  FROM cotio_items_matriz clean
                  WHERE clean.cotio_item_id = padded.cotio_item_id
                    AND clean.matriz_codigo = TRIM(padded.matriz_codigo)
              )
        SQL);

        DB::table('cotio_items_matriz')
            ->whereRaw('matriz_codigo != TRIM(matriz_codigo)')
            ->update([
                'matriz_codigo' => DB::raw('TRIM(matriz_codigo)'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No reversible: los espacios de relleno no se pueden reconstruir de forma fiable.
    }
};
