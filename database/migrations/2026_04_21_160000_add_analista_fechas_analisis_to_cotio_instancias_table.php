<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->date('analista_fecha_inicio')->nullable()->after('protocolo_informe_json');
            $table->date('analista_fecha_fin')->nullable()->after('analista_fecha_inicio');
        });
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn(['analista_fecha_inicio', 'analista_fecha_fin']);
        });
    }
};
