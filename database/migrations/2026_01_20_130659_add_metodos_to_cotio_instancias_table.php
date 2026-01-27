<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->char('cotio_codigometodo', 15)->nullable()->after('cotio_descripcion')->comment('Código del método de muestreo');
            $table->char('cotio_codigometodo_analisis', 15)->nullable()->after('cotio_codigometodo')->comment('Código del método de análisis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn(['cotio_codigometodo', 'cotio_codigometodo_analisis']);
        });
    }
};
