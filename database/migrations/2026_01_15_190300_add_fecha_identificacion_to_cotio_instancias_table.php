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
            $table->timestamp('fecha_identificacion')->nullable()->after('cotio_identificacion')->comment('Fecha y hora en que se cargó o editó la identificación de la muestra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn('fecha_identificacion');
        });
    }
};
