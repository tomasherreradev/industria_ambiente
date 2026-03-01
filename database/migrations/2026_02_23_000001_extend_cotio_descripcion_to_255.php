<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Amplía cotio_descripcion de varchar(60) a varchar(255) para evitar
     * "String data, right truncated" con descripciones largas de componentes.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cotio ALTER COLUMN cotio_descripcion TYPE VARCHAR(255)');
        } else {
            DB::statement('ALTER TABLE cotio MODIFY cotio_descripcion VARCHAR(255)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE cotio ALTER COLUMN cotio_descripcion TYPE VARCHAR(60)');
        } else {
            DB::statement('ALTER TABLE cotio MODIFY cotio_descripcion VARCHAR(60)');
        }
    }
};
