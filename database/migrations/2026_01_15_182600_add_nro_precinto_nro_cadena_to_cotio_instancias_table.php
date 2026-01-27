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
            $table->string('nro_precinto', 100)->nullable()->after('longitud')->comment('Número de precinto de la muestra');
            $table->string('nro_cadena', 100)->nullable()->after('nro_precinto')->comment('Número de cadena de la muestra');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotio_instancias', function (Blueprint $table) {
            $table->dropColumn(['nro_precinto', 'nro_cadena']);
        });
    }
};
