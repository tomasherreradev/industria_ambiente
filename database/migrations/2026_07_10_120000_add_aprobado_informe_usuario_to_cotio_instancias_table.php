<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->string('aprobado_informe_usuario')->nullable()->after('fecha_aprobacion_informe');
        });
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn('aprobado_informe_usuario');
        });
    }
};
