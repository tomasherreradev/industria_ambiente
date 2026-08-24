<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->text('observaciones_muestreo_coord')->nullable();
            $table->text('observaciones_muestreo_muestreador')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn(['observaciones_muestreo_coord', 'observaciones_muestreo_muestreador']);
        });
    }
};
