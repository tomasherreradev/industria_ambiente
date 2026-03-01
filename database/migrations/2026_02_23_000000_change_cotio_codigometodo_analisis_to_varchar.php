<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * cotio.cotio_codigometodo_analisis era char(15); al guardar un valor como '00303'
     * se rellenaba con espacios y la FK a metodos_analisis.codigo fallaba.
     * Cambiar a varchar(15) para que el valor guardado coincida con metodos_analisis.codigo.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE cotio ALTER COLUMN cotio_codigometodo_analisis TYPE varchar(15) USING trim(cotio_codigometodo_analisis)::varchar(15)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE cotio ALTER COLUMN cotio_codigometodo_analisis TYPE char(15) USING (cotio_codigometodo_analisis::char(15))');
    }
};
