<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Re-sincroniza la secuencia de id en cotio_items_matriz (PostgreSQL).
     * Necesario cuando hay inserciones manuales o imports que dejan la secuencia por detrás del MAX(id).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('cotio_items_matriz', 'id'),
                COALESCE((SELECT MAX(id) FROM cotio_items_matriz), 1)
            );
        ");
    }

    public function down(): void
    {
        // No se puede revertir de forma segura; la secuencia queda corregida.
    }
};
